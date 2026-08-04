<?php
/**
 * AC — LegalGateAjax wiring + handler guards.
 *
 * Ejecutar: php tests/http/ajax/test-legal-gate-ajax-ac.php
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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/LegalGateAjax.php');
$bootstrap_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$client_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/backend/class-aa-legal-gate-backend-client.php');

ac_assert('registers aa_get_legal_gate_status', strpos($ajax_src, 'aa_get_legal_gate_status') !== false);
ac_assert('registers aa_accept_agenda_terms', strpos($ajax_src, 'aa_accept_agenda_terms') !== false);
ac_assert('uses aa_legal_gate_nonce', strpos($ajax_src, 'aa_legal_gate_nonce') !== false);
ac_assert('accept checks manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('no nopriv', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('does not read account_id from request', strpos($ajax_src, 'account_id') === false);
ac_assert('does not read installation_id from request', strpos($ajax_src, 'installation_id') === false);
ac_assert('does not read subscription_request_id from request', strpos($ajax_src, 'subscription_request_id') === false);
ac_assert('does not trust wp_user_id from POST', strpos($ajax_src, "\$_POST['wp_user_id']") === false);
ac_assert('status forces refresh', strpos($ajax_src, 'execute(true)') !== false);
ac_assert('bootstrap registers LegalGateAjax', strpos($bootstrap_src, 'LegalGateAjax::register()') !== false);
ac_assert('client hits legal-gate-status', strpos($client_src, '/oauth/legal-gate-status') !== false);
ac_assert('client hits legal-acceptances/terms', strpos($client_src, '/oauth/legal-acceptances/terms') !== false);
ac_assert('client uses aa_send_authenticated_request', strpos($client_src, 'aa_send_authenticated_request') !== false);
ac_assert(
    'handler reads only consent + version',
    strpos($ajax_src, "\$_POST['terms_consent']") !== false
    && strpos($ajax_src, "\$_POST['terms_document_version']") !== false
);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['aa_test_json'] = null;
$GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
$GLOBALS['aa_test_can_manage_options'] = true;
$GLOBALS['aa_test_nonce_valid'] = true;
$GLOBALS['aa_test_logged_in'] = true;
$GLOBALS['aa_test_user_id'] = 5;
$GLOBALS['aa_test_transients'] = [];
$GLOBALS['aa_test_hmac_calls'] = [];
$GLOBALS['aa_test_hmac_mode'] = 'status_needs_terms';

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return !empty($GLOBALS['aa_test_logged_in']);
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return $capability === 'manage_options' && !empty($GLOBALS['aa_test_can_manage_options']);
    }
}
if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg) {
        if (empty($GLOBALS['aa_test_nonce_valid'])) {
            throw new RuntimeException('bad_nonce');
        }
    }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = ['success' => true, 'data' => $data, 'status' => $status_code];
        throw new RuntimeException('json_sent');
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = ['success' => false, 'data' => $data, 'status' => $status_code];
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
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return array_key_exists($key, $GLOBALS['aa_test_options'])
            ? $GLOBALS['aa_test_options'][$key]
            : $default;
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return (int) ($GLOBALS['aa_test_user_id'] ?? 0);
    }
}
if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['aa_test_transients'][$key] ?? false;
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
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return (int) ($response['response']['code'] ?? 200);
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return (string) ($response['body'] ?? '');
    }
}
if (!function_exists('aa_send_authenticated_request')) {
    function aa_send_authenticated_request($endpoint, $method = 'GET', $body = null) {
        $GLOBALS['aa_test_hmac_calls'][] = [
            'endpoint' => (string) $endpoint,
            'method'   => (string) $method,
            'body'     => $body,
        ];

        $mode = (string) ($GLOBALS['aa_test_hmac_mode'] ?? 'status_needs_terms');
        if ($mode === 'accept_ok') {
            return [
                'response' => ['code' => 200],
                'body'     => json_encode([
                    'ok'               => true,
                    'already_accepted' => false,
                    'document_version' => '2026-08-03.1',
                    'source'           => 'agenda_legal_gate',
                ]),
            ];
        }

        return [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'ok'               => true,
                'status'           => 'needs_terms',
                'privacy_accepted' => true,
                'terms_accepted'   => false,
                'terms_document'   => [
                    'version'   => '2026-08-03.1',
                    'human_url' => 'https://deoia.com/terminos/',
                ],
            ]),
        ];
    }
}
if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-legal-gate-backend-client.php';
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-terms-consent.php';
require_once $plugin_root . '/includes/application/legal/GetLegalGateStatusUseCase.php';
require_once $plugin_root . '/includes/application/legal/AcceptAgendaTermsUseCase.php';
require_once $plugin_root . '/includes/http/ajax/LegalGateAjax.php';

function run_handler(callable $fn): array {
    $GLOBALS['aa_test_json'] = null;
    try {
        $fn();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent' && $e->getMessage() !== 'bad_nonce') {
            throw $e;
        }
    }
    return $GLOBALS['aa_test_json'] ?? [];
}

$GLOBALS['aa_test_hmac_calls'] = [];
$GLOBALS['aa_test_hmac_mode'] = 'status_needs_terms';
$_POST = [];
$out = run_handler([LegalGateAjax::class, 'handleStatus']);
ac_assert('status returns needs_terms', !empty($out['success']) && ($out['data']['status'] ?? '') === 'needs_terms');
ac_assert('status HMAC called once', count($GLOBALS['aa_test_hmac_calls']) === 1);
ac_assert('status response has no account_id', strpos((string) json_encode($out), '"account_id"') === false);
ac_assert('status response has no client_secret', strpos((string) json_encode($out), 'client_secret') === false);

$GLOBALS['aa_test_can_manage_options'] = false;
$out = run_handler([LegalGateAjax::class, 'handleAccept']);
ac_assert('accept without cap is 403', empty($out['success']) && (int) ($out['status'] ?? 0) === 403);

$GLOBALS['aa_test_can_manage_options'] = true;
$GLOBALS['aa_test_nonce_valid'] = false;
$threw = false;
try {
    LegalGateAjax::handleAccept();
} catch (RuntimeException $e) {
    $threw = $e->getMessage() === 'bad_nonce';
}
ac_assert('accept invalid nonce dies', $threw);
$GLOBALS['aa_test_nonce_valid'] = true;

$GLOBALS['aa_test_hmac_calls'] = [];
$GLOBALS['aa_test_hmac_mode'] = 'accept_ok';
$GLOBALS['aa_test_transients'] = ['aa_legal_gate_ready_5' => '1'];
$_POST = [
    'terms_consent'          => '1',
    'terms_document_version' => '2026-08-03.1',
    'wp_user_id'             => '999',
    'account_id'             => 'acc_x',
    'installation_id'        => 'inst_x',
    'subscription_request_id'=> 'sr_x',
];
$out = run_handler([LegalGateAjax::class, 'handleAccept']);
ac_assert('accept succeeds', !empty($out['success']));
ac_assert('accept HMAC called once', count($GLOBALS['aa_test_hmac_calls']) === 1);
$sent = $GLOBALS['aa_test_hmac_calls'][0]['body'] ?? [];
ac_assert('accept sends PHP wp_user_id=5', (int) ($sent['wp_user_id'] ?? 0) === 5);
ac_assert('accept sends shown version', ($sent['terms_document_version'] ?? '') === '2026-08-03.1');
ac_assert('accept sends terms_consent true', !empty($sent['terms_consent']));
ac_assert('accept body omits account_id', !array_key_exists('account_id', is_array($sent) ? $sent : []));
ac_assert('accept body omits installation_id', !array_key_exists('installation_id', is_array($sent) ? $sent : []));
ac_assert('accept body omits subscription_request_id', !array_key_exists('subscription_request_id', is_array($sent) ? $sent : []));
ac_assert('accept clears ready cache', get_transient('aa_legal_gate_ready_5') === false);

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
