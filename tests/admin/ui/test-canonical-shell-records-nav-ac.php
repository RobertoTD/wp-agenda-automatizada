<?php
/**
 * AC Test — Canonical shell records SSR navigation (SB1-3B).
 *
 * Ejecutar: php tests/admin/ui/test-canonical-shell-records-nav-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$status_headers = [];
$aa_timezone_option = 'UTC';

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
if (!function_exists('status_header')) {
    function status_header(int $code): void {
        global $status_headers;
        $status_headers[] = $code;
    }
}
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        global $aa_timezone_option;
        if ($key === 'aa_timezone') {
            return $aa_timezone_option;
        }
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
if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url) {
        return parse_url($url);
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
if (!function_exists('aa_asset_url')) {
    function aa_asset_url(string $relative_path): string {
        return 'https://example.com/assets/' . ltrim($relative_path, '/');
    }
}
if (!defined('AA_PLUGIN_URL')) {
    define('AA_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-agenda-automatizada/');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

final class ShellRecordsEmptyFinanceWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';

    public function prepare(string $query, ...$args): string {
        $flat = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $inner) {
                    $flat[] = $inner;
                }
            } else {
                $flat[] = $arg;
            }
        }
        foreach ($flat as $arg) {
            $val = is_numeric($arg) ? (string) (int) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }

    /** @return string|null */
    public function get_var(string $query) {
        $this->last_error = '';
        // PCU-5B: enablement store checks aa_canonical_families via SHOW TABLES.
        if (stripos($query, 'SHOW TABLES LIKE') !== false && strpos($query, 'aa_canonical_families') !== false) {
            return $this->prefix . 'aa_canonical_families';
        }
        if (strpos($query, 'COUNT(*)') !== false) {
            return '0';
        }
        // Relational resolve_family_id for finance/archive in this harness.
        if (strpos($query, 'SELECT id FROM') !== false && strpos($query, 'aa_canonical_families') !== false) {
            return '1';
        }
        return null;
    }

    /** @return object|null|array|false */
    public function get_row(string $query, $output = null) {
        $this->last_error = '';
        return null;
    }

    /** @return array<int,mixed>|false */
    public function get_results(string $query, $output = null) {
        $this->last_error = '';
        // PCU-5B enablement SELECT: provisioned + enabled for declared families.
        if (strpos($query, 'is_enabled') !== false && strpos($query, 'aa_canonical_families') !== false) {
            return [
                ['family_key' => 'finance', 'is_enabled' => 1],
                ['family_key' => 'archive', 'is_enabled' => 1],
            ];
        }
        return [];
    }
}

$GLOBALS['wpdb'] = new ShellRecordsEmptyFinanceWpdbMock();

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPagination.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
require_once $plugin_root . '/includes/application/canonical/ResolveCanonicalRouteUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordPageContributorRegistry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityShellRecordsEnricher.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Phone_Normalizer.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Email_Normalizer.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-amount-shell-presenter.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-whatsapp-shell-presenter.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-phone-shell-presenter.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-email-shell-presenter.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-images-shell-presenter.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/class-aa-canonical-family-icon-markup.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-dossier-shell-presenter.php';

if (!class_exists('AA_Canonical_Capability_Page_Contributor_Bootstrap')) {
    final class AA_Canonical_Capability_Page_Contributor_Bootstrap {
        public static function bootstrap() {
            return new CanonicalCapabilityRecordPageContributorRegistry();
        }
    }
}

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

ac_assert(
    'Harness PCU-5B: SHOW TABLES aa_canonical_families',
    $GLOBALS['wpdb']->get_var("SHOW TABLES LIKE 'wp_aa_canonical_families'") === 'wp_aa_canonical_families'
);
ac_assert(
    'Harness PCU-5B: enablement rows finance+archive',
    count($GLOBALS['wpdb']->get_results('SELECT family_key, is_enabled FROM `wp_aa_canonical_families`')) === 2
);

function render_shell(array $vars): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__, 3) . '/includes/admin/ui/modules/canonical_shell/index.php';
    return (string) ob_get_clean();
}

function query_has(string $url, string $key, ?string $expected = null): bool {
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['query'])) {
        return false;
    }
    parse_str($parts['query'], $q);
    if (!array_key_exists($key, $q)) {
        return false;
    }
    if ($expected === null) {
        return true;
    }
    return (string) $q[$key] === $expected;
}

function query_missing(string $url, string $key): bool {
    $parts = parse_url($url);
    $q = [];
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $q);
    }
    return !array_key_exists($key, $q);
}

/**
 * Simula validación + composición de la rama canonical_shell de index.php.
 *
 * @return array<string,mixed>
 */
