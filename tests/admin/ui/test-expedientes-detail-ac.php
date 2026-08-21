<?php
/**
 * AC — Vista de detalle de expediente padre (view=detail).
 *
 * Ejecutar: php tests/admin/ui/test-expedientes-detail-ac.php
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
$module = ac_read('includes/admin/ui/modules/expedientes/index.php');
$detail = ac_read('includes/admin/ui/modules/expedientes/detail.php');
$header = ac_read('includes/admin/ui/shared/header.php');
$detail_partial = ac_read('includes/admin/ui/shared/expediente-record-readonly.php');
$js = ac_read('includes/admin/ui/modules/expedientes/expedientes-module.js');
$css = ac_read('includes/admin/ui/assets/css/admin.source.css');
$ajax = ac_read('includes/http/ajax/ExpedientesAjax.php');
$clients_index = ac_read('includes/admin/ui/modules/clients/index.php');
$clients_js = ac_read('includes/admin/ui/modules/clients/clients-module.js');
$registros_js = ac_read('includes/admin/ui/modules/clients/expediente-registros.js');
$main_js = ac_read('includes/admin/ui/assets/js/main.js');
$schema = ac_read('includes/infrastructure/wp/Schema.php');

$gate_full = strpos($router, 'AA_Shell_Access::ACCESS_FULL');
$get_uc = strpos($router, 'new GetExpedienteUseCase');
$list_children_uc = strpos($router, 'new ListExpedienteRegistrosUseCase');
$layout = strpos($router, "require __DIR__ . '/shared/layout.php'");

ac_assert('router resuelve view=detail', strpos($router, "\$view_raw === 'detail'") !== false);
ac_assert('gate full antes de GetExpedienteUseCase', $gate_full !== false && $get_uc !== false && $gate_full < $get_uc);
ac_assert('GetExpedienteUseCase antes de layout', $get_uc !== false && $layout !== false && $get_uc < $layout);
ac_assert(
    'listado hijos después del padre y antes del layout',
    $get_uc !== false && $list_children_uc !== false && $layout !== false
    && $get_uc < $list_children_uc
    && $list_children_uc < $layout
);
ac_assert(
    'siguen exactamente 2 execute() del gate',
    substr_count($router, 'ResolveShellAccessUseCase())->execute()') === 2
);
ac_assert('router usa records_page', strpos($router, "'records_page'") !== false);
ac_assert('router usa id resuelto del padre para hijos', strpos($router, "\$aa_expediente_detail['id']") !== false);
ac_assert('router paginación canónica sin copiar $_GET', strpos($router, '$aa_records_base_query = [') !== false
    && strpos($router, "'action' => 'aa_iframe_content'") !== false
    && strpos($router, "'module' => 'expedientes'") !== false
    && strpos($router, "'view' => 'detail'") !== false
    && strpos($router, "add_query_arg(\$_GET") === false);
ac_assert(
    'router 500 si listado hijos falla',
    strpos($router, "No se pudieron cargar los registros del expediente.") !== false
    && strpos($router, "['response' => 500]") !== false
);
ac_assert('router no usa absint', strpos($router, 'absint(') === false);
ac_assert('router detalle no lee blog_id', strpos($router, "['blog_id']") === false
    && strpos($router, '$_GET[\'blog_id\']') === false);
ac_assert('400 invalid_id', strpos($router, "wp_die('Expediente no válido'") !== false
    && strpos($router, "['response' => 400]") !== false);
ac_assert('404 not_found', strpos($router, "wp_die('Expediente no encontrado'") !== false
    && strpos($router, "['response' => 404]") !== false);
ac_assert('detalle solo en module=expedientes', preg_match(
    "/\\\$active_module === 'expedientes' && \\\$view_raw === 'detail'/",
    $router
) === 1);

ac_assert('módulo ramifica a detail.php', strpos($module, "require __DIR__ . '/detail.php'") !== false);
ac_assert('listado conserva buscador', strpos($module, 'id="aa-expedientes-search"') !== false);
ac_assert('listado conserva paginador', strpos($module, 'id="aa-expedientes-pagination"') !== false);
ac_assert('listado conserva FAB', strpos($module, 'id="aa-expedientes-new-expediente"') !== false);
ac_assert('listado conserva create modal JS', strpos($module, 'expediente-create-modal.js') !== false
    && strpos($module, 'expedientes-module.js') !== false);

ac_assert('detalle sin Volver a Expedientes en body', strpos($detail, 'Volver a Expedientes') === false
    && strpos($detail, 'aa-expediente-detail-back') === false);
ac_assert('header Volver a Expedientes en vista detail', strpos($header, 'Volver a Expedientes') !== false
    && strpos($header, 'aa-expediente-detail-back-link') !== false
    && strpos($header, "\$view_raw === 'detail'") !== false
    && strpos($header, "\$active_module === 'expedientes'") !== false);
ac_assert('detalle escapa título', strpos($detail, 'esc_html($aa_detail_title') !== false
    || strpos($detail, 'esc_html($aa_detail_page_title') !== false
    || strpos($detail, 'esc_attr($aa_detail_page_title)') !== false);
ac_assert('detalle escapa descripción', strpos($detail, 'esc_html($aa_detail_description)') !== false);
ac_assert('detalle escapa categoría', strpos($detail, 'esc_html($aa_detail_category_name)') !== false);
ac_assert('detalle slot registros', strpos($detail, 'id="aa-expediente-detail-registros"') !== false);
ac_assert('detalle sección Registros', strpos($detail, 'Registros') !== false);
ac_assert('detalle incluye partial read-only', strpos($detail, 'expediente-record-readonly.php') !== false);
ac_assert('detalle empty honesto', strpos($detail, 'Aún no hay registros en este expediente') !== false);
ac_assert('detalle incluye paginación SSR', strpos($detail, 'aa-expediente-detail-pagination') !== false);
ac_assert('detalle tiene FAB Nuevo registro propio', strpos($detail, 'id="aa-expediente-detail-new-registro"') !== false
    && strpos($detail, 'Nuevo registro') !== false
    && strpos($detail, 'data-expediente-detail-tool="create-registro"') !== false);
ac_assert('detalle sin FAB Nuevo expediente del listado', strpos($detail, 'aa-expedientes-new-expediente') === false
    && strpos($detail, 'aa-expedientes-fab-stack') === false);
ac_assert('detalle emite AA_EXPEDIENTE_DETAIL_DATA', strpos($detail, 'window.AA_EXPEDIENTE_DETAIL_DATA') !== false);
ac_assert('detalle successUrl canónica sin records_page', strpos($detail, "'view' => 'detail'") !== false
    && strpos($detail, "'expediente_id' => (string) \$aa_detail_id") !== false
    && strpos($detail, "'records_page'") === false
    && strpos($detail, 'client_id') === false
    && strpos($detail, 'blog_id') === false);
ac_assert(
    'detalle config C1b: scopeKey desde padre resuelto',
    strpos($detail, "\$aa_detail_scope_key = \$aa_detail_can_create") !== false
    && strpos($detail, "'expediente:' . (string) \$aa_detail_id") !== false
    && strpos($detail, 'scopeKey:') !== false
);
ac_assert(
    'detalle config C1b: recordsPage efectiva del SSR',
    strpos($detail, 'recordsPage:') !== false
    && strpos($detail, '(int) $aa_records_page') !== false
    && strpos($detail, "\$_GET['records_page']") === false
);
ac_assert(
    'detalle config C1b: cinco actions canónicas',
    strpos($detail, 'listRegistros:') !== false
    && strpos($detail, 'createRegistro:') !== false
    && strpos($detail, 'attachRegistro:') !== false
    && strpos($detail, 'signAdjuntoRead:') !== false
    && strpos($detail, 'deleteAdjunto:') !== false
    && strpos($detail, 'ACTION_LIST') !== false
    && strpos($detail, 'ACTION_ATTACH') !== false
    && strpos($detail, 'ACTION_SIGN_READ') !== false
    && strpos($detail, 'ACTION_DELETE') !== false
);
ac_assert(
    'detalle config C1b: capabilities exactas',
    strpos($detail, 'createRegistro: true') !== false
    && strpos($detail, 'updateRegistro: false') !== false
    && strpos($detail, 'deleteRegistro: false') !== false
    && strpos($detail, 'attach: true') !== false
    && strpos($detail, 'signRead: true') !== false
    && strpos($detail, 'deleteAdjunto: true') !== false
);
ac_assert(
    'detalle config C1b: sin clientId ni rutas privadas',
    strpos($detail, 'clientId') === false
    && strpos($detail, 'client_id') === false
    && strpos($detail, 'storage_path') === false
    && strpos($detail, 'storagePath') === false
);
ac_assert(
    'detalle reutiliza nonce by-expediente (sin nonce extra)',
    strpos($detail, 'aa_expediente_registros_by_expediente_nonce') !== false
    && substr_count($detail, 'wp_create_nonce') === 1
);
ac_assert('detalle carga script create registro', strpos($detail, 'expediente-registro-create-modal.js') !== false);
ac_assert('detalle sin buscador/paginador', strpos($detail, 'aa-expedientes-search') === false
    && strpos($detail, 'aa-expedientes-pagination') === false);
ac_assert('detalle sin menú opciones', strpos($detail, 'aa-expediente-options') === false);
ac_assert('detalle sin JS de listado padre ni create-expediente', strpos($detail, 'expedientes-module.js') === false
    && strpos($detail, 'expediente-create-modal.js') === false
    && strpos($detail, 'clients-module.js') === false);
ac_assert(
    'detalle C1c1 markup SSR/live',
    strpos($detail, 'id="aa-expediente-detail-registros-ssr"') !== false
    && strpos($detail, 'id="aa-expediente-detail-registros-live"') !== false
    && strpos($detail, 'aa-expediente-detail-registros-title') !== false
    && strpos($detail, 'aa-expediente-detail-pagination') !== false
);
ac_assert(
    'detalle C1c1 scripts renderer+adapter+mount+provisional',
    strpos($detail, 'expediente-registros.js') !== false
    && strpos($detail, 'expediente-registros-canonical-adapter.js') !== false
    && strpos($detail, 'expediente-registros-canonical-mount.js') !== false
    && strpos($detail, 'expediente-registro-create-modal.js') !== false
    && strpos($detail, 'executable-options-menu-placement') === false
    && strpos($detail, 'ExpedienteRegistros.openCreate') === false
    && strpos($detail, 'onCreateComplete') === false
);
ac_assert(
    'detalle C1c1: listado rico ≠ create rico adoptado',
    preg_match(
        '/expediente-registros\.js.*expediente-registros-canonical-adapter\.js.*expediente-registros-canonical-mount\.js.*expediente-registro-create-modal\.js/s',
        $detail
    ) === 1
);
ac_assert('listado no emite AA_EXPEDIENTE_DETAIL_DATA', strpos($module, 'AA_EXPEDIENTE_DETAIL_DATA') === false
    && strpos($module, 'aa-expediente-detail-new-registro') === false
    && strpos($module, 'expediente-registro-create-modal.js') === false);
ac_assert('legacy clients sin config/script de detalle', strpos($clients_index, 'AA_EXPEDIENTE_DETAIL_DATA') === false
    && strpos($clients_index, 'expediente-registro-create-modal.js') === false
    && strpos($clients_index, 'aa-expediente-detail-new-registro') === false);
ac_assert('detalle no reutiliza aa-expediente-root', strpos($detail, 'aa-expediente-root') === false);
ac_assert('detalle no usa $wpdb', strpos($detail, '$wpdb') === false);
ac_assert('partial usa details/summary', strpos($detail_partial, '<details') !== false && strpos($detail_partial, '<summary') !== false);
ac_assert('partial folio #ID', strpos($detail_partial, 'Folio #') !== false);
ac_assert('partial usa <time>', strpos($detail_partial, '<time') !== false);
ac_assert('partial body como texto', strpos($detail_partial, 'esc_html($aa_record_body)') !== false);
ac_assert('partial sin acciones ni adjuntos', strpos($detail_partial, 'aa-expediente-registro-options') === false
    && strpos($detail_partial, 'aa-expediente-adjunto') === false);

ac_assert('JS cards son enlace real', strpos($js, "createElement(detailUrl ? 'a' : 'div')") !== false
    && strpos($js, "searchParams.set('view', 'detail')") !== false
    && strpos($js, "searchParams.set('expediente_id'") !== false);
ac_assert('JS sin toggle overlay ni slot', strpos($js, 'data-aa-card-toggle') === false
    && strpos($js, 'aa-card-overlay') === false
    && strpos($js, 'aa-expediente-registros-slot') === false);
ac_assert('JS conserva búsqueda y FAB', strpos($js, 'aa-expedientes-search') !== false
    && strpos($js, 'ExpedienteCreateModal.openCreate') !== false);
ac_assert('JS no envía blog_id', strpos($js, "append('blog_id'") === false);

ac_assert('CSS listado sin is-open propio', strpos($css, '#aa-expedientes-grid [data-aa-card].is-open') === false);
ac_assert('CSS listado sin toggle', strpos($css, '#aa-expedientes-grid [data-aa-card-toggle]') === false);
ac_assert('CSS enlace de card', strpos($css, '.aa-expediente-card-link') !== false);
ac_assert('CSS detalle paginación SSR', strpos($css, '.aa-expediente-detail-pagination') !== false
    && strpos($css, '.aa-expediente-detail-pagination-link') !== false);

ac_assert('AJAX sin acción nueva de get', strpos($ajax, 'GetExpedienteUseCase') === false
    && substr_count($ajax, 'public const ACTION_') === 2);
ac_assert('legacy clients intacta', strpos($clients_index, "view' => 'expediente'") !== false
    && strpos($clients_js, "set('view', 'expediente')") !== false);
ac_assert('expediente-registros.js no tocado para detail', strpos($registros_js, 'view=detail') === false
    && strpos($registros_js, 'expediente_id') === false);
ac_assert('main.js overlays intacto', strpos($main_js, 'data-aa-card-toggle') !== false);
ac_assert('schema sin cambios de este ciclo', strpos($schema, 'DB_VERSION') !== false);

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}
if (!function_exists('esc_url')) {
    function esc_url($url) {
        return (string) $url;
    }
}
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
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
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        $base = (string) $url;
        $query = http_build_query($args, '', '&', PHP_QUERY_RFC3986);
        $sep = strpos($base, '?') === false ? '?' : '&';
        return $base . $sep . $query;
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.test/wp-content/plugins/wp-agenda-automatizada/includes/admin/ui/modules/expedientes/';
    }
}
if (!defined('AA_PLUGIN_VERSION')) {
    define('AA_PLUGIN_VERSION', 'test');
}
if (!class_exists('ExpedienteRegistrosByExpedienteAjax')) {
    final class ExpedienteRegistrosByExpedienteAjax {
        public const ACTION_CREATE = 'aa_create_expediente_registro_for_expediente';
        public const ACTION_LIST = 'aa_list_expediente_registros_for_expediente';
        public const NONCE_ACTION = 'aa_expediente_registros_by_expediente_nonce';
    }
}
if (!class_exists('ExpedienteAdjuntosByExpedienteAjax')) {
    final class ExpedienteAdjuntosByExpedienteAjax {
        public const ACTION_ATTACH = 'aa_attach_expediente_adjunto_for_expediente';
        public const ACTION_SIGN_READ = 'aa_sign_expediente_adjunto_read_for_expediente';
        public const ACTION_DELETE = 'aa_delete_expediente_adjunto_for_expediente';
    }
}

$aa_expediente_detail = [
    'id' => 7,
    'title' => '<img src=x onerror=alert(1)>',
    'description' => '<b>html</b>',
    'created_at' => '2026-08-17 13:00:00',
    'category' => ['slug' => 'general', 'name' => '<script>x</script>'],
];
$aa_expediente_records_view = [
    'records' => [
        [
            'id' => 11,
            'title' => '<b>Título A</b>',
            'body' => "Linea 1\nLinea 2",
            'recorded_at' => '2026-08-19 10:00:00',
            'created_at' => '2026-08-19 10:00:00',
            'updated_at' => null,
        ],
        [
            'id' => 10,
            'title' => 'Título B',
            'body' => '<script>alert(1)</script>',
            'recorded_at' => '2026-08-18 09:00:00',
            'created_at' => '2026-08-18 09:00:00',
            'updated_at' => null,
        ],
    ],
    'page' => 2,
    'per_page' => 15,
    'total' => 32,
    'total_pages' => 3,
    'has_previous' => true,
    'has_next' => true,
    'prev_url' => 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes&view=detail&expediente_id=7&records_page=1&junk=x',
    'next_url' => 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes&view=detail&expediente_id=7&records_page=3',
];
ob_start();
include $plugin_root . '/includes/admin/ui/modules/expedientes/index.php';
$rendered_detail = ob_get_clean();

ac_assert('runtime detail root', strpos($rendered_detail, 'id="aa-expediente-detail-root"') !== false);
ac_assert('runtime no carga listado', strpos($rendered_detail, 'id="aa-expedientes-grid"') === false
    && strpos($rendered_detail, 'aa-expedientes-new-expediente') === false
    && strpos($rendered_detail, 'expedientes-module.js') === false
    && strpos($rendered_detail, 'expediente-create-modal.js') === false);
ac_assert('runtime FAB Nuevo registro', strpos($rendered_detail, 'id="aa-expediente-detail-new-registro"') !== false
    && strpos($rendered_detail, '>Nuevo registro<') !== false);
ac_assert('runtime emite AA_EXPEDIENTE_DETAIL_DATA', strpos($rendered_detail, 'AA_EXPEDIENTE_DETAIL_DATA') !== false);
ac_assert(
    'runtime config action/nonce/id',
    strpos($rendered_detail, 'aa_create_expediente_registro_for_expediente') !== false
    && strpos($rendered_detail, 'nonce-aa_expediente_registros_by_expediente_nonce') !== false
    && strpos($rendered_detail, 'expedienteId:') !== false
    && strpos($rendered_detail, '"7"') !== false
);
ac_assert(
    'runtime config C1b scope/page/actions/capabilities',
    strpos($rendered_detail, '"expediente:7"') !== false
    && strpos($rendered_detail, 'recordsPage:') !== false
    && strpos($rendered_detail, 'recordsPage: 2') !== false
    && strpos($rendered_detail, 'aa_list_expediente_registros_for_expediente') !== false
    && strpos($rendered_detail, 'aa_attach_expediente_adjunto_for_expediente') !== false
    && strpos($rendered_detail, 'aa_sign_expediente_adjunto_read_for_expediente') !== false
    && strpos($rendered_detail, 'aa_delete_expediente_adjunto_for_expediente') !== false
    && strpos($rendered_detail, 'createRegistro: true') !== false
    && strpos($rendered_detail, 'updateRegistro: false') !== false
    && strpos($rendered_detail, 'deleteRegistro: false') !== false
    && strpos($rendered_detail, 'attach: true') !== false
    && strpos($rendered_detail, 'signRead: true') !== false
    && strpos($rendered_detail, 'deleteAdjunto: true') !== false
    && strpos($rendered_detail, 'clientId') === false
);
ac_assert(
    'runtime C1c1 carga renderer+adapter+mount; create provisional; sin placement',
    strpos($rendered_detail, 'expediente-registros-canonical-adapter.js') !== false
    && strpos($rendered_detail, 'expediente-registros.js') !== false
    && strpos($rendered_detail, 'expediente-registros-canonical-mount.js') !== false
    && strpos($rendered_detail, 'expediente-registro-create-modal.js') !== false
    && strpos($rendered_detail, 'executable-options-menu-placement') === false
    && strpos($rendered_detail, 'clients-module.js') === false
    && strpos($rendered_detail, 'id="aa-expediente-detail-registros-ssr"') !== false
    && strpos($rendered_detail, 'id="aa-expediente-detail-registros-live"') !== false
);
ac_assert(
    'runtime C1c1 live oculto y ssr visible en HTML inicial',
    strpos($rendered_detail, 'id="aa-expediente-detail-registros-live"') !== false
    && preg_match('/id="aa-expediente-detail-registros-live"[^>]*(hidden|class="[^"]*hidden)/', $rendered_detail) === 1
);
ac_assert(
    'runtime successUrl canónica',
    strpos($rendered_detail, 'action=aa_iframe_content') !== false
    && strpos($rendered_detail, 'module=expedientes') !== false
    && strpos($rendered_detail, 'view=detail') !== false
    && strpos($rendered_detail, 'expediente_id=7') !== false
);
ac_assert(
    'runtime successUrl sin records_page ni extras',
    preg_match('/AA_EXPEDIENTE_DETAIL_DATA[\s\S]*?successUrl["\']?\s*:\s*["\']([^"\']+)["\']/', $rendered_detail, $success_m) === 1
    && isset($success_m[1])
    && strpos($success_m[1], 'records_page') === false
    && strpos($success_m[1], 'client_id') === false
    && strpos($success_m[1], 'blog_id') === false
    && strpos($success_m[1], 'junk') === false
);
ac_assert('runtime carga script create registro', strpos($rendered_detail, 'expediente-registro-create-modal.js') !== false);
ac_assert('runtime conserva create provisional', strpos($rendered_detail, 'expediente-registro-create-modal.js') !== false);
ac_assert('runtime sin editar/eliminar/adjuntar', strpos($rendered_detail, 'Editar registro') === false
    && strpos($rendered_detail, 'Eliminar') === false
    && strpos($rendered_detail, 'Añadir imagen') === false
    && strpos($rendered_detail, 'aa-expediente-registro-options') === false);
ac_assert(
    'runtime escapa título',
    strpos($rendered_detail, '&lt;img src=x onerror=alert(1)&gt;') !== false
    && strpos($rendered_detail, '<img src=x onerror=alert(1)>') === false
);
ac_assert(
    'runtime escapa descripción',
    strpos($rendered_detail, '&lt;b&gt;html&lt;/b&gt;') !== false
    && strpos($rendered_detail, '<b>html</b>') === false
);
ac_assert(
    'runtime escapa categoría',
    strpos($rendered_detail, '&lt;script&gt;x&lt;/script&gt;') !== false
    && strpos($rendered_detail, '<script>x</script>') === false
);
ac_assert('runtime fecha formateada', strpos($rendered_detail, '17/08/2026') !== false);
ac_assert('runtime detalle sin volver en body', strpos($rendered_detail, 'Volver a Expedientes') === false
    && strpos($rendered_detail, 'aa-expediente-detail-back') === false);

$active_module = 'expedientes';
$view_raw = 'detail';
ob_start();
include $plugin_root . '/includes/admin/ui/shared/header.php';
$rendered_header_detail = ob_get_clean();
ac_assert('runtime header volver en detail', strpos($rendered_header_detail, 'Volver a Expedientes') !== false
    && strpos($rendered_header_detail, 'aa-expediente-detail-back-link') !== false
    && strpos($rendered_header_detail, 'action=aa_iframe_content&module=expedientes') !== false);

$active_module = 'expedientes';
$view_raw = '';
ob_start();
include $plugin_root . '/includes/admin/ui/shared/header.php';
$rendered_header_list = ob_get_clean();
ac_assert('runtime header sin volver en listado', strpos($rendered_header_list, 'Volver a Expedientes') === false
    && strpos($rendered_header_list, 'aa-expediente-detail-back-link') === false);

ac_assert('runtime slot estable', strpos($rendered_detail, 'id="aa-expediente-detail-registros"') !== false
    && strpos($rendered_detail, 'data-expediente-id="7"') !== false);
ac_assert('runtime renderiza sección Registros', strpos($rendered_detail, 'Registros') !== false);
ac_assert('runtime no muestra placeholder provisional', strpos($rendered_detail, 'Los registros estarán disponibles próximamente') === false);
ac_assert('runtime renderiza dos records en orden recibido', strpos($rendered_detail, 'Folio #11') !== false
    && strpos($rendered_detail, 'Folio #10') !== false
    && strpos($rendered_detail, 'Folio #11') < strpos($rendered_detail, 'Folio #10'));
ac_assert('runtime details cerrados por defecto', strpos($rendered_detail, '<details class="aa-expediente-registro" open') === false);
ac_assert('runtime fecha legacy meses abreviados', strpos($rendered_detail, '19/Ago/2026') !== false
    && strpos($rendered_detail, '18/Ago/2026') !== false);
ac_assert('runtime cuerpo conserva saltos (sin nl2br)', strpos($rendered_detail, 'Linea 1' . "\n" . 'Linea 2') !== false
    && strpos($rendered_detail, '<br') === false);
ac_assert('runtime escape título/cuerpo', strpos($rendered_detail, '&lt;b&gt;Título A&lt;/b&gt;') !== false
    && strpos($rendered_detail, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false);
ac_assert('runtime paginación visible', strpos($rendered_detail, 'aa-expediente-detail-pagination') !== false
    && strpos($rendered_detail, 'Página 2') !== false
    && strpos($rendered_detail, 'de 3') !== false);
ac_assert('runtime enlaces de paginación presentes', strpos($rendered_detail, 'records_page=1') !== false
    && strpos($rendered_detail, 'records_page=3') !== false);
ac_assert('runtime Sin descripción no forzado si hay texto', strpos($rendered_detail, 'Sin descripción') === false);

$aa_expediente_detail = [
    'id' => 8,
    'title' => 'Vacío',
    'description' => null,
    'created_at' => '2026-08-17 13:00:00',
    'category' => ['slug' => 'general', 'name' => 'General'],
];
$aa_expediente_records_view = [
    'records' => [],
    'page' => 1,
    'per_page' => 15,
    'total' => 0,
    'total_pages' => 0,
    'has_previous' => false,
    'has_next' => false,
    'prev_url' => '',
    'next_url' => '',
];
ob_start();
include $plugin_root . '/includes/admin/ui/modules/expedientes/detail.php';
$rendered_empty_desc = ob_get_clean();
ac_assert('runtime Sin descripción', strpos($rendered_empty_desc, 'Sin descripción') !== false);
ac_assert('runtime empty state registros', strpos($rendered_empty_desc, 'Aún no hay registros en este expediente') !== false);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
