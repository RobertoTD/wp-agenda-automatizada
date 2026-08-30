<?php
/**
 * Finance Container Repository — SQL puro para contenedores de Finanzas (aa_finance_containers).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class FinanceContainerRepository {

    public const PAGE_SIZE = 15;

    /**
     * @return string
     */
    private static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'aa_finance_containers';
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{
     *     id: int,
     *     variant_key: string,
     *     title: string,
     *     details: ?string,
     *     created_at: string
     * }|null
     */
    private static function map_row(?array $row): ?array {
        if (!is_array($row) || !isset($row['id']) || (int) $row['id'] < 1) {
            return null;
        }

        $details = $row['details'] ?? null;

        return [
            'id' => (int) $row['id'],
            'variant_key' => (string) ($row['variant_key'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'details' => ($details === null || $details === '') ? null : (string) $details,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * Crea un nuevo contenedor financiero.
     *
     * @param string $variant_key Clave de variante canónica.
     * @param string $title Título del contenedor.
     * @param string|null $details Detalles opcionales del contenedor.
     * @return array{
     *     id: int,
     *     variant_key: string,
     *     title: string,
     *     details: ?string,
     *     created_at: string
     * }
     * @throws \InvalidArgumentException Si $variant_key o $title son inválidos.
     * @throws \RuntimeException Si la inserción SQL falla.
     */
    public static function create(string $variant_key, string $title, ?string $details = null): array {
        if ($variant_key === '') {
            throw new \InvalidArgumentException('[FinanceContainerRepository] variant_key no puede estar vacío');
        }

        if ($title === '') {
            throw new \InvalidArgumentException('[FinanceContainerRepository] title no puede estar vacío');
        }

        global $wpdb;
        $table = self::table_name();
        $now = current_time('mysql');

        $data = [
            'variant_key' => $variant_key,
            'title' => $title,
            'details' => $details,
            'created_at' => $now,
        ];

        $formats = [
            '%s',
            '%s',
            $details === null ? null : '%s',
            '%s',
        ];

        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false || !empty($wpdb->last_error)) {
            error_log('[FinanceContainerRepository] create error: ' . ($wpdb->last_error ?: 'insert failed'));
            throw new \RuntimeException('[FinanceContainerRepository] Error al crear el contenedor financiero');
        }

        $id = (int) $wpdb->insert_id;
        if ($id < 1) {
            throw new \RuntimeException('[FinanceContainerRepository] Error al obtener ID del contenedor creado');
        }

        return [
            'id' => $id,
            'variant_key' => $variant_key,
            'title' => $title,
            'details' => $details,
            'created_at' => $now,
        ];
    }

    /**
     * Busca un contenedor por su ID primario.
     *
     * @param int $id
     * @return array{
     *     id: int,
     *     variant_key: string,
     *     title: string,
     *     details: ?string,
     *     created_at: string
     * }|null Devuelve el contenedor o null si no existe.
     * @throws \InvalidArgumentException Si $id < 1.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function find_by_id(int $id): ?array {
        if ($id < 1) {
            throw new \InvalidArgumentException('[FinanceContainerRepository] id debe ser mayor o igual a 1');
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, variant_key, title, details, created_at
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if (!empty($wpdb->last_error)) {
            error_log('[FinanceContainerRepository] find_by_id error: ' . $wpdb->last_error);
            throw new \RuntimeException('[FinanceContainerRepository] Error al consultar el contenedor financiero');
        }

        return self::map_row(is_array($row) ? $row : null);
    }

    /**
     * Lista contenedores de una variante con paginación fija de 15 en orden created_at DESC, id DESC.
     *
     * @param string $variant_key Clave de variante.
     * @param int $page Número de página (1-based).
     * @return list<array{
     *     id: int,
     *     variant_key: string,
     *     title: string,
     *     details: ?string,
     *     created_at: string
     * }>
     * @throws \InvalidArgumentException Si $variant_key está vacío o $page < 1.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function list_by_variant(string $variant_key, int $page = 1): array {
        if ($variant_key === '') {
            throw new \InvalidArgumentException('[FinanceContainerRepository] variant_key no puede estar vacío');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('[FinanceContainerRepository] page debe ser mayor o igual a 1');
        }

        global $wpdb;
        $table = self::table_name();
        $offset = ($page - 1) * self::PAGE_SIZE;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, variant_key, title, details, created_at
                 FROM {$table}
                 WHERE variant_key = %s
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $variant_key,
                self::PAGE_SIZE,
                $offset
            ),
            ARRAY_A
        );

        if (!empty($wpdb->last_error)) {
            error_log('[FinanceContainerRepository] list_by_variant error: ' . $wpdb->last_error);
            throw new \RuntimeException('[FinanceContainerRepository] Error al listar contenedores financieros');
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

    /**
     * Cuenta el total de contenedores para una variante.
     *
     * @param string $variant_key Clave de variante.
     * @return int
     * @throws \InvalidArgumentException Si $variant_key está vacío.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function count_by_variant(string $variant_key): int {
        if ($variant_key === '') {
            throw new \InvalidArgumentException('[FinanceContainerRepository] variant_key no puede estar vacío');
        }

        global $wpdb;
        $table = self::table_name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE variant_key = %s",
                $variant_key
            )
        );

        if (!empty($wpdb->last_error)) {
            error_log('[FinanceContainerRepository] count_by_variant error: ' . $wpdb->last_error);
            throw new \RuntimeException('[FinanceContainerRepository] Error al contar contenedores financieros');
        }

        return (int) $count;
    }

    /**
     * Elimina un contenedor por su ID primario.
     *
     * @param int $id
     * @return bool true si se eliminó 1 fila, false si no existía (0 filas).
     * @throws \InvalidArgumentException Si $id < 1.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function delete(int $id): bool {
        if ($id < 1) {
            throw new \InvalidArgumentException('[FinanceContainerRepository] id debe ser mayor o igual a 1');
        }

        global $wpdb;
        $table = self::table_name();

        $deleted = $wpdb->delete(
            $table,
            ['id' => $id],
            ['%d']
        );

        if ($deleted === false || !empty($wpdb->last_error)) {
            error_log('[FinanceContainerRepository] delete error: ' . ($wpdb->last_error ?: 'delete failed'));
            throw new \RuntimeException('[FinanceContainerRepository] Error al eliminar el contenedor financiero');
        }

        return (int) $deleted === 1;
    }
}
