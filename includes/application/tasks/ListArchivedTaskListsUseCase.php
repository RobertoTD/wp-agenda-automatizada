<?php
/**
 * List Archived Task Lists Use Case — listas user archivadas para restaurar.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class ListArchivedTaskListsUseCase {

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input = []): array {
        $lists = TaskListRepository::list_archived_recent_first();

        return TaskUseCaseSupport::ok(['lists' => $lists]);
    }
}
