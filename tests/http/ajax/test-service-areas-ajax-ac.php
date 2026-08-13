<?php
/**
 * AC — ServiceAreasAjax structural + auth contract.
 *
 * Ejecutar: php tests/http/ajax/test-service-areas-ajax-ac.php
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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ServiceAreasAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$legacy_src = file_get_contents($plugin_root . '/includes/services/assignments/areasService.php');
$layout_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/layout.php');

ac_assert('ServiceAreasAjax file exists', is_string($ajax_src) && $ajax_src !== '');
ac_assert('registers aa_update_service_area', strpos($ajax_src, 'aa_update_service_area') !== false);
ac_assert('capability manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('nonce aa_update_service_area', strpos($ajax_src, "'aa_update_service_area'") !== false);
ac_assert('delegates to UpdateServiceAreaUseCase', strpos($ajax_src, 'UpdateServiceAreaUseCase') !== false);
ac_assert('does not read description from POST', strpos($ajax_src, "\$_POST['description']") === false
    && strpos($ajax_src, "\$_REQUEST['description']") === false);
ac_assert('sanitizes name', strpos($ajax_src, 'sanitize_text_field') !== false);
ac_assert('404 when not found', strpos($ajax_src, '404') !== false);
ac_assert('403 when unauthorized', strpos($ajax_src, '403') !== false);
ac_assert('bootstrap requires ServiceAreasAjax', strpos($bootstrap_src, 'includes/http/ajax/ServiceAreasAjax.php') !== false);
ac_assert('bootstrap registers ServiceAreasAjax', strpos($bootstrap_src, 'ServiceAreasAjax::register()') !== false);
ac_assert('layout exposes AA_AREAS_NONCES', strpos($layout_src, 'AA_AREAS_NONCES') !== false);
ac_assert('layout nonce update_service_area', strpos($layout_src, 'aa_update_service_area') !== false);

ac_assert('legacy aa_update_service_area_name intact', strpos($legacy_src, 'function aa_update_service_area_name') !== false);
ac_assert('legacy aa_update_service_area_color intact', strpos($legacy_src, 'function aa_update_service_area_color') !== false);
ac_assert('legacy aa_update_service_area_description intact', strpos($legacy_src, 'function aa_update_service_area_description') !== false);
ac_assert('legacy still registers individual name endpoint', strpos($legacy_src, "add_action('wp_ajax_aa_update_service_area_name'") !== false);
ac_assert('legacy still registers individual color endpoint', strpos($legacy_src, "add_action('wp_ajax_aa_update_service_area_color'") !== false);
ac_assert('legacy still registers individual description endpoint', strpos($legacy_src, "add_action('wp_ajax_aa_update_service_area_description'") !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

require_once $plugin_root . '/includes/http/ajax/ServiceAreasAjax.php';

ac_assert('class ServiceAreasAjax exists', class_exists('ServiceAreasAjax'));
ac_assert('register callable', method_exists('ServiceAreasAjax', 'register'));
ac_assert('handle_update callable', method_exists('ServiceAreasAjax', 'handle_update'));
ac_assert('ACTION constant', ServiceAreasAjax::ACTION_UPDATE === 'aa_update_service_area');
ac_assert('NONCE constant', ServiceAreasAjax::NONCE_ACTION === 'aa_update_service_area');

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
