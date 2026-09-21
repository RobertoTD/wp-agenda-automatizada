<?php
/**
 * AC — Frontera del Legacy Presentation Bridge de capabilities.
 *
 * Ejecutar: php tests/application/canonical/capabilities/test-canonical-capability-presentation-boundary-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 4);

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';

$total = 0;
$passed = 0;
$failed = [];

function presentation_boundary_assert(string $label, bool $ok): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . "\n";
        return;
    }
    $failed[] = $label;
    echo '[FAIL] ' . $label . "\n";
}

$bridge_files = [
    'includes/admin/ui/modules/canonical_shell/partials/record-card.php',
    'includes/admin/ui/modules/canonical_shell/index.php',
    'includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js',
];
$bridge_source = '';
$markers_present = true;
foreach ($bridge_files as $file) {
    $source = (string) file_get_contents($plugin_root . '/' . $file);
    $bridge_source .= "\n" . $source;
    $markers_present = $markers_present && strpos($source, 'Legacy Presentation Bridge') !== false;
}

$legacy_keys = ['amount', 'phone', 'whatsapp', 'email', 'images'];
$pending_migration_keys = ['completed'];
$allowed_direct_keys = array_merge($legacy_keys, $pending_migration_keys);
$registry = AA_Canonical_Capability_Registry_Bootstrap::build_registry();
$unexpected_direct_keys = [];

foreach ($registry->all() as $definition) {
    $key = $definition->key();
    $quoted_key = '/[\'\"]' . preg_quote($key, '/') . '[\'\"]/';
    if (preg_match($quoted_key, $bridge_source) === 1 && !in_array($key, $allowed_direct_keys, true)) {
        $unexpected_direct_keys[] = $key;
    }
}

presentation_boundary_assert('Los tres puntos del bridge están marcados explícitamente', $markers_present);
presentation_boundary_assert('La lista legacy permanece cerrada', $unexpected_direct_keys === []);
foreach ($legacy_keys as $key) {
    presentation_boundary_assert('La capability legacy ' . $key . ' permanece identificada en el bridge', preg_match('/[\'\"]' . preg_quote($key, '/') . '[\'\"]/', $bridge_source) === 1);
}
presentation_boundary_assert('Completed solo es objetivo transitorio de migración', preg_match('/[\'\"]completed[\'\"]/', $bridge_source) === 1);
presentation_boundary_assert('Postpone no entra al bridge', strpos($bridge_source, "'postpone'") === false && strpos($bridge_source, '"postpone"') === false);
presentation_boundary_assert('Event date no entra al bridge', strpos($bridge_source, "'event_date'") === false && strpos($bridge_source, '"event_date"') === false);

echo "\n{$passed}/{$total} assertions passed.\n";
if ($failed !== []) {
    exit(1);
}
