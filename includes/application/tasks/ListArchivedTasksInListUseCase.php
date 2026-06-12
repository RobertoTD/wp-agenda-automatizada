<?php
/**
 * List Archived Tasks In List Use Case — tareas archivadas de una lista user activa.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-list-governance-policy.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class ListArchivedTasksInListUseCase {

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

        if (!(new AA_Task_List_Governance_Policy())->can_edit_list($list)) {
            return TaskUseCaseSupport::fail('list_not_accessible', 'No se pueden listar tareas archivadas de esta lista.');
        }

        $tasks = TaskRepository::list_archived_by_list_id($list_id);

        return TaskUseCaseSupport::ok(['tasks' => $tasks]);
    }
}
