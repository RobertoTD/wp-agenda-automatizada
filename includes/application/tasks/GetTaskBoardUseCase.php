<?php
/**
 * Get Task Board Use Case — tablero de listas/tareas con organización local.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-active-view-projection-policy.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-prioritization-policy.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-signal-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class GetTaskBoardUseCase {

    /**
     * @return array{
     *     lists:list<array<string,mixed>>,
     *     tasks:list<array<string,mixed>>,
     *     task_state_by_id:array<int,array<string,mixed>>,
     *     organization:array{
     *         list_order:list<int>,
     *         task_order_by_list:array<int,list<int>>,
     *         task_bucket_order_by_list:array<int,array{primary:list<int>,secondary:list<int>}>,
     *         executive_candidates:list<int>,
     *         task_evaluations_by_id:array<int,array<string,mixed>>
     *     }
     * }
     */
    public function execute(): array {
        $lists = TaskListRepository::list_all('active');
        $tasks = $this->collect_tasks_for_lists($lists);
        $now = TaskUseCaseSupport::resolve_now();
        $task_state_by_id = TaskStateRepository::find_by_task_ids($this->collect_task_ids($tasks));

        $base_organization = (new AA_Task_Prioritization_Policy())->prioritize([
            'lists' => $lists,
            'tasks' => $tasks,
            'now' => $now,
        ]);

        $signal_evaluations_by_id = (new AA_Task_Signal_Policy())->evaluate_all([
            'tasks' => $tasks,
            'task_state_by_id' => $task_state_by_id,
            'now' => $now,
        ]);

        $active_projection = (new AA_Task_Active_View_Projection_Policy())->project([
            'lists' => $lists,
            'tasks' => $tasks,
            'list_order' => $base_organization['list_order'],
            'task_order_by_list' => $base_organization['task_order_by_list'],
            'task_evaluations_by_id' => $signal_evaluations_by_id,
            'now' => $now,
        ]);

        $organization = [
            'list_order' => $base_organization['list_order'],
            'task_order_by_list' => $base_organization['task_order_by_list'],
            'task_bucket_order_by_list' => $active_projection['task_bucket_order_by_list'],
            'executive_candidates' => $base_organization['executive_candidates'],
            'task_evaluations_by_id' => $active_projection['task_evaluations_by_id'],
        ];

        return [
            'lists' => $lists,
            'tasks' => $tasks,
            'task_state_by_id' => $task_state_by_id,
            'organization' => $organization,
        ];
    }

    /**
     * @param list<array<string,mixed>> $lists
     * @return list<array<string,mixed>>
     */
    private function collect_tasks_for_lists(array $lists): array {
        $tasks = [];

        foreach ($lists as $list) {
            $list_id = (int) ($list['id'] ?? 0);

            if ($list_id < 1) {
                continue;
            }

            foreach (TaskRepository::list_by_list_id($list_id) as $task) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    /**
     * @param list<array<string,mixed>> $tasks
     * @return list<int>
     */
    private function collect_task_ids(array $tasks): array {
        $ids = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $task_id = (int) ($task['id'] ?? 0);

            if ($task_id > 0) {
                $ids[] = $task_id;
            }
        }

        return $ids;
    }
}
