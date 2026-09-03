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

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

final class ShellRecordsEmptyFinanceWpdbMock {
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

$GLOBALS['wpdb'] = new ShellRecordsEmptyFinanceWpdbMock();

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
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
        if ($family_present || $variant_present) {
            $aa_shell_route_state = 'invalid_request';
            $aa_shell_route_message = 'El modo preview no admite family ni variant.';
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
    } elseif (!$family_present && !$variant_present) {
        $aa_shell_route_state = 'missing_identity';
    } elseif ($family_present xor $variant_present) {
        $aa_shell_route_state = 'incomplete_identity';
        status_header(400);
    } else {
        $route_result = (new ResolveCanonicalRouteUseCase(AA_Canonical_Core_Bootstrap::instance()))->execute([
            'family_key'  => $family_input,
            'variant_key' => $variant_input,
        ]);
        if (!$route_result['success']) {
            $aa_shell_route_state = 'not_found';
            status_header(404);
        } elseif ($is_records_view) {
            $family = $route_result['data']['family'];
            $variant = $route_result['data']['variant'];
            $aa_shell_route_state = 'resolved';
            $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_family_records(
                $family,
                $variant,
                $shell_container_id,
                $shell_page,
                $shell_containers_page
            );
        } else {
            $aa_shell_route_state = 'resolved';
            $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_family(
                $route_result['data']['family'],
                $route_result['data']['variant'],
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
$family_records = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 'general', 7, 1, 1);
ac_assert('Family records URL omits page=1 and containers_page=1', query_has($family_records, 'view', 'records')
    && query_has($family_records, 'container_id', '7')
    && query_has($family_records, 'family', 'finance')
    && query_missing($family_records, 'page')
    && query_missing($family_records, 'containers_page'));

$family_records_p = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 'general', 7, 2, 3);
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
$variant = AA_Canonical_Core_Bootstrap::instance()->variant('finance', 'general');
$not_found_records = AA_Canonical_Shell_View_Composer::compose_family_records($family, $variant, 1, 1, 1);
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
    'aa_canonical_variant' => $variant,
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
        'variant_label' => 'Vista de prueba',
        'qualified_key' => 'shell_preview.demo',
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

$page2_containers = AA_Canonical_Shell_View_Composer::compose_preview(2);
ac_assert('Containers page 2 records_url keeps containers_page', isset($page2_containers['items_view'][0]['records_url'])
    && query_has((string) $page2_containers['items_view'][0]['records_url'], 'containers_page', '2'));

$module_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
$card_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php');
$composer_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php');
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

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
