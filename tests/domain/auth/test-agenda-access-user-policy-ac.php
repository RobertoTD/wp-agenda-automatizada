<?php
/**
 * AC — agenda access user resolution policy (C2).
 *
 *   php tests/domain/auth/test-agenda-access-user-policy-ac.php
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
        public function __construct($id, $email, $login = 'owner') {
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

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        if ($field === 'id') {
            return $GLOBALS['aa_users_by_id'][(int) $value] ?? false;
        }
        if ($field === 'email') {
            $key = strtolower(trim((string) $value));
            return $GLOBALS['aa_users_by_email'][$key] ?? false;
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

$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/auth/class-aa-agenda-access-user-policy.php';

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

function seed_user(int $id, string $email, int $blog_id, bool $cap = true): void {
    $u = new WP_User($id, $email);
    $GLOBALS['aa_users_by_id'][$id] = $u;
    $GLOBALS['aa_users_by_email'][strtolower($email)] = $u;
    $GLOBALS['aa_members'][$id][$blog_id] = true;
    if ($cap) {
        $GLOBALS['aa_caps'][$id]['manage_options'] = true;
    }
}

$policy = new AA_Agenda_Access_User_Policy();
seed_user(7, 'owner@example.com', 3);

$ok = $policy->resolve(['email' => 'owner@example.com', 'wp_user_id' => 7], 3);
ac('id+email match succeeds', !empty($ok['ok']) && (int) $ok['user_id'] === 7);

$mismatch = $policy->resolve(['email' => 'other@example.com', 'wp_user_id' => 7], 3);
ac('id/email mismatch fails', empty($mismatch['ok']) && ($mismatch['code'] ?? '') === AA_Agenda_Access_User_Policy::CODE_EMAIL_MISMATCH);

$missing = $policy->resolve(['email' => 'owner@example.com', 'wp_user_id' => 99], 3);
ac('unknown id fails', empty($missing['ok']) && ($missing['code'] ?? '') === AA_Agenda_Access_User_Policy::CODE_USER_NOT_FOUND);

$email_only = $policy->resolve(['email' => 'owner@example.com', 'wp_user_id' => null], 3);
ac('null wp_user_id falls back to email', !empty($email_only['ok']) && (int) $email_only['user_id'] === 7);

$GLOBALS['aa_members'][7][3] = false;
$not_member = $policy->resolve(['email' => 'owner@example.com', 'wp_user_id' => 7], 3);
ac('non-member of blog fails', empty($not_member['ok']) && ($not_member['code'] ?? '') === AA_Agenda_Access_User_Policy::CODE_NOT_MEMBER);

$GLOBALS['aa_members'][7][3] = true;
unset($GLOBALS['aa_caps'][7]['manage_options']);
$no_cap = $policy->resolve(['email' => 'owner@example.com', 'wp_user_id' => 7], 3);
ac('missing manage_options fails', empty($no_cap['ok']) && ($no_cap['code'] ?? '') === AA_Agenda_Access_User_Policy::CODE_CAPABILITY);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
