<?php
/**
 * AC Test — Login Redirect for Canonical Shell in iframe gateway (LEGACY-X).
 *
 * Ejecutar: php tests/admin/test-iframe-canonical-login-redirect-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$last_redirect = null;

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('wp_login_url')) {
    function wp_login_url(string $redirect_to = ''): string {
        return 'https://example.com/wp-login.php?redirect_to=' . rawurlencode($redirect_to);
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args): string {
        if (count($args) === 2 && is_array($args[0])) {
            $query = http_build_query($args[0]);
            $url = $args[1];
        } elseif (count($args) === 3) {
            $query = http_build_query([$args[0] => $args[1]]);
            $url = $args[2];
        } else {
            return '';
        }
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . $query;
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return $value;
    }
}
if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $location): void {
        global $last_redirect;
        $last_redirect = $location;
    }
}
if (!function_exists('aa_app_login_url')) {
    function aa_app_login_url(string $redirect_to): string {
        return wp_login_url($redirect_to) . '&deoia_app_login=1';
    }
}

$plugin_root = dirname(__DIR__, 2);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

/**
 * Mirror of aa_handle_iframe_content_nopriv shell/login redirect target selection.
 */
function lx_iframe_login_target_url(): string {
    $module_raw = isset($_GET['module']) ? sanitize_key($_GET['module']) : '';
    if ($module_raw === AA_Canonical_Shell_Base_Url_Policy::MODULE_SHELL) {
        $family = isset($_GET['family']) && is_string($_GET['family']) ? wp_unslash($_GET['family']) : '';
        $variant = isset($_GET['variant']) && is_string($_GET['variant']) ? wp_unslash($_GET['variant']) : null;
        if (is_string($family) && $family !== '' && AA_Canonical_Key::is_valid($family)) {
            return AA_Canonical_Shell_Base_Url_Policy::build_url(
                $family,
                (is_string($variant) && $variant !== '') ? $variant : null
            );
        }

        return AA_Canonical_Shell_Base_Url_Policy::build_module_url();
    }

    $target_url = admin_url('admin-post.php?action=aa_iframe_content');
    if ($module_raw !== '') {
        $target_url = add_query_arg('module', $module_raw, $target_url);
    }

    return $target_url;
}

// 1. Shell request preserves family via Base_Url_Policy
$_GET = [
    'module' => 'canonical_shell',
    'family' => 'finance',
    'arbitrary' => 'dropped',
];
$last_redirect = null;
$target_url = lx_iframe_login_target_url();
$login_url = aa_app_login_url($target_url);
wp_safe_redirect($login_url);

ac_assert('Redirect preserves module=canonical_shell', strpos($last_redirect, 'module%3Dcanonical_shell') !== false);
ac_assert('Redirect preserves family=finance', strpos($last_redirect, 'family%3Dfinance') !== false);
ac_assert('Redirect drops arbitrary parameter', strpos($last_redirect, 'arbitrary') === false);
ac_assert('Redirect includes deoia_app_login flag', strpos($last_redirect, 'deoia_app_login=1') !== false);

// 2. Retired module=canonical is not treated as shell policy (generic module arg only)
$_GET = [
    'module' => 'canonical',
    'family' => 'finance',
];
$last_redirect = null;
$target_url = lx_iframe_login_target_url();
$login_url = aa_app_login_url($target_url);
wp_safe_redirect($login_url);

ac_assert(
    'Retired module=canonical uses generic iframe redirect (not shell builder)',
    strpos($last_redirect, 'module%3Dcanonical') !== false
    && strpos($last_redirect, 'family%3Dfinance') === false
);

// 3. Calendar keeps legacy redirect shape
$_GET = [
    'module' => 'calendar',
    'view' => 'day',
];
$last_redirect = null;
$target_url = lx_iframe_login_target_url();
$login_url = aa_app_login_url($target_url);
wp_safe_redirect($login_url);

ac_assert('Legacy redirect has module=calendar', strpos($last_redirect, 'module%3Dcalendar') !== false);
ac_assert('Legacy redirect does not add family', strpos($last_redirect, 'family') === false);

ac_assert(
    'Shell_Url_Policy ausente',
    !is_file($plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-url-policy.php')
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
