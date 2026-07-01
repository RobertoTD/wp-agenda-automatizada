<?php
/**
 * AC para GetAccountStatusUseCase (M4D).
 *
 * Uso:
 *   php tests/application/account/test-get-account-status-use-case-ac.php
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

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://tenant.example.test' . $path;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url) {
        $separator = strpos((string) $url, '?') !== false ? '&' : '?';

        return (string) $url . $separator . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }
}

$root = dirname(__DIR__, 3);

require_once $root . '/includes/domain/site/class-aa-public-site-status.php';
require_once $root . '/includes/domain/tenant/class-aa-installation-provisioning-detector.php';
require_once $root . '/includes/infrastructure/wp/PublicSitePreview.php';
require_once $root . '/includes/infrastructure/backend/class-aa-account-status-backend-client.php';
require_once $root . '/includes/application/account/GetAccountStatusUseCase.php';

final class Mock_Account_Status_Backend_Client extends AA_Account_Status_Backend_Client {

    /** @var array<string,mixed> */
    public static $response = [
        'ok' => false,
        'code' => 'account_backend_error',
        'error' => 'unset',
        'http_status' => 500,
    ];

    public function fetch(): array {
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

// --- pro active ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => [
        'plan_tier'               => 'pro',
        'stripe_status'           => 'active',
        'effective_access_tier'   => 'pro',
        'billing_state'           => 'active',
        'current_period_end'      => '2026-06-28T00:00:00.000Z',
        'cancel_at'               => null,
        'is_cancel_scheduled'     => false,
        'sync_pending'            => false,
        'payment_action_required' => false,
        'messages'                => [],
        'stripe_customer_id'      => 'cus_SHOULD_NOT_PASS',
        'id'                      => 'sub-internal',
    ],
];

$use_case = new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client());
$result   = $use_case->execute();

ac(
    'pro active returns success',
    !empty($result['success'])
        && ($result['data']['account_status']['plan_tier'] ?? '') === 'pro'
        && ($result['data']['account_status']['effective_access_tier'] ?? '') === 'pro',
    wp_json_encode_safe($result)
);

$forbidden = forbidden_keys_in_payload($result['data']['account_status'] ?? []);
ac(
    'pro active strips forbidden fields',
    empty($forbidden),
    implode(', ', $forbidden)
);

ac(
    'pro active normalizes booleans',
    ($result['data']['account_status']['is_cancel_scheduled'] ?? null) === false
        && ($result['data']['account_status']['sync_pending'] ?? null) === false,
    wp_json_encode_safe($result['data']['account_status'] ?? [])
);

// --- missing subscription ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => [
        'plan_tier'               => null,
        'stripe_status'           => null,
        'effective_access_tier'   => 'freemium',
        'billing_state'           => 'missing',
        'current_period_end'      => null,
        'cancel_at'               => null,
        'is_cancel_scheduled'     => false,
        'sync_pending'            => false,
        'payment_action_required' => false,
        'messages'                => ['No hay suscripción vinculada a esta agenda.'],
    ],
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
ac(
    'missing subscription returns freemium/missing',
    !empty($result['success'])
        && ($result['data']['account_status']['billing_state'] ?? '') === 'missing'
        && ($result['data']['account_status']['effective_access_tier'] ?? '') === 'freemium',
    wp_json_encode_safe($result)
);

// --- sin aa_client_secret ---
reset_options();
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => ['plan_tier' => 'pro'],
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
ac(
    'sin aa_client_secret returns account_backend_not_configured',
    empty($result['success'])
        && ($result['error']['code'] ?? '') === 'account_backend_not_configured',
    wp_json_encode_safe($result)
);

// --- backend error ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Account_Status_Backend_Client::$response = [
    'ok'          => false,
    'code'        => 'account_backend_error',
    'error'       => 'Invalid signature',
    'http_status' => 403,
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
ac(
    'backend error propagates code and message',
    empty($result['success'])
        && ($result['error']['code'] ?? '') === 'account_backend_error'
        && ($result['error']['message'] ?? '') === 'Invalid signature',
    wp_json_encode_safe($result)
);

// --- messages normalization ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => [
        'plan_tier' => 'pro',
        'messages'  => ['  Aviso uno  ', '', 123, 'Aviso dos'],
    ],
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
$messages = $result['data']['account_status']['messages'] ?? null;
ac(
    'messages normalized as string array',
    is_array($messages)
        && $messages === ['Aviso uno', 'Aviso dos'],
    wp_json_encode_safe($messages)
);

// --- public_site: provisioned + maintenance ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-06-01 12:00:00';
$GLOBALS['aa_test_options'][AA_Public_Site_Status::OPTION] = AA_Public_Site_Status::STATUS_MAINTENANCE;
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => [
        'plan_tier' => 'pro',
        'billing_state' => 'active',
        'effective_access_tier' => 'pro',
    ],
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
$public_site = $result['data']['public_site'] ?? [];
ac(
    'provisioned maintenance exposes public_site section',
    !empty($result['success'])
        && ($public_site['show_section'] ?? false) === true
        && ($public_site['status'] ?? '') === AA_Public_Site_Status::STATUS_MAINTENANCE
        && ($public_site['can_activate'] ?? true) === false
        && ($public_site['can_deactivate'] ?? true) === false,
    wp_json_encode_safe($public_site)
);
ac(
    'provisioned maintenance includes public preview url',
    is_string($public_site['public_url'] ?? null)
        && strpos((string) $public_site['public_url'], 'deoia_public_preview=1') !== false,
    (string) ($public_site['public_url'] ?? '')
);

