<?php
/**
 * Restore Task List Use Case — restaura lista archivada sin tocar tareas.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class RestoreTaskListUseCase {

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

        if (($list['status'] ?? '') === 'active') {
            $tasks = TaskRepository::list_by_list_id($list_id);

            return TaskUseCaseSupport::ok([
                'list' => $list,
                'tasks_preserved' => count($tasks),
            ]);
        }

        $row = TaskListRepository::restore($list_id);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo restaurar la lista.');
        }

        $tasks = TaskRepository::list_by_list_id($list_id);

        return TaskUseCaseSupport::ok([
            'list' => $row,
            'tasks_preserved' => count($tasks),
        ]);
    }
}
