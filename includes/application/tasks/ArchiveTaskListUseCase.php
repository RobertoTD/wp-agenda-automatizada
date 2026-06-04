<?php
/**
 * Archive Task List Use Case — archiva sin borrar tareas.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class ArchiveTaskListUseCase {

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

        if (($list['status'] ?? '') === 'archived') {
            return TaskUseCaseSupport::ok(['list' => $list]);
        }

        $row = TaskListRepository::archive($list_id);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo archivar la lista.');
        }

        $tasks = TaskRepository::list_by_list_id($list_id);

        return TaskUseCaseSupport::ok([
            'list' => $row,
            'tasks_preserved' => count($tasks),
        ]);
    }
}
