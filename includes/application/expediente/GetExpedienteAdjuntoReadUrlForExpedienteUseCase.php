<?php
/**
 * Get Expediente Adjunto Read URL For Expediente Use Case (B3a / P2).
 *
 * Scope canónico por expediente_id: expediente → registro → adjunto → policy dual
 * → sign_read. Sin gate/nonce. Sin attachments_unavailable.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('AA_Expediente_Adjunto_Identity_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-adjunto-identity-policy.php';
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
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('AA_Expediente_Attachment_Read_Url_Validator')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';
}

final class GetExpedienteAdjuntoReadUrlForExpedienteUseCase {

    /** @var object */
    private $backend;

    /** @var AA_Expediente_Attachment_Read_Url_Validator */
    private $url_validator;

    /**
     * @param object|null $backend
     * @param AA_Expediente_Attachment_Read_Url_Validator|null $url_validator
     */
    public function __construct($backend = null, ?AA_Expediente_Attachment_Read_Url_Validator $url_validator = null) {
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
        $this->url_validator = $url_validator ?: new AA_Expediente_Attachment_Read_Url_Validator();
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

        $record_client = AA_Expediente_Adjunto_Identity_Policy::normalize_client_id($record['client_id'] ?? null);
        if (!$record_client['ok'] || $record_client['id'] !== $parent_client['id']) {
            error_log('[GetExpedienteAdjuntoReadUrlForExpedienteUseCase] record owner mismatch');
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
        $signed = $this->backend->sign_read($storage_path, $variant);
        if (empty($signed['ok'])) {
            return $this->fail(
                (string) ($signed['code'] ?? 'sign_read_failed'),
                'No se pudo obtener la imagen.'
            );
        }

        /** @var array<string,mixed> $result */
        $result = $signed['result'];
        $url = (string) ($result['url'] ?? '');
        $expires_in = (int) ($result['expires_in'] ?? 0);
        $got_variant = $result['variant'] ?? null;

        if ($url === '' || $expires_in < 1 || !is_string($got_variant) || $got_variant !== $variant) {
            return $this->fail('sign_read_invalid', 'Respuesta de firma incompleta.');
        }

        $validated = $this->url_validator->validate($url, $storage_path, $variant);
        if (empty($validated['ok'])) {
            return $this->fail('signed_url_invalid', 'No se pudo obtener la imagen.');
        }

        return $this->ok([
            'url' => (string) $validated['url'],
            'expires_in' => $expires_in,
            'variant' => $variant,
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
