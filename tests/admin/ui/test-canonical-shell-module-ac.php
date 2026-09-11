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
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string {
        return 'nonce-' . $action;
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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/ResolveCanonicalRouteUseCase.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';

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
 * Simula la rama de allowlist + resolución de index.php para canonical_shell (LEGACY-X).
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
        'canonical_shell',
    ];

    $requested_module = isset($_GET['module']) ? sanitize_key($_GET['module']) : 'calendar';
    $active_module = 'calendar';

    $aa_canonical_family = null;
    $aa_canonical_variant = null;
    $aa_shell_route_state = null;
    $aa_shell_route_message = null;
    $aa_canonical_url = '';

    try {
        // LEGACY-X: module=canonical retirado — mismo tratamiento que módulo no encontrado.
        if ($requested_module === 'canonical') {
            wp_die('UI module not found', 'Error', ['response' => 404]);
        }

        $active_module = in_array($requested_module, $allowed_modules, true) ? $requested_module : 'calendar';

        if ($active_module === 'canonical_shell') {
            $family_present = array_key_exists('family', $_GET);
            $family_input = $family_present ? wp_unslash($_GET['family']) : null;
            $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url();

            if (!$family_present) {
                $aa_shell_route_state = 'resolved';
                $aa_shell_route_message = 'Listado general de listas.';
                $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url();
            } else {
                $canonical_registry = AA_Canonical_Core_Bootstrap::instance();
                $route_result = (new ResolveCanonicalRouteUseCase($canonical_registry))->execute([
                    'family_key' => $family_input,
                ]);
                if (!$route_result['success']) {
                    $error_code = (string) ($route_result['error']['code'] ?? '');
                    if ($error_code === 'unknown_family') {
                        $aa_shell_route_state = 'not_found';
                        $aa_shell_route_message = (string) ($route_result['error']['message'] ?? 'Familia no encontrada.');
                        status_header(404);
                    } else {
                        $aa_shell_route_state = 'invalid_request';
                        $aa_shell_route_message = (string) ($route_result['error']['message'] ?? 'Solicitud canónica no válida.');
                        status_header(400);
                    }
                } else {
                    $aa_canonical_family = $route_result['data']['family'];
                    $aa_shell_route_state = 'resolved';
                    $aa_shell_route_message = 'Ruta canónica resuelta.';
                    $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_url(
                        $aa_canonical_family->key()
                    );
                }
            }
        }

        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado', 'Error', ['response' => 403]);
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
            'layout'                 => $active_module === 'canonical_shell'
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
ac_assert(
    'Router no allowlistea module=canonical legacy',
    preg_match("/\\\$allowed_modules\\s*=\\s*\\[[^\\]]*\\n\\s*'canonical'\\s*,/s", $router_src) !== 1
);
ac_assert(
    'Router retira module=canonical con 404',
    strpos($router_src, "\$requested_module === 'canonical'") !== false
    && strpos($router_src, "'response' => 404") !== false
);
ac_assert(
    'Router uses canonical-layout for canonical_shell',
    strpos($router_src, "\$active_module === 'canonical_shell'") !== false
    && strpos($router_src, 'canonical-layout.php') !== false
);
ac_assert('Router opens all-lists without family', strpos($router_src, 'compose_all_containers') !== false);
ac_assert('Router usa available_families para alcance general', strpos($router_src, 'AA_Canonical_Family_Enablement_Nav::available_families') !== false);
ac_assert(
    'Router redirige bare module con N=1 antes de HTML',
    strpos($router_src, 'wp_safe_redirect($aa_canonical_url, 302)') !== false
    && preg_match(
        '/count\(\$enabled_families\)\s*===\s*1[\s\S]*?wp_safe_redirect\(\$aa_canonical_url,\s*302\)[\s\S]*?exit;/',
        $router_src
    ) === 1
);
ac_assert('Router no longer treats bare module as missing_identity', strpos($router_src, "\$aa_shell_route_state = 'missing_identity'") === false);

$shell_ui_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
ac_assert('All-lists page title attribute', strpos($shell_ui_src, 'Todas las listas') !== false);

$res_ok = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'finance', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('Admin resolves finance on shell (access 200)', $res_ok['status'] === 200);
ac_assert('Shell route state is resolved', ($res_ok['aa_shell_route_state'] ?? '') === 'resolved');
ac_assert('Resolved family is finance', $res_ok['aa_canonical_family'] instanceof AA_Canonical_Family_Definition
    && $res_ok['aa_canonical_family']->key() === 'finance');
ac_assert('Shell uses canonical-layout', ($res_ok['layout'] ?? '') === 'canonical-layout');
ac_assert('Shell URL keeps module=canonical_shell', strpos((string) ($res_ok['aa_canonical_url'] ?? ''), 'module=canonical_shell') !== false);

$res_denied = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'finance', 'variant' => 'general'],
    []
);
ac_assert('Non-admin cannot access canonical_shell (403)', ($res_denied['status'] ?? 0) === 403);

