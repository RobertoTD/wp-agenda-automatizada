<?php
/**
 * Backend client — Training status, enrollment, consent and content (HMAC).
 *
 * Delegates transport to aa_send_authenticated_request(). Does not expose
 * secrets, signatures, stack traces, or raw HTML in error payloads.
 */

defined('ABSPATH') or die('No direct access');

class AA_Training_Backend_Client {

    public const COURSE_KEY = 'fundamentos-deoia';

    public const CONSENT_SOURCE_ACCOUNT_CARD = 'account_training_card';

    private const LESSON_KEY_RE = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** @var list<string> */
    private const KNOWN_TRAINING_ERROR_PREFIX = 'training_';

    /**
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function get_status(): array {
        return $this->requestJson(
            'GET',
            '/training/status?course_key=' . rawurlencode(self::COURSE_KEY),
            null,
            ['course_key']
        );
    }

    /**
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function enroll(): array {
        return $this->requestJson(
            'POST',
            '/training/enroll',
            ['course_key' => self::COURSE_KEY],
            ['course_key']
        );
    }

    /**
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function unsubscribe(): array {
        return $this->requestJson(
            'POST',
            '/training/unsubscribe',
            ['course_key' => self::COURSE_KEY],
            ['course_key']
        );
    }

    /**
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function get_consent_status(): array {
        return $this->requestJson(
            'GET',
            '/training/consent/status?course_key=' . rawurlencode(self::COURSE_KEY),
            null,
            ['course_key', 'consent']
        );
    }

    /**
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function accept_consent(): array {
        return $this->requestJson(
            'POST',
            '/training/consent/accept',
            [
                'course_key' => self::COURSE_KEY,
                'source'     => self::CONSENT_SOURCE_ACCOUNT_CARD,
            ],
            ['course_key', 'consent']
        );
    }

    /**
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function revoke_consent(): array {
        return $this->requestJson(
            'POST',
            '/training/consent/revoke',
            ['course_key' => self::COURSE_KEY],
            ['course_key', 'consent']
        );
    }

    /**
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function get_course(): array {
        return $this->requestJson(
            'GET',
            '/training/courses/' . rawurlencode(self::COURSE_KEY),
            null,
            ['course', 'lessons']
        );
    }

    /**
     * @param string $lesson_key
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function get_lesson($lesson_key): array {
        $key = is_string($lesson_key) ? $lesson_key : '';
        if (!$this->is_valid_lesson_key($key)) {
            return $this->failure('training_content_lesson_key_invalid', '', 400);
        }

        return $this->requestJson(
            'GET',
            '/training/courses/' . rawurlencode(self::COURSE_KEY) . '/lessons/' . rawurlencode($key),
            null,
            ['course_key', 'lesson', 'blocks']
        );
    }

    /**
     * @param string $lesson_key
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function mark_lesson_opened($lesson_key): array {
        $key = is_string($lesson_key) ? $lesson_key : '';
        if (!$this->is_valid_lesson_key($key)) {
            return $this->failure('training_content_lesson_key_invalid', '', 400);
        }

        return $this->requestJson(
            'POST',
            '/training/courses/' . rawurlencode(self::COURSE_KEY)
                . '/lessons/' . rawurlencode($key) . '/opened',
            [],
            ['lesson_key', 'progress']
        );
    }

    /**
     * @param string $lesson_key
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    public function mark_lesson_completed($lesson_key): array {
        $key = is_string($lesson_key) ? $lesson_key : '';
        if (!$this->is_valid_lesson_key($key)) {
            return $this->failure('training_content_lesson_key_invalid', '', 400);
        }

        return $this->requestJson(
            'POST',
            '/training/courses/' . rawurlencode(self::COURSE_KEY)
                . '/lessons/' . rawurlencode($key) . '/completed',
            [],
            ['lesson_key', 'progress']
        );
    }

    /**
     * @param string $value
     */
    public function is_valid_lesson_key($value): bool {
        if (!is_string($value) || $value === '') {
            return false;
        }
        if (strpos($value, '/') !== false || strpos($value, '\\') !== false) {
            return false;
        }
        if (strpos($value, '..') !== false) {
            return false;
        }
        if (strlen($value) > 64) {
            return false;
        }

        return (bool) preg_match(self::LESSON_KEY_RE, $value);
    }

    /**
     * @param string               $method
     * @param string               $path_with_query Absolute path starting with /training
     * @param array<string,mixed>|null $body
     * @param list<string>         $required_result_keys Keys expected in successful payload (besides ok)
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    private function requestJson($method, $path_with_query, $body, array $required_result_keys): array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->failure('training_backend_error', '', 0);
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return $this->failure('training_backend_error', '', 0);
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . $path_with_query;

        if ($method === 'GET') {
            $response = aa_send_authenticated_request($endpoint, 'GET');
        } else {
            $response = aa_send_authenticated_request($endpoint, $method, is_array($body) ? $body : []);
        }

        return $this->parseResponse($response, $required_result_keys);
    }

    /**
     * @param array|\WP_Error $response
     * @param list<string>    $required_result_keys
     * @return array{ok: true, result: array<string,mixed>}|array{ok: false, code: string, error: string, http_status: int}
     */
    private function parseResponse($response, array $required_result_keys): array {
        if (is_wp_error($response)) {
            return $this->failure('training_backend_unreachable', '', 0);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded     = $this->decodeJsonBody($response);

        if ($status_code < 200 || $status_code >= 300) {
            if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
                $backend_error = trim((string) $decoded['error']);
                if ($this->is_known_training_error($backend_error)) {
                    return $this->failure($backend_error, '', $status_code > 0 ? $status_code : 0);
                }
            }

            if ($status_code >= 500) {
                return $this->failure('training_backend_unreachable', '', $status_code);
            }

            return $this->failure('training_backend_error', '', $status_code > 0 ? $status_code : 0);
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return $this->failure(
                'training_backend_invalid_response',
                '',
                $status_code > 0 ? $status_code : 0
            );
        }

        $result = $decoded;
        unset($result['ok']);

        foreach ($required_result_keys as $key) {
            if (!array_key_exists($key, $result)) {
                return $this->failure(
                    'training_backend_invalid_response',
                    '',
                    $status_code > 0 ? $status_code : 0
                );
            }
        }

        return [
            'ok'     => true,
            'result' => $result,
        ];
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
     * @param string $code
     */
    private function is_known_training_error($code): bool {
        if (!is_string($code) || $code === '') {
            return false;
        }

        return strpos($code, self::KNOWN_TRAINING_ERROR_PREFIX) === 0;
    }

    /**
     * @return array{ok: false, code: string, error: string, http_status: int}
     */
    private function failure(string $code, string $message, int $http_status): array {
        return [
            'ok'          => false,
            'code'        => $code,
            'error'       => $message,
            'http_status' => $http_status,
        ];
    }
}
