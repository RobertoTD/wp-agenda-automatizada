<?php
/**
 * AC — GetLegalGateStatusUseCase.
 *
 * Ejecutar: php tests/application/legal/test-get-legal-gate-status-use-case-ac.php
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
require_once $plugin_root . '/includes/application/legal/GetLegalGateStatusUseCase.php';

final class Mock_Legal_Gate_Backend_Client extends AA_Legal_Gate_Backend_Client {
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

function reset_gate_state(): void {
    $GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
    $GLOBALS['aa_test_can_manage_options'] = true;
    $GLOBALS['aa_test_user_id'] = 7;
    $GLOBALS['aa_test_transients'] = [];
    Mock_Legal_Gate_Backend_Client::$fetch_calls = 0;
    Mock_Legal_Gate_Backend_Client::$status = ['ok' => false, 'code' => 'unset', 'error' => 'unset', 'http_status' => 500];
}

function forbidden_in(array $payload): array {
    $json = (string) json_encode($payload);
    $keys = [
        'account_id', 'installation_id', 'subscription_request_id',
        'client_secret', 'aa_client_secret', 'hmac', 'token',
    ];
    $found = [];
    foreach ($keys as $key) {
        if (stripos($json, '"' . $key . '"') !== false || stripos($json, $key) !== false) {
            // Allow false positives on message text carefully — require JSON key quotes.
            if (stripos($json, '"' . $key . '"') !== false) {
                $found[] = $key;
            }
        }
    }
    return $found;
}

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'ready',
    'privacy_accepted' => true,
    'terms_accepted' => true,
    'terms_document' => null,
];
$uc = new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client());
$r1 = $uc->execute();
$r2 = $uc->execute();
ac_assert('ready succeeds', !empty($r1['success']) && ($r1['data']['status'] ?? '') === 'ready');
ac_assert('ready caches second call', Mock_Legal_Gate_Backend_Client::$fetch_calls === 1);
ac_assert('cached ready still ready', !empty($r2['success']) && ($r2['data']['status'] ?? '') === 'ready');
ac_assert('ready payload has no secrets/ids', forbidden_in($r1) === []);

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_terms',
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
];
$uc = new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client());
$r = $uc->execute();
ac_assert('needs_terms succeeds', !empty($r['success']) && ($r['data']['status'] ?? '') === 'needs_terms');
ac_assert('needs_terms can_accept for admin', !empty($r['data']['can_accept_terms']));
ac_assert('needs_terms exposes version', ($r['data']['terms_document']['version'] ?? '') === '2026-08-03.1');
ac_assert('needs_terms not cached as ready', get_transient('aa_legal_gate_ready_7') === false);
ac_assert('needs_terms no secret ids', forbidden_in($r) === []);

reset_gate_state();
$GLOBALS['aa_test_can_manage_options'] = false;
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_terms',
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert('non-admin cannot accept', empty($r['data']['can_accept_terms']));

foreach (['privacy_required', 'provisioning_request_missing'] as $status) {
    reset_gate_state();
    Mock_Legal_Gate_Backend_Client::$status = [
        'ok' => true,
        'status' => $status,
        'privacy_accepted' => $status !== 'privacy_required',
        'terms_accepted' => false,
        'terms_document' => null,
    ];
    $r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
    ac_assert("{$status} succeeds", !empty($r['success']) && ($r['data']['status'] ?? '') === $status);
    ac_assert("{$status} cannot accept", empty($r['data']['can_accept_terms']));
}

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => false,
    'code' => 'legal_gate_backend_unreachable',
    'error' => 'timeout',
    'http_status' => 0,
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert('backend error fails closed', empty($r['success']) && ($r['error']['code'] ?? '') === 'legal_gate_backend_unreachable');

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'weird_future_status',
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => null,
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert('unknown status fails closed', empty($r['success']) && ($r['error']['code'] ?? '') === 'legal_gate_unknown_status');

reset_gate_state();
set_transient('aa_legal_gate_ready_7', '1');
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_terms',
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
];
$uc = new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client());
$cached = $uc->execute(false);
$forced = $uc->execute(true);
ac_assert('force refresh bypasses ready cache', ($cached['data']['status'] ?? '') === 'ready');
ac_assert('force refresh sees needs_terms', ($forced['data']['status'] ?? '') === 'needs_terms');
ac_assert('force refresh called backend once', Mock_Legal_Gate_Backend_Client::$fetch_calls === 1);

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
