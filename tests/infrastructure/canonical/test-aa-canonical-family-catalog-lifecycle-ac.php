<?php
/**
 * AC Test — AA_Canonical_Family_Catalog_Lifecycle (PCU-4).
 *
 * Ejecutar:
 *   php tests/infrastructure/canonical/test-aa-canonical-family-catalog-lifecycle-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-family-catalog-lifecycle-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$lifecycle_file = $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-catalog-lifecycle.php';
$main_file = $plugin_root . '/wp-agenda-automatizada.php';
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$bootstrap_file = $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
$binding_boot = $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';

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

echo "=== 1. Estático / contención ===\n";

$lc_src = (string) file_get_contents($lifecycle_file);
$main_src = (string) file_get_contents($main_file);
$schema_src = (string) file_get_contents($schema_file);
$bind_src = (string) file_get_contents($binding_boot);

ac_assert('CATALOG_VERSION = 1', strpos($lc_src, 'CATALOG_VERSION = 1') !== false);
ac_assert('OPTION_VERSION correcta', strpos($lc_src, "OPTION_VERSION = 'aa_canonical_family_catalog_version'") !== false);
ac_assert('Prioridad admin_init 25', strpos($lc_src, 'ADMIN_INIT_PRIORITY = 25') !== false);
ac_assert('MIN_DB_VERSION 21', strpos($lc_src, "MIN_DB_VERSION = '21'") !== false);
ac_assert('Plugin registra lifecycle', strpos($main_src, 'AA_Canonical_Family_Catalog_Lifecycle::register') !== false);
ac_assert('Plugin require lifecycle', strpos($main_src, 'class-aa-canonical-family-catalog-lifecycle.php') !== false);
ac_assert('Schema no invoca provisioner', strpos($schema_src, 'Family_Provisioner') === false
    && strpos($schema_src, 'Family_Catalog_Lifecycle') === false);
ac_assert('DB_VERSION permanece 21', strpos($schema_src, "DB_VERSION = '21'") !== false);
ac_assert('Binding productivo es Relational (PCU-5B)', strpos($bind_src, 'AA_Canonical_Relational_Read_Adapter') !== false
    && strpos($bind_src, 'AA_Finance_Canonical_Read_Adapter') === false);
ac_assert('Lifecycle no carga adapters PCU-3', strpos($lc_src, 'Relational_Read_Adapter') === false
    && strpos($lc_src, 'Relational_Write_Adapter') === false);
ac_assert('Lifecycle no toca aa_db_version', strpos($lc_src, 'aa_db_version') !== false
    && strpos($lc_src, "update_option('aa_db_version'") === false);

// Prioridad relativa: Schema default 10, catalog 25
ac_assert(
    'Hook corre después de schema migrate',
    strpos($schema_src, "add_action('admin_init', [__CLASS__, 'maybe_migrate'])") !== false
    && strpos($lc_src, 'ADMIN_INIT_PRIORITY = 25') !== false
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load === '' || !is_readable($wp_load)) {
    // Unitario sin WP: mock mínimo de options vía override
    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_root . '/');
    }
    require_once $lifecycle_file;

    $calls = 0;
    AA_Canonical_Family_Catalog_Lifecycle::set_provision_override_for_tests(static function () use (&$calls): void {
        $calls++;
    });

    // Sin WP get_option: skip path hard — only static asserts already done
    echo "[INFO / SKIP] Integración lifecycle MySQL/options no ejecutada (AA_WP_ROOT ausente).\n";
    AA_Canonical_Family_Catalog_Lifecycle::set_provision_override_for_tests(null);
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $bootstrap_file;
require_once $lifecycle_file;
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';

global $wpdb;

echo "=== 2. Lifecycle con options temporales ===\n";

$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_cflc_' . substr(md5(uniqid('lc', true)), 0, 8) . '_';
$option_key = AA_Canonical_Family_Catalog_Lifecycle::OPTION_VERSION;
$err_key = AA_Canonical_Family_Catalog_Lifecycle::OPTION_LAST_ERROR;
$prior_option = get_option($option_key, null);
$prior_err = get_option($err_key, null);
$prior_db = get_option('aa_db_version', null);

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_cflc_') !== 0) {
        return;
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $like = $wpdb->esc_like($p) . '%';
    $rows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if (is_array($rows)) {
        foreach ($rows as $t) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
};

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);
    AA_Canonical_Schema::install();

    delete_option($option_key);
    delete_option($err_key);
    update_option('aa_db_version', '20');

    $calls = 0;
    AA_Canonical_Family_Catalog_Lifecycle::set_provision_override_for_tests(static function () use (&$calls): void {
        $calls++;
    });
    AA_Canonical_Family_Catalog_Lifecycle::maybe_provision();
    $opt_after_skip = get_option($option_key, null);
    ac_assert(
        'DB < 21 → skip sin provisionar',
        $calls === 0 && ($opt_after_skip === null || $opt_after_skip === false)
    );

    update_option('aa_db_version', '21');
    update_option($option_key, '1');
    $calls = 0;
    AA_Canonical_Family_Catalog_Lifecycle::maybe_provision();
    ac_assert('Catálogo versión 1 → skip', $calls === 0);

    delete_option($option_key);
    $calls = 0;
    AA_Canonical_Family_Catalog_Lifecycle::maybe_provision();
    ac_assert('Versión ausente → provisiona', $calls === 1);
    ac_assert('Éxito → option = 1', (string) get_option($option_key, '0') === '1');

    $calls = 0;
    AA_Canonical_Family_Catalog_Lifecycle::maybe_provision();
    ac_assert('Segundo request con v1 no re-provisiona', $calls === 0);

    // Fallo no avanza option
    delete_option($option_key);
    AA_Canonical_Family_Catalog_Lifecycle::set_provision_override_for_tests(static function (): void {
        throw new CanonicalFamilyProvisioningFailed('forced lifecycle fail');
    });
    AA_Canonical_Family_Catalog_Lifecycle::maybe_provision();
    $opt_after_fail = get_option($option_key, null);
    ac_assert(
        'Fallo → option no avanza',
        $opt_after_fail === null || $opt_after_fail === false
    );
    ac_assert('Fallo → last_error registrado', is_string(get_option($err_key, null)));

    // Nueva versión permite reejecutar: simular CATALOG_VERSION check via stored 0 vs override success
    AA_Canonical_Family_Catalog_Lifecycle::set_provision_override_for_tests(static function () use (&$calls): void {
        $calls++;
    });
    $calls = 0;
    // stored still absent
    AA_Canonical_Family_Catalog_Lifecycle::maybe_provision();
    ac_assert('Tras fallo, reintento exitoso marca v1', $calls === 1 && (string) get_option($option_key, '0') === '1');

    // Provisioner real: filas creadas, cero containers
    delete_option($option_key);
    AA_Canonical_Family_Catalog_Lifecycle::set_provision_override_for_tests(null);
    AA_Canonical_Family_Catalog_Lifecycle::maybe_provision();
    $f = AA_Canonical_Schema::families_table_name();
    $c = AA_Canonical_Schema::containers_table_name();
    $r = AA_Canonical_Schema::records_table_name();
    ac_assert('Lifecycle real crea 2 familias', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f}`") === 2);
    ac_assert('Lifecycle real cero containers', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c}`") === 0);
    ac_assert('Lifecycle real cero records', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$r}`") === 0);
    ac_assert('aa_db_version intacto en 21', (string) get_option('aa_db_version') === '21');

} finally {
    AA_Canonical_Family_Catalog_Lifecycle::set_provision_override_for_tests(null);
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
    if ($prior_option === null) {
        delete_option($option_key);
    } else {
        update_option($option_key, $prior_option);
    }
    if ($prior_err === null) {
        delete_option($err_key);
    } else {
        update_option($err_key, $prior_err);
    }
    if ($prior_db === null) {
        delete_option('aa_db_version');
    } else {
        update_option('aa_db_version', $prior_db);
    }
    echo "Limpieza options/tablas temporales.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
