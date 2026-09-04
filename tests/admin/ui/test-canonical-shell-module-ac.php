<?php
/**
 * AC Test — Canonical Shell Base module (SB1-1).
 *
 * Ejecutar: php tests/admin/ui/test-canonical-shell-module-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$last_die = null;
$current_caps = [];
$user_logged_in = true;
$is_multisite_env = false;
$user_member_of_blog = true;
$status_headers = [];

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
if (!function_exists('status_header')) {
    function status_header(int $code): void {
        global $status_headers;
        $status_headers[] = $code;
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return $url;
    }
}
if (!function_exists('aa_asset_url')) {
    function aa_asset_url(string $relative_path): string {
        return 'https://example.com/plugin/' . ltrim($relative_path, '/');
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url) {
        return parse_url($url);
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
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';

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

/**
 * Simula la rama de allowlist + resolución de index.php para canonical_shell / canonical.
 *
 * @return array<string,mixed>
 */
function simulate_shell_router(array $get_params, array $caps = []): array {
    global $last_die, $current_caps, $user_logged_in, $user_member_of_blog, $status_headers;
    $last_die = null;
    $status_headers = [];
    $current_caps = $caps;
    $user_logged_in = true;
    $user_member_of_blog = true;
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
        'canonical_shell',
    ];

    $requested_module = isset($_GET['module']) ? sanitize_key($_GET['module']) : 'calendar';
    $active_module    = in_array($requested_module, $allowed_modules, true) ? $requested_module : 'calendar';

    $aa_canonical_family = null;
    $aa_canonical_variant = null;
    $aa_shell_route_state = null;
    $aa_shell_route_message = null;
    $aa_canonical_url = '';

    try {
        if ($active_module === 'canonical') {
            $family_input = array_key_exists('family', $_GET) ? wp_unslash($_GET['family']) : null;
            $variant_input = array_key_exists('variant', $_GET) ? wp_unslash($_GET['variant']) : null;
            $canonical_registry = AA_Canonical_Core_Bootstrap::instance();
            $route_result = (new ResolveCanonicalRouteUseCase($canonical_registry))->execute([
                'family_key'  => $family_input,
                'variant_key' => $variant_input,
            ]);
            if (!$route_result['success']) {
                $error_code = (string) ($route_result['error']['code'] ?? '');
                if ($error_code === 'unknown_family' || $error_code === 'unknown_variant') {
                    wp_die('Familia o variante canónica no encontrada.', 'Error', ['response' => 404]);
                }
                wp_die('Parámetros de ruta canónica no válidos.', 'Error', ['response' => 400]);
            }
            $aa_canonical_family  = $route_result['data']['family'];
            $aa_canonical_variant = $route_result['data']['variant'];
            $aa_canonical_url     = AA_Canonical_Shell_Url_Policy::build_url(
                $aa_canonical_family->key(),
                $aa_canonical_variant->key()
            );
        } elseif ($active_module === 'canonical_shell') {
            $family_present = array_key_exists('family', $_GET);
            $variant_present = array_key_exists('variant', $_GET);
            $family_input = $family_present ? wp_unslash($_GET['family']) : null;
            $variant_input = $variant_present ? wp_unslash($_GET['variant']) : null;
            $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url();

            if (!$family_present && !$variant_present) {
                $aa_shell_route_state = 'missing_identity';
                $aa_shell_route_message = 'Identidad canónica no suministrada. Este módulo es un shell base en construcción.';
            } elseif ($family_present xor $variant_present) {
                $aa_shell_route_state = 'incomplete_identity';
                $aa_shell_route_message = 'La identidad canónica está incompleta: se requieren family y variant juntos.';
                status_header(400);
            } else {
                $canonical_registry = AA_Canonical_Core_Bootstrap::instance();
                $route_result = (new ResolveCanonicalRouteUseCase($canonical_registry))->execute([
                    'family_key'  => $family_input,
                    'variant_key' => $variant_input,
                ]);
                if (!$route_result['success']) {
                    $error_code = (string) ($route_result['error']['code'] ?? '');
                    if ($error_code === 'unknown_family' || $error_code === 'unknown_variant') {
                        $aa_shell_route_state = 'not_found';
                        $aa_shell_route_message = (string) ($route_result['error']['message'] ?? 'Familia o variante no encontrada.');
                        status_header(404);
                    } else {
                        $aa_shell_route_state = 'invalid_request';
                        $aa_shell_route_message = (string) ($route_result['error']['message'] ?? 'Solicitud canónica no válida.');
                        status_header(400);
                    }
                } else {
                    $aa_canonical_family  = $route_result['data']['family'];
                    $aa_canonical_variant = $route_result['data']['variant'];
                    $aa_shell_route_state = 'resolved';
                    $aa_shell_route_message = 'Ruta canónica resuelta.';
                    $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_url(
                        $aa_canonical_family->key(),
                        $aa_canonical_variant->key()
                    );
                }
            }
        }

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
            'status'                 => 200,
            'http_status'            => $status_headers !== [] ? end($status_headers) : 200,
            'active_module'          => $active_module,
            'aa_shell_route_state'   => $aa_shell_route_state,
            'aa_shell_route_message' => $aa_shell_route_message,
            'aa_canonical_family'    => $aa_canonical_family,
            'aa_canonical_variant'   => $aa_canonical_variant,
            'aa_canonical_url'       => $aa_canonical_url,
            'layout'                 => ($active_module === 'canonical' || $active_module === 'canonical_shell')
                ? 'canonical-layout'
                : 'shared-layout',
        ];
    } catch (RuntimeException $e) {
        return [
            'status'        => $last_die['code'] ?? 500,
            'http_status'   => $last_die['code'] ?? 500,
            'message'       => $last_die['message'] ?? $e->getMessage(),
            'active_module' => $active_module,
        ];
    }
}

