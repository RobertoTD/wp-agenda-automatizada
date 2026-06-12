<?php
/**
 * AC — DeleteTaskListUseCase (hard delete lista user + cascada de tareas).
 *
 * Ejecutar: php tests/application/tasks/test-delete-task-list-use-case-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    require_once $wp_load;
} elseif (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return is_string($str) ? trim($str) : '';
    }
}

$delete_src = file_get_contents($plugin_root . '/includes/application/tasks/DeleteTaskListUseCase.php');
$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/TasksAjax.php');

ac_assert('DeleteTaskListUseCase file readable', $delete_src !== false);
ac_assert('DeleteTaskListUseCase uses list governance', strpos($delete_src, 'AA_Task_List_Governance_Policy') !== false);
ac_assert('DeleteTaskListUseCase uses task governance', strpos($delete_src, 'AA_Task_Governance_Policy') !== false);
ac_assert('DeleteTaskListUseCase rejects list_not_found', strpos($delete_src, 'list_not_found') !== false);
ac_assert('DeleteTaskListUseCase rejects list_not_deletable', strpos($delete_src, 'list_not_deletable') !== false);
ac_assert('DeleteTaskListUseCase rejects list_has_protected_tasks', strpos($delete_src, 'list_has_protected_tasks') !== false);
ac_assert('DeleteTaskListUseCase rejects persistence_failed', strpos($delete_src, 'persistence_failed') !== false);
ac_assert('DeleteTaskListUseCase deletes actions batch first', strpos($delete_src, 'TaskActionRepository::delete_by_task_ids') !== false);
ac_assert('DeleteTaskListUseCase deletes state batch second', strpos($delete_src, 'TaskStateRepository::delete_by_task_ids') !== false);
ac_assert('DeleteTaskListUseCase deletes tasks by list third', strpos($delete_src, 'TaskRepository::delete_by_list_id') !== false);
ac_assert('DeleteTaskListUseCase deletes list last', strpos($delete_src, 'TaskListRepository::delete') !== false);
ac_assert('DeleteTaskListUseCase does not touch archived_at', strpos($delete_src, 'archived_at') === false);

ac_assert('AJAX registers aa_delete_task_list', strpos($ajax_src, 'aa_delete_task_list') !== false);
ac_assert('AJAX delete list uses DeleteTaskListUseCase', strpos($ajax_src, 'DeleteTaskListUseCase') !== false);
ac_assert(
    'AJAX delete list passes list_id to DeleteTaskListUseCase',
    strpos($ajax_src, 'handle_delete_task_list') !== false
    && strpos($ajax_src, "'list_id' => self::post_scalar('list_id')") !== false
);
ac_assert(
    'AJAX delete list responds via respond_use_case',
    strpos($ajax_src, 'handle_delete_task_list') !== false
    && strpos($ajax_src, 'respond_use_case($result)') !== false
);

require_once $plugin_root . '/includes/application/tasks/DeleteTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ArchiveTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ArchiveTaskListUseCase.php';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskListRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskStateRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskActionRepository.php';

    AA_Schema::install();

    global $wpdb;
    $suffix = (string) time();
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $state_table = $wpdb->prefix . 'aa_task_state';
    $actions_table = $wpdb->prefix . 'aa_task_actions';
    $lists_table = $wpdb->prefix . 'aa_task_lists';

    $other_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Delete list UC other ' . $suffix,
    ]);
    $other_list_id = (int) ($other_list['data']['list']['id'] ?? 0);
    $other_task = (new CreateTaskUseCase())->execute([
        'list_id' => $other_list_id,
        'title' => 'Other list task ' . $suffix,
    ]);
    $other_task_id = (int) ($other_task['data']['task']['id'] ?? 0);

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Delete list UC ' . $suffix,
    ]);
    ac_assert('Create list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);

    $pending_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Delete list pending ' . $suffix,
    ]);
    $pending_task_id = (int) ($pending_task['data']['task']['id'] ?? 0);

    $done_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Delete list done ' . $suffix,
    ]);
    $done_task_id = (int) ($done_task['data']['task']['id'] ?? 0);
    (new ChangeTaskStatusUseCase())->execute([
        'task_id' => $done_task_id,
        'status' => 'done',
    ]);

    $archived_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Delete list archived ' . $suffix,
    ]);
    $archived_task_id = (int) ($archived_task['data']['task']['id'] ?? 0);
    (new ArchiveTaskUseCase())->execute([
        'task_id' => $archived_task_id,
        'archived_at' => '2026-06-12 09:00:00',
    ]);

    TaskStateRepository::record_dismiss($pending_task_id, '2026-06-10 10:00:00', '2026-06-20 10:00:00');
    TaskActionRepository::upsert($pending_task_id, [
        'action_key' => 'delete.list.test.' . $suffix,
        'type' => 'navigate',
        'label' => 'Ir',
        'url' => 'https://example.test',
    ]);

    $deleted = (new DeleteTaskListUseCase())->execute(['list_id' => $list_id]);
    ac_assert('Delete user list success', !empty($deleted['success']));
    ac_assert(
        'Delete response includes list_id deleted and tasks_deleted',
        (int) ($deleted['data']['list_id'] ?? 0) === $list_id
        && !empty($deleted['data']['deleted'])
        && (int) ($deleted['data']['tasks_deleted'] ?? -1) === 3
    );
    ac_assert('List row removed', TaskListRepository::find_by_id($list_id) === null);
    ac_assert(
        'All list tasks removed',
        (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tasks_table} WHERE list_id = %d", $list_id)) === 0
    );
    ac_assert(
        'Task state removed for list tasks',
        (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$state_table} WHERE task_id IN (%d,%d,%d)", $pending_task_id, $done_task_id, $archived_task_id)) === 0
    );
    ac_assert(
        'Task actions removed for list tasks',
        (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$actions_table} WHERE task_id IN (%d,%d,%d)", $pending_task_id, $done_task_id, $archived_task_id)) === 0
    );
    ac_assert(
        'Other list preserved',
        TaskListRepository::find_by_id($other_list_id) !== null
    );
    ac_assert(
        'Other list task preserved',
        TaskRepository::find_by_id($other_task_id) !== null
    );

    $empty_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Delete empty list ' . $suffix,
    ]);
    $empty_list_id = (int) ($empty_list['data']['list']['id'] ?? 0);
    $deleted_empty = (new DeleteTaskListUseCase())->execute(['list_id' => $empty_list_id]);
    ac_assert('Delete empty user list success', !empty($deleted_empty['success']));
    ac_assert(
        'Delete empty list reports zero tasks_deleted',
        (int) ($deleted_empty['data']['tasks_deleted'] ?? -1) === 0
    );

    $missing = (new DeleteTaskListUseCase())->execute(['list_id' => 999999999]);
    ac_assert(
        'Delete missing list returns list_not_found',
        empty($missing['success']) && ($missing['error']['code'] ?? '') === 'list_not_found'
    );

    $seeded_list = SeededTaskRepository::upsert_list([
        'title' => 'Delete seeded list ' . $suffix,
        'source_category' => 'agenda_app',
        'origin_key' => 'delete.list.' . $suffix,
        'managed_by' => 'developer',
    ]);
    $seeded_list_id = (int) ($seeded_list['id'] ?? 0);
    $blocked = (new DeleteTaskListUseCase())->execute(['list_id' => $seeded_list_id]);
    ac_assert(
        'Delete rejects agenda_app list',
        empty($blocked['success']) && ($blocked['error']['code'] ?? '') === 'list_not_deletable'
    );
    ac_assert('Agenda list still exists after blocked delete', TaskListRepository::find_by_id($seeded_list_id) !== null);

    $protected_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Protected task list ' . $suffix,
    ]);
    $protected_list_id = (int) ($protected_list['data']['list']['id'] ?? 0);
    $now = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
    $wpdb->insert(
        $tasks_table,
        [
            'list_id' => $protected_list_id,
            'title' => 'Protected developer task ' . $suffix,
            'status' => 'pending',
            'source' => 'system',
            'source_category' => 'agenda_app',
            'managed_by' => 'developer',
            'default_bucket' => 'primary',
            'completion_type' => 'manual',
            'importance' => 0,
            'position' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
    );
    $protected_task_id = (int) $wpdb->insert_id;
    $blocked_protected = (new DeleteTaskListUseCase())->execute(['list_id' => $protected_list_id]);
    ac_assert(
        'Delete aborts list_has_protected_tasks',
        empty($blocked_protected['success']) && ($blocked_protected['error']['code'] ?? '') === 'list_has_protected_tasks'
    );
    ac_assert(
        'Protected list still exists after abort',
        TaskListRepository::find_by_id($protected_list_id) !== null
    );
    ac_assert(
        'Protected task still exists after abort',
        TaskRepository::find_by_id($protected_task_id) !== null
    );

    $archived_user_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Delete archived user list ' . $suffix,
    ]);
    $archived_user_list_id = (int) ($archived_user_list['data']['list']['id'] ?? 0);
    (new ArchiveTaskListUseCase())->execute(['list_id' => $archived_user_list_id]);
    $deleted_archived_list = (new DeleteTaskListUseCase())->execute(['list_id' => $archived_user_list_id]);
    ac_assert('Delete archived user list success', !empty($deleted_archived_list['success']));

    require_once $plugin_root . '/includes/application/tasks/GetTaskBoardUseCase.php';
    require_once $plugin_root . '/includes/application/executable/TaskBoardToExecutableMapper.php';

    $board_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Board delete list ' . $suffix,
    ]);
    $board_list_id = (int) ($board_list['data']['list']['id'] ?? 0);
    $board_task = (new CreateTaskUseCase())->execute([
        'list_id' => $board_list_id,
        'title' => 'Board delete task ' . $suffix,
    ]);
    $board_task_id = (int) ($board_task['data']['task']['id'] ?? 0);
    (new DeleteTaskListUseCase())->execute(['list_id' => $board_list_id]);

    $board = (new GetTaskBoardUseCase())->execute();
    $board_list_ids = array_map(static function (array $list): int {
        return (int) ($list['id'] ?? 0);
    }, $board['lists'] ?? []);
    ac_assert(
        'GetTaskBoard excludes deleted list',
        !in_array($board_list_id, $board_list_ids, true)
    );
    ac_assert(
        'GetTaskBoard excludes deleted list tasks',
        !in_array($board_task_id, array_map(static function (array $task): int {
            return (int) ($task['id'] ?? 0);
        }, $board['tasks'] ?? []), true)
    );
    $feed = TaskBoardToExecutableMapper::map($board);
    $feed_list_ids = array_map(static function (array $list): int {
        return (int) ($list['id'] ?? 0);
    }, $feed);
    ac_assert(
        'Feed mapper excludes deleted list',
        !in_array($board_list_id, $feed_list_ids, true)
    );

    $wpdb->delete($actions_table, ['task_id' => $protected_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $protected_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['list_id' => $protected_list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $protected_list_id], ['%d']);
    $wpdb->delete($tasks_table, ['list_id' => $other_list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $other_list_id], ['%d']);
    $wpdb->delete($tasks_table, ['list_id' => $seeded_list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $seeded_list_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar use cases.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