function simulate_shell_records_router(array $get): array {
    global $status_headers;
    $status_headers = [];
    $_GET = $get;

    $family_present = array_key_exists('family', $_GET);
    $variant_present = array_key_exists('variant', $_GET);
    $shell_mode_present = array_key_exists('shell_mode', $_GET);
    $shell_mode_raw = $shell_mode_present ? wp_unslash($_GET['shell_mode']) : null;
    $shell_mode = is_string($shell_mode_raw) ? sanitize_key($shell_mode_raw) : '';
    $family_input = $family_present ? wp_unslash($_GET['family']) : null;
    $variant_input = $variant_present ? wp_unslash($_GET['variant']) : null;

    $view_present = array_key_exists('view', $_GET);
    $view_raw = $view_present ? wp_unslash($_GET['view']) : null;
    $view = is_string($view_raw) ? sanitize_key($view_raw) : '';
    $container_id_present = array_key_exists('container_id', $_GET);
    $containers_page_present = array_key_exists('containers_page', $_GET);

    $aa_shell_route_state = null;
    $aa_shell_route_message = null;
    $aa_shell_view = null;
    $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url();

    $shell_page = 1;
    $page_invalid = false;
    if (array_key_exists('page', $_GET)) {
        $parsed_page = AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value(wp_unslash($_GET['page']));
        if ($parsed_page === null) {
            $page_invalid = true;
        } else {
            $shell_page = $parsed_page;
        }
    }

    $shell_container_id = null;
    $shell_containers_page = 1;
    $records_transport_invalid = false;
    $records_transport_message = '';

    if ($view_present) {
        if (!is_string($view_raw) || $view !== AA_Canonical_Shell_Base_Url_Policy::VIEW_RECORDS) {
            $records_transport_invalid = true;
            $records_transport_message = 'El parámetro view no es válido.';
        } elseif (!$container_id_present) {
            $records_transport_invalid = true;
            $records_transport_message = 'El parámetro container_id es obligatorio para view=records.';
        } else {
            $parsed_container_id = AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id(
                wp_unslash($_GET['container_id'])
            );
            if ($parsed_container_id === null) {
                $records_transport_invalid = true;
                $records_transport_message = 'El parámetro container_id no es válido.';
            } else {
                $shell_container_id = $parsed_container_id;
            }
        }
        if (!$records_transport_invalid && $containers_page_present) {
            $parsed_containers_page = AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value(
                wp_unslash($_GET['containers_page'])
            );
            if ($parsed_containers_page === null) {
                $records_transport_invalid = true;
                $records_transport_message = 'El parámetro containers_page no es válido.';
            } else {
                $shell_containers_page = $parsed_containers_page;
            }
        }
    } elseif ($container_id_present || $containers_page_present) {
        $records_transport_invalid = true;
        $records_transport_message = 'container_id y containers_page solo se admiten con view=records.';
    }

    $is_records_view = (
        !$page_invalid
        && !$records_transport_invalid
        && $view === AA_Canonical_Shell_Base_Url_Policy::VIEW_RECORDS
        && $shell_container_id !== null
    );

    if ($page_invalid) {
        $aa_shell_route_state = 'invalid_request';
        $aa_shell_route_message = 'El parámetro page no es válido.';
        status_header(400);
    } elseif ($records_transport_invalid) {
        $aa_shell_route_state = 'invalid_request';
        $aa_shell_route_message = $records_transport_message;
        status_header(400);
    } elseif ($shell_mode === AA_Canonical_Shell_Base_Url_Policy::SHELL_MODE_PREVIEW) {
        if ($family_present) {
            $aa_shell_route_state = 'invalid_request';
            $aa_shell_route_message = 'El modo preview no admite family.';
            status_header(400);
        } elseif (!AA_Canonical_Shell_View_Composer::is_preview_enabled()) {
            $aa_shell_route_state = 'preview_unavailable';
            $aa_shell_route_message = 'La demostración del shell no está disponible.';
            status_header(404);
        } elseif ($is_records_view) {
            $aa_shell_route_state = 'preview';
            $aa_shell_route_message = 'Demostración del shell.';
            $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_preview_records_url(
                $shell_container_id,
                $shell_page > 1 ? $shell_page : null,
                $shell_containers_page > 1 ? $shell_containers_page : null
            );
            $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_preview_records(
                $shell_container_id,
                $shell_page,
                $shell_containers_page
            );
        } else {
            $aa_shell_route_state = 'preview';
            $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_preview($shell_page);
        }
    } elseif ($shell_mode_present && $shell_mode !== '') {
        $aa_shell_route_state = 'invalid_request';
        status_header(400);
    } elseif (!$family_present) {
        if ($is_records_view || $view_present) {
            $aa_shell_route_state = 'invalid_request';
            status_header(400);
        } else {
            $aa_shell_route_state = 'resolved';
            $aa_shell_view = [
                'shell_view' => 'containers',
                'lists_scope' => 'all',
                'read_state' => 'empty',
                'family_label' => 'Todas las listas',
                'items_view' => [],
                'available_families' => [],
            ];
        }
    } else {
        $route_result = (new ResolveCanonicalRouteUseCase(AA_Canonical_Core_Bootstrap::instance()))->execute([
            'family_key' => $family_input,
        ]);
        if (!$route_result['success']) {
            $aa_shell_route_state = 'not_found';
            status_header(404);
        } elseif ($is_records_view) {
            $family = $route_result['data']['family'];
            $aa_shell_route_state = 'resolved';
            $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_family_records($family, $shell_container_id,
                $shell_page,
                $shell_containers_page
            );
        } else {
            $aa_shell_route_state = 'resolved';
            $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_family(
                $route_result['data']['family'],
                $shell_page
            );
        }
    }

    return [
        'http_status' => $status_headers !== [] ? end($status_headers) : 200,
        'route_state' => $aa_shell_route_state,
        'view' => $aa_shell_view,
        'url' => $aa_canonical_url,
    ];
}

// --- URL builders ---
$family_records = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 7, 1, 1);
ac_assert('Family records URL omits page=1 and containers_page=1', query_has($family_records, 'view', 'records')
    && query_has($family_records, 'container_id', '7')
    && query_has($family_records, 'family', 'finance')
    && query_has($family_records, 'records_view', 'simple')
    && query_missing($family_records, 'page')
    && query_missing($family_records, 'containers_page'));

$family_records_p = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 7, 2, 3);
ac_assert('Family records URL keeps page and containers_page', query_has($family_records_p, 'page', '2')
    && query_has($family_records_p, 'containers_page', '3'));

$preview_records = AA_Canonical_Shell_Base_Url_Policy::build_preview_records_url(1, 2, 3);
ac_assert('Preview records URL shape', query_has($preview_records, 'shell_mode', 'preview')
    && query_has($preview_records, 'view', 'records')
    && query_has($preview_records, 'container_id', '1')
    && query_has($preview_records, 'page', '2')
    && query_has($preview_records, 'containers_page', '3')
    && query_missing($preview_records, 'family'));
