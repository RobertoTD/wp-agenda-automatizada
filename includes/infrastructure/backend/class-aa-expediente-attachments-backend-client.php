<?php
/**
 * Backend client — Expediente attachment Storage ops (HMAC JSON) (MC4a2).
 *
 * authorize-upload / finalize / sign-read. No registra signed_url, token ni upload_intent.
 * El campo separado `token` de authorize se descarta; `signed_url` solo se entrega
 * al caller PHP interno (transporte PUT), nunca a UI.
 */

defined('ABSPATH') or die('No direct access');

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
    ];

    /**
     * @param array{
     *   upload_operation_id:string,
     *   wp_client_id:int,
     *   wp_record_id:int,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int
     * } $input
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function authorize_upload(array $input): array {
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
     * @return array{ok:true,result:array{url:string,expires_in:int}}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function sign_read(string $storage_path): array {
        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/sign-read';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'storage_path' => $storage_path,
        ]);

        return $this->parseSignReadResponse($response);
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
        $parsed = $this->parseJsonOk($response, ['status', 'upload_operation_id', 'storage_path', 'upload_intent']);
        if ($parsed['ok'] !== true) {
            return $parsed;
        }

        /** @var array<string,mixed> $result */
        $result = $parsed['result'];
        $status = (string) ($result['status'] ?? '');

        // Descartar token separado de inmediato (nunca exponerlo).
        unset($result['token']);

        if ($status === 'pending_upload') {
            if (empty($result['signed_url']) || !is_string($result['signed_url'])) {
                return $this->failure('expediente_attachments_invalid_response', '', 0);
            }
        } elseif ($status === 'already_uploaded') {
            unset($result['signed_url'], $result['token']);
        } else {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

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
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseSignReadResponse($response): array {
        return $this->parseJsonOk($response, ['url', 'expires_in']);
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
