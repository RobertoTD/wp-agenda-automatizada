<?php
/**
 * AC MC13N-1 — ReturnIgnoredUserTasksUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-return-ignored-user-tasks-use-case-ac.php
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

$use_case_file = $plugin_root . '/includes/application/tasks/ReturnIgnoredUserTasksUseCase.php';
$use_case_src = file_get_contents($use_case_file);

ac_assert('ReturnIgnoredUserTasksUseCase file readable', $use_case_src !== false);
ac_assert('Use case uses clear_dismiss_hiding_effect_for_task_ids', strpos($use_case_src, 'clear_dismiss_hiding_effect_for_task_ids') !== false);
ac_assert('Use case uses AA_Task_Signal_Policy', strpos($use_case_src, 'AA_Task_Signal_Policy') !== false);
ac_assert('Use case scopes active lists', strpos($use_case_src, "list_all('active')") !== false);
ac_assert('Use case filters pending tasks', strpos($use_case_src, "'pending'") !== false);
ac_assert('Use case returns returned_count', strpos($use_case_src, "'returned_count'") !== false);
ac_assert('Use case returns task_ids', strpos($use_case_src, "'task_ids'") !== false);

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
    require_once $plugin_root . '/includes/application/tasks/RecordTaskDismissSignalUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/GetTaskBoardUseCase.php';
    require_once $use_case_file;

    AA_Schema::install();

    global $wpdb;
    $state_table = $wpdb->prefix . 'aa_task_state';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $suffix = (string) time();
    $fixed_now = '2026-06-08 12:00:00';

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Return ignored list ' . $suffix,
    ]);
    ac_assert('Seed active list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);

    $created_hidden = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Hidden ignored task ' . $suffix,
    ]);
    ac_assert('Seed hidden task success', !empty($created_hidden['success']));
    $hidden_task_id = (int) ($created_hidden['data']['task']['id'] ?? 0);

    $created_visible = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Visible pending task ' . $suffix,
    ]);
    ac_assert('Seed visible task success', !empty($created_visible['success']));
    $visible_task_id = (int) ($created_visible['data']['task']['id'] ?? 0);

    $created_done = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Done dismissed task ' . $suffix,
    ]);
    ac_assert('Seed done task success', !empty($created_done['success']));
    $done_task_id = (int) ($created_done['data']['task']['id'] ?? 0);

    (new ChangeTaskStatusUseCase())->execute([
        'task_id' => $done_task_id,
        'status' => 'done',
    ]);
    TaskStateRepository::record_dismiss($done_task_id, '2026-06-08 10:00:00');

    $empty_result = (new ReturnIgnoredUserTasksUseCase())->execute([
        'now' => $fixed_now,
    ]);
    ac_assert('No-op when no dismissed hiding tasks', !empty($empty_result['success']));
    ac_assert(
        'No-op returns returned_count=0',
        (int) ($empty_result['data']['returned_count'] ?? -1) === 0
    );

    $dismiss_hidden = (new RecordTaskDismissSignalUseCase())->execute(['task_id' => $hidden_task_id]);
    ac_assert('Dismiss hidden task success', !empty($dismiss_hidden['success']));

    $board_before = (new GetTaskBoardUseCase())->execute();
    $hidden_before = task_ids_in_active_buckets($board_before, $hidden_task_id);
    $visible_before = task_ids_in_active_buckets($board_before, $visible_task_id);
    ac_assert('Hidden dismissed task absent from active buckets before return', $hidden_before === false);
    ac_assert('Visible pending task present before return', $visible_before === true);

    $return_result = (new ReturnIgnoredUserTasksUseCase())->execute([
        'now' => $fixed_now,
    ]);
    ac_assert('Return happy path success', !empty($return_result['success']));
    ac_assert(
        'Return happy path returned_count=1',
        (int) ($return_result['data']['returned_count'] ?? 0) === 1
    );
    ac_assert(
        'Return happy path includes hidden task id',
        in_array($hidden_task_id, $return_result['data']['task_ids'] ?? [], true)
    );
    ac_assert(
        'Return happy path excludes visible pending task id',
        !in_array($visible_task_id, $return_result['data']['task_ids'] ?? [], true)
    );
    ac_assert(
        'Return happy path excludes done task id',
        !in_array($done_task_id, $return_result['data']['task_ids'] ?? [], true)
    );

    $state_after = TaskStateRepository::find_by_task_id($hidden_task_id);
    ac_assert(
        'Return preserves dismiss_count',
        (int) ($state_after['dismiss_count'] ?? 0) === 1
    );
    ac_assert(
        'Return preserves last_dismissed_at',
        ($state_after['last_dismissed_at'] ?? '') !== ''
    );
    ac_assert(
        'Return writes dismiss_until',
        ($state_after['dismiss_until'] ?? '') === $fixed_now
    );

    $board_after = (new GetTaskBoardUseCase())->execute();
    ac_assert(
        'GetTaskBoard projects returned task in active buckets',
        task_ids_in_active_buckets($board_after, $hidden_task_id) === true
    );
    ac_assert(
        'GetTaskBoard keeps visible pending task in active buckets',
        task_ids_in_active_buckets($board_after, $visible_task_id) === true
    );
    ac_assert(
        'GetTaskBoard keeps done task outside active buckets',
        task_ids_in_active_buckets($board_after, $done_task_id) === false
    );

    $second_return = (new ReturnIgnoredUserTasksUseCase())->execute([
        'now' => $fixed_now,
    ]);
    ac_assert('Second return is idempotent success', !empty($second_return['success']));
    ac_assert(
        'Second return returns returned_count=0',
        (int) ($second_return['data']['returned_count'] ?? -1) === 0
    );

    $archived_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Archived ignored list ' . $suffix,
    ]);
    $archived_list_id = (int) ($archived_list['data']['list']['id'] ?? 0);
    (new ArchiveTaskListUseCase())->execute(['list_id' => $archived_list_id]);

    $archived_task = (new CreateTaskUseCase())->execute([
        'list_id' => $archived_list_id,
        'title' => 'Archived list ignored task ' . $suffix,
    ]);
    $archived_task_id = (int) ($archived_task['data']['task']['id'] ?? 0);
    (new RecordTaskDismissSignalUseCase())->execute(['task_id' => $archived_task_id]);

    $return_archived = (new ReturnIgnoredUserTasksUseCase())->execute([
        'now' => '2026-06-08 13:00:00',
    ]);
    ac_assert('Return after archived dismiss still success', !empty($return_archived['success']));
    ac_assert(
        'Return does not include archived list dismissed task',
        !in_array($archived_task_id, $return_archived['data']['task_ids'] ?? [], true)
    );

    $wpdb->delete($state_table, ['task_id' => $hidden_task_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $done_task_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $archived_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $hidden_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $visible_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $done_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $archived_task_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $archived_list_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar use case end-to-end.\n";
}

/**
 * @param array<string,mixed> $board
 */
function task_ids_in_active_buckets(array $board, int $task_id): bool {
    $organization = is_array($board['organization'] ?? null) ? $board['organization'] : [];
    $bucket_order = is_array($organization['task_bucket_order_by_list'] ?? null)
        ? $organization['task_bucket_order_by_list']
        : [];

    foreach ($bucket_order as $list_buckets) {
        if (!is_array($list_buckets)) {
            continue;
        }

        foreach (['primary', 'secondary'] as $bucket_key) {
            $ids = $list_buckets[$bucket_key] ?? [];

            if (!is_array($ids)) {
                continue;
            }

            foreach ($ids as $id) {
                if ((int) $id === $task_id) {
                    return true;
                }
            }
        }
    }

    return false;
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
