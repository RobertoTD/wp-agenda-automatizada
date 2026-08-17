<?php
/**
 * AC — Ciclo D: ruta, gate, sidebar y esqueleto del módulo Expedientes.
 *
 * Ejecutar: php tests/admin/ui/test-expedientes-module-ac.php
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

function ac_read(string $relative): string {
    global $plugin_root;
    $src = file_get_contents($plugin_root . '/' . $relative);
    return is_string($src) ? $src : '';
}

$router = ac_read('includes/admin/ui/index.php');
$sidebar = ac_read('includes/admin/ui/shared/sidebar.php');
$module = ac_read('includes/admin/ui/modules/expedientes/index.php');
$clients_index = ac_read('includes/admin/ui/modules/clients/index.php');
$layout = ac_read('includes/admin/ui/shared/layout.php');
$header = ac_read('includes/admin/ui/shared/header.php');

ac_assert('módulo index existe', $module !== '');
ac_assert('whitelist incluye expedientes', strpos($router, "'expedientes'") !== false);
ac_assert(
    'gate cubre module=expedientes y clients/expediente en una rama',
    preg_match(
        "/\\\$active_module === 'expedientes'[\s\S]{0,120}\\\$active_module === 'clients' && \\\$view_raw === 'expediente'/",
        $router
    ) === 1
);
ac_assert(
    'sigue habiendo exactamente 2 execute() síncronos',
    substr_count($router, 'ResolveShellAccessUseCase())->execute()') === 2
);
ac_assert(
    'gate niega con wp_die 403',
    preg_match(
        "/active_module === 'expedientes'[\s\S]{0,500}wp_die\('Acceso denegado'[\s\S]{0,120}403/",
        $router
    ) === 1
);
ac_assert(
    'gate antes de layout.php',
    strpos($router, "\$active_module === 'expedientes'") < strpos($router, 'shared/layout.php')
);

ac_assert(
    'sidebar Clientes apunta a module=clients',
    strpos($sidebar, 'aa_iframe_content&module=clients') !== false
    && strpos($sidebar, 'data-aa-nav-module="clients"') !== false
    && strpos($sidebar, '>Clientes</span>') !== false
);
ac_assert(
    'sidebar Expedientes apunta a module=expedientes',
    strpos($sidebar, 'aa_iframe_content&module=expedientes') !== false
    && strpos($sidebar, 'data-aa-nav-module="expedientes"') !== false
    && strpos($sidebar, '>Expedientes</span>') !== false
);
ac_assert(
    'sidebar Expedientes va después de Clientes',
    strpos($sidebar, 'module=clients') < strpos($sidebar, 'module=expedientes')
    && strpos($sidebar, '>Clientes</span>') < strpos($sidebar, '>Expedientes</span>')
);
ac_assert(
    'sidebar ya no reutiliza Clientes como Expedientes',
    strpos($sidebar, 'reutiliza module=clients') === false
);
ac_assert(
    'vista legacy sigue en clients',
    strpos($clients_index, "'expediente'") !== false
    && strpos($clients_index, 'aa-expediente-root') !== false
    && strpos($clients_index, "admin_url('admin-post.php?action=aa_iframe_content&module=clients')") !== false
);

ac_assert('título de página Expedientes', strpos($module, 'data-aa-page-title="Expedientes"') !== false);
ac_assert('root aa-expedientes-root', strpos($module, 'id="aa-expedientes-root"') !== false);
ac_assert('list root propio', strpos($module, 'id="aa-expedientes-list-root"') !== false);
ac_assert('grid aa-expedientes-grid', strpos($module, 'id="aa-expedientes-grid"') !== false);
ac_assert('no reutiliza aa-expediente-root legacy', strpos($module, 'aa-expediente-root') === false);
ac_assert('emite AA_EXPEDIENTES_DATA', strpos($module, 'window.AA_EXPEDIENTES_DATA') !== false);
ac_assert('emite ajaxUrl', strpos($module, 'ajaxUrl:') !== false);
ac_assert('emite nonce aa_expedientes_nonce', strpos($module, 'aa_expedientes_nonce') !== false);
ac_assert('emite action list', strpos($module, 'aa_list_expedientes') !== false);
ac_assert('emite action create', strpos($module, 'aa_create_expediente') !== false);
ac_assert('emite moduleBaseUrl', strpos($module, 'moduleBaseUrl') !== false);
ac_assert('sin empty state estático', strpos($module, 'Aún no hay expedientes') === false);
ac_assert('sin JS de listado', strpos($module, 'expedientes-module.js') === false && strpos($module, 'fetch(') === false);
ac_assert('sin FAB', strpos($module, 'fab') === false && strpos($module, 'Nuevo expediente') === false);
ac_assert('sin modal', stripos($module, 'modal') === false);
ac_assert('sin $wpdb', strpos($module, '$wpdb') === false);
ac_assert('layout no encola JS de expedientes padre', strpos($layout, 'expedientes-module.js') === false);
ac_assert('header no añade tools de expedientes padre', strpos($header, 'aa-expedientes-area-tools') === false);

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'nonce-' . $action;
    }
}

if (!class_exists('ExpedientesAjax')) {
    final class ExpedientesAjax {
        public const ACTION_LIST = 'aa_list_expedientes';
        public const ACTION_CREATE = 'aa_create_expediente';
        public const NONCE_ACTION = 'aa_expedientes_nonce';
    }
}

ob_start();
include $plugin_root . '/includes/admin/ui/modules/expedientes/index.php';
$rendered = ob_get_clean();

ac_assert('runtime emite AA_EXPEDIENTES_DATA', strpos($rendered, 'AA_EXPEDIENTES_DATA') !== false);
ac_assert('runtime nonce del módulo', strpos($rendered, 'nonce-aa_expedientes_nonce') !== false);
ac_assert('runtime list action', strpos($rendered, 'aa_list_expedientes') !== false);
ac_assert('runtime create action', strpos($rendered, 'aa_create_expediente') !== false);
ac_assert('runtime ajaxUrl admin-ajax.php', strpos($rendered, 'admin-ajax.php') !== false);
ac_assert('runtime moduleBaseUrl module=expedientes', strpos($rendered, 'module=expedientes') !== false);
ac_assert('runtime grid vacío', preg_match('/id="aa-expedientes-grid"[^>]*>\s*<\/div>/', $rendered) === 1);
ac_assert('runtime título Expedientes', strpos($rendered, 'data-aa-page-title="Expedientes"') !== false);
ac_assert('runtime sin empty copy', strpos($rendered, 'Aún no hay') === false);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
