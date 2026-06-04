<?php
/**
 * Get Task Board Use Case — tablero de listas/tareas con organización local.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-prioritization-policy.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class GetTaskBoardUseCase {

    /**
     * @return array{
     *     lists:list<array<string,mixed>>,
     *     tasks:list<array<string,mixed>>,
     *     organization:array{
     *         list_order:list<int>,
     *         task_order_by_list:array<int,list<int>>,
     *         executive_candidates:list<int>
     *     }
     * }
     */
    public function execute(): array {
        $lists = TaskListRepository::list_all('active');
        $tasks = $this->collect_tasks_for_lists($lists);
        $now = TaskUseCaseSupport::resolve_now();

        $organization = (new AA_Task_Prioritization_Policy())->prioritize([
            'lists' => $lists,
            'tasks' => $tasks,
            'now' => $now,
        ]);

        return [
            'lists' => $lists,
            'tasks' => $tasks,
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
}
