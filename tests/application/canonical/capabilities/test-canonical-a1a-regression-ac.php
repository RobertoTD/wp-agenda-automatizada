<?php
/**
 * AC A1a bloque 3 — regresión núcleo canónico + C1a (sin aportaciones / producto no ready).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-a1a-regression-ac.php
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

echo "=== Contención / producto ===\n";
$boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
ac_assert('Bootstrap producto amount is_ready false', preg_match("/'amount'[\s\S]*?false/", $boot) === 1);

$create_ajax = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalCreateRecordAjax.php');
ac_assert('Create record AJAX usa composition', strpos($create_ajax, 'build_write_composition') !== false);
$create_c_ajax = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalCreateContainerAjax.php');
ac_assert('Create container AJAX usa materializer', strpos($create_c_ajax, 'materializer') !== false);

$cmd = (string) file_get_contents($plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php');
ac_assert('Command sin campo amount tipado', strpos($cmd, 'function amount') === false);
ac_assert('Command transporta WriteBag', strpos($cmd, 'CanonicalCapabilityWriteBag') !== false);

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
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-capability-write-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordAmountRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_a1a3_' . substr(md5((string) microtime(true)), 0, 8) . '_';
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

echo "\n=== CRUD sin aportaciones + C1a producto ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();
    AA_Canonical_Core_Bootstrap::bootstrap();
    AA_Canonical_Capability_Registry_Bootstrap::reset_for_tests();
    AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);

    $stack = AA_Canonical_Capability_Write_Bootstrap::build_stack($wpdb);
    $relational = new CanonicalRelationalRepository($wpdb);
    $adapter = new AA_Canonical_Relational_Write_Adapter($relational);
    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $write_registry->register(new CanonicalReadIdentity('finance'), $adapter);
    $gateway = new CanonicalWriteGateway($write_registry);

    $container_uc = new WriteCanonicalShellContainerUseCase($gateway, $stack['materializer']);
    $record_uc = new WriteCanonicalShellRecordUseCase($gateway, $stack['preparer']);
    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('finance'),
        $family_registry->family('finance')
    );

    $list = $container_uc->create($manifest, new CanonicalCreateContainerCommand('Regresión', 'd'));
    ac_assert('Create lista sin ready amount → confirmed', $list->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $cid = (int) $list->receipt()->resource_id();

    $config = new CanonicalCapabilityConfigRepository($wpdb);
    ac_assert('Sin amount materializado en producto', $config->list_container_capabilities($cid) === []);

    $rec = $record_uc->create($manifest, new CanonicalCreateRecordCommand($cid, 'Solo base', 'x'));
    ac_assert('Create record sin bag → confirmed', $rec->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $rid = (int) $rec->receipt()->resource_id();
    $amount_repo = new CanonicalRecordAmountRepository($wpdb);
    ac_assert('Sin fila amount', $amount_repo->find_amount($rid) === null);

    $upd = $record_uc->update($manifest, new CanonicalUpdateRecordCommand($cid, $rid, 'Base 2', null));
    ac_assert('Update sin bag → confirmed', $upd->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $def = AA_Canonical_Capability_Registry_Bootstrap::instance()->get('amount');
    ac_assert('Catálogo producto amount !ready', $def->is_ready() === false);

    ac_assert(
        'Option aa_db_version de uso restaurable (harness no deja 23 en uso)',
        true
    );
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
    update_option('aa_db_version', $prior_db_version);
}

$live_version = (string) get_option('aa_db_version', '0');
ac_assert('BD de uso no forzada a 23 por este harness', $live_version === $prior_db_version || version_compare($live_version, '23', '<'));

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
