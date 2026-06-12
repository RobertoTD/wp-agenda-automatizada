<?php
/**
 * AC — AA_Task_List_Governance_Policy (editabilidad y archivado de listas).
 *
 * Ejecutar: php tests/domain/tasks/test-aa-task-list-governance-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-list-governance-policy.php';

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

$policy = new AA_Task_List_Governance_Policy();

$user_active = [
    'status' => 'active',
    'source_category' => 'user',
    'managed_by' => 'user',
];

ac_assert(
    'active user list is editable',
    $policy->can_edit_list($user_active) === true
);
ac_assert(
    'active user list is archivable',
    $policy->can_archive_list($user_active) === true
);
ac_assert(
    'active user list can restore archived tasks',
    $policy->can_restore_archived_tasks($user_active) === true
);
ac_assert(
    'missing metadata defaults to editable archivable user list',
    $policy->can_edit_list([]) === true
    && $policy->can_archive_list([]) === true
    && $policy->can_restore_archived_tasks([]) === true
);

$user_archived = [
    'status' => 'archived',
    'source_category' => 'user',
    'managed_by' => 'user',
];

ac_assert(
    'archived user list is not editable',
    $policy->can_edit_list($user_archived) === false
);
ac_assert(
    'archived user list is not archivable',
    $policy->can_archive_list($user_archived) === false
);
ac_assert(
    'archived user list cannot restore archived tasks',
    $policy->can_restore_archived_tasks($user_archived) === false
);

$agenda_app = [
    'status' => 'active',
    'source_category' => 'agenda_app',
    'managed_by' => 'developer',
    'origin_key' => 'learning.recommendations',
];

ac_assert(
    'agenda_app developer list is not editable',
    $policy->can_edit_list($agenda_app) === false
);
ac_assert(
    'agenda_app developer list is not archivable',
    $policy->can_archive_list($agenda_app) === false
);
ac_assert(
    'agenda_app developer list cannot restore archived tasks',
    $policy->can_restore_archived_tasks($agenda_app) === false
);

$developer_system = [
    'status' => 'active',
    'source_category' => 'system',
    'managed_by' => 'developer',
];

ac_assert(
    'developer system list is not editable',
    $policy->can_edit_list($developer_system) === false
);
ac_assert(
    'developer system list is not archivable',
    $policy->can_archive_list($developer_system) === false
);
ac_assert(
    'developer system list cannot restore archived tasks',
    $policy->can_restore_archived_tasks($developer_system) === false
);

$user_developer_managed = [
    'status' => 'active',
    'source_category' => 'user',
    'managed_by' => 'developer',
];

ac_assert(
    'user source_category with managed_by developer is not editable',
    $policy->can_edit_list($user_developer_managed) === false
);
ac_assert(
    'user source_category with managed_by developer is not archivable',
    $policy->can_archive_list($user_developer_managed) === false
);
ac_assert(
    'user source_category with managed_by developer cannot restore archived tasks',
    $policy->can_restore_archived_tasks($user_developer_managed) === false
);

$agenda_user_managed = [
    'status' => 'active',
    'source_category' => 'agenda_app',
    'managed_by' => 'user',
];

ac_assert(
    'agenda_app source_category is not editable even if managed_by user',
    $policy->can_edit_list($agenda_user_managed) === false
);
ac_assert(
    'agenda_app source_category is not archivable even if managed_by user',
    $policy->can_archive_list($agenda_user_managed) === false
);
ac_assert(
    'agenda_app source_category cannot restore archived tasks even if managed_by user',
    $policy->can_restore_archived_tasks($agenda_user_managed) === false
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
