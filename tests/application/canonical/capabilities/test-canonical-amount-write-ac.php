<?php
/**
 * AC A1a bloque 1 — amount + atomicidad de registros (UC + transporte bag + repo TX).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-amount-write-ac.php
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

echo "=== 1. Contención normalizador / producto ===\n";
$finance_support_path = $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
ac_assert('FinanceUseCaseSupport ausente (LEGACY-X)', !is_file($finance_support_path));
$norm_src = (string) file_get_contents($plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_Normalizer.php');
ac_assert('Normalizador canónico no importa FinanceUseCaseSupport', strpos($norm_src, 'FinanceUseCaseSupport') === false);

$product_boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
ac_assert('Producto amount is_ready=true', preg_match("/new AA_Canonical_Capability_Definition\(\s*'amount'\s*,\s*AA_Canonical_Capability_Definition::SCOPE_RECORD\s*,\s*true\s*\)/", $product_boot) === 1);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_Normalizer.php';

echo "\n=== 1b. Normalización canónica ===\n";
$ok = AA_Canonical_Amount_Normalizer::normalize('12.5');
ac_assert('Normaliza 12.5 → 12.50', !empty($ok['ok']) && $ok['value'] === '12.50');
$zero = AA_Canonical_Amount_Normalizer::normalize('0');
ac_assert('Cero válido → 0.00', !empty($zero['ok']) && $zero['value'] === '0.00');
$empty = AA_Canonical_Amount_Normalizer::normalize('');
ac_assert('Vacío → null', !empty($empty['ok']) && $empty['value'] === null);
$bad = AA_Canonical_Amount_Normalizer::normalize('1.234');
ac_assert('Decimales excess → amount_too_many_decimals', empty($bad['ok']) && $bad['error']['code'] === 'amount_too_many_decimals');

require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-capability-write-bootstrap.php';
AA_Canonical_Capability_Write_Bootstrap::build_stack();
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityWriteBag.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityInactive.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityWriteRejected.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityUnknown.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordAmountRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';
require_once $plugin_root . '/includes/http/ajax/CanonicalShellWriteAjaxRejection.php';
require_once $plugin_root . '/includes/http/ajax/CanonicalShellWriteAjaxSupport.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_a1a1_' . substr(md5((string) microtime(true)), 0, 8) . '_';
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

echo "\n=== 2. MySQL: escritura amount vía UC ===\n";
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
    $handlers = new CanonicalCapabilityWriteHandlerRegistry();
    $handlers->register(new AA_Canonical_Amount_Write_Handler($ready_registry, $config, $amount_repo));
    $handlers->freeze();
    $preparer = new CanonicalCapabilityRecordWritePreparer($handlers);

    $relational = new CanonicalRelationalRepository($wpdb);
    $adapter = new AA_Canonical_Relational_Write_Adapter($relational);
    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $write_registry->register(new CanonicalReadIdentity('finance'), $adapter);
    $gateway = new CanonicalWriteGateway($write_registry);
    $record_uc = new WriteCanonicalShellRecordUseCase($gateway, $preparer);
    $container_uc = new WriteCanonicalShellContainerUseCase($gateway);

    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('finance'),
        $family_registry->family('finance')
    );

    $created_list = $container_uc->create($manifest, new CanonicalCreateContainerCommand('Lista A1a', null));
    ac_assert('Lista creada', $created_list->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $container_id = (int) $created_list->receipt()->resource_id();

    $family_id = (int) $config->resolve_family_id('finance');
    $config->upsert_container_capability($container_id, 'amount', true);

    $bag_set = CanonicalCapabilityWriteBag::from_present_fields(['amount' => '10.5']);
    $created = $record_uc->create(
        $manifest,
        new CanonicalCreateRecordCommand($container_id, 'Con amount', null, $bag_set)
    );
    ac_assert('Create con amount → confirmed', $created->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $record_id = (int) $created->receipt()->resource_id();
    ac_assert('Persistió 10.50', $amount_repo->find_amount($record_id) === '10.50');

    $omit = $record_uc->update(
        $manifest,
        new CanonicalUpdateRecordCommand($container_id, $record_id, 'Sin tocar amount', null)
    );
    ac_assert('Omit conserva', $omit->state() === CanonicalShellMutationResult::STATE_CONFIRMED
        && $amount_repo->find_amount($record_id) === '10.50');

    $zero = $record_uc->update(
        $manifest,
        new CanonicalUpdateRecordCommand(
            $container_id,
            $record_id,
            'Cero',
            null,
            CanonicalCapabilityWriteBag::from_present_fields(['amount' => '0'])
        )
    );
    ac_assert('Cero válido', $zero->state() === CanonicalShellMutationResult::STATE_CONFIRMED
        && $amount_repo->find_amount($record_id) === '0.00');

    $clear = $record_uc->update(
        $manifest,
        new CanonicalUpdateRecordCommand(
            $container_id,
            $record_id,
            'Clear',
            null,
            CanonicalCapabilityWriteBag::from_present_fields(['amount' => ''])
        )
    );
    ac_assert('Vaciar elimina fila', $clear->state() === CanonicalShellMutationResult::STATE_CONFIRMED
        && $amount_repo->find_amount($record_id) === null);

    $rejected = false;
    try {
        $record_uc->create(
            $manifest,
            new CanonicalCreateRecordCommand(
                $container_id,
                'Bad',
                null,
                CanonicalCapabilityWriteBag::from_present_fields(['amount' => '1.999'])
            )
        );
    } catch (CanonicalCapabilityWriteRejected $e) {
        $rejected = ($e->error_code() === 'amount_too_many_decimals' && $e->http_status() === 400);
    }
    ac_assert('Input inválido rechazado antes de mutar', $rejected);

    $config->upsert_container_capability($container_id, 'amount', false);
    $inactive = false;
    try {
        $record_uc->create(
            $manifest,
            new CanonicalCreateRecordCommand(
                $container_id,
                'Inactiva',
                null,
                CanonicalCapabilityWriteBag::from_present_fields(['amount' => '1.00'])
            )
        );
    } catch (CanonicalCapabilityInactive $e) {
        $inactive = ($e->error_code() === 'capability_inactive');
    }
    ac_assert('Capacidad inactiva → capability_inactive', $inactive);

    $not_ready_registry = (new AA_Canonical_Capability_Registry())
        ->register(new AA_Canonical_Capability_Definition('amount', AA_Canonical_Capability_Definition::SCOPE_RECORD, false))
        ->freeze();
    $not_ready_handlers = new CanonicalCapabilityWriteHandlerRegistry();
    $not_ready_handlers->register(new AA_Canonical_Amount_Write_Handler($not_ready_registry, $config, $amount_repo));
    $not_ready_handlers->freeze();
    $not_ready_preparer = new CanonicalCapabilityRecordWritePreparer($not_ready_handlers);
    $not_ready_uc = new WriteCanonicalShellRecordUseCase($gateway, $not_ready_preparer);
    $config->upsert_container_capability($container_id, 'amount', true);
    $not_ready = false;
    try {
        $not_ready_uc->create(
            $manifest,
            new CanonicalCreateRecordCommand(
                $container_id,
                'No ready',
                null,
                CanonicalCapabilityWriteBag::from_present_fields(['amount' => '2.00'])
            )
        );
    } catch (CanonicalCapabilityNotReady $e) {
        $not_ready = ($e->error_code() === 'capability_not_ready' && $e->http_status() === 409);
    }
    ac_assert('Fixture !ready → capability_not_ready', $not_ready);

    $failing_effect = new class implements CanonicalRecordCapabilityEffect {
        public function apply(CanonicalRecordMutationContext $context): void {
            throw new CanonicalMutationPersistenceFailed('simulated amount effect failure');
        }
    };
    $before_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_RECORDS . '`');
    $rolled = false;
    try {
        $relational->create_record($family_id, $container_id, 'Boom', null, [$failing_effect]);
    } catch (CanonicalRelationalQueryFailed $e) {
        $rolled = true;
    } catch (CanonicalRelationalAmbiguousOutcome $e) {
        $rolled = false;
    }
    $after_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_RECORDS . '`');
    ac_assert('Fallo de effect → rollback confirmado', $rolled && $after_count === $before_count);

    echo "\n=== 3. Transporte: bag + mapeo HTTP ===\n";
    try {
        $bag = CanonicalShellWriteAjaxSupport::capability_write_bag_from_source(['amount' => '3.00']);
        ac_assert('Bag desde source con amount', $bag->has('amount') && $bag->raw('amount') === '3.00');
    } catch (CanonicalShellWriteAjaxRejection $e) {
        ac_assert('Bag desde source con amount', false, $e->error_code());
    }
    $mapped = CanonicalShellWriteAjaxSupport::map_capability_write_exception(
        new CanonicalCapabilityNotReady('amount')
    );
    ac_assert(
        'Map not_ready → 409',
        $mapped instanceof CanonicalShellWriteAjaxRejection
        && $mapped->error_code() === 'capability_not_ready'
        && $mapped->http_status() === 409
    );
    $mapped_bad = CanonicalShellWriteAjaxSupport::map_capability_write_exception(
        new CanonicalCapabilityWriteRejected('invalid_amount', 'bad', 400)
    );
    ac_assert(
        'Map invalid_amount → 400',
        $mapped_bad instanceof CanonicalShellWriteAjaxRejection
        && $mapped_bad->http_status() === 400
    );
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