ac_assert('Preview records URL allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($preview_records));
ac_assert('Family records URL allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($family_records_p));

ac_assert('container_id=0 invalid', AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id('0') === null);
ac_assert('container_id=-1 invalid', AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id('-1') === null);
ac_assert('container_id=1.5 invalid', AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id('1.5') === null);
ac_assert('container_id=abc invalid', AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id('abc') === null);
ac_assert('container_id array invalid', AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id(['1']) === null);
ac_assert('container_id=12 valid', AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id('12') === 12);

$bad_view = AA_Canonical_Shell_Base_Url_Policy::build_preview_url(null) . '&view=detail';
ac_assert('Unknown view rejected by allowlist', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($bad_view) === false);
$cid_without_view = AA_Canonical_Shell_Base_Url_Policy::build_preview_url(null) . '&container_id=1';
ac_assert('container_id without view rejected', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($cid_without_view) === false);
$cp_without_view = AA_Canonical_Shell_Base_Url_Policy::build_preview_url(null) . '&containers_page=2';
ac_assert('containers_page without view rejected', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($cp_without_view) === false);

// --- Router transport ---
$r = simulate_shell_records_router(['module' => 'canonical_shell', 'view' => 'nope', 'container_id' => '1']);
ac_assert('Invalid view → 400', ($r['http_status'] ?? 0) === 400 && ($r['route_state'] ?? '') === 'invalid_request');

$r = simulate_shell_records_router(['module' => 'canonical_shell', 'view' => 'records']);
ac_assert('view=records without container_id → 400', ($r['http_status'] ?? 0) === 400);

$r = simulate_shell_records_router(['module' => 'canonical_shell', 'view' => 'records', 'container_id' => '0']);
ac_assert('container_id=0 → 400', ($r['http_status'] ?? 0) === 400);

$r = simulate_shell_records_router(['module' => 'canonical_shell', 'view' => 'records', 'container_id' => ['1']]);
ac_assert('container_id array → 400', ($r['http_status'] ?? 0) === 400);

$r = simulate_shell_records_router(['module' => 'canonical_shell', 'container_id' => '1']);
ac_assert('container_id without view → 400', ($r['http_status'] ?? 0) === 400);

$r = simulate_shell_records_router(['module' => 'canonical_shell', 'containers_page' => '2']);
ac_assert('containers_page without view → 400', ($r['http_status'] ?? 0) === 400);

$r = simulate_shell_records_router([
    'module' => 'canonical_shell',
    'shell_mode' => 'preview',
    'view' => 'records',
    'container_id' => '1',
]);
ac_assert('Preview disabled → 404', ($r['http_status'] ?? 0) === 404 && ($r['route_state'] ?? '') === 'preview_unavailable');

define('AA_CANONICAL_SHELL_PREVIEW', true);

$r = simulate_shell_records_router([
    'module' => 'canonical_shell',
    'shell_mode' => 'preview',
    'view' => 'records',
    'container_id' => '1',
    'page' => '2',
    'containers_page' => '2',
]);
ac_assert('Preview records resolved → 200', ($r['http_status'] ?? 0) === 200 && ($r['route_state'] ?? '') === 'preview');
ac_assert('Preview records shell_view', ($r['view']['shell_view'] ?? '') === 'records');
ac_assert('Preview records resolved_page', ($r['view']['read_state'] ?? '') === 'resolved_page');
ac_assert('Back preserves containers_page=2', query_has((string) ($r['view']['back_url'] ?? ''), 'page', '2')
    && query_has((string) ($r['view']['back_url'] ?? ''), 'shell_mode', 'preview')
    && query_missing((string) ($r['view']['back_url'] ?? ''), 'view'));
ac_assert('Prev/next keep view+container+containers_page', query_has((string) ($r['view']['prev_url'] ?? ''), 'view', 'records')
    && query_has((string) ($r['view']['prev_url'] ?? ''), 'container_id', '1')
    && query_has((string) ($r['view']['prev_url'] ?? ''), 'containers_page', '2'));

$status_headers = [];
$empty = AA_Canonical_Shell_View_Composer::compose_preview_records(2, 1, 1);
ac_assert('Empty container → empty state 200', ($empty['read_state'] ?? '') === 'empty'
    && ($status_headers === [] || end($status_headers) === 200));
ac_assert('Empty still has parent container', is_array($empty['container'] ?? null));

$status_headers = [];
$missing = AA_Canonical_Shell_View_Composer::compose_preview_records(999, 1, 1);
ac_assert('Missing container → container_not_found', ($missing['read_state'] ?? '') === 'container_not_found');
ac_assert('Missing container → 404', in_array(404, $status_headers, true));

$status_headers = [];
$family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
$not_found_records = AA_Canonical_Shell_View_Composer::compose_family_records($family, 1, 1, 1);
ac_assert('finance.general records → container_not_found (binding, no data)', ($not_found_records['read_state'] ?? '') === 'container_not_found');
ac_assert('Not found does not invent items', ($not_found_records['items_view'] ?? null) === []);

// Title-only link on containers
$containers = AA_Canonical_Shell_View_Composer::compose_preview(1);
$html_c = render_shell([
    'aa_shell_route_state' => 'preview',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $containers,
]);
ac_assert('Container title is the only card link', preg_match('/<h4[^>]*>\s*<a[^>]+href="/', $html_c) === 1
    || substr_count($html_c, ' — ver registros') >= 1);
ac_assert('Card article is not wrapped by anchor', preg_match('/<a[^>]*>\s*<article/', $html_c) !== 1);
ac_assert('records_url present on items', isset($containers['items_view'][0]['records_url'])
    && strpos((string) $containers['items_view'][0]['records_url'], 'view=records') !== false);

// Records render
$records = AA_Canonical_Shell_View_Composer::compose_preview_records(1, 1, 2);
$html_r = render_shell([
    'aa_shell_route_state' => 'preview',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $records,
]);
ac_assert('Records show parent title', strpos($html_r, 'data-aa-shell-view="records"') !== false);
ac_assert('Back link present', strpos($html_r, 'Volver a contenedores') !== false);
ac_assert('Records pagination label', strpos($html_r, 'aria-label="Paginación de registros"') !== false);
ac_assert('Records list present', strpos($html_r, 'aria-label="Registros canónicos"') !== false);
ac_assert('time datetime Z in records', strpos($html_r, 'datetime="') !== false && strpos($html_r, 'Z"') !== false);

$escape = $records;
$escape['items_view'] = [[
    'id' => 1,
    'title' => '<script>x</script>',
    'details' => '<b>y</b>',
    'updated_at_iso' => '2026-03-01T12:00:00Z',
    'updated_at_display' => '1 Mar 2026, 12:00',
], [
    'id' => 2,
    'title' => 'Sin detalle',
    'details' => null,
    'updated_at_iso' => '2026-03-01T12:00:00Z',
    'updated_at_display' => '1 Mar 2026, 12:00',
]];
$html_e = render_shell([
    'aa_shell_route_state' => 'preview',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $escape,
]);
ac_assert('Record title escaped', strpos($html_e, '<script>x</script>') === false
    && strpos($html_e, '&lt;script&gt;x&lt;/script&gt;') !== false);
ac_assert('Record details escaped', strpos($html_e, '<b>y</b>') === false);
ac_assert('Null details omitted', preg_match('/Sin detalle<\/h4>\s*<p class="mt-2/', $html_e) !== 1);
ac_assert('Canonical time attr', strpos($html_e, 'datetime="2026-03-01T12:00:00Z"') !== false);

$html_nf = render_shell([
    'aa_shell_route_state' => 'preview',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $missing,
]);
ac_assert('Not found UI', strpos($html_nf, 'Contenedor no encontrado') !== false);

$html_empty = render_shell([
    'aa_shell_route_state' => 'preview',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $empty,
]);
ac_assert('Empty records UI distinct', strpos($html_empty, 'Sin registros') !== false
    && strpos($html_empty, 'Contenedor no encontrado') === false);

$html_nf_finance = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $not_found_records,
    'aa_canonical_family' => $family,
]);
ac_assert('Finance records not found UI (not pending)', strpos($html_nf_finance, 'Contenedor no encontrado') !== false
    && strpos($html_nf_finance, 'Lectura pendiente') === false);
