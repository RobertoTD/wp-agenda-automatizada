<?php
/**
 * Archive Task List Use Case — archiva sin borrar tareas.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-list-governance-policy.php';

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

        if (!(new AA_Task_List_Governance_Policy())->can_archive_list($list)) {
            return TaskUseCaseSupport::fail('list_not_archivable', 'Esta lista no se puede archivar.');
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
