<?php
/**
 * AC — agenda access redirect allowlist (C2).
 *
 *   php tests/domain/auth/test-agenda-access-redirect-policy-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://tenant.example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('wp_login_url')) {
    function wp_login_url($redirect = '') {
        return 'https://tenant.example.com/wp-login.php?redirect_to=' . rawurlencode((string) $redirect);
    }
}

if (!function_exists('aa_app_login_url')) {
    function aa_app_login_url(string $redirect_to): string {
        return 'https://tenant.example.com/wp-login.php?deoia_app_login=1&redirect_to=' . rawurlencode($redirect_to);
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value = null, $url = null) {
        if (is_array($key)) {
            $url = $value;
            foreach ($key as $k => $v) {
                $url = add_query_arg($k, $v, $url);
            }
            return $url;
        }
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
require_once $root . '/includes/domain/auth/class-aa-agenda-access-redirect-policy.php';

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

$policy = new AA_Agenda_Access_Redirect_Policy();
$cal = $policy->calendar_url();
ac('calendar url uses aa_iframe_content', strpos($cal, 'action=aa_iframe_content') !== false && strpos($cal, 'module=calendar') !== false);
ac('welcome purpose maps to calendar', $policy->success_url('welcome') === $cal);
ac('login_request purpose maps to calendar', $policy->success_url('login_request') === $cal);
ac('allowlists calendar url', $policy->is_allowlisted_success_url($cal));
ac('rejects external host', !$policy->is_allowlisted_success_url('https://evil.example/wp-admin/admin-post.php?action=aa_iframe_content&module=calendar'));
ac('rejects other module', !$policy->is_allowlisted_success_url(admin_url('admin-post.php?action=aa_iframe_content&module=settings')));
ac('rejects arbitrary path', !$policy->is_allowlisted_success_url('https://tenant.example.com/wp-admin/'));

$err = $policy->error_login_url();
ac('error login has flag and no token key', strpos($err, 'aa_agenda_access_error=1') !== false && stripos($err, 'token') === false);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
