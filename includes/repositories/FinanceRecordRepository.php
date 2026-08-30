<?php
/**
 * Finance Record Repository — SQL puro para registros de Finanzas (aa_finance_records).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class FinanceRecordRepository {

    public const PAGE_SIZE = 15;

    /**
     * @return string
     */
    private static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'aa_finance_records';
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{
     *     id: int,
     *     container_id: int,
     *     title: string,
     *     details: ?string,
     *     amount: ?string,
     *     created_at: string
     * }|null
     */
    private static function map_row(?array $row): ?array {
        if (!is_array($row) || !isset($row['id']) || (int) $row['id'] < 1) {
            return null;
        }

        $details = $row['details'] ?? null;
        $amount = $row['amount'] ?? null;

        return [
            'id' => (int) $row['id'],
            'container_id' => (int) ($row['container_id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'details' => ($details === null || $details === '') ? null : (string) $details,
            'amount' => ($amount === null || $amount === '') ? null : (string) $amount,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * Crea un nuevo registro dentro de un contenedor financiero.
     *
     * @param int $container_id ID del contenedor padre.
     * @param string $title Título del registro.
     * @param string|null $details Detalles opcionales.
     * @param string|null $amount Importe decimal opcional como string (ej. "150.50", "-25.00", "0.00").
     * @return array{
     *     id: int,
     *     container_id: int,
     *     title: string,
     *     details: ?string,
     *     amount: ?string,
     *     created_at: string
     * }
     * @throws \InvalidArgumentException Si $container_id < 1 o $title está vacío.
     * @throws \RuntimeException Si la inserción SQL o la foreign key fallan.
     */
    public static function create(int $container_id, string $title, ?string $details = null, ?string $amount = null): array {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] container_id debe ser mayor o igual a 1');
        }

        if ($title === '') {
            throw new \InvalidArgumentException('[FinanceRecordRepository] title no puede estar vacío');
        }

        global $wpdb;
        $table = self::table_name();
        $now = current_time('mysql');

        $data = [
            'container_id' => $container_id,
            'title' => $title,
            'details' => $details,
            'amount' => $amount,
            'created_at' => $now,
        ];

        $formats = [
            '%d',
            '%s',
            $details === null ? null : '%s',
            $amount === null ? null : '%s',
            '%s',
        ];

        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false || !empty($wpdb->last_error)) {
            error_log('[FinanceRecordRepository] create error: ' . ($wpdb->last_error ?: 'insert failed'));
            throw new \RuntimeException('[FinanceRecordRepository] Error al crear el registro financiero');
        }

        $id = (int) $wpdb->insert_id;
        if ($id < 1) {
            throw new \RuntimeException('[FinanceRecordRepository] Error al obtener ID del registro creado');
        }

        return [
            'id' => $id,
            'container_id' => $container_id,
            'title' => $title,
            'details' => $details,
            'amount' => $amount,
            'created_at' => $now,
        ];
    }

    /**
     * Busca un registro por ID asegurando pertenencia estricta al contenedor especificado.
     *
     * @param int $id ID del registro.
     * @param int $container_id ID del contenedor padre.
     * @return array{
     *     id: int,
     *     container_id: int,
     *     title: string,
     *     details: ?string,
     *     amount: ?string,
     *     created_at: string
     * }|null Devuelve el registro o null si no existe o no pertenece al contenedor.
     * @throws \InvalidArgumentException Si $id < 1 o $container_id < 1.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function find_by_id_and_container(int $id, int $container_id): ?array {
        if ($id < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] id debe ser mayor o igual a 1');
        }

        if ($container_id < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] container_id debe ser mayor o igual a 1');
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, container_id, title, details, amount, created_at
                 FROM {$table}
                 WHERE id = %d AND container_id = %d
                 LIMIT 1",
                $id,
                $container_id
            ),
            ARRAY_A
        );

        if (!empty($wpdb->last_error)) {
            error_log('[FinanceRecordRepository] find_by_id_and_container error: ' . $wpdb->last_error);
            throw new \RuntimeException('[FinanceRecordRepository] Error al consultar el registro financiero');
        }

        return self::map_row(is_array($row) ? $row : null);
    }

    /**
     * Lista registros de un contenedor con paginación fija de 15 en orden created_at DESC, id DESC.
     *
     * @param int $container_id ID del contenedor.
     * @param int $page Número de página (1-based).
     * @return list<array{
     *     id: int,
     *     container_id: int,
     *     title: string,
     *     details: ?string,
     *     amount: ?string,
     *     created_at: string
     * }>
     * @throws \InvalidArgumentException Si $container_id < 1 o $page < 1.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function list_by_container(int $container_id, int $page = 1): array {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] container_id debe ser mayor o igual a 1');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] page debe ser mayor o igual a 1');
        }

        global $wpdb;
        $table = self::table_name();
        $offset = ($page - 1) * self::PAGE_SIZE;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, container_id, title, details, amount, created_at
                 FROM {$table}
                 WHERE container_id = %d
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $container_id,
                self::PAGE_SIZE,
                $offset
            ),
            ARRAY_A
        );

        if (!empty($wpdb->last_error)) {
            error_log('[FinanceRecordRepository] list_by_container error: ' . $wpdb->last_error);
            throw new \RuntimeException('[FinanceRecordRepository] Error al listar registros financieros');
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
     * Cuenta el total de registros dentro de un contenedor.
     *
     * @param int $container_id ID del contenedor.
     * @return int
     * @throws \InvalidArgumentException Si $container_id < 1.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function count_by_container(int $container_id): int {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] container_id debe ser mayor o igual a 1');
        }

        global $wpdb;
        $table = self::table_name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE container_id = %d",
                $container_id
            )
        );

        if (!empty($wpdb->last_error)) {
            error_log('[FinanceRecordRepository] count_by_container error: ' . $wpdb->last_error);
            throw new \RuntimeException('[FinanceRecordRepository] Error al contar registros financieros');
        }

        return (int) $count;
    }

    /**
     * Calcula la suma de montos dentro de un contenedor.
     *
     * @param int $container_id ID del contenedor.
     * @return string|null null si no hay importes o el contenedor está vacío; "0.00" o string decimal si existen importes.
     * @throws \InvalidArgumentException Si $container_id < 1.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function sum_amounts_by_container(int $container_id): ?string {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] container_id debe ser mayor o igual a 1');
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(amount) AS count_with_amount, SUM(amount) AS total_amount
                 FROM {$table}
                 WHERE container_id = %d",
                $container_id
            ),
            ARRAY_A
        );

        if (!empty($wpdb->last_error)) {
            error_log('[FinanceRecordRepository] sum_amounts_by_container error: ' . $wpdb->last_error);
            throw new \RuntimeException('[FinanceRecordRepository] Error al calcular la suma de montos');
        }

        if (!is_array($row) || (int) ($row['count_with_amount'] ?? 0) === 0) {
            return null;
        }

        $total = $row['total_amount'] ?? null;
        if ($total === null || $total === '') {
            return null;
        }

        return (string) $total;
    }

    /**
     * Elimina un registro por su ID asegurando pertenencia al contenedor especificado.
     *
     * @param int $id ID del registro.
     * @param int $container_id ID del contenedor.
     * @return bool true si se eliminó 1 fila, false si no existía (0 filas).
     * @throws \InvalidArgumentException Si $id < 1 o $container_id < 1.
     * @throws \RuntimeException Si la consulta SQL falla.
     */
    public static function delete(int $id, int $container_id): bool {
        if ($id < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] id debe ser mayor o igual a 1');
        }

        if ($container_id < 1) {
            throw new \InvalidArgumentException('[FinanceRecordRepository] container_id debe ser mayor o igual a 1');
        }

        global $wpdb;
        $table = self::table_name();

        $deleted = $wpdb->delete(
            $table,
            [
                'id' => $id,
                'container_id' => $container_id,
            ],
            ['%d', '%d']
        );

        if ($deleted === false || !empty($wpdb->last_error)) {
            error_log('[FinanceRecordRepository] delete error: ' . ($wpdb->last_error ?: 'delete failed'));
            throw new \RuntimeException('[FinanceRecordRepository] Error al eliminar el registro financiero');
        }

        return (int) $deleted === 1;
    }
}
