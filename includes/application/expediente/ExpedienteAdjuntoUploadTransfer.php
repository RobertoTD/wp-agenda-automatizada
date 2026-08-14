<?php
/**
 * Transferencia v1 de adjuntos de expediente (original + tres variantes).
 *
 * Application: orquesta generador, authorize/finalize y PUT firmados.
 * No persiste filas, no construye DTO, no compensa Storage.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}

final class ExpedienteAdjuntoUploadTransfer {

    /** @var list<string> */
    public const OBJECT_KEYS = [
        'original',
        'summary',
        'gallery',
        'display',
    ];

    /** @var list<string> */
    public const UPLOAD_ORDER = [
        'summary',
        'gallery',
        'display',
        'original',
    ];

    /** @var object */
    private $generator;

    /** @var object */
    private $backend;

    /** @var object */
    private $uploader;

    /**
     * @param object $generator generate(string): array, delete_generated(array): void
     * @param object $backend   authorize_upload(array): array, finalize(string): array
     * @param object $uploader  put_jpeg(string, string, string): array
     */
    public function __construct(object $generator, object $backend, object $uploader) {
        $this->generator = $generator;
        $this->backend = $backend;
        $this->uploader = $uploader;
    }

    /**
     * @param array{
     *   source_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   upload_operation_id:string,
     *   wp_client_id:int,
     *   wp_record_id:int,
     *   used_bytes:int
     * } $input
     * @return array{ok:true, storage_path:string, finalize:array<string,mixed>}
     *     |array{ok:false, code:string, message:string}
     */
    public function transfer(array $input): array {
        $variants = [];

        try {
            $source_path = (string) ($input['source_path'] ?? '');
            $operation_id = strtolower(trim((string) ($input['upload_operation_id'] ?? '')));
            $client_id = (int) ($input['wp_client_id'] ?? 0);
            $record_id = (int) ($input['wp_record_id'] ?? 0);
            $original_bytes = (int) ($input['byte_size'] ?? 0);

            $generated = $this->generator->generate($source_path);
            if (!is_array($generated) || empty($generated['ok'])) {
                return $this->fail(
                    'variant_generation_failed',
                    'No se pudo generar las variantes de la imagen.'
                );
            }

            $raw_variants = isset($generated['variants']) && is_array($generated['variants'])
                ? $generated['variants']
                : [];
            $variants = $raw_variants;

            $checked = $this->checked_generated_variants($raw_variants);
            if ($checked === null) {
                return $this->fail(
                    'variant_generation_failed',
                    'No se pudo generar las variantes de la imagen.'
                );
            }

            $authorize = $this->backend->authorize_upload([
                'upload_operation_id' => $operation_id,
                'wp_client_id' => $client_id,
                'wp_record_id' => $record_id,
                'mime_type' => (string) ($input['mime_type'] ?? ''),
                'byte_size' => $original_bytes,
                'width' => (int) ($input['width'] ?? 0),
                'height' => (int) ($input['height'] ?? 0),
                'used_bytes' => (int) ($input['used_bytes'] ?? 0),
                'variants_manifest_version' => ExpedienteAdjuntoVariants::MANIFEST_VERSION,
                'variant_byte_sizes' => [
                    'summary' => $checked['summary']['byte_size'],
                    'gallery' => $checked['gallery']['byte_size'],
                    'display' => $checked['display']['byte_size'],
                ],
            ]);

            $auth_failure = $this->closed_failure($authorize, 'authorize_invalid', 'No se pudo autorizar la subida de la imagen.');
            if ($auth_failure !== null) {
                return $auth_failure;
            }

            if (!is_array($authorize) || empty($authorize['ok'])) {
                return $this->fail(
                    'authorize_invalid',
                    'No se pudo autorizar la subida de la imagen.'
                );
            }

            $plan = $this->operational_plan($authorize, $client_id, $record_id, $operation_id);
            if ($plan['ok'] !== true) {
                return $this->fail($plan['code'], $plan['message']);
            }

            $local_files = [
                'original' => $source_path,
                'summary' => $checked['summary']['path'],
                'gallery' => $checked['gallery']['path'],
                'display' => $checked['display']['path'],
            ];
            $expected_sizes = [
                'original' => $original_bytes,
                'summary' => $checked['summary']['byte_size'],
                'gallery' => $checked['gallery']['byte_size'],
                'display' => $checked['display']['byte_size'],
            ];

            foreach (self::UPLOAD_ORDER as $key) {
                $entry = $plan['objects'][$key];
                if ($entry['status'] === 'already_uploaded') {
                    continue;
                }

                $binary = @file_get_contents($local_files[$key]);
                if ($binary === false || strlen($binary) !== $expected_sizes[$key]) {
                    return $this->fail('read_failed', 'No se pudo leer el archivo temporal.');
                }

                $signed_url = $entry['signed_url'];
                $put = $this->uploader->put_jpeg($signed_url, $binary, $plan['paths'][$key]);
                unset($signed_url, $binary);

                $put_failure = $this->closed_failure($put, 'upload_failed', 'No se pudo subir la imagen.');
                if ($put_failure !== null) {
                    return $put_failure;
                }

                if (!is_array($put) || empty($put['ok'])) {
                    return $this->fail('upload_failed', 'No se pudo subir la imagen.');
                }
            }

            $finalize = $this->backend->finalize($plan['upload_intent']);
            $fin_failure = $this->closed_failure(
                $finalize,
                'finalize_mismatch',
                'No se pudo confirmar la subida de la imagen.'
            );
            if ($fin_failure !== null) {
                return $fin_failure;
            }

            if (!is_array($finalize) || empty($finalize['ok'])) {
                return $this->fail(
                    'finalize_mismatch',
                    'No se pudo confirmar la subida de la imagen.'
                );
            }

            $fin_result = $finalize['result'] ?? null;
            if (!is_array($fin_result)) {
                return $this->fail(
                    'finalize_mismatch',
                    'La confirmación no coincide con los datos esperados.'
                );
            }

            return [
                'ok' => true,
                'storage_path' => $plan['storage_path'],
                'finalize' => $fin_result,
            ];
        } finally {
            $this->generator->delete_generated($variants);
        }
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,array{path:string,byte_size:int}>|null
     */
    private function checked_generated_variants(array $raw): ?array {
        $checked = [];

        foreach (ExpedienteAdjuntoVariants::ALLOWED_VARIANTS as $name) {
            if (!isset($raw[$name]) || !is_array($raw[$name])) {
                return null;
            }

            $path = isset($raw[$name]['path']) ? (string) $raw[$name]['path'] : '';
            $byte_size = $raw[$name]['byte_size'] ?? null;
            if ($path === '' || !is_int($byte_size) || $byte_size < 1) {
                return null;
            }
            if (!is_file($path) || !is_readable($path)) {
                return null;
            }

            $size = @filesize($path);
            if ($size === false || (int) $size !== $byte_size) {
                return null;
            }

            $checked[$name] = [
                'path' => $path,
                'byte_size' => $byte_size,
            ];
        }

        return $checked;
    }

    /**
     * @param mixed $authorize
     * @return array{
     *   ok:true,
     *   storage_path:string,
     *   upload_intent:string,
     *   objects:array<string,array{status:string,signed_url?:string}>,
     *   paths:array<string,string>
     * }|array{ok:false,code:string,message:string}
     */
    private function operational_plan($authorize, int $client_id, int $record_id, string $operation_id): array {
        if (!is_array($authorize) || empty($authorize['ok'])) {
            return $this->fail('authorize_invalid', 'Respuesta de autorización incompleta.');
        }

        $result = $authorize['result'] ?? null;
        if (!is_array($result)) {
            return $this->fail('authorize_invalid', 'Respuesta de autorización incompleta.');
        }

        $storage_path = isset($result['storage_path']) && is_string($result['storage_path'])
            ? $result['storage_path']
            : '';
        $upload_intent = isset($result['upload_intent']) && is_string($result['upload_intent'])
            ? $result['upload_intent']
            : '';
        $objects = $result['objects'] ?? null;

        if ($storage_path === '' || $upload_intent === '' || !is_array($objects)) {
            return $this->fail('authorize_invalid', 'Respuesta de autorización incompleta.');
        }

        $parsed = ExpedienteAdjuntoVariants::parse_original_path($storage_path);
        if ($parsed === null) {
            return $this->fail('path_mismatch', 'La ruta de almacenamiento no coincide con el contexto.');
        }
        if (
            (int) $parsed['wp_client_id'] !== $client_id
            || (int) $parsed['wp_record_id'] !== $record_id
            || (string) $parsed['upload_operation_id'] !== $operation_id
        ) {
            return $this->fail('path_mismatch', 'La ruta de almacenamiento no coincide con el contexto.');
        }

        $canonical = (string) $parsed['storage_path'];
        $paths = ['original' => $canonical];
        foreach (ExpedienteAdjuntoVariants::ALLOWED_VARIANTS as $variant) {
            $derived = ExpedienteAdjuntoVariants::derive_path($canonical, $variant);
            if ($derived === null) {
                return $this->fail('path_mismatch', 'La ruta de almacenamiento no coincide con el contexto.');
            }
            $paths[$variant] = $derived;
        }

        $normalized = [];
        foreach (self::OBJECT_KEYS as $key) {
            if (!isset($objects[$key]) || !is_array($objects[$key])) {
                return $this->fail('authorize_invalid', 'Respuesta de autorización incompleta.');
            }

            $status = isset($objects[$key]['status']) ? (string) $objects[$key]['status'] : '';
            if ($status === 'already_uploaded') {
                $normalized[$key] = ['status' => 'already_uploaded'];
                continue;
            }
            if ($status !== 'pending_upload') {
                return $this->fail('authorize_invalid', 'Respuesta de autorización incompleta.');
            }

            $signed_url = isset($objects[$key]['signed_url']) && is_string($objects[$key]['signed_url'])
                ? $objects[$key]['signed_url']
                : '';
            if ($signed_url === '') {
                return $this->fail('authorize_invalid', 'Respuesta de autorización incompleta.');
            }

            $normalized[$key] = [
                'status' => 'pending_upload',
                'signed_url' => $signed_url,
            ];
        }

        return [
            'ok' => true,
            'storage_path' => $canonical,
            'upload_intent' => $upload_intent,
            'objects' => $normalized,
            'paths' => $paths,
        ];
    }

    /**
     * Interpreta un fallo cerrado de un adaptador. null = no es un fallo válido.
     *
     * @param mixed $response
     * @return array{ok:false,code:string,message:string}|null
     */
    private function closed_failure($response, string $malformed_code, string $default_message): ?array {
        if (!is_array($response)) {
            return $this->fail($malformed_code, $default_message);
        }

        if (!empty($response['ok'])) {
            return null;
        }

        $code = isset($response['code']) && is_string($response['code']) ? $response['code'] : '';
        if ($code === '') {
            return $this->fail($malformed_code, $default_message);
        }

        $message = '';
        if (isset($response['message']) && is_string($response['message'])) {
            $message = $response['message'];
        } elseif (isset($response['error']) && is_string($response['error'])) {
            $message = $response['error'];
        }
        if ($message === '') {
            $message = $default_message;
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
