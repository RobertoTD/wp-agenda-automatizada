<?php
/**
 * Delete Task Use Case — hard delete de tarea user y dependencias (sin soft delete).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-governance-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskActionRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class DeleteTaskUseCase {

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $task_id = TaskUseCaseSupport::normalize_task_id($input['task_id'] ?? $input['id'] ?? 0);
        $task = TaskUseCaseSupport::find_task($task_id);

        if ($task === null) {
            return TaskUseCaseSupport::fail('task_not_found', 'Tarea no encontrada.');
        }

        if (!(new AA_Task_Governance_Policy())->can_delete_task($task)) {
            return TaskUseCaseSupport::fail('task_not_deletable', 'Esta tarea no se puede eliminar.');
        }

        $actions_deleted = TaskActionRepository::delete_by_task_id($task_id);

        if ($actions_deleted === false) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar la tarea.');
        }

        if (!TaskStateRepository::delete_by_task_id($task_id)) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar la tarea.');
        }

        if (!TaskRepository::delete($task_id)) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar la tarea.');
        }

        return TaskUseCaseSupport::ok([
            'task_id' => $task_id,
            'deleted' => true,
        ]);
    }
}
