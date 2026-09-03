<?php
/**
 * AC Test — Canonical read binding bootstrap + composer integration (SB1-4B).
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-read-binding-bootstrap-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$plugin_root = dirname(__DIR__, 3);
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
if (!function_exists('status_header')) {
    function status_header(int $code): void {
        global $status_headers;
        $status_headers[] = $code;
    }
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

/**
 * wpdb mínimo: COUNT=0 y SELECT=[] para simular Finance vacío sin MySQL.
 */
final class BindingBootstrapEmptyFinanceWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';

    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? (string) (int) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }

    /** @return string */
    public function get_var(string $query) {
        $this->last_error = '';
        if (strpos($query, 'COUNT(*)') !== false) {
            return '0';
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
        return [];
    }
}

$GLOBALS['wpdb'] = new BindingBootstrapEmptyFinanceWpdbMock();

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
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

echo "=== Canonical Read Binding Bootstrap ===\n";

$registry = new AA_Canonical_Read_Binding_Registry();
AA_Canonical_Read_Binding_Bootstrap::register_productive($registry);

$finance_identity = new CanonicalReadIdentity('finance', 'general');
$adapter = $registry->require($finance_identity);
ac_assert('finance.general binding resolves adapter', $adapter instanceof AA_Finance_Canonical_Read_Adapter);

$other_missing = false;
try {
    $registry->require(new CanonicalReadIdentity('finance', 'other'));
} catch (CanonicalReadBindingNotFound $e) {
    $other_missing = strpos($e->getMessage(), 'finance.other') !== false;
}
ac_assert('Distinct identity remains unbound', $other_missing);

$family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
$variant = AA_Canonical_Core_Bootstrap::instance()->variant('finance', 'general');
$view = AA_Canonical_Shell_View_Composer::compose_family($family, $variant, 1);
ac_assert('compose_family resolves finance (not pending)', ($view['read_state'] ?? '') === 'empty');
ac_assert('compose_family qualified finance.general', ($view['qualified_key'] ?? '') === 'finance.general');

$records_view = AA_Canonical_Shell_View_Composer::compose_family_records($family, $variant, 1, 1, 1);
ac_assert('compose_family_records not pending', ($records_view['read_state'] ?? '') === 'container_not_found');

// Preview stays independent
define('AA_CANONICAL_SHELL_PREVIEW', true);
$preview = AA_Canonical_Shell_View_Composer::compose_preview(1);
ac_assert('Preview resolves independently', ($preview['read_state'] ?? '') === 'resolved_page'
    && ($preview['qualified_key'] ?? '') === 'shell_preview.demo');

// Without bootstrap → pending
$manifest = new CanonicalShellManifest($finance_identity, $family, $variant);
$empty_registry = new AA_Canonical_Read_Binding_Registry();
$pending_uc = (new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($empty_registry)))
    ->execute($manifest, 1);
ac_assert('Registry without bootstrap → read_adapter_pending', $pending_uc->state() === CanonicalShellReadResult::STATE_READ_ADAPTER_PENDING);

$bootstrap_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php');
$composer_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php');
ac_assert('Bootstrap registers only finance.general', strpos($bootstrap_src, "new CanonicalReadIdentity('finance', 'general')") !== false);
ac_assert('Bootstrap lazy-loads finance adapter', strpos($bootstrap_src, 'finance/class-aa-finance-canonical-read-adapter.php') !== false);
foreach (['finance', 'AA_Finance', 'aa_finance_', 'amount', 'amount_total'] as $needle) {
    ac_assert('Composer shell universal excludes ' . $needle, strpos($composer_src, $needle) === false);
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
