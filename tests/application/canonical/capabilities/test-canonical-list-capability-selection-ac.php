<?php
/**
 * AC — selección de capacidades por lista (conservación, subset, repertorio).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-list-capability-selection-ac.php
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
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilitySelection.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityWriteBag.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityWriteRejected.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/ReadContainerCapabilityConfigUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilityConfigSnapshot.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordAmountRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_lcs_' . substr(md5((string) microtime(true)), 0, 8) . '_';
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

$cap_active = static function (array $caps, string $key): ?bool {
    foreach ($caps as $row) {
        if (($row['capability_key'] ?? '') === $key) {
            return !empty($row['is_active']);
        }
    }
    return null;
};

echo "=== Selección de capacidades por lista ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();
    AA_Canonical_Core_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);

    $ready_registry = (new AA_Canonical_Capability_Registry())
        ->register(new AA_Canonical_Capability_Definition('amount', AA_Canonical_Capability_Definition::SCOPE_RECORD, true))
        ->freeze();

    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $amount_repo = new CanonicalRecordAmountRepository($wpdb);
    $family_id = (int) $config->resolve_family_id('finance');
    $config->upsert_family_capability($family_id, 'amount', true);

    $materializer = new AA_Canonical_Capability_Defaults_Materializer($ready_registry, $config);
    $selection_preparer = new CanonicalContainerCapabilitySelectionPreparer($config, $ready_registry);
    $handlers = new CanonicalCapabilityWriteHandlerRegistry();
    $handlers->register(new AA_Canonical_Amount_Write_Handler($ready_registry, $config, $amount_repo));
    $handlers->freeze();
    $record_preparer = new CanonicalCapabilityRecordWritePreparer($handlers);

    $relational = new CanonicalRelationalRepository($wpdb);
    $adapter = new AA_Canonical_Relational_Write_Adapter($relational);
    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $write_registry->register(new CanonicalReadIdentity('finance'), $adapter);
    $gateway = new CanonicalWriteGateway($write_registry);
    $uc = new WriteCanonicalShellContainerUseCase($gateway, $materializer, $selection_preparer);
    $record_uc = new WriteCanonicalShellRecordUseCase($gateway, $record_preparer);
    $read_uc = new ReadContainerCapabilityConfigUseCase($config, $family_registry, $ready_registry);
    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('finance'),
        $family_registry->family('finance')
    );

    // omit create → defaults
    $omit = $uc->create($manifest, new CanonicalCreateContainerCommand('Omit defaults', null));
    ac_assert('Omit create confirmed', $omit->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $omit_id = (int) $omit->receipt()->resource_id();
    ac_assert('Omit materializa amount activo', $cap_active($config->list_container_capabilities($omit_id), 'amount') === true);

    // explicit empty create
    $empty = $uc->create(
        $manifest,
        new CanonicalCreateContainerCommand('Empty sel', null),
        CanonicalContainerCapabilitySelection::present([], [])
    );
    $empty_id = (int) $empty->receipt()->resource_id();
    ac_assert('Explicit empty: sin caps activas', $config->list_container_capabilities($empty_id) === []);

    // selection with amount → active
    $with_amount = $uc->create(
        $manifest,
        new CanonicalCreateContainerCommand('Con amount', null),
        CanonicalContainerCapabilitySelection::present(['amount'], ['amount'])
    );
    $list_id = (int) $with_amount->receipt()->resource_id();
    ac_assert('Selection amount activo', $cap_active($config->list_container_capabilities($list_id), 'amount') === true);

    // seed record amount value
    $rec = $record_uc->create(
        $manifest,
        new CanonicalCreateRecordCommand(
            $list_id,
            'Reg amount',
            null,
            CanonicalCapabilityWriteBag::from_present_fields(['amount' => '42.5'])
        )
    );
    ac_assert('Record con amount created', $rec->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $record_id = (int) $rec->receipt()->resource_id();
    ac_assert('Valor amount 42.50', $amount_repo->find_amount($record_id) === '42.50');

    // update omit → preserve
    $omit_upd = $uc->update(
        $manifest,
        new CanonicalUpdateContainerCommand($list_id, 'Con amount renombrada', null)
    );
    ac_assert('Update omit confirmed', $omit_upd->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('Update omit preserva amount activo', $cap_active($config->list_container_capabilities($list_id), 'amount') === true);

    // scope=[] selection=[] → no keys modified (amount stays active)
    $noop_scope = $uc->update(
        $manifest,
        new CanonicalUpdateContainerCommand($list_id, 'Con amount renombrada', null),
        CanonicalContainerCapabilitySelection::present([], [])
    );
    ac_assert('Update scope vacío confirmed', $noop_scope->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert(
        'scope=[] no toca amount (sigue activo)',
        $cap_active($config->list_container_capabilities($list_id), 'amount') === true
    );

    // scope=[amount] selection=[] → deactivate; values remain
    $deact = $uc->update(
        $manifest,
        new CanonicalUpdateContainerCommand($list_id, 'Con amount renombrada', null),
        CanonicalContainerCapabilitySelection::present(['amount'], [])
    );
    ac_assert('Deactivate confirmed', $deact->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('amount desactivado', $cap_active($config->list_container_capabilities($list_id), 'amount') === false);
    ac_assert('Valor amount conservado tras deactivate', $amount_repo->find_amount($record_id) === '42.50');

    // reactivate recovers access to value
    $react = $uc->update(
        $manifest,
        new CanonicalUpdateContainerCommand($list_id, 'Con amount renombrada', null),
        CanonicalContainerCapabilitySelection::present(['amount'], ['amount'])
    );
    ac_assert('Reactivate confirmed', $react->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('amount reactivado', $cap_active($config->list_container_capabilities($list_id), 'amount') === true);
    ac_assert('Valor amount intacto tras reactivate', $amount_repo->find_amount($record_id) === '42.50');

    // invalid: selection not subset
    $before_count = (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS . '`'
    );
    $subset_rejected = false;
    $subset_code = '';
    try {
        $uc->create(
            $manifest,
            new CanonicalCreateContainerCommand('Bad subset', null),
            CanonicalContainerCapabilitySelection::present(['amount'], ['amount', 'ghost'])
        );
    } catch (CanonicalCapabilityWriteRejected $e) {
        $subset_rejected = true;
        $subset_code = $e->error_code();
    }
    $after_count = (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS . '`'
    );
    ac_assert(
        'selection ⊄ scope rechaza sin write',
        $subset_rejected && $subset_code === 'invalid_capability_selection' && $after_count === $before_count
    );

    // create with key not in repertoire
    $wpdb->query($wpdb->prepare(
        'DELETE FROM `' . AA_Canonical_Schema::family_capabilities_table_name() . '` WHERE family_id = %d',
        $family_id
    ));
    $before_count = (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS . '`'
    );
    $rep_rejected = false;
    $rep_code = '';
    try {
        $uc->create(
            $manifest,
            new CanonicalCreateContainerCommand('Fuera repertorio', null),
            CanonicalContainerCapabilitySelection::present(['amount'], ['amount'])
        );
    } catch (CanonicalCapabilityWriteRejected $e) {
        $rep_rejected = true;
        $rep_code = $e->error_code();
    }
    $after_count = (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS . '`'
    );
    ac_assert(
        'Create fuera de repertorio rechaza sin write',
        $rep_rejected && $rep_code === 'capability_not_in_repertoire' && $after_count === $before_count
    );

    // Restore repertoire for snapshot test; list already has amount assigned
    $config->upsert_family_capability($family_id, 'amount', true);
    $snap_before = $read_uc->execute('finance', $list_id);
    ac_assert('Snapshot amount assigned+active', $snap_before->is_assigned('amount') && $snap_before->is_active('amount'));

    // Remove from repertoire; assigned list row remains visible in snapshot
    $wpdb->query($wpdb->prepare(
        'DELETE FROM `' . AA_Canonical_Schema::family_capabilities_table_name()
        . '` WHERE family_id = %d AND capability_key = %s',
        $family_id,
        'amount'
    ));
    $snap_after = $read_uc->execute('finance', $list_id);
    ac_assert(
        'Snapshot sigue mostrando amount asignado tras quitar del repertorio',
        $snap_after->is_assigned('amount') && $snap_after->is_active('amount')
    );

    // Update may still edit already-assigned key even if out of repertoire
    $deact2 = $uc->update(
        $manifest,
        new CanonicalUpdateContainerCommand($list_id, 'Fuera repertorio ok', null),
        CanonicalContainerCapabilitySelection::present(['amount'], [])
    );
    ac_assert(
        'Update assigned-out-of-repertoire permitido',
        $deact2->state() === CanonicalShellMutationResult::STATE_CONFIRMED
        && $cap_active($config->list_container_capabilities($list_id), 'amount') === false
    );
} catch (\Throwable $e) {
    ac_assert('Excepción inesperada: ' . $e->getMessage(), false);
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
