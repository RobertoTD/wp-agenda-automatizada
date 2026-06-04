<?php
/**
 * Create Task List Use Case.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class CreateTaskListUseCase {

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $title = TaskUseCaseSupport::normalize_required_title($input['title'] ?? null);

        if ($title === null) {
            return TaskUseCaseSupport::fail('missing_title', 'El título de la lista es obligatorio.');
        }

        $row = TaskListRepository::create([
            'title' => $title,
            'description' => TaskUseCaseSupport::normalize_optional_string($input['description'] ?? null),
            'owner_type' => 'user',
            'importance' => TaskUseCaseSupport::normalize_importance($input['importance'] ?? 0),
            'position' => TaskUseCaseSupport::normalize_position($input['position'] ?? 0),
            'status' => 'active',
        ]);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo crear la lista.');
        }

        return TaskUseCaseSupport::ok(['list' => $row]);
    }
}
