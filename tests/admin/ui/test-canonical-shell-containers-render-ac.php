<?php
/**
 * AC Test — Canonical shell containers render + isolation (SB1-2B).
 *
 * Ejecutar: php tests/admin/ui/test-canonical-shell-containers-render-ac.php
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
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1): string {
        return 'test-nonce';
    }
}
if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = ''): string {
        return 'https://example.com/wp-content/plugins/wp-agenda-automatizada/' . ltrim((string) $path, '/');
    }
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

final class ShellUiEmptyFinanceWpdbMock {
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

$GLOBALS['wpdb'] = new ShellUiEmptyFinanceWpdbMock();

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPort.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-store.php';
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

// Empty finance with productive binding (no preview CTA without constant)
$family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
$empty_view = AA_Canonical_Shell_View_Composer::compose_family($family, 1);
$html_empty = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => 'Ruta canónica resuelta.',
    'aa_shell_view' => $empty_view,
    'aa_canonical_family' => $family,
]);
ac_assert('Empty finance shows Sin contenedores', strpos($html_empty, 'Sin contenedores') !== false);
ac_assert('Empty finance not pending', strpos($html_empty, 'Lectura pendiente') === false);
ac_assert('Empty finance shows Finanzas label', strpos($html_empty, 'Finanzas') !== false);
ac_assert('Empty without CTA when preview off', strpos($html_empty, 'Ver demostración del shell') === false);

// Preview unavailable transport state
$html_404 = render_shell([
    'aa_shell_route_state' => 'preview_unavailable',
    'aa_shell_route_message' => 'La demostración del shell no está disponible.',
    'aa_shell_view' => null,
]);
ac_assert('Preview unavailable controlled UI', strpos($html_404, 'No disponible') !== false
    || strpos($html_404, 'no está disponible') !== false);

// Contract error UI (no technical leak)
$html_err = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => [
        'read_state' => 'contract_error',
        'family_label' => 'Finanzas',
        'qualified_key' => 'finance',
        'is_preview' => false,
        'preview_banner' => null,
        'preview_enabled' => false,
        'preview_url' => null,
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
ac_assert('Contract error neutral message', strpos($html_err, 'No se pudo cargar la lista') !== false);
ac_assert('Contract error hides exception tags', strpos($html_err, 'invalid_page_contract') === false
    && strpos($html_err, 'InvalidArgument') === false
    && strpos($html_err, 'Trace') === false);

// Escape + details null + time element with preview on
define('AA_CANONICAL_SHELL_PREVIEW', true);
$preview_view = AA_Canonical_Shell_View_Composer::compose_preview(1);
// Inject XSS payload into a copy for escape check
$escape_view = $preview_view;
$escape_view['items_view'] = [[
    'id' => 99,
    'title' => '<script>alert(1)</script>',
    'details' => '<b>x</b>',
    'updated_at_iso' => '2026-03-01T15:00:00Z',
    'updated_at_display' => '1 Mar 2026, 15:00',
], [
    'id' => 98,
    'title' => 'Sin detalle',
    'details' => null,
    'updated_at_iso' => '2026-03-01T15:00:00Z',
    'updated_at_display' => '1 Mar 2026, 15:00',
]];
$escape_view['has_previous'] = false;
$escape_view['has_next'] = true;
$escape_view['next_url'] = AA_Canonical_Shell_Base_Url_Policy::build_preview_url(2);
$escape_view['page'] = 1;
$escape_view['total_pages'] = 2;

$html_prev = render_shell([
    'aa_shell_route_state' => 'preview',
    'aa_shell_route_message' => 'Demostración del shell.',
    'aa_shell_view' => $escape_view,
]);
ac_assert('Preview banner rendered', strpos($html_prev, 'Demostración del shell') !== false
    && strpos($html_prev, 'datos temporales') !== false);
ac_assert('Title escaped', strpos($html_prev, '<script>alert(1)</script>') === false
    && strpos($html_prev, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false);
ac_assert('Container card omits details paragraph', strpos($html_prev, 'whitespace-pre-wrap') === false
    && strpos($html_prev, '<b>x</b>') === false);
ac_assert('Container card omits time element', strpos($html_prev, '<time') === false);
ac_assert('Semantic list and article', strpos($html_prev, '<ul') !== false && strpos($html_prev, '<article') !== false);
ac_assert('Pagination nav label', strpos($html_prev, 'aria-label="Paginación de contenedores"') !== false);
ac_assert('Next link present', strpos($html_prev, '>Siguiente</a>') !== false);
ac_assert('Cards are not anchor wrappers', preg_match('/<a[^>]*>\s*<article/', $html_prev) !== 1);

$module_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
$card_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/container-card.php');
foreach (['aa-finance', 'AA_FINANCE', 'amount_total', 'finance-module', 'aa_list_finance', '$wpdb'] as $needle) {
    ac_assert('Shell template excludes ' . $needle, strpos($module_src, $needle) === false && strpos($card_src, $needle) === false);
}
ac_assert('Shell template does not require preview adapter', strpos($module_src, 'Preview_Adapter') === false);
ac_assert('Shell template does not call composer', strpos($module_src, 'View_Composer') === false);

// Empty finance with preview enabled still exposes CTA in view data (empty UI has no CTA block)
$empty_on = AA_Canonical_Shell_View_Composer::compose_family($family, 1);
$html_cta = render_shell([
    'aa_shell_route_state' => 'resolved',
    'aa_shell_route_message' => '',
    'aa_shell_view' => $empty_on,
    'aa_canonical_family' => $family,
]);
ac_assert('Empty finance view exposes preview URL when enabled', !empty($empty_on['preview_enabled'])
    && is_string($empty_on['preview_url'])
    && strpos($empty_on['preview_url'], 'shell_mode=preview') !== false);
ac_assert('Empty finance UI is not pending', strpos($html_cta, 'Sin contenedores') !== false
    && strpos($html_cta, 'Lectura pendiente') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
