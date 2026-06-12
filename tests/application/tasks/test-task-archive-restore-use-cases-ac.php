<?php
/**
 * AC — ArchiveTaskUseCase, RestoreTaskUseCase, ListArchivedTasksInListUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-task-archive-restore-use-cases-ac.php
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

$archive_src = file_get_contents($plugin_root . '/includes/application/tasks/ArchiveTaskUseCase.php');
$restore_src = file_get_contents($plugin_root . '/includes/application/tasks/RestoreTaskUseCase.php');
$list_src = file_get_contents($plugin_root . '/includes/application/tasks/ListArchivedTasksInListUseCase.php');

ac_assert('ArchiveTaskUseCase file readable', $archive_src !== false);
ac_assert('RestoreTaskUseCase file readable', $restore_src !== false);
ac_assert('ListArchivedTasksInListUseCase file readable', $list_src !== false);
ac_assert('ArchiveTaskUseCase uses governance', strpos($archive_src, 'AA_Task_Governance_Policy') !== false);
ac_assert('ArchiveTaskUseCase rejects task_not_archivable', strpos($archive_src, 'task_not_archivable') !== false);
ac_assert('ArchiveTaskUseCase calls TaskRepository::archive', strpos($archive_src, 'TaskRepository::archive') !== false);
ac_assert('RestoreTaskUseCase uses governance', strpos($restore_src, 'AA_Task_Governance_Policy') !== false);
ac_assert('RestoreTaskUseCase rejects task_not_restorable', strpos($restore_src, 'task_not_restorable') !== false);
ac_assert('ListArchivedTasksInListUseCase uses list governance', strpos($list_src, 'AA_Task_List_Governance_Policy') !== false);
ac_assert(
    'ListArchivedTasksInListUseCase uses can_restore_archived_tasks',
    strpos($list_src, 'can_restore_archived_tasks') !== false
);
ac_assert('ListArchivedTasksInListUseCase rejects list_not_accessible', strpos($list_src, 'list_not_accessible') !== false);
ac_assert(
    'ListArchivedTasksInListUseCase uses list_archived_by_list_id',
    strpos($list_src, 'list_archived_by_list_id') !== false
);

require_once $plugin_root . '/includes/application/tasks/ArchiveTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/RestoreTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ListArchivedTasksInListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskStateRepository.php';

    AA_Schema::install();

    global $wpdb;
    $suffix = (string) time();

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Archive UC list ' . $suffix,
    ]);
    ac_assert('Create list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);

    $created_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Archive UC task ' . $suffix,
        'default_bucket' => 'secondary',
    ]);
    ac_assert('Create task success', !empty($created_task['success']));
    $task_id = (int) ($created_task['data']['task']['id'] ?? 0);

    $dismiss_before = TaskStateRepository::record_dismiss(
        $task_id,
        '2026-06-10 10:00:00',
        '2026-06-20 10:00:00'
    );
    ac_assert('Seed dismiss state', is_array($dismiss_before));

    $archive_ts = '2026-06-11 14:00:00';
    $archived = (new ArchiveTaskUseCase())->execute([
        'task_id' => $task_id,
        'archived_at' => $archive_ts,
    ]);
    ac_assert('Archive user task success', !empty($archived['success']));
    ac_assert(
        'Archive sets archived_at',
        ($archived['data']['task']['archived_at'] ?? '') === $archive_ts
    );
    ac_assert(
        'Archive keeps status pending',
        ($archived['data']['task']['status'] ?? '') === 'pending'
    );
    ac_assert(
        'Archive keeps default_bucket',
        ($archived['data']['task']['default_bucket'] ?? '') === 'secondary'
    );
    ac_assert(
        'Archive keeps completed_at null',
        ($archived['data']['task']['completed_at'] ?? null) === null
    );
    $dismiss_after_archive = TaskStateRepository::find_by_task_id($task_id);
    ac_assert(
        'Archive does not change dismiss_until',
        ($dismiss_after_archive['dismiss_until'] ?? '') === ($dismiss_before['dismiss_until'] ?? '')
    );

    $blocked_archive = (new ArchiveTaskUseCase())->execute(['task_id' => $task_id]);
    ac_assert(
        'Archive rejects already archived task',
        empty($blocked_archive['success']) && ($blocked_archive['error']['code'] ?? '') === 'task_not_archivable'
    );

    $active_sibling = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Active sibling ' . $suffix,
    ]);
    $active_sibling_id = (int) ($active_sibling['data']['task']['id'] ?? 0);

    $listed = (new ListArchivedTasksInListUseCase())->execute(['list_id' => $list_id]);
    ac_assert('List archived in list success', !empty($listed['success']));
    $listed_ids = array_map(static function ($row) {
        return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    }, $listed['data']['tasks'] ?? []);
    ac_assert('List includes archived task', in_array($task_id, $listed_ids, true));
    ac_assert('List excludes active task', !in_array($active_sibling_id, $listed_ids, true));

    $other_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Other list archive UC ' . $suffix,
    ]);
    $other_list_id = (int) ($other_list['data']['list']['id'] ?? 0);
    $other_task = (new CreateTaskUseCase())->execute([
        'list_id' => $other_list_id,
        'title' => 'Other archived ' . $suffix,
    ]);
    $other_task_id = (int) ($other_task['data']['task']['id'] ?? 0);
    (new ArchiveTaskUseCase())->execute(['task_id' => $other_task_id, 'archived_at' => '2026-06-12 08:00:00']);
    ac_assert(
        'List excludes archived tasks from other list',
        !in_array($other_task_id, $listed_ids, true)
    );

    $restored = (new RestoreTaskUseCase())->execute(['task_id' => $task_id]);
    ac_assert('Restore user task success', !empty($restored['success']));
    ac_assert(
        'Restore clears archived_at',
        ($restored['data']['task']['archived_at'] ?? 'x') === null
    );
    ac_assert(
        'Restore keeps status',
        ($restored['data']['task']['status'] ?? '') === 'pending'
    );
    ac_assert(
        'Restore keeps default_bucket',
        ($restored['data']['task']['default_bucket'] ?? '') === 'secondary'
    );
    $dismiss_after_restore = TaskStateRepository::find_by_task_id($task_id);
    ac_assert(
        'Restore does not change dismiss_until',
        ($dismiss_after_restore['dismiss_until'] ?? '') === ($dismiss_before['dismiss_until'] ?? '')
    );

    $restore_active = (new RestoreTaskUseCase())->execute(['task_id' => $task_id]);
    ac_assert(
        'Restore idempotent on active task',
        empty($restore_active['success']) && ($restore_active['error']['code'] ?? '') === 'task_not_restorable'
    );

    $done_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Done archive UC ' . $suffix,
    ]);
    $done_task_id = (int) ($done_task['data']['task']['id'] ?? 0);
    (new ChangeTaskStatusUseCase())->execute([
        'task_id' => $done_task_id,
        'status' => 'done',
    ]);
    $done_row = TaskRepository::find_by_id($done_task_id);
    $completed_at_before = $done_row['completed_at'] ?? null;
    $archived_done = (new ArchiveTaskUseCase())->execute([
        'task_id' => $done_task_id,
        'archived_at' => '2026-06-13 10:00:00',
    ]);
    ac_assert('Archive done task success', !empty($archived_done['success']));
    ac_assert(
        'Archive done keeps status done',
        ($archived_done['data']['task']['status'] ?? '') === 'done'
    );
    $restored_done = (new RestoreTaskUseCase())->execute(['task_id' => $done_task_id]);
    ac_assert(
        'Restore done archived task',
        !empty($restored_done['success'])
    );
    ac_assert(
        'Restore done keeps status done',
        ($restored_done['data']['task']['status'] ?? '') === 'done'
    );
    ac_assert(
        'Restore done keeps completed_at',
        ($restored_done['data']['task']['completed_at'] ?? null) === $completed_at_before
    );

    $missing = (new ArchiveTaskUseCase())->execute(['task_id' => 999999999]);
    ac_assert(
        'Archive rejects missing task',
        empty($missing['success']) && ($missing['error']['code'] ?? '') === 'task_not_found'
    );

    $seeded_list = SeededTaskRepository::upsert_seeded_list([
        'title' => 'Agenda archive UC ' . $suffix,
        'owner_type' => 'developer',
        'source_category' => 'agenda_app',
        'origin_key' => 'archive.uc.' . $suffix,
        'managed_by' => 'developer',
        'status' => 'active',
        'importance' => 0,
        'position' => 0,
    ]);
    $seeded_list_id = (int) ($seeded_list['id'] ?? 0);
    $seeded_task = SeededTaskRepository::upsert_seeded_task([
        'list_id' => $seeded_list_id,
        'title' => 'Agenda task archive UC ' . $suffix,
        'source_category' => 'agenda_app',
        'managed_by' => 'developer',
        'origin_key' => 'archive.task.' . $suffix,
        'status' => 'pending',
    ]);
    $seeded_task_id = (int) ($seeded_task['id'] ?? 0);

    $blocked_agenda_archive = (new ArchiveTaskUseCase())->execute(['task_id' => $seeded_task_id]);
    ac_assert(
        'Archive rejects agenda_app task',
        empty($blocked_agenda_archive['success']) && ($blocked_agenda_archive['error']['code'] ?? '') === 'task_not_archivable'
    );

    TaskRepository::archive($seeded_task_id, '2026-06-12 09:00:00');
    $blocked_agenda_restore = (new RestoreTaskUseCase())->execute(['task_id' => $seeded_task_id]);
    ac_assert(
        'Restore rejects agenda_app task',
        empty($blocked_agenda_restore['success']) && ($blocked_agenda_restore['error']['code'] ?? '') === 'task_not_restorable'
    );

    $blocked_list = (new ListArchivedTasksInListUseCase())->execute(['list_id' => $seeded_list_id]);
    ac_assert(
        'List archived rejects agenda_app list',
        empty($blocked_list['success']) && ($blocked_list['error']['code'] ?? '') === 'list_not_accessible'
    );

    $missing_list = (new ListArchivedTasksInListUseCase())->execute(['list_id' => 999999999]);
    ac_assert(
        'List archived rejects missing list',
        empty($missing_list['success']) && ($missing_list['error']['code'] ?? '') === 'list_not_found'
    );

    require_once $plugin_root . '/includes/application/tasks/GetTaskBoardUseCase.php';
    require_once $plugin_root . '/includes/application/executable/TaskBoardToExecutableMapper.php';

    $board_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Board archive UC ' . $suffix,
    ]);
    $board_task_id = (int) ($board_task['data']['task']['id'] ?? 0);
    (new ArchiveTaskUseCase())->execute([
        'task_id' => $board_task_id,
        'archived_at' => '2026-06-14 10:00:00',
    ]);

    $board_archived = (new GetTaskBoardUseCase())->execute();
    $bucket_ids = $board_archived['organization']['task_bucket_order_by_list'][$list_id]['primary'] ?? [];
    ac_assert(
        'GetTaskBoard excludes archived task from primary bucket',
        !in_array($board_task_id, $bucket_ids, true)
    );
    ac_assert(
        'GetTaskBoard excludes archived task from executive_candidates',
        !in_array($board_task_id, $board_archived['organization']['executive_candidates'] ?? [], true)
    );
    $feed_archived = TaskBoardToExecutableMapper::map($board_archived);
    $feed_item_ids = [];
    foreach ($feed_archived as $feed_list) {
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
        'Feed mapper excludes archived task from visible buckets',
        !in_array($board_task_id, $feed_item_ids, true)
    );

    (new RestoreTaskUseCase())->execute(['task_id' => $board_task_id]);
    $board_restored = (new GetTaskBoardUseCase())->execute();
    $restored_bucket_ids = $board_restored['organization']['task_bucket_order_by_list'][$list_id]['primary'] ?? [];
    ac_assert(
        'GetTaskBoard includes restored task in primary bucket',
        in_array($board_task_id, $restored_bucket_ids, true)
    );

    $wpdb->delete($wpdb->prefix . 'aa_task_state', ['task_id' => $task_id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'aa_tasks', ['list_id' => $list_id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'aa_tasks', ['list_id' => $other_list_id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'aa_tasks', ['list_id' => $seeded_list_id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'aa_task_lists', ['id' => $list_id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'aa_task_lists', ['id' => $other_list_id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'aa_task_lists', ['id' => $seeded_list_id], ['%d']);
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
