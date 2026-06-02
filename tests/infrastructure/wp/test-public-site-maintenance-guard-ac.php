<?php
/**
 * AC para AA_Public_Site_Maintenance_Guard.
 *
 * Ejecutar: php tests/infrastructure/wp/test-public-site-maintenance-guard-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$root = dirname(__DIR__, 3);

if (!defined('AA_PLUGIN_PATH')) {
    define('AA_PLUGIN_PATH', $root . '/');
}

$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_test_is_admin'] = false;
$GLOBALS['aa_test_doing_ajax'] = false;
$GLOBALS['aa_test_home_url'] = 'https://tenant.example.com/';

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }

        return $default;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return (bool) $GLOBALS['aa_test_is_admin'];
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool {
        return (bool) $GLOBALS['aa_test_doing_ajax'];
    }
}

if (!function_exists('home_url')) {
    function home_url($path = ''): string {
        $base = rtrim((string) $GLOBALS['aa_test_home_url'], '/');
        $path = (string) $path;

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

require_once $root . '/includes/domain/site/class-aa-public-site-status.php';
require_once $root . '/includes/domain/tenant/class-aa-installation-provisioning-detector.php';
require_once $root . '/includes/infrastructure/wp/PublicSiteMaintenanceGuard.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo "[FAIL] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
}

/**
 * @param array<string,mixed> $options
 */
function reset_request(array $options = [], string $request_uri = '/', bool $is_admin = false, bool $doing_ajax = false): void {
    $GLOBALS['aa_test_options'] = $options;
    $GLOBALS['aa_test_is_admin'] = $is_admin;
    $GLOBALS['aa_test_doing_ajax'] = $doing_ajax;
    $GLOBALS['aa_test_home_url'] = 'https://tenant.example.com/';
    $_SERVER['REQUEST_URI'] = $request_uri;
}

function provisioned_options(string $status): array {
    return [
        'deoia_platform_provisioned_at' => '2026-06-01 12:00:00',
        AA_Public_Site_Status::OPTION => $status,
    ];
}

// Main guard conditions.
reset_request([AA_Public_Site_Status::OPTION => AA_Public_Site_Status::STATUS_MAINTENANCE], '/');
ac_assert(
    'non-provisioned site with maintenance status does not intercept',
    AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === false
);

reset_request(provisioned_options(AA_Public_Site_Status::STATUS_ACTIVE), '/');
ac_assert(
    'provisioned site with active status does not intercept',
    AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === false
);

reset_request(provisioned_options(AA_Public_Site_Status::STATUS_MAINTENANCE), '/');
ac_assert(
    'provisioned site with maintenance status intercepts public home',
    AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === true
);

reset_request(provisioned_options('invalid'), '/');
ac_assert(
    'invalid status resolves active and does not intercept',
    AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === false
);

// Context exclusions.
reset_request(provisioned_options(AA_Public_Site_Status::STATUS_MAINTENANCE), '/', true, false);
ac_assert(
    'is_admin context does not intercept',
    AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === false
);

reset_request(provisioned_options(AA_Public_Site_Status::STATUS_MAINTENANCE), '/wp-admin/admin-ajax.php', false, true);
ac_assert(
    'ajax context does not intercept',
    AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === false
);

// Path exclusions.
$excluded_paths = [
    '/wp-admin/' => 'wp-admin path',
    '/wp-login.php' => 'login path',
    '/agenda-app/' => 'agenda app path',
    '/wp-admin/admin-post.php?action=aa_iframe_content' => 'admin-post path',
    '/wp-admin/admin-ajax.php' => 'admin-ajax path',
    '/wp-json/aa/v1/branding' => 'REST API path',
    '/wp-content/plugins/wp-agenda-automatizada/app.js' => 'wp-content assets path',
    '/wp-includes/js/wp-emoji-release.min.js' => 'wp-includes assets path',
    '/favicon.ico' => 'favicon path',
    '/robots.txt' => 'robots path',
    '/wp-cron.php' => 'wp-cron path',
    '/xmlrpc.php' => 'xmlrpc path',
    '/citas-virtuales/?token=abc' => 'virtual appointment portal path',
];

foreach ($excluded_paths as $path => $label) {
    reset_request(provisioned_options(AA_Public_Site_Status::STATUS_MAINTENANCE), $path);
    ac_assert(
        $label . ' does not intercept',
        AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === false,
        $path
    );
}

// Multisite/subdirectory home path handling.
$GLOBALS['aa_test_home_url'] = 'https://example.com/tenant/';
$_SERVER['REQUEST_URI'] = '/tenant/agenda-app/';
$GLOBALS['aa_test_options'] = provisioned_options(AA_Public_Site_Status::STATUS_MAINTENANCE);
$GLOBALS['aa_test_is_admin'] = false;
$GLOBALS['aa_test_doing_ajax'] = false;
ac_assert(
    'subdirectory agenda-app path does not intercept',
    AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === false
);

$GLOBALS['aa_test_home_url'] = 'https://example.com/tenant/';
$_SERVER['REQUEST_URI'] = '/tenant/';
ac_assert(
    'subdirectory public home intercepts',
    AA_Public_Site_Maintenance_Guard::should_intercept_current_request() === true
);

// Maintenance response/source validation without triggering exit.
$guard_source = (string) file_get_contents($root . '/includes/infrastructure/wp/PublicSiteMaintenanceGuard.php');
$view_source = (string) file_get_contents($root . '/views/public-site-maintenance.php');

ac_assert('guard sends 503 status', strpos($guard_source, 'status_header(503)') !== false);
ac_assert('guard sends Retry-After header', strpos($guard_source, 'Retry-After:') !== false);
ac_assert('guard sends X-Robots noindex header', strpos($guard_source, 'X-Robots-Tag: noindex, nofollow') !== false);
ac_assert('guard sends no-store cache header', strpos($guard_source, 'no-store') !== false);
ac_assert('view includes noindex/nofollow meta', strpos($view_source, 'noindex, nofollow') !== false);
ac_assert('view includes maintenance title', strpos($view_source, 'Sitio web en preparación') !== false);
ac_assert('view includes DEOIA Citas brand', strpos($view_source, 'DEOIA Citas') !== false);
ac_assert('view does not load theme header', strpos($view_source, 'get_header') === false && strpos($view_source, 'wp_head') === false);
ac_assert('view does not load public shortcode', strpos($view_source, 'do_shortcode') === false && strpos($view_source, 'agenda_automatizada') === false);
ac_assert('view does not expose internal links', stripos($view_source, 'href=') === false && stripos($view_source, 'wp-login') === false && stripos($view_source, 'agenda-app') === false);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
