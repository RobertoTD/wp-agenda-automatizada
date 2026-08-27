<?php
/**
 * Delete Expediente Adjunto For Expediente Use Case (B3b2 / P2).
 *
 * Algoritmo canónico dual: padre → registro → adjunto → lock → policy →
 * Storage → metadata exacta null-safe. Sin attachments_unavailable.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('AA_Expediente_Adjunto_Identity_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-adjunto-identity-policy.php';
}
if (!class_exists('ExpedienteAdjuntoPublicDto')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoPublicDto.php';
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
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}

final class DeleteExpedienteAdjuntoForExpedienteUseCase {

    /** @var object */
    private $backend;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    /**
     * @param object|null $backend
     * @param AA_Expediente_Aggregate_Lock|null $lock
     */
    public function __construct($backend = null, ?AA_Expediente_Aggregate_Lock $lock = null) {
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
    }

    /**
     * @param array{expediente_id?:mixed,record_id?:mixed,attachment_id?:mixed} $input
     * @return array{
     *   success:true,
     *   data:array{
     *     record_id:int,
     *     deleted_attachment_id:int,
     *     adjuntos:list<array<string,mixed>>,
     *     adjunto:array<string,mixed>|null
     *   }
     * }|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        $record_id = AA_Expediente_Id_Policy::normalize($input['record_id'] ?? null);
        $attachment_id = AA_Expediente_Id_Policy::normalize($input['attachment_id'] ?? null);
        if ($expediente_id === null || $record_id === null || $attachment_id === null) {
            return $this->fail('invalid_id', 'Identificador no válido.');
        }

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

        $parent_client = AA_Expediente_Adjunto_Identity_Policy::normalize_client_id($owner['client_id'] ?? null);
        if (!$parent_client['ok']) {
            return $this->fail('adjunto_inconsistent', 'El adjunto local es inconsistente.');
        }

        $record = ExpedienteRegistrosRepository::find_by_id_for_expediente($record_id, $expediente_id);
        if ($record === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
        }
        if ($record === false) {
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $adjunto = ExpedienteAdjuntosRepository::find_by_id_for_record($attachment_id, $record_id);
        if ($adjunto === null) {
            return $this->fail('not_found', 'Imagen no encontrada.');
        }

        $scope_kind = $parent_client['id'] === null
            ? AA_Expediente_Aggregate_Lock::SCOPE_EXPEDIENTE
            : AA_Expediente_Aggregate_Lock::SCOPE_CLIENT;
        $scope_id = $parent_client['id'] === null ? $expediente_id : $parent_client['id'];

        $lease = $this->lock->acquire(
            $scope_kind,
            $scope_id,
            AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
        );
        if (is_wp_error($lease)) {
            return $this->fail_from_lock($lease);
        }

        try {
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
            $re_parent = AA_Expediente_Adjunto_Identity_Policy::normalize_client_id($owner['client_id'] ?? null);
            if (!$re_parent['ok'] || $re_parent['id'] !== $parent_client['id']) {
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $record = ExpedienteRegistrosRepository::find_by_id_for_expediente($record_id, $expediente_id);
            if ($record === null) {
                return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
            }
            if ($record === false) {
                return $this->fail('not_found', 'Registro no encontrado.');
            }

            $adjunto = ExpedienteAdjuntosRepository::find_by_id_for_record($attachment_id, $record_id);
            if ($adjunto === null) {
                return $this->fail('not_found', 'Imagen no encontrada.');
            }

            $check = AA_Expediente_Adjunto_Identity_Policy::validate(
                [
                    'id' => $expediente_id,
                    'client_id' => $parent_client['id'],
                ],
                [
                    'id' => (int) $record['id'],
                    'expediente_id' => (int) $record['expediente_id'],
                    'client_id' => $record['client_id'] ?? null,
                ],
                $adjunto
            );
            if (empty($check['ok'])) {
                return $this->fail('adjunto_inconsistent', 'El adjunto local es inconsistente.');
            }

            $storage_path = (string) ($adjunto['storage_path'] ?? '');
            $deleted = $this->backend->delete_object($storage_path);
            if (empty($deleted['ok'])) {
                return $this->fail(
                    (string) ($deleted['code'] ?? 'storage_delete_failed'),
                    'No se pudo eliminar la imagen.'
                );
            }

            $status = (string) (($deleted['result']['status'] ?? ''));
            if ($status !== 'deleted' && $status !== 'already_absent') {
                return $this->fail('storage_delete_failed', 'No se pudo eliminar la imagen.');
            }

            $held = $this->lock->assert_held($lease);
            if (is_wp_error($held)) {
                return $this->fail_from_lock($held);
            }

            $meta = ExpedienteAdjuntosRepository::delete_by_exact_identity([
                'id' => (int) $adjunto['id'],
                'record_id' => $record_id,
                'client_id' => $adjunto['client_id'] ?? null,
                'upload_operation_id' => (string) ($adjunto['upload_operation_id'] ?? ''),
                'storage_path' => $storage_path,
            ]);
            if (is_wp_error($meta)) {
                return $this->fail('local_delete_failed', 'No se pudo eliminar la imagen.');
            }
            if ($meta !== true) {
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $remaining_bulk = ExpedienteAdjuntosRepository::list_by_record_ids_for_records([$record_id]);
            if ($remaining_bulk === null) {
                return $this->fail('lookup_failed', 'No se pudo verificar los adjuntos.');
            }
            $remaining_rows = $remaining_bulk[$record_id] ?? [];
            $dtos = [];
            foreach ($remaining_rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row_check = AA_Expediente_Adjunto_Identity_Policy::validate(
                    [
                        'id' => $expediente_id,
                        'client_id' => $parent_client['id'],
                    ],
                    [
                        'id' => (int) $record['id'],
                        'expediente_id' => (int) $record['expediente_id'],
                        'client_id' => $record['client_id'] ?? null,
                    ],
                    $row
                );
                if (empty($row_check['ok'])) {
                    return $this->fail('adjunto_inconsistent', 'El adjunto local es inconsistente.');
                }
                $dto = ExpedienteAdjuntoPublicDto::from($row);
                if ($dto !== null) {
                    $dtos[] = $dto;
                }
            }

            return $this->ok([
                'record_id' => $record_id,
                'deleted_attachment_id' => $attachment_id,
                'adjuntos' => $dtos,
                'adjunto' => $dtos[0] ?? null,
            ]);
        } finally {
            $this->lock->release($lease);
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
     * @param array{
     *   record_id:int,
     *   deleted_attachment_id:int,
     *   adjuntos:list<array<string,mixed>>,
     *   adjunto:array<string,mixed>|null
     * } $data
     * @return array{success:true,data:array<string,mixed>}
     */
    private function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
