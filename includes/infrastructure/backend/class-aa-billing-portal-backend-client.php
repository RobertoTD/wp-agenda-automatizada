<?php
/**
 * Backend client — Stripe Billing Portal session (HMAC POST).
 *
 * Delegates transport to aa_send_authenticated_request(). Does not expose
 * secrets, raw HTTP bodies, Stripe IDs, or internal IDs to callers above infrastructure.
 */

defined('ABSPATH') or die('No direct access');

class AA_Billing_Portal_Backend_Client {

    /** @var list<string> */
    private const KNOWN_CONFLICT_ERRORS = [
        'missing_subscription',
        'sync_pending',
        'billing_unavailable',
    ];

    /**
     * @param string $return_url Server-built return URL for Stripe Customer Portal.
     * @return array{
     *     ok: true,
     *     url: string
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int
     * }
     */
    public function createSession(string $return_url): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->error(
                'billing_backend_not_configured',
                'AA_API_BASE_URL no está definida.',
                0
            );
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->error(
                'billing_backend_not_configured',
                'auth-helper no disponible (aa_send_authenticated_request).',
                0
            );
        }

        $return_url = trim($return_url);
        if ($return_url === '') {
            return $this->error(
                'billing_backend_invalid_request',
                'return_url vacío.',
                0
            );
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/oauth/billing-portal-session';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'return_url' => $return_url,
        ]);

        if (is_wp_error($response)) {
            return $this->error(
                'billing_backend_unreachable',
                $response->get_error_message(),
                0
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $decoded     = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $code = 'billing_backend_error';

            if (is_array($decoded) && !empty($decoded['error']) && is_string($decoded['error'])) {
                $backend_error = trim((string) $decoded['error']);
                if (in_array($backend_error, self::KNOWN_CONFLICT_ERRORS, true)) {
                    $code = $backend_error;
                }
            }

            return $this->error($code, '', $status_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->error(
                'billing_backend_invalid_response',
                'Respuesta del backend no es JSON válido.',
                $status_code
            );
        }

        if (empty($decoded['ok'])) {
            return $this->error(
                'billing_backend_invalid_response',
                'Respuesta del backend sin confirmación ok.',
                $status_code
            );
        }

        $url = isset($decoded['url']) && is_string($decoded['url']) ? trim($decoded['url']) : '';
        if ($url === '') {
            return $this->error(
                'billing_backend_invalid_response',
                'Respuesta del backend sin url.',
                $status_code
            );
        }

        return [
            'ok'  => true,
            'url' => $url,
        ];
    }

    /**
     * @return array{ok: false, code: string, error: string, http_status: int}
     */
    private function error(string $code, string $message, int $http_status): array {
        return [
            'ok'          => false,
            'code'        => $code,
            'error'       => $message,
            'http_status' => $http_status,
        ];
    }
}
