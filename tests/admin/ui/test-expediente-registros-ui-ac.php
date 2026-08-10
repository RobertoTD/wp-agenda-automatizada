<?php
/**
 * AC — MC2/MC3 UI expediente registros (structural).
 *
 * Ejecutar: php tests/admin/ui/test-expediente-registros-ui-ac.php
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

$js = file_get_contents($plugin_root . '/includes/admin/ui/modules/clients/expediente-registros.js');
$module = file_get_contents($plugin_root . '/includes/admin/ui/modules/clients/clients-module.js');
$index = file_get_contents($plugin_root . '/includes/admin/ui/modules/clients/index.php');
$css = file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.source.css');
$main = file_get_contents($plugin_root . '/includes/admin/ui/assets/js/main.js');

ac_assert('expediente-registros.js existe', is_string($js) && $js !== '');
ac_assert('AAAdmin.ExpedienteRegistros.init', strpos($js, 'AAAdmin.ExpedienteRegistros') !== false && strpos($js, 'init:') !== false);
ac_assert('Nuevo registro', strpos($js, 'Nuevo registro') !== false);
ac_assert('openCreate público', strpos($js, 'openCreate: openCreateForm') !== false);
ac_assert('clients-module menú Nuevo registro en header', strpos($module, 'aa-expediente-options-trigger') !== false
    && strpos($module, 'data-expediente-tool="create-registro"') !== false
    && strpos($module, 'Nuevo registro') !== false
    && strpos($module, 'openCreate') !== false);
ac_assert('clients-module sin botón aa-expediente-nuevo-registro-btn', strpos($module, 'aa-expediente-nuevo-registro-btn') === false);
ac_assert('registros sin botón aa-expediente-nuevo-registro-btn', strpos($js, 'aa-expediente-nuevo-registro-btn') === false);
ac_assert('openRegistroForm create/edit ready', strpos($js, "mode === 'edit'") !== false && strpos($js, "mode: 'create'") !== false);
ac_assert('usa details', strpos($js, "createElement('details')") !== false);
ac_assert('textContent no innerHTML para datos', strpos($js, 'titleSpan.textContent') !== false && strpos($js, 'body.textContent') !== false);
ac_assert('prependRecord con expandId', strpos($js, 'function prependRecord') !== false && strpos($js, 'expandId') !== false);
ac_assert('folio Folio #', strpos($js, "'Folio #'") !== false || strpos($js, '"Folio #"') !== false);
ac_assert('crea time con datetime', strpos($js, "createElement('time')") !== false && strpos($js, 'datetime') !== false);
ac_assert('slot acciones con Editar y Eliminar', strpos($js, 'aa-expediente-registro-actions') !== false
    && strpos($js, 'aa-expediente-btn-editar') !== false
    && strpos($js, 'aa-expediente-btn-eliminar') !== false
    && strpos($js, "textContent = 'Eliminar'") !== false);
ac_assert('confirmAndDeleteRegistro + modal.isOpen', strpos($js, 'confirmAndDeleteRegistro') !== false
    && strpos($js, 'modal.isOpen') !== false);
ac_assert('replaceRecord sin reordenar updated_at', strpos($js, 'function replaceRecord') !== false && strpos($js, 'updateRegistro') !== false);
ac_assert('sin data-aa-card', strpos($js, 'data-aa-card') === false);
ac_assert('sin aa-card-overlay / appointment', strpos($js, 'aa-card-overlay') === false && strpos($js, 'aa-appointment-') === false);
ac_assert('loadRecords al init', strpos($js, 'loadRecords()') !== false);
ac_assert('openModal compartido', strpos($js, 'AAAdmin.openModal') !== false);
ac_assert('clients-module solo monta', strpos($module, 'mountExpedienteRegistros') !== false);
ac_assert('clients-module no define listRegistros fetch propio', strpos($module, 'aa_list_expediente_registros') === false);
ac_assert('index script condicional vista expediente', preg_match('/if \(\$aa_clients_is_expediente\).*expediente-registros\.js/s', $index) === 1);
ac_assert('CSS details registro', strpos($css, 'aa-expediente-registro') !== false);
ac_assert('CSS sin botón legacy nuevo registro', strpos($css, 'aa-expediente-nuevo-registro-btn') === false);
ac_assert('CSS folio y actions:empty', strpos($css, 'aa-expediente-registro-folio') !== false && strpos($css, 'aa-expediente-registro-actions:empty') !== false);
ac_assert('CSS botón editar', strpos($css, 'aa-expediente-btn-editar') !== false);
ac_assert('CSS botón eliminar (MC5c2)', strpos($css, 'aa-expediente-btn-eliminar') !== false);
ac_assert('CSS summary sin chevron ::after', strpos($css, '.aa-expediente-registro-summary::after') === false);
ac_assert('CSS open summary border', strpos($css, '.aa-expediente-registro[open]') !== false);
ac_assert('main.js sin cambios expediente registros', strpos($main, 'ExpedienteRegistros') === false && strpos($main, 'aa_list_expediente') === false);

// Runtime: lista no carga script ni actions de registros
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
}
if (!function_exists('absint')) {
    function absint($n) { return abs((int) $n); }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'https://example.test/wp-admin/' . ltrim((string) $path, '/'); }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url) {
        $parts = [];
        foreach ($args as $k => $v) {
            $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        return $url . (strpos($url, '?') === false ? '?' : '&') . implode('&', $parts);
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($a) { return 'nonce-' . $a; }
}
if (!function_exists('esc_url')) {
    function esc_url($u) { return $u; }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($f) {
        return 'https://example.test/plugins/clients/';
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
if (!class_exists('ExpedienteRegistrosAjax')) {
    class ExpedienteRegistrosAjax {
        public const ACTION_LIST = 'aa_list_expediente_registros';
        public const ACTION_CREATE = 'aa_create_expediente_registro';
        public const ACTION_UPDATE = 'aa_update_expediente_registro';
        public const ACTION_DELETE = 'aa_delete_expediente_registro';
        public const NONCE_ACTION = 'aa_expediente_registros_nonce';
    }
}
if (!class_exists('ExpedienteAdjuntosAjax')) {
    class ExpedienteAdjuntosAjax {
        public const ACTION_ATTACH = 'aa_attach_expediente_registro';
        public const ACTION_SIGN_READ = 'aa_sign_expediente_adjunto_read';
        public const ACTION_DELETE = 'aa_delete_expediente_adjunto';
    }
}

$_GET = [];
ob_start();
include $plugin_root . '/includes/admin/ui/modules/clients/index.php';
$list_html = ob_get_clean();
ac_assert('vista lista sin expediente-registros.js', strpos($list_html, 'expediente-registros.js') === false);
ac_assert('vista lista sin listRegistros', strpos($list_html, 'listRegistros') === false);

$_GET = ['view' => 'expediente', 'client_id' => '5'];
ob_start();
include $plugin_root . '/includes/admin/ui/modules/clients/index.php';
$exp_html = ob_get_clean();
ac_assert('vista expediente carga js', strpos($exp_html, 'expediente-registros.js') !== false);
ac_assert('vista expediente emite listRegistros', strpos($exp_html, 'listRegistros') !== false);
ac_assert('vista expediente emite createRegistro', strpos($exp_html, 'createRegistro') !== false);
ac_assert('vista expediente emite updateRegistro', strpos($exp_html, 'updateRegistro') !== false);
ac_assert('vista expediente emite deleteRegistro', strpos($exp_html, 'deleteRegistro') !== false
    && strpos($exp_html, 'aa_delete_expediente_registro') !== false);
ac_assert('vista expediente emite nonce registros', strpos($exp_html, 'expediente_registros') !== false);

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
