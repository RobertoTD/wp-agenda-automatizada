<?php
/**
 * Route parser for the read-only canonical shell.
 *
 * This is deliberately a presentation concern: it accepts only the two
 * canonical read routes and has no knowledge of Family, presets or
 * capabilities.
 */
defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Clean_Shell_Read_Route {
    public const ROOT = 'root';
    public const RECORDS = 'records';
    public const INVALID_RECORDS = 'invalid_records';

    /**
     * @return array{kind:string,page:int,container_id:int}
     */
    public static function from_query(array $query): array {
        $page = self::positive_decimal($query['page'] ?? null) ?? 1;
        $view = isset($query['view']) && is_string($query['view']) ? $query['view'] : '';

        if ($view !== self::RECORDS) {
            return ['kind' => self::ROOT, 'page' => $page, 'container_id' => 0];
        }

        $container_id = self::positive_decimal($query['container_id'] ?? null);
        if ($container_id === null) {
            return ['kind' => self::INVALID_RECORDS, 'page' => $page, 'container_id' => 0];
        }

        return ['kind' => self::RECORDS, 'page' => $page, 'container_id' => $container_id];
    }

    private static function positive_decimal($value): ?int {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || $value === '' || !ctype_digit($value)) {
            return null;
        }
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }
}
