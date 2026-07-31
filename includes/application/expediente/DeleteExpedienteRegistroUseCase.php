<?php
/**
 * Delete Expediente Registro Use Case (MC5c2).
 *
 * Orden: Storage de todos los adjuntos (MC5c1 delete_object) → filas de
 * adjuntos → fila del registro. Sin transacciones. El navegador nunca
 * aporta storage_path.
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
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}

final class DeleteExpedienteRegistroUseCase {

    /** @var object */
    private $backend;

    /**
     * @param object|null $backend AA_Expediente_Attachments_Backend_Client o doble de prueba
     */
    public function __construct($backend = null) {
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
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

        if (ExpedienteRegistrosRepository::find_by_id_for_client($record_id, $client_id) === null) {
            return $this->fail('record_not_found', 'Registro no encontrado.');
        }

        $adjuntos = ExpedienteAdjuntosRepository::list_by_record_for_client($record_id, $client_id);
        $expected_suffix = sprintf('/clients/%d/records/%d/', $client_id, $record_id);

        foreach ($adjuntos as $adjunto) {
            $storage_path = (string) ($adjunto['storage_path'] ?? '');
            if ($storage_path === '' || strpos($storage_path, $expected_suffix) === false) {
                return $this->fail('adjunto_inconsistent', 'Un adjunto local es inconsistente.');
            }

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
        }

        // Solo tras todos los objetos ausentes: filas locales y registro.
        if (!ExpedienteAdjuntosRepository::delete_by_record_for_client($record_id, $client_id)) {
            return $this->fail('local_delete_failed', 'No se pudo eliminar el registro.');
        }

        if (!ExpedienteRegistrosRepository::delete_by_id_for_client($record_id, $client_id)) {
            // Adjuntos ya ausentes; el reintento sin imágenes puede completar.
            return $this->fail('local_delete_failed', 'No se pudo eliminar el registro.');
        }

        return [
            'ok' => true,
            'deleted' => true,
            'record_id' => $record_id,
        ];
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
