<?php
/**
 * AC MC4 — Dashboard exposes AA_PUSH_CONFIG without backend render calls.
 *
 * Ejecutar: php tests/http/ajax/test-push-config-dashboard-ac.php
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

$dashboard_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/dashboard/index.php');

ac_assert('Dashboard exposes window.AA_PUSH_CONFIG', strpos($dashboard_src, 'window.AA_PUSH_CONFIG') !== false);
ac_assert('AA_PUSH_CONFIG includes ajaxUrl', strpos($dashboard_src, 'ajaxUrl') !== false);
ac_assert('AA_PUSH_CONFIG includes registerAction', strpos($dashboard_src, 'registerAction') !== false);
ac_assert('AA_PUSH_CONFIG includes configAction', strpos($dashboard_src, 'configAction') !== false);
ac_assert('AA_PUSH_CONFIG includes nonce', strpos($dashboard_src, 'nonce') !== false);
ac_assert('AA_PUSH_CONFIG uses PushSubscriptionAjax constants', strpos($dashboard_src, 'PushSubscriptionAjax::ACTION_REGISTER') !== false);
ac_assert('AA_PUSH_CONFIG uses PushSubscriptionAjax config action', strpos($dashboard_src, 'PushSubscriptionAjax::ACTION_CONFIG') !== false);
ac_assert('AA_PUSH_CONFIG uses dedicated nonce action', strpos($dashboard_src, 'PushSubscriptionAjax::NONCE_ACTION') !== false);
ac_assert('Dashboard does not embed vapidPublicKey at render', strpos($dashboard_src, 'vapidPublicKey') === false);
ac_assert('Dashboard does not call aa_send_authenticated_request during render', strpos($dashboard_src, 'aa_send_authenticated_request') === false);
ac_assert('Dashboard does not call getVapidPublicKey during render', strpos($dashboard_src, 'getVapidPublicKey') === false);
ac_assert('Dashboard does not store VAPID in WP options', strpos($dashboard_src, 'vapid') === false && strpos($dashboard_src, 'push_public_key') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
