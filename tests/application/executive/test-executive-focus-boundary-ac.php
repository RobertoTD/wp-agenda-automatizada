<?php
/**
 * AC MC5 — Executive focus boundary.
 *
 * Ejecutar: php tests/application/executive/test-executive-focus-boundary-ac.php
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

$change_focus = file_get_contents($plugin_root . '/includes/application/executive/ChangeExecutiveFocusUseCase.php');
$transition = file_get_contents($plugin_root . '/includes/application/executive/ExecutiveFocusTransitionService.php');

ac_assert('ChangeExecutiveFocusUseCase readable', $change_focus !== false);
ac_assert('ExecutiveFocusTransitionService readable', $transition !== false);
ac_assert(
    'ChangeExecutiveFocusUseCase no referencia ChangeTaskStatusUseCase',
    strpos((string) $change_focus, 'ChangeTaskStatusUseCase') === false
);
ac_assert(
    'ChangeExecutiveFocusUseCase no referencia RecordTaskDismissSignalUseCase',
    strpos((string) $change_focus, 'RecordTaskDismissSignalUseCase') === false
);
ac_assert(
    'ExecutiveFocusTransitionService no referencia ChangeTaskStatusUseCase',
    strpos((string) $transition, 'ChangeTaskStatusUseCase') === false
);
ac_assert(
    'ExecutiveFocusTransitionService no referencia RecordTaskDismissSignalUseCase',
    strpos((string) $transition, 'RecordTaskDismissSignalUseCase') === false
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
