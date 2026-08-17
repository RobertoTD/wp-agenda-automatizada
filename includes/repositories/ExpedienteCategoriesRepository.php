<?php
/**
 * Expediente Categories Repository — SQL puro del catálogo de categorías.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ExpedienteCategoriesRepository {

    /**
     * @return string
     */
    private static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'aa_expediente_categories';
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{id:int,slug:string,name:string,created_at:string}|null
     */
    private static function map_row(?array $row): ?array {
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @return array{id:int,slug:string,name:string,created_at:string}|null
     */
    public static function find_by_slug(string $slug): ?array {
        if ($slug === '') {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, slug, name, created_at
                 FROM {$table}
                 WHERE slug = %s
                 LIMIT 1",
                $slug
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteCategoriesRepository] find_by_slug error: ' . $wpdb->last_error);
            return null;
        }

        return self::map_row(is_array($row) ? $row : null);
    }

    /**
     * @return list<array{id:int,slug:string,name:string,created_at:string}>
     */
    public static function list_all(): array {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            "SELECT id, slug, name, created_at
             FROM {$table}
             ORDER BY slug ASC",
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteCategoriesRepository] list_all error: ' . $wpdb->last_error);
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mapped = self::map_row(is_array($row) ? $row : null);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }
}
