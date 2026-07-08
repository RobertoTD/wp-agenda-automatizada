<?php
/**
 * AC MC4 — PushSubscriptionAjax bridge.
 *
 * Ejecutar: php tests/http/ajax/test-push-subscription-ajax-ac.php
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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/PushSubscriptionAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

ac_assert('PushSubscriptionAjax file readable', $ajax_src !== false);
ac_assert('AJAX registers aa_register_push_subscription', strpos($ajax_src, 'aa_register_push_subscription') !== false);
ac_assert('AJAX registers aa_get_push_config', strpos($ajax_src, 'aa_get_push_config') !== false);
ac_assert('AJAX uses dedicated nonce aa_push_subscription_nonce', strpos($ajax_src, 'aa_push_subscription_nonce') !== false);
ac_assert('AJAX checks manage_options capability', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('AJAX uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('AJAX uses wp_ajax_ only (no nopriv)', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('AJAX does not accept installation_id from browser', strpos($ajax_src, "\$_POST['installation_id']") === false);
ac_assert('AJAX does not accept agenda_client_id from browser', strpos($ajax_src, 'agenda_client_id') === false);
ac_assert('AJAX does not accept domain from browser', strpos($ajax_src, "['domain']") === false && strpos($ajax_src, '$_POST[\'domain\']') === false);
ac_assert('AJAX does not accept site_url from browser', strpos($ajax_src, 'site_url') === false);
ac_assert('AJAX preserves invalid_subscription', strpos($ajax_src, "'invalid_subscription'") !== false);
ac_assert('AJAX preserves no_installation_id', strpos($ajax_src, "'no_installation_id'") !== false);
ac_assert('AJAX preserves endpoint_conflict', strpos($ajax_src, "'endpoint_conflict'") !== false);
ac_assert('AJAX normalizes technical errors to push_backend_unavailable', strpos($ajax_src, "'push_backend_unavailable'") !== false);
ac_assert('AJAX config failure uses push_config_unavailable', strpos($ajax_src, "'push_config_unavailable'") !== false);
ac_assert('AJAX config success exposes vapidPublicKey', strpos($ajax_src, "'vapidPublicKey'") !== false);
ac_assert('Plugin bootstrap registers PushSubscriptionAjax', strpos($bootstrap_src, 'PushSubscriptionAjax::register()') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['aa_test_json'] = null;
$GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return (bool) ($GLOBALS['aa_test_can_manage_options'] ?? true);
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg) {
        if (empty($GLOBALS['aa_test_nonce_valid'])) {
            wp_die('bad nonce');
        }
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = [
            'success' => true,
            'data'    => $data,
            'status'  => $status_code,
        ];
        throw new RuntimeException('json_sent');
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = [
            'success' => false,
            'data'    => $data,
            'status'  => $status_code,
        ];
        throw new RuntimeException('json_sent');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim((string) $str);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return $value;
    }
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-push-backend-client.php';
require_once $plugin_root . '/includes/http/ajax/PushSubscriptionAjax.php';

final class Mock_Push_Backend_Client extends AA_Push_Backend_Client {
    /** @var array<string,mixed> */
    public static $register_response = ['ok' => false, 'code' => 'unset'];
    /** @var array<string,mixed> */
    public static $config_response = ['ok' => false, 'code' => 'unset'];
    /** @var int */
    public static $register_calls = 0;

    public function registerSubscription(array $subscription): array {
        self::$register_calls++;
        return self::$register_response;
    }

    public function getVapidPublicKey(): array {
        return self::$config_response;
    }
}

final class Testable_PushSubscriptionAjax extends PushSubscriptionAjax {
    protected static function resolveBackendClient(): AA_Push_Backend_Client {
        return new Mock_Push_Backend_Client();
    }
}

function run_ajax(callable $handler): array {
    $GLOBALS['aa_test_json'] = null;
    try {
        $handler();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent') {
            throw $e;
        }
    }

    return $GLOBALS['aa_test_json'] ?? [];
}

ac_assert('PushSubscriptionAjax::register is callable', method_exists('PushSubscriptionAjax', 'register'));
ac_assert('PushSubscriptionAjax::handle_register is callable', method_exists('PushSubscriptionAjax', 'handle_register'));
ac_assert('PushSubscriptionAjax::handle_get_config is callable', method_exists('PushSubscriptionAjax', 'handle_get_config'));

$parsed = PushSubscriptionAjax::parseSubscriptionFromPost();
ac_assert('parseSubscriptionFromPost rejects empty POST', $parsed === null);

