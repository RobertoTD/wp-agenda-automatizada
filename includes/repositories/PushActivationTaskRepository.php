<?php
/**
 * Push Activation Task Repository — SQL y lock para ocurrencias enable_push:*.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PushActivationTaskRepository {

    private const LOCK_TIMEOUT_SECONDS = 2;

    /**
     * @param string $blog_id
     * @param string $device_key 32 hex lowercase
     */
    public static function build_lock_name(string $blog_id, string $device_key): string {
        $raw = $blog_id . ':' . $device_key;

        return 'aa_ep_' . substr(hash('sha256', $raw), 0, 40);
    }

    /**
     * @return bool True when the lock was acquired.
     */
    public static function try_acquire_lock(string $lock_name, int $timeout_seconds = self::LOCK_TIMEOUT_SECONDS): bool {
        $normalized = self::normalize_lock_name($lock_name);

        if ($normalized === null) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare('SELECT GET_LOCK(%s, %d)', $normalized, max(0, $timeout_seconds))
        );

        if ($wpdb->last_error) {
            error_log('[PushActivationTaskRepository] try_acquire_lock: ' . $wpdb->last_error);

            return false;
        }

        return (int) $result === 1;
    }

    public static function release_lock(string $lock_name): void {
        $normalized = self::normalize_lock_name($lock_name);

        if ($normalized === null) {
            return;
        }

        global $wpdb;

        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $normalized));

        if ($wpdb->last_error) {
            error_log('[PushActivationTaskRepository] release_lock: ' . $wpdb->last_error);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function list_occurrences_by_device_prefix(string $source_category, string $device_prefix): array {
        $source = self::normalize_key($source_category);
        $prefix = self::normalize_prefix($device_prefix);

        if ($source === null || $prefix === null) {
            return [];
        }

        global $wpdb;

        $table = self::tasks_table();
        $like = $wpdb->esc_like($prefix) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE source_category = %s
                   AND origin_key LIKE %s
                 ORDER BY id ASC",
                $source,
                $like
            )
        );

        if ($wpdb->last_error) {
            error_log('[PushActivationTaskRepository] list_occurrences_by_device_prefix: ' . $wpdb->last_error);

            return [];
        }

        $mapped = [];

        foreach ($rows as $row) {
            $item = self::map_task_row($row);

            if ($item !== null) {
                $mapped[] = $item;
            }
        }

        return $mapped;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function list_non_done_occurrences(string $source_category, string $device_key): array {
        $prefix = self::build_device_prefix($device_key);

        if ($prefix === null) {
            return [];
        }

        $occurrences = self::list_occurrences_by_device_prefix($source_category, $prefix);

        return array_values(array_filter($occurrences, static function (array $task): bool {
            return strtolower(trim((string) ($task['status'] ?? ''))) !== 'done';
        }));
    }

    public static function build_device_prefix(string $device_key): ?string {
        if (!self::is_valid_device_key($device_key)) {
            return null;
        }

        return 'enable_push:' . $device_key . ':';
    }

    public static function build_origin_key(string $device_key, string $occurrence_id): ?string {
        if (!self::is_valid_device_key($device_key) || !self::is_valid_occurrence_id($occurrence_id)) {
            return null;
        }

        return 'enable_push:' . $device_key . ':' . $occurrence_id;
    }

    public static function is_valid_device_key(string $device_key): bool {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $device_key);
    }

    public static function is_valid_occurrence_id(string $occurrence_id): bool {
        return (bool) preg_match('/^[a-f0-9]{16}$/', $occurrence_id);
    }

    /**
     * @return string 16 hex lowercase
     */
    public static function generate_occurrence_id(): string {
        return bin2hex(random_bytes(8));
    }

    /**
     * @return string
     */
    private static function tasks_table() {
        global $wpdb;

        return $wpdb->prefix . 'aa_tasks';
    }

    /**
     * @param mixed $value
     */
    private static function normalize_key($value): ?string {
        $key = is_string($value) ? trim($value) : '';

        return $key !== '' ? $key : null;
    }

    private static function normalize_prefix(string $prefix): ?string {
        $normalized = trim($prefix);

        return $normalized !== '' ? $normalized : null;
    }

    private static function normalize_lock_name(string $lock_name): ?string {
        $normalized = trim($lock_name);

        if ($normalized === '' || strlen($normalized) > 64) {
            return null;
        }

        return $normalized;
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
            'notes' => $row->notes === null ? null : (string) $row->notes,
            'status' => (string) $row->status,
            'source' => (string) $row->source,
            'source_category' => (string) $row->source_category,
            'origin_key' => (string) $row->origin_key,
            'managed_by' => (string) $row->managed_by,
            'default_bucket' => (string) $row->default_bucket,
            'completion_type' => $row->completion_type === null ? null : (string) $row->completion_type,
            'completion_fact_key' => $row->completion_fact_key === null ? null : (string) $row->completion_fact_key,
            'importance' => (int) $row->importance,
            'due_at' => $row->due_at,
            'execution_available_at' => $row->execution_available_at ?? null,
            'position' => (int) $row->position,
            'completed_at' => $row->completed_at,
            'archived_at' => $row->archived_at ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
