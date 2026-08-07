<?php
/**
 * AC — ResolveShellAccessUseCase (free by default; subscribed only via backend).
 *
 * Ejecutar: php tests/application/legal/test-resolve-shell-access-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);

$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_test_can_manage_options'] = true;
$GLOBALS['aa_test_user_id'] = 7;
$GLOBALS['aa_test_transients'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return array_key_exists($key, $GLOBALS['aa_test_options'])
            ? $GLOBALS['aa_test_options'][$key]
            : $default;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap) {
        return $cap === 'manage_options' && !empty($GLOBALS['aa_test_can_manage_options']);
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return (int) ($GLOBALS['aa_test_user_id'] ?? 0);
    }
}
if (!function_exists('get_transient')) {
    function get_transient($key) {
        return array_key_exists($key, $GLOBALS['aa_test_transients'])
            ? $GLOBALS['aa_test_transients'][$key]
            : false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['aa_test_transients'][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['aa_test_transients'][$key]);
        return true;
    }
}
if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-legal-gate-backend-client.php';
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-terms-consent.php';
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-privacy-consent.php';
require_once $plugin_root . '/includes/domain/legal/class-aa-shell-access.php';
require_once $plugin_root . '/includes/application/legal/GetLegalGateStatusUseCase.php';
require_once $plugin_root . '/includes/application/legal/ResolveShellAccessUseCase.php';

final class Mock_Shell_Legal_Gate_Client extends AA_Legal_Gate_Backend_Client {
    /** @var array<string,mixed> */
    public static $status = ['ok' => false, 'code' => 'unset', 'error' => 'unset', 'http_status' => 500];
    /** @var int */
    public static $fetch_calls = 0;

    public function fetchStatus(): array {
        self::$fetch_calls++;
        return self::$status;
    }
}

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
        return;
    }
    $failed[] = $label;
    echo "[FAIL] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
}

function reset_shell_state(): void {
    $GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
    $GLOBALS['aa_test_can_manage_options'] = true;
    $GLOBALS['aa_test_user_id'] = 7;
    $GLOBALS['aa_test_transients'] = [];
    Mock_Shell_Legal_Gate_Client::$fetch_calls = 0;
    Mock_Shell_Legal_Gate_Client::$status = ['ok' => false, 'code' => 'unset', 'error' => 'unset', 'http_status' => 500];
}

function shell_uc(): ResolveShellAccessUseCase {
    return new ResolveShellAccessUseCase(
        new GetLegalGateStatusUseCase(new Mock_Shell_Legal_Gate_Client())
    );
}

reset_shell_state();
$GLOBALS['aa_test_options'] = ['aa_client_secret' => ''];
$r = shell_uc()->execute();
ac_assert('missing credentials → free', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_FREE);
ac_assert('missing credentials reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_MISSING_CREDENTIALS);
ac_assert('missing credentials skips backend', Mock_Shell_Legal_Gate_Client::$fetch_calls === 0);

reset_shell_state();
$GLOBALS['aa_test_options'] = ['aa_client_secret' => "  \t  "];
$r = shell_uc()->execute();
ac_assert('whitespace secret → missing_credentials', ($r['reason'] ?? '') === AA_Shell_Access::REASON_MISSING_CREDENTIALS);
ac_assert('whitespace secret skips backend', Mock_Shell_Legal_Gate_Client::$fetch_calls === 0);

reset_shell_state();
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => true,
    'status' => 'provisioning_request_missing',
    'subscription_active' => false,
    'privacy_accepted' => false,
    'terms_accepted' => false,
    'terms_document' => null,
    'privacy_document' => null,
];
$r = shell_uc()->execute();
ac_assert('no subscription → free', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_FREE);
ac_assert('no subscription reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_NO_SUBSCRIPTION);

reset_shell_state();
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => true,
    'status' => 'needs_terms',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
    'privacy_document' => null,
];
$r = shell_uc()->execute();
ac_assert('active + docs pending → legal_gate', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_LEGAL_GATE);
ac_assert('docs pending reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_DOCUMENTS_PENDING);
ac_assert('legal payload needs_terms', ($r['legal']['data']['status'] ?? '') === 'needs_terms');

reset_shell_state();
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => true,
    'status' => 'ready',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => true,
    'terms_document' => null,
    'privacy_document' => null,
];
$r = shell_uc()->execute();
ac_assert('active + accepted → full', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_FULL);
ac_assert('accepted reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_DOCUMENTS_ACCEPTED);

reset_shell_state();
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => false,
    'code' => 'legal_gate_backend_unreachable',
    'error' => 'timeout',
    'http_status' => 0,
];
$r = shell_uc()->execute();
ac_assert('transport error → free', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_FREE);
ac_assert('transport reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_TRANSPORT_ERROR);

reset_shell_state();
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => false,
    'code' => 'legal_gate_credentials_invalid',
    'error' => 'Invalid signature',
    'http_status' => 403,
];
$r = shell_uc()->execute();
ac_assert('invalid credentials → free', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_FREE);
ac_assert('invalid credentials reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_CREDENTIALS_INVALID);

reset_shell_state();
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => false,
    'code' => 'legal_gate_client_not_found',
    'error' => 'Client not found',
    'http_status' => 404,
];
$r = shell_uc()->execute();
ac_assert('installation missing → free', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_FREE);
ac_assert('installation missing reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_INSTALLATION_MISSING);

reset_shell_state();
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => true,
    'status' => 'ready',
    'privacy_accepted' => true,
    'terms_accepted' => true,
    'terms_document' => null,
    'privacy_document' => null,
];
$r = shell_uc()->execute();
ac_assert('missing subscription_active → free/unknown', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_FREE);
ac_assert('missing subscription_active reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_UNKNOWN);

reset_shell_state();
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => true,
    'status' => 'ready',
    'subscription_active' => 'yes',
    'privacy_accepted' => true,
    'terms_accepted' => true,
    'terms_document' => null,
    'privacy_document' => null,
];
$r = shell_uc()->execute();
ac_assert('invalid subscription_active → free/unknown', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_FREE);
ac_assert('invalid subscription_active reason', ($r['reason'] ?? '') === AA_Shell_Access::REASON_UNKNOWN);

reset_shell_state();
set_transient('aa_legal_gate_ready_7', '1');
Mock_Shell_Legal_Gate_Client::$status = [
    'ok' => true,
    'status' => 'needs_terms',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
    'privacy_document' => null,
];
$r = shell_uc()->execute();
ac_assert('ready transient does not skip backend', Mock_Shell_Legal_Gate_Client::$fetch_calls === 1);
ac_assert('ready transient does not force full', ($r['access'] ?? '') === AA_Shell_Access::ACCESS_LEGAL_GATE);

ac_assert(
    'pending is admitted constant',
    AA_Shell_Access::REASON_PENDING === 'pending'
);

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
