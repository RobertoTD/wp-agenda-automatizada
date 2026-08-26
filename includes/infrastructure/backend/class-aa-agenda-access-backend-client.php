<?php
/**
 * Backend client — agenda magic-link consume (HMAC POST /agenda/access/consume).
 *
 * Single request, no retries. Does not log tokens or raw bodies.
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

class AA_Agenda_Access_Backend_Client {

    public const CODE_OK = 'ok';
    public const CODE_INVALID_OR_EXPIRED = 'invalid_or_expired';
    public const CODE_UNAVAILABLE = 'unavailable';
    public const CODE_NOT_CONFIGURED = 'not_configured';
    public const CODE_INVALID_RESPONSE = 'invalid_response';

    /**
     * @return array{
     *     ok: true,
     *     email: string,
     *     wp_user_id: int|null,
     *     purpose: string
     * }|array{
     *     ok: false,
     *     code: string,
     *     http_status: int
     * }
     */
    public function consume(string $token): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->failure(self::CODE_NOT_CONFIGURED, 0);
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->failure(self::CODE_NOT_CONFIGURED, 0);
        }

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure(self::CODE_NOT_CONFIGURED, 0);
        }

        $token = trim($token);
        if ($token === '') {
            return $this->failure(self::CODE_INVALID_OR_EXPIRED, 0);
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/agenda/access/consume';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'token' => $token,
        ]);

        if (is_wp_error($response)) {
            return $this->failure(self::CODE_UNAVAILABLE, 0);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = (string) wp_remote_retrieve_body($response);
        $decoded     = json_decode($raw_body, true);

        if ($status_code === 401) {
            return $this->failure(self::CODE_INVALID_OR_EXPIRED, 401);
        }

        if ($status_code < 200 || $status_code >= 300) {
            return $this->failure(self::CODE_UNAVAILABLE, $status_code);
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return $this->failure(self::CODE_INVALID_RESPONSE, $status_code);
        }

        $email = isset($decoded['email']) && is_string($decoded['email'])
            ? strtolower(trim($decoded['email']))
            : '';
        if ($email === '' || strpos($email, '@') === false) {
            return $this->failure(self::CODE_INVALID_RESPONSE, $status_code);
        }

        $wp_user_id = null;
        if (array_key_exists('wp_user_id', $decoded) && $decoded['wp_user_id'] !== null) {
            if (is_int($decoded['wp_user_id']) && $decoded['wp_user_id'] > 0) {
                $wp_user_id = $decoded['wp_user_id'];
            } elseif (is_string($decoded['wp_user_id']) && ctype_digit($decoded['wp_user_id'])) {
                $n = (int) $decoded['wp_user_id'];
                $wp_user_id = $n > 0 ? $n : null;
            } else {
                return $this->failure(self::CODE_INVALID_RESPONSE, $status_code);
            }
        }

        $purpose = isset($decoded['purpose']) && is_string($decoded['purpose'])
            ? sanitize_key($decoded['purpose'])
            : '';

        return [
            'ok'         => true,
            'email'      => $email,
            'wp_user_id' => $wp_user_id,
            'purpose'    => $purpose,
        ];
    }

    /**
     * @return array{ok: false, code: string, http_status: int}
     */
    private function failure(string $code, int $http_status): array {
        return [
            'ok'          => false,
            'code'        => $code,
            'http_status' => $http_status,
        ];
    }
}
