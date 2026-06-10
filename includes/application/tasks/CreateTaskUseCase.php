<?php
/**
 * Create Task Use Case.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class CreateTaskUseCase {

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

        $title = TaskUseCaseSupport::normalize_required_title($input['title'] ?? null);

        if ($title === null) {
            return TaskUseCaseSupport::fail('missing_title', 'El título de la tarea es obligatorio.');
        }

        $payload = [
            'list_id' => $list_id,
            'title' => $title,
            'notes' => TaskUseCaseSupport::normalize_optional_string($input['notes'] ?? null),
            'source' => 'user',
            'importance' => TaskUseCaseSupport::normalize_importance($input['importance'] ?? 0),
            'due_at' => TaskUseCaseSupport::normalize_due_at($input['due_at'] ?? null),
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

        return TaskUseCaseSupport::ok(['task' => $row]);
    }
}