ac_assert('Labels from definitions', strpos($html_nf_finance, 'Finanzas') !== false);

$html_ce = render_shell([
    'aa_shell_route_state' => 'preview',
    'aa_shell_route_message' => '',
    'aa_shell_view' => [
        'shell_view' => 'records',
        'read_state' => 'contract_error',
        'family_label' => 'Demostración del shell',
        'qualified_key' => 'shell_preview',
        'is_preview' => true,
        'preview_banner' => 'Demostración del shell — datos temporales.',
        'preview_enabled' => true,
        'preview_url' => AA_Canonical_Shell_Base_Url_Policy::build_preview_url(null),
        'container_id' => 1,
        'containers_page' => 1,
        'container' => null,
        'back_url' => AA_Canonical_Shell_Base_Url_Policy::build_preview_url(null),
        'items_view' => [],
        'page' => null,
        'per_page' => null,
        'total' => null,
        'total_pages' => null,
        'has_previous' => false,
        'has_next' => false,
        'prev_url' => '',
        'next_url' => '',
    ],
]);
ac_assert('Contract error records UI neutral', strpos($html_ce, 'No se pudo cargar la lista') !== false
    && strpos($html_ce, 'invalid_page_contract') === false);

// Ciclo 3 — records fill (resolved empty/resolved_page; no preview)
$fill_empty_view = [
    'shell_view' => 'records',
    'read_state' => 'empty',
    'family_label' => 'Finanzas',
    'family_icon_key' => 'currency',
    'qualified_key' => 'finance',
    'is_preview' => false,
    'preview_banner' => '',
    'preview_enabled' => false,
    'preview_url' => '',
    'container_id' => 42,
    'containers_page' => 1,
    'container' => [
        'id' => 42,
        'title' => 'Lista fill',
        'details' => 'Detalle de la lista',
        'updated_at_iso' => '2026-03-01T12:00:00Z',
        'updated_at_display' => '1 Mar 2026, 12:00',
    ],
    'back_url' => AA_Canonical_Shell_Base_Url_Policy::build_url('finance'),
    'items_view' => [],
    'page' => 1,
    'per_page' => 15,
    'total' => 0,
    'total_pages' => 0,
    'has_previous' => false,
    'has_next' => false,
    'prev_url' => '',
    'next_url' => '',
    'lists_scope' => '',
];
$html_fill = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_empty_view,
    'aa_canonical_family' => $family,
]);
ac_assert('Fill panel present on resolved empty', strpos($html_fill, 'aa-shell-list-panel') !== false
    && strpos($html_fill, 'aa-shell-records-fill-root') !== false);
ac_assert('Fill header has Volver and Detalles', strpos($html_fill, 'Volver a contenedores') !== false
    && strpos($html_fill, 'id="aa-shell-list-details-toggle"') !== false
    && strpos($html_fill, 'aria-controls="aa-shell-list-details"') !== false
    && strpos($html_fill, 'aria-expanded="false"') !== false);
