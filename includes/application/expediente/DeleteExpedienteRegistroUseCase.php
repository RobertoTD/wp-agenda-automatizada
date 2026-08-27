<?php
/**
 * Delete Expediente Registro Use Case (MC5c2 / P2).
 *
 * Orden: Storage de todos los adjuntos → filas de adjuntos → fila del registro.
 * Histórico sin expediente → solo client_v1. Bridged → policy dual.
 *
 * Ciclo A: named lock client:{client_id} cubre revalidación → Storage → SQL.
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
if (!class_exists('AA_Expediente_Adjunto_Identity_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-adjunto-identity-policy.php';
}
if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}

final class DeleteExpedienteRegistroUseCase {

    /** @var object */
    private $backend;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    /**
     * @param object|null $backend AA_Expediente_Attachments_Backend_Client o doble de prueba
     * @param AA_Expediente_Aggregate_Lock|null $lock
     */
    public function __construct($backend = null, ?AA_Expediente_Aggregate_Lock $lock = null) {
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
    }

    /**
     * @param array{client_id:int,record_id:int} $input
     * @return array{ok:true,deleted:true,record_id:int}|array{ok:false,code:string,message:string}
     */
    public function execute(array $input): array {
        $client_id = (int) ($input['client_id'] ?? 0);
        $record_id = (int) ($input['record_id'] ?? 0);

        if ($client_id < 1 || $record_id < 1) {
            return $this->fail('invalid_context', 'Cliente o registro no válido.');
        }

        if (ClientsRepository::find_by_id($client_id) === null) {
            return $this->fail('client_not_found', 'Cliente no encontrado.');
        }

        $record = ExpedienteRegistrosRepository::find_by_id_for_client($record_id, $client_id);
        if ($record === null) {
            return $this->fail('record_not_found', 'Registro no encontrado.');
        }

        $lease = $this->lock->acquire(
            AA_Expediente_Aggregate_Lock::SCOPE_CLIENT,
            $client_id,
            AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
        );
        if (is_wp_error($lease)) {
            return $this->fail_from_lock($lease);
        }

        try {
            if (ClientsRepository::find_by_id($client_id) === null) {
                return $this->fail('client_not_found', 'Cliente no encontrado.');
            }

            $record = ExpedienteRegistrosRepository::find_by_id_for_client($record_id, $client_id);
            if ($record === null) {
                return $this->fail('record_not_found', 'Registro no encontrado.');
            }

            $held = $this->lock->assert_held($lease);
            if (is_wp_error($held)) {
                return $this->fail_from_lock($held);
            }

            $adjuntos = ExpedienteAdjuntosRepository::list_by_record_for_client($record_id, $client_id);

            foreach ($adjuntos as $adjunto) {
                if (!is_array($adjunto)) {
                    return $this->fail('adjunto_inconsistent', 'Un adjunto local es inconsistente.');
                }
                if (!$this->attachment_identity_ok($client_id, $record, $adjunto)) {
                    return $this->fail('adjunto_inconsistent', 'Un adjunto local es inconsistente.');
                }
            }

            foreach ($adjuntos as $adjunto) {
                $storage_path = (string) ($adjunto['storage_path'] ?? '');
                $deleted = $this->backend->delete_object($storage_path);
                if (empty($deleted['ok'])) {
                    return $this->fail(
                        (string) ($deleted['code'] ?? 'storage_delete_partial'),
                        'No se pudo eliminar el registro.'
                    );
                }

                $status = (string) ($deleted['result']['status'] ?? '');
                if ($status !== 'deleted' && $status !== 'already_absent') {
                    return $this->fail('storage_delete_partial', 'No se pudo eliminar el registro.');
                }

                $held = $this->lock->assert_held($lease);
                if (is_wp_error($held)) {
                    return $this->fail_from_lock($held);
                }

                $meta = ExpedienteAdjuntosRepository::delete_by_exact_identity([
                    'id' => (int) $adjunto['id'],
                    'record_id' => $record_id,
                    'client_id' => $adjunto['client_id'] ?? $client_id,
                    'upload_operation_id' => (string) ($adjunto['upload_operation_id'] ?? ''),
                    'storage_path' => $storage_path,
                ]);
                if (is_wp_error($meta) || $meta !== true) {
                    return $this->fail('local_delete_failed', 'No se pudo eliminar el registro.');
                }
            }

            if (!ExpedienteRegistrosRepository::delete_by_id_for_client($record_id, $client_id)) {
                return $this->fail('local_delete_failed', 'No se pudo eliminar el registro.');
            }

            return [
                'ok' => true,
                'deleted' => true,
                'record_id' => $record_id,
            ];
        } finally {
            $this->lock->release($lease);
        }
    }

    /**
     * @param array<string,mixed> $record
     * @param array<string,mixed> $adjunto
     */
    private function attachment_identity_ok(int $client_id, array $record, array $adjunto): bool {
        $expediente_id = AA_Expediente_Id_Policy::normalize($record['expediente_id'] ?? null);

        if ($expediente_id === null) {
            $check = AA_Expediente_Adjunto_Identity_Policy::validate_legacy_orphan(
                $client_id,
                [
                    'id' => (int) ($record['id'] ?? 0),
                    'client_id' => $client_id,
                    'expediente_id' => null,
                ],
                $adjunto
            );

            return !empty($check['ok']);
        }

        $owner = ExpedientesRepository::find_owner_context_by_id($expediente_id);
        if ($owner === null) {
            return false;
        }
        $parent_client = AA_Expediente_Adjunto_Identity_Policy::normalize_client_id($owner['client_id'] ?? null);
        if (!$parent_client['ok'] || $parent_client['id'] !== $client_id) {
            return false;
        }

        $check = AA_Expediente_Adjunto_Identity_Policy::validate(
            [
                'id' => $expediente_id,
                'client_id' => $client_id,
            ],
            [
                'id' => (int) ($record['id'] ?? 0),
                'expediente_id' => $expediente_id,
                'client_id' => $client_id,
            ],
            $adjunto
        );

        return !empty($check['ok']);
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
