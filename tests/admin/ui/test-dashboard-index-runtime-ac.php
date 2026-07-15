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

echo "\n{$passed}/{$total} passed\n";

if ($failed !== []) {
    exit(1);
}

exit(0);
