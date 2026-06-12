<?php
/**
 * AC — DeleteTaskUseCase (hard delete tarea user + dependencias).
 *
 * Ejecutar: php tests/application/tasks/test-delete-task-use-case-ac.php
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

$delete_src = file_get_contents($plugin_root . '/includes/application/tasks/DeleteTaskUseCase.php');
$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/TasksAjax.php');

ac_assert('DeleteTaskUseCase file readable', $delete_src !== false);
ac_assert('DeleteTaskUseCase uses governance', strpos($delete_src, 'AA_Task_Governance_Policy') !== false);
ac_assert('DeleteTaskUseCase rejects task_not_found', strpos($delete_src, 'task_not_found') !== false);
ac_assert('DeleteTaskUseCase rejects task_not_deletable', strpos($delete_src, 'task_not_deletable') !== false);
ac_assert('DeleteTaskUseCase rejects persistence_failed', strpos($delete_src, 'persistence_failed') !== false);
ac_assert('DeleteTaskUseCase deletes actions first', strpos($delete_src, 'TaskActionRepository::delete_by_task_id') !== false);
ac_assert('DeleteTaskUseCase deletes state second', strpos($delete_src, 'TaskStateRepository::delete_by_task_id') !== false);
ac_assert('DeleteTaskUseCase deletes task last', strpos($delete_src, 'TaskRepository::delete') !== false);
ac_assert('DeleteTaskUseCase does not touch archived_at', strpos($delete_src, 'archived_at') === false);
ac_assert('DeleteTaskUseCase does not touch status field updates', strpos($delete_src, 'update_status') === false);

ac_assert('AJAX registers aa_delete_task', strpos($ajax_src, 'aa_delete_task') !== false);
ac_assert('AJAX delete task uses DeleteTaskUseCase', strpos($ajax_src, 'DeleteTaskUseCase') !== false);
ac_assert(
    'AJAX delete task passes task_id to DeleteTaskUseCase',
    strpos($ajax_src, 'handle_delete_task') !== false
    && strpos($ajax_src, "'task_id' => self::post_scalar('task_id')") !== false
);
ac_assert(
    'AJAX delete task responds via respond_use_case',
    strpos($ajax_src, 'handle_delete_task') !== false
    && strpos($ajax_src, 'respond_use_case($result)') !== false
);

require_once $plugin_root . '/includes/application/tasks/DeleteTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ArchiveTaskUseCase.php';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
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

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Delete UC list ' . $suffix,
    ]);
    ac_assert('Create list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);

    $created_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Delete UC task ' . $suffix,
    ]);
    ac_assert('Create task success', !empty($created_task['success']));
    $task_id = (int) ($created_task['data']['task']['id'] ?? 0);

    TaskStateRepository::record_dismiss($task_id, '2026-06-10 10:00:00', '2026-06-20 10:00:00');
    TaskActionRepository::upsert($task_id, [
        'action_key' => 'delete.test.' . $suffix,
        'type' => 'navigate',
        'label' => 'Ir',
        'url' => 'https://example.test',
    ]);

    $deleted = (new DeleteTaskUseCase())->execute(['task_id' => $task_id]);
    ac_assert('Delete user task success', !empty($deleted['success']));
    ac_assert(
        'Delete response includes task_id and deleted flag',
        (int) ($deleted['data']['task_id'] ?? 0) === $task_id
        && !empty($deleted['data']['deleted'])
    );
    ac_assert('Task row removed', TaskRepository::find_by_id($task_id) === null);
    ac_assert(
        'Task state removed',
        (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$state_table} WHERE task_id = %d", $task_id)) === 0
    );
    ac_assert(
        'Task actions removed',
        (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$actions_table} WHERE task_id = %d", $task_id)) === 0
    );
    ac_assert(
        'List row preserved',
        (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$lists_table} WHERE id = %d", $list_id)) === 1
    );

    $bare_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Delete bare task ' . $suffix,
    ]);
    $bare_task_id = (int) ($bare_task['data']['task']['id'] ?? 0);
    $deleted_bare = (new DeleteTaskUseCase())->execute(['task_id' => $bare_task_id]);
    ac_assert('Delete task without state/actions succeeds', !empty($deleted_bare['success']));

    $missing = (new DeleteTaskUseCase())->execute(['task_id' => 999999999]);
    ac_assert(
        'Delete missing task returns task_not_found',
        empty($missing['success']) && ($missing['error']['code'] ?? '') === 'task_not_found'
    );

    $seeded_list = SeededTaskRepository::upsert_list([
        'title' => 'Delete seeded list ' . $suffix,
        'source_category' => 'agenda_app',
        'origin_key' => 'delete.list.' . $suffix,
        'managed_by' => 'developer',
    ]);
    $seeded_list_id = (int) ($seeded_list['id'] ?? 0);
    $seeded_task = SeededTaskRepository::upsert_task([
        'list_id' => $seeded_list_id,
        'title' => 'Delete seeded task ' . $suffix,
        'source_category' => 'agenda_app',
        'managed_by' => 'developer',
        'origin_key' => 'delete.task.' . $suffix,
        'status' => 'pending',
    ]);
    $seeded_task_id = (int) ($seeded_task['id'] ?? 0);
    $blocked_agenda = (new DeleteTaskUseCase())->execute(['task_id' => $seeded_task_id]);
    ac_assert(
        'Delete rejects agenda_app task',
        empty($blocked_agenda['success']) && ($blocked_agenda['error']['code'] ?? '') === 'task_not_deletable'
    );
    ac_assert('Agenda task still exists after blocked delete', TaskRepository::find_by_id($seeded_task_id) !== null);

    $done_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Delete done task ' . $suffix,
    ]);
    $done_task_id = (int) ($done_task['data']['task']['id'] ?? 0);
    (new ChangeTaskStatusUseCase())->execute([
        'task_id' => $done_task_id,
        'status' => 'done',
    ]);
    $deleted_done = (new DeleteTaskUseCase())->execute(['task_id' => $done_task_id]);
    ac_assert('Delete done user task success', !empty($deleted_done['success']));

    $archived_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Delete archived task ' . $suffix,
    ]);
    $archived_task_id = (int) ($archived_task['data']['task']['id'] ?? 0);
    (new ArchiveTaskUseCase())->execute([
        'task_id' => $archived_task_id,
        'archived_at' => '2026-06-12 09:00:00',
    ]);
    $deleted_archived = (new DeleteTaskUseCase())->execute(['task_id' => $archived_task_id]);
    ac_assert('Delete archived user task success', !empty($deleted_archived['success']));

    require_once $plugin_root . '/includes/application/tasks/GetTaskBoardUseCase.php';
    require_once $plugin_root . '/includes/application/executable/TaskBoardToExecutableMapper.php';

    $board_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Board delete UC ' . $suffix,
    ]);
    $board_task_id = (int) ($board_task['data']['task']['id'] ?? 0);
    (new DeleteTaskUseCase())->execute(['task_id' => $board_task_id]);

    $board = (new GetTaskBoardUseCase())->execute();
    $bucket_ids = $board['organization']['task_bucket_order_by_list'][$list_id]['primary'] ?? [];
    ac_assert(
        'GetTaskBoard excludes deleted task from primary bucket',
        !in_array($board_task_id, $bucket_ids, true)
    );
    ac_assert(
        'GetTaskBoard excludes deleted task from executive_candidates',
        !in_array($board_task_id, $board['organization']['executive_candidates'] ?? [], true)
    );
    $feed = TaskBoardToExecutableMapper::map($board);
    $feed_item_ids = [];
    foreach ($feed as $feed_list) {
        if ((int) ($feed_list['id'] ?? 0) !== $list_id) {
            continue;
        }
        foreach ($feed_list['buckets'] ?? [] as $bucket) {
            foreach ($bucket['items'] ?? [] as $item) {
                if (is_array($item)) {
                    $feed_item_ids[] = (int) ($item['id'] ?? 0);
                }
            }
        }
    }
    ac_assert(
        'Feed mapper excludes deleted task from visible buckets',
        !in_array($board_task_id, $feed_item_ids, true)
    );

    $wpdb->delete($actions_table, ['task_id' => $seeded_task_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $seeded_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['list_id' => $seeded_list_id], ['%d']);
    $wpdb->delete($tasks_table, ['list_id' => $list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
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
