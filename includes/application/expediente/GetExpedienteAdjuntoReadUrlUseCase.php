<?php
/**
 * Get Expediente Adjunto Read URL Use Case (MC4c/MC5a/MC5b / 6A).
 *
 * Lectura siempre dirigida: client_id + record_id + attachment_id + variant.
 * Firma exclusivamente summary | gallery | display derivadas del original
 * canónico local. La signed URL solo existe en la respuesta autenticada.
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
if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('AA_Expediente_Attachment_Read_Url_Validator')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';
}

final class GetExpedienteAdjuntoReadUrlUseCase {

    /** @var object */
    private $backend;

    /** @var AA_Expediente_Attachment_Read_Url_Validator */
    private $url_validator;

    /**
     * @param object|null $backend AA_Expediente_Attachments_Backend_Client o doble de prueba
     * @param AA_Expediente_Attachment_Read_Url_Validator|null $url_validator
     */
    public function __construct($backend = null, ?AA_Expediente_Attachment_Read_Url_Validator $url_validator = null) {
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
        $this->url_validator = $url_validator ?: new AA_Expediente_Attachment_Read_Url_Validator();
    }

    /**
     * @param array{client_id:int,record_id:int,attachment_id:int,variant:mixed} $input
     * @return array{ok:true,url:string,expires_in:int,variant:string}|array{ok:false,code:string,message:string}
     */
    public function execute(array $input): array {
        $variant = $input['variant'] ?? null;
        if (!is_string($variant) || !ExpedienteAdjuntoVariants::is_allowed_variant($variant)) {
            return $this->fail('variant_invalid', 'Variante de imagen no válida.');
        }

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

        return [
            'ok' => true,
            'url' => (string) $validated['url'],
            'expires_in' => $expires_in,
            'variant' => $variant,
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
