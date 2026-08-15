<?php
/**
 * Backend client — Expediente attachment Storage ops (HMAC JSON) (MC4a2/MC5c1).
 *
 * authorize-upload / finalize / sign-read / delete. No registra signed_url, token ni upload_intent.
 * El campo separado `token` de authorize se descarta; `signed_url` solo vive
 * dentro de `objects` pendientes para el transporte PUT interno, nunca en UI.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}

class AA_Expediente_Attachments_Backend_Client {

    /** @var list<string> */
    private const KNOWN_ERRORS = [
        'unauthorized',
        'invalid_operation_id',
        'invalid_image_meta',
        'upload_intent_invalid',
        'path_invalid',
        'path_forbidden',
        'installation_missing',
        'object_missing',
        'object_mismatch',
        'sign_failed',
        'delete_failed',
        'storage_not_included',
        'storage_quota_exceeded',
        'invalid_usage_report',
        'manifest_version_invalid',
        'invalid_variant_meta',
        'variant_bytes_exceeded',
        'variant_invalid',
    ];

    /** @var list<string> */
    private const OBJECT_KEYS = ['original', 'summary', 'gallery', 'display'];

    /**
     * @param array{
     *   upload_operation_id:string,
     *   wp_client_id:int,
     *   wp_record_id:int,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   used_bytes:int,
     *   variants_manifest_version:int,
     *   variant_byte_sizes:array{summary:int,gallery:int,display:int}
     * } $input
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function authorize_upload(array $input): array {
        $manifest = $input['variants_manifest_version'] ?? null;
        if (
            !isset($input['variants_manifest_version'])
            || !is_int($manifest)
            || $manifest !== ExpedienteAdjuntoVariants::MANIFEST_VERSION
        ) {
            return $this->failure('manifest_version_invalid', '', 0);
        }

        $variant_sizes = $this->normalize_variant_byte_sizes($input['variant_byte_sizes'] ?? null);
        if ($variant_sizes === null) {
            return $this->failure('invalid_variant_meta', '', 0);
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $payload = [
            'upload_operation_id' => (string) ($input['upload_operation_id'] ?? ''),
            'wp_client_id' => (int) ($input['wp_client_id'] ?? 0),
            'wp_record_id' => (int) ($input['wp_record_id'] ?? 0),
            'mime_type' => (string) ($input['mime_type'] ?? ''),
            'byte_size' => (int) ($input['byte_size'] ?? 0),
            'width' => (int) ($input['width'] ?? 0),
            'height' => (int) ($input['height'] ?? 0),
            'used_bytes' => (int) ($input['used_bytes'] ?? 0),
            'variants_manifest_version' => ExpedienteAdjuntoVariants::MANIFEST_VERSION,
            'variant_byte_sizes' => $variant_sizes,
        ];

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/authorize-upload';
        $response = aa_send_authenticated_request($endpoint, 'POST', $payload);

        return $this->parseAuthorizeResponse($response);
    }

    /**
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function finalize(string $upload_intent): array {
        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/finalize';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'upload_intent' => $upload_intent,
        ]);

        return $this->parseFinalizeResponse($response);
    }

    /**
     * @param mixed $variant
     * @return array{ok:true,result:array{url:string,expires_in:int,variant:string}}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function sign_read(string $storage_path, $variant): array {
        if (!is_string($variant) || !ExpedienteAdjuntoVariants::is_allowed_variant($variant)) {
            return $this->failure('variant_invalid', '', 0);
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/sign-read';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'storage_path' => $storage_path,
            'variant' => $variant,
        ]);

        return $this->parseSignReadResponse($response, $variant);
    }

    /**
     * MC5c1: elimina un objeto privado. Éxito solo con status deleted|already_absent.
     *
     * @return array{ok:true,result:array{status:string}}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function delete_object(string $storage_path): array {
        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/delete';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'storage_path' => $storage_path,
        ]);

        return $this->parseDeleteResponse($response);
    }

    /**
     * @return array{ok:false,code:string,error:string,http_status:int}|null
     */
    private function preflight(): ?array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->failure('expediente_attachments_unreachable', '', 0);
        }
        if (!function_exists('aa_send_authenticated_request')) {
            return $this->failure('expediente_attachments_unreachable', '', 0);
        }
        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure('expediente_attachments_unreachable', '', 0);
        }

        return null;
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseAuthorizeResponse($response): array {
        $parsed = $this->parseJsonOk($response, [
            'variants_manifest_version',
            'upload_operation_id',
            'storage_path',
            'upload_intent',
            'objects',
        ]);
        if ($parsed['ok'] !== true) {
            return $parsed;
        }

        /** @var array<string,mixed> $result */
        $result = $parsed['result'];
        unset($result['token']);

        if (array_key_exists('status', $result) || array_key_exists('signed_url', $result)) {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        $manifest = $result['variants_manifest_version'] ?? null;
        $operation_id = $result['upload_operation_id'] ?? null;
        $storage_path = $result['storage_path'] ?? null;
        $upload_intent = $result['upload_intent'] ?? null;
        $objects = $result['objects'] ?? null;

        if (
            !is_int($manifest)
            || $manifest !== ExpedienteAdjuntoVariants::MANIFEST_VERSION
            || !is_string($operation_id) || $operation_id === ''
            || !is_string($storage_path) || $storage_path === ''
            || !is_string($upload_intent) || $upload_intent === ''
            || !is_array($objects)
        ) {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        $normalized_objects = $this->normalize_authorize_objects($objects);
        if ($normalized_objects === null) {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        $result['objects'] = $normalized_objects;

        return [
            'ok' => true,
            'result' => $result,
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseFinalizeResponse($response): array {
        return $this->parseJsonOk($response, [
            'storage_path',
            'upload_operation_id',
            'installation_id',
            'mime_type',
            'byte_size',
            'width',
            'height',
        ]);
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok:true,result:array{url:string,expires_in:int,variant:string}}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseSignReadResponse($response, string $variant): array {
        $parsed = $this->parseJsonOk($response, ['url', 'expires_in', 'variant']);
        if ($parsed['ok'] !== true) {
            return $parsed;
        }

        /** @var array<string,mixed> $result */
        $result = $parsed['result'];
        $url = $result['url'] ?? null;
        $expires_in = $result['expires_in'] ?? null;
        $got_variant = $result['variant'] ?? null;

        if (
            !is_string($url) || $url === ''
            || !is_int($expires_in) || $expires_in < 1
            || !is_string($got_variant)
            || $got_variant !== $variant
        ) {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        return [
            'ok' => true,
            'result' => [
                'url' => $url,
                'expires_in' => $expires_in,
                'variant' => $got_variant,
            ],
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok:true,result:array{status:string}}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseDeleteResponse($response): array {
        $parsed = $this->parseJsonOk($response, ['status']);
        if ($parsed['ok'] !== true) {
            return $parsed;
        }

        /** @var array<string,mixed> $result */
        $result = $parsed['result'];
        $status = (string) ($result['status'] ?? '');
        if ($status !== 'deleted' && $status !== 'already_absent') {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        return [
            'ok' => true,
            'result' => ['status' => $status],
        ];
    }

    /**
     * @param mixed $value
     * @return array{summary:int,gallery:int,display:int}|null
     */
    private function normalize_variant_byte_sizes($value): ?array {
        if (!is_array($value) || array_is_list($value)) {
            return null;
        }

        $expected = ExpedienteAdjuntoVariants::ALLOWED_VARIANTS;
        if (count($value) !== count($expected)) {
            return null;
        }

        $sizes = [];
        foreach ($expected as $key) {
            if (!array_key_exists($key, $value) || !is_int($value[$key]) || $value[$key] < 1) {
                return null;
            }
            $sizes[$key] = $value[$key];
        }

        foreach (array_keys($value) as $key) {
            if (!in_array($key, $expected, true)) {
                return null;
            }
        }

        return $sizes;
    }

    /**
     * @param array<string,mixed> $objects
     * @return array<string,array{status:string,signed_url?:string}>|null
     */
    private function normalize_authorize_objects(array $objects): ?array {
        if (count($objects) !== count(self::OBJECT_KEYS)) {
            return null;
        }

        $normalized = [];
        foreach (self::OBJECT_KEYS as $key) {
            if (!array_key_exists($key, $objects) || !is_array($objects[$key])) {
                return null;
            }

            $entry = $objects[$key];
            unset($entry['token']);

            $status = isset($entry['status']) ? (string) $entry['status'] : '';
            if ($status === 'pending_upload') {
                $signed_url = $entry['signed_url'] ?? null;
                if (!is_string($signed_url) || $signed_url === '') {
                    return null;
                }
                $normalized[$key] = [
                    'status' => 'pending_upload',
                    'signed_url' => $signed_url,
                ];
                continue;
            }

            if ($status === 'already_uploaded') {
                if (array_key_exists('signed_url', $entry)) {
                    return null;
                }
                $normalized[$key] = ['status' => 'already_uploaded'];
                continue;
            }

            return null;
        }

        foreach (array_keys($objects) as $key) {
            if (!in_array($key, self::OBJECT_KEYS, true)) {
                return null;
            }
        }

        return $normalized;
    }

    /**
     * @param array|\WP_Error $response
     * @param list<string> $required_keys
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseJsonOk($response, array $required_keys): array {
        if (is_wp_error($response)) {
            return $this->failure('expediente_attachments_unreachable', '', 0);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = $this->decode_json_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
                $backend_error = trim((string) $decoded['error']);
                if (in_array($backend_error, self::KNOWN_ERRORS, true)) {
                    return $this->failure($backend_error, '', $status_code > 0 ? $status_code : 0);
                }
            }

            if ($status_code >= 500) {
                return $this->failure('expediente_attachments_unreachable', '', $status_code);
            }

            return $this->failure('expediente_attachments_backend_error', '', $status_code > 0 ? $status_code : 0);
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return $this->failure(
                'expediente_attachments_invalid_response',
                '',
                $status_code > 0 ? $status_code : 0
            );
        }

        $result = $decoded;
        unset($result['ok']);

        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $result)) {
                return $this->failure(
                    'expediente_attachments_invalid_response',
                    '',
                    $status_code > 0 ? $status_code : 0
                );
            }
        }

        return [
            'ok' => true,
            'result' => $result,
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array<string,mixed>|null
     */
    private function decode_json_body($response): ?array {
        $raw_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($raw_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array{ok:false,code:string,error:string,http_status:int}
     */
    private function failure(string $code, string $error, int $http_status): array {
        return [
            'ok' => false,
            'code' => $code,
            'error' => $error,
            'http_status' => $http_status,
        ];
    }
}
