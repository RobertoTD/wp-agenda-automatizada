<?php
/**
 * AC — AA_Agenda_Access_Backend_Client mapping (C2).
 *
 *   php tests/infrastructure/backend/test-aa-agenda-access-backend-client-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

$GLOBALS['aa_options'] = ['aa_client_secret' => 'secret'];
$GLOBALS['aa_remote'] = null;
$GLOBALS['aa_remote_calls'] = 0;

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['aa_options'][$key] ?? $default;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function get_error_message() {
            return 'fail';
        }
    }
}

if (!function_exists('aa_send_authenticated_request')) {
    function aa_send_authenticated_request($endpoint, $method = 'POST', $data = []) {
        $GLOBALS['aa_remote_calls']++;
        $GLOBALS['aa_last_endpoint'] = $endpoint;
        $GLOBALS['aa_last_data'] = $data;
        return $GLOBALS['aa_remote'];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return (int) ($response['code'] ?? 0);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return (string) ($response['body'] ?? '');
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/infrastructure/backend/class-aa-agenda-access-backend-client.php';

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
}

$client = new AA_Agenda_Access_Backend_Client();

$GLOBALS['aa_remote'] = [
    'code' => 200,
    'body' => json_encode([
        'ok'         => true,
        'email'      => 'owner@example.com',
        'wp_user_id' => 7,
        'purpose'    => 'welcome',
    ]),
];
$GLOBALS['aa_remote_calls'] = 0;
$ok = $client->consume(str_repeat('A', 43));
ac('200 ok payload', !empty($ok['ok']) && $ok['email'] === 'owner@example.com' && $ok['wp_user_id'] === 7);
ac('hits consume path', strpos($GLOBALS['aa_last_endpoint'] ?? '', '/agenda/access/consume') !== false);
ac('single call', $GLOBALS['aa_remote_calls'] === 1);

$GLOBALS['aa_remote'] = ['code' => 401, 'body' => json_encode(['ok' => false, 'error' => 'invalid_or_expired'])];
$GLOBALS['aa_remote_calls'] = 0;
$bad = $client->consume(str_repeat('A', 43));
ac('401 mapped', empty($bad['ok']) && ($bad['code'] ?? '') === AA_Agenda_Access_Backend_Client::CODE_INVALID_OR_EXPIRED);

$GLOBALS['aa_remote'] = ['code' => 500, 'body' => '{}'];
$fail = $client->consume(str_repeat('A', 43));
ac('500 unavailable', empty($fail['ok']) && ($fail['code'] ?? '') === AA_Agenda_Access_Backend_Client::CODE_UNAVAILABLE);

$GLOBALS['aa_remote'] = new WP_Error();
$err = $client->consume(str_repeat('A', 43));
ac('WP_Error unavailable', empty($err['ok']) && ($err['code'] ?? '') === AA_Agenda_Access_Backend_Client::CODE_UNAVAILABLE);

$GLOBALS['aa_options']['aa_client_secret'] = '';
$GLOBALS['aa_remote_calls'] = 0;
$mis = $client->consume(str_repeat('A', 43));
ac('missing secret no network', $GLOBALS['aa_remote_calls'] === 0
    && ($mis['code'] ?? '') === AA_Agenda_Access_Backend_Client::CODE_NOT_CONFIGURED);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
