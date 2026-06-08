<?php
/**
 * AC MC13G-B — AA_Task_Signal_Policy.
 *
 * Ejecutar: php tests/domain/tasks/test-aa-task-signal-policy-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-signal-policy.php';

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

/**
 * @param list<array<string,mixed>> $tasks
 * @param array<int,array<string,mixed>> $task_state_by_id
 * @return array<int,array<string,mixed>>
 */
function task_signal_evaluate_all(array $tasks, array $task_state_by_id, string $now = '2026-06-06 12:00:00'): array {
    return (new AA_Task_Signal_Policy())->evaluate_all([
        'tasks' => $tasks,
        'task_state_by_id' => $task_state_by_id,
        'now' => $now,
    ]);
}

$pending_task = [
    'id' => 10,
    'list_id' => 1,
    'title' => 'Pending task',
    'status' => 'pending',
];

$now = '2026-06-06 12:00:00';
$policy = new AA_Task_Signal_Policy();

$without_state = $policy->evaluate_task(AA_Task::from_array($pending_task), null, $now);
ac_assert('Without state has_defer false', ($without_state['signals']['has_defer'] ?? true) === false);
ac_assert('Without state has_dismiss false', ($without_state['signals']['has_dismiss'] ?? true) === false);
ac_assert('Without state is_defer_active false', ($without_state['state']['is_defer_active'] ?? true) === false);
ac_assert('Without state is_dismiss_active false', ($without_state['state']['is_dismiss_active'] ?? true) === false);
ac_assert('Without state visible_in_active true', ($without_state['visible_in_active'] ?? false) === true);

$defer_state = [
    'task_id' => 10,
    'defer_count' => 1,
    'last_deferred_at' => '2026-06-06 10:00:00',
    'defer_until' => null,
    'dismiss_count' => 0,
    'last_dismissed_at' => null,
    'dismiss_until' => null,
];
$defer_eval = $policy->evaluate_task(AA_Task::from_array($pending_task), $defer_state, $now);
ac_assert('Defer signal sets has_defer true', ($defer_eval['signals']['has_defer'] ?? false) === true);
ac_assert('Defer signal without until keeps is_defer_active false', ($defer_eval['state']['is_defer_active'] ?? true) === false);

$dismiss_state = [
    'task_id' => 10,
    'defer_count' => 0,
    'last_deferred_at' => null,
    'defer_until' => null,
    'dismiss_count' => 2,
    'last_dismissed_at' => '2026-06-06 11:00:00',
    'dismiss_until' => null,
];
$dismiss_eval = $policy->evaluate_task(AA_Task::from_array($pending_task), $dismiss_state, $now);
ac_assert('Dismiss signal sets has_dismiss true', ($dismiss_eval['signals']['has_dismiss'] ?? false) === true);
ac_assert('Dismiss signal without until keeps is_dismiss_active false', ($dismiss_eval['state']['is_dismiss_active'] ?? true) === false);
ac_assert(
    'Dismiss signal without until sets is_dismiss_hiding true',
    ($dismiss_eval['state']['is_dismiss_hiding'] ?? false) === true
);

$future_defer_state = [
    'task_id' => 10,
    'defer_count' => 1,
    'last_deferred_at' => '2026-06-06 10:00:00',
    'defer_until' => '2026-06-07 10:00:00',
    'dismiss_count' => 0,
    'last_dismissed_at' => null,
    'dismiss_until' => null,
];
$future_defer_eval = $policy->evaluate_task(AA_Task::from_array($pending_task), $future_defer_state, $now);
ac_assert('Future defer_until sets is_defer_active true', ($future_defer_eval['state']['is_defer_active'] ?? false) === true);
ac_assert('Future defer_until disables latent can_defer', ($future_defer_eval['capabilities']['can_defer'] ?? true) === false);

$past_defer_state = [
    'task_id' => 10,
    'defer_count' => 1,
    'last_deferred_at' => '2026-06-05 10:00:00',
    'defer_until' => '2026-06-06 11:00:00',
    'dismiss_count' => 0,
    'last_dismissed_at' => null,
    'dismiss_until' => null,
];
$past_defer_eval = $policy->evaluate_task(AA_Task::from_array($pending_task), $past_defer_state, $now);
ac_assert('Past defer_until sets is_defer_active false', ($past_defer_eval['state']['is_defer_active'] ?? true) === false);

$future_dismiss_state = [
    'task_id' => 10,
    'defer_count' => 0,
    'last_deferred_at' => null,
    'defer_until' => null,
    'dismiss_count' => 1,
    'last_dismissed_at' => '2026-06-06 10:00:00',
    'dismiss_until' => '2026-06-07 10:00:00',
];
$future_dismiss_eval = $policy->evaluate_task(AA_Task::from_array($pending_task), $future_dismiss_state, $now);
ac_assert('Future dismiss_until sets is_dismiss_active true', ($future_dismiss_eval['state']['is_dismiss_active'] ?? false) === true);
ac_assert('Future dismiss_until disables latent can_dismiss', ($future_dismiss_eval['capabilities']['can_dismiss'] ?? true) === false);

$past_dismiss_state = [
    'task_id' => 10,
    'defer_count' => 0,
    'last_deferred_at' => null,
    'defer_until' => null,
    'dismiss_count' => 1,
    'last_dismissed_at' => '2026-06-05 10:00:00',
    'dismiss_until' => '2026-06-06 11:00:00',
];
$past_dismiss_eval = $policy->evaluate_task(AA_Task::from_array($pending_task), $past_dismiss_state, $now);
ac_assert('Past dismiss_until sets is_dismiss_active false', ($past_dismiss_eval['state']['is_dismiss_active'] ?? true) === false);
ac_assert(
    'Past dismiss_until clears is_dismiss_hiding while has_dismiss stays true',
    ($past_dismiss_eval['signals']['has_dismiss'] ?? false) === true
    && ($past_dismiss_eval['state']['is_dismiss_hiding'] ?? true) === false
);

$all = task_signal_evaluate_all([$pending_task], [
    10 => $defer_state,
]);
ac_assert('evaluate_all indexes by task id', isset($all[10]));
ac_assert('evaluate_all visible_in_active true', ($all[10]['visible_in_active'] ?? false) === true);
ac_assert('evaluate_all can_reactivate false', ($all[10]['capabilities']['can_reactivate'] ?? true) === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
