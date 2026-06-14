<?php
/**
 * AC MC4 — Sprint write boundary (solo flujo ejecutivo).
 *
 * Ejecutar: php tests/application/executive/test-executive-sprint-write-boundary-ac.php
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

$repo_token = 'ExecutiveSprintStateRepository';

$tasks_ajax = file_get_contents($plugin_root . '/includes/http/ajax/TasksAjax.php');
$lists_ajax = file_get_contents($plugin_root . '/includes/http/ajax/ExecutableListsAjax.php');
$change_status = file_get_contents($plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php');
$dismiss_uc = file_get_contents($plugin_root . '/includes/application/tasks/RecordTaskDismissSignalUseCase.php');
$record_exec = file_get_contents($plugin_root . '/includes/application/executive/RecordExecutiveActionUseCase.php');
$get_exec = file_get_contents($plugin_root . '/includes/application/executive/GetExecutiveProposalUseCase.php');
$coordinator = file_get_contents($plugin_root . '/includes/admin/ui/modules/learning/executable-actions-coordinator.js');

ac_assert('TasksAjax file readable', $tasks_ajax !== false);
ac_assert('ExecutableListsAjax file readable', $lists_ajax !== false);
ac_assert('TasksAjax does not reference sprint repository', strpos((string) $tasks_ajax, $repo_token) === false);
ac_assert('ExecutableListsAjax does not reference sprint repository', strpos((string) $lists_ajax, $repo_token) === false);
ac_assert('ChangeTaskStatusUseCase does not reference sprint repository', strpos((string) $change_status, $repo_token) === false);
ac_assert('RecordTaskDismissSignalUseCase does not reference sprint repository', strpos((string) $dismiss_uc, $repo_token) === false);
ac_assert('executable-actions-coordinator does not reference sprint repository', strpos((string) $coordinator, $repo_token) === false);
ac_assert('RecordExecutiveActionUseCase references sprint repository', strpos((string) $record_exec, $repo_token) !== false);
ac_assert('GetExecutiveProposalUseCase references sprint repository', strpos((string) $get_exec, $repo_token) !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
