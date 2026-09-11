<?php
/**
 * AC Test — Canonical Empty Shell & Lightweight Layout (post LEGACY-X).
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
if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return $url;
    }
}
if (!function_exists('aa_asset_url')) {
    function aa_asset_url(string $relative_path): string {
        return 'https://example.com/plugin/' . ltrim($relative_path, '/');
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string {
        return 'nonce-' . $action;
    }
}
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can(string $cap): bool {
        return $cap === 'manage_options';
    }
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
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

ac_assert(
    'Módulo legacy modules/canonical ausente',
    !is_dir($plugin_root . '/includes/admin/ui/modules/canonical')
);
ac_assert(
    'Fallback legacy _fallback.php ausente',
    !is_file($plugin_root . '/includes/admin/ui/modules/canonical/_fallback.php')
);

$shell_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
ac_assert('Shell empty state: Sin contenedores', strpos($shell_src, 'Sin contenedores') !== false);
ac_assert(
    'Shell empty copy universal',
    strpos($shell_src, 'Aún no hay contenedores en este tipo de registro.') !== false
);
ac_assert('Shell empty state: Sin listas (alcance general)', strpos($shell_src, 'Sin listas') !== false);
ac_assert('Shell empty state: Sin tipos de registros', strpos($shell_src, 'Sin tipos de registros') !== false);

$registry = AA_Canonical_Core_Bootstrap::build_registry();
$aa_canonical_family = $registry->family('finance');
$aa_shell_route_state = 'resolved';
$aa_shell_route_message = 'Ruta canónica resuelta.';
$aa_shell_view = [
    'shell_view' => 'containers',
    'family_label' => $aa_canonical_family->label(),
    'qualified_key' => $aa_canonical_family->key(),
    'read_state' => 'empty',
    'lists_scope' => '',
    'available_families' => [],
    'is_preview' => false,
    'preview_banner' => '',
    'preview_enabled' => false,
    'preview_url' => '',
    'items_view' => [],
    'page' => 1,
    'total_pages' => 1,
    'has_previous' => false,
    'has_next' => false,
    'prev_url' => '',
    'next_url' => '',
    'create_family_key' => $aa_canonical_family->key(),
    'capability_contributions' => [],
];

ob_start();
require $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php';
$html = ob_get_clean();

ac_assert('Empty shell root id present', strpos($html, 'id="aa-canonical-shell-root"') !== false);
ac_assert('Empty shell shows family label', strpos($html, 'Finanzas') !== false);
ac_assert('Empty shell shows Sin contenedores', strpos($html, 'Sin contenedores') !== false);
ac_assert(
    'Empty shell universal copy',
    strpos($html, 'Aún no hay contenedores en este tipo de registro.') !== false
);
ac_assert('Empty shell not pending', strpos($html, 'Lectura pendiente') === false);
ac_assert('Empty shell has no preview CTA without constant', strpos($html, 'Ver demostración del shell') === false);

// Inspect canonical-layout.php file content
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
