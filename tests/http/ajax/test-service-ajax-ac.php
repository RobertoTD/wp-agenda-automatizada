<?php
/**
 * AC — ServiceAjax structural + auth contract.
 *
 * Ejecutar: php tests/http/ajax/test-service-ajax-ac.php
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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ServiceAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$legacy_src = file_get_contents($plugin_root . '/includes/services/assignments/servicesService.php');
$layout_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/layout.php');

ac_assert('ServiceAjax file exists', is_string($ajax_src) && $ajax_src !== '');
ac_assert('registers aa_update_service', strpos($ajax_src, 'aa_update_service') !== false);
ac_assert('capability manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('nonce aa_update_service', strpos($ajax_src, "'aa_update_service'") !== false);
ac_assert('delegates to UpdateServiceUseCase', strpos($ajax_src, 'UpdateServiceUseCase') !== false);
ac_assert('does not read description from POST', strpos($ajax_src, "\$_POST['description']") === false);
ac_assert('does not read active from POST', strpos($ajax_src, "\$_POST['active']") === false);
ac_assert('reads price as string', strpos($ajax_src, "\$_POST['price']") !== false && strpos($ajax_src, 'floatval') === false);
ac_assert('sanitizes name', strpos($ajax_src, 'sanitize_text_field') !== false);
ac_assert('sanitizes indicaciones as textarea', strpos($ajax_src, 'sanitize_textarea_field') !== false);
ac_assert('404 when not found', strpos($ajax_src, '404') !== false);
ac_assert('403 when unauthorized', strpos($ajax_src, '403') !== false);
ac_assert('bootstrap requires ServiceAjax', strpos($bootstrap_src, 'includes/http/ajax/ServiceAjax.php') !== false);
ac_assert('bootstrap registers ServiceAjax', strpos($bootstrap_src, 'ServiceAjax::register()') !== false);
ac_assert('layout exposes AA_SERVICE_NONCES', strpos($layout_src, 'AA_SERVICE_NONCES') !== false);
ac_assert('layout nonce update_service', strpos($layout_src, 'aa_update_service') !== false);

ac_assert('legacy aa_get_services_db intact', strpos($legacy_src, 'function aa_get_services_db') !== false);
ac_assert('legacy aa_create_service intact', strpos($legacy_src, 'function aa_create_service') !== false);
ac_assert('legacy aa_update_service_db intact', strpos($legacy_src, 'function aa_update_service_db') !== false);
ac_assert('legacy aa_toggle_service intact', strpos($legacy_src, 'function aa_toggle_service') !== false);
ac_assert('legacy aa_delete_service_db intact', strpos($legacy_src, 'function aa_delete_service_db') !== false);
ac_assert('legacy still registers update endpoint', strpos($legacy_src, "add_action('wp_ajax_aa_update_service_db'") !== false);
ac_assert('legacy still registers toggle endpoint', strpos($legacy_src, "add_action('wp_ajax_aa_toggle_service'") !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

require_once $plugin_root . '/includes/http/ajax/ServiceAjax.php';

ac_assert('class ServiceAjax exists', class_exists('ServiceAjax'));
ac_assert('register callable', method_exists('ServiceAjax', 'register'));
ac_assert('handle_update callable', method_exists('ServiceAjax', 'handle_update'));
ac_assert('ACTION constant', ServiceAjax::ACTION_UPDATE === 'aa_update_service');
ac_assert('NONCE constant', ServiceAjax::NONCE_ACTION === 'aa_update_service');

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
