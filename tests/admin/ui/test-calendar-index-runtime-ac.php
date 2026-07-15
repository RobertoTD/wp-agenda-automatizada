<?php
/**
 * AC — Calendar runtime enqueue (PWA install first opportunity).
 *
 * Ejecutar: php tests/admin/ui/test-calendar-index-runtime-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$index_php = file_get_contents($plugin_root . '/includes/admin/ui/modules/calendar/index.php');

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

ac_assert('calendar index.php readable', $index_php !== false);

ac_assert(
    'calendar enqueues learning-action-handlers.js',
    is_string($index_php)
    && strpos($index_php, 'learning-action-handlers.js') !== false
);

ac_assert(
    'calendar enqueues pwa-install-first-opportunity.js',
    is_string($index_php)
    && strpos($index_php, 'pwa-install-first-opportunity.js') !== false
);

ac_assert(
    'calendar loads learning-action-handlers.js before pwa-install-first-opportunity.js',
    is_string($index_php)
    && strpos($index_php, 'learning-action-handlers.js') < strpos($index_php, 'pwa-install-first-opportunity.js')
);

ac_assert(
    'calendar learning-action-handlers.js uses defer',
    is_string($index_php)
    && (bool) preg_match('/\$learning_handlers_js.*defer/m', $index_php)
);

ac_assert(
    'calendar pwa-install-first-opportunity.js uses defer',
    is_string($index_php)
    && (bool) preg_match('/\$pwa_install_first_opportunity_js.*defer/m', $index_php)
);

ac_assert(
    'calendar resolves learning-action-handlers via learning/index.php anchor',
    is_string($index_php)
    && strpos($index_php, "plugin_dir_url(__DIR__ . '/../learning/index.php')") !== false
);

ac_assert(
    'calendar resolves pwa-install-first-opportunity via dashboard/index.php anchor',
    is_string($index_php)
    && strpos($index_php, "plugin_dir_url(__DIR__ . '/../dashboard/index.php')") !== false
);

echo "\n{$passed}/{$total} passed\n";

if ($failed !== []) {
    exit(1);
}

exit(0);
