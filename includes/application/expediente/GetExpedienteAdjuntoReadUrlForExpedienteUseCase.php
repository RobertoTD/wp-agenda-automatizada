<?php
/**
 * Get Expediente Adjunto Read URL For Expediente Use Case (B3a).
 *
 * Scope canónico por expediente_id: valida pertenencia, deriva client_id del
 * padre y delega la firma a GetExpedienteAdjuntoReadUrlUseCase. Sin gate/nonce.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
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
if (!class_exists('GetExpedienteAdjuntoReadUrlUseCase')) {
    require_once __DIR__ . '/GetExpedienteAdjuntoReadUrlUseCase.php';
}

final class GetExpedienteAdjuntoReadUrlForExpedienteUseCase {

    /** @var GetExpedienteAdjuntoReadUrlUseCase */
    private $sign_use_case;

    public function __construct(?GetExpedienteAdjuntoReadUrlUseCase $sign_use_case = null) {
        $this->sign_use_case = $sign_use_case ?: new GetExpedienteAdjuntoReadUrlUseCase();
    }

    /**
     * @param array{expediente_id?:mixed,record_id?:mixed,attachment_id?:mixed,variant?:mixed} $input
     * @return array{success:true,data:array{url:string,expires_in:int,variant:string}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        $record_id = AA_Expediente_Id_Policy::normalize($input['record_id'] ?? null);
        $attachment_id = AA_Expediente_Id_Policy::normalize($input['attachment_id'] ?? null);
        if ($expediente_id === null || $record_id === null || $attachment_id === null) {
            return $this->fail('invalid_id', 'Identificador no válido.');
        }

        $variant = $input['variant'] ?? null;
        if (!is_string($variant) || !ExpedienteAdjuntoVariants::is_allowed_variant($variant)) {
            return $this->fail('variant_invalid', 'Variante de imagen no válida.');
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
            error_log('[GetExpedienteAdjuntoReadUrlForExpedienteUseCase] record owner mismatch');
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $signed = $this->sign_use_case->execute([
            'client_id' => $parent_client_id,
            'record_id' => $record_id,
            'attachment_id' => $attachment_id,
            'variant' => $variant,
        ]);

        if (empty($signed['ok'])) {
            return $this->fail(
                (string) ($signed['code'] ?? 'sign_read_failed'),
                (string) ($signed['message'] ?? 'No se pudo obtener la imagen.')
            );
        }

        return $this->ok([
            'url' => (string) ($signed['url'] ?? ''),
            'expires_in' => (int) ($signed['expires_in'] ?? 0),
            'variant' => (string) ($signed['variant'] ?? $variant),
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
     * @param array{url:string,expires_in:int,variant:string} $data
     * @return array{success:true,data:array{url:string,expires_in:int,variant:string}}
     */
    private function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
