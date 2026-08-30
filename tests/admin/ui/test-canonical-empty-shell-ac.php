<?php
/**
 * AC Test — Canonical Empty Shell & Lightweight Layout.
 *
 * Ejecutar: php tests/admin/ui/test-canonical-empty-shell-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';

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

$registry = AA_Canonical_Core_Bootstrap::build_registry();
$aa_canonical_family = $registry->family('finance');
$aa_canonical_variant = $registry->variant('finance', 'general');

// 1. Render canonical fallback template (_fallback.php)
ob_start();
require $plugin_root . '/includes/admin/ui/modules/canonical/_fallback.php';
$html = ob_get_clean();

ac_assert('Fallback renders Finanzas label', strpos($html, 'Finanzas') !== false);
ac_assert('Fallback renders General variant', strpos($html, 'General') !== false);
ac_assert('Fallback sets data-aa-page-title="Finanzas"', strpos($html, 'data-aa-page-title="Finanzas"') !== false);
ac_assert('Fallback sets data-aa-canonical-family="finance"', strpos($html, 'data-aa-canonical-family="finance"') !== false);
ac_assert('Fallback sets data-aa-canonical-variant="general"', strpos($html, 'data-aa-canonical-variant="general"') !== false);
ac_assert('Fallback sets data-aa-canonical-qualified="finance.general"', strpos($html, 'data-aa-canonical-qualified="finance.general"') !== false);
ac_assert('Fallback contains no form tag', strpos($html, '<form') === false);
ac_assert('Fallback contains no input fields', strpos($html, '<input') === false);
ac_assert('Fallback contains no buttons', strpos($html, '<button') === false);
ac_assert('Fallback contains no script tags', strpos($html, '<script') === false);

// 2. Inspect canonical-layout.php file content
$layout_source = file_get_contents($plugin_root . '/includes/admin/ui/shared/canonical-layout.php');

ac_assert('Canonical layout does not use $wpdb', strpos($layout_source, '$wpdb') === false);
ac_assert('Canonical layout does not query aa_reservas', strpos($layout_source, 'aa_reservas') === false);
ac_assert('Canonical layout does not include appointments-modal', strpos($layout_source, 'appointments-modal') === false);
ac_assert('Canonical layout does not include fastappointment', strpos($layout_source, 'fastappointment') === false);
ac_assert('Canonical layout does not include aichat', strpos($layout_source, 'aichat') === false);
ac_assert('Canonical layout does not include crearcliente', strpos($layout_source, 'crearcliente') === false);
ac_assert('Canonical layout does not publish wpaa_vars', strpos($layout_source, 'wpaa_vars') === false);
ac_assert('Canonical layout does not publish AA_CLIENTS_NONCES', strpos($layout_source, 'AA_CLIENTS_NONCES') === false);
ac_assert('Canonical layout does not load flatpickr', strpos($layout_source, 'flatpickr') === false);
ac_assert('Canonical layout sets AA_SHELL_ACCESS_DATA', strpos($layout_source, 'AA_SHELL_ACCESS_DATA') !== false);
ac_assert('Canonical layout includes sidebar.js and shellAccessProjection.js', strpos($layout_source, 'sidebar.js') !== false && strpos($layout_source, 'shellAccessProjection.js') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
