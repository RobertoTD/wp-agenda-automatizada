<?php
/**
 * AC — MC1 Expedientes (clients view=expediente).
 *
 * Ejecutar: php tests/admin/ui/test-clients-expediente-mc1-ac.php
 *
 * Cubre: URL/configuración PHP→JS, endpoint, autorización, aislamiento por prefix,
 * ramificación lista/expediente. No carga WordPress ni BD.
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
    $path = $plugin_root . '/' . $relative;
    $src = file_get_contents($path);
    return is_string($src) ? $src : '';
}

$index = ac_read('includes/admin/ui/modules/clients/index.php');
$js = ac_read('includes/admin/ui/modules/clients/clients-module.js');
$ajax = ac_read('includes/http/ajax/ClientsAjax.php');
$repo = ac_read('includes/repositories/ClientsRepository.php');
$bootstrap = ac_read('wp-agenda-automatizada.php');
$css = ac_read('includes/admin/ui/assets/css/admin.source.css');
$sidebar = ac_read('includes/admin/ui/shared/sidebar.php');
$router = ac_read('includes/admin/ui/index.php');
$main_js = ac_read('includes/admin/ui/assets/js/main.js');

// --- URL / config PHP ---
ac_assert('index emite AA_CLIENTS_DATA', strpos($index, 'window.AA_CLIENTS_DATA') !== false);
ac_assert('index usa admin_url para listUrl', strpos($index, "admin_url('admin-post.php?action=aa_iframe_content&module=clients')") !== false);
ac_assert('index usa add_query_arg para expediente', strpos($index, 'add_query_arg') !== false);
ac_assert('index serializa con wp_json_encode', strpos($index, 'wp_json_encode($aa_clients_list_url)') !== false);
ac_assert('index normaliza view=expediente', strpos($index, "'expediente'") !== false);
ac_assert('index normaliza client_id con absint', strpos($index, 'absint') !== false);
ac_assert('index tiene aa-clients-list-root', strpos($index, 'aa-clients-list-root') !== false);
ac_assert('index tiene aa-expediente-root', strpos($index, 'aa-expediente-root') !== false);
ac_assert('index no acepta blog_id de GET', strpos($index, 'blog_id') === false);
ac_assert('nonce get_cliente en AA_CLIENTS_NONCES', strpos($index, 'get_cliente') !== false);
ac_assert('index emite expedienteAccessAllowed', strpos($index, 'expedienteAccessAllowed') !== false);
ac_assert('JS guarda expedienteAccessAllowed en click', strpos($js, 'expedienteAccessAllowed') !== false);

// --- JS ramificación ---
ac_assert('JS ramifica isExpedienteView', strpos($js, 'isExpedienteView') !== false);
ac_assert('JS initExpedienteView existe', strpos($js, 'function initExpedienteView') !== false);
ac_assert('JS initListView existe', strpos($js, 'function initListView') !== false);
ac_assert('JS no inicia lista en expediente', preg_match('/function init\(\)\s*\{[\s\S]*isExpedienteView\(\)[\s\S]*initExpedienteView[\s\S]*return;[\s\S]*initListView/', $js) === 1);
ac_assert('JS botón Expediente presente', strpos($js, 'aa-btn-expediente-cliente') !== false);
ac_assert('JS conserva Editar', strpos($js, 'aa-btn-editar-cliente') !== false && strpos($js, 'openEdit') !== false);
ac_assert('JS stopPropagation en Expediente', substr_count($js, 'stopPropagation') >= 2);
ac_assert('JS Volver a Clientes', strpos($js, 'Volver a Clientes') !== false);
ac_assert('JS empty state registros', strpos($js, 'Aún no hay registros en este expediente') !== false);
ac_assert('JS buildExpedienteUrl usa moduleBaseUrl', strpos($js, 'moduleBaseUrl') !== false && strpos($js, "set('view', 'expediente')") !== false);
ac_assert('JS fetch aa_get_cliente / getCliente', strpos($js, 'getCliente') !== false || strpos($js, 'aa_get_cliente') !== false);
ac_assert('JS no hardcodea /wp-admin/admin-post.php como base', preg_match('#moduleBaseUrl[^\n]*admin-post\.php#', $js) !== 1);

// --- Endpoint / auth / aislamiento ---
ac_assert('ClientsAjax existe', $ajax !== '');
ac_assert('ClientsAjax ACTION aa_get_cliente', strpos($ajax, "aa_get_cliente") !== false);
ac_assert('ClientsAjax usa manage_options', strpos($ajax, "current_user_can('manage_options')") !== false);
ac_assert('ClientsAjax check_ajax_referer', strpos($ajax, 'check_ajax_referer') !== false);
ac_assert('ClientsAjax usa ClientsRepository::find_by_id', strpos($ajax, 'ClientsRepository::find_by_id') !== false);
ac_assert('ClientsAjax solo campos id/nombre/telefono/correo en success', preg_match("/wp_send_json_success\(\[\s*'id'[\s\S]*'nombre'[\s\S]*'telefono'[\s\S]*'correo'/", $ajax) === 1);
ac_assert('ClientsAjax no lee blog_id del request', strpos($ajax, "\$_REQUEST['blog_id']") === false && strpos($ajax, "\$_POST['blog_id']") === false);
ac_assert('Bootstrap registra ClientsAjax', strpos($bootstrap, 'ClientsAjax::register()') !== false);

ac_assert('Repository find_by_id existe', strpos($repo, 'function find_by_id') !== false);
ac_assert('Repository find_by_id usa $wpdb->prefix', strpos($repo, "\$wpdb->prefix . 'aa_clientes'") !== false);
ac_assert(
    'find_by_id selecciona solo id,nombre,telefono,correo',
    preg_match('/function find_by_id[\s\S]*?SELECT id, nombre, telefono, correo FROM/', $repo) === 1
);

// --- Guardrails ---
ac_assert('whitelist sin module=expedientes', strpos($router, "'expedientes'") === false);
ac_assert('sidebar Expedientes sigue en module=clients', strpos($sidebar, 'module=clients') !== false && strpos($sidebar, 'Expedientes') !== false);
ac_assert('main.js no modificado para expediente', strpos($main_js, 'expediente') === false && strpos($main_js, 'Expediente') === false);
ac_assert('CSS grupo aa-client-card-actions', strpos($css, 'aa-client-card-actions') !== false);
ac_assert('CSS botón expediente', strpos($css, 'aa-btn-expediente-cliente') !== false);

// --- Runtime: normalización PHP sin WP pleno ---
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}
if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url) {
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        $parts = [];
        foreach ($args as $k => $v) {
            $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        return $url . $sep . implode('&', $parts);
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
if (!function_exists('esc_url')) {
    function esc_url($url) {
        return $url;
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.test/wp-content/plugins/wp-agenda-automatizada/includes/admin/ui/modules/clients/';
    }
}
if (!defined('AA_PLUGIN_VERSION')) {
    define('AA_PLUGIN_VERSION', 'test');
}
if (!class_exists('ClientsAjax')) {
    class ClientsAjax {
        public const ACTION_GET_CLIENTE = 'aa_get_cliente';
        public const NONCE_ACTION = 'aa_get_cliente';
    }
}

// Simular GET expediente válido
$_GET = ['view' => 'expediente', 'client_id' => '42'];
ob_start();
include $plugin_root . '/includes/admin/ui/modules/clients/index.php';
$rendered_exp = ob_get_clean();

ac_assert(
    'runtime view=expediente emite clientId 42',
    strpos($rendered_exp, 'view: "expediente"') !== false
    && strpos($rendered_exp, 'clientId: 42') !== false,
    substr($rendered_exp, strpos($rendered_exp, 'AA_CLIENTS_DATA') ?: 0, 400)
);
ac_assert(
    'runtime expedienteUrl incluye view y client_id',
    strpos($rendered_exp, 'view=expediente') !== false
    && strpos($rendered_exp, 'client_id=42') !== false
);
ac_assert(
    'runtime lista oculta en expediente',
    strpos($rendered_exp, 'id="aa-clients-list-root" class="hidden"') !== false
    || preg_match('/id="aa-clients-list-root"[^>]*class="[^"]*hidden/', $rendered_exp) === 1
);

// Simular GET inválido → lista
$_GET = ['view' => 'expediente', 'client_id' => '0'];
ob_start();
include $plugin_root . '/includes/admin/ui/modules/clients/index.php';
$rendered_invalid = ob_get_clean();

ac_assert(
    'runtime client_id inválido cae a lista',
    strpos($rendered_invalid, 'view: "list"') !== false
    && strpos($rendered_invalid, 'clientId: 0') !== false
);

// Simular lista
$_GET = [];
ob_start();
include $plugin_root . '/includes/admin/ui/modules/clients/index.php';
$rendered_list = ob_get_clean();

ac_assert(
    'runtime lista por defecto',
    strpos($rendered_list, 'view: "list"') !== false
);
ac_assert(
    'runtime expedienteAccessAllowed false sin shell_access (fail-closed UX)',
    strpos($rendered_list, 'expedienteAccessAllowed: false') !== false
);

// Aunque exista $shell_access full en scope, el flag PHP arranca SIEMPRE false:
// solo la proyección asíncrona (JS) lo habilita ante una respuesta `full` viva.
$shell_access = ['access' => 'full', 'reason' => 'documents_accepted', 'legal' => []];
if (!class_exists('AA_Shell_Access', false)) {
    require_once $plugin_root . '/includes/domain/legal/class-aa-shell-access.php';
}
$_GET = [];
ob_start();
include $plugin_root . '/includes/admin/ui/modules/clients/index.php';
$rendered_full = ob_get_clean();
ac_assert(
    'runtime expedienteAccessAllowed siempre false en PHP (JS habilita, nunca autoridad)',
    strpos($rendered_full, 'expedienteAccessAllowed: false') !== false
    && strpos($rendered_full, 'expedienteAccessAllowed: true') === false
);
unset($shell_access);

// Repository runtime con $wpdb mock
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

require_once $plugin_root . '/includes/repositories/ClientsRepository.php';

global $wpdb;
$wpdb = new class {
    public $prefix = 'wp_3_';
    public $last_error = '';
    public $last_query = '';
    public $prepare_args = [];
    public $row = null;

    public function prepare($query, ...$args) {
        $this->last_query = $query;
        $this->prepare_args = $args;
        return $query;
    }

    public function get_row($query, $output = OBJECT) {
        if ($this->row === null) {
            return null;
        }
        if ($output === ARRAY_A) {
            return $this->row;
        }
        return (object) $this->row;
    }
};

ac_assert('find_by_id(0) → null', ClientsRepository::find_by_id(0) === null);

$wpdb->row = [
    'id' => '7',
    'nombre' => 'Ana',
    'telefono' => '525512345678',
    'correo' => 'ana@example.com',
];
$found = ClientsRepository::find_by_id(7);
ac_assert('find_by_id hit usa prefijo de sitio', strpos($wpdb->last_query, 'wp_3_aa_clientes') !== false);
ac_assert(
    'find_by_id devuelve solo campos esperados',
    is_array($found)
    && $found === [
        'id' => 7,
        'nombre' => 'Ana',
        'telefono' => '525512345678',
        'correo' => 'ana@example.com',
    ]
);

$wpdb->row = null;
ac_assert('find_by_id miss → null (otro sitio / inexistente)', ClientsRepository::find_by_id(99) === null);

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}

echo 'Failed ' . count($failed) . "/{$total}\n";
foreach ($failed as $label) {
    echo " - {$label}\n";
}
exit(1);
