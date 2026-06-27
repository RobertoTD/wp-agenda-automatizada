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
        'default_bucket',
        'importance',
        'due_at',
        'position',
        'completed_at',
    ];

    public const DEFAULT_BUCKET_PRIMARY = 'primary';

    public const DEFAULT_BUCKET_SECONDARY = 'secondary';

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
            'archived_at' => $row->archived_at ?? null,
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
     * @param int         $id
     * @param string|null $archived_at Y-m-d H:i:s; null usa current_time('mysql').
     * @return array<string,mixed>|null
     */
    public static function archive($id, $archived_at = null) {
        $task_id = (int) $id;

        if ($task_id < 1) {
            return null;
        }

        $existing = self::find_by_id($task_id);

        if ($existing === null) {
            return null;
        }

        if (self::is_archived_row($existing)) {
            return $existing;
        }

        $timestamp = is_string($archived_at) && trim($archived_at) !== ''
            ? trim($archived_at)
            : current_time('mysql');

        global $wpdb;

        $table = self::table_name();
        $result = $wpdb->update(
            $table,
            [
                'archived_at' => $timestamp,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $task_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            error_log('[TaskRepository] archive: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_id($task_id);
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function restore($id) {
        $task_id = (int) $id;

        if ($task_id < 1) {
            return null;
        }

        $existing = self::find_by_id($task_id);

        if ($existing === null) {
            return null;
        }

        if (!self::is_archived_row($existing)) {
            return $existing;
        }

        global $wpdb;

        $table = self::table_name();
        $result = $wpdb->update(
            $table,
            [
                'archived_at' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $task_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            error_log('[TaskRepository] restore: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_id($task_id);
    }

    /**
     * @param int $id
     * @return bool false si id inválido, error SQL o la fila no se borró.
     */
    public static function delete($id): bool {
        $task_id = (int) $id;

        if ($task_id < 1) {
            return false;
        }

        global $wpdb;

        $table = self::table_name();
        $result = $wpdb->delete($table, ['id' => $task_id], ['%d']);

        if ($result === false || $wpdb->last_error) {
            error_log('[TaskRepository] delete: ' . $wpdb->last_error);
            return false;
        }

        return (int) $result > 0;
    }

    /**
     * @param int $list_id
     * @return int|false Filas borradas, o false si hubo error SQL.
     */
    public static function delete_by_list_id(int $list_id) {
        $normalized_list_id = (int) $list_id;

        if ($normalized_list_id < 1) {
            return false;
        }

        global $wpdb;

        $table = self::table_name();
        $result = $wpdb->delete($table, ['list_id' => $normalized_list_id], ['%d']);

        if ($result === false || $wpdb->last_error) {
            error_log('[TaskRepository] delete_by_list_id: ' . $wpdb->last_error);
            return false;
        }

        return (int) $result;
    }

    /**
     * @param int $list_id
     * @return list<array<string,mixed>>
     */
    public static function list_archived_by_list_id($list_id) {
        $normalized_list_id = (int) $list_id;

        if ($normalized_list_id < 1) {
            return [];
        }

        global $wpdb;

        $table = self::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE list_id = %d
                   AND archived_at IS NOT NULL
                 ORDER BY archived_at DESC, id DESC",
                $normalized_list_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[TaskRepository] list_archived_by_list_id: ' . $wpdb->last_error);
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
     * Marca una tarea como "No realizada" (resolución terminal negativa).
     *
     * No cuenta como completada: deja completed_at en NULL. No toca archived_at.
     *
     * @param int    $id
     * @param string $now Y-m-d H:i:s
     * @return array<string,mixed>|null
     */
    public static function mark_missed($id, $now) {
        return self::update($id, [
            'status' => 'missed',
            'completed_at' => null,
        ]);
    }

    /**
     * Backfill idempotente MC13O-H3B-2: defer histórico + default_bucket primary → secondary.
     *
     * Criterio: aa_task_state.defer_count > 0, last_deferred_at no vacío,
     * aa_tasks.default_bucket = primary. No filtra por status ni lista archivada.
     * No modifica aa_task_state ni otros campos de aa_tasks.
     *
     * @return array{matched_count:int,updated_count:int,skipped_count:int}
     */
    public static function backfill_deferred_primary_to_secondary_bucket(): array {
        global $wpdb;

        $tasks_table = self::table_name();
        $state_table = $wpdb->prefix . 'aa_task_state';
        $where_sql = '
            s.defer_count > 0
            AND s.last_deferred_at IS NOT NULL
            AND s.last_deferred_at != \'\'
            AND t.default_bucket = %s
        ';

        $matched = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tasks_table} t
             INNER JOIN {$state_table} s ON s.task_id = t.id
             WHERE {$where_sql}",
            self::DEFAULT_BUCKET_PRIMARY
        ));

        if ($wpdb->last_error) {
            error_log('[TaskRepository] backfill_deferred_primary_to_secondary_bucket count: ' . $wpdb->last_error);

            return [
                'matched_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
            ];
        }

        if ($matched < 1) {
            return [
                'matched_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
            ];
        }

        $now = current_time('mysql');
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$tasks_table} t
             INNER JOIN {$state_table} s ON s.task_id = t.id
             SET t.default_bucket = %s, t.updated_at = %s
             WHERE {$where_sql}",
            self::DEFAULT_BUCKET_SECONDARY,
            $now,
            self::DEFAULT_BUCKET_PRIMARY
        ));

        if ($updated === false) {
            error_log('[TaskRepository] backfill_deferred_primary_to_secondary_bucket update: ' . $wpdb->last_error);

            return [
                'matched_count' => $matched,
                'updated_count' => 0,
                'skipped_count' => $matched,
            ];
        }

        $updated_count = (int) $updated;

        return [
            'matched_count' => $matched,
            'updated_count' => $updated_count,
            'skipped_count' => max(0, $matched - $updated_count),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function is_archived_row(array $row): bool {
        $archived_at = $row['archived_at'] ?? null;

        if ($archived_at === null || $archived_at === '') {
            return false;
        }

        return trim((string) $archived_at) !== '';
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

        if ($column === 'default_bucket') {
            return self::normalize_default_bucket($value);
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

    /**
     * @param mixed $value
     */
    public static function normalize_default_bucket($value): string {
        $bucket = is_string($value) ? strtolower(trim($value)) : '';

        if ($bucket === self::DEFAULT_BUCKET_SECONDARY) {
            return self::DEFAULT_BUCKET_SECONDARY;
        }

        return self::DEFAULT_BUCKET_PRIMARY;
    }
}
