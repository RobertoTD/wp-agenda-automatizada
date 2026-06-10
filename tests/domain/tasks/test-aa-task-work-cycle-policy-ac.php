<?php
/**
 * AC MC13O-H1/H2 — AA_Task_Work_Cycle_Policy.
 *
 * Ejecutar: php tests/domain/tasks/test-aa-task-work-cycle-policy-ac.php
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

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/domain/tasks/class-aa-task-work-cycle-policy.php';

$policy = new AA_Task_Work_Cycle_Policy();

ac_assert(
    '2026-06-10 10:30:00 cycles=1 → 2026-06-10 12:00:00',
    $policy->resolve_ignore_until('2026-06-10 10:30:00', 1) === '2026-06-10 12:00:00'
);
ac_assert(
    '2026-06-10 12:00:00 cycles=1 → 2026-06-11 12:00:00',
    $policy->resolve_ignore_until('2026-06-10 12:00:00', 1) === '2026-06-11 12:00:00'
);
ac_assert(
    '2026-06-10 15:00:00 cycles=1 → 2026-06-11 12:00:00',
    $policy->resolve_ignore_until('2026-06-10 15:00:00', 1) === '2026-06-11 12:00:00'
);
ac_assert(
    '2026-06-10 15:00:00 cycles=2 → 2026-06-12 12:00:00',
    $policy->resolve_ignore_until('2026-06-10 15:00:00', 2) === '2026-06-12 12:00:00'
);
ac_assert(
    'cycles=0 treated as 1',
    $policy->resolve_ignore_until('2026-06-10 10:30:00', 0) === '2026-06-10 12:00:00'
);
ac_assert(
    'negative cycles treated as 1',
    $policy->resolve_ignore_until('2026-06-10 10:30:00', -3) === '2026-06-10 12:00:00'
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
