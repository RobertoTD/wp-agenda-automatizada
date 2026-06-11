<?php
/**
 * Change Task Default Bucket Use Case — clasificación natural primary/secondary (MC13O-H3B-1).
 *
 * Write-only: actualiza aa_tasks.default_bucket; no altera projection, defer/dismiss ni status.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-governance-policy.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class ChangeTaskDefaultBucketUseCase {

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $task_id = TaskUseCaseSupport::normalize_task_id($input['task_id'] ?? $input['id'] ?? 0);

        if ($task_id < 1) {
            return TaskUseCaseSupport::fail('invalid_task_id', 'Identificador de tarea inválido.');
        }

        $default_bucket = TaskUseCaseSupport::normalize_default_bucket_strict($input['default_bucket'] ?? null);

        if ($default_bucket === null) {
            return TaskUseCaseSupport::fail(
                'invalid_default_bucket',
                'Clasificación de tarea inválida; use primary o secondary.'
            );
        }

        $task = TaskUseCaseSupport::find_task($task_id);

        if ($task === null) {
            return TaskUseCaseSupport::fail('task_not_found', 'Tarea no encontrada.');
        }

        if (!(new AA_Task_Governance_Policy())->can_edit_task($task)) {
            return TaskUseCaseSupport::fail('task_not_editable', 'Esta tarea no se puede editar.');
        }

        $list_id = (int) ($task['list_id'] ?? 0);
        $list = TaskUseCaseSupport::find_active_list($list_id);

        if ($list === null) {
            return TaskUseCaseSupport::fail('list_not_found', 'Lista no encontrada o no activa.');
        }

        $row = TaskRepository::update($task_id, [
            'default_bucket' => $default_bucket,
        ]);

        if ($row === null) {
            return TaskUseCaseSupport::fail(
                'persistence_failed',
                'No se pudo actualizar la clasificación de la tarea.'
            );
        }

        return TaskUseCaseSupport::ok(['task' => $row]);
    }
}
