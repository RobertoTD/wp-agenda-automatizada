<?php
/**
 * AC — Dashboard runtime enqueue (learning-action-handlers path).
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
    'dashboard enqueues learning-action-handlers via learning/index.php anchor',
    is_string($index_php)
    && strpos($index_php, "plugin_dir_url(__DIR__ . '/../learning/index.php')") !== false
    && strpos($index_php, "'learning-action-handlers.js'") !== false
);

ac_assert(
    'dashboard does not use broken learning/ directory-only plugin_dir_url',
    is_string($index_php)
    && strpos($index_php, "plugin_dir_url(__DIR__ . '/../learning/')") === false
);

echo "\n{$passed}/{$total} passed\n";

if ($failed !== []) {
    exit(1);
}

exit(0);
