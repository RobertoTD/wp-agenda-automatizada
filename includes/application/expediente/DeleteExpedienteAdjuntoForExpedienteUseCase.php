<?php
/**
 * Delete Expediente Adjunto For Expediente Use Case (B3b2).
 *
 * Scope canónico por expediente_id: valida pertenencia, deriva client_id del
 * padre y delega a DeleteExpedienteAdjuntoUseCase. Sin gate/nonce.
 * Storage/DB y DTO quedan en el UC legacy.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}
if (!class_exists('ExpedienteRegistrosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
}
if (!class_exists('DeleteExpedienteAdjuntoUseCase')) {
    require_once __DIR__ . '/DeleteExpedienteAdjuntoUseCase.php';
}

final class DeleteExpedienteAdjuntoForExpedienteUseCase {

    /** @var DeleteExpedienteAdjuntoUseCase */
    private $delete_use_case;

    public function __construct(?DeleteExpedienteAdjuntoUseCase $delete_use_case = null) {
        $this->delete_use_case = $delete_use_case ?: new DeleteExpedienteAdjuntoUseCase();
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

        $parent_client_id = $owner['client_id'] ?? null;
        if (!is_int($parent_client_id) || $parent_client_id < 1) {
            return $this->fail('attachments_unavailable', 'Este expediente no admite adjuntos.');
        }

        $record = ExpedienteRegistrosRepository::find_by_id_for_expediente($record_id, $expediente_id);
        if ($record === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
        }
        if ($record === false) {
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $record_client_raw = $record['client_id'] ?? null;
        $record_client_id = is_int($record_client_raw) ? $record_client_raw : null;
        if ($record_client_id === null || $record_client_id < 1 || $record_client_id !== $parent_client_id) {
            error_log('[DeleteExpedienteAdjuntoForExpedienteUseCase] record owner mismatch');
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $deleted = $this->delete_use_case->execute([
            'client_id' => $parent_client_id,
            'record_id' => $record_id,
            'attachment_id' => $attachment_id,
        ]);

        if (empty($deleted['ok'])) {
            return $this->fail(
                (string) ($deleted['code'] ?? 'delete_failed'),
                (string) ($deleted['message'] ?? 'No se pudo eliminar la imagen.')
            );
        }

        $adjuntos = is_array($deleted['adjuntos'] ?? null) ? $deleted['adjuntos'] : [];
        $adjunto = array_key_exists('adjunto', $deleted) ? $deleted['adjunto'] : null;
        if ($adjunto !== null && !is_array($adjunto)) {
            $adjunto = null;
        }

        return $this->ok([
            'record_id' => (int) ($deleted['record_id'] ?? $record_id),
            'deleted_attachment_id' => (int) ($deleted['deleted_attachment_id'] ?? $attachment_id),
            'adjuntos' => $adjuntos,
            'adjunto' => $adjunto,
        ]);
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
