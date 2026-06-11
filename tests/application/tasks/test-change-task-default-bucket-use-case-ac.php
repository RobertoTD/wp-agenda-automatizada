<?php
/**
 * AC MC13O-H3B-1 — ChangeTaskDefaultBucketUseCase + write path default_bucket.
 *
 * Ejecutar: php tests/application/tasks/test-change-task-default-bucket-use-case-ac.php
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

$uc_file = $plugin_root . '/includes/application/tasks/ChangeTaskDefaultBucketUseCase.php';
$uc_src = file_get_contents($uc_file);

ac_assert('ChangeTaskDefaultBucketUseCase file readable', $uc_src !== false);
ac_assert('Use case uses TaskRepository update', strpos($uc_src, 'TaskRepository::update') !== false);
ac_assert('Use case validates invalid_task_id', strpos($uc_src, 'invalid_task_id') !== false);
ac_assert('Use case validates invalid_default_bucket', strpos($uc_src, 'invalid_default_bucket') !== false);
ac_assert('Use case validates active list', strpos($uc_src, 'find_active_list') !== false);
ac_assert('Use case does not touch TaskStateRepository', strpos($uc_src, 'TaskStateRepository') === false);
ac_assert('Use case does not touch record_defer', strpos($uc_src, 'record_defer') === false);
ac_assert('Use case does not touch update_status', strpos($uc_src, 'update_status') === false);
ac_assert('Use case returns task payload', strpos($uc_src, "'task'") !== false);
ac_assert('Use case uses governance policy', strpos($uc_src, 'AA_Task_Governance_Policy') !== false);
ac_assert('Use case rejects task_not_editable', strpos($uc_src, 'task_not_editable') !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/TaskStateRepository.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/ArchiveTaskListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/RecordTaskDeferSignalUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/RecordTaskDismissSignalUseCase.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
    require_once $uc_file;

    AA_Schema::install();

    global $wpdb;
    $state_table = $wpdb->prefix . 'aa_task_state';
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $suffix = (string) time();

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Bucket UC list ' . $suffix,
    ]);
    ac_assert('Seed list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);

    $created_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Bucket UC task ' . $suffix,
    ]);
    ac_assert('Seed task success', !empty($created_task['success']));
    $task_id = (int) ($created_task['data']['task']['id'] ?? 0);
    ac_assert('Seed task default_bucket primary', ($created_task['data']['task']['default_bucket'] ?? '') === 'primary');

    $to_secondary = (new ChangeTaskDefaultBucketUseCase())->execute([
        'task_id' => $task_id,
        'default_bucket' => 'secondary',
    ]);
    ac_assert('Change to secondary success', !empty($to_secondary['success']));
    ac_assert(
        'Change to secondary persists bucket',
        ($to_secondary['data']['task']['default_bucket'] ?? '') === 'secondary'
    );
    ac_assert(
        'Change to secondary keeps status pending',
        ($to_secondary['data']['task']['status'] ?? '') === 'pending'
    );
    ac_assert(
        'Change to secondary keeps completed_at null',
        ($to_secondary['data']['task']['completed_at'] ?? null) === null
    );

    $to_primary = (new ChangeTaskDefaultBucketUseCase())->execute([
        'task_id' => $task_id,
        'default_bucket' => 'primary',
    ]);
    ac_assert('Change to primary success', !empty($to_primary['success']));
    ac_assert(
        'Change to primary persists bucket',
        ($to_primary['data']['task']['default_bucket'] ?? '') === 'primary'
    );

    $invalid_id = (new ChangeTaskDefaultBucketUseCase())->execute([
        'task_id' => 0,
        'default_bucket' => 'secondary',
    ]);
    ac_assert(
        'Invalid task id fails',
        empty($invalid_id['success'])
        && ($invalid_id['error']['code'] ?? '') === 'invalid_task_id'
    );

    $invalid_bucket = (new ChangeTaskDefaultBucketUseCase())->execute([
        'task_id' => $task_id,
        'default_bucket' => 'tertiary',
    ]);
    ac_assert(
        'Invalid bucket fails',
        empty($invalid_bucket['success'])
        && ($invalid_bucket['error']['code'] ?? '') === 'invalid_default_bucket'
    );

    $missing_task = (new ChangeTaskDefaultBucketUseCase())->execute([
        'task_id' => 999999991,
        'default_bucket' => 'secondary',
    ]);
    ac_assert(
        'Missing task fails',
        empty($missing_task['success'])
        && ($missing_task['error']['code'] ?? '') === 'task_not_found'
    );

    (new RecordTaskDeferSignalUseCase())->execute(['task_id' => $task_id]);
    require_once $plugin_root . '/includes/domain/tasks/class-aa-task-work-cycle-policy.php';
    (new RecordTaskDismissSignalUseCase())->execute(['task_id' => $task_id]);

    $after_signals = (new ChangeTaskDefaultBucketUseCase())->execute([
        'task_id' => $task_id,
        'default_bucket' => 'secondary',
    ]);
    ac_assert('Change bucket after defer/dismiss success', !empty($after_signals['success']));

    $state_row = TaskStateRepository::find_by_task_id($task_id);
    ac_assert(
        'Defer signal preserved after bucket change',
        is_array($state_row) && (int) ($state_row['defer_count'] ?? 0) === 1
    );
    ac_assert(
        'Dismiss signal preserved after bucket change',
        is_array($state_row) && (int) ($state_row['dismiss_count'] ?? 0) === 1
    );

    $archived_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Archived bucket list ' . $suffix,
    ]);
    $archived_list_id = (int) ($archived_list['data']['list']['id'] ?? 0);
    (new ArchiveTaskListUseCase())->execute(['list_id' => $archived_list_id]);
    $archived_task = (new CreateTaskUseCase())->execute([
        'list_id' => $archived_list_id,
        'title' => 'Task on archived list',
    ]);
    ac_assert('Create on archived list fails', empty($archived_task['success']));

    $seeded_list = SeededTaskRepository::upsert_seeded_list([
        'title' => 'Bucket governance ' . $suffix,
        'description' => 'Lista developer',
        'owner_type' => 'developer',
        'source_category' => 'agenda_app',
        'origin_key' => 'bucket.governance.' . $suffix,
        'managed_by' => 'developer',
        'status' => 'active',
        'importance' => 0,
        'position' => 0,
    ]);
    ac_assert('Seed developer list for bucket guard', is_array($seeded_list));
    $seeded_list_id = (int) ($seeded_list['id'] ?? 0);

    $seeded_task = SeededTaskRepository::upsert_seeded_task([
        'list_id' => $seeded_list_id,
        'title' => 'Developer bucket task ' . $suffix,
        'notes' => 'No editable',
        'source_category' => 'agenda_app',
        'origin_key' => 'bucket.governance.task.' . $suffix,
        'managed_by' => 'developer',
        'default_bucket' => 'primary',
        'completion_type' => 'manual',
    ]);
    ac_assert('Seed developer task for bucket guard', is_array($seeded_task));
    $developer_task_id = (int) ($seeded_task['id'] ?? 0);

    $blocked_bucket = (new ChangeTaskDefaultBucketUseCase())->execute([
        'task_id' => $developer_task_id,
        'default_bucket' => 'secondary',
    ]);
    ac_assert(
        'ChangeTaskDefaultBucketUseCase rejects developer task',
        empty($blocked_bucket['success']) && ($blocked_bucket['error']['code'] ?? '') === 'task_not_editable'
    );

    $wpdb->delete($tasks_table, ['id' => $developer_task_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $seeded_list_id], ['%d']);

    (new ArchiveTaskListUseCase())->execute(['list_id' => $list_id]);
    $inactive_change = (new ChangeTaskDefaultBucketUseCase())->execute([
        'task_id' => $task_id,
        'default_bucket' => 'secondary',
    ]);
    ac_assert(
        'Inactive list fails bucket change',
        empty($inactive_change['success'])
        && ($inactive_change['error']['code'] ?? '') === 'list_not_found'
    );

    $wpdb->delete($state_table, ['task_id' => $task_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar use case end-to-end.\n";
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
