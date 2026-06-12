<?php
/**
 * Task List Repository — SQL puro para listas de tareas (Listas/Tareas).
 *
 * Sin reglas de negocio ni validación semántica de owner_type/status.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TaskListRepository {

    /**
     * Columnas permitidas en create/update (whitelist).
     *
     * @var list<string>
     */
    private const WRITABLE_COLUMNS = [
        'title',
        'description',
        'owner_type',
        'importance',
        'status',
        'position',
    ];

    /**
     * @return string
     */
    private static function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'aa_task_lists';
    }

    /**
     * @param object|null $row
     * @return array<string,mixed>|null
     */
    private static function map_row($row) {
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'description' => $row->description === null ? null : (string) $row->description,
            'owner_type' => (string) $row->owner_type,
            'source_category' => isset($row->source_category) && $row->source_category !== null ? (string) $row->source_category : 'user',
            'origin_key' => isset($row->origin_key) && $row->origin_key !== null ? (string) $row->origin_key : null,
            'managed_by' => isset($row->managed_by) && $row->managed_by !== null ? (string) $row->managed_by : 'user',
            'importance' => (int) $row->importance,
            'status' => (string) $row->status,
            'position' => (int) $row->position,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public static function create(array $data) {
        global $wpdb;

        $payload = self::filter_writable_data($data);
        $now = current_time('mysql');

        $insert = array_merge(
            [
                'title' => '',
                'description' => null,
                'owner_type' => 'user',
                'importance' => 0,
                'status' => 'active',
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $payload
        );

        $table = self::table_name();
        $formats = self::build_formats($insert);
        $result = $wpdb->insert($table, $insert, $formats);

        if ($result === false) {
            error_log('[TaskListRepository] create: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_id((int) $wpdb->insert_id);
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function find_by_id($id) {
        $list_id = (int) $id;

        if ($list_id < 1) {
            return null;
        }

        global $wpdb;

        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $list_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[TaskListRepository] find_by_id: ' . $wpdb->last_error);
            return null;
        }

        return self::map_row($row);
    }

    /**
     * @param string|null $status Filtra por status exacto; null = todas.
     * @return list<array<string,mixed>>
     */
    public static function list_all($status = null) {
        global $wpdb;

        $table = self::table_name();

        if ($status !== null && $status !== '') {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = %s ORDER BY position ASC, id ASC",
                    (string) $status
                )
            );
        } else {
            $rows = $wpdb->get_results(
                "SELECT * FROM {$table} ORDER BY position ASC, id ASC"
            );
        }

        if ($wpdb->last_error) {
            error_log('[TaskListRepository] list_all: ' . $wpdb->last_error);
            return [];
        }

        $mapped = [];

        foreach ($rows as $row) {
            $item = self::map_row($row);

            if ($item !== null) {
                $mapped[] = $item;
            }
        }

        return $mapped;
    }

    /**
     * @param int                  $id
     * @param array<string,mixed>  $data
     * @return array<string,mixed>|null
     */
    public static function update($id, array $data) {
        $list_id = (int) $id;

        if ($list_id < 1) {
            return null;
        }

        $payload = self::filter_writable_data($data);

        if ($payload === []) {
            return self::find_by_id($list_id);
        }

        global $wpdb;

        $table = self::table_name();
        $update = array_merge($payload, ['updated_at' => current_time('mysql')]);
        $formats = self::build_formats($update);

        $result = $wpdb->update(
            $table,
            $update,
            ['id' => $list_id],
            $formats,
            ['%d']
        );

        if ($result === false) {
            error_log('[TaskListRepository] update: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_id($list_id);
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function archive($id) {
        return self::update($id, ['status' => 'archived']);
    }

    /**
     * Listas archivadas de usuario, más recientes primero (proxy: updated_at).
     *
     * @return list<array<string,mixed>>
     */
    public static function list_archived_recent_first() {
        global $wpdb;

        $table = self::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND owner_type = %s ORDER BY updated_at DESC, id DESC",
                'archived',
                'user'
            )
        );

        if ($wpdb->last_error) {
            error_log('[TaskListRepository] list_archived_recent_first: ' . $wpdb->last_error);
            return [];
        }

        $mapped = [];

        foreach ($rows as $row) {
            $item = self::map_row($row);

            if ($item !== null) {
                $mapped[] = $item;
            }
        }

        return $mapped;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function restore($id) {
        return self::update($id, ['status' => 'active']);
    }

    /**
     * @param int $id
     * @return bool false si id inválido, error SQL o la fila no se borró.
     */
    public static function delete($id): bool {
        $list_id = (int) $id;

        if ($list_id < 1) {
            return false;
        }

        global $wpdb;

        $table = self::table_name();
        $result = $wpdb->delete($table, ['id' => $list_id], ['%d']);

        if ($result === false || $wpdb->last_error) {
            error_log('[TaskListRepository] delete: ' . $wpdb->last_error);
            return false;
        }

        return (int) $result > 0;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function filter_writable_data(array $data) {
        $filtered = [];

        foreach (self::WRITABLE_COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $filtered[$column] = self::normalize_column_value($column, $data[$column]);
        }

        return $filtered;
    }

    /**
     * @param string $column
     * @param mixed  $value
     * @return mixed
     */
    private static function normalize_column_value($column, $value) {
        if (in_array($column, ['importance', 'position'], true)) {
            return (int) $value;
        }

        if ($column === 'description') {
            if ($value === null || $value === '') {
                return null;
            }

            return is_string($value) ? $value : null;
        }

        if (in_array($column, ['title', 'owner_type', 'status'], true)) {
            return is_string($value) ? $value : (string) $value;
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     * @return list<string>
     */
    private static function build_formats(array $row) {
        $formats = [];

        foreach (array_keys($row) as $column) {
            if (in_array($column, ['importance', 'position'], true)) {
                $formats[] = '%d';
                continue;
            }

            if ($column === 'description' && $row[$column] === null) {
                $formats[] = '%s';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }
}
