<?php
/**
 * AC Test — Canonical read binding bootstrap (PCU-5B supersede SB1-4B).
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-read-binding-bootstrap-ac.php
 *
 * Cobertura funcional amplia: test-aa-canonical-universal-binding-bootstrap-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

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

$boot = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php');
$composer = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php');
$write = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php');

ac_assert('Bootstrap sin AA_Finance_Canonical_Read_Adapter', strpos($boot, 'AA_Finance_Canonical_Read_Adapter') === false);
ac_assert('Bootstrap sin finance/class-aa-finance', strpos($boot, 'finance/class-aa-finance-canonical-read-adapter.php') === false);
ac_assert('Bootstrap usa Relational Read', strpos($boot, 'AA_Canonical_Relational_Read_Adapter') !== false);
ac_assert('Bootstrap usa enablement Use Case', strpos($boot, 'ReadCanonicalFamilyEnablementUseCase') !== false);
ac_assert('Composer delega a read bootstrap', strpos($composer, 'AA_Canonical_Read_Binding_Bootstrap::register_productive') !== false);
ac_assert('Composer no invoca write bootstrap', strpos($composer, 'Write_Binding_Bootstrap') === false);
ac_assert('Write bootstrap archivo presente', $write !== '' && strpos($write, 'AA_Canonical_Relational_Write_Adapter') !== false);
ac_assert('Write bootstrap sin Finance', strpos($write, 'AA_Finance') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
