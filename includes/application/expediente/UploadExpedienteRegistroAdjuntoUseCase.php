<?php
/**
 * Upload Expediente Registro Adjunto Use Case (MC4b).
 *
 * Orquesta: validación JPEG → cuota local → transfer v1 → insert_finalized.
 * Nunca inserta metadatos antes de un finalize coincidente.
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
if (!class_exists('ExpedienteAdjuntoJpegValidator')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoJpegValidator.php';
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

final class UploadExpedienteRegistroAdjuntoUseCase {

    private const UUID_V4_RE = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** @var ExpedienteAdjuntoJpegValidator */
    private $validator;

    /** @var object */
    private $transfer;

    /**
     * @param ExpedienteAdjuntoJpegValidator|null $validator
     * @param object|null $transfer ExpedienteAdjuntoUploadTransfer o doble de prueba
     */
    public function __construct(
        ?ExpedienteAdjuntoJpegValidator $validator = null,
        $transfer = null
    ) {
        $this->validator = $validator ?: new ExpedienteAdjuntoJpegValidator();
        $this->transfer = $transfer ?: new ExpedienteAdjuntoUploadTransfer(
            new AA_Expediente_Adjunto_Variant_Generator(),
            new AA_Expediente_Attachments_Backend_Client(),
            new AA_Expediente_Attachment_Signed_Uploader()
        );
    }

    /**
     * @param array{
     *   client_id:int,
     *   record_id:int,
     *   upload_operation_id:string,
     *   file:array<string,mixed>
     * } $input
     * @return array{ok:true,attachment:array<string,mixed>}|array{ok:false,code:string,message:string}
     */
    public function execute(array $input): array {
        $client_id = (int) ($input['client_id'] ?? 0);
        $record_id = (int) ($input['record_id'] ?? 0);
        $operation_id = strtolower(trim((string) ($input['upload_operation_id'] ?? '')));
        $file = isset($input['file']) && is_array($input['file']) ? $input['file'] : [];

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

            $used_bytes = ExpedienteAdjuntosRepository::sum_byte_size_total();
            if ($used_bytes === null) {
                return $this->fail(
                    'storage_usage_unavailable',
                    'No se pudo verificar el espacio disponible.'
                );
            }

            $transferred = $this->transfer->transfer([
                'source_path' => $tmp_to_clean,
                'mime_type' => $mime,
                'byte_size' => $byte_size,
                'width' => $width,
                'height' => $height,
                'upload_operation_id' => $operation_id,
                'wp_client_id' => $client_id,
                'wp_record_id' => $record_id,
                'used_bytes' => $used_bytes,
            ]);

            if (empty($transferred['ok'])) {
                $code = (string) ($transferred['code'] ?? 'transfer_failed');
                return $this->fail($code, $this->transfer_failure_message($code, $transferred));
            }

            $storage_path = (string) ($transferred['storage_path'] ?? '');
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
            $this->cleanup_tmp($tmp_to_clean);
        }
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

        return $this->storage_path_matches_context(
            (string) $fin['storage_path'],
            $expected['client_id'],
            $expected['record_id'],
            $expected['upload_operation_id']
        );
    }

    private function storage_path_matches_context(
        string $storage_path,
        int $client_id,
        int $record_id,
        string $operation_id
    ): bool {
        $needle = sprintf(
            '/clients/%d/records/%d/%s.jpg',
            $client_id,
            $record_id,
            $operation_id
        );

        if (substr($storage_path, -strlen($needle)) !== $needle) {
            return false;
        }

        return (bool) preg_match(
            '#^installations/[0-9a-f\\-]{36}/clients/' . $client_id . '/records/' . $record_id . '/' . preg_quote($operation_id, '#') . '\\.jpg$#i',
            $storage_path
        );
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