$currency_path = 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2';
ac_assert('Fill header shows family icon gray-900', (bool) preg_match(
    '/aa-shell-list-panel-header[\s\S]*?text-gray-900[\s\S]*?<svg[\s\S]*?' . preg_quote($currency_path, '/') . '[\s\S]*?id="aa-shell-parent-heading"/',
    $html_fill
));
ac_assert('Fill header icon is decorative aria-hidden', (bool) preg_match(
    '/aa-shell-list-panel-header[\s\S]*?aria-hidden="true"[\s\S]*?id="aa-shell-parent-heading"/',
    $html_fill
));
ac_assert('Fill header omits indigo icon color', !preg_match(
    '/aa-shell-list-panel-header[\s\S]*?text-indigo-600[\s\S]*?id="aa-shell-parent-heading"/',
    $html_fill
));
ac_assert('Fill heading id stays on h2', (bool) preg_match(
    '/<h2 id="aa-shell-parent-heading"[^>]*truncate/',
    $html_fill
));
$fill_long = $fill_empty_view;
$fill_long['container']['title'] = 'Título muy largo de lista financiera para comprobar truncado visual del encabezado';
$html_fill_long = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_long,
    'aa_canonical_family' => $family,
]);
ac_assert('Fill long title keeps truncate on heading', (bool) preg_match(
    '/<h2 id="aa-shell-parent-heading"[^>]*\btruncate\b/',
    $html_fill_long
) && strpos($html_fill_long, 'Título muy largo de lista financiera') !== false);
$archive_family = AA_Canonical_Core_Bootstrap::instance()->family('archive');
$fill_archive = $fill_empty_view;
$fill_archive['family_label'] = 'Archivo';
$fill_archive['family_icon_key'] = 'folder';
$fill_archive['qualified_key'] = 'archive';
$fill_archive['back_url'] = AA_Canonical_Shell_Base_Url_Policy::build_url('archive');
$html_fill_archive = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_archive,
    'aa_canonical_family' => $archive_family,
]);
$folder_path = 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z';
ac_assert('Archive fill header shows folder icon', strpos($html_fill_archive, $folder_path) !== false
    && (bool) preg_match(
        '/aa-shell-list-panel-header[\s\S]*?text-gray-900[\s\S]*?id="aa-shell-parent-heading"/',
        $html_fill_archive
    ));
preg_match('/aa-shell-list-panel-header[\s\S]*?<\/header>/', $html_fill, $fill_header_match);
$fill_header_html = $fill_header_match[0] ?? '';
ac_assert('Fill title row has options trigger', strpos($fill_header_html, 'aa-shell-container-options-trigger') !== false
    && strpos($fill_header_html, 'aa-shell-container-options-popup') !== false
    && strpos($fill_header_html, 'w-[12rem]') !== false
    && strpos($fill_header_html, 'max-w-full') === false);
$fill_popup_inner = '';
if (preg_match(
    '/class="aa-shell-container-options-popup[^"]*"[^>]*>([\s\S]*?)<\/div>\s*<\/div>\s*<\/div>/',
    $fill_header_html,
    $fill_popup_match
)) {
    $fill_popup_inner = $fill_popup_match[1];
}
ac_assert('Fill popup keeps edit/delete classes and list aria-labels', $fill_popup_inner !== ''
    && substr_count($fill_popup_inner, 'aa-shell-edit-container-btn') === 1
    && substr_count($fill_popup_inner, 'aa-shell-delete-container-btn') === 1
    && strpos($fill_popup_inner, 'aria-label="Editar lista: Lista fill"') !== false
    && strpos($fill_popup_inner, 'aria-label="Eliminar lista: Lista fill"') !== false
    && (bool) preg_match('/>\s*Editar\s*</', $fill_popup_inner)
    && (bool) preg_match('/>\s*Eliminar\s*</', $fill_popup_inner));
ac_assert('Fill omits legacy header-actions and visible Editar lista text', strpos($fill_header_html, 'aa-shell-list-header-actions') === false
    && strpos($fill_header_html, '>Editar lista<') === false
    && strpos($fill_header_html, '>Eliminar lista<') === false);
ac_assert('Fill details control outside popup', strpos($fill_header_html, 'aa-shell-list-details-control') !== false
    && (bool) preg_match(
        '/aa-shell-list-details-control[\s\S]*?id="aa-shell-list-details-toggle"/',
        $fill_header_html
    )
    && strpos($fill_popup_inner, 'aa-shell-list-details-toggle') === false);
$html_fill_no_write = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => array_merge($fill_empty_view, [
        'container_id' => 0,
        'container' => array_merge($fill_empty_view['container'], ['id' => 0]),
    ]),
]);
ac_assert('Fill without write omits options trigger', strpos($html_fill_no_write, 'aa-shell-container-options-trigger') === false
    && strpos($html_fill_no_write, 'id="aa-shell-list-details-toggle"') !== false);
$module_shell_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
ac_assert('No-fill layout also embeds container options pattern', (bool) preg_match(
    '/<h3 id="aa-shell-parent-heading"[\s\S]*?aa-shell-container-options-trigger[\s\S]*?aa-shell-container-options-popup/',
    $module_shell_src
));
ac_assert('Preview non-fill omits visible Editar lista buttons', strpos($html_r, '>Editar lista<') === false
    && strpos($html_r, '>Eliminar lista<') === false);
ac_assert('Fill details start collapsed', strpos($html_fill, 'id="aa-shell-list-details"') !== false
    && preg_match('/id="aa-shell-list-details"[^>]*\bhidden\b/', $html_fill) === 1);
ac_assert('Fill details include text and updated_at', strpos($html_fill, 'Detalle de la lista') !== false
    && strpos($html_fill, 'datetime="2026-03-01T12:00:00Z"') !== false);
ac_assert('Fill FAB Nuevo registro', strpos($html_fill, 'id="aa-shell-open-create-record-btn"') !== false
    && strpos($html_fill, 'aria-label="Nuevo registro"') !== false
    && strpos($html_fill, 'aa-shell-list-panel-body--fab') !== false
    && preg_match('/id="aa-canonical-shell-root"[^>]*pb-24/', $html_fill) !== 1);
ac_assert('Fill modal still above FAB z-index', preg_match('/id="aa-shell-record-modal"[\s\S]*?z-\[300\]/', $html_fill) === 1);
ac_assert('Preview records keep non-fill panel', strpos($html_r, 'aa-shell-list-panel') === false
    && strpos($html_r, 'aa-shell-open-create-record-btn') === false);
ac_assert('Not-found records exclude fill panel', strpos($html_nf_finance, 'aa-shell-list-panel') === false
    && strpos($html_nf_finance, 'aa-shell-records-fill-root') === false);

