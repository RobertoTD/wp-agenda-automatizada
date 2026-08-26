<?php
/**
 * AC — AA_Agenda_Access_Session_Issuer 30-day filter lifecycle (C2).
 *
 *   php tests/infrastructure/auth/test-agenda-access-session-issuer-ac.php
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

$GLOBALS['aa_filters'] = [];
$GLOBALS['aa_auth_cookie'] = null;
$GLOBALS['aa_current_user'] = 0;
$GLOBALS['aa_login_hooks'] = [];

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        if ($field === 'id' && (int) $value === 5) {
            return new WP_User(5, 'owner@example.com', 'owner');
        }
        return false;
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
            static function ($cb) use ($callback) {
                return $cb !== $callback;
            }
        ));
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($id) {
        $GLOBALS['aa_current_user'] = (int) $id;
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
        $GLOBALS['aa_login_hooks'][] = [$hook, $args];
    }
}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/infrastructure/auth/class-aa-agenda-access-session-issuer.php';

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

$issuer = new AA_Agenda_Access_Session_Issuer();
ac('filter inactive before', !AA_Agenda_Access_Session_Issuer::is_expiration_filter_active());

$issuer->establish(5);

ac('remember cookie set', !empty($GLOBALS['aa_auth_cookie']['remember']));
ac('expiry is 30 days', ($GLOBALS['aa_auth_cookie']['length'] ?? 0) === AA_Agenda_Access_Session_Issuer::REMEMBER_TTL_SECONDS);
ac('filter inactive after', !AA_Agenda_Access_Session_Issuer::is_expiration_filter_active());
ac('no leftover filter callbacks', empty($GLOBALS['aa_filters']['auth_cookie_expiration'][99]));
ac('wp_login fired', !empty($GLOBALS['aa_login_hooks']) && $GLOBALS['aa_login_hooks'][0][0] === 'wp_login');
ac('current user set', $GLOBALS['aa_current_user'] === 5);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
