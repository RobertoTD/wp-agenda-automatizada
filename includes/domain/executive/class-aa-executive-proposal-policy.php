<?php
/**
 * Executive Proposal Policy — lista foco y top-3 de tareas candidatas (MC1).
 *
 * Dominio puro: sin WordPress, SQL ni render.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-executive-contract.php';
require_once dirname(__DIR__) . '/tasks/class-aa-task.php';
require_once dirname(__DIR__) . '/tasks/class-aa-task-list.php';
require_once dirname(__DIR__) . '/tasks/class-aa-task-active-view-projection-policy.php';

final class AA_Executive_Proposal_Policy {

    private const MAX_TASKS = 3;

    /**
     * @param array<string,mixed> $input lists, tasks, organization
     * @param array<string,mixed> $context preferred_focus_list_id
     * @return array{
     *     status:string,
     *     focus_list_id:int|null,
     *     task_ids:list<int>,
     *     eligible_count_in_focus_list:int,
     *     focus_reason:string|null,
     *     preferred_focus_list_id:int|null,
     *     preferred_focus_used:bool
     * }
     */
    public function propose(array $input, array $context = []): array {
        $lists = $this->normalize_lists($input['lists'] ?? []);
        $tasks = $this->normalize_tasks($input['tasks'] ?? []);
        $organization = is_array($input['organization'] ?? null) ? $input['organization'] : [];
        $evaluations_by_id = $this->normalize_evaluations_by_id($organization['task_evaluations_by_id'] ?? []);
        $list_order = $this->normalize_id_list($organization['list_order'] ?? []);

        $lists_by_id = $this->index_lists_by_id($lists);
        $tasks_by_id = $this->index_tasks_by_id($tasks);
        $eligible_by_list = $this->count_eligible_by_list($tasks, $evaluations_by_id);

        $preferred_focus_list_id = isset($context['preferred_focus_list_id'])
            ? (int) $context['preferred_focus_list_id']
            : 0;
        $sprint_active = !empty($context['sprint_active']);

        $focus_list_id = null;
        $preferred_focus_used = false;

        if ($preferred_focus_list_id > 0) {
            $preferred_list = $lists_by_id[$preferred_focus_list_id] ?? null;

            if (
                $preferred_list !== null
                && $preferred_list->is_active()
                && ($eligible_by_list[$preferred_focus_list_id] ?? 0) >= 1
            ) {
                $focus_list_id = $preferred_focus_list_id;
                $preferred_focus_used = true;
            }
        }

        if ($focus_list_id === null) {
            $focus_list_id = $this->resolve_focus_list_id($list_order, $lists_by_id, $eligible_by_list);
        }

        if ($focus_list_id === null) {
            return [
                'status' => AA_Executive_Contract::STATUS_EMPTY,
                'focus_list_id' => null,
                'task_ids' => [],
                'eligible_count_in_focus_list' => 0,
                'focus_reason' => null,
                'preferred_focus_list_id' => $preferred_focus_list_id > 0 ? $preferred_focus_list_id : null,
                'preferred_focus_used' => false,
            ];
        }

        $eligible_tasks = $this->collect_eligible_tasks_for_list(
            $focus_list_id,
            $tasks,
            $evaluations_by_id
        );

        usort($eligible_tasks, function (AA_Task $a, AA_Task $b) use ($evaluations_by_id): int {
            return $this->compare_executive_tasks($a, $b, $evaluations_by_id);
        });

        $task_ids = [];

        foreach (array_slice($eligible_tasks, 0, self::MAX_TASKS) as $task) {
            $task_ids[] = $task->id();
        }

        $focus_reason = ($sprint_active || $preferred_focus_used)
            ? AA_Executive_Contract::FOCUS_REASON_SPRINT_ACTIVE
            : AA_Executive_Contract::FOCUS_REASON_FIRST_LIST_WITH_ELIGIBLE;

        return [
            'status' => AA_Executive_Contract::STATUS_READY,
            'focus_list_id' => $focus_list_id,
            'task_ids' => $task_ids,
            'eligible_count_in_focus_list' => count($eligible_tasks),
            'focus_reason' => $focus_reason,
            'preferred_focus_list_id' => $preferred_focus_list_id > 0 ? $preferred_focus_list_id : null,
            'preferred_focus_used' => $preferred_focus_used,
        ];
    }

    /**
     * @param list<int>                      $list_order
     * @param array<int,AA_Task_List>        $lists_by_id
     * @param array<int,int>                 $eligible_by_list
     */
    private function resolve_focus_list_id(array $list_order, array $lists_by_id, array $eligible_by_list): ?int {
        foreach ($list_order as $list_id) {
            $list = $lists_by_id[$list_id] ?? null;

            if ($list === null || !$list->is_active()) {
                continue;
            }

            if (($eligible_by_list[$list_id] ?? 0) < 1) {
                continue;
            }

            return $list_id;
        }

        return null;
    }

    /**
     * @param list<AA_Task>                   $tasks
     * @param array<int,array<string,mixed>>  $evaluations_by_id
     * @return array<int,int>
     */
    private function count_eligible_by_list(array $tasks, array $evaluations_by_id): array {
        $counts = [];

        foreach ($tasks as $task) {
            if (!$this->is_eligible_task($task, $evaluations_by_id)) {
                continue;
            }

            $list_id = $task->list_id();

            if ($list_id < 1) {
                continue;
            }

            if (!isset($counts[$list_id])) {
                $counts[$list_id] = 0;
            }

            $counts[$list_id]++;
        }

        return $counts;
    }

    /**
     * @param list<AA_Task>                   $tasks
     * @param array<int,array<string,mixed>>  $evaluations_by_id
     * @return list<AA_Task>
     */
    private function collect_eligible_tasks_for_list(int $list_id, array $tasks, array $evaluations_by_id): array {
        $eligible = [];

        foreach ($tasks as $task) {
            if ($task->list_id() !== $list_id) {
                continue;
            }

            if (!$this->is_eligible_task($task, $evaluations_by_id)) {
                continue;
            }

            $eligible[] = $task;
        }

        return $eligible;
    }

    /**
     * @param array<int,array<string,mixed>> $evaluations_by_id
     */
    public function is_eligible_task(AA_Task $task, array $evaluations_by_id): bool {
        if (!$task->is_pending() || $task->is_archived()) {
            return false;
        }

        $evaluation = $evaluations_by_id[$task->id()] ?? null;

        if (!is_array($evaluation)) {
            return false;
        }

        if (empty($evaluation['visible_in_active'])) {
            return false;
        }

        $bucket = $this->resolve_projected_bucket($evaluation);

        return $bucket === AA_Task_Active_View_Projection_Policy::BUCKET_PRIMARY
            || $bucket === AA_Task_Active_View_Projection_Policy::BUCKET_SECONDARY;
    }

    /**
     * @param array<int,array<string,mixed>> $evaluations_by_id
     */
    private function compare_executive_tasks(AA_Task $a, AA_Task $b, array $evaluations_by_id): int {
        $a_bucket = $this->resolve_task_bucket_rank($a, $evaluations_by_id);
        $b_bucket = $this->resolve_task_bucket_rank($b, $evaluations_by_id);

        if ($a_bucket !== $b_bucket) {
            return $a_bucket <=> $b_bucket;
        }

        $importance_cmp = $b->importance() <=> $a->importance();

        if ($importance_cmp !== 0) {
            return $importance_cmp;
        }

        $position_cmp = $a->position() <=> $b->position();

        if ($position_cmp !== 0) {
            return $position_cmp;
        }

        return $a->id() <=> $b->id();
    }

    /**
     * @param array<int,array<string,mixed>> $evaluations_by_id
     */
    private function resolve_task_bucket_rank(AA_Task $task, array $evaluations_by_id): int {
        $evaluation = $evaluations_by_id[$task->id()] ?? null;
        $bucket = is_array($evaluation)
            ? $this->resolve_projected_bucket($evaluation)
            : $task->default_bucket();

        return $bucket === AA_Task_Active_View_Projection_Policy::BUCKET_SECONDARY ? 1 : 0;
    }

    /**
     * @param array<string,mixed> $evaluation
     */
    private function resolve_projected_bucket(array $evaluation): ?string {
        $projection = is_array($evaluation['projection'] ?? null) ? $evaluation['projection'] : [];
        $bucket = isset($projection['projected_bucket'])
            ? strtolower(trim((string) $projection['projected_bucket']))
            : '';

        if ($bucket === AA_Task_Active_View_Projection_Policy::BUCKET_PRIMARY) {
            return AA_Task_Active_View_Projection_Policy::BUCKET_PRIMARY;
        }

        if ($bucket === AA_Task_Active_View_Projection_Policy::BUCKET_SECONDARY) {
            return AA_Task_Active_View_Projection_Policy::BUCKET_SECONDARY;
        }

        return null;
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

            $id = (int) $task_id;

            if ($id < 1) {
                continue;
            }

            $normalized[$id] = $evaluation;
        }

        return $normalized;
    }

    /**
     * @param mixed $raw_ids
     * @return list<int>
     */
    private function normalize_id_list($raw_ids): array {
        if (!is_array($raw_ids)) {
            return [];
        }

        $ids = [];

        foreach ($raw_ids as $raw_id) {
            $id = (int) $raw_id;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
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
