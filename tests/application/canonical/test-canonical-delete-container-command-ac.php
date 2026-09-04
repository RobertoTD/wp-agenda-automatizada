<?php
/**
 * AC Test — CanonicalDeleteContainerCommand (SB1-5B6).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-delete-container-command-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteContainerCommand.php';

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

try {
    new CanonicalDeleteContainerCommand(0);
    ac_assert('ID 0 throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('ID 0 throws', strpos($e->getMessage(), '[invalid_container_id]') === 0);
}

try {
    new CanonicalDeleteContainerCommand(-1);
    ac_assert('Negative ID throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Negative ID throws', strpos($e->getMessage(), '[invalid_container_id]') === 0);
}

$ok = new CanonicalDeleteContainerCommand(7);
ac_assert('Valid id preserved', $ok->container_id() === 7);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
