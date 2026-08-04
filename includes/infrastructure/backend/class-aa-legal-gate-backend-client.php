<?php
/**
 * Backend client — legal gate status + terms acceptance (HMAC).
 *
 * Does not expose secrets or internal IDs (subscription_request, installation, account).
 */

defined('ABSPATH') or die('No direct access');

class AA_Legal_Gate_Backend_Client {

    /**
     * @return array{
     *     ok: true,
     *     status: string,
     *     privacy_accepted: bool,
     *     terms_accepted: bool,
     *     terms_document: array{version: string, human_url: string}|null
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int,
     *     current_version?: string|null,
     *     shown_version?: string|null
     * }
     */
    public function fetchStatus(): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->error('legal_gate_backend_error', 'AA_API_BASE_URL no está definida.', 0);
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->error(
                'legal_gate_backend_error',
                'auth-helper no disponible (aa_send_authenticated_request).',
                0
            );
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/oauth/legal-gate-status';
        $response = aa_send_authenticated_request($endpoint, 'GET');

        if (is_wp_error($response)) {
            return $this->error(
                'legal_gate_backend_unreachable',
                $response->get_error_message(),
                0
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded     = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = 'Error al consultar el estado legal.';
            if (is_array($decoded) && !empty($decoded['error']) && is_string($decoded['error'])) {
                $message = (string) $decoded['error'];
            }

            if ($status_code === 404) {
                return $this->error('legal_gate_client_not_found', $message, $status_code);
            }
            if ($status_code >= 500) {
                return $this->error('legal_gate_backend_unreachable', $message, $status_code);
            }

            return $this->error('legal_gate_backend_error', $message, $status_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || empty($decoded['ok'])) {
            return $this->error(
                'legal_gate_backend_invalid_response',
                'Respuesta del backend no es válida.',
                $status_code
            );
        }

        $status = isset($decoded['status']) && is_string($decoded['status'])
            ? trim($decoded['status'])
            : '';
        if ($status === '') {
            return $this->error(
                'legal_gate_backend_invalid_response',
                'Respuesta sin status legal.',
                $status_code
            );
        }

        $terms_document = null;
        if (isset($decoded['terms_document']) && is_array($decoded['terms_document'])) {
            $version = isset($decoded['terms_document']['version'])
                && is_string($decoded['terms_document']['version'])
                ? trim($decoded['terms_document']['version'])
                : '';
            $human_url = isset($decoded['terms_document']['human_url'])
                && is_string($decoded['terms_document']['human_url'])
                ? trim($decoded['terms_document']['human_url'])
                : '';
            if ($version !== '' && $human_url !== '') {
                $terms_document = [
                    'version'   => $version,
                    'human_url' => $human_url,
                ];
            }
        }

        return [
            'ok'                => true,
            'status'            => $status,
            'privacy_accepted'  => !empty($decoded['privacy_accepted']),
            'terms_accepted'    => !empty($decoded['terms_accepted']),
            'terms_document'    => $terms_document,
        ];
    }

    /**
     * @param array{terms_consent: bool, terms_document_version: string, wp_user_id: int} $payload
     * @return array{
     *     ok: true,
     *     already_accepted: bool,
     *     document_version: string,
     *     source: string
     * }|array{
     *     ok: false,
     *     code: string,
     *     error: string,
     *     http_status: int,
     *     current_version?: string|null,
     *     shown_version?: string|null
     * }
     */
    public function acceptTerms(array $payload): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->error('legal_gate_backend_error', 'AA_API_BASE_URL no está definida.', 0);
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->error(
                'legal_gate_backend_error',
                'auth-helper no disponible (aa_send_authenticated_request).',
                0
            );
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/oauth/legal-acceptances/terms';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'terms_consent'          => true,
            'terms_document_version' => (string) $payload['terms_document_version'],
            'wp_user_id'             => (int) $payload['wp_user_id'],
        ]);

        if (is_wp_error($response)) {
            return $this->error(
                'legal_gate_backend_unreachable',
                $response->get_error_message(),
                0
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded     = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300) {
            $code = 'legal_gate_backend_error';
            $message = 'No se pudo registrar la aceptación de Términos.';
            $extras = [];

            if (is_array($decoded) && !empty($decoded['error']) && is_string($decoded['error'])) {
                $code = (string) $decoded['error'];
                $message = $code;
            }
            if (is_array($decoded) && array_key_exists('current_version', $decoded)) {
                $extras['current_version'] = is_string($decoded['current_version'])
                    ? $decoded['current_version']
                    : null;
            }
            if (is_array($decoded) && array_key_exists('shown_version', $decoded)) {
                $extras['shown_version'] = is_string($decoded['shown_version'])
                    ? $decoded['shown_version']
                    : null;
            }

            if ($status_code === 404) {
                $code = 'legal_gate_client_not_found';
            } elseif ($status_code >= 500 && $code === 'legal_gate_backend_error') {
                $code = 'legal_gate_backend_unreachable';
            }

            return array_merge($this->error($code, $message, $status_code), $extras);
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || empty($decoded['ok'])) {
            return $this->error(
                'legal_gate_backend_invalid_response',
                'Respuesta del backend no es válida.',
                $status_code
            );
        }

        return [
            'ok'               => true,
            'already_accepted' => !empty($decoded['already_accepted']),
            'document_version' => isset($decoded['document_version']) && is_string($decoded['document_version'])
                ? $decoded['document_version']
                : '',
            'source'           => isset($decoded['source']) && is_string($decoded['source'])
                ? $decoded['source']
                : '',
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
