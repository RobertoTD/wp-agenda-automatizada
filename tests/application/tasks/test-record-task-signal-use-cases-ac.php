<?php
/**
 * AC MC13G-A — RecordTaskDeferSignalUseCase + RecordTaskDismissSignalUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-record-task-signal-use-cases-ac.php
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

$defer_uc_file = $plugin_root . '/includes/application/tasks/RecordTaskDeferSignalUseCase.php';
$dismiss_uc_file = $plugin_root . '/includes/application/tasks/RecordTaskDismissSignalUseCase.php';

// ─── Estáticos ───────────────────────────────────────────────

$defer_src = file_get_contents($defer_uc_file);
ac_assert('RecordTaskDeferSignalUseCase file readable', $defer_src !== false);
ac_assert('Defer use case uses record_defer', strpos($defer_src, 'record_defer') !== false);
ac_assert('Defer use case returns task_state', strpos($defer_src, "'task_state'") !== false);
ac_assert('Defer use case checks pending', strpos($defer_src, 'task_not_pending') !== false);
ac_assert('Defer use case does not touch TaskRepository update', strpos($defer_src, 'update_status') === false);

$dismiss_src = file_get_contents($dismiss_uc_file);
ac_assert('RecordTaskDismissSignalUseCase file readable', $dismiss_src !== false);
ac_assert('Dismiss use case uses record_dismiss', strpos($dismiss_src, 'record_dismiss') !== false);
ac_assert('Dismiss use case returns task_state', strpos($dismiss_src, "'task_state'") !== false);
ac_assert('Dismiss use case checks pending', strpos($dismiss_src, 'task_not_pending') !== false);
ac_assert('Dismiss use case does not touch TaskRepository update', strpos($dismiss_src, 'update_status') === false);

// ─── Integración WordPress ───────────────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/TaskStateRepository.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/ArchiveTaskListUseCase.php';
    require_once $defer_uc_file;
    require_once $dismiss_uc_file;

    AA_Schema::install();

    global $wpdb;
    $state_table = $wpdb->prefix . 'aa_task_state';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $suffix = (string) time();

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Signal UC list ' . $suffix,
    ]);
    ac_assert('Seed list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);

    $created_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Signal UC task ' . $suffix,
    ]);
    ac_assert('Seed task success', !empty($created_task['success']));
    $task_id = (int) ($created_task['data']['task']['id'] ?? 0);

    $defer_result = (new RecordTaskDeferSignalUseCase())->execute(['task_id' => $task_id]);
    ac_assert('Defer happy path success', !empty($defer_result['success']));
    ac_assert('Defer response includes task_state', is_array($defer_result['data']['task_state'] ?? null));
    ac_assert(
        'Defer response defer_count=1',
        (int) ($defer_result['data']['task_state']['defer_count'] ?? 0) === 1
    );
    ac_assert(
        'Defer response defer_until null',
        ($defer_result['data']['task_state']['defer_until'] ?? 'x') === null
    );

    $task_after_defer = TaskRepository::find_by_id($task_id);
    ac_assert('Defer does not change task status', ($task_after_defer['status'] ?? '') === 'pending');

    $dismiss_result = (new RecordTaskDismissSignalUseCase())->execute(['task_id' => $task_id]);
    ac_assert('Dismiss happy path success', !empty($dismiss_result['success']));
    ac_assert('Dismiss response includes task_state', is_array($dismiss_result['data']['task_state'] ?? null));
    ac_assert(
        'Dismiss response dismiss_count=1',
        (int) ($dismiss_result['data']['task_state']['dismiss_count'] ?? 0) === 1
    );
    ac_assert(
        'Dismiss response dismiss_until null',
        ($dismiss_result['data']['task_state']['dismiss_until'] ?? 'x') === null
    );

    $not_found = (new RecordTaskDeferSignalUseCase())->execute(['task_id' => 99999999]);
    ac_assert(
        'Defer task not found',
        empty($not_found['success']) && ($not_found['error']['code'] ?? '') === 'task_not_found'
    );

    $not_found_dismiss = (new RecordTaskDismissSignalUseCase())->execute(['task_id' => 99999999]);
    ac_assert(
        'Dismiss task not found',
        empty($not_found_dismiss['success']) && ($not_found_dismiss['error']['code'] ?? '') === 'task_not_found'
    );

    (new ChangeTaskStatusUseCase())->execute([
        'task_id' => $task_id,
        'status' => 'done',
    ]);

    $defer_done = (new RecordTaskDeferSignalUseCase())->execute(['task_id' => $task_id]);
    ac_assert(
        'Defer rejects done task',
        empty($defer_done['success']) && ($defer_done['success'] ?? true) === false
        && ($defer_done['error']['code'] ?? '') === 'task_not_pending'
    );

    $dismiss_done = (new RecordTaskDismissSignalUseCase())->execute(['task_id' => $task_id]);
    ac_assert(
        'Dismiss rejects done task',
        empty($dismiss_done['success'])
        && ($dismiss_done['error']['code'] ?? '') === 'task_not_pending'
    );

    $pending_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Signal archived list task ' . $suffix,
    ]);
    $pending_task_id = (int) ($pending_task['data']['task']['id'] ?? 0);

    (new ArchiveTaskListUseCase())->execute(['list_id' => $list_id]);

    $defer_archived = (new RecordTaskDeferSignalUseCase())->execute(['task_id' => $pending_task_id]);
    ac_assert(
        'Defer rejects archived list',
        empty($defer_archived['success'])
        && ($defer_archived['error']['code'] ?? '') === 'list_not_found'
    );

    $dismiss_archived = (new RecordTaskDismissSignalUseCase())->execute(['task_id' => $pending_task_id]);
    ac_assert(
        'Dismiss rejects archived list',
        empty($dismiss_archived['success'])
        && ($dismiss_archived['error']['code'] ?? '') === 'list_not_found'
    );

    $invalid_task_id = (new RecordTaskDeferSignalUseCase())->execute(['task_id' => 0]);
    ac_assert(
        'Defer rejects invalid task id',
        empty($invalid_task_id['success'])
        && ($invalid_task_id['error']['code'] ?? '') === 'task_not_found'
    );

    $wpdb->delete($state_table, ['task_id' => $task_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $pending_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['list_id' => $list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
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
