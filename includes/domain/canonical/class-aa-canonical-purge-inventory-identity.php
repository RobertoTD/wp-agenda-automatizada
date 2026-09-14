<?php
/**
 * Identidad de ítem de inventario de purge canónico.
 *
 * Alinea UUID v4, SHA-256, byte_size y wp_record_id con el normalizador
 * de accept del backend. No calcula fingerprint: ese hash es del servidor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Purge_Inventory_Identity {

    public const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
    public const SHA256_PATTERN = '/^[0-9a-f]{64}$/';
    public const BYTE_SIZE_MIN = 1;
    public const BYTE_SIZE_MAX = 1048576;
    public const STORAGE_PATH_MAX = 191;

    public static function normalize_upload_operation_id($raw): ?string {
        if (!is_string($raw)) {
            return null;
        }
        $value = strtolower(trim($raw));
        if ($value === '' || preg_match(self::UUID_V4_PATTERN, $value) !== 1) {
            return null;
        }

        return $value;
    }

    public static function normalize_content_sha256($raw): ?string {
        if (!is_string($raw)) {
            return null;
        }
        $value = strtolower(trim($raw));
        if (preg_match(self::SHA256_PATTERN, $value) !== 1) {
            return null;
        }

        return $value;
    }

    public static function normalize_storage_path($raw): ?string {
        if (!is_string($raw)) {
            return null;
        }
        $value = trim($raw);
        if ($value === '' || strlen($value) > self::STORAGE_PATH_MAX) {
            return null;
        }

        return $value;
    }

    public static function is_valid_wp_record_id($raw): bool {
        if (is_int($raw)) {
            return $raw >= 1;
        }
        if (is_string($raw) && preg_match('/^[1-9][0-9]*$/', $raw) === 1) {
            return true;
        }

        return false;
    }

    public static function is_valid_byte_size($raw): bool {
        if (is_int($raw)) {
            return $raw >= self::BYTE_SIZE_MIN && $raw <= self::BYTE_SIZE_MAX;
        }
        if (is_string($raw) && preg_match('/^[1-9][0-9]*$/', $raw) === 1) {
            $n = (int) $raw;
            return $n >= self::BYTE_SIZE_MIN && $n <= self::BYTE_SIZE_MAX;
        }

        return false;
    }

    public static function new_uuid_v4(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    public static function metadata_matches(array $left, array $right): bool {
        return (int) ($left['wp_record_id'] ?? 0) === (int) ($right['wp_record_id'] ?? 0)
            && strtolower((string) ($left['content_sha256'] ?? '')) === strtolower((string) ($right['content_sha256'] ?? ''))
            && (int) ($left['byte_size'] ?? 0) === (int) ($right['byte_size'] ?? 0)
            && (string) ($left['storage_path'] ?? '') === (string) ($right['storage_path'] ?? '');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *   upload_operation_id:string,
     *   wp_record_id:int,
     *   content_sha256:string,
     *   byte_size:int,
     *   storage_path:string
     * }|null
     */
    public static function try_normalize_row(array $row): ?array {
        $op = self::normalize_upload_operation_id($row['upload_operation_id'] ?? null);
        $sha = self::normalize_content_sha256($row['content_sha256'] ?? null);
        $path = self::normalize_storage_path($row['storage_path'] ?? null);
        $record_id = $row['wp_record_id'] ?? ($row['record_id'] ?? null);
        $byte_size = $row['byte_size'] ?? null;

        if ($op === null || $sha === null || $path === null) {
            return null;
        }
        if (!self::is_valid_wp_record_id($record_id) || !self::is_valid_byte_size($byte_size)) {
            return null;
        }

        return [
            'upload_operation_id' => $op,
            'wp_record_id' => (int) $record_id,
            'content_sha256' => $sha,
            'byte_size' => (int) $byte_size,
            'storage_path' => $path,
        ];
    }
}
