<?php
/**
 * AC — AA_Agenda_Access_Request_Eligibility (C3B gate).
 *
 *   php tests/domain/auth/test-agenda-access-request-eligibility-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$GLOBALS['aa_test_options'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }

        return $default;
    }
}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/tenant/class-aa-installation-provisioning-detector.php';
require_once $root . '/includes/domain/auth/class-aa-agenda-access-request-eligibility.php';

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

function reset_state(): void {
    $GLOBALS['aa_test_options'] = [];
    if (defined('AA_API_BASE_URL')) {
        // Cannot undefine; tests that need missing URL use empty string via runkit-less workaround:
        // eligibility checks (string) AA_API_BASE_URL === '' — we define once to a real URL
        // and treat "missing" as a separate process isn't available; instead we only test
        // empty secret / not provisioned when constant is present.
    }
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

reset_state();
ac('not provisioned → false', AA_Agenda_Access_Request_Eligibility::can_request() === false);

reset_state();
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-06-01 12:00:00';
$GLOBALS['aa_test_options']['aa_client_secret'] = 'secret';
ac('managed + secret + API → true', AA_Agenda_Access_Request_Eligibility::can_request() === true);

reset_state();
$GLOBALS['aa_test_options']['aa_client_secret'] = 'oauth-secret';
ac('independent OAuth secret only → false', AA_Agenda_Access_Request_Eligibility::can_request() === false);

reset_state();
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-06-01 12:00:00';
$GLOBALS['aa_test_options']['aa_client_secret'] = '';
ac('managed without secret → false', AA_Agenda_Access_Request_Eligibility::can_request() === false);

reset_state();
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-06-01 12:00:00';
$GLOBALS['aa_test_options']['aa_client_secret'] = '   ';
ac('managed with whitespace secret → false', AA_Agenda_Access_Request_Eligibility::can_request() === false);

reset_state();
$GLOBALS['aa_test_options']['deoia_platform_slug'] = 'only-slug';
$GLOBALS['aa_test_options']['aa_client_secret'] = 'secret';
ac('invalid managed marker (slug only) → false', AA_Agenda_Access_Request_Eligibility::can_request() === false);

reset_state();
$GLOBALS['aa_test_options']['deoia_platform_slug'] = 'mi-agenda';
$GLOBALS['aa_test_options']['deoia_subscription_request_id'] = 'req-1';
$GLOBALS['aa_test_options']['aa_client_secret'] = 'secret';
ac('slug+request_id signals + secret → true', AA_Agenda_Access_Request_Eligibility::can_request() === true);

$elig_src = (string) file_get_contents($root . '/includes/domain/auth/class-aa-agenda-access-request-eligibility.php');
ac('eligibility ignores POST/form fields', strpos($elig_src, '$_POST') === false && strpos($elig_src, '$_REQUEST') === false);
ac('eligibility ignores hostname/URL', stripos($elig_src, 'HTTP_HOST') === false && strpos($elig_src, 'home_url') === false);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
