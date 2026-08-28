<?php
/**
 * AC — AgendaAccessHandlers C3B request CTA + handler gate.
 *
 *   php tests/infrastructure/auth/test-agenda-access-request-handlers-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

$GLOBALS['aa_actions'] = [];
$GLOBALS['aa_filters'] = [];
$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_remote_calls'] = 0;
$GLOBALS['aa_nonce_ok'] = true;
$GLOBALS['aa_blog_id'] = 3;
$GLOBALS['aa_redirects'] = [];

class AA_Test_Redirect_Exception extends Exception {
    /** @var string */
    public $url;

    public function __construct(string $url) {
        parent::__construct($url);
        $this->url = $url;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['aa_actions'][$hook][] = $callback;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['aa_filters'][$hook][$priority][] = $callback;
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['aa_test_options'][$key] ?? $default;
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

        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://tenant.example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://tenant.example.com' . $path;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'nonce-for-' . $action;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        if (empty($GLOBALS['aa_nonce_ok'])) {
            return false;
        }

        return is_string($nonce) && $nonce !== '';
    }
}

if (!function_exists('wp_validate_redirect')) {
    function wp_validate_redirect($location, $fallback = false) {
        if (!is_string($location) || $location === '') {
            return $fallback;
        }
        if (strpos($location, 'https://evil.example') === 0) {
            return $fallback;
        }

        return $location;
    }
}

if (!function_exists('aa_app_login_url')) {
    function aa_app_login_url(string $redirect_to): string {
        return 'https://tenant.example.com/wp-login.php?deoia_app_login=1&redirect_to=' . rawurlencode($redirect_to);
    }
}

if (!function_exists('aa_redirect_to_is_app_context')) {
    function aa_redirect_to_is_app_context(string $url): bool {
        return preg_match('~/agenda-app/?([?#]|$)~', $url) === 1
            || strpos($url, 'action=aa_iframe_content') !== false;
    }
}

