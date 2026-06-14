<?php
/**
 * AC MC3 — Executive action POST AJAX.
 *
 * Ejecutar: php tests/http/ajax/test-executive-action-ajax-ac.php
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
ac_assert('AJAX registers aa_executive_action', strpos($ajax_src, 'aa_executive_action') !== false);
ac_assert('AJAX uses RecordExecutiveActionUseCase', strpos($ajax_src, 'RecordExecutiveActionUseCase') !== false);
ac_assert('AJAX executive action uses same nonce', strpos($ajax_src, 'aa_executive_proposal_nonce') !== false);
ac_assert('AJAX checks manage_options capability', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('AJAX action success returns wp_send_json_success', strpos($ajax_src, 'wp_send_json_success') !== false);
ac_assert('AJAX action maps proposal_empty status', strpos($ajax_src, 'proposal_empty') !== false);
ac_assert('AJAX action maps task_not_current status', strpos($ajax_src, 'task_not_current') !== false);
ac_assert('AJAX action maps action_not_allowed status', strpos($ajax_src, 'action_not_allowed') !== false);
ac_assert('AJAX action reads task_id and action_key', strpos($ajax_src, "post_scalar('task_id')") !== false && strpos($ajax_src, "post_string('action_key')") !== false);

ac_assert('index.php configures actionPost aa_executive_action', is_string($index_src) && strpos($index_src, 'aa_executive_action') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/http/ajax/ExecutiveProposalAjax.php';

ac_assert('ExecutiveProposalAjax::handle_executive_action is callable', method_exists('ExecutiveProposalAjax', 'handle_executive_action'));

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