// --- public_site: provisioned + active ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-06-01 12:00:00';
$GLOBALS['aa_test_options'][AA_Public_Site_Status::OPTION] = AA_Public_Site_Status::STATUS_ACTIVE;
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => [
        'plan_tier' => 'pro',
        'billing_state' => 'active',
        'effective_access_tier' => 'pro',
    ],
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
$public_site = $result['data']['public_site'] ?? [];
ac(
    'provisioned active exposes public_site section without deactivate',
    !empty($result['success'])
        && ($public_site['show_section'] ?? false) === true
        && ($public_site['status'] ?? '') === AA_Public_Site_Status::STATUS_ACTIVE
        && ($public_site['can_deactivate'] ?? true) === false,
    wp_json_encode_safe($public_site)
);
ac(
    'provisioned active includes public preview url',
    is_string($public_site['public_url'] ?? null)
        && strpos((string) $public_site['public_url'], 'deoia_public_preview=1') !== false,
    (string) ($public_site['public_url'] ?? '')
);

// --- public_site: not provisioned ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
$GLOBALS['aa_test_options'][AA_Public_Site_Status::OPTION] = AA_Public_Site_Status::STATUS_MAINTENANCE;
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => [
        'plan_tier' => 'pro',
        'billing_state' => 'active',
        'effective_access_tier' => 'pro',
    ],
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
$public_site = $result['data']['public_site'] ?? [];
ac(
    'non-provisioned site hides public_site section',
    !empty($result['success'])
        && ($public_site['show_section'] ?? true) === false
        && ($public_site['is_provisioned'] ?? true) === false,
    wp_json_encode_safe($public_site)
);
ac(
    'non-provisioned site has no public preview url',
    empty($public_site['public_url'] ?? null),
    wp_json_encode_safe($public_site['public_url'] ?? null)
);

// --- public_site still available when backend not configured ---
reset_options();
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-06-01 12:00:00';
$GLOBALS['aa_test_options'][AA_Public_Site_Status::OPTION] = AA_Public_Site_Status::STATUS_MAINTENANCE;

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
$public_site = $result['data']['public_site'] ?? [];
ac(
    'backend not configured still returns local public_site payload',
    empty($result['success'])
        && ($result['error']['code'] ?? '') === 'account_backend_not_configured'
        && ($public_site['show_section'] ?? false) === true
        && ($public_site['status'] ?? '') === AA_Public_Site_Status::STATUS_MAINTENANCE,
    wp_json_encode_safe($result)
);

// --- benefit_quotas sanitization ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => [
        'plan_tier' => 'freemium',
        'billing_state' => 'active',
        'effective_access_tier' => 'freemium',
        'benefit_quotas' => [
            'period_yyyymm' => '202607',
            'usage_counters_applicable' => true,
            'quota_read_error' => null,
            'access_reason' => null,
            'installation_id' => 'inst-should-strip',
            'agenda_client_id' => 'client-should-strip',
            'items' => [
                [
                    'key' => 'deoia_email_sends',
                    'limit' => 30,
                    'consumed' => 5,
                    'remaining' => 25,
                    'can_consume' => true,
                    'at_limit' => false,
                    'exceeded' => false,
                    'installation_id' => 'nested-should-strip',
                ],
                [
                    'key' => 'unknown_quota_key',
                    'limit' => 999,
                    'remaining' => 999,
                ],
                [
                    'key' => 'deoia_ai_chat_queries',
                    'limit' => 30,
                    'consumed' => 0,
                    'remaining' => 30,
                    'can_consume' => true,
                    'at_limit' => false,
                    'exceeded' => false,
                ],
            ],
        ],
    ],
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
$benefit_quotas = $result['data']['account_status']['benefit_quotas'] ?? null;
ac(
    'benefit_quotas valid payload passes sanitized',
    !empty($result['success'])
        && is_array($benefit_quotas)
        && ($benefit_quotas['period_yyyymm'] ?? '') === '202607'
        && ($benefit_quotas['usage_counters_applicable'] ?? false) === true
        && count($benefit_quotas['items'] ?? []) === 2,
    wp_json_encode_safe($benefit_quotas)
);
ac(
    'benefit_quotas filters unknown item keys',
    ($benefit_quotas['items'][0]['key'] ?? '') === 'deoia_email_sends'
        && ($benefit_quotas['items'][1]['key'] ?? '') === 'deoia_ai_chat_queries',
    wp_json_encode_safe($benefit_quotas['items'] ?? [])
);
$forbidden_bq = forbidden_keys_in_payload($benefit_quotas ?? []);
ac(
    'benefit_quotas strips internal ids from payload',
    empty($forbidden_bq),
    implode(', ', $forbidden_bq)
);

// --- benefit_quotas malformed omitted ---
reset_options();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
Mock_Account_Status_Backend_Client::$response = [
    'ok' => true,
    'account_status' => [
        'plan_tier' => 'pro',
        'billing_state' => 'active',
        'effective_access_tier' => 'pro',
        'benefit_quotas' => [
            'period_yyyymm' => 'invalid-period',
            'items' => 'not-an-array',
        ],
    ],
];

$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client()))->execute();
ac(
    'malformed benefit_quotas does not break account-status',
    !empty($result['success'])
        && !array_key_exists('benefit_quotas', $result['data']['account_status'] ?? []),
    wp_json_encode_safe($result['data']['account_status'] ?? [])
);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
