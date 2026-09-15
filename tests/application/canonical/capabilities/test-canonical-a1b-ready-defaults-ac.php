<?php
/**
 * AC A1b bloque 3 — ready, defaults y materialización (guarda insert-if-missing).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-a1b-ready-defaults-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

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

echo "=== 1. Producto ready / version ===\n";
$boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
$life = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php'
);
ac_assert('amount is_ready=true', preg_match("/SCOPE_RECORD\s*,\s*true\s*\)/", $boot) === 1);
ac_assert(
    'images is_ready=true',
    preg_match("/new AA_Canonical_Capability_Definition\(\s*'images'\s*,\s*AA_Canonical_Capability_Definition::SCOPE_RECORD\s*,\s*true\s*\)/s", $boot) === 1
);
ac_assert('DEFAULTS_VERSION=3', strpos($life, 'DEFAULTS_VERSION = 3') !== false);
ac_assert('declared_seeds incluye images', strpos($life, "'capability_key' => 'images'") !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-catalog-lifecycle.php';
require_once $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-capability-write-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_a1b3_' . substr(md5((string) microtime(true)), 0, 8) . '_';
$prior_db_version = (string) get_option('aa_db_version', '0');
$prior_defaults_version = (string) get_option(AA_Canonical_Capability_Defaults_Lifecycle::OPTION_VERSION, '0');

$cleanup = static function () use ($wpdb, $temp_prefix): void {
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $like = $wpdb->esc_like($temp_prefix) . '%';
    $rows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if (is_array($rows)) {
        foreach ($rows as $t) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
};

echo "\n=== 2. Defaults seed + materialización ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();
    update_option('aa_db_version', '23');
    update_option(AA_Canonical_Family_Catalog_Lifecycle::OPTION_VERSION, (string) AA_Canonical_Family_Catalog_Lifecycle::CATALOG_VERSION);
    update_option(AA_Canonical_Capability_Defaults_Lifecycle::OPTION_VERSION, '0');

    AA_Canonical_Core_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);
    AA_Canonical_Capability_Registry_Bootstrap::reset_for_tests();
    AA_Canonical_Capability_Registry_Bootstrap::bootstrap();

    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $finance_id = (int) $config->resolve_family_id('finance');

    // Simular ensure (sin admin_init).
    foreach (AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds() as $seed) {
        $def = AA_Canonical_Capability_Registry_Bootstrap::instance()->get($seed['capability_key']);
        if (!$def->is_ready()) {
            continue;
        }
        $fid = $config->resolve_family_id($seed['family_key']);
        if ($fid === null) {
            continue;
        }
        $config->insert_family_capability_if_missing($fid, $seed['capability_key'], $seed['is_default']);
    }
    $row = $config->find_family_capability($finance_id, 'amount');
    ac_assert('Seed inserta finance/amount enabled', is_array($row) && $row['is_default'] === true);

    $images_product = $config->find_family_capability($finance_id, 'images');
    $archive_id = $config->resolve_family_id('archive');
    $images_archive_product = $archive_id !== null
        ? $config->find_family_capability((int) $archive_id, 'images')
        : null;
    ac_assert(
        'Producto ready: ensure inserta images finance default off',
        is_array($images_product) && $images_product['is_default'] === false
    );
    ac_assert(
        'Producto ready: ensure inserta images archive default on',
        is_array($images_archive_product) && $images_archive_product['is_default'] === true
    );

    $config->upsert_family_capability($finance_id, 'amount', false);
    foreach (AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds() as $seed) {
        if ($seed['capability_key'] !== 'amount') {
            continue;
        }
        $config->insert_family_capability_if_missing($finance_id, 'amount', true);
    }
    $guarded = $config->find_family_capability($finance_id, 'amount');
    ac_assert('Re-ensure no sobrescribe desactivación guardada', is_array($guarded) && $guarded['is_default'] === false);

    // Re-habilitar para materializar.
    $config->upsert_family_capability($finance_id, 'amount', true);

    $stack = AA_Canonical_Capability_Write_Bootstrap::build_stack($wpdb);
    $relational = new CanonicalRelationalRepository($wpdb);
    $adapter = new AA_Canonical_Relational_Write_Adapter($relational);
    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $write_registry->register(new CanonicalReadIdentity('finance'), $adapter);
    $gateway = new CanonicalWriteGateway($write_registry);
    $container_uc = new WriteCanonicalShellContainerUseCase($gateway, $stack['materializer']);
    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('finance'),
        $family_registry->family('finance')
    );

    $created = $container_uc->create($manifest, new CanonicalCreateContainerCommand('Con default amount', null));
    ac_assert('Create lista confirmed', $created->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $cid = (int) $created->receipt()->resource_id();
    $caps = $config->list_container_capabilities($cid);
    $active = false;
    foreach ($caps as $cap) {
        if ($cap['capability_key'] === 'amount' && !empty($cap['is_active'])) {
            $active = true;
        }
    }
    ac_assert('Materializó amount en lista nueva', $active);

    // Listas existentes no reciben activación masiva: crear lista sin default enabled.
    $config->upsert_family_capability($finance_id, 'amount', false);
    $created2 = $container_uc->create($manifest, new CanonicalCreateContainerCommand('Sin default', null));
    $cid2 = (int) $created2->receipt()->resource_id();
    ac_assert(
        'Lista nueva sin default enabled → sin amount activo',
        $config->find_container_capability($cid2, 'amount') === null
            || empty($config->find_container_capability($cid2, 'amount')['is_active'])
    );
    // La lista anterior conserva su activación.
    $still = $config->find_container_capability($cid, 'amount');
    ac_assert('Lista existente conserva activación previa', is_array($still) && !empty($still['is_active']));
} catch (\Throwable $e) {
    ac_assert('Excepción inesperada: ' . $e->getMessage(), false);
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
    update_option('aa_db_version', $prior_db_version);
    update_option(AA_Canonical_Capability_Defaults_Lifecycle::OPTION_VERSION, $prior_defaults_version);
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