// --- Router / acceso ---
$router_src = file_get_contents($plugin_root . '/includes/admin/ui/index.php');
ac_assert('Router allowlists canonical_shell', strpos($router_src, "'canonical_shell'") !== false);
ac_assert('Router uses canonical-layout for canonical_shell', strpos($router_src, "\$active_module === 'canonical' || \$active_module === 'canonical_shell'") !== false);

$res_ok = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'finance', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('Admin resolves finance.general on shell (access 200)', $res_ok['status'] === 200);
ac_assert('Shell route state is resolved', ($res_ok['aa_shell_route_state'] ?? '') === 'resolved');
ac_assert('Resolved family is finance', $res_ok['aa_canonical_family'] instanceof AA_Canonical_Family_Definition
    && $res_ok['aa_canonical_family']->key() === 'finance');
ac_assert('Resolved variant is general', $res_ok['aa_canonical_variant'] instanceof AA_Canonical_Variant_Definition
    && $res_ok['aa_canonical_variant']->key() === 'general');
ac_assert('Shell uses canonical-layout', ($res_ok['layout'] ?? '') === 'canonical-layout');
ac_assert('Shell URL keeps module=canonical_shell', strpos((string) ($res_ok['aa_canonical_url'] ?? ''), 'module=canonical_shell') !== false);

$res_denied = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'finance', 'variant' => 'general'],
    []
);
ac_assert('Non-admin cannot access canonical_shell (403)', ($res_denied['status'] ?? 0) === 403);

$res_missing = simulate_shell_router(['module' => 'canonical_shell'], ['manage_options' => true]);
ac_assert('Missing identity is controlled state', ($res_missing['aa_shell_route_state'] ?? '') === 'missing_identity');
ac_assert('Missing identity does not fall back to calendar', ($res_missing['active_module'] ?? '') === 'canonical_shell');
ac_assert('Missing identity remains HTTP 200 body path', ($res_missing['status'] ?? 0) === 200 && ($res_missing['http_status'] ?? 0) === 200);

$res_incomplete = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'finance'],
    ['manage_options' => true]
);
ac_assert('Only family yields incomplete_identity', ($res_incomplete['aa_shell_route_state'] ?? '') === 'incomplete_identity');
ac_assert('Incomplete identity sets status 400', ($res_incomplete['http_status'] ?? 0) === 400);

