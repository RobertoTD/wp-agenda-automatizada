<?php
/**
 * AC — ClientsAjax structural + auth contract.
 *
 * Ejecutar: php tests/http/ajax/test-clients-ajax-ac.php
 */

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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ClientsAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

ac_assert('ClientsAjax file exists', is_string($ajax_src) && $ajax_src !== '');
ac_assert('registers aa_get_cliente', strpos($ajax_src, 'aa_get_cliente') !== false);
ac_assert('capability manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('nonce aa_get_cliente', strpos($ajax_src, 'NONCE_ACTION') !== false && strpos($ajax_src, "'aa_get_cliente'") !== false);
ac_assert('uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('uses absint client_id', strpos($ajax_src, 'absint') !== false);
ac_assert('404 when missing', strpos($ajax_src, '404') !== false);
ac_assert('400 when invalid id', strpos($ajax_src, '400') !== false);
ac_assert('403 when unauthorized', strpos($ajax_src, '403') !== false);
ac_assert('delegates to ClientsRepository', strpos($ajax_src, 'ClientsRepository::find_by_id') !== false);
ac_assert('bootstrap requires ClientsAjax', strpos($bootstrap_src, 'includes/http/ajax/ClientsAjax.php') !== false);
ac_assert('bootstrap registers ClientsAjax', strpos($bootstrap_src, 'ClientsAjax::register()') !== false);
ac_assert('does not expand aa_view_panel gate', strpos($ajax_src, 'aa_view_panel') === false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/http/ajax/ClientsAjax.php';

ac_assert('class ClientsAjax exists', class_exists('ClientsAjax'));
ac_assert('register callable', method_exists('ClientsAjax', 'register'));
ac_assert('handle_get_cliente callable', method_exists('ClientsAjax', 'handle_get_cliente'));
ac_assert('ACTION constant', ClientsAjax::ACTION_GET_CLIENTE === 'aa_get_cliente');
ac_assert('NONCE constant', ClientsAjax::NONCE_ACTION === 'aa_get_cliente');

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
