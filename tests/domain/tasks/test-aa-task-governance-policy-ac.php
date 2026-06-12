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

$user_active = [
    'source_category' => 'user',
    'managed_by' => 'user',
    'status' => 'pending',
];
ac_assert(
    'user not archived can_archive',
    $policy->can_archive_task($user_active) === true
    && $policy->can_restore_task($user_active) === false
);
ac_assert(
    'user archived can_restore not archive',
    $policy->can_archive_task(array_merge($user_active, ['archived_at' => '2026-06-10 10:00:00'])) === false
    && $policy->can_restore_task(array_merge($user_active, ['archived_at' => '2026-06-10 10:00:00'])) === true
);
ac_assert(
    'user done not archived can_archive',
    $policy->can_archive_task(array_merge($user_active, ['status' => 'done'])) === true
);
ac_assert(
    'user done archived can_restore',
    $policy->can_restore_task([
        'source_category' => 'user',
        'managed_by' => 'user',
        'status' => 'done',
        'archived_at' => '2026-06-10 10:00:00',
    ]) === true
);
ac_assert(
    'missing archived_at treated as not archived',
    $policy->can_archive_task(['managed_by' => 'user']) === true
    && $policy->can_restore_task(['managed_by' => 'user']) === false
);
ac_assert(
    'agenda_app task cannot archive or restore',
    $policy->can_archive_task([
        'source_category' => 'agenda_app',
        'managed_by' => 'developer',
    ]) === false
    && $policy->can_restore_task([
        'source_category' => 'agenda_app',
        'managed_by' => 'developer',
        'archived_at' => '2026-06-10 10:00:00',
    ]) === false
);
ac_assert(
    'developer user category cannot archive',
    $policy->can_archive_task([
        'source_category' => 'user',
        'managed_by' => 'developer',
    ]) === false
);
ac_assert(
    'system managed task cannot archive',
    $policy->can_archive_task([
        'source_category' => 'user',
        'managed_by' => 'system',
    ]) === false
);

$user_delete_shape = [
    'source_category' => 'user',
    'managed_by' => 'user',
];
ac_assert(
    'user task can_delete',
    $policy->can_delete_task($user_delete_shape) === true
);
ac_assert(
    'user done task can_delete',
    $policy->can_delete_task(array_merge($user_delete_shape, ['status' => 'done'])) === true
);
ac_assert(
    'user archived task can_delete',
    $policy->can_delete_task(array_merge($user_delete_shape, ['archived_at' => '2026-06-10 10:00:00'])) === true
);
ac_assert(
    'agenda_app task cannot delete',
    $policy->can_delete_task([
        'source_category' => 'agenda_app',
        'managed_by' => 'developer',
    ]) === false
);
ac_assert(
    'developer user category cannot delete',
    $policy->can_delete_task([
        'source_category' => 'user',
        'managed_by' => 'developer',
    ]) === false
);
ac_assert(
    'system managed task cannot delete',
    $policy->can_delete_task([
        'source_category' => 'user',
        'managed_by' => 'system',
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
