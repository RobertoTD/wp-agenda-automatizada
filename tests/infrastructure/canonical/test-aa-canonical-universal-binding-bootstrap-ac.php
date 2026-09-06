<?php
/**
 * AC Test — Canonical read/write binding bootstraps universales (PCU-5B).
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-universal-binding-bootstrap-ac.php
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
 * wpdb mock: enablement + tablas canónicas vacías. Sin aa_finance_*.
 */
final class UniversalBindingWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    /** @var array<string,int> */
    public $enabled_map = ['finance' => 1, 'archive' => 1];
    /** @var array<string,int> */
    public $family_ids = ['finance' => 1, 'archive' => 2];
    public $table_exists = true;
    /** @var list<string> */
    public $queries = [];

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

    public function get_var(string $query) {
        $this->queries[] = $query;
        $this->last_error = '';
        if (stripos($query, 'SHOW TABLES LIKE') !== false) {
            return $this->table_exists ? $this->prefix . 'aa_canonical_families' : null;
        }
        if (stripos($query, 'COUNT(*)') !== false) {
            return '0';
        }
        if (preg_match("/WHERE family_key = '([^']+)'/", $query, $m)) {
            $key = $m[1];
            if (isset($this->family_ids[$key]) && isset($this->enabled_map[$key])) {
                return (string) $this->family_ids[$key];
            }
            return null;
        }
        return null;
    }

    public function get_row(string $query, $output = OBJECT) {
        $this->queries[] = $query;
        $this->last_error = '';
        return null;
    }

    public function get_results(string $query, $output = OBJECT) {
        $this->queries[] = $query;
        $this->last_error = '';
        if (stripos($query, 'is_enabled') !== false && stripos($query, 'family_key IN') !== false) {
            $rows = [];
            foreach ($this->enabled_map as $key => $enabled) {
                if ($enabled < 0) {
                    continue; // sentinel: not provisioned
                }
                $rows[] = [
                    'family_key' => $key,
                    'is_enabled' => (int) $enabled,
                ];
            }
            return $rows;
        }
        return [];
    }
}

$mock = new UniversalBindingWpdbMock();
$GLOBALS['wpdb'] = $mock;

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyUnknown.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPort.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-store.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php';
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

function adapter_repository(object $adapter) {
    $ref = new ReflectionClass($adapter);
    $prop = $ref->getProperty('repository');
    $prop->setAccessible(true);
    return $prop->getValue($adapter);
}

echo "=== Contención estática ===\n";
$read_boot_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php');
$write_boot_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php');
$composer_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php');

ac_assert('Read bootstrap sin Finance adapter', strpos($read_boot_src, 'AA_Finance_Canonical_Read_Adapter') === false);
ac_assert('Read bootstrap sin aa_finance_', strpos($read_boot_src, 'aa_finance_') === false);
ac_assert('Read bootstrap sin aa_expediente_/Expediente', stripos($read_boot_src, 'aa_expediente_') === false
    && stripos($read_boot_src, 'Expediente') === false);