$res_incomplete_var = simulate_shell_router(
    ['module' => 'canonical_shell', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('Only variant yields incomplete_identity', ($res_incomplete_var['aa_shell_route_state'] ?? '') === 'incomplete_identity');

$res_invalid = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'Bad_Key!', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('Invalid key yields invalid_request', ($res_invalid['aa_shell_route_state'] ?? '') === 'invalid_request');
ac_assert('Invalid key sets status 400', ($res_invalid['http_status'] ?? 0) === 400);

$res_unknown = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'unknown_family', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('Unknown family yields not_found', ($res_unknown['aa_shell_route_state'] ?? '') === 'not_found');
ac_assert('Unknown family sets status 404', ($res_unknown['http_status'] ?? 0) === 404);

// Finance path intact
$res_fin = simulate_shell_router(
    ['module' => 'canonical', 'family' => 'finance', 'variant' => 'general'],
    []
);
ac_assert('Finance path still 200 for non-admin', ($res_fin['status'] ?? 0) === 200);
ac_assert('Finance still active_module=canonical', ($res_fin['active_module'] ?? '') === 'canonical');
ac_assert('Finance URL still module=canonical', strpos((string) ($res_fin['aa_canonical_url'] ?? ''), 'module=canonical&') !== false
    || (strpos((string) ($res_fin['aa_canonical_url'] ?? ''), 'module=canonical') !== false
        && strpos((string) ($res_fin['aa_canonical_url'] ?? ''), 'canonical_shell') === false));

// --- URL policy hermana ---
$shell_url = AA_Canonical_Shell_Base_Url_Policy::build_url('finance', 'general');
ac_assert('Shell base URL uses module=canonical_shell', strpos($shell_url, 'module=canonical_shell') !== false);
ac_assert('Shell base URL includes finance.general', strpos($shell_url, 'family=finance') !== false
    && strpos($shell_url, 'variant=general') !== false);
$finance_url = AA_Canonical_Shell_Url_Policy::build_url('finance', 'general');
ac_assert('Existing finance URL policy unchanged (module=canonical)', strpos($finance_url, 'module=canonical') !== false
    && strpos($finance_url, 'canonical_shell') === false);
ac_assert('Shell URL is allowlisted by shell policy', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($shell_url) === true);
ac_assert('Finance URL is not allowlisted as shell URL', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($finance_url) === false);

// --- Sidebar (PCU-5A: Tipos de registros reemplaza "Shell canónico") ---
$sidebar_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/sidebar.php');
ac_assert('Sidebar contains Tipos de registros label', strpos($sidebar_src, 'Tipos de registros') !== false);
ac_assert('Sidebar sin label provisional Shell canónico', strpos($sidebar_src, 'Shell canónico') === false);
ac_assert('Sidebar Shell link uses data-aa-nav-module=canonical_shell', strpos($sidebar_src, 'data-aa-nav-module="canonical_shell"') !== false);
ac_assert('Sidebar Shell highlight checks canonical_shell', strpos($sidebar_src, "\$active_module === 'canonical_shell'") !== false);
ac_assert('Sidebar nav gated by manage_options + enablement', strpos($sidebar_src, 'ReadCanonicalFamilyEnablementUseCase') !== false
    && strpos($sidebar_src, 'AA_Canonical_Family_Enablement_Nav') !== false
    && strpos($sidebar_src, 'aa-canonical-record-types-nav') !== false);
ac_assert('Sidebar Finanzas still uses AA_Canonical_Shell_Url_Policy', strpos($sidebar_src, "AA_Canonical_Shell_Url_Policy::build_url('finance', 'general')") !== false);
ac_assert('Sidebar Finanzas highlight still canonical only', preg_match(
    '/data-aa-nav-module="canonical"[\s\S]*?\$active_module === \'canonical\'/',
    $sidebar_src
) === 1);

$active_module = 'canonical_shell';
$current_caps = ['manage_options' => true];
$can_manage_options = true;
$aa_installation_slug = null;
$aa_finance_url = AA_Canonical_Shell_Url_Policy::build_url('finance', 'general');
$aa_canonical_family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
ob_start();
require $plugin_root . '/includes/admin/ui/shared/sidebar.php';
$sidebar_html = ob_get_clean();
ac_assert('Rendered sidebar includes Tipos de registros for manage_options', strpos($sidebar_html, 'Tipos de registros') !== false);
ac_assert('Rendered sidebar incluye contenedor nav dinámico', strpos($sidebar_html, 'id="aa-canonical-record-types-nav"') !== false);
ac_assert('Sin familias enabled: sin links shell falsos por defecto',
    strpos($sidebar_html, 'data-aa-nav-family=') === false
);
ac_assert('Shell current page does not aria-current Finanzas legacy',
    preg_match('/data-aa-nav-module="canonical"[^>]*aria-current="page"/', $sidebar_html) !== 1
);

$current_caps = [];
$can_manage_options = false;
$active_module = 'calendar';
ob_start();
require $plugin_root . '/includes/admin/ui/shared/sidebar.php';
$sidebar_no_admin = ob_get_clean();
ac_assert('Non-manage_options sidebar hides Tipos de registros', strpos($sidebar_no_admin, 'Tipos de registros') === false);
ac_assert('Non-manage_options sidebar still shows Finanzas', strpos($sidebar_no_admin, 'Finanzas') !== false);

// --- Root render + aislamiento ---
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $default;
    }
}
if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone {
        return new DateTimeZone('UTC');
    }
}
if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null): string {
        $tz = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone('UTC');
        $dt = (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone($tz);
        return $dt->format($format);
    }
}
final class ShellModuleEmptyFinanceWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? (string) (int) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }
    /** @return string|null */
    public function get_var(string $query) {
        $this->last_error = '';
        return strpos($query, 'COUNT(*)') !== false ? '0' : null;
    }
    /** @return object|null */
    public function get_row(string $query) {
        $this->last_error = '';
        return null;
    }
    /** @return array<int,mixed> */
    public function get_results(string $query) {
        $this->last_error = '';
        return [];
    }
}
$GLOBALS['wpdb'] = new ShellModuleEmptyFinanceWpdbMock();

