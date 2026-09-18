<?php
/**
 * Normalizador canónico de email (ASCII MVP).
 *
 * FILTER_VALIDATE_EMAIL + restricciones explícitas; sin sanitize_email ni gramática atext.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Email_Normalizer {

    public const MAX_TOTAL_LENGTH = 254;
    public const MAX_LOCAL_LENGTH = 64;
    public const MAX_DOMAIN_LENGTH = 253;

    /**
     * @param mixed $value
     * @return array{ok:true,value:?string}|array{ok:false,error:array{code:string,message:string}}
     */
    public static function normalize($value): array {
        if ($value === null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_payload',
                    'message' => 'El correo debe ser una cadena de texto.',
                ],
            ];
        }

        if (!is_string($value)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_payload',
                    'message' => 'El correo debe ser una cadena de texto.',
                ],
            ];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [
                'ok' => true,
                'value' => null,
            ];
        }

        if (self::contains_non_ascii($trimmed)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_email',
                    'message' => 'El correo debe usar solo caracteres ASCII. Dominios o nombres Unicode no están admitidos en esta versión.',
                ],
            ];
        }

        if (strlen($trimmed) > self::MAX_TOTAL_LENGTH) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_email',
                    'message' => 'El correo supera la longitud máxima permitida.',
                ],
            ];
        }

        if (substr_count($trimmed, '@') !== 1) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_email',
                    'message' => 'El correo electrónico no es válido.',
                ],
            ];
        }

        $parts = explode('@', $trimmed, 2);
        $local = $parts[0];
        $domain = $parts[1];

        if ($local === '' || $domain === '') {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_email',
                    'message' => 'El correo electrónico no es válido.',
                ],
            ];
        }

        if (strlen($local) > self::MAX_LOCAL_LENGTH || strlen($domain) > self::MAX_DOMAIN_LENGTH) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_email',
                    'message' => 'El correo supera la longitud máxima permitida.',
                ],
            ];
        }

        if (
            strpos($local, ' ') !== false
            || strpos($domain, ' ') !== false
            || $local[0] === '"'
            || substr($local, -1) === '"'
            || $domain[0] === '"'
            || substr($domain, -1) === '"'
        ) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_email',
                    'message' => 'El correo electrónico no es válido.',
                ],
            ];
        }

        if (strpos($domain, '.') === false) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_email',
                    'message' => 'El correo electrónico no es válido.',
                ],
            ];
        }

        $domain_lc = strtolower($domain);
        $candidate = $local . '@' . $domain_lc;

        if (filter_var($candidate, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_email',
                    'message' => 'El correo electrónico no es válido.',
                ],
            ];
        }

        return [
            'ok' => true,
            'value' => $candidate,
        ];
    }

    /**
     * Re-parsea un valor persistido; null si no cumple el contrato actual.
     */
    public static function parse_stored(string $email): ?string {
        $normalized = self::normalize($email);
        if (empty($normalized['ok']) || $normalized['value'] === null) {
            return null;
        }

        return (string) $normalized['value'];
    }

    /**
     * mailto: RFC 6068 — percent-encode local y domain una vez; @ literal.
     */
    public static function mailto_href(string $email): ?string {
        $parsed = self::parse_stored($email);
        if ($parsed === null) {
            return null;
        }

        $at = strpos($parsed, '@');
        if ($at === false) {
            return null;
        }

        $local = substr($parsed, 0, $at);
        $domain = substr($parsed, $at + 1);
        if ($local === '' || $domain === '') {
            return null;
        }

        return 'mailto:' . rawurlencode($local) . '@' . rawurlencode($domain);
    }

    private static function contains_non_ascii(string $value): bool {
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            if (ord($value[$i]) >= 0x80) {
                return true;
            }
        }

        return false;
    }
}
