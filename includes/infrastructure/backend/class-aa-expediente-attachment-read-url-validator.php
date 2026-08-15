<?php
/**
 * Validación estricta de signed READ URL de Supabase Storage (MC4c).
 *
 * Formato esperado (storage-js createSignedUrl):
 *   https://<origin>/storage/v1/object/sign/expediente-adjuntos/<storage_path>?token=<JWT>
 *
 * Nota: el path de lectura es /object/sign/ (distinto del de upload
 * /object/upload/sign/ que valida el uploader). PHP nunca hace GET de esta
 * URL: solo la valida y la entrega al navegador en respuesta autenticada.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}

final class AA_Expediente_Attachment_Read_Url_Validator {

    public const BUCKET = 'expediente-adjuntos';
    private const READ_PATH_PREFIX = '/storage/v1/object/sign/';
    private const TOKEN_CHARSET_RE = '/^[A-Za-z0-9._~-]+$/';

    /**
     * @return array{ok:true,url:string}|array{ok:false,code:string}
     */
    public function validate(string $signed_url, string $canonical_original_path, string $variant): array {
        if (!ExpedienteAdjuntoVariants::is_allowed_variant($variant)) {
            return $this->fail('signed_url_path_invalid');
        }

        $expected_path = ExpedienteAdjuntoVariants::derive_path($canonical_original_path, $variant);
        if ($expected_path === null) {
            return $this->fail('storage_path_invalid');
        }

        if (!defined('AA_EXPEDIENTE_STORAGE_ORIGIN') || (string) AA_EXPEDIENTE_STORAGE_ORIGIN === '') {
            return $this->fail('storage_origin_not_configured');
        }

        $origin = wp_parse_url(rtrim((string) AA_EXPEDIENTE_STORAGE_ORIGIN, '/'));
        if (!is_array($origin) || empty($origin['scheme']) || empty($origin['host'])) {
            return $this->fail('storage_origin_invalid');
        }
        if (strtolower((string) $origin['scheme']) !== 'https') {
            return $this->fail('storage_origin_invalid');
        }

        $url = trim($signed_url);
        if ($url === '' || strpbrk($url, "\r\n") !== false || strpos($url, '\\') !== false) {
            return $this->fail('signed_url_invalid');
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
            return $this->fail('signed_url_invalid');
        }

        if (strtolower((string) $parts['scheme']) !== 'https') {
            return $this->fail('signed_url_invalid');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return $this->fail('signed_url_invalid');
        }

        if (isset($parts['fragment']) && (string) $parts['fragment'] !== '') {
            return $this->fail('signed_url_invalid');
        }

        if (!$this->origin_matches($origin, $parts)) {
            return $this->fail('signed_url_host_mismatch');
        }

        $path_check = $this->validate_read_path((string) $parts['path'], $expected_path);
        if ($path_check !== null) {
            return $path_check;
        }

        $query_check = $this->validate_query(isset($parts['query']) ? (string) $parts['query'] : '');
        if ($query_check !== null) {
            return $query_check;
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

        $origin_port = isset($origin['port']) ? (int) $origin['port'] : 443;
        $url_port = isset($parts['port']) ? (int) $parts['port'] : 443;

        return $scheme_ok && $host_ok && $origin_port === $url_port;
    }

    /**
     * @return array{ok:false,code:string}|null null si es válido.
     */
    private function validate_read_path(string $raw_path, string $storage_path): ?array {
        $storage_path = trim($storage_path);
        if (
            $storage_path === ''
            || strpos($storage_path, '..') !== false
            || strpos($storage_path, '//') !== false
            || $storage_path[0] === '/'
            || substr($storage_path, -1) === '/'
        ) {
            return $this->fail('storage_path_invalid');
        }

        $decoded = $this->decode_path_once($raw_path);
        if ($decoded === null) {
            return $this->fail('signed_url_path_invalid');
        }

        $prefix = self::READ_PATH_PREFIX . self::BUCKET . '/';
        if (strpos($decoded, $prefix) !== 0) {
            return $this->fail('signed_url_path_invalid');
        }

        $remainder = substr($decoded, strlen($prefix));
        if ($remainder === false || $remainder === '' || $remainder !== $storage_path) {
            return $this->fail('signed_url_path_invalid');
        }

        return null;
    }

    /**
     * Query permitida: exactamente un parámetro `token` no vacío.
     *
     * @return array{ok:false,code:string}|null null si es válida.
     */
    private function validate_query(string $query): ?array {
        if ($query === '') {
            return $this->fail('signed_url_query_invalid');
        }

        $pairs = explode('&', $query);
        if (count($pairs) !== 1) {
            return $this->fail('signed_url_query_invalid');
        }

        $eq_pos = strpos($pairs[0], '=');
        if ($eq_pos === false) {
            return $this->fail('signed_url_query_invalid');
        }

        $key = substr($pairs[0], 0, $eq_pos);
        $value = substr($pairs[0], $eq_pos + 1);

        if ($key !== 'token' || !is_string($value) || $value === '') {
            return $this->fail('signed_url_query_invalid');
        }

        if (!preg_match(self::TOKEN_CHARSET_RE, $value)) {
            return $this->fail('signed_url_query_invalid');
        }

        return null;
    }

    /**
     * Decodifica el path una sola vez; rechaza traversal, separadores
     * codificados y doble encoding.
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

        if (preg_match('/%2e|%2f|%5c/i', $raw_path)) {
            return null;
        }

        $decoded = rawurldecode($raw_path);
        if ($decoded === '' || $decoded[0] !== '/') {
            return null;
        }

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
     * @return array{ok:false,code:string}
     */
    private function fail(string $code): array {
        return [
            'ok' => false,
            'code' => $code,
        ];
    }
}
