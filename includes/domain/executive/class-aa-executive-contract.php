<?php
/**
 * Executive Contract — forma estable del payload de Propuesta ejecutiva (MC1).
 *
 * Dominio puro: normalización defensiva sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Executive_Contract {

    public const STATUS_READY = 'ready';

    public const STATUS_EMPTY = 'empty';

    public const SLOT_CURRENT = 'current';

    public const SLOT_NEXT = 'next';

    public const SLOT_THIRD = 'third';

    public const REASON_FOCUS_CURRENT = 'focus_current';

    public const REASON_CONTINUITY = 'continuity';

    public const FOCUS_REASON_FIRST_LIST_WITH_ELIGIBLE = 'first_list_with_eligible_tasks';

    public const FOCUS_REASON_SPRINT_ACTIVE = 'sprint_active';

    public const EMPTY_REASON_NO_ELIGIBLE_TASKS = 'no_eligible_tasks';

    public const META_VERSION = 1;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function normalize_proposal(array $payload): array {
        $status = self::normalize_status($payload['status'] ?? self::STATUS_EMPTY);
        $focus_list = self::normalize_focus_list($payload['focus_list'] ?? null);
        $tasks = self::normalize_tasks($payload['tasks'] ?? []);
        $meta = self::normalize_meta($payload['meta'] ?? [], $status);

        return [
            'success' => !empty($payload['success']),
            'status' => $status,
            'focus_list' => $focus_list,
            'tasks' => $tasks,
            'meta' => $meta,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function normalize_status($value): string {
        $status = is_string($value) ? strtolower(trim($value)) : '';

        if ($status === self::STATUS_READY) {
            return self::STATUS_READY;
        }

        return self::STATUS_EMPTY;
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>|null
     */
    private static function normalize_focus_list($value): ?array {
        if (!is_array($value)) {
            return null;
        }

        $id = (int) ($value['id'] ?? 0);

        if ($id < 1) {
            return null;
        }

        return [
            'id' => $id,
            'title' => self::normalize_string($value['title'] ?? ''),
            'source_category' => self::normalize_source_category($value['source_category'] ?? 'user'),
            'importance' => (int) ($value['importance'] ?? 0),
        ];
    }

    /**
     * @param mixed $raw_tasks
     * @return list<array<string,mixed>>
     */
    private static function normalize_tasks($raw_tasks): array {
        if (!is_array($raw_tasks)) {
            return [];
        }

        $tasks = [];

        foreach ($raw_tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $normalized = self::normalize_task($task);

            if ($normalized !== null) {
                $tasks[] = $normalized;
            }
        }

        return $tasks;
    }

    /**
     * @param array<string,mixed> $task
     * @return array<string,mixed>|null
     */
    private static function normalize_task(array $task): ?array {
        $task_id = (int) ($task['task_id'] ?? 0);

        if ($task_id < 1) {
            return null;
        }

        $slot = self::normalize_slot($task['slot'] ?? '');

        if ($slot === '') {
            return null;
        }

        $actionable = !empty($task['actionable']);
        $continuation = !empty($task['continuation']);

        $normalized = [
            'slot' => $slot,
            'task_id' => $task_id,
            'title' => self::normalize_string($task['title'] ?? ''),
            'description' => self::nullable_string($task['description'] ?? null),
            'default_bucket' => self::normalize_bucket($task['default_bucket'] ?? 'primary'),
            'due_at' => self::nullable_string($task['due_at'] ?? null),
            'is_overdue' => !empty($task['is_overdue']),
            'actionable' => $actionable,
            'continuation' => $continuation,
            'executive_actions' => $actionable
                ? self::normalize_executive_actions($task['executive_actions'] ?? [])
                : [],
            'primary_action' => $actionable
                ? self::normalize_primary_action($task['primary_action'] ?? null)
                : null,
            'reason' => $actionable ? self::REASON_FOCUS_CURRENT : self::REASON_CONTINUITY,
        ];

        if ($actionable) {
            $normalized['origin_key'] = self::nullable_string($task['origin_key'] ?? null);
            $normalized['source'] = self::normalize_executive_source($task['source'] ?? null);
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_slot($value): string {
        $slot = is_string($value) ? strtolower(trim($value)) : '';

        if (
            $slot === self::SLOT_CURRENT
            || $slot === self::SLOT_NEXT
            || $slot === self::SLOT_THIRD
        ) {
            return $slot;
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private static function normalize_bucket($value): string {
        $bucket = is_string($value) ? strtolower(trim($value)) : '';

        if ($bucket === 'secondary') {
            return 'secondary';
        }

        return 'primary';
    }

    /**
     * @param mixed $raw_actions
     * @return list<array<string,mixed>>
     */
    private static function normalize_executive_actions($raw_actions): array {
        if (!is_array($raw_actions)) {
            return [];
        }

        $actions = [];

        foreach ($raw_actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $key = self::normalize_string($action['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $actions[] = [
                'key' => $key,
                'type' => self::normalize_string($action['type'] ?? ''),
                'category' => self::normalize_string($action['category'] ?? ''),
                'label' => self::normalize_string($action['label'] ?? ''),
                'placement' => self::normalize_string($action['placement'] ?? 'primary'),
                'target_status' => self::nullable_string($action['target_status'] ?? null),
                'url' => self::nullable_string($action['url'] ?? null),
                'handler' => self::nullable_string($action['handler'] ?? null),
            ];
        }

        return $actions;
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>|null
     */
    private static function normalize_primary_action($value): ?array {
        if (!is_array($value)) {
            return null;
        }

        $type = self::normalize_string($value['type'] ?? '');

        if ($type === '') {
            return null;
        }

        $normalized = [
            'key' => self::nullable_string($value['key'] ?? null),
            'type' => $type,
            'label' => self::normalize_string($value['label'] ?? ''),
        ];

        if ($type === 'navigate') {
            $normalized['url'] = self::nullable_string($value['url'] ?? null);
        }

        if ($type === 'handler') {
            $normalized['handler'] = self::nullable_string($value['handler'] ?? null);
        }

        if ($type === 'status') {
            $normalized['to'] = self::nullable_string($value['to'] ?? null);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function normalize_meta(array $meta, string $status): array {
        $empty_reason = self::nullable_string($meta['empty_reason'] ?? null);

        if ($status !== self::STATUS_EMPTY) {
            $empty_reason = null;
        }

        if ($status === self::STATUS_EMPTY && $empty_reason === null) {
            $empty_reason = self::EMPTY_REASON_NO_ELIGIBLE_TASKS;
        }

        return [
            'version' => self::META_VERSION,
            'eligible_count_in_focus_list' => max(0, (int) ($meta['eligible_count_in_focus_list'] ?? 0)),
            'focus_reason' => $status === self::STATUS_READY
                ? self::normalize_focus_reason($meta['focus_reason'] ?? null)
                : null,
            'empty_reason' => $empty_reason,
            'sprint' => self::normalize_sprint_meta($meta['sprint'] ?? null),
        ];
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>
     */
    private static function normalize_sprint_meta($value): array {
        if (!is_array($value)) {
            return [
                'sprint_active' => false,
                'active_focus_list_id' => null,
                'sprint_started_at' => null,
                'last_executive_action_at' => null,
                'sprint_expires_at' => null,
                'seconds_remaining' => null,
                'focus_reason' => null,
                'current_focus_list_id' => null,
                'inactive_reason' => 'no_active_sprint',
            ];
        }

        $sprint_active = !empty($value['sprint_active']);
        $inactive_reason = self::nullable_string($value['inactive_reason'] ?? null);

        if (!$sprint_active && ($inactive_reason !== 'expired' && $inactive_reason !== 'no_active_sprint')) {
            $inactive_reason = 'no_active_sprint';
        }

        if ($sprint_active) {
            $inactive_reason = null;
        }

        $focus_reason = self::nullable_string($value['focus_reason'] ?? null);

        if ($focus_reason !== null && $focus_reason !== self::FOCUS_REASON_SPRINT_ACTIVE) {
            $focus_reason = self::FOCUS_REASON_FIRST_LIST_WITH_ELIGIBLE;
        }

        return [
            'sprint_active' => $sprint_active,
            'active_focus_list_id' => self::nullable_positive_int($value['active_focus_list_id'] ?? null),
            'sprint_started_at' => self::nullable_non_negative_int($value['sprint_started_at'] ?? null),
            'last_executive_action_at' => self::nullable_non_negative_int($value['last_executive_action_at'] ?? null),
            'sprint_expires_at' => self::nullable_non_negative_int($value['sprint_expires_at'] ?? null),
            'seconds_remaining' => $sprint_active
                ? max(0, (int) ($value['seconds_remaining'] ?? 0))
                : null,
            'focus_reason' => $focus_reason,
            'current_focus_list_id' => self::nullable_positive_int($value['current_focus_list_id'] ?? null),
            'inactive_reason' => $inactive_reason,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function nullable_positive_int($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    /**
     * @param mixed $value
     */
    private static function nullable_non_negative_int($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized >= 0 ? $normalized : null;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_focus_reason($value): string {
        $reason = is_string($value) ? strtolower(trim($value)) : '';

        if ($reason === self::FOCUS_REASON_SPRINT_ACTIVE) {
            return self::FOCUS_REASON_SPRINT_ACTIVE;
        }

        return self::FOCUS_REASON_FIRST_LIST_WITH_ELIGIBLE;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_string($value): string {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param mixed $value
     */
    private static function nullable_string($value): ?string {
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
    private static function normalize_executive_source($value): ?string {
        $source = is_string($value) ? strtolower(trim($value)) : '';

        if ($source === 'system' || $source === 'user') {
            return $source;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_source_category($value): string {
        $category = is_string($value) ? strtolower(trim($value)) : '';

        return $category !== '' ? $category : 'user';
    }
}
