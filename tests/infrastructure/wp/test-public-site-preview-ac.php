<?php
/**
 * AC para AA_Public_Site_Preview.
 *
 * Ejecutar: php tests/infrastructure/wp/test-public-site-preview-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$GLOBALS['aa_preview_test_is_admin'] = false;
$GLOBALS['aa_preview_test_user_logged_in'] = false;
$GLOBALS['aa_preview_test_can_manage_options'] = false;
$_GET = [];

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return (bool) $GLOBALS['aa_preview_test_is_admin'];
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return (bool) $GLOBALS['aa_preview_test_user_logged_in'];
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool {
        if ($capability === 'manage_options') {
            return (bool) $GLOBALS['aa_preview_test_can_manage_options'];
        }

        return false;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://tenant.example.test' . $path;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url) {
        $separator = strpos((string) $url, '?') !== false ? '&' : '?';

        return (string) $url . $separator . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

require_once dirname(__DIR__, 3) . '/includes/infrastructure/wp/PublicSitePreview.php';

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

function reset_preview_request(array $get = [], bool $is_admin = false, bool $logged_in = false, bool $can_manage = false): void {
    $_GET = $get;
    $GLOBALS['aa_preview_test_is_admin'] = $is_admin;
    $GLOBALS['aa_preview_test_user_logged_in'] = $logged_in;
    $GLOBALS['aa_preview_test_can_manage_options'] = $can_manage;
}

$preview_url = AA_Public_Site_Preview::public_url();
ac_assert(
    'public_url includes preview query param',
    strpos($preview_url, 'deoia_public_preview=1') !== false,
    $preview_url
);

reset_preview_request(['deoia_public_preview' => '1'], false, true, true);
ac_assert(
    'preview request detected on frontend for admin',
    AA_Public_Site_Preview::is_preview_request() === true
);
ac_assert(
    'preview hides admin bar for logged-in manage_options user',
    AA_Public_Site_Preview::filter_show_admin_bar(true) === false
);

reset_preview_request([], false, true, true);
ac_assert(
    'normal frontend keeps admin bar for admin without preview param',
    AA_Public_Site_Preview::filter_show_admin_bar(true) === true
);

reset_preview_request(['deoia_public_preview' => '1'], false, false, false);
ac_assert(
    'preview does not hide admin bar for logged-out visitor',
    AA_Public_Site_Preview::filter_show_admin_bar(true) === true
);

reset_preview_request(['deoia_public_preview' => '1'], false, true, false);
ac_assert(
    'preview does not hide admin bar without manage_options',
    AA_Public_Site_Preview::filter_show_admin_bar(true) === true
);

reset_preview_request(['deoia_public_preview' => '1'], true, true, true);
ac_assert(
    'preview is ignored in admin context',
    AA_Public_Site_Preview::is_preview_request() === false
        && AA_Public_Site_Preview::filter_show_admin_bar(true) === true
);

$guard_source = (string) file_get_contents(dirname(__DIR__, 3) . '/includes/infrastructure/wp/PublicSiteMaintenanceGuard.php');
ac_assert(
    'maintenance guard does not exclude preview query param by path',
    strpos($guard_source, 'deoia_public_preview') === false,
    'guard should rely on path only; preview param must not bypass maintenance'
);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
