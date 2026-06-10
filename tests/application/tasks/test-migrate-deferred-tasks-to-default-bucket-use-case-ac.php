<?php
/**
 * AC MC13O-H3B-2 — MigrateDeferredTasksToDefaultBucketUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-migrate-deferred-tasks-to-default-bucket-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$use_case_file = $plugin_root . '/includes/application/tasks/MigrateDeferredTasksToDefaultBucketUseCase.php';

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

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-06-09 12:00:00' : '2026-06-09 12:00:00';
    }
}

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $use_case_file;

$use_case_src = file_get_contents($use_case_file);
$repo_src = file_get_contents($plugin_root . '/includes/repositories/TaskRepository.php');

ac_assert('Use case file readable', $use_case_src !== false);
ac_assert(
    'Use case defines MigrateDeferredTasksToDefaultBucketUseCase',
    strpos($use_case_src, 'class MigrateDeferredTasksToDefaultBucketUseCase') !== false
);
ac_assert(
    'Use case uses TaskRepository backfill',
    strpos($use_case_src, 'backfill_deferred_primary_to_secondary_bucket') !== false
);
ac_assert('Use case does not use record_defer', strpos($use_case_src, 'record_defer') === false);
ac_assert('Use case does not use TaskStateRepository', strpos($use_case_src, 'TaskStateRepository') === false);
ac_assert('Use case does not update status', strpos($use_case_src, 'update_status') === false);
ac_assert('Use case returns matched_count', strpos($use_case_src, 'matched_count') !== false);
ac_assert(
    'Repository defines backfill_deferred_primary_to_secondary_bucket',
    strpos($repo_src, 'function backfill_deferred_primary_to_secondary_bucket') !== false
);
ac_assert('Repository backfill checks defer_count', strpos($repo_src, 'defer_count > 0') !== false);
ac_assert('Repository backfill checks last_deferred_at', strpos($repo_src, 'last_deferred_at') !== false);
ac_assert('Repository backfill targets primary default_bucket', strpos($repo_src, 'DEFAULT_BUCKET_PRIMARY') !== false);

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
    require_once $plugin_root . '/includes/domain/tasks/class-aa-task-work-cycle-policy.php';
    require_once $plugin_root . '/includes/application/tasks/RecordTaskDismissSignalUseCase.php';

    AA_Schema::install();

    global $wpdb;
    $state_table = $wpdb->prefix . 'aa_task_state';
    $suffix = (string) time();

    $list = (new CreateTaskListUseCase())->execute(['title' => 'Backfill list ' . $suffix]);
    $list_id = (int) ($list['data']['list']['id'] ?? 0);

    $primary_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Defer primary ' . $suffix,
    ]);
    $primary_task_id = (int) ($primary_task['data']['task']['id'] ?? 0);

    $secondary_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Defer secondary ' . $suffix,
        'default_bucket' => 'secondary',
    ]);
    $secondary_task_id = (int) ($secondary_task['data']['task']['id'] ?? 0);

    $no_defer_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'No defer ' . $suffix,
    ]);
    $no_defer_task_id = (int) ($no_defer_task['data']['task']['id'] ?? 0);

    $done_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Done defer ' . $suffix,
    ]);
    $done_task_id = (int) ($done_task['data']['task']['id'] ?? 0);
    (new ChangeTaskStatusUseCase())->execute(['task_id' => $done_task_id, 'status' => 'done']);

    TaskStateRepository::record_defer($primary_task_id, '2026-06-08 10:00:00');
    TaskStateRepository::record_defer($secondary_task_id, '2026-06-08 11:00:00');
    TaskStateRepository::record_defer($done_task_id, '2026-06-08 12:00:00');

    $dismiss_until = (new AA_Task_Work_Cycle_Policy())->resolve_next_work_cycle_reset_at('2026-06-08 10:00:00');
    TaskStateRepository::record_dismiss($primary_task_id, '2026-06-08 10:05:00', $dismiss_until);
    TaskStateRepository::record_system_completion_evaluation($primary_task_id, true, '2026-06-08 10:06:00');

    $state_before = TaskStateRepository::find_by_task_id($primary_task_id);
    $task_before = TaskRepository::find_by_id($primary_task_id);
    $done_before = TaskRepository::find_by_id($done_task_id);

    $first_run = (new MigrateDeferredTasksToDefaultBucketUseCase())->execute();
    ac_assert('First backfill success', !empty($first_run['success']));
    ac_assert(
        'First backfill updates deferred primary',
        ($first_run['data']['updated_count'] ?? 0) >= 1
        && ($first_run['data']['matched_count'] ?? 0) >= 1
    );

    $primary_after = TaskRepository::find_by_id($primary_task_id);
    ac_assert(
        'Deferred primary task becomes secondary default_bucket',
        ($primary_after['default_bucket'] ?? '') === 'secondary'
    );
    ac_assert(
        'Deferred secondary task stays secondary',
        (TaskRepository::find_by_id($secondary_task_id)['default_bucket'] ?? '') === 'secondary'
    );
    ac_assert(
        'Task without defer stays primary',
        (TaskRepository::find_by_id($no_defer_task_id)['default_bucket'] ?? '') === 'primary'
    );
    ac_assert(
        'Done deferred task also backfills to secondary',
        (TaskRepository::find_by_id($done_task_id)['default_bucket'] ?? '') === 'secondary'
        && ($done_before['status'] ?? '') === 'done'
        && (TaskRepository::find_by_id($done_task_id)['status'] ?? '') === 'done'
    );

    $state_after = TaskStateRepository::find_by_task_id($primary_task_id);
    ac_assert(
        'defer_count preserved',
        (int) ($state_after['defer_count'] ?? 0) === (int) ($state_before['defer_count'] ?? 0)
    );
    ac_assert(
        'last_deferred_at preserved',
        ($state_after['last_deferred_at'] ?? '') === ($state_before['last_deferred_at'] ?? '')
    );
    ac_assert(
        'dismiss_until preserved',
        ($state_after['dismiss_until'] ?? '') === ($state_before['dismiss_until'] ?? '')
    );
    ac_assert(
        'dismiss_count preserved',
        (int) ($state_after['dismiss_count'] ?? 0) === (int) ($state_before['dismiss_count'] ?? 0)
    );
    ac_assert(
        'completed_by_system preserved',
        (int) ($state_after['completed_by_system'] ?? 0) === (int) ($state_before['completed_by_system'] ?? 0)
    );
    ac_assert(
        'status preserved on backfilled task',
        ($primary_after['status'] ?? '') === ($task_before['status'] ?? '')
        && ($primary_after['completed_at'] ?? null) === ($task_before['completed_at'] ?? null)
    );

    $second_run = (new MigrateDeferredTasksToDefaultBucketUseCase())->execute();
    ac_assert('Second backfill success', !empty($second_run['success']));
    ac_assert(
        'Second backfill is idempotent',
        ($second_run['data']['matched_count'] ?? -1) === 0
        && ($second_run['data']['updated_count'] ?? -1) === 0
    );

    $edge_list = (new CreateTaskListUseCase())->execute(['title' => 'Backfill edge ' . $suffix]);
    $edge_list_id = (int) ($edge_list['data']['list']['id'] ?? 0);

    $zero_count_task = (new CreateTaskUseCase())->execute([
        'list_id' => $edge_list_id,
        'title' => 'Zero defer count',
    ]);
    $zero_count_id = (int) ($zero_count_task['data']['task']['id'] ?? 0);
    TaskStateRepository::upsert($zero_count_id, [
        'defer_count' => 0,
        'last_deferred_at' => '2026-06-07 08:00:00',
    ]);

    $null_deferred_task = (new CreateTaskUseCase())->execute([
        'list_id' => $edge_list_id,
        'title' => 'Null deferred at',
    ]);
    $null_deferred_id = (int) ($null_deferred_task['data']['task']['id'] ?? 0);
    TaskStateRepository::upsert($null_deferred_id, [
        'defer_count' => 2,
        'last_deferred_at' => null,
    ]);

    $edge_run = (new MigrateDeferredTasksToDefaultBucketUseCase())->execute();
    ac_assert('Edge backfill still success', !empty($edge_run['success']));
    ac_assert(
        'defer_count zero with last_deferred_at does not migrate',
        (TaskRepository::find_by_id($zero_count_id)['default_bucket'] ?? '') === 'primary'
    );
    ac_assert(
        'defer_count positive without last_deferred_at does not migrate',
        (TaskRepository::find_by_id($null_deferred_id)['default_bucket'] ?? '') === 'primary'
    );

    $wpdb->delete($state_table, ['task_id' => $primary_task_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $secondary_task_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $done_task_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $zero_count_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $null_deferred_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar backfill end-to-end.\n";
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
