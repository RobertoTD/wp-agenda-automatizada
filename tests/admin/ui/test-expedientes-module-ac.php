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
ac_assert('carga JS solo en el módulo', strpos($module, 'expedientes-module.js') !== false);
ac_assert('PHP del módulo no hace fetch', strpos($module, 'fetch(') === false);
ac_assert('buscador con placeholder propio', strpos($module, 'Buscar expediente por nombre') !== false
    && strpos($module, 'id="aa-expedientes-search"') !== false);
ac_assert('action bar propia', strpos($module, 'id="aa-expedientes-action-bar"') !== false);
ac_assert('paginador propio', strpos($module, 'id="aa-expedientes-pagination"') !== false
    && strpos($module, 'id="aa-expedientes-prev"') !== false
    && strpos($module, 'id="aa-expedientes-next"') !== false);
ac_assert('status live region', strpos($module, 'id="aa-expedientes-status"') !== false);
ac_assert('FAB propio en módulo expedientes', strpos($module, 'id="aa-expedientes-new-expediente"') !== false
    && strpos($module, 'data-expedientes-tool="create-expediente"') !== false
    && strpos($module, 'Nuevo expediente') !== false);
ac_assert('FAB expedientes no reutiliza ids de clientes', strpos($module, 'aa-clients-new-client') === false
    && strpos($module, 'data-clients-tool="create-client"') === false);
ac_assert('clientes no incluye FAB de expedientes', strpos($clients_index, 'aa-expedientes-new-expediente') === false);
ac_assert('encola modal create JS', strpos($module, 'expediente-create-modal.js') !== false);
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
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.test/wp-content/plugins/wp-agenda-automatizada/includes/admin/ui/modules/expedientes/';
    }
}
if (!function_exists('esc_url')) {
    function esc_url($url) {
        return $url;
    }
}
if (!defined('AA_PLUGIN_VERSION')) {
    define('AA_PLUGIN_VERSION', 'test');
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
ac_assert('runtime encola expedientes-module.js', strpos($rendered, 'expedientes-module.js') !== false);
ac_assert('runtime encola expediente-create-modal.js', strpos($rendered, 'expediente-create-modal.js') !== false);
ac_assert('runtime FAB visible', strpos($rendered, 'id="aa-expedientes-new-expediente"') !== false);
ac_assert('runtime buscador visible', strpos($rendered, 'id="aa-expedientes-search"') !== false
    && strpos($rendered, 'Buscar expediente por nombre') !== false);

$css = ac_read('includes/admin/ui/assets/css/admin.source.css');
$js = ac_read('includes/admin/ui/modules/expedientes/expedientes-module.js');
$createJs = ac_read('includes/admin/ui/modules/expedientes/expediente-create-modal.js');
ac_assert('CSS scope #aa-expedientes-grid', strpos($css, '#aa-expedientes-grid') !== false);
ac_assert('CSS action bar propia', strpos($css, '.aa-expedientes-action-bar') !== false
    && strpos($css, '.aa-expedientes-search-input') !== false);
ac_assert('JS no usa #aa-clients-grid', strpos($js, '#aa-clients-grid') === false
    && strpos($js, 'aa-clients-search') === false);
ac_assert('JS no envía per_page/limit/offset/blog_id', strpos($js, "append('per_page'") === false
    && strpos($js, "append('limit'") === false
    && strpos($js, "append('offset'") === false
    && strpos($js, "append('blog_id'") === false);
ac_assert('JS envía action nonce query page', strpos($js, "append('action'") !== false
    && strpos($js, "append('_wpnonce'") !== false
    && strpos($js, "append('query'") !== false
    && strpos($js, "append('page'") !== false);
ac_assert('JS usa AbortController', strpos($js, 'AbortController') !== false);
ac_assert('JS debounce 300', strpos($js, 'SEARCH_DEBOUNCE_MS = 300') !== false);
ac_assert('JS cards data-aa-card', strpos($js, "setAttribute('data-aa-card'") !== false);
ac_assert('JS cards son enlace a detail', strpos($js, "searchParams.set('view', 'detail')") !== false
    && strpos($js, "searchParams.set('expediente_id'") !== false
    && strpos($js, 'data-aa-card-toggle') === false
    && strpos($js, 'aa-card-overlay') === false);
ac_assert('JS título por textContent', strpos($js, 'name.textContent = titleText') !== false);
ac_assert('JS module escucha aa:expediente:saved', strpos($js, 'aa:expediente:saved') !== false
    && strpos($js, 'resetSearchAndReload') !== false);
ac_assert('JS module FAB abre ExpedienteCreateModal', strpos($js, 'aa-expedientes-new-expediente') !== false
    && strpos($js, 'ExpedienteCreateModal.openCreate') !== false);
ac_assert('create modal usa AAAdmin.openModal', strpos($createJs, 'AAAdmin.openModal') !== false);
ac_assert('create modal emite aa:expediente:saved', strpos($createJs, 'aa:expediente:saved') !== false);
ac_assert('create modal no envía category_id ni client_id', strpos($createJs, "append('category_id'") === false
    && strpos($createJs, "append('client_id'") === false
    && strpos($createJs, "append('category_slug'") === false);
ac_assert('create modal maxlength 200/10000', strpos($createJs, 'maxLength = TITLE_MAX') !== false
    && strpos($createJs, 'DESCRIPTION_MAX = 10000') !== false);
ac_assert('crearcliente intacto', strpos(ac_read('includes/admin/ui/modals/crearcliente/crearcliente.js'), 'aa:client:saved') !== false);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
