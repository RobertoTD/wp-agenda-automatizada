<?php
/**
 * AC — StaffAjax structural + auth contract.
 *
 * Ejecutar: php tests/http/ajax/test-staff-ajax-ac.php
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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/StaffAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$legacy_src = file_get_contents($plugin_root . '/includes/services/assignments/staffService.php');
$layout_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/layout.php');

ac_assert('StaffAjax file exists', is_string($ajax_src) && $ajax_src !== '');
ac_assert('registers aa_update_staff', strpos($ajax_src, 'aa_update_staff') !== false);
ac_assert('capability manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('nonce aa_update_staff', strpos($ajax_src, "'aa_update_staff'") !== false);
ac_assert('delegates to UpdateStaffUseCase', strpos($ajax_src, 'UpdateStaffUseCase') !== false);
ac_assert('reads service_ids from POST', strpos($ajax_src, "\$_POST['service_ids']") !== false);
ac_assert('does not read active/toggle from POST', strpos($ajax_src, "\$_POST['active']") === false);
ac_assert('sanitizes name', strpos($ajax_src, 'sanitize_text_field') !== false);
ac_assert('404 when not found', strpos($ajax_src, '404') !== false);
ac_assert('403 when unauthorized', strpos($ajax_src, '403') !== false);
ac_assert('bootstrap requires StaffAjax', strpos($bootstrap_src, 'includes/http/ajax/StaffAjax.php') !== false);
ac_assert('bootstrap registers StaffAjax', strpos($bootstrap_src, 'StaffAjax::register()') !== false);
ac_assert('layout exposes AA_STAFF_NONCES', strpos($layout_src, 'AA_STAFF_NONCES') !== false);
ac_assert('layout nonce update_staff', strpos($layout_src, 'aa_update_staff') !== false);

ac_assert('legacy aa_get_staff intact', strpos($legacy_src, 'function aa_get_staff') !== false);
ac_assert('legacy aa_create_staff intact', strpos($legacy_src, 'function aa_create_staff') !== false);
ac_assert('legacy aa_add_staff_service intact', strpos($legacy_src, 'function aa_add_staff_service') !== false);
ac_assert('legacy aa_remove_staff_service intact', strpos($legacy_src, 'function aa_remove_staff_service') !== false);
ac_assert('legacy still registers add endpoint', strpos($legacy_src, "add_action('wp_ajax_aa_add_staff_service'") !== false);
ac_assert('legacy still registers remove endpoint', strpos($legacy_src, "add_action('wp_ajax_aa_remove_staff_service'") !== false);
ac_assert('legacy still registers toggle endpoint', strpos($legacy_src, "add_action('wp_ajax_aa_toggle_staff'") !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

require_once $plugin_root . '/includes/http/ajax/StaffAjax.php';

ac_assert('class StaffAjax exists', class_exists('StaffAjax'));
ac_assert('register callable', method_exists('StaffAjax', 'register'));
ac_assert('handle_update callable', method_exists('StaffAjax', 'handle_update'));
ac_assert('ACTION constant', StaffAjax::ACTION_UPDATE === 'aa_update_staff');
ac_assert('NONCE constant', StaffAjax::NONCE_ACTION === 'aa_update_staff');

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