$_POST = [
    'endpoint' => 'https://push.example.test/subscription/abc',
    'p256dh'   => 'p256dh-key',
    'auth'     => 'auth-key',
];
$parsed = PushSubscriptionAjax::parseSubscriptionFromPost();
ac_assert(
    'parseSubscriptionFromPost builds MC3 body shape',
    is_array($parsed)
    && ($parsed['endpoint'] ?? '') === 'https://push.example.test/subscription/abc'
    && ($parsed['keys']['p256dh'] ?? '') === 'p256dh-key'
    && ($parsed['keys']['auth'] ?? '') === 'auth-key'
);

$GLOBALS['aa_test_nonce_valid'] = true;
$GLOBALS['aa_test_can_manage_options'] = true;
Mock_Push_Backend_Client::$register_calls = 0;
Mock_Push_Backend_Client::$register_response = [
    'ok'           => true,
    'registration' => 'created',
    'first_test'   => ['status' => 'sent'],
];
$_POST['_wpnonce'] = 'valid';
$response = run_ajax([Testable_PushSubscriptionAjax::class, 'handle_register']);
ac_assert('valid register returns success envelope', ($response['success'] ?? false) === true);
ac_assert('valid register preserves sent', ($response['data']['first_test']['status'] ?? '') === 'sent');
ac_assert('valid register calls backend once', Mock_Push_Backend_Client::$register_calls === 1);

foreach (['already_sent', 'failed', 'sent_unconfirmed'] as $status) {
    Mock_Push_Backend_Client::$register_response = [
        'ok'           => true,
        'registration' => 'updated',
        'first_test'   => $status === 'failed'
            ? ['status' => $status, 'reason' => 'transport']
            : ['status' => $status],
    ];
    $response = run_ajax([Testable_PushSubscriptionAjax::class, 'handle_register']);
    ac_assert(
        "register preserves first_test status {$status}",
        ($response['data']['first_test']['status'] ?? '') === $status
    );
}

$_POST = ['endpoint' => '', 'p256dh' => '', 'auth' => '', '_wpnonce' => 'valid'];
Mock_Push_Backend_Client::$register_calls = 0;
$response = run_ajax([Testable_PushSubscriptionAjax::class, 'handle_register']);
ac_assert('invalid input returns invalid_subscription without backend call', Mock_Push_Backend_Client::$register_calls === 0);
ac_assert('invalid input uses HTTP 400', (int) ($response['status'] ?? 0) === 400);
ac_assert('invalid input error code preserved', ($response['data']['error'] ?? '') === 'invalid_subscription');

foreach (
    [
        'invalid_subscription' => 400,
        'no_installation_id'   => 409,
        'endpoint_conflict'    => 409,
    ] as $error => $http_status
) {
    $_POST = [
        'endpoint' => 'https://push.example.test/subscription/abc',
        'p256dh'   => 'p256dh-key',
        'auth'     => 'auth-key',
        '_wpnonce' => 'valid',
    ];
    Mock_Push_Backend_Client::$register_response = [
        'ok'          => false,
        'code'        => $error,
        'error'       => '',
        'http_status' => $http_status,
    ];
    $response = run_ajax([Testable_PushSubscriptionAjax::class, 'handle_register']);
    ac_assert("functional error {$error} preserved", ($response['data']['error'] ?? '') === $error);
    ac_assert("functional error {$error} uses HTTP {$http_status}", (int) ($response['status'] ?? 0) === $http_status);
}

$_POST = [
    'endpoint' => 'https://push.example.test/subscription/abc',
    'p256dh'   => 'p256dh-key',
    'auth'     => 'auth-key',
    '_wpnonce' => 'valid',
];
Mock_Push_Backend_Client::$register_response = [
    'ok'          => false,
    'code'        => 'push_backend_unavailable',
    'error'       => 'timeout',
    'http_status' => 503,
];
$response = run_ajax([Testable_PushSubscriptionAjax::class, 'handle_register']);
ac_assert('technical register failure normalizes to push_backend_unavailable', ($response['data']['error'] ?? '') === 'push_backend_unavailable');
ac_assert('technical register failure uses HTTP 503', (int) ($response['status'] ?? 0) === 503);

Mock_Push_Backend_Client::$config_response = [
    'ok'               => true,
    'vapid_public_key' => 'test-public-key',
];
$response = run_ajax([Testable_PushSubscriptionAjax::class, 'handle_get_config']);
ac_assert('aa_get_push_config success returns vapidPublicKey', ($response['data']['vapidPublicKey'] ?? '') === 'test-public-key');

Mock_Push_Backend_Client::$config_response = [
    'ok'          => false,
    'code'        => 'push_backend_unavailable',
    'error'       => 'missing',
    'http_status' => 503,
];
$response = run_ajax([Testable_PushSubscriptionAjax::class, 'handle_get_config']);
ac_assert('aa_get_push_config failure returns push_config_unavailable', ($response['data']['error'] ?? '') === 'push_config_unavailable');
ac_assert('aa_get_push_config failure uses HTTP 503', (int) ($response['status'] ?? 0) === 503);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
