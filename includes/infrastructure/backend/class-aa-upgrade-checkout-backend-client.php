<?php
/**
 * Backend client — Stripe upgrade checkout session (HMAC POST).
 *
 * Delegates transport to aa_send_authenticated_request(). Does not expose
 * secrets, raw HTTP bodies, Stripe IDs, or internal IDs to callers above infrastructure.
 */

defined('ABSPATH') or die('No direct access');

class AA_Upgrade_Checkout_Backend_Client {

    /** @var list<string> */
    private const KNOWN_CONFLICT_ERRORS = [
        'upgrade_unavailable',
        'missing_installation',
        'missing_subscription',
        'missing_account',
        'missing_customer_email',
        'installation_mismatch',
        'sync_pending',
    ];

    /**
     * @param string $return_url Server-built return URL for Stripe Checkout.
     * @return array{
     *     ok: true,
     *     checkout_url: string
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int,
     *     reason?: string|null
     * }
     */
    public function createSession(string $return_url): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->error(
                'upgrade_backend_not_configured',
                'AA_API_BASE_URL no está definida.',
                0
            );
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->error(
                'upgrade_backend_not_configured',
                'auth-helper no disponible (aa_send_authenticated_request).',
                0
            );
        }

        $return_url = trim($return_url);
        if ($return_url === '') {
            return $this->error(
                'upgrade_backend_invalid_request',
                'return_url vacío.',
                0
            );
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/oauth/upgrade-checkout-session';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'return_url' => $return_url,
        ]);

        if (is_wp_error($response)) {
            return $this->error(
                'upgrade_backend_unreachable',
                $response->get_error_message(),
                0
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $decoded     = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $code   = 'upgrade_backend_error';
            $reason = null;

            if (is_array($decoded)) {
                if (!empty($decoded['error']) && is_string($decoded['error'])) {
                    $backend_error = trim((string) $decoded['error']);
                    if (in_array($backend_error, self::KNOWN_CONFLICT_ERRORS, true)) {
                        $code = $backend_error;
                    }
                }
                if (isset($decoded['reason']) && is_scalar($decoded['reason'])) {
                    $trimmed = trim((string) $decoded['reason']);
                    $reason  = $trimmed === '' ? null : $trimmed;
                }
            }

            $out = [
                'ok'          => false,
                'code'        => $code,
                'error'       => '',
                'http_status' => $status_code,
            ];
            if ($reason !== null) {
                $out['reason'] = $reason;
            }

            return $out;
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->error(
                'upgrade_backend_invalid_response',
                'Respuesta del backend no es JSON válido.',
                $status_code
            );
        }

        if (empty($decoded['ok'])) {
            return $this->error(
                'upgrade_backend_invalid_response',
                'Respuesta del backend sin confirmación ok.',
                $status_code
            );
        }

        $checkout_url = isset($decoded['checkout_url']) && is_string($decoded['checkout_url'])
            ? trim($decoded['checkout_url'])
            : '';
        if ($checkout_url === '') {
            return $this->error(
                'upgrade_backend_invalid_response',
                'Respuesta del backend sin checkout_url.',
                $status_code
            );
        }

        return [
            'ok'           => true,
            'checkout_url' => $checkout_url,
        ];
    }

    /**
     * @return array{ok: false, code: string, error: string, http_status: int, reason?: string|null}
     */
    private function error(string $code, string $message, int $http_status, ?string $reason = null): array {
        $out = [
            'ok'          => false,
            'code'        => $code,
            'error'       => $message,
            'http_status' => $http_status,
        ];
        if ($reason !== null) {
            $out['reason'] = $reason;
        }

        return $out;
    }
}
