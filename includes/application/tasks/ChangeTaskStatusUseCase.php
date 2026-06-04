<?php
/**
 * Change Task Status Use Case — pending/done; done usa mark_completed().
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class ChangeTaskStatusUseCase {

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

        $status = TaskUseCaseSupport::normalize_task_status($input['status'] ?? null);

        if ($status === null) {
            return TaskUseCaseSupport::fail('invalid_status', 'Estado de tarea inválido.');
        }

        if ($status === 'done') {
            $row = TaskRepository::mark_completed($task_id, TaskUseCaseSupport::resolve_now());
        } else {
            $row = TaskRepository::update($task_id, [
                'status' => 'pending',
                'completed_at' => null,
            ]);
        }

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo actualizar el estado de la tarea.');
        }

        return TaskUseCaseSupport::ok(['task' => $row]);
    }
}
