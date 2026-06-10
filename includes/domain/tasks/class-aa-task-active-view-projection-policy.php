<?php
/**
 * Task Active View Projection Policy — proyecta buckets/capabilities de view=active.
 *
 * Dominio puro: interpreta señales defer/dismiss y facts de task/lista para
 * producir task_bucket_order_by_list y evaluaciones enriquecidas. Sin SQL ni UI.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-task.php';
require_once __DIR__ . '/class-aa-task-list.php';

final class AA_Task_Active_View_Projection_Policy {

    public const VIEW_ACTIVE = 'active';

    public const BUCKET_PRIMARY = 'primary';

    public const BUCKET_SECONDARY = 'secondary';

    public const REASON_DEFAULT_PRIMARY = 'default_primary';

    public const REASON_DEFERRED = 'deferred';

    public const REASON_DISMISSED = 'dismissed';

    public const REASON_NOT_PENDING = 'not_pending';

    public const REASON_SYSTEM_COMPLETED = 'system_completed';

    public const REASON_LIST_NOT_ACTIVE = 'list_not_active';

    /**
     * @param array<string,mixed> $input lists, tasks, list_order, task_order_by_list, task_evaluations_by_id, now
     * @return array{
     *     task_bucket_order_by_list:array<int,array{primary:list<int>,secondary:list<int>}>,
     *     task_evaluations_by_id:array<int,array<string,mixed>>
     * }
     */
    public function project(array $input): array {
        $lists = $this->normalize_lists($input['lists'] ?? []);
        $tasks = $this->normalize_tasks($input['tasks'] ?? []);
        $lists_by_id = $this->index_lists_by_id($lists);
        $tasks_by_id = $this->index_tasks_by_id($tasks);
        $list_order = $this->resolve_list_order($input, $lists_by_id);
        $task_order_by_list = $this->normalize_task_order_by_list($input['task_order_by_list'] ?? []);
        $signal_evaluations = $this->normalize_evaluations_by_id($input['task_evaluations_by_id'] ?? []);

        $task_bucket_order_by_list = [];
        $enriched_evaluations = [];

        foreach ($list_order as $list_id) {
            $list = $lists_by_id[$list_id] ?? null;
            $task_bucket_order_by_list[$list_id] = [
                self::BUCKET_PRIMARY => [],
                self::BUCKET_SECONDARY => [],
            ];

            if ($list === null) {
                continue;
            }

            $ordered_ids = $task_order_by_list[$list_id] ?? [];

            foreach ($ordered_ids as $task_id) {
                $task = $tasks_by_id[$task_id] ?? null;

                if ($task === null) {
                    continue;
                }

                $base_eval = $signal_evaluations[$task_id] ?? $this->empty_signal_evaluation();
                $projection = $this->evaluate_task_projection($task, $list, $base_eval);
                $enriched_evaluations[$task_id] = $this->merge_evaluation($base_eval, $projection);

                if (!$projection['visible_in_active'] || $projection['projected_bucket'] === null) {
                    continue;
                }

                $task_bucket_order_by_list[$list_id][$projection['projected_bucket']][] = $task_id;
            }
        }

        foreach ($signal_evaluations as $task_id => $base_eval) {
            if (isset($enriched_evaluations[$task_id])) {
                continue;
            }

            $task = $tasks_by_id[$task_id] ?? null;
            $list = $task !== null ? ($lists_by_id[$task->list_id()] ?? null) : null;

            if ($task === null || $list === null) {
                $enriched_evaluations[$task_id] = is_array($base_eval) ? $base_eval : $this->empty_signal_evaluation();
                continue;
            }

            $projection = $this->evaluate_task_projection($task, $list, $base_eval);
            $enriched_evaluations[$task_id] = $this->merge_evaluation($base_eval, $projection);
        }

        return [
            'task_bucket_order_by_list' => $task_bucket_order_by_list,
            'task_evaluations_by_id' => $enriched_evaluations,
        ];
    }

    /**
     * @param array<string,mixed> $base_eval
     * @return array{
     *     visible_in_active:bool,
     *     projected_bucket:string|null,
     *     projection_reason:string,
     *     suggested_active_bucket:string,
     *     can_defer:bool,
     *     can_dismiss:bool
     * }
     */
    private function evaluate_task_projection(AA_Task $task, AA_Task_List $list, array $base_eval): array {
        $suggested_active_bucket = self::BUCKET_PRIMARY;

        if (!$list->is_active()) {
            return $this->projection_result(
                false,
                null,
                self::REASON_LIST_NOT_ACTIVE,
                $suggested_active_bucket,
                false,
                false
            );
        }

        if (!$task->is_pending()) {
            return $this->projection_result(
                false,
                null,
                self::REASON_NOT_PENDING,
                $suggested_active_bucket,
                false,
                false
            );
        }

        $signals = is_array($base_eval['signals'] ?? null) ? $base_eval['signals'] : [];
        $signal_state = is_array($base_eval['state'] ?? null) ? $base_eval['state'] : [];
        $is_system_completed = !empty($signal_state['is_system_completed']);

        if ($is_system_completed) {
            return $this->projection_result(
                false,
                null,
                self::REASON_SYSTEM_COMPLETED,
                $suggested_active_bucket,
                false,
                false
            );
        }

        $is_dismiss_hiding = !empty($signal_state['is_dismiss_hiding']);
        $has_defer = !empty($signals['has_defer']);

        if ($is_dismiss_hiding) {
            return $this->projection_result(
                false,
                null,
                self::REASON_DISMISSED,
                $suggested_active_bucket,
                false,
                false
            );
        }

        if ($has_defer) {
            return $this->projection_result(
                true,
                self::BUCKET_SECONDARY,
                self::REASON_DEFERRED,
                $suggested_active_bucket,
                false,
                $this->resolve_can_dismiss($base_eval)
            );
        }

        return $this->projection_result(
            true,
            self::BUCKET_PRIMARY,
            self::REASON_DEFAULT_PRIMARY,
            $suggested_active_bucket,
            true,
            $this->resolve_can_dismiss($base_eval)
        );
    }

    /**
     * @param array<string,mixed> $base_eval Evaluación de AA_Task_Signal_Policy.
     */
    private function resolve_can_dismiss(array $base_eval): bool {
        $capabilities = is_array($base_eval['capabilities'] ?? null) ? $base_eval['capabilities'] : [];

        return !empty($capabilities['can_dismiss']);
    }

    /**
     * @return array{
     *     visible_in_active:bool,
     *     projected_bucket:string|null,
     *     projection_reason:string,
     *     suggested_active_bucket:string,
     *     can_defer:bool,
     *     can_dismiss:bool
     * }
     */
    private function projection_result(
        bool $visible_in_active,
        ?string $projected_bucket,
        string $projection_reason,
        string $suggested_active_bucket,
        bool $can_defer,
        bool $can_dismiss
    ): array {
        return [
            'visible_in_active' => $visible_in_active,
            'projected_bucket' => $projected_bucket,
            'projection_reason' => $projection_reason,
            'suggested_active_bucket' => $suggested_active_bucket,
            'can_defer' => $can_defer,
            'can_dismiss' => $can_dismiss,
        ];
    }

    /**
     * @param array<string,mixed> $base_eval
     * @param array<string,mixed> $projection
     * @return array<string,mixed>
     */
    private function merge_evaluation(array $base_eval, array $projection): array {
        return array_merge($base_eval, [
            'visible_in_active' => $projection['visible_in_active'],
            'projection' => [
                'view' => self::VIEW_ACTIVE,
                'visible_in_active' => $projection['visible_in_active'],
                'projected_bucket' => $projection['projected_bucket'],
                'projection_reason' => $projection['projection_reason'],
                'suggested_active_bucket' => $projection['suggested_active_bucket'],
            ],
            'capabilities' => [
                'can_defer' => $projection['can_defer'],
                'can_dismiss' => $projection['can_dismiss'],
                'can_reactivate' => false,
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function empty_signal_evaluation(): array {
        return [
            'signals' => [
                'has_defer' => false,
                'has_dismiss' => false,
                'defer_count' => 0,
                'dismiss_count' => 0,
            ],
            'state' => [
                'is_defer_active' => false,
                'is_dismiss_active' => false,
                'is_dismiss_hiding' => false,
            ],
            'capabilities' => [
                'can_defer' => false,
                'can_dismiss' => false,
                'can_reactivate' => false,
            ],
            'visible_in_active' => true,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<int,AA_Task_List> $lists_by_id
     * @return list<int>
     */
    private function resolve_list_order(array $input, array $lists_by_id): array {
        $ordered = [];
        $raw_order = $input['list_order'] ?? null;

        if (is_array($raw_order)) {
            foreach ($raw_order as $list_id) {
                $normalized = (int) $list_id;

                if ($normalized > 0) {
                    $ordered[] = $normalized;
                }
            }
        }

        if ($ordered !== []) {
            return $ordered;
        }

        foreach ($lists_by_id as $list_id => $list) {
            if ($list->is_active()) {
                $ordered[] = $list_id;
            }
        }

        return $ordered;
    }

    /**
     * @param mixed $raw_order
     * @return array<int,list<int>>
     */
    private function normalize_task_order_by_list($raw_order): array {
        if (!is_array($raw_order)) {
            return [];
        }

        $normalized = [];

        foreach ($raw_order as $list_id => $task_ids) {
            if (!is_array($task_ids)) {
                continue;
            }

            $normalized_list_id = (int) $list_id;

            if ($normalized_list_id < 1) {
                continue;
            }

            $normalized[$normalized_list_id] = [];

            foreach ($task_ids as $task_id) {
                $normalized_task_id = (int) $task_id;

                if ($normalized_task_id > 0) {
                    $normalized[$normalized_list_id][] = $normalized_task_id;
                }
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $raw_evaluations
     * @return array<int,array<string,mixed>>
     */
    private function normalize_evaluations_by_id($raw_evaluations): array {
        if (!is_array($raw_evaluations)) {
            return [];
        }

        $normalized = [];

        foreach ($raw_evaluations as $task_id => $evaluation) {
            if (!is_array($evaluation)) {
                continue;
            }

            $normalized_id = (int) $task_id;

            if ($normalized_id < 1) {
                continue;
            }

            $normalized[$normalized_id] = $evaluation;
        }

        return $normalized;
    }

    /**
     * @param mixed $raw_lists
     * @return list<AA_Task_List>
     */
    private function normalize_lists($raw_lists): array {
        if (!is_array($raw_lists)) {
            return [];
        }

        $lists = [];

        foreach ($raw_lists as $row) {
            if (!is_array($row)) {
                continue;
            }

            $lists[] = AA_Task_List::from_array($row);
        }

        return $lists;
    }

    /**
     * @param mixed $raw_tasks
     * @return list<AA_Task>
     */
    private function normalize_tasks($raw_tasks): array {
        if (!is_array($raw_tasks)) {
            return [];
        }

        $tasks = [];

        foreach ($raw_tasks as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tasks[] = AA_Task::from_array($row);
        }

        return $tasks;
    }

    /**
     * @param list<AA_Task_List> $lists
     * @return array<int,AA_Task_List>
     */
    private function index_lists_by_id(array $lists): array {
        $indexed = [];

        foreach ($lists as $list) {
            $indexed[$list->id()] = $list;
        }

        return $indexed;
    }

    /**
     * @param list<AA_Task> $tasks
     * @return array<int,AA_Task>
     */
    private function index_tasks_by_id(array $tasks): array {
        $indexed = [];

        foreach ($tasks as $task) {
            $indexed[$task->id()] = $task;
        }

        return $indexed;
    }
}