$res_all = simulate_shell_router(['module' => 'canonical_shell'], ['manage_options' => true]);
ac_assert('Module without family resolves all-lists scope', ($res_all['aa_shell_route_state'] ?? '') === 'resolved');
ac_assert('All-lists does not fall back to calendar', ($res_all['active_module'] ?? '') === 'canonical_shell');
ac_assert('All-lists remains HTTP 200 body path', ($res_all['status'] ?? 0) === 200 && ($res_all['http_status'] ?? 0) === 200);
ac_assert('All-lists URL is module-only', strpos((string) ($res_all['aa_canonical_url'] ?? ''), 'module=canonical_shell') !== false
    && strpos((string) ($res_all['aa_canonical_url'] ?? ''), 'family=') === false);

$res_family_only = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'finance'],
    ['manage_options' => true]
);
ac_assert('Family-only resolves without incomplete_identity', ($res_family_only['aa_shell_route_state'] ?? '') === 'resolved');

$res_variant_only = simulate_shell_router(
    ['module' => 'canonical_shell', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('Variant-only without family is all-lists resolved', ($res_variant_only['aa_shell_route_state'] ?? '') === 'resolved');

$res_invalid = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'Bad_Key!', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('Invalid key yields invalid_request', ($res_invalid['aa_shell_route_state'] ?? '') === 'invalid_request');
ac_assert('Invalid key sets status 400', ($res_invalid['http_status'] ?? 0) === 400);
ac_assert('Invalid family does not become all-lists', strpos((string) ($res_invalid['aa_canonical_url'] ?? ''), 'family=') !== false
    || ($res_invalid['aa_shell_route_state'] ?? '') === 'invalid_request');

$res_unknown = simulate_shell_router(
    ['module' => 'canonical_shell', 'family' => 'unknown_family', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('Unknown family yields not_found', ($res_unknown['aa_shell_route_state'] ?? '') === 'not_found');
ac_assert('Unknown family sets status 404', ($res_unknown['http_status'] ?? 0) === 404);

// LEGACY-X: module=canonical retirado
$res_fin = simulate_shell_router(
    ['module' => 'canonical', 'family' => 'finance', 'variant' => 'general'],
    ['manage_options' => true]
);
ac_assert('module=canonical retires with 404', ($res_fin['status'] ?? 0) === 404);

// --- URL policy shell ---
$shell_url = AA_Canonical_Shell_Base_Url_Policy::build_url('finance');
ac_assert('Shell base URL uses module=canonical_shell', strpos($shell_url, 'module=canonical_shell') !== false);
ac_assert('Shell base URL includes family=finance without variant', strpos($shell_url, 'family=finance') !== false
    && strpos($shell_url, 'variant=') === false);
ac_assert('Shell URL is allowlisted by shell policy', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($shell_url) === true);
$retired_canonical_url = admin_url('admin-post.php') . '?action=aa_iframe_content&module=canonical&family=finance&variant=general';
ac_assert(
    'Retired module=canonical URL is not allowlisted as shell URL',
    AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($retired_canonical_url) === false
);
ac_assert(
    'Shell_Url_Policy ausente (LEGACY-X)',
    !is_file($plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-url-policy.php')
);

// --- Sidebar (Ciclo 2A: entrada Listas; sin grupo Tipos de registros) ---
$sidebar_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/sidebar.php');
ac_assert('Sidebar contiene entrada Listas', strpos($sidebar_src, '>Listas</span>') !== false);
ac_assert('Sidebar sin label provisional Shell canónico', strpos($sidebar_src, 'Shell canónico') === false);
ac_assert('Sidebar sin grupo Tipos de registros', strpos($sidebar_src, 'Tipos de registros') === false);
ac_assert('Sidebar sin contenedor nav dinámico de tipos', strpos($sidebar_src, 'aa-canonical-record-types-nav') === false);
ac_assert('Sidebar Listas usa data-aa-nav-module=canonical_shell', strpos($sidebar_src, 'data-aa-nav-module="canonical_shell"') !== false);
ac_assert('Sidebar Listas highlight checks canonical_shell', strpos($sidebar_src, "\$active_module === 'canonical_shell'") !== false
    || strpos($sidebar_src, '$aa_lists_active') !== false);
ac_assert('Sidebar Listas usa build_module_url', strpos($sidebar_src, 'AA_Canonical_Shell_Base_Url_Policy::build_module_url') !== false);
ac_assert(
    'Sidebar Listas enlaza familia única cuando nav tiene un ítem',
    strpos($sidebar_src, 'count($aa_canonical_record_types_nav) === 1') !== false
);
ac_assert('Sidebar Enablement_Nav fallback conservado para hoist header', strpos($sidebar_src, 'ReadCanonicalFamilyEnablementUseCase') !== false
    && strpos($sidebar_src, 'AA_Canonical_Family_Enablement_Nav') !== false);
ac_assert(
    'Sidebar sin data-aa-nav-module=canonical legacy',
    strpos($sidebar_src, 'data-aa-nav-module="canonical"') === false
);
ac_assert(
    'Sidebar sin enlace Finanzas legacy',
    strpos($sidebar_src, '>Finanzas</span>') === false
    && strpos($sidebar_src, 'AA_Canonical_Shell_Url_Policy') === false
);

$active_module = 'canonical_shell';
$current_caps = ['manage_options' => true];
$can_manage_options = true;
$aa_installation_slug = null;
$aa_canonical_family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
ob_start();
require $plugin_root . '/includes/admin/ui/shared/sidebar.php';
$sidebar_html = ob_get_clean();
ac_assert('Rendered sidebar incluye Listas para manage_options', strpos($sidebar_html, '>Listas</span>') !== false);
ac_assert('Rendered Listas activo en canonical_shell', preg_match(
    '/data-aa-nav-module="canonical_shell"[^>]*aria-current="page"/',
    $sidebar_html
) === 1);
ac_assert('Sin grupo Tipos ni links data-aa-nav-family',
    strpos($sidebar_html, 'Tipos de registros') === false
    && strpos($sidebar_html, 'data-aa-nav-family=') === false
);
ac_assert(
    'Rendered sidebar sin nav Finanzas legacy',
    strpos($sidebar_html, 'data-aa-nav-module="canonical"') === false
    && strpos($sidebar_html, '>Finanzas</span>') === false
);

$aa_canonical_record_types_nav = [
    [
        'family_key' => 'archive',
        'label' => 'Archivo',
        'url' => AA_Canonical_Shell_Base_Url_Policy::build_url('archive'),
    ],
];
ob_start();
require $plugin_root . '/includes/admin/ui/shared/sidebar.php';
$sidebar_one = ob_get_clean();
ac_assert(
    'Listas con N=1 apunta a family=archive',
    preg_match(
        '/href="[^"]*family=archive[^"]*"[^>]*data-aa-nav-module="canonical_shell"/',
        $sidebar_one
    ) === 1
    || (
        strpos($sidebar_one, 'data-aa-nav-module="canonical_shell"') !== false
        && strpos($sidebar_one, 'family=archive') !== false
        && preg_match(
            '/<!-- Listas[\s\S]*?family=archive[\s\S]*?>Listas<\/span>/',
            $sidebar_one
        ) === 1
    )
);

$aa_canonical_record_types_nav = [];
ob_start();
require $plugin_root . '/includes/admin/ui/shared/sidebar.php';
$sidebar_zero = ob_get_clean();
ac_assert(
    'Listas con N=0 apunta a module sin family',
    preg_match(
        '/<!-- Listas[\s\S]*?href="([^"]+)"[\s\S]*?>Listas<\/span>/',
        $sidebar_zero,
        $m_zero
    ) === 1
    && strpos($m_zero[1], 'module=canonical_shell') !== false
    && strpos($m_zero[1], 'family=') === false
);

$current_caps = [];
$can_manage_options = false;
$active_module = 'calendar';
ob_start();
require $plugin_root . '/includes/admin/ui/shared/sidebar.php';
$sidebar_no_admin = ob_get_clean();
ac_assert('Non-manage_options sidebar hides Listas', strpos($sidebar_no_admin, '>Listas</span>') === false);
ac_assert(
    'Non-manage_options sidebar sin Finanzas legacy',
    strpos($sidebar_no_admin, 'Finanzas') === false
    && strpos($sidebar_no_admin, 'data-aa-nav-module="canonical"') === false
);

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
final class ShellModuleUniversalEmptyWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public function prepare(string $query, ...$args): string {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            if (is_array($arg)) {
                continue;
            }
            $val = is_numeric($arg) ? (string) (int) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }
    /** @return string|null */
    public function get_var(string $query) {
        $this->last_error = '';
        if (stripos($query, 'SHOW TABLES LIKE') !== false) {
            return $this->prefix . 'aa_canonical_families';
        }
        if (strpos($query, 'COUNT(*)') !== false) {
            return '0';
        }
        if (preg_match("/WHERE family_key = '([^']+)'/", $query, $m)) {
            return $m[1] === 'finance' ? '1' : ($m[1] === 'archive' ? '2' : null);
        }
        return null;
    }
    /** @return object|null */
    public function get_row(string $query) {
        $this->last_error = '';
        return null;
    }
    /** @return array<int,mixed> */
    public function get_results(string $query) {
        $this->last_error = '';
        if (stripos($query, 'is_enabled') !== false) {
            return [
                ['family_key' => 'finance', 'is_enabled' => 1],
                ['family_key' => 'archive', 'is_enabled' => 1],
            ];
        }
        return [];
    }
}
$GLOBALS['wpdb'] = new ShellModuleUniversalEmptyWpdbMock();

require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPort.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-store.php';
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
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
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php';

$aa_shell_route_state = 'resolved';
$aa_shell_route_message = 'Ruta canónica resuelta.';
$aa_canonical_family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
$aa_canonical_variant = null;
$aa_shell_view = AA_Canonical_Shell_View_Composer::compose_family($aa_canonical_family, 1);
ob_start();
require $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php';
$shell_html = ob_get_clean();
ac_assert('Shell root id present', strpos($shell_html, 'id="aa-canonical-shell-root"') !== false);
ac_assert('Shell shows Finanzas label from registry', strpos($shell_html, 'Finanzas') !== false);
ac_assert('Shell shows qualified finance', strpos($shell_html, 'finance') !== false);
ac_assert('Shell shows empty finance (not pending)', strpos($shell_html, 'Sin contenedores') !== false
    && strpos($shell_html, 'Lectura pendiente') === false);
ac_assert('Shell empty copy universal', strpos($shell_html, 'Aún no hay contenedores en este tipo de registro.') !== false);
ac_assert('Shell empty has no preview CTA without constant', strpos($shell_html, 'Ver demostración del shell') === false);
ac_assert('Shell CTA Nueva lista es FAB', strpos($shell_html, 'id="aa-shell-fab-stack"') !== false
    && strpos($shell_html, 'id="aa-shell-open-create-btn"') !== false
    && strpos($shell_html, 'aria-label="Nueva lista"') !== false
    && strpos($shell_html, 'bg-violet-600') !== false);
ac_assert('Shell listados resueltos sin badge Resuelto ni header card duplicado', strpos($shell_html, '>Resuelto<') === false
    && strpos($shell_html, 'class="sr-only"') !== false
    && preg_match('/<h1 class="sr-only">Finanzas<\/h1>/', $shell_html) === 1);
ac_assert('Shell container modal present', strpos($shell_html, 'id="aa-shell-container-modal"') !== false
    && strpos($shell_html, 'Nombre de la lista') !== false
    && strpos($shell_html, 'Crear lista') !== false);
ac_assert('Shell modal z-index sobre FAB', strpos($shell_html, 'id="aa-shell-container-modal"') !== false
    && preg_match('/id="aa-shell-container-modal"[\s\S]*?z-\[300\]/', $shell_html) === 1);
ac_assert('Shell container form config present', strpos($shell_html, 'AA_CANONICAL_SHELL_CONTAINER_FORM') !== false
    && strpos($shell_html, 'aa_create_canonical_container') !== false
    && strpos($shell_html, 'aa_update_canonical_container') !== false
    && strpos($shell_html, 'aa_delete_canonical_container') !== false);
ac_assert('Shell container form script loaded', strpos($shell_html, 'canonical-shell-container-form.js') !== false);
ac_assert('Shell delete container modal present', strpos($shell_html, 'id="aa-shell-delete-container-modal"') !== false
    && strpos($shell_html, 'Eliminar lista') !== false
    && strpos($shell_html, 'Recargar listas') !== false);
$delete_container_modal_pos = strpos($shell_html, 'id="aa-shell-delete-container-modal"');
$container_form_script_pos = strpos($shell_html, 'canonical-shell-container-form.js');
ac_assert(
    'Shell delete container modal renders before synchronous container-form.js (IIFE needs DOM)',
    $delete_container_modal_pos !== false
    && $container_form_script_pos !== false
    && $delete_container_modal_pos < $container_form_script_pos
);
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

ac_assert(
    'Módulo legacy modules/canonical ausente',
    !is_dir($plugin_root . '/includes/admin/ui/modules/canonical')
);
ac_assert(
    'FinanceUseCaseSupport ausente',
    !is_file($plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php')
);

$plugin_bootstrap = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert('Plugin loads shell base URL policy', strpos($plugin_bootstrap, 'class-aa-canonical-shell-base-url-policy.php') !== false);
ac_assert(
    'Plugin no carga Shell_Url_Policy legacy',
    strpos($plugin_bootstrap, 'class-aa-canonical-shell-url-policy.php') === false
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
