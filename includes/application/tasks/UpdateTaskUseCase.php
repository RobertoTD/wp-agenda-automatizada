<?php
/**
 * Update Task Use Case.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-governance-policy.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class UpdateTaskUseCase {

    /** @var callable|null */
    private $post_update_sync;

    /**
     * @param callable|null $post_update_sync (array $task): void
     */
    public function __construct(?callable $post_update_sync = null) {
        $this->post_update_sync = $post_update_sync;
    }

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

        if (!(new AA_Task_Governance_Policy())->can_edit_task($task)) {
            return TaskUseCaseSupport::fail('task_not_editable', 'Esta tarea no se puede editar.');
        }

        $payload = [];

        if (array_key_exists('title', $input)) {
            $title = TaskUseCaseSupport::normalize_required_title($input['title']);

            if ($title === null) {
                return TaskUseCaseSupport::fail('missing_title', 'El título de la tarea es obligatorio.');
            }

            $payload['title'] = $title;
        }

        if (array_key_exists('notes', $input)) {
            if (TaskUseCaseSupport::task_notes_exceed_max_length($input['notes'])) {
                return TaskUseCaseSupport::fail(
                    'notes_too_long',
                    'Las notas no pueden superar ' . TaskUseCaseSupport::TASK_NOTES_MAX_LENGTH . ' caracteres.'
                );
            }

            $payload['notes'] = TaskUseCaseSupport::normalize_optional_string($input['notes']);
        }

        if (array_key_exists('importance', $input)) {
            $payload['importance'] = TaskUseCaseSupport::normalize_importance($input['importance']);
        }

        if (array_key_exists('due_at', $input)) {
            $payload['due_at'] = TaskUseCaseSupport::normalize_due_at($input['due_at']);
        }

        if (array_key_exists('execution_available_at', $input)) {
            $payload['execution_available_at'] = TaskUseCaseSupport::normalize_execution_available_at(
                $input['execution_available_at']
            );
        }

        if (array_key_exists('position', $input)) {
            $payload['position'] = TaskUseCaseSupport::normalize_position($input['position']);
        }

        if (array_key_exists('default_bucket', $input)) {
            $payload['default_bucket'] = TaskUseCaseSupport::normalize_default_bucket_optional(
                $input['default_bucket']
            );
        }

        if ($payload === []) {
            return TaskUseCaseSupport::ok(['task' => $task]);
        }

        $row = TaskRepository::update($task_id, $payload);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo actualizar la tarea.');
        }

        if (array_key_exists('execution_available_at', $input)) {
            $this->run_post_update_sync($row);
        }

        return TaskUseCaseSupport::ok(['task' => $row]);
    }

    /**
     * @param array<string,mixed> $task
     */
    private function run_post_update_sync(array $task): void {
        if ($this->post_update_sync !== null) {
            ($this->post_update_sync)($task);
            return;
        }

        require_once __DIR__ . '/SyncTaskExecutionAvailablePushJobUseCase.php';
        SyncTaskExecutionAvailablePushJobUseCase::sync_after_task_persisted_best_effort($task);
    }
}
