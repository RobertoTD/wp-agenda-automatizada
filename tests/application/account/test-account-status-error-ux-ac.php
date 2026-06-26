<?php
/**
 * Harness standalone de `AA_Account_Status_Error_Ux`.
 *
 *   php tests/application/account/test-account-status-error-ux-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $args, string $url): string {
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . http_build_query($args, '', '&');
    }
}

require_once __DIR__ . '/../../../includes/application/account/Account_Status_Error_Ux.php';
require_once __DIR__ . '/../../../includes/application/ai/AI_Setup_Action_Link_Builder.php';

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' - ' . $detail) . "\n";
}

function msg_has_no_technical_terms(string $message): bool {
    $lower = strtolower($message);
    $forbidden = ['client secret', 'backend', 'installation_id', 'token', 'endpoint'];
    foreach ($forbidden as $term) {
        if (strpos($lower, $term) !== false) {
            return false;
        }
    }
    return true;
}

$technical = 'Falta el client secret del backend para conectar.';

// 1. account_backend_not_configured + missing_client_secret
$msg = AA_Account_Status_Error_Ux::user_message_for_code(
    'account_backend_not_configured',
    ['reason' => AA_Account_Status_Error_Ux::REASON_MISSING_CLIENT_SECRET]
);
ac(
    'secret vacío → copy de vinculación',
    $msg === AA_Account_Status_Error_Ux::MSG_REQUIRES_LINK,
    $msg
);
ac(
    'secret vacío sin términos técnicos',
    msg_has_no_technical_terms($msg),
    $msg
);
$actions = AA_Account_Status_Error_Ux::actions_for_code(
    'account_backend_not_configured',
    ['reason' => AA_Account_Status_Error_Ux::REASON_MISSING_CLIENT_SECRET]
);
ac(
    'secret vacío incluye action Vincular cuenta',
    count($actions) === 1
        && ($actions[0]['label'] ?? '') === 'Vincular cuenta'
        && strpos($actions[0]['url'] ?? '', 'setup_focus=google_calendar') !== false,
    json_encode($actions, JSON_UNESCAPED_SLASHES)
);

// account_backend_not_configured sin reason de secret → temporal
$msg = AA_Account_Status_Error_Ux::user_message_for_code('account_backend_not_configured', []);
ac(
    'not_configured sin secret reason → copy temporal',
    $msg === AA_Account_Status_Error_Ux::MSG_TEMPORARY_UNAVAILABLE,
    $msg
);
ac(
    'not_configured sin secret reason → sin action',
    AA_Account_Status_Error_Ux::actions_for_code('account_backend_not_configured', []) === [],
    ''
);

// 2. account_client_not_found
$msg = AA_Account_Status_Error_Ux::user_message_for_code('account_client_not_found', []);
ac(
    'account_client_not_found → copy de vinculación',
    $msg === AA_Account_Status_Error_Ux::MSG_REQUIRES_LINK,
    $msg
);
$actions = AA_Account_Status_Error_Ux::actions_for_code('account_client_not_found', []);
ac(
    'account_client_not_found incluye action',
    count($actions) === 1 && ($actions[0]['label'] ?? '') === 'Vincular cuenta',
    json_encode($actions, JSON_UNESCAPED_UNICODE)
);

// 3. account_backend_unreachable
$msg = AA_Account_Status_Error_Ux::user_message_for_code('account_backend_unreachable', []);
ac(
    'account_backend_unreachable → copy temporal',
    $msg === AA_Account_Status_Error_Ux::MSG_TEMPORARY_UNAVAILABLE,
    $msg
);
ac(
    'account_backend_unreachable sin action',
    AA_Account_Status_Error_Ux::actions_for_code('account_backend_unreachable', []) === [],
    ''
);

// 4. account_backend_invalid_response
$msg = AA_Account_Status_Error_Ux::user_message_for_code('account_backend_invalid_response', []);
ac(
    'account_backend_invalid_response → copy incompleto',
    $msg === AA_Account_Status_Error_Ux::MSG_INCOMPLETE,
    $msg
);
ac(
    'account_backend_invalid_response sin action',
    AA_Account_Status_Error_Ux::actions_for_code('account_backend_invalid_response', []) === [],
    ''
);

// 5. Regresión use case: billing_state missing sigue siendo success
if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
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

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://tenant.example.test' . $path;
    }
}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/site/class-aa-public-site-status.php';
require_once $root . '/includes/domain/tenant/class-aa-installation-provisioning-detector.php';
require_once $root . '/includes/infrastructure/wp/PublicSitePreview.php';
require_once $root . '/includes/infrastructure/backend/class-aa-account-status-backend-client.php';
require_once $root . '/includes/application/account/GetAccountStatusUseCase.php';

final class Mock_Account_Status_Backend_Client_Missing_Sub extends AA_Account_Status_Backend_Client {
    public function fetch(): array {
        return [
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
    }
}

$GLOBALS['aa_test_options']['aa_client_secret'] = 'test-secret';
$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client_Missing_Sub()))->execute();
ac(
    'billing_state missing sigue como success',
    !empty($result['success'])
        && ($result['data']['account_status']['billing_state'] ?? '') === 'missing'
        && ($result['data']['account_status']['effective_access_tier'] ?? '') === 'freemium',
    json_encode($result, JSON_UNESCAPED_UNICODE)
);

// Use case sin secret devuelve reason missing_client_secret
$GLOBALS['aa_test_options'] = [];
$result = (new GetAccountStatusUseCase(new Mock_Account_Status_Backend_Client_Missing_Sub()))->execute();
ac(
    'use case sin secret incluye reason missing_client_secret',
    empty($result['success'])
        && ($result['error']['code'] ?? '') === 'account_backend_not_configured'
        && ($result['error']['reason'] ?? '') === 'missing_client_secret',
    json_encode($result, JSON_UNESCAPED_UNICODE)
);

echo "\n{$passed}/{$total} acceptance checks passed.\n";
exit($passed === $total ? 0 : 1);
