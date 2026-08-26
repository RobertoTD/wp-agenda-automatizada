<?php
/**
 * Upload Expediente Adjunto For Expediente Use Case (B3b1 / P3).
 *
 * Writer canónico por expediente_id.
 * Gate OFF: general → attachments_unavailable; relacionado → pipeline v1 legacy.
 * Gate ON: authorize/transfer/finalize expediente_v2 + locks aggregate→quota.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('AA_Expediente_Attachments_V2_Enablement')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-attachments-v2-enablement.php';
}
if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}
if (!class_exists('ExpedienteRegistrosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
}
if (!class_exists('ExpedienteAdjuntosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteAdjuntosRepository.php';
}
if (!class_exists('ExpedienteAdjuntoJpegValidator')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoJpegValidator.php';
}
if (!class_exists('ExpedienteAdjuntoUploadTransfer')) {
    require_once __DIR__ . '/ExpedienteAdjuntoUploadTransfer.php';
}
if (!class_exists('AA_Expediente_Adjunto_Variant_Generator')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-adjunto-variant-generator.php';
}
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('AA_Expediente_Attachment_Signed_Uploader')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachment-signed-uploader.php';
}
if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}
if (!class_exists('ExpedienteAdjuntoPublicDto')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoPublicDto.php';
}

final class UploadExpedienteAdjuntoForExpedienteUseCase {

    private const UUID_V4_RE = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** @var object|null UploadExpedienteRegistroAdjuntoUseCase (lazy require). */
    private $legacy_upload;

    /** @var ExpedienteAdjuntoJpegValidator */
    private $validator;

    /** @var object */
    private $transfer;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    /** @var object|null */
    private $cleanup_client;

    /**
     * @param object|null $legacy_upload Solo path gate OFF relacionado.
     * @param ExpedienteAdjuntoJpegValidator|null $validator
     * @param object|null $transfer
     * @param AA_Expediente_Aggregate_Lock|null $lock
     * @param object|null $cleanup_client
     */
    public function __construct(
        $legacy_upload = null,
        ?ExpedienteAdjuntoJpegValidator $validator = null,
        $transfer = null,
        ?AA_Expediente_Aggregate_Lock $lock = null,
        $cleanup_client = null
    ) {
        $this->legacy_upload = is_object($legacy_upload) ? $legacy_upload : null;
        $this->validator = $validator ?: new ExpedienteAdjuntoJpegValidator();
        $this->transfer = $transfer ?: new ExpedienteAdjuntoUploadTransfer(
            new AA_Expediente_Adjunto_Variant_Generator(),
            new AA_Expediente_Attachments_Backend_Client(),
            new AA_Expediente_Attachment_Signed_Uploader()
        );
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
        $this->cleanup_client = is_object($cleanup_client) ? $cleanup_client : null;
    }

    /**
     * @param array{
     *   expediente_id?:mixed,
     *   record_id?:mixed,
     *   upload_operation_id?:mixed,
     *   file?:mixed
     * } $input
     * @return array{success:true,data:array{record_id:int,adjunto:array<string,mixed>}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        $record_id = AA_Expediente_Id_Policy::normalize($input['record_id'] ?? null);
        if ($expediente_id === null || $record_id === null) {
            return $this->fail('invalid_id', 'Identificador no válido.');
        }

        $operation_id = $input['upload_operation_id'] ?? '';
        if (!is_string($operation_id) && !is_int($operation_id) && !is_float($operation_id)) {
            $operation_id = '';
        } else {
            $operation_id = strtolower(trim((string) $operation_id));
        }

        $file = isset($input['file']) && is_array($input['file']) ? $input['file'] : [];

        $exists = ExpedientesRepository::exists_by_id($expediente_id);
        if ($exists === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }
        if ($exists === false) {
            return $this->fail('not_found', 'Expediente no encontrado.');
        }

        $owner = ExpedientesRepository::find_owner_context_by_id($expediente_id);
        if ($owner === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }

        $parent_client_id = $this->normalize_nullable_client($owner['client_id'] ?? null);

        if (!AA_Expediente_Attachments_V2_Enablement::is_enabled() && $parent_client_id === null) {
            return $this->fail('attachments_unavailable', 'Este expediente no admite adjuntos.');
        }

        $record = ExpedienteRegistrosRepository::find_by_id_for_expediente($record_id, $expediente_id);
        if ($record === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
        }
        if ($record === false) {
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $coherence = $this->assert_parent_record_coherence($parent_client_id, $record);
        if ($coherence !== null) {
            return $coherence;
        }

        if (!AA_Expediente_Attachments_V2_Enablement::is_enabled()) {
            // Relacionado: parent_client_id ya validado no-null arriba.
            return $this->delegate_legacy_v1((int) $parent_client_id, $record_id, $operation_id, $file);
        }

        return $this->execute_v2(
            $expediente_id,
            $record_id,
            $parent_client_id,
            $operation_id,
            $file
        );
    }

    /**
     * @param array<string,mixed> $file
     * @return array{success:true,data:array{record_id:int,adjunto:array<string,mixed>}}|array{success:false,error:array{code:string,message:string}}
     */
    private function delegate_legacy_v1(int $parent_client_id, int $record_id, string $operation_id, array $file): array {
        if (!class_exists('UploadExpedienteRegistroAdjuntoUseCase')) {
            require_once __DIR__ . '/UploadExpedienteRegistroAdjuntoUseCase.php';
        }
        $legacy = $this->legacy_upload ?: new UploadExpedienteRegistroAdjuntoUseCase();
        $uploaded = $legacy->execute([
            'client_id' => $parent_client_id,
            'record_id' => $record_id,
            'upload_operation_id' => $operation_id,
            'file' => $file,
            // Evita reentrada canónica si el registro está bridged y el gate cambia.
            '_aa_skip_canonical_bridge' => true,
        ]);

        if (empty($uploaded['ok'])) {
            return $this->fail(
                (string) ($uploaded['code'] ?? 'attach_failed'),
                (string) ($uploaded['message'] ?? 'No se pudo subir la imagen.')
            );
        }

        $attachment = is_array($uploaded['attachment'] ?? null) ? $uploaded['attachment'] : null;
        $dto = ExpedienteAdjuntoPublicDto::from($attachment);
        if ($dto === null) {
            return $this->fail('persist_failed', 'No se pudo guardar el adjunto.');
        }

        return $this->ok([
            'record_id' => $record_id,
            'adjunto' => $dto,
        ]);
    }

    /**
     * @param array<string,mixed> $file
     * @return array{success:true,data:array{record_id:int,adjunto:array<string,mixed>}}|array{success:false,error:array{code:string,message:string}}
     */
    private function execute_v2(
        int $expediente_id,
        int $record_id,
        ?int $parent_client_id,
        string $operation_id,
        array $file
    ): array {
        $tmp_to_clean = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';

        try {
            if ($operation_id === '' || !preg_match(self::UUID_V4_RE, $operation_id)) {
                return $this->fail('invalid_operation_id', 'Identificador de operación no válido.');
            }

            $validated = $this->validator->validate($file);
            if (empty($validated['ok'])) {
                return $this->fail(
                    (string) ($validated['code'] ?? 'invalid_file'),
                    (string) ($validated['message'] ?? 'Archivo no válido.')
                );
            }

            $tmp_to_clean = (string) $validated['tmp_name'];
            $mime = (string) $validated['mime_type'];
            $byte_size = (int) $validated['byte_size'];
            $width = (int) $validated['width'];
            $height = (int) $validated['height'];

            $scope = $this->aggregate_scope($expediente_id, $parent_client_id);
            $aggregate_lease = $this->lock->acquire(
                $scope['kind'],
                $scope['id'],
                AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
            );
            if (is_wp_error($aggregate_lease)) {
                return $this->fail_from_lock($aggregate_lease);
            }

            $quota_lease = null;
            $storage_path_for_cleanup = '';

            try {
                $recheck = $this->revalidate_parent_record($expediente_id, $record_id, $parent_client_id);
                if ($recheck !== null) {
                    return $recheck;
                }

                $held = $this->lock->assert_held($aggregate_lease);
                if (is_wp_error($held)) {
                    return $this->fail_from_lock($held);
                }

                $quota_lease = $this->lock->acquire(
                    AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA,
                    AA_Expediente_Aggregate_Lock::STORAGE_QUOTA_SCOPE_ID,
                    AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
                );
                if (is_wp_error($quota_lease)) {
                    return $this->fail_from_lock($quota_lease);
                }

                $used_bytes = ExpedienteAdjuntosRepository::sum_byte_size_total();
                if ($used_bytes === null) {
                    return $this->fail(
                        'storage_usage_unavailable',
                        'No se pudo verificar el espacio disponible.'
                    );
                }

                $held_quota = $this->lock->assert_held($quota_lease);
                if (is_wp_error($held_quota)) {
                    return $this->fail_from_lock($held_quota);
                }

                $transferred = $this->transfer->transfer([
                    'source_path' => $tmp_to_clean,
                    'mime_type' => $mime,
                    'byte_size' => $byte_size,
                    'width' => $width,
                    'height' => $height,
                    'upload_operation_id' => $operation_id,
                    'used_bytes' => $used_bytes,
                    'identity' => [
                        'contract' => ExpedienteAdjuntoVariants::CONTRACT_EXPEDIENTE_V2,
                        'record_id' => $record_id,
                        'expediente_id' => $expediente_id,
                        'client_id' => null,
                    ],
                ]);

                if (empty($transferred['ok'])) {
                    $code = (string) ($transferred['code'] ?? 'transfer_failed');
                    return $this->fail($code, $this->transfer_failure_message($code, $transferred));
                }

                $storage_path = (string) ($transferred['storage_path'] ?? '');
                $storage_path_for_cleanup = $storage_path;
                /** @var array<string,mixed> $fin */
                $fin = isset($transferred['finalize']) && is_array($transferred['finalize'])
                    ? $transferred['finalize']
                    : [];

                if (!$this->finalize_matches_v2($fin, [
                    'expediente_id' => $expediente_id,
                    'record_id' => $record_id,
                    'upload_operation_id' => $operation_id,
                    'storage_path' => $storage_path,
                    'mime_type' => $mime,
                    'byte_size' => $byte_size,
                    'width' => $width,
                    'height' => $height,
                ])) {
                    return $this->fail('finalize_mismatch', 'La confirmación no coincide con los datos esperados.');
                }

                $held_agg_after = $this->lock->assert_held($aggregate_lease);
                if (is_wp_error($held_agg_after)) {
                    return $this->fail_after_storage_coordination_loss(
                        $storage_path_for_cleanup,
                        $operation_id,
                        $held_agg_after
                    );
                }

                $held_quota_after = $this->lock->assert_held($quota_lease);
                if (is_wp_error($held_quota_after)) {
                    return $this->fail_after_storage_coordination_loss(
                        $storage_path_for_cleanup,
                        $operation_id,
                        $held_quota_after
                    );
                }

                $recheck_final = $this->revalidate_parent_record($expediente_id, $record_id, $parent_client_id);
                if ($recheck_final !== null) {
                    return $this->fail_after_storage_coordination_loss(
                        $storage_path_for_cleanup,
                        $operation_id,
                        new WP_Error(
                            AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_LOST,
                            'Se perdió la coordinación de la operación.'
                        )
                    );
                }

                $inserted = ExpedienteAdjuntosRepository::insert_finalized([
                    'record_id' => $record_id,
                    'client_id' => $parent_client_id,
                    'upload_operation_id' => $operation_id,
                    'storage_path' => (string) $fin['storage_path'],
                    'mime_type' => (string) $fin['mime_type'],
                    'byte_size' => (int) $fin['byte_size'],
                    'width' => (int) $fin['width'],
                    'height' => (int) $fin['height'],
                ]);

                if (is_wp_error($inserted)) {
                    $code = $inserted->get_error_code();
                    return $this->fail(
                        is_string($code) && $code !== '' ? $code : 'persist_failed',
                        'No se pudo guardar el adjunto.'
                    );
                }

                $dto = ExpedienteAdjuntoPublicDto::from([
                    'id' => (int) $inserted['id'],
                    'record_id' => (int) $inserted['record_id'],
                    'client_id' => $inserted['client_id'] ?? null,
                    'upload_operation_id' => (string) $inserted['upload_operation_id'],
                    'storage_path' => (string) $inserted['storage_path'],
                    'mime_type' => (string) $inserted['mime_type'],
                    'byte_size' => (int) $inserted['byte_size'],
                    'width' => (int) $inserted['width'],
                    'height' => (int) $inserted['height'],
                    'created_at' => (string) $inserted['created_at'],
                ]);
                if ($dto === null) {
                    return $this->fail('persist_failed', 'No se pudo guardar el adjunto.');
                }

                return $this->ok([
                    'record_id' => $record_id,
                    'adjunto' => $dto,
                ]);
            } finally {
                if ($quota_lease !== null && !is_wp_error($quota_lease)) {
                    $this->lock->release($quota_lease);
                }
                $this->lock->release($aggregate_lease);
            }
        } finally {
            $this->cleanup_tmp($tmp_to_clean);
        }
    }

    /**
     * @return array{kind:string,id:int}
     */
    private function aggregate_scope(int $expediente_id, ?int $parent_client_id): array {
        if ($parent_client_id !== null) {
            return [
                'kind' => AA_Expediente_Aggregate_Lock::SCOPE_CLIENT,
                'id' => $parent_client_id,
            ];
        }

        return [
            'kind' => AA_Expediente_Aggregate_Lock::SCOPE_EXPEDIENTE,
            'id' => $expediente_id,
        ];
    }

    /**
     * @param array<string,mixed> $record
     * @return array{success:false,error:array{code:string,message:string}}|null
     */
    private function assert_parent_record_coherence(?int $parent_client_id, array $record): ?array {
        $record_client = $this->normalize_nullable_client($record['client_id'] ?? null);

        if ($parent_client_id === null) {
            if ($record_client !== null) {
                error_log('[UploadExpedienteAdjuntoForExpedienteUseCase] record owner mismatch');
                return $this->fail('not_found', 'Registro no encontrado.');
            }
            return null;
        }

        if ($record_client === null || $record_client !== $parent_client_id) {
            error_log('[UploadExpedienteAdjuntoForExpedienteUseCase] record owner mismatch');
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        return null;
    }

    /**
     * @return array{success:false,error:array{code:string,message:string}}|null
     */
    private function revalidate_parent_record(int $expediente_id, int $record_id, ?int $expected_client_id): ?array {
        $exists = ExpedientesRepository::exists_by_id($expediente_id);
        if ($exists === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }
        if ($exists === false) {
            return $this->fail('not_found', 'Expediente no encontrado.');
        }

        $owner = ExpedientesRepository::find_owner_context_by_id($expediente_id);
        if ($owner === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }

        $parent_client = $this->normalize_nullable_client($owner['client_id'] ?? null);
        if ($parent_client !== $expected_client_id) {
            error_log('[UploadExpedienteAdjuntoForExpedienteUseCase] parent client snapshot changed');
            return $this->fail('not_found', 'Expediente no encontrado.');
        }

        $record = ExpedienteRegistrosRepository::find_by_id_for_expediente($record_id, $expediente_id);
        if ($record === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
        }
        if ($record === false) {
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        return $this->assert_parent_record_coherence($expected_client_id, $record);
    }

    /**
     * @param mixed $raw
     */
    private function normalize_nullable_client($raw): ?int {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_int($raw) && !(is_string($raw) && ctype_digit($raw))) {
            return null;
        }
        $id = (int) $raw;
        return $id >= 1 ? $id : null;
    }

    /**
     * @param array<string,mixed> $fin
     * @param array{
     *   expediente_id:int,
     *   record_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int
     * } $expected
     */
    private function finalize_matches_v2(array $fin, array $expected): bool {
        $installation_id = trim((string) ($fin['installation_id'] ?? ''));
        if ($installation_id === '') {
            return false;
        }

        if ((string) ($fin['upload_operation_id'] ?? '') !== $expected['upload_operation_id']) {
            return false;
        }
        if ((string) ($fin['storage_path'] ?? '') !== $expected['storage_path']) {
            return false;
        }
        if ((string) ($fin['mime_type'] ?? '') !== $expected['mime_type']) {
            return false;
        }
        if ((int) ($fin['byte_size'] ?? -1) !== $expected['byte_size']) {
            return false;
        }
        if ((int) ($fin['width'] ?? -1) !== $expected['width']) {
            return false;
        }
        if ((int) ($fin['height'] ?? -1) !== $expected['height']) {
            return false;
        }

        $parsed = ExpedienteAdjuntoVariants::parse_original_path((string) $fin['storage_path']);
        if ($parsed === null) {
            return false;
        }
        if ((string) ($parsed['contract'] ?? '') !== ExpedienteAdjuntoVariants::CONTRACT_EXPEDIENTE_V2) {
            return false;
        }
        if ((int) ($parsed['expediente_id'] ?? 0) !== $expected['expediente_id']) {
            return false;
        }
        if ((int) ($parsed['record_id'] ?? 0) !== $expected['record_id']) {
            return false;
        }
        if (strtolower((string) ($parsed['operation_id'] ?? '')) !== $expected['upload_operation_id']) {
            return false;
        }

        return true;
    }

    /**
     * @param WP_Error $lock_error
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail_after_storage_coordination_loss(string $storage_path, string $operation_id, $lock_error): array {
        $existing = ExpedienteAdjuntosRepository::find_by_upload_operation_id($operation_id);
        if (is_array($existing) && (string) ($existing['storage_path'] ?? '') === $storage_path) {
            $dto = ExpedienteAdjuntoPublicDto::from($existing);
            if ($dto !== null) {
                return $this->ok([
                    'record_id' => (int) ($existing['record_id'] ?? 0),
                    'adjunto' => $dto,
                ]);
            }
        }

        if ($storage_path !== '' && $existing === null) {
            $cleaned = $this->compensate_storage($storage_path);
            if ($cleaned !== true) {
                return $this->fail(
                    'storage_cleanup_failed',
                    'No se pudo completar la limpieza tras perder la coordinación.'
                );
            }
        }

        return $this->fail_from_lock($lock_error);
    }

    private function compensate_storage(string $storage_path): bool {
        $client = $this->cleanup_client;
        if (!is_object($client) || !method_exists($client, 'delete_object')) {
            $client = new AA_Expediente_Attachments_Backend_Client();
        }

        if (!is_object($client) || !method_exists($client, 'delete_object')) {
            error_log('[UploadExpedienteAdjuntoForExpedienteUseCase] storage cleanup unavailable');
            return false;
        }

        $deleted = $client->delete_object($storage_path);
        if (empty($deleted['ok'])) {
            error_log('[UploadExpedienteAdjuntoForExpedienteUseCase] storage cleanup failed');
            return false;
        }

        $status = (string) ($deleted['result']['status'] ?? '');
        if ($status !== 'deleted' && $status !== 'already_absent') {
            error_log('[UploadExpedienteAdjuntoForExpedienteUseCase] storage cleanup unexpected');
            return false;
        }

        return true;
    }

    private function cleanup_tmp(string $tmp): void {
        if ($tmp === '' || !is_string($tmp)) {
            return;
        }
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }

    /**
     * @param array<string,mixed> $transferred
     */
    private function transfer_failure_message(string $code, array $transferred): string {
        switch ($code) {
            case 'storage_not_included':
                return 'Tu plan actual no incluye almacenamiento de imágenes en el servidor.';
            case 'storage_quota_exceeded':
                return 'No queda espacio de almacenamiento. Elimina alguna imagen para liberar espacio.';
            default:
                $message = (string) ($transferred['message'] ?? '');
                return $message !== '' ? $message : 'No se pudo subir la imagen.';
        }
    }

    /**
     * @param WP_Error $error
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail_from_lock($error): array {
        $code = (string) $error->get_error_code();
        $message = (string) $error->get_error_message();
        if ($code === '') {
            $code = AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_FAILED;
        }
        if ($message === '') {
            $message = 'No se pudo coordinar la operación.';
        }

        return $this->fail($code, $message);
    }

    /**
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array{record_id:int,adjunto:array<string,mixed>} $data
     * @return array{success:true,data:array{record_id:int,adjunto:array<string,mixed>}}
     */
    private function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
