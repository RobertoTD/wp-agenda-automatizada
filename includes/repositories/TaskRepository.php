<?php
/**
 * Task Repository — SQL puro para tareas (Listas/Tareas).
 *
 * Sin reglas de negocio ni validación semántica de status/source.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TaskRepository {

    /**
     * Columnas permitidas en create/update (whitelist).
     *
     * @var list<string>
     */
    private const WRITABLE_COLUMNS = [
        'list_id',
        'title',
        'notes',
        'status',
        'source',
        'importance',
        'due_at',
        'position',
        'completed_at',
    ];

    /**
     * @return string
     */
    private static function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'aa_tasks';
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
            'list_id' => (int) $row->list_id,
            'title' => (string) $row->title,
            'notes' => $row->notes === null ? null : (string) $row->notes,
            'status' => (string) $row->status,
            'source' => (string) $row->source,
            'source_category' => isset($row->source_category) && $row->source_category !== null ? (string) $row->source_category : 'user',
            'origin_key' => isset($row->origin_key) && $row->origin_key !== null ? (string) $row->origin_key : null,
            'managed_by' => isset($row->managed_by) && $row->managed_by !== null ? (string) $row->managed_by : 'user',
            'default_bucket' => isset($row->default_bucket) && $row->default_bucket !== null ? (string) $row->default_bucket : 'primary',
            'completion_type' => isset($row->completion_type) && $row->completion_type !== null ? (string) $row->completion_type : 'manual',
            'completion_fact_key' => isset($row->completion_fact_key) && $row->completion_fact_key !== null ? (string) $row->completion_fact_key : null,
            'importance' => (int) $row->importance,
            'due_at' => $row->due_at,
            'position' => (int) $row->position,
            'completed_at' => $row->completed_at,
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
                'list_id' => 0,
                'title' => '',
                'notes' => null,
                'status' => 'pending',
                'source' => 'user',
                'importance' => 0,
                'due_at' => null,
                'position' => 0,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $payload
        );

        $table = self::table_name();
        $formats = self::build_formats($insert);
        $result = $wpdb->insert($table, $insert, $formats);

        if ($result === false) {
            error_log('[TaskRepository] create: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_id((int) $wpdb->insert_id);
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function find_by_id($id) {
        $task_id = (int) $id;

        if ($task_id < 1) {
            return null;
        }

        global $wpdb;

        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $task_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[TaskRepository] find_by_id: ' . $wpdb->last_error);
            return null;
        }

        return self::map_row($row);
    }

    /**
     * @param int $list_id
     * @return list<array<string,mixed>>
     */
    public static function list_by_list_id($list_id) {
        $normalized_list_id = (int) $list_id;

        if ($normalized_list_id < 1) {
            return [];
        }

        global $wpdb;

        $table = self::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE list_id = %d ORDER BY position ASC, id ASC",
                $normalized_list_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[TaskRepository] list_by_list_id: ' . $wpdb->last_error);
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
     * Tareas seeded agenda_app con completion por sistema pendientes de evaluación.
     *
     * @return list<array<string,mixed>>
     */
    public static function list_system_completion_candidates() {
        global $wpdb;

        $table = self::table_name();
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE source_category = 'agenda_app'
               AND completion_type = 'system'
               AND completion_fact_key IS NOT NULL
               AND completion_fact_key != ''
               AND status = 'pending'
             ORDER BY position ASC, id ASC"
        );

        if ($wpdb->last_error) {
            error_log('[TaskRepository] list_system_completion_candidates: ' . $wpdb->last_error);
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
        $task_id = (int) $id;

        if ($task_id < 1) {
            return null;
        }

        $payload = self::filter_writable_data($data);

        if ($payload === []) {
            return self::find_by_id($task_id);
        }

        global $wpdb;

        $table = self::table_name();
        $update = array_merge($payload, ['updated_at' => current_time('mysql')]);
        $formats = self::build_formats($update);

        $result = $wpdb->update(
            $table,
            $update,
            ['id' => $task_id],
            $formats,
            ['%d']
        );

        if ($result === false) {
            error_log('[TaskRepository] update: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_id($task_id);
    }

    /**
     * @param int    $id
     * @param string $status
     * @return array<string,mixed>|null
     */
    public static function update_status($id, $status) {
        return self::update($id, ['status' => (string) $status]);
    }

    /**
     * @param int    $id
     * @param string $completed_at
     * @return array<string,mixed>|null
     */
    public static function mark_completed($id, $completed_at) {
        return self::update($id, [
            'status' => 'done',
            'completed_at' => $completed_at,
        ]);
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
        if (in_array($column, ['list_id', 'importance', 'position'], true)) {
            return (int) $value;
        }

        if (in_array($column, ['notes', 'due_at', 'completed_at'], true)) {
            if ($value === null || $value === '') {
                return null;
            }

            return is_string($value) ? $value : null;
        }

        if (in_array($column, ['title', 'status', 'source'], true)) {
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
            if (in_array($column, ['list_id', 'importance', 'position'], true)) {
                $formats[] = '%d';
                continue;
            }

            if (in_array($column, ['notes', 'due_at', 'completed_at'], true) && $row[$column] === null) {
                $formats[] = '%s';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }
}
