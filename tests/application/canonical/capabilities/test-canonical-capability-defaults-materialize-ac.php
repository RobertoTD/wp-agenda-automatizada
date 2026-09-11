<?php
/**
 * AC A1a bloque 2 — materialización de defaults al crear listas (atómica).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-capability-defaults-materialize-ac.php
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

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: 0/0 ---\n";
    exit(0);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-capability-write-bootstrap.php';
AA_Canonical_Capability_Write_Bootstrap::build_stack();
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
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
$temp_prefix = 'tmp_a1a2_' . substr(md5((string) microtime(true)), 0, 8) . '_';
$prior_db_version = (string) get_option('aa_db_version', '0');

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

echo "=== Defaults / materialización ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();
    AA_Canonical_Core_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);

    $ready_registry = (new AA_Canonical_Capability_Registry())
        ->register(new AA_Canonical_Capability_Definition('amount', AA_Canonical_Capability_Definition::SCOPE_RECORD, true))
        ->register(new AA_Canonical_Capability_Definition('probe', AA_Canonical_Capability_Definition::SCOPE_RECORD, true))
        ->freeze();

    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $family_id = (int) $config->resolve_family_id('finance');
    $config->upsert_family_default($family_id, 'amount', true);
    $config->upsert_family_default($family_id, 'probe', true);

    $existing_id = null;
    $relational = new CanonicalRelationalRepository($wpdb);
    $existing = $relational->create_container($family_id, 'Lista previa', null, []);
    $existing_id = (int) $existing['id'];
    ac_assert('Lista previa sin capabilities', $config->list_container_capabilities($existing_id) === []);

    $materializer = new AA_Canonical_Capability_Defaults_Materializer($ready_registry, $config);
    $adapter = new AA_Canonical_Relational_Write_Adapter($relational);
    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $write_registry->register(new CanonicalReadIdentity('finance'), $adapter);
    $gateway = new CanonicalWriteGateway($write_registry);
    $uc = new WriteCanonicalShellContainerUseCase($gateway, $materializer);
    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('finance'),
        $family_registry->family('finance')
    );

    $created = $uc->create($manifest, new CanonicalCreateContainerCommand('Lista nueva', null));
    ac_assert('Create lista → confirmed', $created->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $new_id = (int) $created->receipt()->resource_id();
    $caps = $config->list_container_capabilities($new_id);
    $keys = array_map(static function ($row) {
        return $row['capability_key'];
    }, $caps);
    sort($keys);
    ac_assert('Materializó amount+probe ready', $keys === ['amount', 'probe']);
    ac_assert('Lista previa intacta', $config->list_container_capabilities($existing_id) === []);

    $product_registry = AA_Canonical_Capability_Registry_Bootstrap::instance();
    $product_materializer = new AA_Canonical_Capability_Defaults_Materializer($product_registry, $config);
    $product_uc = new WriteCanonicalShellContainerUseCase($gateway, $product_materializer);
    $created_product = $product_uc->create($manifest, new CanonicalCreateContainerCommand('Lista producto', null));
    ac_assert('Create con catálogo producto → confirmed', $created_product->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $product_list_id = (int) $created_product->receipt()->resource_id();
    $product_caps = $config->list_container_capabilities($product_list_id);
    $has_amount = false;
    foreach ($product_caps as $row) {
        if ($row['capability_key'] === 'amount') {
            $has_amount = true;
        }
    }
    ac_assert('Producto ready materializa amount si default enabled', $has_amount);

    $not_ready_registry = (new AA_Canonical_Capability_Registry())
        ->register(new AA_Canonical_Capability_Definition('amount', AA_Canonical_Capability_Definition::SCOPE_RECORD, false))
        ->freeze();
    $nr_materializer = new AA_Canonical_Capability_Defaults_Materializer($not_ready_registry, $config);
    $nr_uc = new WriteCanonicalShellContainerUseCase($gateway, $nr_materializer);
    $created_nr = $nr_uc->create($manifest, new CanonicalCreateContainerCommand('Lista !ready', null));
    $nr_id = (int) $created_nr->receipt()->resource_id();
    $nr_caps = $config->list_container_capabilities($nr_id);
    ac_assert('Fixture !ready no materializa amount', $nr_caps === []);

    $failing = new class implements CanonicalContainerCapabilityEffect {
        public function apply(CanonicalContainerMutationContext $context): void {
            throw new CanonicalMutationPersistenceFailed('simulated materialize failure');
        }
    };
    $before = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS . '`');
    $rolled = false;
    try {
        $relational->create_container($family_id, 'Fail list', null, [$failing]);
    } catch (CanonicalRelationalQueryFailed $e) {
        $rolled = true;
    }
    $after = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS . '`');
    ac_assert('Fallo materialización → rollback lista', $rolled && $after === $before);
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
    update_option('aa_db_version', $prior_db_version);
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
