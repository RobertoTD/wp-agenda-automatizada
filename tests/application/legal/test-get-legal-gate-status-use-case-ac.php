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
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-privacy-consent.php';
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
        if (stripos($json, '"' . $key . '"') !== false) {
            $found[] = $key;
        }
    }
    return $found;
}

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'ready',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => true,
    'terms_document' => null,
];
$uc = new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client());
$r1 = $uc->execute();
$r2 = $uc->execute();
ac_assert('ready succeeds', !empty($r1['success']) && ($r1['data']['status'] ?? '') === 'ready');
ac_assert('ready always re-queries backend', Mock_Legal_Gate_Backend_Client::$fetch_calls === 2);
ac_assert('ready exposes subscription_active', ($r1['data']['subscription_active'] ?? null) === true);
ac_assert('ready payload has no secrets/ids', forbidden_in($r1) === []);

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_terms',
    'subscription_active' => true,
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
ac_assert('needs_terms dual flag off', empty($r['data']['can_accept_privacy_and_terms']));
ac_assert('needs_terms exposes version', ($r['data']['terms_document']['version'] ?? '') === '2026-08-03.1');
ac_assert('needs_terms does not write ready transient', get_transient('aa_legal_gate_ready_7') === false);
ac_assert('needs_terms no secret ids', forbidden_in($r) === []);

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_privacy_and_terms',
    'subscription_active' => true,
    'privacy_accepted' => false,
    'terms_accepted' => false,
    'privacy_document' => [
        'version' => '2026-08-04.1',
        'human_url' => 'https://deoia.com/politica-de-privacidad/',
    ],
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://cdn.example/terms-v1.html',
    ],
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert(
    'needs_privacy_and_terms succeeds',
    !empty($r['success']) && ($r['data']['status'] ?? '') === 'needs_privacy_and_terms'
);
ac_assert('dual can_accept_privacy_and_terms for admin', !empty($r['data']['can_accept_privacy_and_terms']));
ac_assert('dual does not set can_accept_terms', empty($r['data']['can_accept_terms']));
ac_assert(
    'dual parses privacy document',
    ($r['data']['privacy_document']['version'] ?? '') === '2026-08-04.1'
    && ($r['data']['privacy_document']['human_url'] ?? '') === 'https://deoia.com/politica-de-privacidad/'
);
ac_assert(
    'dual keeps status terms human_url without local swap',
    ($r['data']['terms_document']['human_url'] ?? '') === 'https://cdn.example/terms-v1.html'
);
ac_assert('dual does not write ready transient', get_transient('aa_legal_gate_ready_7') === false);
ac_assert('dual no secret ids', forbidden_in($r) === []);

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_privacy_and_terms',
    'subscription_active' => true,
    'privacy_accepted' => false,
    'terms_accepted' => false,
    'privacy_document' => [
        'version' => '2026-08-04.1',
        'human_url' => '',
    ],
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert(
    'dual incomplete privacy meta fails',
    empty($r['success']) && ($r['error']['code'] ?? '') === 'legal_gate_backend_invalid_response'
);

reset_gate_state();
$GLOBALS['aa_test_can_manage_options'] = false;
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_terms',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert('non-admin cannot accept terms', empty($r['data']['can_accept_terms']));

reset_gate_state();
$GLOBALS['aa_test_can_manage_options'] = false;
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_privacy_and_terms',
    'subscription_active' => true,
    'privacy_accepted' => false,
    'terms_accepted' => false,
    'privacy_document' => [
        'version' => '2026-08-04.1',
        'human_url' => 'https://deoia.com/politica-de-privacidad/',
    ],
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert('non-admin cannot accept dual', empty($r['data']['can_accept_privacy_and_terms']));

foreach (['privacy_required', 'provisioning_request_missing'] as $status) {
    reset_gate_state();
    Mock_Legal_Gate_Backend_Client::$status = [
        'ok' => true,
        'status' => $status,
        'subscription_active' => true,
        'privacy_accepted' => $status !== 'privacy_required',
        'terms_accepted' => false,
        'terms_document' => null,
        'privacy_document' => null,
    ];
    $r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
    ac_assert("{$status} succeeds", !empty($r['success']) && ($r['data']['status'] ?? '') === $status);
    ac_assert("{$status} cannot accept terms", empty($r['data']['can_accept_terms']));
    ac_assert("{$status} cannot accept dual", empty($r['data']['can_accept_privacy_and_terms']));
}

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => false,
    'code' => 'legal_gate_backend_unreachable',
    'error' => 'timeout',
    'http_status' => 0,
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert('backend error surfaces', empty($r['success']) && ($r['error']['code'] ?? '') === 'legal_gate_backend_unreachable');

reset_gate_state();
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'weird_future_status',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => null,
];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert('unknown status fails', empty($r['success']) && ($r['error']['code'] ?? '') === 'legal_gate_unknown_status');

reset_gate_state();
set_transient('aa_legal_gate_ready_7', '1');
Mock_Legal_Gate_Backend_Client::$status = [
    'ok' => true,
    'status' => 'needs_terms',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
];
$uc = new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client());
$with_stale = $uc->execute(false);
$forced = $uc->execute(true);
ac_assert('stale ready transient ignored', ($with_stale['data']['status'] ?? '') === 'needs_terms');
ac_assert('force refresh flag still queries', ($forced['data']['status'] ?? '') === 'needs_terms');
ac_assert('backend called twice despite transient', Mock_Legal_Gate_Backend_Client::$fetch_calls === 2);

reset_gate_state();
$GLOBALS['aa_test_options'] = ['aa_client_secret' => '   '];
$r = (new GetLegalGateStatusUseCase(new Mock_Legal_Gate_Backend_Client()))->execute();
ac_assert(
    'trimmed empty secret not configured',
    empty($r['success']) && ($r['error']['code'] ?? '') === 'legal_gate_backend_not_configured'
);
ac_assert('trimmed empty secret skips fetch', Mock_Legal_Gate_Backend_Client::$fetch_calls === 0);

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
