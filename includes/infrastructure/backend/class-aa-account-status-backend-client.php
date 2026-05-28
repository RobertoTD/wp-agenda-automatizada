<?php
/**
 * Backend client — read-only account/subscription status (HMAC GET).
 *
 * Delegates transport to aa_send_authenticated_request(). Does not expose
 * secrets, raw HTTP bodies, or internal IDs to callers above infrastructure.
 */

defined('ABSPATH') or die('No direct access');

class AA_Account_Status_Backend_Client {

    /**
     * @return array{
     *     ok: true,
     *     account_status: array<string,mixed>
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int
     * }
     */
    public function fetch(): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->error(
                'account_backend_not_configured',
                'AA_API_BASE_URL no está definida.',
                0
            );
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->error(
                'account_backend_not_configured',
                'auth-helper no disponible (aa_send_authenticated_request).',
                0
            );
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/oauth/account-status';
        $response = aa_send_authenticated_request($endpoint, 'GET');

        if (is_wp_error($response)) {
            return $this->error(
                'account_backend_unreachable',
                $response->get_error_message(),
                0
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $decoded     = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = 'Error al consultar el estado de cuenta.';
            if (is_array($decoded) && !empty($decoded['error']) && is_string($decoded['error'])) {
                $message = (string) $decoded['error'];
            }

            return $this->error('account_backend_error', $message, $status_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->error(
                'account_backend_invalid_response',
                'Respuesta del backend no es JSON válido.',
                $status_code
            );
        }

        if (empty($decoded['ok']) || !isset($decoded['account_status']) || !is_array($decoded['account_status'])) {
            return $this->error(
                'account_backend_invalid_response',
                'Respuesta del backend sin account_status.',
                $status_code
            );
        }

        return [
            'ok'             => true,
            'account_status' => $decoded['account_status'],
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
