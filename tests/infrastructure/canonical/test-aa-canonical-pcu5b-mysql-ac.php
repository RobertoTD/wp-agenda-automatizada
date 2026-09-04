<?php
/**
 * AC Test — PCU-5B MySQL: shell universal aislado de aa_finance_*.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-pcu5b-mysql-ac.php
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

$boot_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php');
ac_assert('Bootstrap productivo sin Finance adapter', strpos($boot_src, 'AA_Finance_Canonical_Read_Adapter') === false);
ac_assert('Bootstrap productivo usa Relational', strpos($boot_src, 'AA_Canonical_Relational_Read_Adapter') !== false);

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
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-store.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_pcu5b_' . substr(md5(uniqid('p5', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_pcu5b_') !== 0) {
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
AA_Canonical_Core_Bootstrap::bootstrap();

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);
    AA_Canonical_Schema::install();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);

    $f_table = AA_Canonical_Schema::families_table_name();
    $finance_legacy = $temp_prefix . 'aa_finance_containers';

    // Crear tabla legacy con filas que el shell NO debe ver.
    $wpdb->query(
        "CREATE TABLE `{$finance_legacy}` (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            title varchar(191) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB"
    );
    $wpdb->insert($finance_legacy, ['title' => 'legacy-should-not-appear'], ['%s']);
    $legacy_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$finance_legacy}`");
    ac_assert('MySQL: filas legacy sembradas', $legacy_count === 1);

    $store = new AA_Canonical_Family_Enablement_Store($wpdb);
    $store->set_enabled('finance', true);
    $store->set_enabled('archive', true);

    $read_reg = new AA_Canonical_Read_Binding_Registry();
    AA_Canonical_Read_Binding_Bootstrap::register_productive($read_reg);
    $fin = $read_reg->require(new CanonicalReadIdentity('finance', 'general'));
    $arch = $read_reg->require(new CanonicalReadIdentity('archive', 'general'));
    ac_assert('MySQL: finance Relational Read', $fin instanceof AA_Canonical_Relational_Read_Adapter);
    ac_assert('MySQL: archive Relational Read', $arch instanceof AA_Canonical_Relational_Read_Adapter);

    $write_reg = new AA_Canonical_Write_Binding_Registry();
    AA_Canonical_Write_Binding_Bootstrap::register_productive($write_reg);
    $w = $write_reg->require(new CanonicalReadIdentity('finance', 'general'));
    ac_assert('MySQL: write Relational', $w instanceof AA_Canonical_Relational_Write_Adapter);

    $family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
    $variant = AA_Canonical_Core_Bootstrap::instance()->variant('finance', 'general');
    $view = AA_Canonical_Shell_View_Composer::compose_family($family, $variant, 1);
    ac_assert('MySQL: shell finance empty pese a legacy', ($view['read_state'] ?? '') === 'empty');

    $c_table = AA_Canonical_Schema::containers_table_name();
    $canon_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c_table}`");
    ac_assert('MySQL: cero containers universales', $canon_count === 0);
    ac_assert('MySQL: legacy intacto', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$finance_legacy}`") === 1);

    $store->set_enabled('finance', false);
    $read_off = new AA_Canonical_Read_Binding_Registry();
    AA_Canonical_Read_Binding_Bootstrap::register_productive($read_off);
    $off = false;
    try {
        $read_off->require(new CanonicalReadIdentity('finance', 'general'));
    } catch (CanonicalReadBindingNotFound $e) {
        $off = true;
    }
    ac_assert('MySQL: disable → sin read binding', $off);
    ac_assert('MySQL: archive sigue enabled', $read_off->require(new CanonicalReadIdentity('archive', 'general')) instanceof AA_Canonical_Relational_Read_Adapter);

} finally {
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
