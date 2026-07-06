<?php
/**
 * AC — AA_Tutorial_State_Policy.
 *
 * Ejecutar: php tests/domain/tutorials/test-aa-tutorial-state-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

require_once __DIR__ . '/../../../includes/domain/tutorials/class-aa-tutorial-state-policy.php';

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

$tutorial_id = AA_Tutorial_State_Policy::TUTORIAL_CREATE_TEST_APPOINTMENT;
$empty = AA_Tutorial_State_Policy::empty_state();

ac_assert('empty state version', ($empty['version'] ?? null) === 1);
ac_assert('empty state tutorials', ($empty['tutorials'] ?? null) === []);

$effective = AA_Tutorial_State_Policy::get_effective_tutorial($empty, $tutorial_id);
ac_assert('absent tutorial is available', ($effective['status'] ?? '') === 'available');
ac_assert('absent tutorial has null step', array_key_exists('current_step_id', $effective) && $effective['current_step_id'] === null);

$accepted = AA_Tutorial_State_Policy::apply_transition($empty, $tutorial_id, [
    'status' => 'in_progress',
    'current_step_id' => 'open_sidebar',
]);
ac_assert('accept available -> in_progress ok', ($accepted['ok'] ?? false) === true);
$accepted_tutorial = $accepted['state']['tutorials'][$tutorial_id] ?? [];
ac_assert('accept sets open_sidebar', ($accepted_tutorial['current_step_id'] ?? '') === 'open_sidebar');
ac_assert('accept transition kind', ($accepted['transition_kind'] ?? '') === 'accept');

$invalid_tutorial = AA_Tutorial_State_Policy::apply_transition($empty, 'unknown_tutorial', [
    'status' => 'in_progress',
    'current_step_id' => 'open_sidebar',
]);
ac_assert('reject unknown tutorial', ($invalid_tutorial['ok'] ?? true) === false);

$invalid_status = AA_Tutorial_State_Policy::apply_transition($empty, $tutorial_id, [
    'status' => 'dismissed',
    'current_step_id' => 'open_sidebar',
]);
ac_assert('reject invalid status', ($invalid_status['ok'] ?? true) === false);

$invalid_step = AA_Tutorial_State_Policy::apply_transition($empty, $tutorial_id, [
    'status' => 'in_progress',
    'current_step_id' => 'not_a_real_step',
]);
ac_assert('reject invalid step on accept', ($invalid_step['ok'] ?? true) === false);

$wrong_first_step = AA_Tutorial_State_Policy::apply_transition($empty, $tutorial_id, [
    'status' => 'in_progress',
    'current_step_id' => 'open_calendar',
]);
ac_assert('reject accept not on first step', ($wrong_first_step['ok'] ?? true) === false);

$state = $accepted['state'] ?? AA_Tutorial_State_Policy::empty_state();

$advanced = AA_Tutorial_State_Policy::apply_transition($state, $tutorial_id, [
    'status' => 'in_progress',
    'current_step_id' => 'open_calendar',
]);
ac_assert('advance open_sidebar -> open_calendar ok', ($advanced['ok'] ?? false) === true);
ac_assert('advance transition kind', ($advanced['transition_kind'] ?? '') === 'advance');

$skip_step = AA_Tutorial_State_Policy::apply_transition($state, $tutorial_id, [
    'status' => 'in_progress',
    'current_step_id' => 'calendar_overview',
]);
ac_assert('reject non-linear advance', ($skip_step['ok'] ?? true) === false);

$paused = AA_Tutorial_State_Policy::apply_transition($state, $tutorial_id, [
    'status' => 'paused',
]);
ac_assert('pause in_progress ok', ($paused['ok'] ?? false) === true);
$paused_tutorial = $paused['state']['tutorials'][$tutorial_id] ?? [];
ac_assert('pause retains current_step_id', ($paused_tutorial['current_step_id'] ?? '') === 'open_sidebar');
ac_assert('pause transition kind', ($paused['transition_kind'] ?? '') === 'pause');

$paused_state = $paused['state'] ?? $state;
$resumed = AA_Tutorial_State_Policy::apply_transition($paused_state, $tutorial_id, [
    'status' => 'in_progress',
]);
ac_assert('resume paused -> in_progress ok', ($resumed['ok'] ?? false) === true);
$resumed_tutorial = $resumed['state']['tutorials'][$tutorial_id] ?? [];
ac_assert('resume retains current_step_id', ($resumed_tutorial['current_step_id'] ?? '') === 'open_sidebar');

$paused_mid = AA_Tutorial_State_Policy::apply_transition($state, $tutorial_id, [
    'status' => 'paused',
]);
$complete_from_paused_mid = AA_Tutorial_State_Policy::apply_transition($paused_mid['state'] ?? $state, $tutorial_id, [
    'status' => 'completed',
    'current_step_id' => null,
]);
ac_assert('reject paused mid-flow -> completed', ($complete_from_paused_mid['ok'] ?? true) === false);

$complete_from_mid = AA_Tutorial_State_Policy::apply_transition($state, $tutorial_id, [
    'status' => 'completed',
    'current_step_id' => null,
]);
ac_assert('reject in_progress mid-flow -> completed', ($complete_from_mid['ok'] ?? true) === false);

$last_step_state = $state;
foreach (['open_calendar', 'calendar_overview', 'create_test_appointment'] as $step_id) {
    $result = AA_Tutorial_State_Policy::apply_transition($last_step_state, $tutorial_id, [
        'status' => 'in_progress',
        'current_step_id' => $step_id,
    ]);
    ac_assert('advance to ' . $step_id, ($result['ok'] ?? false) === true);
    $last_step_state = $result['state'] ?? $last_step_state;
}

$completed = AA_Tutorial_State_Policy::apply_transition($last_step_state, $tutorial_id, [
    'status' => 'completed',
    'current_step_id' => null,
]);
ac_assert('complete from last step ok', ($completed['ok'] ?? false) === true);
$completed_tutorial = $completed['state']['tutorials'][$tutorial_id] ?? [];
ac_assert('complete sets status completed', ($completed_tutorial['status'] ?? '') === 'completed');
ac_assert('complete clears current_step_id', array_key_exists('current_step_id', $completed_tutorial) && $completed_tutorial['current_step_id'] === null);

$completed_state = $completed['state'] ?? $last_step_state;
$restart = AA_Tutorial_State_Policy::apply_transition($completed_state, $tutorial_id, [
    'status' => 'in_progress',
    'current_step_id' => 'open_sidebar',
]);
ac_assert('reject completed -> in_progress', ($restart['ok'] ?? true) === false);

$paused_last = AA_Tutorial_State_Policy::apply_transition($last_step_state, $tutorial_id, [
    'status' => 'paused',
]);
$complete_from_paused_last = AA_Tutorial_State_Policy::apply_transition($paused_last['state'] ?? $last_step_state, $tutorial_id, [
    'status' => 'completed',
    'current_step_id' => null,
]);
ac_assert('complete from paused last step ok', ($complete_from_paused_last['ok'] ?? false) === true);

$corrupt = AA_Tutorial_State_Policy::sanitize([
    'version' => 1,
    'tutorials' => [
        $tutorial_id => [
            'status' => 'in_progress',
            'current_step_id' => 'open_sidebar',
            'accepted_at' => 'not-a-date',
        ],
    ],
]);
ac_assert('sanitize drops corrupt tutorial', !isset($corrupt['tutorials'][$tutorial_id]));

$wrong_version = AA_Tutorial_State_Policy::sanitize(['version' => 2, 'tutorials' => []]);
ac_assert('sanitize wrong version -> empty', ($wrong_version['tutorials'] ?? null) === []);

$skipped = AA_Tutorial_State_Policy::apply_transition($empty, $tutorial_id, [
    'status' => 'skipped',
    'current_step_id' => null,
]);
ac_assert('skip available -> skipped ok', ($skipped['ok'] ?? false) === true);
ac_assert('skip transition kind', ($skipped['transition_kind'] ?? '') === 'skip');
$skipped_tutorial = $skipped['state']['tutorials'][$tutorial_id] ?? [];
ac_assert('skip sets status skipped', ($skipped_tutorial['status'] ?? '') === 'skipped');
ac_assert('skip clears current_step_id', array_key_exists('current_step_id', $skipped_tutorial) && $skipped_tutorial['current_step_id'] === null);
ac_assert('skip accepted_at null', array_key_exists('accepted_at', $skipped_tutorial) && $skipped_tutorial['accepted_at'] === null);
ac_assert('skip started_at null', array_key_exists('started_at', $skipped_tutorial) && $skipped_tutorial['started_at'] === null);
ac_assert('skip completed_at null', array_key_exists('completed_at', $skipped_tutorial) && $skipped_tutorial['completed_at'] === null);

$sanitized_skipped = AA_Tutorial_State_Policy::sanitize([
    'version' => 1,
    'tutorials' => [
        $tutorial_id => [
            'status' => 'skipped',
            'current_step_id' => null,
            'accepted_at' => null,
            'started_at' => null,
            'paused_at' => null,
            'skipped_at' => '2026-07-04 08:00:00',
            'completed_at' => null,
            'updated_at' => '2026-07-04 08:00:00',
        ],
    ],
]);
$sanitized_skipped_tutorial = $sanitized_skipped['tutorials'][$tutorial_id] ?? [];
ac_assert('sanitize preserves skipped', ($sanitized_skipped_tutorial['status'] ?? '') === 'skipped');
ac_assert('sanitize preserves skipped_at', ($sanitized_skipped_tutorial['skipped_at'] ?? '') === '2026-07-04 08:00:00');

$effective_skipped = AA_Tutorial_State_Policy::get_effective_tutorial($sanitized_skipped, $tutorial_id);
ac_assert('effective skipped status', ($effective_skipped['status'] ?? '') === 'skipped');

$skip_from_in_progress = AA_Tutorial_State_Policy::apply_transition($state, $tutorial_id, [
    'status' => 'skipped',
    'current_step_id' => null,
]);
ac_assert('reject in_progress -> skipped', ($skip_from_in_progress['ok'] ?? true) === false);

$skip_from_paused = AA_Tutorial_State_Policy::apply_transition($paused_state, $tutorial_id, [
    'status' => 'skipped',
    'current_step_id' => null,
]);
ac_assert('reject paused -> skipped', ($skip_from_paused['ok'] ?? true) === false);

$skip_from_completed = AA_Tutorial_State_Policy::apply_transition($completed_state, $tutorial_id, [
    'status' => 'skipped',
    'current_step_id' => null,
]);
ac_assert('reject completed -> skipped', ($skip_from_completed['ok'] ?? true) === false);

$skipped_state = $skipped['state'] ?? AA_Tutorial_State_Policy::empty_state();
$skip_again = AA_Tutorial_State_Policy::apply_transition($skipped_state, $tutorial_id, [
    'status' => 'in_progress',
    'current_step_id' => 'open_sidebar',
]);
ac_assert('reject skipped -> in_progress', ($skip_again['ok'] ?? true) === false);

$skip_terminal = AA_Tutorial_State_Policy::apply_transition($skipped_state, $tutorial_id, [
    'status' => 'skipped',
    'current_step_id' => null,
]);
ac_assert('reject skipped -> skipped', ($skip_terminal['ok'] ?? true) === false);

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