// Ciclo 4 — compact overlay (fill resolved_page)
$fill_page_view = $fill_empty_view;
$fill_page_view['read_state'] = 'resolved_page';
$fill_page_view['items_view'] = [
    [
        'id' => 7,
        'title' => 'Registro A',
        'details' => 'Cuerpo A',
        'updated_at_iso' => '2026-03-01T12:00:00Z',
        'updated_at_display' => '1 Mar 2026, 12:00',
    ],
    [
        'id' => 8,
        'title' => 'Registro B',
        'details' => null,
        'updated_at_iso' => '2026-03-02T12:00:00Z',
        'updated_at_display' => '2 Mar 2026, 12:00',
    ],
];
$fill_page_view['total'] = 2;
$fill_page_view['total_pages'] = 2;
$fill_page_view['has_previous'] = false;
$fill_page_view['has_next'] = true;
$fill_page_view['next_url'] = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 42, 2, 1);
$html_fill_page = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_page_view,
    'aa_canonical_family' => $family,
]);
ac_assert('Compact list one column in fill resolved_page', strpos($html_fill_page, 'id="aa-shell-records-list"') !== false
    && strpos($html_fill_page, 'aa-shell-records-list space-y-2') !== false
    && strpos($html_fill_page, 'sm:grid-cols-2') === false);
ac_assert('Compact rows and extender before pagination', strpos($html_fill_page, 'data-aa-shell-record') !== false
    && strpos($html_fill_page, 'aa-shell-record-toggle') !== false
    && strpos($html_fill_page, 'id="aa-shell-records-scroll-extender"') !== false
    && strpos($html_fill_page, 'canonical-shell-records-compact.js') !== false);
$ext_pos = strpos($html_fill_page, 'id="aa-shell-records-scroll-extender"');
$nav_pos = strpos($html_fill_page, 'aria-label="Paginación de registros"');
ac_assert('Extender precedes pagination in DOM', $ext_pos !== false && $nav_pos !== false && $ext_pos < $nav_pos);
ac_assert('Compact keeps edit/delete classes for form binding', strpos($html_fill_page, 'aa-shell-edit-record-btn') !== false
    && strpos($html_fill_page, 'aa-shell-delete-record-btn') !== false
    && strpos($html_fill_page, 'aa-shell-record-options-trigger') !== false);
ac_assert('Compact options live in header beside toggle', strpos($html_fill_page, 'aa-shell-record-header') !== false
    && preg_match(
        '/aa-shell-record-header[\s\S]*?aa-shell-record-toggle[\s\S]*?aa-shell-record-options[\s\S]*?aa-shell-record-options-menu[\s\S]*?aa-shell-record-panel/',
        $html_fill_page
    ) === 1);
ac_assert('Compact options menu width classes without min-w-[12rem]', strpos($html_fill_page, 'min-w-[12rem]') === false
    && strpos($html_fill_page, 'w-[12rem]') !== false
    && strpos($html_fill_page, 'max-w-full') !== false
    && strpos($html_fill_page, 'min-w-0') !== false
    && strpos($html_fill_page, 'box-border') !== false);
ac_assert('Preview records keep classic card article', strpos($html_r, 'data-aa-shell-record') === false
    && strpos($html_r, '<article class="bg-white rounded-xl') !== false
    && strpos($html_r, 'aa-shell-records-scroll-extender') === false);

// RVC-2B — el shell presenta navegación declarada y aplica solo la política de creación.
$simple_view_url = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 42, null, 1, null, 'simple', []);
$completed_view_url = AA_Canonical_Shell_Base_Url_Policy::build_records_url(
    'finance',
    42,
    null,
    1,
    null,
    'simple',
    ['completed' => 'completed']
);
$fill_completed_view = array_merge($fill_page_view, [
    'records_view' => 'simple',
    'capability_views' => ['completed' => 'completed'],
    'simple_record_view' => [
        'key' => 'simple',
        'label' => 'Simple',
        'active' => false,
        'url' => $simple_view_url,
    ],
    'available_record_views' => [[
        'owner' => 'completed',
        'key' => 'completed',
        'label' => 'Completadas',
        'active' => true,
        'url' => $simple_view_url,
    ]],
    'records_view_policy' => ['allows_record_creation' => false],
    'current_record_view' => ['label' => 'Completadas'],
    'default_records_url' => $simple_view_url,
]);
$html_fill_completed = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_completed_view,
    'aa_canonical_family' => $family,
]);
ac_assert('RVC-2B: alternative view renders inline Vista disclosure',
    strpos($html_fill_completed, 'aa-shell-record-views-trigger') !== false
    && strpos($html_fill_completed, 'aria-controls="aa-shell-record-views-panel"') !== false
    && strpos($html_fill_completed, 'aa-shell-record-views-panel hidden') !== false
    && strpos($html_fill_completed, '>Vista<') !== false
    && strpos($html_fill_completed, '>Simple<') !== false
    && strpos($html_fill_completed, '>Completadas<') !== false
);
ac_assert('RVC-2B: active capability view is visibly and semantically marked',
    strpos($html_fill_completed, 'data-aa-record-view-owner="completed"') !== false
    && strpos($html_fill_completed, 'data-aa-record-view-active="1"') !== false
    && strpos($html_fill_completed, '<span class="sr-only">Activa</span>') !== false
);
ac_assert('RVC-2B: creation policy hides only creation UI',
    strpos($html_fill_completed, 'id="aa-shell-open-create-record-btn"') === false
    && strpos($html_fill_completed, 'aa-shell-edit-record-btn') !== false
    && strpos($html_fill_completed, 'aa-shell-delete-record-btn') !== false
    && strpos($html_fill_completed, 'canonical-shell-record-form.js') !== false
);
ac_assert('RVC-2B: alternative view keeps list management out and exposes context label',
    strpos($html_fill_completed, 'aa-shell-edit-container-btn') === false
    && strpos($html_fill_completed, 'aa-shell-delete-container-btn') === false
    && strpos($html_fill_completed, '>Completadas</span>') !== false
    && strpos($html_fill_completed, 'Ver pendientes') === false
    && strpos($html_fill_completed, 'Ver Completadas') === false
);
ac_assert('RVC-2B: alternative-only options load their interaction controller',
    strpos($html_fill_completed, 'canonical-shell-container-options.js') !== false
);