if (!function_exists('aa_is_deoia_app_login_context')) {
    function aa_is_deoia_app_login_context(): bool {
        return !empty($GLOBALS['aa_app_login_context']);
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

if (!function_exists('wp_login_url')) {
    function wp_login_url($redirect = '') {
        return 'https://tenant.example.com/wp-login.php?redirect_to=' . rawurlencode((string) $redirect);
    }
}

if (!function_exists('nocache_headers')) {
    function nocache_headers() {
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302) {
        $GLOBALS['aa_redirects'][] = (string) $location;
        throw new AA_Test_Redirect_Exception((string) $location);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
    }
}

if (!function_exists('aa_send_authenticated_request')) {
    function aa_send_authenticated_request($endpoint, $method = 'POST', $data = []) {
        $GLOBALS['aa_remote_calls'] = ($GLOBALS['aa_remote_calls'] ?? 0) + 1;
        $GLOBALS['aa_last_endpoint'] = $endpoint;
        $GLOBALS['aa_last_data'] = $data;
        $GLOBALS['aa_hmac_log'][] = [
            'endpoint' => $endpoint,
            'data'     => $data,
        ];

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

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/tenant/class-aa-installation-provisioning-detector.php';
require_once $root . '/includes/domain/auth/class-aa-agenda-access-token-format.php';
require_once $root . '/includes/domain/auth/class-aa-agenda-access-redirect-policy.php';
require_once $root . '/includes/domain/auth/class-aa-agenda-access-request-eligibility.php';
require_once $root . '/includes/infrastructure/backend/class-aa-agenda-access-backend-client.php';
require_once $root . '/includes/application/auth/RequestAgendaAccessLinkUseCase.php';
require_once $root . '/includes/infrastructure/auth/AgendaAccessHandlers.php';

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

function managed_ready(): void {
    $GLOBALS['aa_test_options'] = [
        'deoia_platform_provisioned_at' => '2026-06-01 12:00:00',
        'aa_client_secret'              => 'managed-secret',
    ];
}

function independent_oauth(): void {
    $GLOBALS['aa_test_options'] = [
        'aa_client_secret' => 'oauth-secret',
    ];
}

function run_request_handler(): string {
    try {
        AA_Agenda_Access_Handlers::handle_request();
        return '';
    } catch (AA_Test_Redirect_Exception $e) {
        return $e->url;
    }
}

AA_Agenda_Access_Handlers::register();
ac('registers nopriv request', isset($GLOBALS['aa_actions']['admin_post_nopriv_aa_agenda_access_request']));
ac('registers priv request', isset($GLOBALS['aa_actions']['admin_post_aa_agenda_access_request']));
ac('registers request UI filter', isset($GLOBALS['aa_filters']['login_message'][11]));

// --- CTA visibility ---
$GLOBALS['aa_app_login_context'] = true;
$_GET = [];
$_REQUEST = [];

independent_oauth();
$html_indep = AA_Agenda_Access_Handlers::filter_login_request_ui('BASE');
ac('independent OAuth hides CTA', strpos($html_indep, 'aa-agenda-access-request-form') === false);

$GLOBALS['aa_test_options'] = [];
$html_missing = AA_Agenda_Access_Handlers::filter_login_request_ui('BASE');
ac('missing managed marker hides CTA', strpos($html_missing, 'aa-agenda-access-request-form') === false);

managed_ready();
$GLOBALS['aa_test_options']['aa_client_secret'] = '';
$html_nosecret = AA_Agenda_Access_Handlers::filter_login_request_ui('BASE');
ac('managed without secret hides CTA', strpos($html_nosecret, 'aa-agenda-access-request-form') === false);

managed_ready();
$GLOBALS['aa_app_login_context'] = false;
$html_noapp = AA_Agenda_Access_Handlers::filter_login_request_ui('BASE');
ac('non-app login hides CTA', strpos($html_noapp, 'aa-agenda-access-request-form') === false);

$GLOBALS['aa_app_login_context'] = true;
managed_ready();
$html_ok = AA_Agenda_Access_Handlers::filter_login_request_ui('BASE');
ac('managed+HMAC shows CTA', strpos($html_ok, 'aa-agenda-access-request-form') !== false
    && strpos($html_ok, 'Enviarme un enlace de acceso') !== false);
ac('CTA has no email field', stripos($html_ok, 'type="email"') === false && stripos($html_ok, 'name="email"') === false);
ac('CTA preserves AppLogin/BASE composition', strpos($html_ok, 'BASE') !== false);
ac('CTA has no secrets', stripos($html_ok, 'managed-secret') === false);

$cta = AA_Agenda_Access_Handlers::render_request_cta_html();
ac('form posts admin-post request action', strpos($cta, 'name="action" value="aa_agenda_access_request"') !== false);
ac('form has nonce field', strpos($cta, 'aa_agenda_access_request_nonce') !== false);

$_GET['aa_agenda_access_link_sent'] = '1';
$sent = AA_Agenda_Access_Handlers::filter_login_request_ui('BASE');
unset($_GET['aa_agenda_access_link_sent']);
ac('neutral sent message', strpos($sent, 'aa-agenda-access-link-sent') !== false
    && stripos($sent, 'managed-secret') === false
    && stripos($sent, '@') === false);

$_GET['aa_agenda_access_error'] = '1';
$err = AA_Agenda_Access_Handlers::filter_login_error_message('SKIN');
unset($_GET['aa_agenda_access_error']);
ac('C2 error message preserved', strpos($err, 'aa-agenda-access-error') !== false && strpos($err, 'SKIN') !== false);

// --- Handler outcomes ---
$_POST = [
    'action' => 'aa_agenda_access_request',
    'aa_agenda_access_request_nonce' => 'good',
    'redirect_to' => 'https://tenant.example.com/agenda-app/',
];

managed_ready();
$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_remote_calls'] = 0;
$GLOBALS['aa_hmac_log'] = [];
$GLOBALS['aa_nonce_ok'] = true;
$GLOBALS['aa_remote'] = ['code' => 200, 'body' => '{"ok":true}'];
$url1 = run_request_handler();
ac('managed valid → exactly one HMAC', $GLOBALS['aa_remote_calls'] === 1);
ac('HMAC empty body', ($GLOBALS['aa_last_data'] ?? null) === []);
ac('PRG link_sent flag', strpos($url1, 'aa_agenda_access_link_sent=1') !== false);
ac('PRG keeps app login', strpos($url1, 'deoia_app_login=1') !== false);

independent_oauth();
$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_remote_calls'] = 0;
$url_indep = run_request_handler();
ac('forged independent POST → 0 HMAC', $GLOBALS['aa_remote_calls'] === 0);
ac('independent forged still neutral PRG', strpos($url_indep, 'aa_agenda_access_link_sent=1') !== false);

$GLOBALS['aa_test_options'] = ['aa_client_secret' => 'x']; // not provisioned
$GLOBALS['aa_remote_calls'] = 0;
$url_missing = run_request_handler();
ac('absent managed marker → 0 HMAC', $GLOBALS['aa_remote_calls'] === 0);
ac('absent marker neutral PRG', strpos($url_missing, 'aa_agenda_access_link_sent=1') !== false);

managed_ready();
$GLOBALS['aa_test_options']['aa_client_secret'] = '';
$GLOBALS['aa_remote_calls'] = 0;
$url_cfg = run_request_handler();
ac('incomplete HMAC config → 0 HMAC', $GLOBALS['aa_remote_calls'] === 0);

managed_ready();
$GLOBALS['aa_nonce_ok'] = false;
$GLOBALS['aa_remote_calls'] = 0;
$GLOBALS['aa_transients'] = [];
$url_nonce = run_request_handler();
ac('invalid nonce → 0 HMAC', $GLOBALS['aa_remote_calls'] === 0);
ac('invalid nonce neutral PRG', strpos($url_nonce, 'aa_agenda_access_link_sent=1') !== false);

$GLOBALS['aa_nonce_ok'] = true;
managed_ready();
$GLOBALS['aa_transients']['aa_agenda_access_req_3'] = '1';
$GLOBALS['aa_remote_calls'] = 0;
$url_tr = run_request_handler();
ac('active transient → 0 HMAC', $GLOBALS['aa_remote_calls'] === 0);
ac('transient still neutral PRG', strpos($url_tr, 'aa_agenda_access_link_sent=1') !== false);

// Indistinguishable outcomes for success / error / timeout
$outcomes = [];
foreach (
    [
        ['code' => 200, 'body' => '{"ok":true}'],
        ['code' => 429, 'body' => '{"ok":true}'],
        ['code' => 500, 'body' => '{}'],
        'wp_error',
    ] as $remote
) {
    managed_ready();
    $GLOBALS['aa_transients'] = [];
    $GLOBALS['aa_nonce_ok'] = true;
    if ($remote === 'wp_error') {
        $GLOBALS['aa_remote'] = new WP_Error();
    } else {
        $GLOBALS['aa_remote'] = $remote;
    }
    $outcomes[] = run_request_handler();
}
ac('success/error/timeout same PRG shape', count(array_unique($outcomes)) === 1
    && strpos($outcomes[0], 'aa_agenda_access_link_sent=1') !== false);

// POST fields cannot alter classification
managed_ready();
$_POST['managed'] = '0';
$_POST['email'] = 'attacker@evil.test';
$_POST['installation_type'] = 'independent';
$_POST['blog_id'] = '999';
$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_remote_calls'] = 0;
$GLOBALS['aa_remote'] = ['code' => 200, 'body' => '{"ok":true}'];
run_request_handler();
ac('POST spoof fields still call when managed', $GLOBALS['aa_remote_calls'] === 1);

independent_oauth();
$_POST['managed'] = '1';
$_POST['deoia_platform_provisioned_at'] = '2026-01-01';
$_POST['is_provisioned'] = '1';
$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_remote_calls'] = 0;
run_request_handler();
ac('POST cannot force managed classification', $GLOBALS['aa_remote_calls'] === 0);

// Safe redirect
managed_ready();
$_POST = [
    'aa_agenda_access_request_nonce' => 'good',
    'redirect_to' => 'https://evil.example/phish',
];
$GLOBALS['aa_transients'] = [];
$GLOBALS['aa_nonce_ok'] = true;
$GLOBALS['aa_remote'] = ['code' => 200, 'body' => '{"ok":true}'];
$url_evil = run_request_handler();
ac('open redirect rejected', strpos($url_evil, 'evil.example') === false
    && strpos($url_evil, 'aa_agenda_access_link_sent=1') !== false);

$handler_src = (string) file_get_contents($root . '/includes/infrastructure/auth/AgendaAccessHandlers.php');
ac('handler does not error_log secrets', preg_match('/\berror_log\s*\(/', $handler_src) !== 1);
ac('handler does not remove password login', strpos($handler_src, 'remove_action') === false
    && strpos($handler_src, 'login_form_login') === false);
ac('no email in request body construction', preg_match('/request\(\s*[\'"]email/i', $handler_src) !== 1);

$skin_src = (string) file_get_contents($root . '/includes/infrastructure/auth/AppLoginSkin.php');
ac('AppLoginSkin copy untouched', strpos($skin_src, 'Accede a DEOIA Citas') !== false
    && strpos($skin_src, 'aa-agenda-access-request') === false);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
