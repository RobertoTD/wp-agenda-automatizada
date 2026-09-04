<?php
/**
 * AC Test — CanonicalDeleteRecordCommand (SB1-5B4).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-delete-record-command-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteRecordCommand.php';

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
    new CanonicalDeleteRecordCommand(0, 1);
    ac_assert('Zero container throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Zero container throws', strpos($e->getMessage(), '[invalid_container_id]') === 0);
}

try {
    new CanonicalDeleteRecordCommand(-2, 1);
    ac_assert('Negative container throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Negative container throws', strpos($e->getMessage(), '[invalid_container_id]') === 0);
}

try {
    new CanonicalDeleteRecordCommand(1, 0);
    ac_assert('Zero record throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Zero record throws', strpos($e->getMessage(), '[invalid_record_id]') === 0);
}

try {
    new CanonicalDeleteRecordCommand(1, -5);
    ac_assert('Negative record throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Negative record throws', strpos($e->getMessage(), '[invalid_record_id]') === 0);
}

$ok = new CanonicalDeleteRecordCommand(12, 34);
ac_assert('Valid ids preserved', $ok->container_id() === 12 && $ok->record_id() === 34);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
