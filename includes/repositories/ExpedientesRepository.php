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
     * @return array{id:int,client_id:?int}|null
     */
    public static function find_owner_context_by_id(int $id): ?array {
        if ($id < 1) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, client_id FROM {$table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedientesRepository] find_owner_context_by_id error: ' . $wpdb->last_error);
            return null;
        }

        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        $client_raw = $row['client_id'] ?? null;
        $client_id = ($client_raw === null || $client_raw === '')
            ? null
            : (int) $client_raw;

        if ($client_id !== null && $client_id < 1) {
            $client_id = null;
        }

        return [
            'id' => (int) $row['id'],
            'client_id' => $client_id,
        ];
    }

    /**
     * Padre por client_id (UNIQUE). Triestado alineado con exists_by_id.
     *
     * @return array{id:int, client_id:int}|false|null
     *         array  — padre encontrado y válido
     *         false  — no existe padre (o $client_id < 1)
     *         null   — error SQL o fila almacenada malformada
     */
    public static function find_by_client_id(int $client_id) {
        if ($client_id < 1) {
            return false;
        }

        if (!class_exists('AA_Expediente_Id_Policy')) {
            require_once dirname(__DIR__) . '/domain/expediente/class-aa-expediente-id-policy.php';
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, client_id FROM {$table} WHERE client_id = %d LIMIT 1",
                $client_id
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedientesRepository] find_by_client_id error: ' . $wpdb->last_error);
            return null;
        }

        if (!is_array($row)) {
            return false;
        }

        if (!array_key_exists('id', $row) || !array_key_exists('client_id', $row)) {
            error_log('[ExpedientesRepository] find_by_client_id malformed row');
            return null;
        }

        $id = AA_Expediente_Id_Policy::normalize($row['id']);
        $owner = AA_Expediente_Id_Policy::normalize($row['client_id']);
        if ($id === null || $owner === null) {
            error_log('[ExpedientesRepository] find_by_client_id malformed row');
            return null;
        }

        return [
            'id' => $id,
            'client_id' => $owner,
        ];
    }

    /**
     * Get-or-create atómico de padre vinculado a cliente (UNIQUE client_id).
     *
     * Usa INSERT … ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id) para
     * current-read seguro bajo REPEATABLE READ (sin SELECT tras duplicate
     * en el mismo snapshot). No actualiza título/categoría/fechas del padre
     * existente. description siempre NULL en materialización.
     *
     * @return int|WP_Error id del padre
     */
    public static function get_or_create_for_client(
        int $client_id,
        string $title,
        int $category_id,
        string $created_at
    ) {
        if ($client_id < 1 || $title === '' || $category_id < 1 || $created_at === '') {
            return new WP_Error('invalid_expediente_data', 'Datos de expediente incompletos.');
        }

        global $wpdb;
        $table = self::table_name();

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (title, description, category_id, client_id, created_at)
             VALUES (%s, NULL, %d, %d, %s)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
            $title,
            $category_id,
            $client_id,
            $created_at
        );

        $result = $wpdb->query($sql);

        // false = error SQL; 0 = no-op de duplicate (válido si insert_id disponible).
        if ($result === false) {
            error_log('[ExpedientesRepository] get_or_create_for_client error: ' . $wpdb->last_error);

            return new WP_Error('db_error', 'Error al resolver el expediente del cliente.');
        }

        $id = (int) $wpdb->insert_id;
        if ($id < 1) {
            return new WP_Error('db_error', 'No se pudo obtener el ID del expediente.');
        }

        return $id;
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
                'client_id' => null,
                'created_at' => $created_at,
            ],
            ['%s', '%s', '%d', null, '%s']
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