$fill_simple_views = array_merge($fill_empty_view, [
    'records_view' => 'simple',
    'capability_views' => [],
    'simple_record_view' => [
        'key' => 'simple',
        'label' => 'Simple',
        'active' => true,
        'url' => $simple_view_url,
    ],
    'available_record_views' => [[
        'owner' => 'completed',
        'key' => 'completed',
        'label' => 'Completadas',
        'active' => false,
        'url' => $completed_view_url,
    ]],
    'records_view_policy' => ['allows_record_creation' => true],
    'current_record_view' => null,
    'default_records_url' => $simple_view_url,
]);
$html_fill_simple_views = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_simple_views,
    'aa_canonical_family' => $family,
]);
ac_assert('RVC-2B: Simple marks base and preserves list management plus creation',
    strpos($html_fill_simple_views, 'data-aa-record-view-active="1"') !== false
    && strpos($html_fill_simple_views, 'aa-shell-edit-container-btn') !== false
    && strpos($html_fill_simple_views, 'aa-shell-delete-container-btn') !== false
    && strpos($html_fill_simple_views, 'id="aa-shell-open-create-record-btn"') !== false
    && strpos($html_fill_simple_views, '>Pendientes<') === false
);

$fill_permissive_view = array_merge($fill_simple_views, [
    'capability_views' => ['test_flag' => 'flagged'],
    'simple_record_view' => array_merge($fill_simple_views['simple_record_view'], ['active' => false]),
    'available_record_views' => [[
        'owner' => 'test_flag',
        'key' => 'flagged',
        'label' => 'Marcadas',
        'active' => true,
        'url' => $simple_view_url,
    ]],
    'records_view_policy' => ['allows_record_creation' => true],
    'current_record_view' => ['label' => 'Marcadas'],
]);
$html_fill_permissive = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_permissive_view,
    'aa_canonical_family' => $family,
]);
ac_assert('RVC-2B: permissive alternative view keeps record creation available',
    strpos($html_fill_permissive, 'id="aa-shell-open-create-record-btn"') !== false
    && strpos($html_fill_permissive, 'aa-shell-edit-container-btn') === false
    && strpos($html_fill_permissive, '>Marcadas</span>') !== false
);

$layout_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/shared/canonical-layout.php');
$main_js_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/assets/js/main.js');
$css_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.source.css');
$compact_js = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/canonical-shell-records-compact.js');
ac_assert('Layout marks html+body fill explicitly', strpos($layout_src, "root.classList.add('aa-shell-records-fill')") !== false
    && strpos($layout_src, 'aa-shell-records-fill') !== false
    && strpos($layout_src, "\$aa_shell_records_fill = true") !== false);
ac_assert('main.js omits moduleH in records fill', strpos($main_js_src, "classList.contains('aa-shell-records-fill')") !== false
    && preg_match('/aa-shell-records-fill[\s\S]{0,400}?headerH \+ padT \+ padB \+ footerH/', $main_js_src) === 1);
ac_assert('CSS fill chain scopes html and panel body', strpos($css_src, 'html.aa-shell-records-fill') !== false
    && strpos($css_src, 'html.aa-standalone.aa-shell-records-fill') !== false
    && strpos($css_src, '.aa-shell-list-panel-body--fab') !== false);
ac_assert('CSS reserves options inside toggle and suppresses open focus ring', strpos($css_src, '.aa-shell-record-header:has(.aa-shell-record-options) .aa-shell-record-toggle') !== false
    && strpos($css_src, '.aa-shell-record.is-open .aa-shell-record-toggle') !== false
    && strpos($css_src, 'focus:ring-0') !== false
    && strpos($css_src, '.aa-shell-record.is-open:has(.aa-shell-record-toggle:focus) .aa-shell-record-panel') === false);
ac_assert(
    'CSS amount header swaps with is-open',
    strpos($css_src, '.aa-shell-record-amount--header') !== false
    && strpos($css_src, '.aa-shell-record:not(.is-open) .aa-shell-record-amount--header') !== false
    && strpos($css_src, '.aa-shell-record.is-open .aa-shell-record-amount--header') !== false
);
ac_assert('Compact JS measures max panel/menu and outside click scopes options or menu', strpos($compact_js, 'aa-shell-record-header') !== false
    && strpos($compact_js, 'aa-shell-record-options-menu') !== false
    && strpos($compact_js, '.aa-shell-record-options, .aa-shell-record-options-menu') !== false
    && strpos($compact_js, 'menuBottom') !== false);

$page2_containers = AA_Canonical_Shell_View_Composer::compose_preview(2);
ac_assert('Containers page 2 records_url keeps containers_page', isset($page2_containers['items_view'][0]['records_url'])
    && query_has((string) $page2_containers['items_view'][0]['records_url'], 'containers_page', '2'));

