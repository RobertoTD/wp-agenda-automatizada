<?php
/**
 * AC MC4 — Calendar exposes AA_PUSH_CONFIG without backend render calls.
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

$calendar_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/calendar/index.php');

ac_assert('Calendar exposes window.AA_PUSH_CONFIG', strpos($calendar_src, 'window.AA_PUSH_CONFIG') !== false);
ac_assert('AA_PUSH_CONFIG includes ajaxUrl', strpos($calendar_src, 'ajaxUrl') !== false);
ac_assert('AA_PUSH_CONFIG includes registerAction', strpos($calendar_src, 'registerAction') !== false);
ac_assert('AA_PUSH_CONFIG includes configAction', strpos($calendar_src, 'configAction') !== false);
ac_assert('AA_PUSH_CONFIG includes nonce', strpos($calendar_src, 'nonce') !== false);
ac_assert('AA_PUSH_CONFIG uses PushSubscriptionAjax constants', strpos($calendar_src, 'PushSubscriptionAjax::ACTION_REGISTER') !== false);
ac_assert('AA_PUSH_CONFIG uses PushSubscriptionAjax config action', strpos($calendar_src, 'PushSubscriptionAjax::ACTION_CONFIG') !== false);
ac_assert('AA_PUSH_CONFIG uses dedicated nonce action', strpos($calendar_src, 'PushSubscriptionAjax::NONCE_ACTION') !== false);
ac_assert('Calendar does not embed vapidPublicKey at render', strpos($calendar_src, 'vapidPublicKey') === false);
ac_assert('Calendar does not call aa_send_authenticated_request during render', strpos($calendar_src, 'aa_send_authenticated_request') === false);
ac_assert('Calendar does not call getVapidPublicKey during render', strpos($calendar_src, 'getVapidPublicKey') === false);
ac_assert('Calendar does not store VAPID in WP options', strpos($calendar_src, 'vapid') === false && strpos($calendar_src, 'push_public_key') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
