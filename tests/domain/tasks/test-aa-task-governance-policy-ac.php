<?php
/**
 * AC — AA_Task_Governance_Policy (editabilidad de tareas).
 *
 * Ejecutar: php tests/domain/tasks/test-aa-task-governance-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-governance-policy.php';

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

$policy = new AA_Task_Governance_Policy();

ac_assert(
    'managed_by=user task is editable',
    $policy->can_edit_task(['managed_by' => 'user']) === true
);
ac_assert(
    'missing managed_by defaults to editable user task',
    $policy->can_edit_task([]) === true
);
ac_assert(
    'managed_by=developer task is not editable',
    $policy->can_edit_task(['managed_by' => 'developer']) === false
);
ac_assert(
    'managed_by=system task is not editable',
    $policy->can_edit_task(['managed_by' => 'system']) === false
);
ac_assert(
    'managed_by=DEVELOPER normalized to not editable',
    $policy->can_edit_task(['managed_by' => 'DEVELOPER']) === false
);
ac_assert(
    'agenda_app developer task shape is not editable',
    $policy->can_edit_task([
        'source_category' => 'agenda_app',
        'managed_by' => 'developer',
        'origin_key' => 'install_pwa',
    ]) === false
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
