<?php
/**
 * AC — Calendar runtime enqueue (PWA install + notifications first opportunity).
 *
 * Ejecutar: php tests/admin/ui/test-calendar-index-runtime-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$index_php = file_get_contents($plugin_root . '/includes/admin/ui/modules/calendar/index.php');

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

ac_assert('calendar index.php readable', $index_php !== false);

ac_assert(
    'calendar enqueues learning-action-handlers.js',
    is_string($index_php)
    && strpos($index_php, 'learning-action-handlers.js') !== false
);

ac_assert(
    'calendar enqueues pwa-install-first-opportunity.js',
    is_string($index_php)
    && strpos($index_php, 'pwa-install-first-opportunity.js') !== false
);

ac_assert(
    'calendar loads learning-action-handlers.js before pwa-install-first-opportunity.js',
    is_string($index_php)
    && strpos($index_php, 'learning-action-handlers.js') < strpos($index_php, 'pwa-install-first-opportunity.js')
);

ac_assert(
    'calendar learning-action-handlers.js uses defer',
    is_string($index_php)
    && (bool) preg_match('/\$learning_handlers_js.*defer/m', $index_php)
);

ac_assert(
    'calendar pwa-install-first-opportunity.js uses defer',
    is_string($index_php)
    && (bool) preg_match('/\$pwa_install_first_opportunity_js.*defer/m', $index_php)
);

ac_assert(
    'calendar resolves learning-action-handlers via learning/index.php anchor',
    is_string($index_php)
    && strpos($index_php, "plugin_dir_url(__DIR__ . '/../learning/index.php')") !== false
);

ac_assert(
    'calendar resolves pwa-install-first-opportunity via dashboard/index.php anchor',
    is_string($index_php)
    && strpos($index_php, "plugin_dir_url(__DIR__ . '/../dashboard/index.php')") !== false
);

ac_assert(
    'calendar exposes window.AA_PUSH_CONFIG',
    is_string($index_php)
    && strpos($index_php, 'window.AA_PUSH_CONFIG') !== false
);

ac_assert(
    'calendar AA_PUSH_CONFIG uses PushSubscriptionAjax constants',
    is_string($index_php)
    && strpos($index_php, 'PushSubscriptionAjax::ACTION_REGISTER') !== false
    && strpos($index_php, 'PushSubscriptionAjax::ACTION_CONFIG') !== false
    && strpos($index_php, 'PushSubscriptionAjax::NONCE_ACTION') !== false
);

ac_assert(
    'calendar enqueues pwaPushActivationService.js',
    is_string($index_php)
    && strpos($index_php, 'pwaPushActivationService.js') !== false
);

ac_assert(
    'calendar enqueues pwa-notifications-first-opportunity.js',
    is_string($index_php)
    && strpos($index_php, 'pwa-notifications-first-opportunity.js') !== false
);

ac_assert(
    'calendar loads AA_PUSH_CONFIG before pwaPushActivationService',
    is_string($index_php)
    && strpos($index_php, 'window.AA_PUSH_CONFIG') !== false
    && strpos($index_php, 'pwaPushActivationService.js') !== false
    && strpos($index_php, 'window.AA_PUSH_CONFIG') < strpos($index_php, 'pwaPushActivationService.js')
);

ac_assert(
    'calendar loads pwaPushActivationService before pwa-notifications-first-opportunity',
    is_string($index_php)
    && strpos($index_php, 'pwaPushActivationService.js') !== false
    && strpos($index_php, 'pwa-notifications-first-opportunity.js') !== false
    && strpos($index_php, 'pwaPushActivationService.js') < strpos($index_php, 'pwa-notifications-first-opportunity.js')
);

ac_assert(
    'calendar pwaPushActivationService.js uses defer',
    is_string($index_php)
    && (bool) preg_match('/\$pwa_push_activation_service_js.*defer/m', $index_php)
);

ac_assert(
    'calendar pwa-notifications-first-opportunity.js uses defer',
    is_string($index_php)
    && (bool) preg_match('/\$pwa_notifications_first_opportunity_js.*defer/m', $index_php)
);

ac_assert(
    'calendar resolves pwa-notifications-first-opportunity via dashboard/index.php anchor',
    is_string($index_php)
    && (bool) preg_match(
        "/pwa_notifications_first_opportunity_js\s*=\s*plugin_dir_url\(__DIR__\s*\.\s*'\/\.\.\/dashboard\/index\.php'\)/",
        $index_php
    )
);

echo "\n{$passed}/{$total} passed\n";

if ($failed !== []) {
    exit(1);
}

exit(0);