require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php';

$aa_shell_route_state = 'resolved';
$aa_shell_route_message = 'Ruta canónica resuelta.';
$aa_canonical_family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
$aa_canonical_variant = AA_Canonical_Core_Bootstrap::instance()->variant('finance', 'general');
$aa_shell_view = AA_Canonical_Shell_View_Composer::compose_family($aa_canonical_family, $aa_canonical_variant, 1);
ob_start();
require $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php';
$shell_html = ob_get_clean();
ac_assert('Shell root id present', strpos($shell_html, 'id="aa-canonical-shell-root"') !== false);
ac_assert('Shell shows Finanzas label from registry', strpos($shell_html, 'Finanzas') !== false);
ac_assert('Shell shows General label from registry', strpos($shell_html, 'General') !== false);
ac_assert('Shell shows qualified finance.general', strpos($shell_html, 'finance.general') !== false);
ac_assert('Shell shows empty finance (not pending)', strpos($shell_html, 'Sin contenedores') !== false
    && strpos($shell_html, 'Lectura pendiente') === false);
ac_assert('Shell empty has no preview CTA without constant', strpos($shell_html, 'Ver demostración del shell') === false);
ac_assert('Shell resolved root has no script tags', strpos($shell_html, '<script') === false);
ac_assert('Shell resolved root has no amount', stripos($shell_html, 'amount') === false);

$module_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
$forbidden_tokens = [
    'modules/canonical/finance',
    'AA_FINANCE_DATA',
    'finance-module.js',
    'amount_total',
    'FinanceContainer',
    'FinanceRecord',
    'aa_list_finance',
    'aa_create_finance',
    '$wpdb',
];
foreach ($forbidden_tokens as $token) {
    ac_assert('Shell module source excludes ' . $token, strpos($module_src, $token) === false);
}

$dispatcher_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical/index.php');
ac_assert('Finance dispatcher still maps finance template', strpos($dispatcher_src, "'finance' =>") !== false
    && strpos($dispatcher_src, 'finance/index.php') !== false);
ac_assert('Shell module is not required by finance dispatcher', strpos($dispatcher_src, 'canonical_shell') === false);

$plugin_bootstrap = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert('Plugin loads shell base URL policy', strpos($plugin_bootstrap, 'class-aa-canonical-shell-base-url-policy.php') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
