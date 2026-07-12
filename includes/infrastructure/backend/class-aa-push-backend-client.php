<?php
/**
 * Backend client — Web Push subscription registration and VAPID public key (HMAC).
 *
 * Delegates transport to aa_send_authenticated_request(). Does not expose
 * secrets, raw HTTP bodies, or subscription material to callers above infrastructure.
 */

defined('ABSPATH') or die('No direct access');

class AA_Push_Backend_Client {

    /** @var list<string> */
    private const KNOWN_FUNCTIONAL_ERRORS = [
        'invalid_subscription',
        'no_installation_id',
        'endpoint_conflict',
        'invalid_appointment_id',
        'invalid_enabled',
        'invalid_appointment_start',
        'invalid_minutes',
        'invalid_task_id',
        'invalid_execution_available_at',
    ];

    /**
     * @param array{endpoint: string, keys: array{p256dh: string, auth: string}} $subscription
     * @return array{
     *     ok: true,
     *     registration: string,
     *     first_test: array<string,mixed>
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int
     * }
     */
    public function registerSubscription(array $subscription): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->unavailable('AA_API_BASE_URL no está definida.');
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->unavailable('auth-helper no disponible (aa_send_authenticated_request).');
        }

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->unavailable('Falta el client secret del backend.');
        }

        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        $p256dh = trim((string) ($subscription['keys']['p256dh'] ?? ''));
        $auth = trim((string) ($subscription['keys']['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return [
                'ok'          => false,
                'code'        => 'invalid_subscription',
                'error'       => '',
                'http_status' => 400,
            ];
        }

        $payload = [
            'endpoint' => $endpoint,
            'keys'     => [
                'p256dh' => $p256dh,
                'auth'   => $auth,
            ],
        ];

        $endpoint_url = rtrim((string) AA_API_BASE_URL, '/') . '/push/subscriptions';
        $response     = aa_send_authenticated_request($endpoint_url, 'POST', $payload);

        return $this->parseRegisterResponse($response);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{
     *     ok: true,
     *     sync: string
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int
     * }
     */
    public function syncUpcomingConfirmedJob(array $payload): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->unavailable('AA_API_BASE_URL no está definida.');
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->unavailable('auth-helper no disponible (aa_send_authenticated_request).');
        }

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->unavailable('Falta el client secret del backend.');
        }

        $endpoint_url = rtrim((string) AA_API_BASE_URL, '/') . '/push/upcoming-confirmed-jobs/sync';
        $response     = aa_send_authenticated_request($endpoint_url, 'POST', $payload);

        return $this->parseSyncUpcomingConfirmedJobResponse($response);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{
     *     ok: true,
     *     sync: string
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int
     * }
     */
    public function syncTaskExecutionAvailableJob(array $payload): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->unavailable('AA_API_BASE_URL no está definida.');
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->unavailable('auth-helper no disponible (aa_send_authenticated_request).');
        }

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->unavailable('Falta el client secret del backend.');
        }

        $endpoint_url = rtrim((string) AA_API_BASE_URL, '/') . '/push/task-execution-available-jobs/sync';
        $response     = aa_send_authenticated_request($endpoint_url, 'POST', $payload);

        return $this->parseSyncTaskExecutionAvailableJobResponse($response);
    }

    /**
     * @return array{
     *     ok: true,
     *     vapid_public_key: string
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int
     * }
     */
    public function getVapidPublicKey(): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->unavailable('AA_API_BASE_URL no está definida.');
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->unavailable('auth-helper no disponible (aa_send_authenticated_request).');
        }

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->unavailable('Falta el client secret del backend.');
        }

        $endpoint_url = rtrim((string) AA_API_BASE_URL, '/') . '/push/vapid-public-key';
        $response     = aa_send_authenticated_request($endpoint_url, 'GET');

        return $this->parseVapidPublicKeyResponse($response);
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok: true, sync: string}|array{ok: false, code: string, error: string, http_status: int}
     */
    private function parseSyncTaskExecutionAvailableJobResponse($response): array {
        if (is_wp_error($response)) {
            return $this->unavailable($response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded     = $this->decodeJsonBody($response);

        if ($status_code < 200 || $status_code >= 300) {
            if (is_array($decoded) && !empty($decoded['error']) && is_string($decoded['error'])) {
                $backend_error = trim((string) $decoded['error']);
                if (in_array($backend_error, self::KNOWN_FUNCTIONAL_ERRORS, true)) {
                    return [
                        'ok'          => false,
                        'code'        => $backend_error,
                        'error'       => '',
                        'http_status' => $status_code > 0 ? $status_code : 503,
                    ];
                }
            }

            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        $sync = isset($decoded['sync']) && is_string($decoded['sync'])
            ? trim($decoded['sync'])
            : '';

        if ($sync === '') {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        return [
            'ok'   => true,
            'sync' => $sync,
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok: true, sync: string}|array{ok: false, code: string, error: string, http_status: int}
     */
    private function parseSyncUpcomingConfirmedJobResponse($response): array {
        if (is_wp_error($response)) {
            return $this->unavailable($response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded     = $this->decodeJsonBody($response);

        if ($status_code < 200 || $status_code >= 300) {
            if (is_array($decoded) && !empty($decoded['error']) && is_string($decoded['error'])) {
                $backend_error = trim((string) $decoded['error']);
                if (in_array($backend_error, self::KNOWN_FUNCTIONAL_ERRORS, true)) {
                    return [
                        'ok'          => false,
                        'code'        => $backend_error,
                        'error'       => '',
                        'http_status' => $status_code > 0 ? $status_code : 503,
                    ];
                }
            }

            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        $sync = isset($decoded['sync']) && is_string($decoded['sync'])
            ? trim($decoded['sync'])
            : '';

        if ($sync === '') {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        return [
            'ok'   => true,
            'sync' => $sync,
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok: true, registration: string, first_test: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    private function parseRegisterResponse($response): array {
        if (is_wp_error($response)) {
            return $this->unavailable($response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded     = $this->decodeJsonBody($response);

        if ($status_code < 200 || $status_code >= 300) {
            $code = 'push_backend_unavailable';

            if (is_array($decoded) && !empty($decoded['error']) && is_string($decoded['error'])) {
                $backend_error = trim((string) $decoded['error']);
                if (in_array($backend_error, self::KNOWN_FUNCTIONAL_ERRORS, true)) {
                    return [
                        'ok'          => false,
                        'code'        => $backend_error,
                        'error'       => '',
                        'http_status' => $status_code > 0 ? $status_code : 503,
                    ];
                }
            }

            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        $registration = isset($decoded['registration']) && is_string($decoded['registration'])
            ? trim($decoded['registration'])
            : '';
        $first_test = isset($decoded['first_test']) && is_array($decoded['first_test'])
            ? $decoded['first_test']
            : null;

        if ($registration === '' || $first_test === null) {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        return [
            'ok'           => true,
            'registration' => $registration,
            'first_test'   => $this->sanitizeFirstTest($first_test),
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok: true, vapid_public_key: string}|array{ok: false, code: string, error: string, http_status: int}
     */
    private function parseVapidPublicKeyResponse($response): array {
        if (is_wp_error($response)) {
            return $this->unavailable($response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded     = $this->decodeJsonBody($response);

        if ($status_code < 200 || $status_code >= 300) {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        $public_key = isset($decoded['vapid_public_key']) && is_string($decoded['vapid_public_key'])
            ? trim($decoded['vapid_public_key'])
            : '';

        if ($public_key === '') {
            return $this->unavailable('', $status_code > 0 ? $status_code : 503);
        }

        return [
            'ok'               => true,
            'vapid_public_key' => $public_key,
        ];
    }

    /**
     * @param array<string,mixed> $first_test
     * @return array<string,mixed>
     */
    private function sanitizeFirstTest(array $first_test): array {
        $status = isset($first_test['status']) && is_string($first_test['status'])
            ? trim($first_test['status'])
            : '';

        $out = ['status' => $status];

        if (
            isset($first_test['reason'])
            && is_string($first_test['reason'])
            && trim($first_test['reason']) !== ''
        ) {
            $out['reason'] = trim($first_test['reason']);
        }

        return $out;
    }

    /**
     * @param array|\WP_Error $response
     * @return array<string,mixed>|null
     */
    private function decodeJsonBody($response): ?array {
        $raw_body = wp_remote_retrieve_body($response);
        $decoded  = json_decode($raw_body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array{ok: false, code: string, error: string, http_status: int}
     */
    private function unavailable(string $message = '', int $http_status = 503): array {
        return [
            'ok'          => false,
            'code'        => 'push_backend_unavailable',
            'error'       => $message,
            'http_status' => $http_status,
        ];
    }
}
