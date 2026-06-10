<?php
/**
 * Task Use Case Support — helpers compartidos de validación/respuesta (Application).
 *
 * Orquestación auxiliar; reglas de priorización viven en domain/.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/TaskListRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskRepository.php';

final class TaskUseCaseSupport {

    /**
     * @return array{success:false,error:array{code:string,message:string}}
     */
    public static function fail(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{success:true,data:array<string,mixed>}
     */
    public static function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * @return string Y-m-d H:i:s
     */
    public static function resolve_now(): string {
        if (function_exists('aa_get_current_datetime')) {
            return aa_get_current_datetime();
        }

        return current_time('mysql');
    }

    /**
     * @param mixed $value
     */
    public static function normalize_required_title($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $title = trim($value);

        return $title !== '' ? $title : null;
    }

    /**
     * @param mixed $value
     */
    public static function normalize_importance($value): int {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) $value;
    }

    /**
     * @param mixed $value
     */
    public static function normalize_optional_string($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param mixed $value
     */
    public static function normalize_due_at($value): ?string {
        return self::normalize_optional_string($value);
    }

    /**
     * @param mixed $value
     */
    public static function normalize_position($value): int {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) $value;
    }

    /**
     * @param mixed $value
     */
    public static function normalize_list_id($value): int {
        return max(0, (int) $value);
    }

    /**
     * @param mixed $value
     */
    public static function normalize_task_id($value): int {
        return max(0, (int) $value);
    }

    /**
     * @param mixed $value
     */
    public static function normalize_default_bucket_strict($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $bucket = strtolower(trim($value));

        if ($bucket === TaskRepository::DEFAULT_BUCKET_PRIMARY || $bucket === TaskRepository::DEFAULT_BUCKET_SECONDARY) {
            return $bucket;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    public static function normalize_default_bucket_optional($value): string {
        $strict = self::normalize_default_bucket_strict($value);

        if ($strict !== null) {
            return $strict;
        }

        return TaskRepository::normalize_default_bucket($value);
    }

    /**
     * @param mixed $value
     */
    public static function normalize_task_status($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $status = strtolower(trim($value));

        if ($status === 'pending' || $status === 'done') {
            return $status;
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find_list(int $list_id): ?array {
        if ($list_id < 1) {
            return null;
        }

        $list = TaskListRepository::find_by_id($list_id);

        return is_array($list) ? $list : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find_active_list(int $list_id): ?array {
        $list = self::find_list($list_id);

        if ($list === null) {
            return null;
        }

        if (($list['status'] ?? '') !== 'active') {
            return null;
        }

        return $list;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find_task(int $task_id): ?array {
        if ($task_id < 1) {
            return null;
        }

        $task = TaskRepository::find_by_id($task_id);

        return is_array($task) ? $task : null;
    }
}