ac_assert('Read bootstrap carga Relational Read', strpos($read_boot_src, 'AA_Canonical_Relational_Read_Adapter') !== false);
ac_assert('Write bootstrap existe', is_readable($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php'));
ac_assert('Write bootstrap sin Finance', strpos($write_boot_src, 'AA_Finance') === false);
ac_assert('Composer no invoca write bootstrap', strpos($composer_src, 'Write_Binding_Bootstrap') === false);
ac_assert('Composer sigue llamando read bootstrap', strpos($composer_src, 'AA_Canonical_Read_Binding_Bootstrap::register_productive') !== false);

echo "=== Read bootstrap enabled ===\n";
$mock->queries = [];
$mock->enabled_map = ['finance' => 1, 'archive' => 1];
$read_reg = new AA_Canonical_Read_Binding_Registry();
AA_Canonical_Read_Binding_Bootstrap::register_productive($read_reg);

$finance_id = new CanonicalReadIdentity('finance');
$archive_id = new CanonicalReadIdentity('archive');
$fin_adapter = $read_reg->require($finance_id);
$arch_adapter = $read_reg->require($archive_id);
ac_assert('Finance → Relational Read', $fin_adapter instanceof AA_Canonical_Relational_Read_Adapter);
ac_assert('Archive → Relational Read', $arch_adapter instanceof AA_Canonical_Relational_Read_Adapter);
ac_assert('Read adapters distintos por identidad', $fin_adapter !== $arch_adapter);
ac_assert(
    'Read adapters comparten repository',
    adapter_repository($fin_adapter) === adapter_repository($arch_adapter)
);

$other_missing = false;
try {
    $read_reg->require(new CanonicalReadIdentity('taxes'));
} catch (CanonicalReadBindingNotFound $e) {
    $other_missing = true;
} catch (InvalidArgumentException $e) {
    $other_missing = true; // invalid key also acceptable for undeclared family
}
ac_assert('Familia no declarada sin binding', $other_missing);

$joined_sql = implode("\n", $mock->queries);
ac_assert('Read path sin aa_finance_', stripos($joined_sql, 'aa_finance_') === false);
ac_assert('Read path sin aa_expediente_', stripos($joined_sql, 'aa_expediente_') === false);
ac_assert('Read path toca aa_canonical_', stripos($joined_sql, 'aa_canonical_') !== false);

echo "=== Write bootstrap enabled ===\n";
$write_reg = new AA_Canonical_Write_Binding_Registry();
AA_Canonical_Write_Binding_Bootstrap::register_productive($write_reg);
$w_fin = $write_reg->require($finance_id);
$w_arch = $write_reg->require($archive_id);
ac_assert('Write Finance → Relational Write', $w_fin instanceof AA_Canonical_Relational_Write_Adapter);
ac_assert('Write Archive → mismo adapter compartido', $w_fin === $w_arch);

echo "=== Disabled → sin bindings ===\n";
$mock->enabled_map = ['finance' => 0, 'archive' => 0];
$read_off = new AA_Canonical_Read_Binding_Registry();
AA_Canonical_Read_Binding_Bootstrap::register_productive($read_off);
$write_off = new AA_Canonical_Write_Binding_Registry();
AA_Canonical_Write_Binding_Bootstrap::register_productive($write_off);

$fin_off = false;
try {
    $read_off->require($finance_id);
} catch (CanonicalReadBindingNotFound $e) {
    $fin_off = true;
}
$arch_off = false;
try {
    $read_off->require($archive_id);
} catch (CanonicalReadBindingNotFound $e) {
    $arch_off = true;
}
$w_off = false;
try {
    $write_off->require($finance_id);
} catch (CanonicalWriteBindingNotFound $e) {
    $w_off = true;
}
ac_assert('Disabled finance sin read', $fin_off);
ac_assert('Disabled archive sin read', $arch_off);
ac_assert('Disabled sin write', $w_off);

echo "=== Solo finance enabled ===\n";
$mock->enabled_map = ['finance' => 1, 'archive' => 0];
$read_one = new AA_Canonical_Read_Binding_Registry();
AA_Canonical_Read_Binding_Bootstrap::register_productive($read_one);
ac_assert('Solo finance bound', $read_one->require($finance_id) instanceof AA_Canonical_Relational_Read_Adapter);
$arch_unbound = false;
try {
    $read_one->require($archive_id);
} catch (CanonicalReadBindingNotFound $e) {
    $arch_unbound = true;
}
ac_assert('Archive disabled unbound', $arch_unbound);

echo "=== Schema ausente ===\n";
$mock->table_exists = false;
$schema_threw = false;
try {
    AA_Canonical_Read_Binding_Bootstrap::register_productive(new AA_Canonical_Read_Binding_Registry());
} catch (CanonicalFamilyEnablementSchemaNotReady $e) {
    $schema_threw = true;
}
ac_assert('Schema ausente → schema_not_ready', $schema_threw);
$mock->table_exists = true;

echo "=== Compose + preview ===\n";
$mock->enabled_map = ['finance' => 1, 'archive' => 1];
$mock->queries = [];
$family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
$variant = null; // shell no longer exposes variants
$view = AA_Canonical_Shell_View_Composer::compose_family($family, 1);
ac_assert('compose_family empty universal', ($view['read_state'] ?? '') === 'empty');
$view_sql = implode("\n", $mock->queries);
ac_assert('compose SQL sin aa_finance_', stripos($view_sql, 'aa_finance_') === false);
ac_assert('compose SQL sin aa_expediente_', stripos($view_sql, 'aa_expediente_') === false);

define('AA_CANONICAL_SHELL_PREVIEW', true);
$preview = AA_Canonical_Shell_View_Composer::compose_preview(1);
ac_assert('Preview aislado', ($preview['read_state'] ?? '') === 'resolved_page'
    && ($preview['qualified_key'] ?? '') === 'shell_preview');

echo "=== Toggle siguiente petición ===\n";
$mock->enabled_map = ['finance' => 0, 'archive' => 0];
$r1 = new AA_Canonical_Read_Binding_Registry();
AA_Canonical_Read_Binding_Bootstrap::register_productive($r1);
$unbound1 = false;
try {
    $r1->require($finance_id);
} catch (CanonicalReadBindingNotFound $e) {
    $unbound1 = true;
}
$mock->enabled_map = ['finance' => 1, 'archive' => 0];
$r2 = new AA_Canonical_Read_Binding_Registry();
AA_Canonical_Read_Binding_Bootstrap::register_productive($r2);
ac_assert('Tras disable sin binding', $unbound1);
ac_assert('Tras enable vuelve Relational', $r2->require($finance_id) instanceof AA_Canonical_Relational_Read_Adapter);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
