<?php
/**
 * AC — ConsumeAgendaAccessTokenUseCase (C2).
 *
 *   php tests/application/auth/test-consume-agenda-access-token-use-case-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!class_exists('WP_User')) {
    class WP_User {
        public $ID;
        public $user_email;
        public $user_login;
        public function __construct($id = 1, $email = 'a@b.c', $login = 'owner') {
            $this->ID = $id;
            $this->user_email = $email;
            $this->user_login = $login;
        }
    }
}

$GLOBALS['aa_users_by_id'] = [];
$GLOBALS['aa_users_by_email'] = [];
$GLOBALS['aa_members'] = [];
$GLOBALS['aa_caps'] = [];
$GLOBALS['aa_current_user_id'] = 0;
$GLOBALS['aa_logged_in'] = false;
$GLOBALS['aa_logout_count'] = 0;
$GLOBALS['aa_options'] = ['aa_client_secret' => 'secret'];
$GLOBALS['aa_filters'] = [];
$GLOBALS['aa_auth_cookie'] = null;

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['aa_options'][$key] ?? $default;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        if ($field === 'id') {
            return $GLOBALS['aa_users_by_id'][(int) $value] ?? false;
        }
        if ($field === 'email') {
            return $GLOBALS['aa_users_by_email'][strtolower(trim((string) $value))] ?? false;
        }
        return false;
    }
}

if (!function_exists('is_user_member_of_blog')) {
    function is_user_member_of_blog($user_id, $blog_id) {
        return !empty($GLOBALS['aa_members'][(int) $user_id][(int) $blog_id]);
    }
}

if (!function_exists('user_can')) {
    function user_can($user_id, $cap) {
        return !empty($GLOBALS['aa_caps'][(int) $user_id][$cap]);
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        return 3;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return (int) $GLOBALS['aa_current_user_id'];
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return !empty($GLOBALS['aa_logged_in']);
    }
}

if (!function_exists('wp_logout')) {
    function wp_logout() {
        $GLOBALS['aa_logout_count']++;
        $GLOBALS['aa_logged_in'] = false;
        $GLOBALS['aa_current_user_id'] = 0;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://tenant.example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('aa_app_login_url')) {
    function aa_app_login_url(string $redirect_to): string {
        return 'https://tenant.example.com/wp-login.php?deoia_app_login=1&redirect_to=' . rawurlencode($redirect_to);
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value = null, $url = null) {
        $sep = (strpos((string) $url, '?') === false) ? '?' : '&';
        return $url . $sep . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url) {
        return parse_url($url);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted = 1) {
        $GLOBALS['aa_filters'][$hook][$priority][] = $callback;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($hook, $callback, $priority = 10) {
        if (empty($GLOBALS['aa_filters'][$hook][$priority])) {
            return;
        }
        $GLOBALS['aa_filters'][$hook][$priority] = array_values(array_filter(
            $GLOBALS['aa_filters'][$hook][$priority],
            static fn($cb) => $cb !== $callback
        ));
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($id) {
        $GLOBALS['aa_current_user_id'] = (int) $id;
        $GLOBALS['aa_logged_in'] = $id > 0;
    }
}

if (!function_exists('wp_set_auth_cookie')) {
    function wp_set_auth_cookie($user_id, $remember = false) {
        $length = 14 * 86400;
        if (!empty($GLOBALS['aa_filters']['auth_cookie_expiration'][99])) {
            foreach ($GLOBALS['aa_filters']['auth_cookie_expiration'][99] as $cb) {
                $length = $cb($length, $user_id, $remember);
            }
        }
        $GLOBALS['aa_auth_cookie'] = [
            'user_id'  => (int) $user_id,
            'remember' => (bool) $remember,
            'length'   => (int) $length,
        ];
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
    }
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/infrastructure/backend/class-aa-agenda-access-backend-client.php';
require_once $root . '/includes/application/auth/ConsumeAgendaAccessTokenUseCase.php';

final class Mock_Agenda_Access_Backend_Client extends AA_Agenda_Access_Backend_Client {
    /** @var list<string> */
    public static $tokens = [];

    /** @var array<string,mixed> */
    public static $response = ['ok' => false, 'code' => 'unavailable', 'http_status' => 500];

    public function consume(string $token): array {
        self::$tokens[] = $token;
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
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
}

function seed_owner(): void {
    $u = new WP_User(7, 'owner@example.com', 'owner');
    $GLOBALS['aa_users_by_id'][7] = $u;
    $GLOBALS['aa_users_by_email']['owner@example.com'] = $u;
    $GLOBALS['aa_members'][7][3] = true;
    $GLOBALS['aa_caps'][7]['manage_options'] = true;
}

function valid_token(): string {
    return str_repeat('A', 43);
}

seed_owner();

// Invalid format — no backend call
Mock_Agenda_Access_Backend_Client::$tokens = [];
$uc = new ConsumeAgendaAccessTokenUseCase(new Mock_Agenda_Access_Backend_Client());
$out = $uc->execute('short');
ac('invalid format does not call backend', $uc->get_consume_call_count() === 0 && empty(Mock_Agenda_Access_Backend_Client::$tokens));
ac('invalid format errors to login', ($out['status'] ?? '') === 'error' && strpos($out['redirect_url'] ?? '', 'aa_agenda_access_error=1') !== false);

