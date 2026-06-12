<?php
/**
 * Archive Task Use Case — archiva tarea user vía archived_at (sin tocar status).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-governance-policy.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class ArchiveTaskUseCase {

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

        if (!(new AA_Task_Governance_Policy())->can_archive_task($task)) {
            return TaskUseCaseSupport::fail('task_not_archivable', 'Esta tarea no se puede archivar.');
        }

        $archived_at = null;

        if (array_key_exists('archived_at', $input)) {
            $archived_at = TaskUseCaseSupport::normalize_optional_string($input['archived_at']);
        }

        $row = TaskRepository::archive($task_id, $archived_at);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo archivar la tarea.');
        }

        return TaskUseCaseSupport::ok(['task' => $row]);
    }
}
