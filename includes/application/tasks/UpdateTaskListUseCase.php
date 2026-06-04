<?php
/**
 * Update Task List Use Case.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class UpdateTaskListUseCase {

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $list_id = TaskUseCaseSupport::normalize_list_id($input['list_id'] ?? $input['id'] ?? 0);
        $list = TaskUseCaseSupport::find_list($list_id);

        if ($list === null) {
            return TaskUseCaseSupport::fail('list_not_found', 'Lista no encontrada.');
        }

        $payload = [];

        if (array_key_exists('title', $input)) {
            $title = TaskUseCaseSupport::normalize_required_title($input['title']);

            if ($title === null) {
                return TaskUseCaseSupport::fail('missing_title', 'El título de la lista es obligatorio.');
            }

            $payload['title'] = $title;
        }

        if (array_key_exists('description', $input)) {
            $payload['description'] = TaskUseCaseSupport::normalize_optional_string($input['description']);
        }

        if (array_key_exists('importance', $input)) {
            $payload['importance'] = TaskUseCaseSupport::normalize_importance($input['importance']);
        }

        if (array_key_exists('position', $input)) {
            $payload['position'] = TaskUseCaseSupport::normalize_position($input['position']);
        }

        if ($payload === []) {
            return TaskUseCaseSupport::ok(['list' => $list]);
        }

        $row = TaskListRepository::update($list_id, $payload);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo actualizar la lista.');
        }

        return TaskUseCaseSupport::ok(['list' => $row]);
    }
}
