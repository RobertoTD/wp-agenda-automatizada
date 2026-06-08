<?php
/**
 * Return Ignored User Tasks Use Case — regresa tareas ocultas por dismiss en listas activas.
 *
 * Write-only MC13N-1: cierra efecto activo de dismiss (dismiss_until=now) sin borrar historial.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-signal-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class ReturnIgnoredUserTasksUseCase {

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input = []): array {
        $now = $this->resolve_now($input);
        $pending_tasks = $this->collect_pending_tasks_in_active_lists();

        if ($pending_tasks === []) {
            return TaskUseCaseSupport::ok([
                'returned_count' => 0,
                'task_ids' => [],
            ]);
        }

        $task_ids = [];

        foreach ($pending_tasks as $task) {
            $task_id = (int) ($task['id'] ?? 0);

            if ($task_id > 0) {
                $task_ids[] = $task_id;
            }
        }

        $task_state_by_id = TaskStateRepository::find_by_task_ids($task_ids);
        $signal_policy = new AA_Task_Signal_Policy();
        $task_ids_to_return = [];

        foreach ($pending_tasks as $task) {
            $task_id = (int) ($task['id'] ?? 0);

            if ($task_id < 1) {
                continue;
            }

            $evaluation = $signal_policy->evaluate_task(
                AA_Task::from_array($task),
                $task_state_by_id[$task_id] ?? null,
                $now
            );
            $signal_state = is_array($evaluation['state'] ?? null) ? $evaluation['state'] : [];

            if (!empty($signal_state['is_dismiss_hiding'])) {
                $task_ids_to_return[] = $task_id;
            }
        }

        if ($task_ids_to_return === []) {
            return TaskUseCaseSupport::ok([
                'returned_count' => 0,
                'task_ids' => [],
            ]);
        }

        $updated = TaskStateRepository::clear_dismiss_hiding_effect_for_task_ids($task_ids_to_return, $now);
        $returned_ids = array_map('intval', array_keys($updated));

        return TaskUseCaseSupport::ok([
            'returned_count' => count($returned_ids),
            'task_ids' => $returned_ids,
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    private function resolve_now(array $input): string {
        $raw_now = $input['now'] ?? null;

        if (is_string($raw_now) && trim($raw_now) !== '') {
            return trim($raw_now);
        }

        return TaskUseCaseSupport::resolve_now();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function collect_pending_tasks_in_active_lists(): array {
        $lists = TaskListRepository::list_all('active');
        $pending_tasks = [];

        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $list_id = (int) ($list['id'] ?? 0);

            if ($list_id < 1) {
                continue;
            }

            foreach (TaskRepository::list_by_list_id($list_id) as $task) {
                if (!is_array($task)) {
                    continue;
                }

                if (($task['status'] ?? '') !== 'pending') {
                    continue;
                }

                $pending_tasks[] = $task;
            }
        }

        return $pending_tasks;
    }
}