// Success path
Mock_Agenda_Access_Backend_Client::$tokens = [];
Mock_Agenda_Access_Backend_Client::$response = [
    'ok'         => true,
    'email'      => 'owner@example.com',
    'wp_user_id' => 7,
    'purpose'    => 'welcome',
];
$uc = new ConsumeAgendaAccessTokenUseCase(new Mock_Agenda_Access_Backend_Client());
$out = $uc->execute(valid_token());
ac('success status', ($out['status'] ?? '') === ConsumeAgendaAccessTokenUseCase::STATUS_SUCCESS);
ac('single consume call', $uc->get_consume_call_count() === 1 && count(Mock_Agenda_Access_Backend_Client::$tokens) === 1);
ac('cookie remember 30d', ($GLOBALS['aa_auth_cookie']['remember'] ?? false) === true
    && ($GLOBALS['aa_auth_cookie']['length'] ?? 0) === AA_Agenda_Access_Session_Issuer::REMEMBER_TTL_SECONDS);
ac('filter removed after success', !AA_Agenda_Access_Session_Issuer::is_expiration_filter_active());
ac('redirect allowlisted calendar', strpos($out['redirect_url'] ?? '', 'module=calendar') !== false);

// 401
Mock_Agenda_Access_Backend_Client::$response = [
    'ok'          => false,
    'code'        => AA_Agenda_Access_Backend_Client::CODE_INVALID_OR_EXPIRED,
    'http_status' => 401,
];
$GLOBALS['aa_logged_in'] = false;
$GLOBALS['aa_current_user_id'] = 0;
$uc = new ConsumeAgendaAccessTokenUseCase(new Mock_Agenda_Access_Backend_Client());
$out = $uc->execute(valid_token());
ac('401 maps to error login', ($out['status'] ?? '') === 'error' && empty($out['soft_success'])
    && strpos($out['redirect_url'] ?? '', 'aa_agenda_access_error=1') !== false);
ac('401 still single call', $uc->get_consume_call_count() === 1);

// Ambiguous / unavailable — no retry
Mock_Agenda_Access_Backend_Client::$response = [
    'ok'          => false,
    'code'        => AA_Agenda_Access_Backend_Client::CODE_UNAVAILABLE,
    'http_status' => 500,
];
$uc = new ConsumeAgendaAccessTokenUseCase(new Mock_Agenda_Access_Backend_Client());
$out = $uc->execute(valid_token());
ac('ambiguous no retry', $uc->get_consume_call_count() === 1);
ac('ambiguous error', ($out['status'] ?? '') === 'error');

// Soft success when already admin and token used
$GLOBALS['aa_logged_in'] = true;
$GLOBALS['aa_current_user_id'] = 7;
Mock_Agenda_Access_Backend_Client::$response = [
    'ok'          => false,
    'code'        => AA_Agenda_Access_Backend_Client::CODE_INVALID_OR_EXPIRED,
    'http_status' => 401,
];
$uc = new ConsumeAgendaAccessTokenUseCase(new Mock_Agenda_Access_Backend_Client());
$out = $uc->execute(valid_token());
ac('already admin + 401 soft-redirects calendar', !empty($out['soft_success'])
    && strpos($out['redirect_url'] ?? '', 'module=calendar') !== false);

// Other user logged in → logout then switch
$GLOBALS['aa_logout_count'] = 0;
$GLOBALS['aa_logged_in'] = true;
$GLOBALS['aa_current_user_id'] = 99;
$other = new WP_User(99, 'other@example.com', 'other');
$GLOBALS['aa_users_by_id'][99] = $other;
$GLOBALS['aa_members'][99][3] = true;
$GLOBALS['aa_caps'][99]['manage_options'] = true;
Mock_Agenda_Access_Backend_Client::$response = [
    'ok'         => true,
    'email'      => 'owner@example.com',
    'wp_user_id' => 7,
    'purpose'    => 'welcome',
];
$uc = new ConsumeAgendaAccessTokenUseCase(new Mock_Agenda_Access_Backend_Client());
$out = $uc->execute(valid_token());
ac('switches user after logout', ($out['status'] ?? '') === 'success' && $GLOBALS['aa_logout_count'] === 1
    && ($GLOBALS['aa_auth_cookie']['user_id'] ?? 0) === 7);

// ID/email mismatch from backend payload
Mock_Agenda_Access_Backend_Client::$response = [
    'ok'         => true,
    'email'      => 'wrong@example.com',
    'wp_user_id' => 7,
    'purpose'    => 'welcome',
];
$GLOBALS['aa_logged_in'] = false;
$GLOBALS['aa_current_user_id'] = 0;
$uc = new ConsumeAgendaAccessTokenUseCase(new Mock_Agenda_Access_Backend_Client());
$out = $uc->execute(valid_token());
ac('user mismatch rejected', ($out['status'] ?? '') === 'error'
    && ($out['code'] ?? '') === ConsumeAgendaAccessTokenUseCase::ERROR_USER_REJECTED);

// Secrets absent from result
$json = json_encode($out);
ac('no token in error result', $json !== false && stripos($json, valid_token()) === false);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
