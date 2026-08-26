<?php
/**
 * Agenda magic-link token format (C1 base64url, 32 bytes → 43 chars).
 *
 * Pure validation — no WordPress, no network.
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

final class AA_Agenda_Access_Token_Format {

    public const LENGTH = 43;

    private const PATTERN = '/^[A-Za-z0-9_-]{43}$/';

    public static function is_valid(string $token): bool {
        if (strlen($token) !== self::LENGTH) {
            return false;
        }
        return (bool) preg_match(self::PATTERN, $token);
    }

    /**
     * @return string Trimmed token or empty when invalid.
     */
    public static function sanitize(string $raw): string {
        $token = trim($raw);
        return self::is_valid($token) ? $token : '';
    }
}
