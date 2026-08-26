<?php
/**
 * Upload Expediente Registro Adjunto Use Case (MC4b / P3).
 *
 * Orquesta: validación JPEG → locks aggregate→quota → transfer → insert_finalized.
 * Nunca inserta metadatos antes de un finalize coincidente.
 *
 * P3:
 * - Bridged + gate v2 → delega al writer canónico (sin locks locales).
 * - Orphan / gate OFF → client_v1 + site quota lock.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteRegistrosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
}
if (!class_exists('ExpedienteAdjuntosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteAdjuntosRepository.php';
}
if (!class_exists('ClientsRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}
if (!class_exists('ExpedienteAdjuntoJpegValidator')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoJpegValidator.php';
}
if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}
if (!class_exists('AA_Expediente_Attachments_V2_Enablement')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-attachments-v2-enablement.php';
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

final class UploadExpedienteRegistroAdjuntoUseCase {

    private const UUID_V4_RE = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** @var ExpedienteAdjuntoJpegValidator */
    private $validator;

    /** @var object */
    private $transfer;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    /**
     * Cliente Storage solo para compensación post-PUT si se pierde el lock.
     *
     * @var object|null
     */
    private $cleanup_client;

    /** @var object|null Writer canónico (UploadExpedienteAdjuntoForExpedienteUseCase). */
    private $canonical_upload;

    /**
     * @param ExpedienteAdjuntoJpegValidator|null $validator
     * @param object|null $transfer ExpedienteAdjuntoUploadTransfer o doble de prueba
     * @param AA_Expediente_Aggregate_Lock|null $lock
     * @param object|null $cleanup_client Cliente con delete_object() para compensación
     * @param object|null $canonical_upload Solo para tests / bridge v2
     */
    public function __construct(
        ?ExpedienteAdjuntoJpegValidator $validator = null,
        $transfer = null,
        ?AA_Expediente_Aggregate_Lock $lock = null,
        $cleanup_client = null,
        $canonical_upload = null
    ) {
        $this->validator = $validator ?: new ExpedienteAdjuntoJpegValidator();
        $this->transfer = $transfer ?: new ExpedienteAdjuntoUploadTransfer(
            new AA_Expediente_Adjunto_Variant_Generator(),
            new AA_Expediente_Attachments_Backend_Client(),
            new AA_Expediente_Attachment_Signed_Uploader()
        );
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
        $this->cleanup_client = is_object($cleanup_client) ? $cleanup_client : null;
        $this->canonical_upload = is_object($canonical_upload) ? $canonical_upload : null;
    }

    /**
     * @param array{
     *   client_id:int,
     *   record_id:int,
     *   upload_operation_id:string,
     *   file:array<string,mixed>,
     *   _aa_skip_canonical_bridge?:bool
     * } $input
     * @return array{ok:true,attachment:array<string,mixed>}|array{ok:false,code:string,message:string}
     */
    public function execute(array $input): array {
        $client_id = (int) ($input['client_id'] ?? 0);
        $record_id = (int) ($input['record_id'] ?? 0);
        $operation_id = strtolower(trim((string) ($input['upload_operation_id'] ?? '')));
        $file = isset($input['file']) && is_array($input['file']) ? $input['file'] : [];
        $skip_bridge = !empty($input['_aa_skip_canonical_bridge']);

        $tmp_to_clean = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';

        try {
            if ($client_id < 1 || $record_id < 1) {
                return $this->fail('invalid_context', 'Cliente o registro no válido.');
            }

            if ($operation_id === '' || !preg_match(self::UUID_V4_RE, $operation_id)) {
                return $this->fail('invalid_operation_id', 'Identificador de operación no válido.');
            }

            if (ClientsRepository::find_by_id($client_id) === null) {
                return $this->fail('client_not_found', 'Cliente no encontrado.');
            }

            $record = ExpedienteRegistrosRepository::find_by_id_for_client($record_id, $client_id);
            if ($record === null) {
                return $this->fail('record_not_found', 'Registro no encontrado.');
            }

            $bridged_expediente_id = $this->bridged_expediente_id($record);
            if (
                !$skip_bridge
                && $bridged_expediente_id !== null
                && AA_Expediente_Attachments_V2_Enablement::is_enabled()
            ) {
                return $this->delegate_canonical_v2($bridged_expediente_id, $record_id, $client_id, $operation_id, $file);
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

            $aggregate_lease = $this->lock->acquire(
                AA_Expediente_Aggregate_Lock::SCOPE_CLIENT,
                $client_id,
                AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
            );
            if (is_wp_error($aggregate_lease)) {
                return $this->fail_from_lock($aggregate_lease);
            }

            $quota_lease = null;
            $storage_path_for_cleanup = '';

            try {
                if (ClientsRepository::find_by_id($client_id) === null) {
                    return $this->fail('client_not_found', 'Cliente no encontrado.');
                }

                $record_after = ExpedienteRegistrosRepository::find_by_id_for_client($record_id, $client_id);
                if ($record_after === null) {
                    return $this->fail('record_not_found', 'Registro no encontrado.');
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
                        'contract' => ExpedienteAdjuntoVariants::CONTRACT_CLIENT_V1,
                        'record_id' => $record_id,
                        'client_id' => $client_id,
                        'expediente_id' => null,
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

                if (!$this->finalize_matches_expectation($fin, [
                    'client_id' => $client_id,
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

                $held_after_storage = $this->lock->assert_held($aggregate_lease);
                if (is_wp_error($held_after_storage)) {
                    return $this->fail_after_storage_coordination_loss(
                        $storage_path_for_cleanup,
                        $operation_id,
                        $held_after_storage
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

                $record_final = ExpedienteRegistrosRepository::find_by_id_for_client($record_id, $client_id);
                if ($record_final === null) {
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
                    'client_id' => $client_id,
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

                return [
                    'ok' => true,
                    'attachment' => [
                        'id' => (int) $inserted['id'],
                        'record_id' => (int) $inserted['record_id'],
                        'client_id' => (int) $inserted['client_id'],
                        'upload_operation_id' => (string) $inserted['upload_operation_id'],
                        'storage_path' => (string) $inserted['storage_path'],
                        'mime_type' => (string) $inserted['mime_type'],
                        'byte_size' => (int) $inserted['byte_size'],
                        'width' => (int) $inserted['width'],
                        'height' => (int) $inserted['height'],
                        'created_at' => (string) $inserted['created_at'],
                    ],
                ];
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
     * @param array<string,mixed> $record
     */
    private function bridged_expediente_id(array $record): ?int {
        $raw = $record['expediente_id'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = (int) $raw;
        return $id >= 1 ? $id : null;
    }

    /**
     * @param array<string,mixed> $file
     * @return array{ok:true,attachment:array<string,mixed>}|array{ok:false,code:string,message:string}
     */
    private function delegate_canonical_v2(
        int $expediente_id,
        int $record_id,
        int $client_id,
        string $operation_id,
        array $file
    ): array {
        $owner = ExpedientesRepository::find_owner_context_by_id($expediente_id);
        if ($owner === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }
        $parent_client = isset($owner['client_id']) ? (int) $owner['client_id'] : 0;
        if ($parent_client < 1 || $parent_client !== $client_id) {
            return $this->fail('record_not_found', 'Registro no encontrado.');
        }

        if (!class_exists('UploadExpedienteAdjuntoForExpedienteUseCase')) {
            require_once __DIR__ . '/UploadExpedienteAdjuntoForExpedienteUseCase.php';
        }

        $canonical = $this->canonical_upload;
        if ($canonical === null) {
            $canonical = new UploadExpedienteAdjuntoForExpedienteUseCase(
                null,
                $this->validator,
                $this->transfer,
                $this->lock,
                $this->cleanup_client
            );
        }

        $result = $canonical->execute([
            'expediente_id' => $expediente_id,
            'record_id' => $record_id,
            'upload_operation_id' => $operation_id,
            'file' => $file,
        ]);

        if (!empty($result['success'])) {
            $adjunto = is_array($result['data']['adjunto'] ?? null) ? $result['data']['adjunto'] : [];
            // El DTO público no trae path/client; reconstruir attachment mínimo para callers legacy
            // que solo necesitan ok+id. Preferir fila por operation_id si existe.
            $by_op = ExpedienteAdjuntosRepository::find_by_upload_operation_id($operation_id);
            if (is_array($by_op)) {
                return [
                    'ok' => true,
                    'attachment' => [
                        'id' => (int) $by_op['id'],
                        'record_id' => (int) $by_op['record_id'],
                        'client_id' => $by_op['client_id'] ?? null,
                        'upload_operation_id' => (string) $by_op['upload_operation_id'],
                        'storage_path' => (string) $by_op['storage_path'],
                        'mime_type' => (string) $by_op['mime_type'],
                        'byte_size' => (int) $by_op['byte_size'],
                        'width' => (int) $by_op['width'],
                        'height' => (int) $by_op['height'],
                        'created_at' => (string) $by_op['created_at'],
                    ],
                ];
            }

            return [
                'ok' => true,
                'attachment' => [
                    'id' => (int) ($adjunto['id'] ?? 0),
                    'record_id' => $record_id,
                    'client_id' => $client_id,
                    'upload_operation_id' => $operation_id,
                    'storage_path' => '',
                    'mime_type' => 'image/jpeg',
                    'byte_size' => (int) ($adjunto['byte_size'] ?? 0),
                    'width' => (int) ($adjunto['width'] ?? 0),
                    'height' => (int) ($adjunto['height'] ?? 0),
                    'created_at' => (string) ($adjunto['created_at'] ?? ''),
                ],
            ];
        }

        $code = (string) ($result['error']['code'] ?? 'attach_failed');
        $message = (string) ($result['error']['message'] ?? 'No se pudo subir la imagen.');

        return $this->fail($code, $message);
    }

    /**
     * @param WP_Error $lock_error
     * @return array{ok:false,code:string,message:string}|array{ok:true,attachment:array<string,mixed>}
     */
    private function fail_after_storage_coordination_loss(string $storage_path, string $operation_id, $lock_error) {
        $existing = ExpedienteAdjuntosRepository::find_by_upload_operation_id($operation_id);
        if (is_array($existing) && (string) ($existing['storage_path'] ?? '') === $storage_path) {
            return [
                'ok' => true,
                'attachment' => [
                    'id' => (int) $existing['id'],
                    'record_id' => (int) $existing['record_id'],
                    'client_id' => (int) ($existing['client_id'] ?? 0),
                    'upload_operation_id' => (string) $existing['upload_operation_id'],
                    'storage_path' => (string) $existing['storage_path'],
                    'mime_type' => (string) $existing['mime_type'],
                    'byte_size' => (int) $existing['byte_size'],
                    'width' => (int) $existing['width'],
                    'height' => (int) $existing['height'],
                    'created_at' => (string) $existing['created_at'],
                ],
            ];
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

    /**
     * Compensación best-effort del objeto creado por esta petición.
     *
     * @return true|false true si deleted|already_absent
     */
    private function compensate_storage(string $storage_path): bool {
        $client = $this->cleanup_client;
        if (!is_object($client) || !method_exists($client, 'delete_object')) {
            $client = new AA_Expediente_Attachments_Backend_Client();
        }

        if (!is_object($client) || !method_exists($client, 'delete_object')) {
            error_log('[UploadExpedienteRegistroAdjuntoUseCase] storage cleanup unavailable');
            return false;
        }

        $deleted = $client->delete_object($storage_path);
        if (empty($deleted['ok'])) {
            error_log('[UploadExpedienteRegistroAdjuntoUseCase] storage cleanup failed');
            return false;
        }

        $status = (string) ($deleted['result']['status'] ?? '');
        if ($status !== 'deleted' && $status !== 'already_absent') {
            error_log('[UploadExpedienteRegistroAdjuntoUseCase] storage cleanup unexpected');
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $fin
     * @param array{
     *   client_id:int,
     *   record_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int
     * } $expected
     */
    private function finalize_matches_expectation(array $fin, array $expected): bool {
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
        if ((string) ($parsed['contract'] ?? '') !== ExpedienteAdjuntoVariants::CONTRACT_CLIENT_V1) {
            return false;
        }
        if ((int) ($parsed['client_id'] ?? 0) !== $expected['client_id']) {
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
     * @return array{ok:false,code:string,message:string}
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
     * @return array{ok:false,code:string,message:string}
     */
    private function fail(string $code, string $message): array {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
