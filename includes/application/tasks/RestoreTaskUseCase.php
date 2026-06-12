<?php
/**
 * Restore Task Use Case — restaura tarea user archivada (archived_at = null).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-governance-policy.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class RestoreTaskUseCase {

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

        if (!(new AA_Task_Governance_Policy())->can_restore_task($task)) {
            return TaskUseCaseSupport::fail('task_not_restorable', 'Esta tarea no se puede restaurar.');
        }

        $row = TaskRepository::restore($task_id);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo restaurar la tarea.');
        }

        return TaskUseCaseSupport::ok(['task' => $row]);
    }
}
