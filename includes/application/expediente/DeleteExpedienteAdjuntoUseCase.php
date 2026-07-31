<?php
/**
 * Delete Expediente Adjunto Use Case (MC5c1).
 *
 * Orden: Storage eliminado o inequívocamente ausente → fila local.
 * El navegador nunca aporta storage_path.
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
if (!class_exists('ExpedienteAdjuntoPublicDto')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoPublicDto.php';
}
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}

final class DeleteExpedienteAdjuntoUseCase {

    /** @var object */
    private $backend;

    /**
     * @param object|null $backend AA_Expediente_Attachments_Backend_Client o doble de prueba
     */
    public function __construct($backend = null) {
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
    }

    /**
     * @param array{client_id:int,record_id:int,attachment_id:int} $input
     * @return array{
     *   ok:true,
     *   record_id:int,
     *   deleted_attachment_id:int,
     *   adjuntos:list<array{id:int,width:int,height:int,byte_size:int,created_at:string}>,
     *   adjunto:array{id:int,width:int,height:int,byte_size:int,created_at:string}|null
     * }|array{ok:false,code:string,message:string}
     */
    public function execute(array $input): array {
        $client_id = (int) ($input['client_id'] ?? 0);
        $record_id = (int) ($input['record_id'] ?? 0);
        $attachment_id = (int) ($input['attachment_id'] ?? 0);

        if ($client_id < 1 || $record_id < 1 || $attachment_id < 1) {
            return $this->fail('invalid_context', 'Cliente, registro o imagen no válidos.');
        }

        if (ClientsRepository::find_by_id($client_id) === null) {
            return $this->fail('client_not_found', 'Cliente no encontrado.');
        }

        if (ExpedienteRegistrosRepository::find_by_id_for_client($record_id, $client_id) === null) {
            return $this->fail('record_not_found', 'Registro no encontrado.');
        }

        $adjunto = ExpedienteAdjuntosRepository::find_by_id_for_client($attachment_id, $client_id);
        if ($adjunto === null || (int) ($adjunto['record_id'] ?? 0) !== $record_id) {
            return $this->fail('attachment_not_found', 'Imagen no encontrada.');
        }

        $storage_path = (string) ($adjunto['storage_path'] ?? '');
        $expected_suffix = sprintf('/clients/%d/records/%d/', $client_id, $record_id);
        if ($storage_path === '' || strpos($storage_path, $expected_suffix) === false) {
            return $this->fail('adjunto_inconsistent', 'El adjunto local es inconsistente.');
        }

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

        if (!ExpedienteAdjuntosRepository::delete_by_id_for_client($attachment_id, $client_id)) {
            // Storage ausente; fila conservada para reintento idempotente.
            return $this->fail('local_delete_failed', 'No se pudo eliminar la imagen.');
        }

        $remaining_rows = ExpedienteAdjuntosRepository::list_by_record_for_client($record_id, $client_id);
        $dtos = [];
        foreach ($remaining_rows as $row) {
            $dto = ExpedienteAdjuntoPublicDto::from($row);
            if ($dto !== null) {
                $dtos[] = $dto;
            }
        }

        return [
            'ok' => true,
            'record_id' => $record_id,
            'deleted_attachment_id' => $attachment_id,
            'adjuntos' => $dtos,
            'adjunto' => $dtos[0] ?? null,
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
