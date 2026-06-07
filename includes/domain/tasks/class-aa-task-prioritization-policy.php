<?php
/**
 * Task Prioritization Policy — motor local/free de orden para Listas/Tareas.
 *
 * Dominio puro: sin WordPress, SQL ni persistencia de scores.
 * Implementa AA_Prioritization_Provider_Interface para uso local.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-task.php';
require_once __DIR__ . '/class-aa-task-list.php';
require_once __DIR__ . '/interface-aa-prioritization-provider.php';

final class AA_Task_Prioritization_Policy implements AA_Prioritization_Provider_Interface {

    private const DUE_BUCKET_OVERDUE = 0;

    private const DUE_BUCKET_UPCOMING = 1;

    private const DUE_BUCKET_NONE = 2;

    /**
     * @param array<string,mixed> $snapshot
     * @return array{
     *     list_order:list<int>,
     *     task_order_by_list:array<int,list<int>>,
     *     executive_candidates:list<int>
     * }
     */
    public function prioritize(array $snapshot): array {
        $now = $this->resolve_now($snapshot);
        $lists = $this->normalize_lists($snapshot['lists'] ?? []);
        $tasks = $this->normalize_tasks($snapshot['tasks'] ?? []);

        if ($lists === [] && $tasks === []) {
            return $this->empty_result();
        }

        $active_lists = array_values(array_filter($lists, static function (AA_Task_List $list): bool {
            return $list->is_active();
        }));

        usort($active_lists, function (AA_Task_List $a, AA_Task_List $b): int {
            return $this->compare_lists($a, $b);
        });

        $list_order = array_map(static function (AA_Task_List $list): int {
            return $list->id();
        }, $active_lists);

        $active_list_ids = array_fill_keys($list_order, true);
        $tasks_by_list = [];

        foreach ($tasks as $task) {
            $list_id = $task->list_id();

            if ($list_id < 1 || !isset($active_list_ids[$list_id])) {
                continue;
            }

            if (!isset($tasks_by_list[$list_id])) {
                $tasks_by_list[$list_id] = [];
            }

            $tasks_by_list[$list_id][] = $task;
        }

        $task_order_by_list = [];
        $executive_candidates = [];

        foreach ($list_order as $list_id) {
            $list_tasks = $tasks_by_list[$list_id] ?? [];

            usort($list_tasks, function (AA_Task $a, AA_Task $b) use ($now): int {
                return $this->compare_tasks($a, $b, $now);
            });

            $ordered_ids = array_map(static function (AA_Task $task): int {
                return $task->id();
            }, $list_tasks);

            $task_order_by_list[$list_id] = $ordered_ids;

            foreach ($list_tasks as $task) {
                if ($task->is_pending()) {
                    $executive_candidates[] = $task->id();
                }
            }
        }

        usort($executive_candidates, function (int $a_id, int $b_id) use ($tasks, $now): int {
            $a = $this->find_task_by_id($tasks, $a_id);
            $b = $this->find_task_by_id($tasks, $b_id);

            if ($a === null || $b === null) {
                return $a_id <=> $b_id;
            }

            return $this->compare_tasks($a, $b, $now);
        });

        return [
            'list_order' => $list_order,
            'task_order_by_list' => $task_order_by_list,
            'executive_candidates' => $executive_candidates,
        ];
    }

    /**
     * @return array{
     *     list_order:list<int>,
     *     task_order_by_list:array<int,list<int>>,
     *     executive_candidates:list<int>
     * }
     */
    private function empty_result(): array {
        return [
            'list_order' => [],
            'task_order_by_list' => [],
            'executive_candidates' => [],
        ];
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    private function resolve_now(array $snapshot): string {
        $now = $snapshot['now'] ?? null;

        if (is_string($now) && trim($now) !== '') {
            return trim($now);
        }

        return '1970-01-01 00:00:00';
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
     * @param list<AA_Task> $tasks
     */
    private function find_task_by_id(array $tasks, int $id): ?AA_Task {
        foreach ($tasks as $task) {
            if ($task->id() === $id) {
                return $task;
            }
        }

        return null;
    }

    private function compare_lists(AA_Task_List $a, AA_Task_List $b): int {
        $importance_cmp = $a->importance() <=> $b->importance();

        if ($importance_cmp !== 0) {
            return $importance_cmp;
        }

        $position_cmp = $a->position() <=> $b->position();

        if ($position_cmp !== 0) {
            return $position_cmp;
        }

        $id_cmp = $a->id() <=> $b->id();

        if ($id_cmp !== 0) {
            return $id_cmp;
        }

        return strcmp($a->title(), $b->title());
    }

    private function compare_tasks(AA_Task $a, AA_Task $b, string $now): int {
        $a_done = $a->is_done() ? 1 : 0;
        $b_done = $b->is_done() ? 1 : 0;

        if ($a_done !== $b_done) {
            return $a_done <=> $b_done;
        }

        if ($a->is_done()) {
            return $this->compare_stable_tail($a, $b);
        }

        $bucket_cmp = $this->due_bucket($a, $now) <=> $this->due_bucket($b, $now);

        if ($bucket_cmp !== 0) {
            return $bucket_cmp;
        }

        $a_due = $a->due_at();
        $b_due = $b->due_at();

        if ($a_due !== null && $b_due !== null) {
            $due_cmp = strcmp($a_due, $b_due);

            if ($due_cmp !== 0) {
                return $due_cmp;
            }
        }

        $importance_cmp = $a->importance() <=> $b->importance();

        if ($importance_cmp !== 0) {
            return $importance_cmp;
        }

        return $this->compare_stable_tail($a, $b);
    }

    private function compare_stable_tail(AA_Task $a, AA_Task $b): int {
        $position_cmp = $a->position() <=> $b->position();

        if ($position_cmp !== 0) {
            return $position_cmp;
        }

        $id_cmp = $a->id() <=> $b->id();

        if ($id_cmp !== 0) {
            return $id_cmp;
        }

        return strcmp($a->title(), $b->title());
    }

    /**
     * @param string $now Y-m-d H:i:s
     */
    private function due_bucket(AA_Task $task, string $now): int {
        if ($task->is_overdue($now)) {
            return self::DUE_BUCKET_OVERDUE;
        }

        if ($task->has_upcoming_due($now)) {
            return self::DUE_BUCKET_UPCOMING;
        }

        return self::DUE_BUCKET_NONE;
    }
}
