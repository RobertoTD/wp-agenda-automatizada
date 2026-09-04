<?php
/**
 * AC Test — AA_Canonical_Family_Enablement_Store (PCU-5A) MySQL real.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-family-enablement-store-mysql-ac.php
 */

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

$store_file = $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-store.php';
$store_src = (string) file_get_contents($store_file);
ac_assert('Store existe', is_readable($store_file));
ac_assert('No usa CanonicalRelationalRepository', strpos($store_src, 'CanonicalRelationalRepository') === false);
ac_assert('Usa gmdate UTC', strpos($store_src, "gmdate('Y-m-d H:i:s')") !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyUnknown.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPort.php';
require_once $store_file;

global $wpdb;

$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_enab_' . substr(md5(uniqid('en', true)), 0, 8) . '_';
$temp_prefix_2 = 'tmp_enab2_' . substr(md5(uniqid('e2', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_enab') !== 0) {
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

$registry = AA_Canonical_Core_Bootstrap::build_registry();

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);

    echo "=== Tabla ausente ===\n";
    $store = new AA_Canonical_Family_Enablement_Store($wpdb);
    $missing = false;
    try {
        $store->read_for_declared_families(['finance', 'archive']);
    } catch (CanonicalFamilyEnablementSchemaNotReady $e) {
        $missing = true;
    }
    ac_assert('Tabla ausente → schema_not_ready', $missing);

    AA_Canonical_Schema::install();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);

    $f_table = AA_Canonical_Schema::families_table_name();
    $c_table = AA_Canonical_Schema::containers_table_name();
    $r_table = AA_Canonical_Schema::records_table_name();

    echo "=== Lectura ===\n";
    $snap = $store->read_for_declared_families(['finance', 'archive']);
    ac_assert('Ambas provisionadas', $snap->is_provisioned('finance') && $snap->is_provisioned('archive'));
    ac_assert('Ambas disabled', !$snap->is_enabled('finance') && !$snap->is_enabled('archive'));

    $wpdb->insert($f_table, [
        'family_key' => 'orphan_only_db',
        'is_enabled' => 1,
        'seed_version' => 0,
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ], ['%s', '%d', '%d', '%s', '%s']);
    $snap2 = $store->read_for_declared_families(['finance', 'archive']);
    ac_assert('Huérfana BD ignorada', !$snap2->has('orphan_only_db'));

    $wpdb->query($wpdb->prepare("DELETE FROM `{$f_table}` WHERE family_key = %s", 'archive'));
    $snap3 = $store->read_for_declared_families(['finance', 'archive']);
    ac_assert('Fila ausente archive', !$snap3->is_provisioned('archive') && $snap3->is_provisioned('finance'));
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);

    echo "=== Escritura ===\n";
    $before = $wpdb->get_row($wpdb->prepare("SELECT is_enabled, seed_version, created_at, updated_at FROM `{$f_table}` WHERE family_key=%s", 'finance'), ARRAY_A);
    usleep(1100000);
    $res = $store->set_enabled('finance', true);
    ac_assert('Enable changed', $res->changed() === true && $res->is_enabled() === true);
    $after = $wpdb->get_row($wpdb->prepare("SELECT is_enabled, seed_version, created_at, updated_at FROM `{$f_table}` WHERE family_key=%s", 'finance'), ARRAY_A);
    ac_assert('is_enabled=1', (int) $after['is_enabled'] === 1);
    ac_assert('updated_at cambió', $after['updated_at'] !== $before['updated_at']);
    ac_assert('seed_version intacto', (int) $after['seed_version'] === (int) $before['seed_version']);
    ac_assert('created_at intacto', $after['created_at'] === $before['created_at']);

    $ts_before_noop = $after['updated_at'];
    $noop = $store->set_enabled('finance', true);
    ac_assert('No-op changed=false', $noop->changed() === false);
    $after_noop = $wpdb->get_row($wpdb->prepare("SELECT updated_at FROM `{$f_table}` WHERE family_key=%s", 'finance'), ARRAY_A);
    ac_assert('No-op preserva timestamp', $after_noop['updated_at'] === $ts_before_noop);

    $c_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c_table}`");
    $r_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$r_table}`");
    $store->set_enabled('finance', false);
    $store->set_enabled('archive', true);
    $c_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c_table}`");
    $r_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$r_table}`");
    ac_assert('Containers intactos', $c_before === $c_after);
    ac_assert('Records intactos', $r_before === $r_after);

    $missing_row = false;
    try {
        $store->set_enabled('billing', true);
    } catch (CanonicalFamilyNotProvisioned $e) {
        $missing_row = true;
    }
    ac_assert('Fila ausente en set → not provisioned', $missing_row);

    echo "=== Aislamiento prefijos ===\n";
    $wpdb->prefix = $temp_prefix_2;
    $cleanup($temp_prefix_2);
    AA_Canonical_Schema::install();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);
    $store2 = new AA_Canonical_Family_Enablement_Store($wpdb);
    $store2->set_enabled('finance', true);
    $snap_p2 = $store2->read_for_declared_families(['finance']);
    ac_assert('Prefijo 2 finance enabled', $snap_p2->is_enabled('finance'));

    $wpdb->prefix = $temp_prefix;
    $store1 = new AA_Canonical_Family_Enablement_Store($wpdb);
    $snap_p1 = $store1->read_for_declared_families(['finance']);
    ac_assert('Prefijo 1 finance sigue disabled', !$snap_p1->is_enabled('finance'));

} finally {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);
    $wpdb->prefix = $temp_prefix_2;
    $cleanup($temp_prefix_2);
    $wpdb->prefix = $original_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
