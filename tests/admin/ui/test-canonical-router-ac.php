<?php
/**
 * AC Test — Canonical Router & Authorization Dispatch.
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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/ResolveCanonicalRouteUseCase.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-url-policy.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';

// Ensure bootstrap is primed
AA_Canonical_Core_Bootstrap::bootstrap();

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
        'canonical',
    ];

    $requested_module = isset($_GET['module']) ? sanitize_key($_GET['module']) : 'calendar';
    $active_module    = in_array($requested_module, $allowed_modules, true) ? $requested_module : 'calendar';
    $view_raw         = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '';

    $aa_canonical_family = null;
    $aa_canonical_variant = null;

    try {
        if ($active_module === 'canonical') {
            $family_input = array_key_exists('family', $_GET) ? wp_unslash($_GET['family']) : null;
            $variant_input = array_key_exists('variant', $_GET) ? wp_unslash($_GET['variant']) : null;

            $canonical_registry = null;
            if (class_exists('AA_Canonical_Core_Bootstrap')) {
                try {
                    $canonical_registry = AA_Canonical_Core_Bootstrap::instance();
                } catch (\Throwable $e) {
                    $canonical_registry = null;
                }
            }

            if ($canonical_registry === null) {
                wp_die('El núcleo canónico no está disponible.', 'Error', ['response' => 500]);
            }

            $route_result = (new ResolveCanonicalRouteUseCase($canonical_registry))->execute([
                'family_key'  => $family_input,
                'variant_key' => $variant_input,
            ]);

            if (!$route_result['success']) {
                $error_code = (string) ($route_result['error']['code'] ?? '');
                if ($error_code === 'unknown_family' || $error_code === 'unknown_variant') {
                    wp_die('Familia o variante canónica no encontrada.', 'Error', ['response' => 404]);
                }
                if ($error_code === 'canonical_unavailable') {
                    wp_die('El núcleo canónico no está disponible.', 'Error', ['response' => 500]);
                }
                wp_die('Parámetros de ruta canónica no válidos.', 'Error', ['response' => 400]);
            }

            $aa_canonical_family  = $route_result['data']['family'];
            $aa_canonical_variant = $route_result['data']['variant'];
            $aa_canonical_url     = AA_Canonical_Shell_Url_Policy::build_url(
                $aa_canonical_family->key(),
                $aa_canonical_variant->key()
            );
        } else {
            $aa_canonical_url = admin_url('admin-post.php?action=aa_iframe_content&module=' . $active_module);
            if ($view_raw !== '') {
                $aa_canonical_url = add_query_arg('view', $view_raw, $aa_canonical_url);
            }
        }

        // Capability check
        if ($active_module === 'canonical') {
            $family_key = ($aa_canonical_family instanceof AA_Canonical_Family_Definition)
                ? $aa_canonical_family->key()
                : 'finance';

            $access = AA_Canonical_Access_Policy::check_family_access($family_key);
            if (!$access['authorized']) {
                wp_die('Acceso denegado', 'Error', ['response' => $access['status']]);
            }
        } else {
            if (!current_user_can('manage_options')) {
                wp_die('Acceso denegado', 'Error', ['response' => 403]);
            }
        }

        return [
            'status'               => 200,
            'active_module'        => $active_module,
            'aa_canonical_family'  => $aa_canonical_family,
            'aa_canonical_variant' => $aa_canonical_variant,
            'aa_canonical_url'     => $aa_canonical_url,
            'layout'               => $active_module === 'canonical' ? 'canonical-layout' : 'shared-layout',
        ];
    } catch (RuntimeException $e) {
        return [
            'status'        => $last_die['code'] ?? 500,
            'message'       => $last_die['message'] ?? $e->getMessage(),
            'active_module' => $active_module,
        ];
    }
}

// 1. Success for finance.general with non-admin user
$res_fin = simulate_router(['module' => 'canonical', 'family' => 'finance', 'variant' => 'general'], []);
ac_assert('Non-admin can open finance.general (200)', $res_fin['status'] === 200);
ac_assert('Active module is canonical', $res_fin['active_module'] === 'canonical');
ac_assert('Uses canonical-layout', ($res_fin['layout'] ?? '') === 'canonical-layout');
ac_assert('Family is finance', $res_fin['aa_canonical_family']->key() === 'finance');
ac_assert('Variant is general', $res_fin['aa_canonical_variant']->key() === 'general');

// 2. Success for finance without variant (resolves default general)
$res_fin_def = simulate_router(['module' => 'canonical', 'family' => 'finance'], []);
ac_assert('Finance without variant resolves (200)', $res_fin_def['status'] === 200);
ac_assert('Default variant is general', $res_fin_def['aa_canonical_variant']->key() === 'general');

// 3. Non-admin gets 403 on legacy modules (Calendar, Clients, etc.)
$res_cal_non_admin = simulate_router(['module' => 'calendar'], []);
ac_assert('Non-admin is blocked on calendar with 403', $res_cal_non_admin['status'] === 403);

// 4. Admin retains access to Calendar
$res_cal_admin = simulate_router(['module' => 'calendar'], ['manage_options' => true]);
ac_assert('Admin has access to calendar (200)', $res_cal_admin['status'] === 200);
ac_assert('Calendar uses shared-layout', ($res_cal_admin['layout'] ?? '') === 'shared-layout');

// 5. Unknown legacy module falls back to calendar
$res_unknown_legacy = simulate_router(['module' => 'nonexistent_legacy'], ['manage_options' => true]);
ac_assert('Unknown legacy module falls back to calendar', $res_unknown_legacy['active_module'] === 'calendar');

// 6. Invalid canonical params return 400 (not fallback to calendar)
$res_bad_fam = simulate_router(['module' => 'canonical', 'family' => 'Bad_Key!'], []);
ac_assert('Invalid family key gives 400', $res_bad_fam['status'] === 400);

$res_empty_fam = simulate_router(['module' => 'canonical'], []);
ac_assert('Missing family key gives 400', $res_empty_fam['status'] === 400);

$res_empty_var = simulate_router(['module' => 'canonical', 'family' => 'finance', 'variant' => ''], []);
ac_assert('Explicitly empty variant gives 400', $res_empty_var['status'] === 400);

// 7. Unknown canonical family/variant return 404 (not fallback to calendar)
$res_unknown_fam = simulate_router(['module' => 'canonical', 'family' => 'unknown_family'], []);
ac_assert('Unknown canonical family gives 404', $res_unknown_fam['status'] === 404);

$res_unknown_var = simulate_router(['module' => 'canonical', 'family' => 'finance', 'variant' => 'unknown_variant'], []);
ac_assert('Unknown canonical variant gives 404', $res_unknown_var['status'] === 404);

// 8. Unauthenticated user on canonical gives 401
$res_unauth = simulate_router(['module' => 'canonical', 'family' => 'finance'], [], false);
ac_assert('Unauthenticated user on canonical gives 401', $res_unauth['status'] === 401);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
