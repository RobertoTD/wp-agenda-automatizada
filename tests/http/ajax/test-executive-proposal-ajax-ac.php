<?php
/**
 * AC MC2 — ExecutiveProposalAjax read-only.
 *
 * Ejecutar: php tests/http/ajax/test-executive-proposal-ajax-ac.php
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

ac_assert('ExecutiveProposalAjax file readable', $ajax_src !== false);
ac_assert('AJAX registers aa_get_executive_proposal', strpos($ajax_src, 'aa_get_executive_proposal') !== false);
ac_assert('AJAX uses GetExecutiveProposalUseCase', strpos($ajax_src, 'GetExecutiveProposalUseCase') !== false);
ac_assert('AJAX uses dedicated nonce aa_executive_proposal_nonce', strpos($ajax_src, 'aa_executive_proposal_nonce') !== false);
ac_assert('AJAX checks manage_options capability', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('AJAX uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('AJAX success uses wp_send_json_success', strpos($ajax_src, 'wp_send_json_success') !== false);
ac_assert('AJAX error uses wp_send_json_error', strpos($ajax_src, 'wp_send_json_error') !== false);
ac_assert('AJAX registers aa_executive_action POST handler', strpos($ajax_src, 'aa_executive_action') !== false);

ac_assert('Plugin bootstrap registers ExecutiveProposalAjax', strpos($bootstrap_src, 'ExecutiveProposalAjax::register()') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/http/ajax/ExecutiveProposalAjax.php';

ac_assert('ExecutiveProposalAjax::register is callable', method_exists('ExecutiveProposalAjax', 'register'));
ac_assert('ExecutiveProposalAjax::handle_get_proposal is callable', method_exists('ExecutiveProposalAjax', 'handle_get_proposal'));

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
