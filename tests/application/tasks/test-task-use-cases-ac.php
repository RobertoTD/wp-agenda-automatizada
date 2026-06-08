<?php
/**
 * AC MC3 — Use Cases Listas/Tareas + TasksAjax.
 *
 * Ejecutar: php tests/application/tasks/test-task-use-cases-ac.php
 *
 * Parte estática: no requiere WordPress.
 * Integración: AA_WP_ROOT=/ruta/wordpress
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
$wp_integration = $wp_load !== '' && is_readable($wp_load);

if ($wp_integration) {
    require_once $wp_load;
} elseif (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return is_string($str) ? trim($str) : '';
    }
}

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/application/tasks/GetTaskBoardUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/UpdateTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ArchiveTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ListArchivedTaskListsUseCase.php';
require_once $plugin_root . '/includes/application/tasks/RestoreTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/UpdateTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php';

// ─── Estáticos: AJAX wiring ──────────────────────────────────

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/TasksAjax.php');
ac_assert('TasksAjax file readable', $ajax_src !== false);
ac_assert('AJAX registers aa_get_task_board', strpos($ajax_src, 'aa_get_task_board') !== false);
ac_assert('AJAX registers aa_create_task_list', strpos($ajax_src, 'aa_create_task_list') !== false);
ac_assert('AJAX registers aa_update_task_list', strpos($ajax_src, 'aa_update_task_list') !== false);
ac_assert('AJAX registers aa_archive_task_list', strpos($ajax_src, 'aa_archive_task_list') !== false);
ac_assert('AJAX registers aa_list_archived_task_lists', strpos($ajax_src, 'aa_list_archived_task_lists') !== false);
ac_assert('AJAX registers aa_restore_task_list', strpos($ajax_src, 'aa_restore_task_list') !== false);
ac_assert('AJAX list archived uses ListArchivedTaskListsUseCase', strpos($ajax_src, 'ListArchivedTaskListsUseCase') !== false);
ac_assert('AJAX restore uses RestoreTaskListUseCase', strpos($ajax_src, 'RestoreTaskListUseCase') !== false);
ac_assert('AJAX registers aa_create_task', strpos($ajax_src, 'aa_create_task') !== false);
ac_assert('AJAX registers aa_update_task', strpos($ajax_src, 'aa_update_task') !== false);
ac_assert('AJAX registers aa_change_task_status', strpos($ajax_src, 'aa_change_task_status') !== false);
ac_assert('AJAX registers aa_defer_task', strpos($ajax_src, 'aa_defer_task') !== false);
ac_assert('AJAX registers aa_dismiss_task', strpos($ajax_src, 'aa_dismiss_task') !== false);
ac_assert('AJAX defer uses RecordTaskDeferSignalUseCase', strpos($ajax_src, 'RecordTaskDeferSignalUseCase') !== false);
ac_assert('AJAX dismiss uses RecordTaskDismissSignalUseCase', strpos($ajax_src, 'RecordTaskDismissSignalUseCase') !== false);

$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert('Plugin bootstrap registers TasksAjax', strpos($bootstrap_src, 'TasksAjax::register()') !== false);

$get_board_src = file_get_contents($plugin_root . '/includes/application/tasks/GetTaskBoardUseCase.php');
ac_assert('GetTaskBoardUseCase uses prioritization policy', strpos($get_board_src, 'AA_Task_Prioritization_Policy') !== false);
ac_assert('GetTaskBoardUseCase uses signal policy', strpos($get_board_src, 'AA_Task_Signal_Policy') !== false);
ac_assert('GetTaskBoardUseCase uses active view projection policy', strpos($get_board_src, 'AA_Task_Active_View_Projection_Policy') !== false);
ac_assert('GetTaskBoardUseCase loads task_state_by_id', strpos($get_board_src, 'task_state_by_id') !== false);
ac_assert('GetTaskBoardUseCase returns organization key', strpos($get_board_src, "'organization'") !== false);
ac_assert('GetTaskBoardUseCase returns task_evaluations_by_id', strpos($get_board_src, 'task_evaluations_by_id') !== false);

$change_status_src = file_get_contents($plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php');
ac_assert('ChangeTaskStatusUseCase uses mark_completed', strpos($change_status_src, 'mark_completed') !== false);

// ─── Integración WordPress ───────────────────────────────────

if ($wp_integration) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';

    AA_Schema::install();

    global $wpdb;
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $suffix = (string) time();

    $missing_list = (new CreateTaskListUseCase())->execute(['title' => '   ']);
    ac_assert('Reject list without title', empty($missing_list['success']) && ($missing_list['error']['code'] ?? '') === 'missing_title');

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Lista UC ' . $suffix,
        'description' => 'Contexto',
        'importance' => -4,
        'position' => 2,
    ]);
    ac_assert('Create list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);
    ac_assert('Create list returns id', $list_id > 0);

    $updated_list = (new UpdateTaskListUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Lista UC actualizada ' . $suffix,
        'importance' => 1,
    ]);
    ac_assert('Update list success', !empty($updated_list['success']));
    ac_assert('Update list title', ($updated_list['data']['list']['title'] ?? '') === 'Lista UC actualizada ' . $suffix);

    $missing_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => '',
    ]);
    ac_assert('Reject task without title', empty($missing_task['success']) && ($missing_task['error']['code'] ?? '') === 'missing_title');

    $invalid_list_task = (new CreateTaskUseCase())->execute([
        'list_id' => 99999999,
        'title' => 'Tarea imposible',
    ]);
    ac_assert('Reject task with missing list', empty($invalid_list_task['success']) && ($invalid_list_task['error']['code'] ?? '') === 'list_not_found');

    $created_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Tarea UC ' . $suffix,
        'importance' => 5,
        'due_at' => '2026-06-01 08:00:00',
    ]);
    ac_assert('Create task success', !empty($created_task['success']));
    $task_id = (int) ($created_task['data']['task']['id'] ?? 0);
    ac_assert('Create task returns id', $task_id > 0);

    $later_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Tarea lejana ' . $suffix,
        'due_at' => '2026-06-30 08:00:00',
    ]);
    $later_task_id = (int) ($later_task['data']['task']['id'] ?? 0);

    $high_importance_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Lista alta ' . $suffix,
        'importance' => -20,
    ]);
    $high_list_id = (int) ($high_importance_list['data']['list']['id'] ?? 0);

    $board = (new GetTaskBoardUseCase())->execute();
    ac_assert('GetTaskBoard returns lists', is_array($board['lists'] ?? null));
    ac_assert('GetTaskBoard returns tasks', is_array($board['tasks'] ?? null));
    ac_assert('GetTaskBoard returns organization', is_array($board['organization'] ?? null));
    ac_assert(
        'GetTaskBoard organization has expected keys',
        isset($board['organization']['list_order'], $board['organization']['task_order_by_list'], $board['organization']['executive_candidates'])
    );

    $list_order = $board['organization']['list_order'] ?? [];
    ac_assert('GetTaskBoard list_order includes high importance list first', ($list_order[0] ?? null) === $high_list_id);

    $task_order = $board['organization']['task_order_by_list'][$list_id] ?? [];
    ac_assert('GetTaskBoard overdue task before later task', ($task_order[0] ?? null) === $task_id);
    ac_assert(
        'GetTaskBoard executive_candidates prioritize overdue pending',
        ($board['organization']['executive_candidates'][0] ?? null) === $task_id
    );

    ac_assert('GetTaskBoard returns task_state_by_id', is_array($board['task_state_by_id'] ?? null));
    ac_assert(
        'GetTaskBoard returns task_evaluations_by_id',
        is_array($board['organization']['task_evaluations_by_id'] ?? null)
    );

    require_once $plugin_root . '/includes/application/tasks/RecordTaskDeferSignalUseCase.php';
    $defer_signal = (new RecordTaskDeferSignalUseCase())->execute(['task_id' => $later_task_id]);
    ac_assert('Record defer signal for board read path', !empty($defer_signal['success']));

    $board_with_signal = (new GetTaskBoardUseCase())->execute();
    ac_assert(
        'Board task_state_by_id includes deferred task',
        is_array($board_with_signal['task_state_by_id'][$later_task_id] ?? null)
    );
    $later_eval = $board_with_signal['organization']['task_evaluations_by_id'][$later_task_id] ?? null;
    ac_assert(
        'Board task_evaluations_by_id marks has_defer',
        is_array($later_eval) && ($later_eval['signals']['has_defer'] ?? false) === true
    );
    ac_assert(
        'Board moves deferred task to secondary bucket',
        in_array($later_task_id, $board_with_signal['organization']['task_bucket_order_by_list'][$list_id]['secondary'] ?? [], true)
        && !in_array($later_task_id, $board_with_signal['organization']['task_bucket_order_by_list'][$list_id]['primary'] ?? [], true)
    );
    ac_assert(
        'Board deferred task exposes dismiss capability only',
        is_array($later_eval)
        && ($later_eval['capabilities']['can_defer'] ?? true) === false
        && ($later_eval['capabilities']['can_dismiss'] ?? false) === true
    );

    $state_table = $wpdb->prefix . 'aa_task_state';
    $wpdb->delete($state_table, ['task_id' => $later_task_id], ['%d']);

    $status_done = (new ChangeTaskStatusUseCase())->execute([
        'task_id' => $task_id,
        'status' => 'done',
    ]);
    ac_assert('Complete task success', !empty($status_done['success']));
    ac_assert('Complete task status done', ($status_done['data']['task']['status'] ?? '') === 'done');
    ac_assert('Complete task sets completed_at', !empty($status_done['data']['task']['completed_at']));

    $board_after_done = (new GetTaskBoardUseCase())->execute();
    ac_assert(
        'Done task excluded from executive_candidates',
        !in_array($task_id, $board_after_done['organization']['executive_candidates'] ?? [], true)
    );

    $archive_list = (new ArchiveTaskListUseCase())->execute(['list_id' => $list_id]);
    ac_assert('Archive list success', !empty($archive_list['success']));
    ac_assert('Archive list status archived', ($archive_list['data']['list']['status'] ?? '') === 'archived');
    ac_assert('Archive preserves tasks', ($archive_list['data']['tasks_preserved'] ?? 0) >= 2);

    $tasks_after_archive = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$tasks_table} WHERE list_id = %d", $list_id)
    );
    ac_assert('Archive does not delete tasks', $tasks_after_archive >= 2);

    $board_after_archive = (new GetTaskBoardUseCase())->execute();
    ac_assert(
        'Archived list excluded from active board',
        !in_array($list_id, $board_after_archive['organization']['list_order'] ?? [], true)
    );

    $list_archived = (new ListArchivedTaskListsUseCase())->execute();
    ac_assert('List archived success', !empty($list_archived['success']));
    ac_assert('List archived returns lists array', is_array($list_archived['data']['lists'] ?? null));
    ac_assert(
        'List archived includes archived list',
        count(array_filter(
            $list_archived['data']['lists'] ?? [],
            static function ($row) use ($list_id) {
                return is_array($row)
                    && (int) ($row['id'] ?? 0) === $list_id
                    && ($row['status'] ?? '') === 'archived';
            }
        )) === 1
    );

    $restore_list = (new RestoreTaskListUseCase())->execute(['list_id' => $list_id]);
    ac_assert('Restore list success', !empty($restore_list['success']));
    ac_assert('Restore list status active', ($restore_list['data']['list']['status'] ?? '') === 'active');
    ac_assert('Restore preserves tasks', ($restore_list['data']['tasks_preserved'] ?? 0) >= 2);

    $board_after_restore = (new GetTaskBoardUseCase())->execute();
    ac_assert(
        'Restored list included in active board',
        in_array($list_id, $board_after_restore['organization']['list_order'] ?? [], true)
    );

    $restore_active = (new RestoreTaskListUseCase())->execute(['list_id' => $list_id]);
    ac_assert('Restore active list idempotent success', !empty($restore_active['success']));
    ac_assert(
        'Restore active list idempotent status',
        ($restore_active['data']['list']['status'] ?? '') === 'active'
    );

    $restore_missing = (new RestoreTaskListUseCase())->execute(['list_id' => 999999999]);
    ac_assert('Restore missing list fails', empty($restore_missing['success']));
    ac_assert(
        'Restore missing list error code',
        ($restore_missing['error']['code'] ?? '') === 'list_not_found'
    );

    $wpdb->delete($tasks_table, ['list_id' => $list_id], ['%d']);
    $wpdb->delete($tasks_table, ['list_id' => $high_list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $high_list_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar Use Cases.\n";
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
