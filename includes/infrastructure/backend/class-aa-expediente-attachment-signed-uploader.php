<?php
/**
 * PUT seguro a signed upload URL de Supabase Storage (MC4a2).
 *
 * Origen permitido: constante AA_EXPEDIENTE_STORAGE_ORIGIN (scheme://host[:port]).
 * No infiere el origen desde signed_url. No registra URL ni token.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Expediente_Attachment_Signed_Uploader {

    public const MAX_BYTES = 1048576;
    public const TIMEOUT_SECONDS = 60;
    public const BUCKET = 'expediente-adjuntos';

    /**
     * @return array{ok:true}|array{ok:false,code:string,http_status:int}
     */
    public function put_jpeg(string $signed_url, string $binary, string $storage_path): array {
        if (strlen($binary) < 1 || strlen($binary) > self::MAX_BYTES) {
            return $this->fail('invalid_body_size', 0);
        }

        $validated = $this->validate_signed_upload_url($signed_url, $storage_path);
        if ($validated['ok'] !== true) {
            return $validated;
        }

        if (!function_exists('wp_safe_remote_request')) {
            return $this->fail('http_unavailable', 0);
        }

        $response = wp_safe_remote_request($validated['url'], [
            'method' => 'PUT',
            'timeout' => self::TIMEOUT_SECONDS,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'sslverify' => true,
            'headers' => [
                'Content-Type' => 'image/jpeg',
                'x-upsert' => 'false',
            ],
            'body' => $binary,
        ]);

        if (is_wp_error($response)) {
            return $this->fail('upload_transport_error', 0);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return $this->fail('upload_http_error', $status > 0 ? $status : 0);
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok:true,url:string}|array{ok:false,code:string,http_status:int}
     */
    public function validate_signed_upload_url(string $signed_url, string $storage_path): array {
        if (!defined('AA_EXPEDIENTE_STORAGE_ORIGIN') || (string) AA_EXPEDIENTE_STORAGE_ORIGIN === '') {
            return $this->fail('storage_origin_not_configured', 0);
        }

        $origin_raw = rtrim((string) AA_EXPEDIENTE_STORAGE_ORIGIN, '/');
        $origin = wp_parse_url($origin_raw);
        if (!is_array($origin) || empty($origin['scheme']) || empty($origin['host'])) {
            return $this->fail('storage_origin_invalid', 0);
        }
        if (strtolower((string) $origin['scheme']) !== 'https') {
            return $this->fail('storage_origin_invalid', 0);
        }

        $url = trim($signed_url);
        if ($url === '' || strpbrk($url, "\r\n") !== false) {
            return $this->fail('signed_url_invalid', 0);
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
            return $this->fail('signed_url_invalid', 0);
        }

        if (strtolower((string) $parts['scheme']) !== 'https') {
            return $this->fail('signed_url_invalid', 0);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return $this->fail('signed_url_invalid', 0);
        }

        if (isset($parts['fragment']) && (string) $parts['fragment'] !== '') {
            return $this->fail('signed_url_invalid', 0);
        }

        if (!$this->origin_matches($origin, $parts)) {
            return $this->fail('signed_url_host_mismatch', 0);
        }

        $path_check = $this->validate_upload_path((string) $parts['path'], $storage_path);
        if ($path_check['ok'] !== true) {
            return $path_check;
        }

        return [
            'ok' => true,
            'url' => $url,
        ];
    }

    /**
     * @param array<string,mixed> $origin
     * @param array<string,mixed> $parts
     */
    private function origin_matches(array $origin, array $parts): bool {
        $scheme_ok = strtolower((string) $origin['scheme']) === strtolower((string) $parts['scheme']);
        $host_ok = strtolower((string) $origin['host']) === strtolower((string) $parts['host']);

        $origin_port = isset($origin['port']) ? (int) $origin['port'] : $this->default_port((string) $origin['scheme']);
        $url_port = isset($parts['port']) ? (int) $parts['port'] : $this->default_port((string) $parts['scheme']);

        return $scheme_ok && $host_ok && $origin_port === $url_port;
    }

    private function default_port(string $scheme): int {
        return strtolower($scheme) === 'https' ? 443 : 80;
    }

    /**
     * @return array{ok:true}|array{ok:false,code:string,http_status:int}
     */
    private function validate_upload_path(string $raw_path, string $storage_path): array {
        $storage_path = trim($storage_path);
        if (
            $storage_path === ''
            || strpos($storage_path, '..') !== false
            || strpos($storage_path, '//') !== false
            || $storage_path[0] === '/'
            || substr($storage_path, -1) === '/'
        ) {
            return $this->fail('storage_path_invalid', 0);
        }

        $decoded = $this->decode_path_once($raw_path);
        if ($decoded === null) {
            return $this->fail('signed_url_path_invalid', 0);
        }

        $prefix = '/storage/v1/object/upload/sign/' . self::BUCKET . '/';
        if (strpos($decoded, $prefix) !== 0) {
            return $this->fail('signed_url_path_invalid', 0);
        }

        $remainder = substr($decoded, strlen($prefix));
        if ($remainder === false || $remainder === '' || $remainder !== $storage_path) {
            return $this->fail('signed_url_path_invalid', 0);
        }

        if (strpos($remainder, '..') !== false || strpos($remainder, '//') !== false) {
            return $this->fail('signed_url_path_invalid', 0);
        }

        return ['ok' => true];
    }

    /**
     * Decodifica el path una sola vez; rechaza doble codificación y %2f ambiguo en segmentos.
     *
     * @return string|null
     */
    private function decode_path_once(string $raw_path): ?string {
        if ($raw_path === '' || $raw_path[0] !== '/') {
            return null;
        }

        if (strpos($raw_path, '\\') !== false) {
            return null;
        }

        // Rechazar %2f / %2e traversal encoded antes de decodificar.
        if (preg_match('/%2e|%2f|%5c/i', $raw_path)) {
            return null;
        }

        $decoded = rawurldecode($raw_path);
        if ($decoded === '' || $decoded[0] !== '/') {
            return null;
        }

        // Doble codificación: si decodificar otra vez cambia el string, rechazar.
        $decoded_twice = rawurldecode($decoded);
        if ($decoded_twice !== $decoded) {
            return null;
        }

        if (strpos($decoded, '..') !== false || strpos($decoded, '//') !== false) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array{ok:false,code:string,http_status:int}
     */
    private function fail(string $code, int $http_status): array {
        return [
            'ok' => false,
            'code' => $code,
            'http_status' => $http_status,
        ];
    }
}
