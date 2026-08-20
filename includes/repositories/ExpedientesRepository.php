<?php
/**
 * Expedientes Repository — SQL puro de la entidad padre aa_expedientes.
 *
 * Paginación: el consumidor pasa limit/offset ya calculados. Sin techo
 * acumulado de 100. Búsqueda solo por title.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ExpedientesRepository {

    /**
     * @return string
     */
    private static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'aa_expedientes';
    }

    /**
     * @return string
     */
    private static function categories_table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'aa_expediente_categories';
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{
     *     id:int,
     *     title:string,
     *     description:?string,
     *     created_at:string,
     *     updated_at:?string,
     *     category:array{slug:string,name:string}
     * }|null
     */
    private static function map_list_row(?array $row): ?array {
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        $description = $row['description'] ?? null;
        $updated = $row['updated_at'] ?? null;

        return [
            'id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'description' => ($description === null || $description === '') ? null : (string) $description,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => ($updated === null || $updated === '') ? null : (string) $updated,
            'category' => [
                'slug' => (string) ($row['category_slug'] ?? ''),
                'name' => (string) ($row['category_name'] ?? ''),
            ],
        ];
    }

    /**
     * @param array{title:string,description:?string,category_id:int,created_at:string} $data
     * @return array{id:int,title:string,description:?string,created_at:string,updated_at:?string}|null
     */
    public static function insert(array $data): ?array {
        global $wpdb;

        $title = (string) ($data['title'] ?? '');
        $description = array_key_exists('description', $data) ? $data['description'] : null;
        $category_id = (int) ($data['category_id'] ?? 0);
        $created_at = (string) ($data['created_at'] ?? '');

        if ($title === '' || $category_id < 1 || $created_at === '') {
            return null;
        }

        if ($description !== null) {
            $description = (string) $description;
            if ($description === '') {
                $description = null;
            }
        }

        $table = self::table_name();
        $result = $wpdb->insert(
            $table,
            [
                'title' => $title,
                'description' => $description,
                'category_id' => $category_id,
                'created_at' => $created_at,
            ],
            ['%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            error_log('[ExpedientesRepository] insert error: ' . $wpdb->last_error);
            return null;
        }

        $id = (int) $wpdb->insert_id;
        if ($id < 1) {
            return null;
        }

        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'created_at' => $created_at,
            'updated_at' => null,
        ];
    }

    public static function count_matching(string $title_query): int {
        global $wpdb;

        $table = self::table_name();

        if ($title_query === '') {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        } else {
            $like = '%' . $wpdb->esc_like($title_query) . '%';
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE title LIKE %s",
                    $like
                )
            );
        }

        if ($wpdb->last_error) {
            error_log('[ExpedientesRepository] count_matching error: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }

    /**
     * @return list<array{
     *     id:int,
     *     title:string,
     *     description:?string,
     *     created_at:string,
     *     updated_at:?string,
     *     category:array{slug:string,name:string}
     * }>
     */
    public static function list_page(string $title_query, int $limit, int $offset): array {
        if ($limit < 1) {
            return [];
        }

        if ($offset < 0) {
            $offset = 0;
        }

        global $wpdb;
        $table = self::table_name();
        $categories_table = self::categories_table_name();

        $select = "SELECT e.id, e.title, e.description, e.created_at, e.updated_at,
                          c.slug AS category_slug, c.name AS category_name
                   FROM {$table} e
                   INNER JOIN {$categories_table} c ON c.id = e.category_id";

        if ($title_query === '') {
            $sql = $wpdb->prepare(
                $select . " ORDER BY e.created_at DESC, e.id DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        } else {
            $like = '%' . $wpdb->esc_like($title_query) . '%';
            $sql = $wpdb->prepare(
                $select . " WHERE e.title LIKE %s ORDER BY e.created_at DESC, e.id DESC LIMIT %d OFFSET %d",
                $like,
                $limit,
                $offset
            );
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error) {
            error_log('[ExpedientesRepository] list_page error: ' . $wpdb->last_error);
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mapped = self::map_list_row(is_array($row) ? $row : null);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @return array{
     *     id:int,
     *     title:string,
     *     description:?string,
     *     created_at:string,
     *     updated_at:?string,
     *     category:array{slug:string,name:string}
     * }|null
     */
    public static function find_by_id(int $id): ?array {
        if ($id < 1) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        $categories_table = self::categories_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT e.id, e.title, e.description, e.created_at, e.updated_at,
                        c.slug AS category_slug, c.name AS category_name
                 FROM {$table} e
                 INNER JOIN {$categories_table} c ON c.id = e.category_id
                 WHERE e.id = %d
                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedientesRepository] find_by_id error: ' . $wpdb->last_error);
            return null;
        }

        return self::map_list_row(is_array($row) ? $row : null);
    }

    /**
     * Existencia mínima por id (sin JOIN a categorías ni otras tablas).
     *
     * @return bool|null true si existe, false si no, null si error SQL
     */
    public static function exists_by_id(int $id) {
        if ($id < 1) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$table} WHERE id = %d LIMIT 1",
                $id
            )
        );

        if ($wpdb->last_error) {
            error_log('[ExpedientesRepository] exists_by_id error: ' . $wpdb->last_error);
            return null;
        }

        return $found !== null && $found !== false && (string) $found !== '';
    }
}
