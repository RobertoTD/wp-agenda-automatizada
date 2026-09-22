<?php
/**
 * AC Test — AA_Canonical_Write_Binding_Registry (SB1-5A1).
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-write-binding-registry-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/tests/support/canonical/CanonicalFixtureWriteAdapter.php';

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

$registry = new AA_Canonical_Write_Binding_Registry();
$alpha = new CanonicalReadIdentity('finance');
$beta = new CanonicalReadIdentity('archive');
$adapter_alpha = new CanonicalFixtureWriteAdapter('finance');
$adapter_beta = new CanonicalFixtureWriteAdapter('archive');

$registry->register($alpha, $adapter_alpha);
$registry->register($beta, $adapter_beta);

ac_assert('Resolves alpha adapter', $registry->require($alpha) === $adapter_alpha);
ac_assert('Resolves beta adapter', $registry->require($beta) === $adapter_beta);

$dup = false;
$dup_msg = '';
try {
    $registry->register($alpha, $adapter_beta);
} catch (\LogicException $e) {
    $dup = true;
    $dup_msg = $e->getMessage();
}
ac_assert('Duplicate write throws', $dup);
ac_assert('Duplicate write tag', strpos($dup_msg, '[duplicate_write_binding]') !== false);
ac_assert('Original write binding preserved', $registry->require($alpha) === $adapter_alpha);

$missing = new CanonicalReadIdentity('catalog');
$threw = false;
try {
    $registry->require($missing);
} catch (CanonicalWriteBindingNotFound $e) {
    $threw = strpos($e->getMessage(), 'catalog') !== false;
}
ac_assert('Missing binding throws CanonicalWriteBindingNotFound', $threw);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
