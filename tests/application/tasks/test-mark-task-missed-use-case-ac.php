<?php
/**
 * AC MC4 — MarkTaskMissedUseCase ("No realizada").
 *
 * Ejecutar: php tests/application/tasks/test-mark-task-missed-use-case-ac.php
 * Integración WP: AA_WP_ROOT=/ruta/a/wordpress php tests/application/tasks/test-mark-task-missed-use-case-ac.php
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

$uc_file = $plugin_root . '/includes/application/tasks/MarkTaskMissedUseCase.php';
$repo_file = $plugin_root . '/includes/repositories/TaskRepository.php';

// ─── Estáticos ───────────────────────────────────────────────

$uc_src = file_get_contents($uc_file);
ac_assert('MarkTaskMissedUseCase file readable', $uc_src !== false);
ac_assert('Use case defines class', strpos($uc_src, 'class MarkTaskMissedUseCase') !== false);
ac_assert('Use case checks pending', strpos($uc_src, 'task_not_pending') !== false);
ac_assert('Use case checks overdue', strpos($uc_src, 'task_not_overdue') !== false);
ac_assert('Use case uses is_overdue', strpos($uc_src, 'is_overdue($now)') !== false);
ac_assert('Use case calls mark_missed', strpos($uc_src, 'mark_missed') !== false);

$repo_src = file_get_contents($repo_file);
ac_assert('TaskRepository defines mark_missed', strpos($repo_src, 'function mark_missed') !== false);

$mark_missed_start = strpos($repo_src, 'public static function mark_missed');
$mark_missed_body = $mark_missed_start !== false
    ? substr($repo_src, $mark_missed_start, 260)
    : '';
ac_assert('mark_missed sets status missed', strpos($mark_missed_body, "'status' => 'missed'") !== false);
ac_assert('mark_missed nulls completed_at', strpos($mark_missed_body, "'completed_at' => null") !== false);
ac_assert('mark_missed does not touch archived_at', strpos($mark_missed_body, 'archived_at') === false);

// ─── Integración WordPress ───────────────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/TaskRepository.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php';
    require_once $uc_file;

    AA_Schema::install();

    global $wpdb;
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $suffix = (string) time();

    $created_list = (new CreateTaskListUseCase())->execute(['title' => 'Missed UC list ' . $suffix]);
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);
    ac_assert('Seed list success', $list_id > 0);

    $make_task = static function (?string $due_at) use ($list_id, $suffix): int {
        $created = (new CreateTaskUseCase())->execute([
            'list_id' => $list_id,
            'title' => 'Missed UC task ' . $suffix . ' ' . uniqid(),
            'due_at' => $due_at,
        ]);

        return (int) ($created['data']['task']['id'] ?? 0);
    };

    // Tarea pending vencida → missed.
    $overdue_id = $make_task('2000-01-01 08:00:00');
    $missed = (new MarkTaskMissedUseCase())->execute(['task_id' => $overdue_id]);
    ac_assert('Overdue pending task marked missed', !empty($missed['success']));
    ac_assert(
        'Marked task status is missed',
        ($missed['data']['task']['status'] ?? '') === 'missed'
    );
    ac_assert(
        'Marked task completed_at is null',
        ($missed['data']['task']['completed_at'] ?? 'x') === null
    );

    // Idempotencia: ya missed → rechazo task_not_pending.
    $again = (new MarkTaskMissedUseCase())->execute(['task_id' => $overdue_id]);
    ac_assert(
        'Already missed task rejected',
        empty($again['success']) && ($again['error']['code'] ?? '') === 'task_not_pending'
    );

    // Tarea futura → rechazo.
    $future_id = $make_task('2999-01-01 08:00:00');
    $future = (new MarkTaskMissedUseCase())->execute(['task_id' => $future_id]);
    ac_assert(
        'Future task rejected as not overdue',
        empty($future['success']) && ($future['error']['code'] ?? '') === 'task_not_overdue'
    );

    // Tarea sin due_at → rechazo.
    $no_due_id = $make_task(null);
    $no_due = (new MarkTaskMissedUseCase())->execute(['task_id' => $no_due_id]);
    ac_assert(
        'Task without due_at rejected as not overdue',
        empty($no_due['success']) && ($no_due['error']['code'] ?? '') === 'task_not_overdue'
    );

    // Tarea done → rechazo.
    $done_id = $make_task('2000-01-01 08:00:00');
    (new ChangeTaskStatusUseCase())->execute(['task_id' => $done_id, 'status' => 'done']);
    $done = (new MarkTaskMissedUseCase())->execute(['task_id' => $done_id]);
    ac_assert(
        'Done task rejected',
        empty($done['success']) && ($done['error']['code'] ?? '') === 'task_not_pending'
    );

    // Tarea inexistente → rechazo.
    $missing = (new MarkTaskMissedUseCase())->execute(['task_id' => 99999999]);
    ac_assert(
        'Missing task rejected',
        empty($missing['success']) && ($missing['error']['code'] ?? '') === 'task_not_found'
    );

    $wpdb->delete($tasks_table, ['list_id' => $list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar el Use Case end-to-end.\n";
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
