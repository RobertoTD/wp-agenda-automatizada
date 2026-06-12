<?php
/**
 * Delete Task List Use Case — hard delete de lista user y cascada de tareas (sin soft delete).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-governance-policy.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-list-governance-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskActionRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskListRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class DeleteTaskListUseCase {

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $list_id = TaskUseCaseSupport::normalize_list_id($input['list_id'] ?? $input['id'] ?? 0);
        $list = TaskUseCaseSupport::find_list($list_id);

        if ($list === null) {
            return TaskUseCaseSupport::fail('list_not_found', 'Lista no encontrada.');
        }

        if (!(new AA_Task_List_Governance_Policy())->can_delete_list($list)) {
            return TaskUseCaseSupport::fail('list_not_deletable', 'Esta lista no se puede eliminar.');
        }

        $tasks = TaskRepository::list_by_list_id($list_id);
        $task_governance = new AA_Task_Governance_Policy();
        $task_ids = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            if (!$task_governance->can_delete_task($task)) {
                return TaskUseCaseSupport::fail(
                    'list_has_protected_tasks',
                    'Esta lista contiene tareas que no se pueden eliminar.'
                );
            }

            $task_id = (int) ($task['id'] ?? 0);

            if ($task_id > 0) {
                $task_ids[] = $task_id;
            }
        }

        $actions_deleted = TaskActionRepository::delete_by_task_ids($task_ids);

        if ($actions_deleted === false) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar la lista.');
        }

        if (!TaskStateRepository::delete_by_task_ids($task_ids)) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar la lista.');
        }

        $tasks_deleted = TaskRepository::delete_by_list_id($list_id);

        if ($tasks_deleted === false) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar la lista.');
        }

        if (!TaskListRepository::delete($list_id)) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar la lista.');
        }

        return TaskUseCaseSupport::ok([
            'list_id' => $list_id,
            'deleted' => true,
            'tasks_deleted' => count($task_ids),
        ]);
    }
}
