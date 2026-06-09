<?php
/**
 * Task Action Repository — SQL puro para acciones declaradas de tareas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TaskActionRepository {

    /**
     * @var list<string>
     */
    private const UPSERT_COLUMNS = [
        'action_key',
        'type',
        'label',
        'placement',
        'category',
        'target_status',
        'target_module',
        'target_setup_focus',
        'target_fragment',
        'url',
        'handler',
        'payload_json',
        'enabled',
        'position',
    ];

    /**
     * @return string
     */
    private static function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'aa_task_actions';
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
            'task_id' => (int) $row->task_id,
            'action_key' => (string) $row->action_key,
            'type' => (string) $row->type,
            'label' => (string) $row->label,
            'placement' => (string) $row->placement,
            'category' => (string) $row->category,
            'target_status' => $row->target_status === null ? null : (string) $row->target_status,
            'target_module' => $row->target_module === null ? null : (string) $row->target_module,
            'target_setup_focus' => $row->target_setup_focus === null ? null : (string) $row->target_setup_focus,
            'target_fragment' => $row->target_fragment === null ? null : (string) $row->target_fragment,
            'url' => $row->url === null ? null : (string) $row->url,
            'handler' => $row->handler === null ? null : (string) $row->handler,
            'payload_json' => $row->payload_json === null ? null : (string) $row->payload_json,
            'enabled' => (int) $row->enabled,
            'position' => (int) $row->position,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @param int    $task_id
     * @param string $action_key
     * @return array<string,mixed>|null
     */
    public static function find_by_task_and_key(int $task_id, string $action_key): ?array {
        $normalized_task_id = (int) $task_id;
        $normalized_key = self::normalize_key($action_key);

        if ($normalized_task_id < 1 || $normalized_key === null) {
            return null;
        }

        global $wpdb;

        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE task_id = %d AND action_key = %s LIMIT 1",
                $normalized_task_id,
                $normalized_key
            )
        );

        if ($wpdb->last_error) {
            error_log('[TaskActionRepository] find_by_task_and_key: ' . $wpdb->last_error);
            return null;
        }

        return self::map_row($row);
    }

    /**
     * @param int                 $task_id
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public static function upsert(int $task_id, array $data): ?array {
        $normalized_task_id = (int) $task_id;
        $action_key = self::normalize_key($data['action_key'] ?? null);

        if ($normalized_task_id < 1 || $action_key === null) {
            return null;
        }

        $existing = self::find_by_task_and_key($normalized_task_id, $action_key);
        $payload = self::filter_upsert_data(array_merge($data, ['action_key' => $action_key]));
        $now = current_time('mysql');

        global $wpdb;

        $table = self::table_name();

        if ($existing === null) {
            $insert = array_merge(
                [
                    'task_id' => $normalized_task_id,
                    'action_key' => $action_key,
                    'type' => '',
                    'label' => '',
                    'placement' => 'primary',
                    'category' => 'mechanical',
                    'target_status' => null,
                    'target_module' => null,
                    'target_setup_focus' => null,
                    'target_fragment' => null,
                    'url' => null,
                    'handler' => null,
                    'payload_json' => null,
                    'enabled' => 1,
                    'position' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $payload
            );

            $result = $wpdb->insert($table, $insert, self::build_formats($insert));

            if ($result === false) {
                error_log('[TaskActionRepository] upsert insert: ' . $wpdb->last_error);
                return null;
            }

            return self::find_by_task_and_key($normalized_task_id, $action_key);
        }

        unset($payload['action_key']);
        $update = array_merge($payload, ['updated_at' => $now]);

        $result = $wpdb->update(
            $table,
            $update,
            ['task_id' => $normalized_task_id, 'action_key' => $action_key],
            self::build_formats($update),
            ['%d', '%s']
        );

        if ($result === false) {
            error_log('[TaskActionRepository] upsert update: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_task_and_key($normalized_task_id, $action_key);
    }

    /**
     * @param int $task_id
     * @return list<array<string,mixed>>
     */
    public static function list_by_task_id(int $task_id): array {
        $normalized_task_id = (int) $task_id;

        if ($normalized_task_id < 1) {
            return [];
        }

        global $wpdb;

        $table = self::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE task_id = %d ORDER BY position ASC, id ASC",
                $normalized_task_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[TaskActionRepository] list_by_task_id: ' . $wpdb->last_error);
            return [];
        }

        return self::map_rows($rows);
    }

    /**
     * @param list<int|string> $task_ids
     * @return array<int,list<array<string,mixed>>>
     */
    public static function list_by_task_ids(array $task_ids): array {
        $normalized_ids = [];

        foreach ($task_ids as $task_id) {
            $normalized = (int) $task_id;

            if ($normalized > 0) {
                $normalized_ids[$normalized] = $normalized;
            }
        }

        if ($normalized_ids === []) {
            return [];
        }

        global $wpdb;

        $ids = array_values($normalized_ids);
        $table = self::table_name();
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE task_id IN ({$placeholders}) ORDER BY task_id ASC, position ASC, id ASC", ...$ids));

        if ($wpdb->last_error) {
            error_log('[TaskActionRepository] list_by_task_ids: ' . $wpdb->last_error);
            return [];
        }

        $grouped = [];

        foreach (self::map_rows($rows) as $row) {
            $grouped[(int) $row['task_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * @param int          $task_id
     * @param list<string> $active_action_keys
     * @return int Número de acciones deshabilitadas.
     */
    public static function disable_missing_for_task(int $task_id, array $active_action_keys): int {
        $normalized_task_id = (int) $task_id;

        if ($normalized_task_id < 1) {
            return 0;
        }

        $active = [];

        foreach ($active_action_keys as $key) {
            $normalized_key = self::normalize_key($key);

            if ($normalized_key !== null) {
                $active[$normalized_key] = $normalized_key;
            }
        }

        global $wpdb;

        $table = self::table_name();

        if ($active === []) {
            $result = $wpdb->update(
                $table,
                ['enabled' => 0, 'updated_at' => current_time('mysql')],
                ['task_id' => $normalized_task_id],
                ['%d', '%s'],
                ['%d']
            );

            return $result === false ? 0 : (int) $result;
        }

        $keys = array_values($active);
        $placeholders = implode(', ', array_fill(0, count($keys), '%s'));
        $query = $wpdb->prepare(
            "UPDATE {$table} SET enabled = 0, updated_at = %s WHERE task_id = %d AND action_key NOT IN ({$placeholders})",
            current_time('mysql'),
            $normalized_task_id,
            ...$keys
        );
        $result = $wpdb->query($query);

        return $result === false ? 0 : (int) $result;
    }

    /**
     * @param list<object> $rows
     * @return list<array<string,mixed>>
     */
    private static function map_rows(array $rows): array {
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
     * @param mixed $value
     */
    private static function normalize_key($value): ?string {
        $key = is_string($value) ? trim($value) : '';

        if ($key === '') {
            return null;
        }

        return substr($key, 0, 100);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function filter_upsert_data(array $data): array {
        $filtered = [];

        foreach (self::UPSERT_COLUMNS as $column) {
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
    private static function normalize_column_value(string $column, $value) {
        if (in_array($column, ['enabled', 'position'], true)) {
            return (int) $value;
        }

        if (in_array($column, ['target_status', 'target_module', 'target_setup_focus', 'target_fragment', 'url', 'handler', 'payload_json'], true)) {
            if ($value === null || $value === '') {
                return null;
            }
        }

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * @param array<string,mixed> $row
     * @return list<string>
     */
    private static function build_formats(array $row): array {
        $formats = [];

        foreach (array_keys($row) as $column) {
            if (in_array($column, ['id', 'task_id', 'enabled', 'position'], true)) {
                $formats[] = '%d';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }
}
