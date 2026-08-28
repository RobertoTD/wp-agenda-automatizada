<?php
/**
 * AC — RequestAgendaAccessLinkUseCase (C3B).
 *
 *   php tests/application/auth/test-request-agenda-access-link-use-case-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_options'] = ['aa_client_secret' => 'secret'];
$GLOBALS['aa_blog_id'] = 7;

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['aa_options'][$key] ?? $default;
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        return (int) $GLOBALS['aa_blog_id'];
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['aa_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['aa_transients'][$key] = $value;
        $GLOBALS['aa_last_transient_ttl'] = (int) $expiration;

        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('aa_send_authenticated_request')) {
    function aa_send_authenticated_request($endpoint, $method = 'POST', $data = []) {
        $GLOBALS['aa_remote_calls'] = ($GLOBALS['aa_remote_calls'] ?? 0) + 1;
        $GLOBALS['aa_last_endpoint'] = $endpoint;
        $GLOBALS['aa_last_data'] = $data;

        return $GLOBALS['aa_remote'] ?? ['code' => 200, 'body' => '{"ok":true}'];
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

$root = dirname(__DIR__, 3);
require_once $root . '/includes/infrastructure/backend/class-aa-agenda-access-backend-client.php';
require_once $root . '/includes/application/auth/RequestAgendaAccessLinkUseCase.php';

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

$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_remote_calls'] = 0;
$GLOBALS['aa_remote'] = ['code' => 200, 'body' => '{"ok":true}'];
$uc = new RequestAgendaAccessLinkUseCase();
$r1 = $uc->execute();
ac('happy path status attempted', ($r1['status'] ?? '') === RequestAgendaAccessLinkUseCase::STATUS_ATTEMPTED);
ac('exactly one HMAC', ($r1['hmac_calls'] ?? 0) === 1 && $GLOBALS['aa_remote_calls'] === 1);
ac('hits request path', strpos((string) ($GLOBALS['aa_last_endpoint'] ?? ''), '/agenda/access/request') !== false);
ac('empty body data', ($GLOBALS['aa_last_data'] ?? null) === []);
ac('transient key includes blog id', isset($GLOBALS['aa_transients']['aa_agenda_access_req_7']));
ac('transient TTL 60', ($GLOBALS['aa_last_transient_ttl'] ?? 0) === 60);

$GLOBALS['aa_remote_calls'] = 0;
$uc2 = new RequestAgendaAccessLinkUseCase();
$r2 = $uc2->execute();
ac('active transient skips HMAC', ($r2['status'] ?? '') === RequestAgendaAccessLinkUseCase::STATUS_SKIPPED_TRANSIENT
    && ($r2['hmac_calls'] ?? -1) === 0
    && $GLOBALS['aa_remote_calls'] === 0);

$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_remote_calls'] = 0;
$GLOBALS['aa_remote'] = ['code' => 429, 'body' => '{"ok":true}'];
$uc3 = new RequestAgendaAccessLinkUseCase();
$r3 = $uc3->execute();
ac('rate-limit still one call', ($r3['hmac_calls'] ?? 0) === 1 && $GLOBALS['aa_remote_calls'] === 1);

$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_remote_calls'] = 0;
$GLOBALS['aa_remote'] = ['code' => 500, 'body' => '{}'];
$uc4 = new RequestAgendaAccessLinkUseCase();
$r4 = $uc4->execute();
ac('http error still one call', ($r4['hmac_calls'] ?? 0) === 1 && $GLOBALS['aa_remote_calls'] === 1);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
