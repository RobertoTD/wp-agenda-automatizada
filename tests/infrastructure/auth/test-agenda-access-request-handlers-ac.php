<?php
/**
 * AC — AgendaAccessHandlers C3B request UI + handler gate + AppLoginSkin lostpassword copy.
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
        return (string) $text;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return (string) $text;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url) {
        return parse_url((string) $url);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($val) {
        return is_string($val) ? stripslashes($val) : $val;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test-nonce-' . (string) $action;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return !empty($GLOBALS['aa_nonce_ok']) && $nonce === 'good';
    }
}

if (!function_exists('nocache_headers')) {
    function nocache_headers() {}
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302) {
        $GLOBALS['aa_redirects'][] = $location;
        throw new AA_Test_Redirect_Exception((string) $location);
    }
}

if (!function_exists('wp_login_url')) {
    function wp_login_url($redirect = '') {
        $url = 'https://tenant.example.com/wp-login.php';
        if ($redirect !== '') {
            $url .= '?redirect_to=' . rawurlencode($redirect);
        }

        return $url;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value = false, $url = '') {
        if (is_array($key)) {
            $qs = http_build_query($key);
            $sep = (strpos($url, '?') === false) ? '?' : '&';

            return $url . $sep . $qs;
        }
        $sep = (strpos($url, '?') === false) ? '?' : '&';

        return $url . $sep . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
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

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        $GLOBALS['aa_remote_calls']++;
        $GLOBALS['aa_last_remote_url'] = $url;
        $GLOBALS['aa_last_remote_args'] = $args;
        $body = $args['body'] ?? '';
        $GLOBALS['aa_last_data'] = is_string($body) && $body !== '' ? json_decode($body, true) : [];

        return $GLOBALS['aa_remote'] ?? ['code' => 200, 'body' => '{"ok":true}'];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if ($response instanceof WP_Error) {
            return 0;
        }

        return (int) ($response['code'] ?? 0);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if ($response instanceof WP_Error) {
            return '';
        }

        return (string) ($response['body'] ?? '');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

class WP_Error {}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/tenant/class-aa-installation-provisioning-detector.php';
require_once $root . '/includes/domain/auth/class-aa-agenda-access-token-format.php';
require_once $root . '/includes/domain/auth/class-aa-agenda-access-redirect-policy.php';
require_once $root . '/includes/domain/auth/class-aa-agenda-access-request-eligibility.php';
require_once $root . '/includes/infrastructure/backend/class-aa-agenda-access-backend-client.php';
require_once $root . '/includes/application/auth/RequestAgendaAccessLinkUseCase.php';
require_once $root . '/includes/infrastructure/auth/AgendaAccessHandlers.php';
require_once $root . '/includes/infrastructure/auth/AppLoginSkin.php';

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
AA_App_Login_Skin::register();

ac('registers nopriv request', isset($GLOBALS['aa_actions']['admin_post_nopriv_aa_agenda_access_request']));
ac('registers priv request', isset($GLOBALS['aa_actions']['admin_post_aa_agenda_access_request']));
ac('registers request UI filter', isset($GLOBALS['aa_filters']['login_message'][11]));
ac('registers login_site_html_link filter', isset($GLOBALS['aa_filters']['login_site_html_link'][10]));
ac('registers login_footer request footer action', isset($GLOBALS['aa_actions']['login_footer']));

// --- 1. login_message filter clean (no card) ---
$_GET = ['deoia_app_login' => '1'];
$_REQUEST = [];
managed_ready();

$msg = AA_Agenda_Access_Handlers::filter_login_request_ui('BASE');
ac('login_message does NOT render card .aa-agenda-access-request', strpos($msg, 'aa-agenda-access-request') === false);
ac('login_message does NOT render submit button', stripos($msg, 'Enviarme un enlace de acceso') === false);
ac('login_message preserves base message', strpos($msg, 'BASE') !== false);

$_GET['aa_agenda_access_link_sent'] = '1';
$sent = AA_Agenda_Access_Handlers::filter_login_request_ui('BASE');
unset($_GET['aa_agenda_access_link_sent']);
ac('neutral sent message present in login_message', strpos($sent, 'aa-agenda-access-link-sent') !== false
    && stripos($sent, 'managed-secret') === false
    && stripos($sent, '@') === false);

$_GET['aa_agenda_access_error'] = '1';
$err = AA_Agenda_Access_Handlers::filter_login_error_message('SKIN');
unset($_GET['aa_agenda_access_error']);
ac('C2 error message preserved', strpos($err, 'aa-agenda-access-error') !== false && strpos($err, 'SKIN') !== false);

// --- 2. login_site_html_link filter behavior ---
$native_link = '<a href="https://tenant.example.com/">← Ir a Mi Sitio</a>';

// Outside app login: untouched
$_GET = [];
$_REQUEST = [];
$link_noapp = AA_Agenda_Access_Handlers::filter_login_site_html_link($native_link);
ac('outside app login: login_site_html_link returns native link', $link_noapp === $native_link);

// In app login + independent OAuth: removes link (empty string)
$_GET = ['deoia_app_login' => '1'];
independent_oauth();
$link_indep = AA_Agenda_Access_Handlers::filter_login_site_html_link($native_link);
ac('in app login + independent OAuth: returns empty string', $link_indep === '');

// In app login + missing managed marker: returns empty string
$GLOBALS['aa_test_options'] = [];
$link_missing = AA_Agenda_Access_Handlers::filter_login_site_html_link($native_link);
ac('in app login + missing managed marker: returns empty string', $link_missing === '');

// In app login + managed without secret: returns empty string
managed_ready();
$GLOBALS['aa_test_options']['aa_client_secret'] = '';
$link_nosecret = AA_Agenda_Access_Handlers::filter_login_site_html_link($native_link);
ac('in app login + managed without secret: returns empty string', $link_nosecret === '');

// In app login + non-login action (e.g. lostpassword, resetpass, rp): returns empty string (no button, no site link)
managed_ready();
$_GET['action'] = 'lostpassword';
$link_lost = AA_Agenda_Access_Handlers::filter_login_site_html_link($native_link);
ac('in app login + action=lostpassword: returns empty string', $link_lost === '');

$_GET['action'] = 'resetpass';
$link_reset = AA_Agenda_Access_Handlers::filter_login_site_html_link($native_link);
ac('in app login + action=resetpass: returns empty string', $link_reset === '');

$_GET['action'] = 'rp';
$link_rp = AA_Agenda_Access_Handlers::filter_login_site_html_link($native_link);
ac('in app login + action=rp: returns empty string', $link_rp === '');
unset($_GET['action']);

// In app login + normal login action + managed ready: returns button[form]
managed_ready();
$_GET['action'] = 'login';
$link_btn = AA_Agenda_Access_Handlers::filter_login_site_html_link($native_link);
unset($_GET['action']);

ac('in app login + managed ready: returns button[form]', strpos($link_btn, 'form="aa-agenda-access-request-form"') !== false);
ac('button has class aa-agenda-access-link-action', strpos($link_btn, 'aa-agenda-access-link-action') !== false);
ac('button has exact copy Acceder sin contraseña', strpos($link_btn, 'Acceder sin contraseña') !== false);
ac('button has no question marks', strpos($link_btn, '¿') === false && strpos($link_btn, '?') === false);
ac('button is not an a-href and has no GET token', strpos($link_btn, '<a') === false && stripos($link_btn, 'token=') === false);

// --- 3. login_footer rendered content ---
managed_ready();
$_GET = ['deoia_app_login' => '1'];
$_REQUEST = ['redirect_to' => 'https://tenant.example.com/agenda-app/'];

ob_start();
AA_Agenda_Access_Handlers::render_request_footer();
$footer_html = ob_get_clean();

ac('footer renders form #aa-agenda-access-request-form', strpos($footer_html, 'id="aa-agenda-access-request-form"') !== false);
ac('footer form has action=aa_agenda_access_request', strpos($footer_html, 'name="action" value="aa_agenda_access_request"') !== false);
ac('footer form has nonce field', strpos($footer_html, 'aa_agenda_access_request_nonce') !== false);
ac('footer form has redirect_to', strpos($footer_html, 'name="redirect_to" value="https://tenant.example.com/agenda-app/"') !== false);
ac('footer form has hidden attribute', strpos($footer_html, '<form method="post"') !== false && strpos($footer_html, 'hidden') !== false);
ac('footer script has anti-double-click', strpos($footer_html, 'b.disabled = true') !== false);
ac('footer script checks nodes before insertBefore', strpos($footer_html, 'nav.parentNode.insertBefore(back, nav)') !== false);

// Footer renders nothing on non-login action
$_GET['action'] = 'lostpassword';
ob_start();
AA_Agenda_Access_Handlers::render_request_footer();
$footer_lost = ob_get_clean();
unset($_GET['action']);
ac('footer renders nothing on action=lostpassword', $footer_lost === '');

// Footer renders nothing outside app login
$_GET = [];
$_REQUEST = [];
ob_start();
AA_Agenda_Access_Handlers::render_request_footer();
$footer_noapp = ob_get_clean();
$_GET = ['deoia_app_login' => '1'];
ac('footer renders nothing outside app login', $footer_noapp === '');

// --- 4. AppLoginSkin lostpassword copy translation ---
$_GET = ['deoia_app_login' => '1'];
AA_App_Login_Skin::on_login_init();
$trans = AA_App_Login_Skin::filter_lostpassword_text('Lost your password?', 'Lost your password?', 'default');
ac('AppLoginSkin translates Lost your password? to Cambiar contraseña', $trans === 'Cambiar contraseña');
ac('translation has no question marks', strpos($trans, '¿') === false && strpos($trans, '?') === false);

$unrelated = AA_App_Login_Skin::filter_lostpassword_text('Log In', 'Log In', 'default');
ac('unrelated text is untouched', $unrelated === 'Log In');

$other_domain = AA_App_Login_Skin::filter_lostpassword_text('Lost your password?', 'Lost your password?', 'woocommerce');
ac('non-default domain is untouched', $other_domain === 'Lost your password?');

// --- 5. CSS static assertions ---
$css_content = (string) file_get_contents($root . '/includes/admin/ui/assets/css/deoia-app-login.css');
ac('CSS has no display: flex on #login', !preg_match('/#login\s*\{[^}]*display\s*:\s*flex/i', $css_content));
ac('CSS defines .aa-agenda-access-link-action', strpos($css_content, '.aa-agenda-access-link-action') !== false);
ac('CSS defines #backtoblog:empty', strpos($css_content, '#backtoblog:empty') !== false);
ac('CSS removes old .aa-agenda-access-request classes', strpos($css_content, '.aa-agenda-access-request {') === false
    && strpos($css_content, '.aa-agenda-access-request-copy') === false
    && strpos($css_content, '.aa-agenda-access-request-submit') === false
    && strpos($css_content, '.aa-agenda-access-request-separator') === false);

// --- 6. Handler request execution outcomes ---
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
ac('AppLoginSkin title copy untouched', strpos($skin_src, 'Accede a DEOIA Citas') !== false);
ac('AppLoginSkin defines filter_lostpassword_text', strpos($skin_src, 'filter_lostpassword_text') !== false);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
