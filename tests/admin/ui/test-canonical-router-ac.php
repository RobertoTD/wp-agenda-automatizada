<?php
/**
 * AC Test — Canonical Router & Authorization Dispatch (post LEGACY-X).
 *
 * Ejecutar: php tests/admin/ui/test-canonical-router-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$last_die = null;
$current_caps = [];
$user_logged_in = true;
$is_multisite_env = false;
$user_member_of_blog = true;

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
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
if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        global $user_logged_in;
        return $user_logged_in;
    }
}
if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        global $is_multisite_env;
        return $is_multisite_env;
    }
}
if (!function_exists('is_user_member_of_blog')) {
    function is_user_member_of_blog(): bool {
        global $user_member_of_blog;
        return $user_member_of_blog;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can(string $cap): bool {
        global $current_caps;
        return !empty($current_caps[$cap]);
    }
}
if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []): void {
        global $last_die;
        $response_code = isset($args['response']) ? (int) $args['response'] : 500;
        $last_die = [
            'message' => $message,
            'title'   => $title,
            'code'    => $response_code,
        ];
        throw new RuntimeException('wp_die: ' . $response_code . ' ' . $message);
    }
}

$plugin_root = dirname(__DIR__, 3);

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
 * Simula allowlist + gate de index.php (LEGACY-X: module=canonical → 404).
 *
 * @return array<string,mixed>
 */
function simulate_router(array $get_params, array $caps = [], bool $logged_in = true, bool $member = true): array {
    global $last_die, $current_caps, $user_logged_in, $user_member_of_blog;
    $last_die = null;
    $current_caps = $caps;
    $user_logged_in = $logged_in;
    $user_member_of_blog = $member;
    $_GET = $get_params;

    $allowed_modules = [
        'dashboard',
        'settings',
        'account',
        'calendar',
        'clients',
        'expedientes',
        'assignments',
        'learning',
        'training',
        'canonical_shell',
    ];

    $requested_module = isset($_GET['module']) ? sanitize_key($_GET['module']) : 'calendar';
    $active_module = 'calendar';

    try {
        // LEGACY-X: module=canonical retirado — mismo tratamiento que módulo no encontrado.
        if ($requested_module === 'canonical') {
            wp_die('UI module not found', 'Error', ['response' => 404]);
        }

        $active_module = in_array($requested_module, $allowed_modules, true) ? $requested_module : 'calendar';
        $view_raw = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '';

        $aa_canonical_url = admin_url('admin-post.php?action=aa_iframe_content&module=' . $active_module);
        if ($view_raw !== '') {
            $aa_canonical_url = add_query_arg('view', $view_raw, $aa_canonical_url);
        }

        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado', 'Error', ['response' => 403]);
        }

        return [
            'status'        => 200,
            'active_module' => $active_module,
            'aa_canonical_url' => $aa_canonical_url,
            'layout'        => $active_module === 'canonical_shell' ? 'canonical-layout' : 'shared-layout',
        ];
    } catch (RuntimeException $e) {
        return [
            'status'        => $last_die['code'] ?? 500,
            'message'       => $last_die['message'] ?? $e->getMessage(),
            'active_module' => $active_module,
        ];
    }
}

$router_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/index.php');
ac_assert('Router allowlists canonical_shell', strpos($router_src, "'canonical_shell'") !== false);
ac_assert(
    'Router no allowlistea module=canonical legacy',
    preg_match("/\\\$allowed_modules\\s*=\\s*\\[[^\\]]*\\n\\s*'canonical'\\s*,/s", $router_src) !== 1
    && strpos($router_src, "\$requested_module === 'canonical'") !== false
);
ac_assert(
    'Router retira module=canonical con 404',
    strpos($router_src, "\$requested_module === 'canonical'") !== false
    && strpos($router_src, "'response' => 404") !== false
);
ac_assert(
    'FinanceUseCaseSupport / Shell_Url_Policy no referenciados en router',
    strpos($router_src, 'FinanceUseCaseSupport') === false
    && strpos($router_src, 'AA_Canonical_Shell_Url_Policy') === false
);

// 1. LEGACY-X: module=canonical retirado
$res_fin = simulate_router(['module' => 'canonical', 'family' => 'finance', 'variant' => 'general'], ['manage_options' => true]);
ac_assert('module=canonical retires with 404 (admin)', ($res_fin['status'] ?? 0) === 404);

$res_fin_def = simulate_router(['module' => 'canonical', 'family' => 'finance'], []);
ac_assert('module=canonical retires with 404 (non-admin)', ($res_fin_def['status'] ?? 0) === 404);

$res_fin_bad = simulate_router(['module' => 'canonical', 'family' => 'Bad_Key!'], []);
ac_assert('module=canonical invalid family still 404 (not 400)', ($res_fin_bad['status'] ?? 0) === 404);

$res_fin_empty = simulate_router(['module' => 'canonical'], []);
ac_assert('module=canonical bare still 404', ($res_fin_empty['status'] ?? 0) === 404);

// 2. Non-admin gets 403 on legacy modules (Calendar, Clients, etc.)
$res_cal_non_admin = simulate_router(['module' => 'calendar'], []);
ac_assert('Non-admin is blocked on calendar with 403', $res_cal_non_admin['status'] === 403);

// 3. Admin retains access to Calendar
$res_cal_admin = simulate_router(['module' => 'calendar'], ['manage_options' => true]);
ac_assert('Admin has access to calendar (200)', $res_cal_admin['status'] === 200);
ac_assert('Calendar uses shared-layout', ($res_cal_admin['layout'] ?? '') === 'shared-layout');

// 4. Unknown legacy module falls back to calendar
$res_unknown_legacy = simulate_router(['module' => 'nonexistent_legacy'], ['manage_options' => true]);
ac_assert('Unknown legacy module falls back to calendar', $res_unknown_legacy['active_module'] === 'calendar');

// 5. canonical_shell still allowlisted for admin
$res_shell = simulate_router(['module' => 'canonical_shell', 'family' => 'finance'], ['manage_options' => true]);
ac_assert('Admin can open canonical_shell (200)', ($res_shell['status'] ?? 0) === 200);
ac_assert('canonical_shell uses canonical-layout', ($res_shell['layout'] ?? '') === 'canonical-layout');

$res_shell_denied = simulate_router(['module' => 'canonical_shell'], []);
ac_assert('Non-admin blocked on canonical_shell (403)', ($res_shell_denied['status'] ?? 0) === 403);

// 6. Unauthenticated user on retired canonical still 404 (retirement before auth gate)
$res_unauth = simulate_router(['module' => 'canonical', 'family' => 'finance'], [], false);
ac_assert('Unauthenticated user on retired canonical gives 404', $res_unauth['status'] === 404);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
