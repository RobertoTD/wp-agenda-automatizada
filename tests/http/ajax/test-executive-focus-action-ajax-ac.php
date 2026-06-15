<?php
/**
 * AC MC5 — Executive focus action POST AJAX.
 *
 * Ejecutar: php tests/http/ajax/test-executive-focus-action-ajax-ac.php
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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ExecutiveProposalAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$index_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/learning/index.php');

ac_assert('ExecutiveProposalAjax file readable', $ajax_src !== false);
ac_assert('AJAX registers aa_executive_focus_action', strpos($ajax_src, 'aa_executive_focus_action') !== false);
ac_assert('AJAX uses ChangeExecutiveFocusUseCase', strpos($ajax_src, 'ChangeExecutiveFocusUseCase') !== false);
ac_assert('AJAX focus action uses same nonce', strpos($ajax_src, 'aa_executive_proposal_nonce') !== false);
ac_assert('AJAX checks manage_options capability', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('AJAX focus reads focus_action', strpos($ajax_src, "post_string('focus_action')") !== false);
ac_assert('AJAX maps invalid_focus_action', strpos($ajax_src, 'invalid_focus_action') !== false);
ac_assert('AJAX maps previous_focus_unavailable', strpos($ajax_src, 'previous_focus_unavailable') !== false);
ac_assert('AJAX maps no_eligible_focus', strpos($ajax_src, 'no_eligible_focus') !== false);
ac_assert('Plugin bootstrap registers ExecutiveProposalAjax', strpos($bootstrap_src, 'ExecutiveProposalAjax::register()') !== false);
ac_assert('index.php configures focusActionPost', is_string($index_src) && strpos($index_src, 'aa_executive_focus_action') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/http/ajax/ExecutiveProposalAjax.php';

ac_assert('ExecutiveProposalAjax::handle_focus_action is callable', method_exists('ExecutiveProposalAjax', 'handle_focus_action'));

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
