<?php
/**
 * Create Task Use Case.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-list-governance-policy.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class CreateTaskUseCase {

    /** @var callable|null */
    private $post_create_sync;

    /**
     * @param callable|null $post_create_sync (array $task): void
     */
    public function __construct(?callable $post_create_sync = null) {
        $this->post_create_sync = $post_create_sync;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $list_id = TaskUseCaseSupport::normalize_list_id($input['list_id'] ?? 0);
        $list = TaskUseCaseSupport::find_active_list($list_id);

        if ($list === null) {
            return TaskUseCaseSupport::fail('list_not_found', 'Lista no encontrada o no activa.');
        }

        if (!(new AA_Task_List_Governance_Policy())->can_accept_user_created_task($list)) {
            return TaskUseCaseSupport::fail(
                'list_not_manual_destination',
                'Esta lista no admite tareas creadas manualmente.'
            );
        }

        $title = TaskUseCaseSupport::normalize_required_title($input['title'] ?? null);

        if ($title === null) {
            return TaskUseCaseSupport::fail('missing_title', 'El título de la tarea es obligatorio.');
        }

        if (TaskUseCaseSupport::task_notes_exceed_max_length($input['notes'] ?? null)) {
            return TaskUseCaseSupport::fail(
                'notes_too_long',
                'Las notas no pueden superar ' . TaskUseCaseSupport::TASK_NOTES_MAX_LENGTH . ' caracteres.'
            );
        }

        $payload = [
            'list_id' => $list_id,
            'title' => $title,
            'notes' => TaskUseCaseSupport::normalize_optional_string($input['notes'] ?? null),
            'source' => 'user',
            'importance' => TaskUseCaseSupport::normalize_importance($input['importance'] ?? 0),
            'due_at' => TaskUseCaseSupport::normalize_due_at($input['due_at'] ?? null),
            'execution_available_at' => TaskUseCaseSupport::normalize_execution_available_at(
                $input['execution_available_at'] ?? null
            ),
            'position' => TaskUseCaseSupport::normalize_position($input['position'] ?? 0),
            'status' => 'pending',
        ];

        if (array_key_exists('default_bucket', $input)) {
            $payload['default_bucket'] = TaskUseCaseSupport::normalize_default_bucket_optional(
                $input['default_bucket']
            );
        }

        $row = TaskRepository::create($payload);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo crear la tarea.');
        }

        $this->run_post_create_sync($row);

        return TaskUseCaseSupport::ok(['task' => $row]);
    }

    /**
     * @param array<string,mixed> $task
     */
    private function run_post_create_sync(array $task): void {
        $execution_at = isset($task['execution_available_at'])
            ? trim((string) $task['execution_available_at'])
            : '';

        if ($execution_at === '') {
            return;
        }

        if ($this->post_create_sync !== null) {
            ($this->post_create_sync)($task);
            return;
        }

        require_once __DIR__ . '/SyncTaskExecutionAvailablePushJobUseCase.php';
        SyncTaskExecutionAvailablePushJobUseCase::sync_after_task_persisted_best_effort($task);
    }
}
