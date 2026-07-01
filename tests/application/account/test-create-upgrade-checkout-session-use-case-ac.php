<?php
/**
 * AC para CreateUpgradeCheckoutSessionUseCase (MC3).
 *
 * Uso:
 *   php tests/application/account/test-create-upgrade-checkout-session-use-case-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$GLOBALS['aa_test_options'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }
        return $default;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://tenant.example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url) {
        return parse_url($url);
    }
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

$root = dirname(__DIR__, 3);

require_once $root . '/includes/infrastructure/backend/class-aa-upgrade-checkout-backend-client.php';
require_once $root . '/includes/application/account/CreateUpgradeCheckoutSessionUseCase.php';

final class Mock_Upgrade_Checkout_Backend_Client extends AA_Upgrade_Checkout_Backend_Client {

    /** @var array<string,mixed> */
    public static $response = [
        'ok'          => false,
        'code'        => 'upgrade_backend_error',
        'error'       => 'unset',
        'http_status' => 500,
    ];

    /** @var string|null */
    public static $last_return_url = null;

    public function createSession(string $return_url): array {
        self::$last_return_url = $return_url;
        return self::$response;
    }
}

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' — ' . $detail) . "\n";
}

function reset_options(): void {
    $GLOBALS['aa_test_options'] = [];
}

/**
 * @param array<string,mixed> $payload
 * @return list<string>
 */
function forbidden_keys_in_payload(array $payload): array {
    $forbidden = [
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripeCustomerId',
        'account_id',
        'accountId',
        'installation_id',
        'installationId',
        'subscription_id',
        'subscriptionId',
        'client_secret',
        'aa_client_secret',
        'token',
        'token_hash',
        'raw',
        'id',
        'ok',
    ];

    $json = wp_json_encode_safe($payload);
    $found = [];
    foreach ($forbidden as $key) {
        if (stripos($json, '"' . $key . '"') !== false) {
            $found[] = $key;
        }
    }
    return $found;
}

function wp_json_encode_safe($data): string {
    if (function_exists('wp_json_encode')) {
        $encoded = wp_json_encode($data);
        return is_string($encoded) ? $encoded : '';
    }
    return (string) json_encode($data);
}

const STRIPE_CHECKOUT_URL = 'https://checkout.stripe.com/c/pay/cs_test_abc123';

// --- happy path ---
reset_options();
Mock_Upgrade_Checkout_Backend_Client::$last_return_url = null;
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Upgrade_Checkout_Backend_Client::$response = [
    'ok'           => true,
    'checkout_url' => STRIPE_CHECKOUT_URL,
];

$result = (new CreateUpgradeCheckoutSessionUseCase(new Mock_Upgrade_Checkout_Backend_Client()))->execute();

ac(
    'happy path returns checkout_url',
    !empty($result['success'])
        && ($result['data']['checkout_url'] ?? '') === STRIPE_CHECKOUT_URL,
    wp_json_encode_safe($result)
);

ac(
    'happy path passes server-built return_url to backend client',
    is_string(Mock_Upgrade_Checkout_Backend_Client::$last_return_url)
        && strpos(Mock_Upgrade_Checkout_Backend_Client::$last_return_url, 'action=aa_iframe_content') !== false
        && strpos(Mock_Upgrade_Checkout_Backend_Client::$last_return_url, 'module=account') !== false,
    (string) Mock_Upgrade_Checkout_Backend_Client::$last_return_url
);

$forbidden = forbidden_keys_in_payload($result['data'] ?? []);
ac(
    'happy path response contains only checkout_url',
    !empty($result['success'])
        && count($result['data']) === 1
        && array_key_exists('checkout_url', $result['data'])
        && empty($forbidden),
    implode(', ', $forbidden)
);

// --- reject non-Stripe URL ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Upgrade_Checkout_Backend_Client::$response = [
    'ok'           => true,
    'checkout_url' => 'https://evil.example.com/phish',
];

$result = (new CreateUpgradeCheckoutSessionUseCase(new Mock_Upgrade_Checkout_Backend_Client()))->execute();
ac(
    'non-Stripe URL rejected',
    empty($result['success'])
        && ($result['error']['code'] ?? '') === 'upgrade_invalid_response',
    wp_json_encode_safe($result)
);

// --- upgrade_unavailable ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Upgrade_Checkout_Backend_Client::$response = [
    'ok'          => false,
    'code'        => 'upgrade_unavailable',
    'error'       => '',
    'http_status' => 409,
];

$result = (new CreateUpgradeCheckoutSessionUseCase(new Mock_Upgrade_Checkout_Backend_Client()))->execute();
ac(
    'upgrade_unavailable maps to dedicated message',
    empty($result['success'])
        && ($result['error']['code'] ?? '') === 'upgrade_unavailable'
        && stripos((string) ($result['error']['message'] ?? ''), 'ya no está disponible para upgrade') !== false,
    wp_json_encode_safe($result)
);

// --- sin aa_client_secret ---
reset_options();
Mock_Upgrade_Checkout_Backend_Client::$response = [
    'ok'           => true,
    'checkout_url' => STRIPE_CHECKOUT_URL,
];

$result = (new CreateUpgradeCheckoutSessionUseCase(new Mock_Upgrade_Checkout_Backend_Client()))->execute();
ac(
    'sin aa_client_secret returns upgrade_backend_not_configured',
    empty($result['success'])
        && ($result['error']['code'] ?? '') === 'upgrade_backend_not_configured',
    wp_json_encode_safe($result)
);

// --- backend unreachable ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Upgrade_Checkout_Backend_Client::$response = [
    'ok'          => false,
    'code'        => 'upgrade_backend_unreachable',
    'error'       => 'connection refused',
    'http_status' => 0,
];

$result = (new CreateUpgradeCheckoutSessionUseCase(new Mock_Upgrade_Checkout_Backend_Client()))->execute();
ac(
    'backend unreachable does not expose raw transport message',
    empty($result['success'])
        && ($result['error']['code'] ?? '') === 'upgrade_backend_unreachable'
        && stripos((string) ($result['error']['message'] ?? ''), 'connection refused') === false,
    wp_json_encode_safe($result)
);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
