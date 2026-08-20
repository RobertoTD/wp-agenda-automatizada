<?php
/**
 * Upload Expediente Adjunto For Expediente Use Case (B3b1).
 *
 * Scope canónico por expediente_id: valida pertenencia, deriva client_id del
 * padre y delega el pipeline a UploadExpedienteRegistroAdjuntoUseCase.
 * Sin gate/nonce. No duplica validación JPEG ni transfer.
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
if (!class_exists('UploadExpedienteRegistroAdjuntoUseCase')) {
    require_once __DIR__ . '/UploadExpedienteRegistroAdjuntoUseCase.php';
}
if (!class_exists('ExpedienteAdjuntoPublicDto')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoPublicDto.php';
}

final class UploadExpedienteAdjuntoForExpedienteUseCase {

    /** @var UploadExpedienteRegistroAdjuntoUseCase */
    private $upload_use_case;

    public function __construct(?UploadExpedienteRegistroAdjuntoUseCase $upload_use_case = null) {
        $this->upload_use_case = $upload_use_case ?: new UploadExpedienteRegistroAdjuntoUseCase();
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
            error_log('[UploadExpedienteAdjuntoForExpedienteUseCase] record owner mismatch');
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $operation_id = $input['upload_operation_id'] ?? '';
        if (!is_string($operation_id) && !is_int($operation_id) && !is_float($operation_id)) {
            $operation_id = '';
        } else {
            $operation_id = (string) $operation_id;
        }

        $file = isset($input['file']) && is_array($input['file']) ? $input['file'] : [];

        $uploaded = $this->upload_use_case->execute([
            'client_id' => $parent_client_id,
            'record_id' => $record_id,
            'upload_operation_id' => $operation_id,
            'file' => $file,
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
