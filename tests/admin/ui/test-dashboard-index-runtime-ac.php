<?php
/**
 * AC — Dashboard runtime enqueue (PWA install moved to Calendar).
 *
 * Ejecutar: php tests/admin/ui/test-dashboard-index-runtime-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$index_php = file_get_contents($plugin_root . '/includes/admin/ui/modules/dashboard/index.php');

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

ac_assert('dashboard index.php readable', $index_php !== false);

ac_assert(
    'dashboard does NOT enqueue learning-action-handlers.js',
    is_string($index_php)
    && strpos($index_php, 'learning-action-handlers.js') === false
);

ac_assert(
    'dashboard does NOT enqueue pwa-install-first-opportunity.js',
    is_string($index_php)
    && strpos($index_php, 'pwa-install-first-opportunity.js') === false
);

ac_assert(
    'dashboard enqueues pwaPushActivationService before pwa-notifications-first-opportunity',
    is_string($index_php)
    && strpos($index_php, 'pwaPushActivationService.js') !== false
    && strpos($index_php, 'pwaPushActivationService.js') < strpos($index_php, 'pwa-notifications-first-opportunity.js')
);

ac_assert(
    'dashboard still enqueues pwa-notifications-first-opportunity.js',
    is_string($index_php)
    && strpos($index_php, 'pwa-notifications-first-opportunity.js') !== false
);

ac_assert(
    'dashboard does NOT enqueue executiveProposalService.js',
    is_string($index_php)
    && strpos($index_php, 'executiveProposalService.js') === false
);

ac_assert(
    'dashboard does NOT enqueue executiveClientActionRunner.js',
    is_string($index_php)
    && strpos($index_php, 'executiveClientActionRunner.js') === false
);

ac_assert(
    'dashboard does NOT enqueue executiveProposalRenderer.js',
    is_string($index_php)
    && strpos($index_php, 'executiveProposalRenderer.js') === false
);

ac_assert(
    'dashboard does NOT enqueue taskCompletedToast.js',
    is_string($index_php)
    && strpos($index_php, 'taskCompletedToast.js') === false
);

ac_assert(
    'dashboard does NOT enqueue tasksService.js',
    is_string($index_php)
    && strpos($index_php, 'tasksService.js') === false
);

ac_assert(
    'dashboard does NOT emit AA_EXECUTIVE_PROPOSAL_DATA',
    is_string($index_php)
    && strpos($index_php, 'AA_EXECUTIVE_PROPOSAL_DATA') === false
);

ac_assert(
    'dashboard does NOT contain aa-dash-current-task',
    is_string($index_php)
    && strpos($index_php, 'aa-dash-current-task') === false
);

ac_assert(
    'dashboard does NOT contain Tarea actual heading',
    is_string($index_php)
    && strpos($index_php, 'Tarea actual') === false
);

echo "\n{$passed}/{$total} passed\n";

if ($failed !== []) {
    exit(1);
}

exit(0);
