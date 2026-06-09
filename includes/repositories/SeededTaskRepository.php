<?php
/**
 * Seeded Task Repository — SQL puro para definiciones gestionadas por sistema.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SeededTaskRepository {

    /**
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'title',
        'description',
        'owner_type',
        'source_category',
        'origin_key',
        'managed_by',
        'status',
        'importance',
        'position',
    ];

    /**
     * @var list<string>
     */
    private const TASK_COLUMNS = [
        'list_id',
        'title',
        'notes',
        'status',
        'source',
        'source_category',
        'origin_key',
        'managed_by',
        'importance',
        'position',
        'default_bucket',
        'completion_type',
        'completion_fact_key',
        'due_at',
        'completed_at',
    ];

    /**
     * @param string $source_category
     * @param string $origin_key
     * @return array<string,mixed>|null
     */
    public static function find_list_by_origin(string $source_category, string $origin_key): ?array {
        $source = self::normalize_required_key($source_category);
        $origin = self::normalize_required_key($origin_key);

        if ($source === null || $origin === null) {
            return null;
        }

        global $wpdb;

        $table = self::lists_table();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source_category = %s AND origin_key = %s LIMIT 1",
                $source,
                $origin
            )
        );

        if ($wpdb->last_error) {
            error_log('[SeededTaskRepository] find_list_by_origin: ' . $wpdb->last_error);
            return null;
        }

        return self::map_list_row($row);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public static function upsert_seeded_list(array $data): ?array {
        $source = self::normalize_required_key($data['source_category'] ?? null);
        $origin = self::normalize_required_key($data['origin_key'] ?? null);

        if ($source === null || $origin === null) {
            return null;
        }

        $existing = self::find_list_by_origin($source, $origin);
        $payload = self::filter_data(self::LIST_COLUMNS, array_merge($data, [
            'source_category' => $source,
            'origin_key' => $origin,
        ]));
        $now = current_time('mysql');

        global $wpdb;

        $table = self::lists_table();

        if ($existing === null) {
            $insert = array_merge(
                [
                    'title' => '',
                    'description' => '',
                    'owner_type' => 'developer',
                    'source_category' => $source,
                    'origin_key' => $origin,
                    'managed_by' => 'developer',
                    'status' => 'active',
                    'importance' => 0,
                    'position' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $payload
            );

            $result = $wpdb->insert($table, $insert, self::build_formats($insert));

            if ($result === false) {
                error_log('[SeededTaskRepository] upsert_seeded_list insert: ' . $wpdb->last_error);
                return null;
            }

            return self::find_list_by_origin($source, $origin);
        }

        unset($payload['source_category'], $payload['origin_key']);
        $update = array_merge($payload, ['updated_at' => $now]);

        $result = $wpdb->update(
            $table,
            $update,
            ['source_category' => $source, 'origin_key' => $origin],
            self::build_formats($update),
            ['%s', '%s']
        );

        if ($result === false) {
            error_log('[SeededTaskRepository] upsert_seeded_list update: ' . $wpdb->last_error);
            return null;
        }

        return self::find_list_by_origin($source, $origin);
    }

    /**
     * @param string $source_category
     * @param string $origin_key
     * @return array<string,mixed>|null
     */
    public static function find_task_by_origin(string $source_category, string $origin_key): ?array {
        $source = self::normalize_required_key($source_category);
        $origin = self::normalize_required_key($origin_key);

        if ($source === null || $origin === null) {
            return null;
        }

        global $wpdb;

        $table = self::tasks_table();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source_category = %s AND origin_key = %s LIMIT 1",
                $source,
                $origin
            )
        );

        if ($wpdb->last_error) {
            error_log('[SeededTaskRepository] find_task_by_origin: ' . $wpdb->last_error);
            return null;
        }

        return self::map_task_row($row);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public static function upsert_seeded_task(array $data): ?array {
        $source = self::normalize_required_key($data['source_category'] ?? null);
        $origin = self::normalize_required_key($data['origin_key'] ?? null);

        if ($source === null || $origin === null) {
            return null;
        }

        $existing = self::find_task_by_origin($source, $origin);
        $payload = self::filter_data(self::TASK_COLUMNS, array_merge($data, [
            'source_category' => $source,
            'origin_key' => $origin,
        ]));
        $now = current_time('mysql');

        global $wpdb;

        $table = self::tasks_table();

        if ($existing === null) {
            $insert = array_merge(
                [
                    'list_id' => 0,
                    'title' => '',
                    'notes' => '',
                    'status' => 'pending',
                    'source' => 'system',
                    'source_category' => $source,
                    'origin_key' => $origin,
                    'managed_by' => 'developer',
                    'importance' => 0,
                    'position' => 0,
                    'default_bucket' => 'secondary',
                    'completion_type' => 'manual',
                    'completion_fact_key' => null,
                    'due_at' => null,
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $payload
            );

            $result = $wpdb->insert($table, $insert, self::build_formats($insert));

            if ($result === false) {
                error_log('[SeededTaskRepository] upsert_seeded_task insert: ' . $wpdb->last_error);
                return null;
            }

            return self::find_task_by_origin($source, $origin);
        }

        unset($payload['source_category'], $payload['origin_key'], $payload['status'], $payload['completed_at']);
        $update = array_merge($payload, ['updated_at' => $now]);

        $result = $wpdb->update(
            $table,
            $update,
            ['source_category' => $source, 'origin_key' => $origin],
            self::build_formats($update),
            ['%s', '%s']
        );

        if ($result === false) {
            error_log('[SeededTaskRepository] upsert_seeded_task update: ' . $wpdb->last_error);
            return null;
        }

        return self::find_task_by_origin($source, $origin);
    }

    /**
     * @return string
     */
    private static function lists_table() {
        global $wpdb;

        return $wpdb->prefix . 'aa_task_lists';
    }

    /**
     * @return string
     */
    private static function tasks_table() {
        global $wpdb;

        return $wpdb->prefix . 'aa_tasks';
    }

    /**
     * @param object|null $row
     * @return array<string,mixed>|null
     */
    private static function map_list_row($row): ?array {
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'description' => (string) $row->description,
            'owner_type' => (string) $row->owner_type,
            'source_category' => $row->source_category === null ? null : (string) $row->source_category,
            'origin_key' => $row->origin_key === null ? null : (string) $row->origin_key,
            'managed_by' => $row->managed_by === null ? null : (string) $row->managed_by,
            'status' => (string) $row->status,
            'importance' => (int) $row->importance,
            'position' => (int) $row->position,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @param object|null $row
     * @return array<string,mixed>|null
     */
    private static function map_task_row($row): ?array {
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'list_id' => (int) $row->list_id,
            'title' => (string) $row->title,
            'notes' => (string) $row->notes,
            'status' => (string) $row->status,
            'source' => (string) $row->source,
            'source_category' => $row->source_category === null ? null : (string) $row->source_category,
            'origin_key' => $row->origin_key === null ? null : (string) $row->origin_key,
            'managed_by' => $row->managed_by === null ? null : (string) $row->managed_by,
            'importance' => (int) $row->importance,
            'position' => (int) $row->position,
            'default_bucket' => $row->default_bucket === null ? null : (string) $row->default_bucket,
            'completion_type' => $row->completion_type === null ? null : (string) $row->completion_type,
            'completion_fact_key' => $row->completion_fact_key === null ? null : (string) $row->completion_fact_key,
            'due_at' => $row->due_at,
            'completed_at' => $row->completed_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function normalize_required_key($value): ?string {
        $key = is_string($value) ? trim($value) : '';

        if ($key === '') {
            return null;
        }

        return substr($key, 0, 100);
    }

    /**
     * @param list<string>        $columns
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function filter_data(array $columns, array $data): array {
        $filtered = [];

        foreach ($columns as $column) {
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
        if (in_array($column, ['id', 'list_id', 'importance', 'position'], true)) {
            return (int) $value;
        }

        if (in_array($column, ['description', 'notes', 'completion_fact_key', 'due_at', 'completed_at'], true)) {
            if ($value === null) {
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
            if (in_array($column, ['id', 'list_id', 'importance', 'position'], true)) {
                $formats[] = '%d';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }
}
