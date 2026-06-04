<?php
/**
 * Update Task Use Case.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class UpdateTaskUseCase {

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

        $payload = [];

        if (array_key_exists('title', $input)) {
            $title = TaskUseCaseSupport::normalize_required_title($input['title']);

            if ($title === null) {
                return TaskUseCaseSupport::fail('missing_title', 'El título de la tarea es obligatorio.');
            }

            $payload['title'] = $title;
        }

        if (array_key_exists('notes', $input)) {
            $payload['notes'] = TaskUseCaseSupport::normalize_optional_string($input['notes']);
        }

        if (array_key_exists('importance', $input)) {
            $payload['importance'] = TaskUseCaseSupport::normalize_importance($input['importance']);
        }

        if (array_key_exists('due_at', $input)) {
            $payload['due_at'] = TaskUseCaseSupport::normalize_due_at($input['due_at']);
        }

        if (array_key_exists('position', $input)) {
            $payload['position'] = TaskUseCaseSupport::normalize_position($input['position']);
        }

        if ($payload === []) {
            return TaskUseCaseSupport::ok(['task' => $task]);
        }

        $row = TaskRepository::update($task_id, $payload);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo actualizar la tarea.');
        }

        return TaskUseCaseSupport::ok(['task' => $row]);
    }
}
