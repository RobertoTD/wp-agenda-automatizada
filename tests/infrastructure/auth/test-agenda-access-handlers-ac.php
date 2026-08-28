<?php
/**
 * AC — AgendaAccessHandlers bridge HTML + wiring (C2).
 *
 *   php tests/infrastructure/auth/test-agenda-access-handlers-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$GLOBALS['aa_actions'] = [];
$GLOBALS['aa_filters'] = [];

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['aa_actions'][$hook][] = $callback;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['aa_filters'][$hook][] = $callback;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://tenant.example.com/wp-admin/' . ltrim((string) $path, '/');
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

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
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

$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/auth/class-aa-agenda-access-token-format.php';
require_once $root . '/includes/domain/auth/class-aa-agenda-access-redirect-policy.php';
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

AA_Agenda_Access_Handlers::register();
ac('registers nopriv bridge', isset($GLOBALS['aa_actions']['admin_post_nopriv_aa_agenda_access']));
ac('registers priv bridge', isset($GLOBALS['aa_actions']['admin_post_aa_agenda_access']));
ac('registers nopriv consume', isset($GLOBALS['aa_actions']['admin_post_nopriv_aa_agenda_access_consume']));
ac('registers priv consume', isset($GLOBALS['aa_actions']['admin_post_aa_agenda_access_consume']));
ac('registers nopriv request', isset($GLOBALS['aa_actions']['admin_post_nopriv_aa_agenda_access_request']));
ac('registers priv request', isset($GLOBALS['aa_actions']['admin_post_aa_agenda_access_request']));
ac('registers login_message filter', isset($GLOBALS['aa_filters']['login_message']));

$token = str_repeat('A', 43);
$html = AA_Agenda_Access_Handlers::render_transition_html($token);

ac('form posts to admin-post', strpos($html, 'method="post"') !== false
    && strpos($html, 'admin-post.php') !== false);
ac('consume action in form', strpos($html, 'name="action" value="aa_agenda_access_consume"') !== false);
ac('token escaped in form', strpos($html, 'value="' . $token . '"') !== false);
ac('auto-submit present', strpos($html, 'f.submit()') !== false || strpos($html, '.submit()') !== false);
ac('replaceState before submit', strpos($html, 'replaceState') !== false
    && strpos($html, 'replaceState') < strpos($html, 'submit()'));
ac('referrer policy meta', strpos($html, 'referrer" content="no-referrer"') !== false
    || strpos($html, "referrer\" content=\"no-referrer\"") !== false);
ac('no external script src', preg_match('/<script[^>]+src=/i', $html) !== 1);
ac('no external stylesheet', preg_match('/<link[^>]+stylesheet/i', $html) !== 1);
ac('manual fallback button', strpos($html, 'type="submit"') !== false);
ac('html does not contain secret-looking logs', stripos($html, 'error_log') === false);

$msg = AA_Agenda_Access_Handlers::filter_login_error_message('base');
$_GET['aa_agenda_access_error'] = '1';
$msg2 = AA_Agenda_Access_Handlers::filter_login_error_message('base');
unset($_GET['aa_agenda_access_error']);
ac('login message unchanged without flag', $msg === 'base');
ac('login message adds generic notice', strpos($msg2, 'aa-agenda-access-error') !== false
    && stripos($msg2, $token) === false);

// Source-level: bridge must not reference consume client
$handler_src = (string) file_get_contents($root . '/includes/infrastructure/auth/AgendaAccessHandlers.php');
$bridge_fn = 'function handle_bridge';
$bridge_pos = strpos($handler_src, $bridge_fn);
$consume_pos = strpos($handler_src, 'function handle_consume');
$bridge_body = substr($handler_src, $bridge_pos, $consume_pos - $bridge_pos);
ac('GET bridge source has no UseCase execute', strpos($bridge_body, 'ConsumeAgendaAccessTokenUseCase') === false
    && strpos($bridge_body, '->consume(') === false);
ac('GET bridge renders transition only', strpos($bridge_body, 'render_transition_html') !== false);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