$module_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
$card_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php');
$composer_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php');
ac_assert('Compact panel markup has no options block', preg_match(
    '/class="aa-shell-record-panel"[\s\S]*?aa-shell-record-options(?:-trigger|-menu)?/',
    $card_src
) !== 1);
ac_assert('Composer sets 500 on records contract_error', substr_count($composer_src, 'status_header(500)') >= 2);
foreach (['aa-finance', 'AA_FINANCE', 'amount_total', 'finance-module', 'aa_list_finance', '$wpdb'] as $needle) {
    ac_assert('Shell excludes ' . $needle, strpos($module_src, $needle) === false
        && strpos($card_src, $needle) === false
        && strpos($composer_src, $needle) === false);
}
ac_assert('No onclick in container card', strpos(file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/container-card.php'), 'onclick') === false);
ac_assert('Templates do not concatenate query', strpos($module_src, 'http_build_query') === false
    && strpos($module_src, 'add_query_arg') === false);
ac_assert('Manifest not given view fields in composer records path uses UC', strpos($composer_src, 'ReadCanonicalShellRecordsUseCase') !== false);

// Regresión: repertorio en records con lists_scope=all vs sin él; multifamilia en agregado.
ac_assert(
    'Bootstrap prioriza create_family_key sobre available_families de all_lists',
    (bool) preg_match(
        '/\$families_for_capability_options\s*=\s*\[\];\s*if\s*\(\s*\$create_family_key\s*!==\s*\'\'\s*\)/',
        $module_src
    )
    && strpos($module_src, '} elseif ($is_all_lists_scope) {') !== false
);

/**
 * @return mixed
 */
function aa_shell_boot_prop(string $html, string $prop) {
    $needle = $prop . ':';
    $pos = strpos($html, $needle);
    if ($pos === false) {
        return null;
    }
    $i = $pos + strlen($needle);
    $len = strlen($html);
    while ($i < $len && ctype_space($html[$i])) {
        $i++;
    }
    if ($i >= $len) {
        return null;
    }
    if (substr($html, $i, 4) === 'null') {
        return null;
    }
    if ($html[$i] === '"') {
        $j = $i + 1;
        while ($j < $len && $html[$j] !== '"') {
            if ($html[$j] === '\\') {
                $j++;
            }
            $j++;
        }
        $raw = substr($html, $i, $j - $i + 1);
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
    if ($html[$i] !== '{' && $html[$i] !== '[') {
        return null;
    }
    $depth = 0;
    $in_str = false;
    $esc = false;
    for ($j = $i; $j < $len; $j++) {
        $c = $html[$j];
        if ($in_str) {
            if ($esc) {
                $esc = false;
                continue;
            }
            if ($c === '\\') {
                $esc = true;
                continue;
            }
            if ($c === '"') {
                $in_str = false;
            }
            continue;
        }
        if ($c === '"') {
            $in_str = true;
            continue;
        }
        if ($c === '{' || $c === '[') {
            $depth++;
            continue;
        }
        if ($c === '}' || $c === ']') {
            $depth--;
            if ($depth === 0) {
                $raw = substr($html, $i, $j - $i + 1);
                $decoded = json_decode($raw, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            }
        }
    }
    return null;
}

$fill_family_scope = $fill_empty_view;
$fill_family_scope['lists_scope'] = '';
$html_caps_family = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_family_scope,
    'aa_canonical_family' => $family,
]);
$fill_all_scope = $fill_empty_view;
$fill_all_scope['lists_scope'] = 'all';
$fill_all_scope['available_families'] = [];
$fill_all_scope['back_url'] = AA_Canonical_Shell_Base_Url_Policy::build_module_url(null);
$html_caps_all = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $fill_all_scope,
    'aa_canonical_family' => $family,
]);
$opts_family = aa_shell_boot_prop($html_caps_family, 'familyCapabilityOptions');
$opts_all = aa_shell_boot_prop($html_caps_all, 'familyCapabilityOptions');
$lists_scope_family = aa_shell_boot_prop($html_caps_family, 'listsScope');
$lists_scope_all = aa_shell_boot_prop($html_caps_all, 'listsScope');
$edit_caps_family = aa_shell_boot_prop($html_caps_family, 'editContainerCapabilities');
$edit_caps_all = aa_shell_boot_prop($html_caps_all, 'editContainerCapabilities');

ac_assert(
    'Records sin lists_scope boots repertorio de la familia real',
    is_array($opts_family) && array_key_exists('finance', $opts_family) && is_array($opts_family['finance'])
);
ac_assert(
    'Records con lists_scope=all boots repertorio de la familia real (no mapa vacío)',
    is_array($opts_all) && array_key_exists('finance', $opts_all) && is_array($opts_all['finance'])
);
ac_assert(
    'Repertorio records all_scope ≡ family_scope (claves/orden)',
    is_array($opts_family) && is_array($opts_all)
    && json_encode($opts_family['finance'] ?? null) === json_encode($opts_all['finance'] ?? null)
);
ac_assert('listsScope vacío en records familiar', $lists_scope_family === '');
ac_assert('listsScope=all conservado en records desde Todas', $lists_scope_all === 'all');
ac_assert(
    'editContainerCapabilities status alineado con/sin lists_scope',
    is_array($edit_caps_family) && is_array($edit_caps_all)
    && ($edit_caps_family['status'] ?? '') === ($edit_caps_all['status'] ?? '')
    && json_encode($edit_caps_family['active'] ?? null) === json_encode($edit_caps_all['active'] ?? null)
);

$html_agg = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => [
        'shell_view' => 'containers',
        'lists_scope' => 'all',
        'read_state' => 'empty',
        'family_label' => 'Todas las listas',
        'qualified_key' => '',
        'is_preview' => false,
        'preview_banner' => '',
        'preview_enabled' => false,
        'preview_url' => '',
        'available_families' => [
            ['family_key' => 'finance', 'label' => 'Finanzas'],
            ['family_key' => 'archive', 'label' => 'Archivo'],
        ],
        'items_view' => [],
        'page' => 1,
        'per_page' => 15,
        'total' => 0,
        'total_pages' => 0,
        'has_previous' => false,
        'has_next' => false,
        'prev_url' => '',
        'next_url' => '',
    ],
]);
$opts_agg = aa_shell_boot_prop($html_agg, 'familyCapabilityOptions');
$lists_agg = aa_shell_boot_prop($html_agg, 'listsScope');
$avail_agg = aa_shell_boot_prop($html_agg, 'availableFamilies');
ac_assert(
    'Listado agregado boots repertorio multifamilia para create/edit',
    is_array($opts_agg)
    && array_key_exists('finance', $opts_agg)
    && array_key_exists('archive', $opts_agg)
    && is_array($opts_agg['finance'])
    && is_array($opts_agg['archive'])
);
ac_assert('Listado agregado conserva listsScope=all', $lists_agg === 'all');
ac_assert(
    'Listado agregado expone availableFamilies multifamilia',
    is_array($avail_agg) && count($avail_agg) === 2
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
