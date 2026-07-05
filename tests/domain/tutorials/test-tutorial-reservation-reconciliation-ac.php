<?php
/**
 * AC — AA_Tutorial_State_Policy::reconcile_for_reservation_existence().
 *
 * Ejecutar: php tests/domain/tutorials/test-tutorial-reservation-reconciliation-ac.php
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

$no_reservations = AA_Tutorial_State_Policy::reconcile_for_reservation_existence($empty, $tutorial_id, false);
ac_assert('exists false + absent no change', ($no_reservations['changed'] ?? true) === false);

$in_progress_state = [
    'version' => 1,
    'tutorials' => [
        $tutorial_id => [
            'status' => 'in_progress',
            'current_step_id' => 'calendar_overview',
            'accepted_at' => '2026-07-01 10:00:00',
            'started_at' => '2026-07-01 10:00:00',
            'paused_at' => null,
            'completed_at' => null,
            'updated_at' => '2026-07-01 10:05:00',
        ],
    ],
];

$no_reservations_progress = AA_Tutorial_State_Policy::reconcile_for_reservation_existence(
    $in_progress_state,
    $tutorial_id,
    false
);
ac_assert('exists false + in_progress no change', ($no_reservations_progress['changed'] ?? true) === false);

$paused_state = [
    'version' => 1,
    'tutorials' => [
        $tutorial_id => [
            'status' => 'paused',
            'current_step_id' => 'calendar_overview',
            'accepted_at' => '2026-07-01 10:00:00',
            'started_at' => '2026-07-01 10:00:00',
            'paused_at' => '2026-07-02 12:00:00',
            'completed_at' => null,
            'updated_at' => '2026-07-02 12:00:00',
        ],
    ],
];

$no_reservations_paused = AA_Tutorial_State_Policy::reconcile_for_reservation_existence(
    $paused_state,
    $tutorial_id,
    false
);
ac_assert('exists false + paused no change', ($no_reservations_paused['changed'] ?? true) === false);

$completed_state = [
    'version' => 1,
    'tutorials' => [
        $tutorial_id => [
            'status' => 'completed',
            'current_step_id' => null,
            'accepted_at' => '2026-07-01 10:00:00',
            'started_at' => '2026-07-01 10:00:00',
            'paused_at' => null,
            'completed_at' => '2026-07-03 09:00:00',
            'updated_at' => '2026-07-03 09:00:00',
        ],
    ],
];

$completed_again = AA_Tutorial_State_Policy::reconcile_for_reservation_existence(
    $completed_state,
    $tutorial_id,
    true
);
ac_assert('exists true + completed no change', ($completed_again['changed'] ?? true) === false);

$absent_reconciled = AA_Tutorial_State_Policy::reconcile_for_reservation_existence($empty, $tutorial_id, true);
$absent_tutorial = $absent_reconciled['state']['tutorials'][$tutorial_id] ?? [];
ac_assert('exists true + absent reconciles', ($absent_reconciled['changed'] ?? false) === true);
ac_assert('absent status completed', ($absent_tutorial['status'] ?? '') === 'completed');
ac_assert('absent current_step_id null', array_key_exists('current_step_id', $absent_tutorial) && $absent_tutorial['current_step_id'] === null);
ac_assert('absent accepted_at null', array_key_exists('accepted_at', $absent_tutorial) && $absent_tutorial['accepted_at'] === null);
ac_assert('absent started_at null', array_key_exists('started_at', $absent_tutorial) && $absent_tutorial['started_at'] === null);
ac_assert('absent paused_at null', array_key_exists('paused_at', $absent_tutorial) && $absent_tutorial['paused_at'] === null);

$progress_reconciled = AA_Tutorial_State_Policy::reconcile_for_reservation_existence(
    $in_progress_state,
    $tutorial_id,
    true
);
$progress_tutorial = $progress_reconciled['state']['tutorials'][$tutorial_id] ?? [];
ac_assert('in_progress reconciles', ($progress_reconciled['changed'] ?? false) === true);
ac_assert('in_progress status completed', ($progress_tutorial['status'] ?? '') === 'completed');
ac_assert('in_progress preserves accepted_at', ($progress_tutorial['accepted_at'] ?? '') === '2026-07-01 10:00:00');
ac_assert('in_progress preserves started_at', ($progress_tutorial['started_at'] ?? '') === '2026-07-01 10:00:00');
ac_assert('in_progress clears current_step_id', array_key_exists('current_step_id', $progress_tutorial) && $progress_tutorial['current_step_id'] === null);

$paused_reconciled = AA_Tutorial_State_Policy::reconcile_for_reservation_existence(
    $paused_state,
    $tutorial_id,
    true
);
$paused_tutorial = $paused_reconciled['state']['tutorials'][$tutorial_id] ?? [];
ac_assert('paused reconciles', ($paused_reconciled['changed'] ?? false) === true);
ac_assert('paused preserves paused_at', ($paused_tutorial['paused_at'] ?? '') === '2026-07-02 12:00:00');

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
