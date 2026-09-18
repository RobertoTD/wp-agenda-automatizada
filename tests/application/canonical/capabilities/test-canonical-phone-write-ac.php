<?php
/**
 * AC — escritura transaccional phone (omit/clear/set + seed contact + sin UNIQUE).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-phone-write-ac.php
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

echo "=== 1. Contención producto ===\n";
$boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
ac_assert(
    'Producto phone is_ready=true',
    preg_match("/new AA_Canonical_Capability_Definition\(\s*'phone'\s*,\s*AA_Canonical_Capability_Definition::SCOPE_RECORD\s*,\s*true\s*\)/", $boot) === 1
);
$life = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php'
);
ac_assert('DEFAULTS_VERSION=4', strpos($life, 'DEFAULTS_VERSION = 4') !== false);
ac_assert(
    'Seed contact/phone/false',
    strpos($life, "'capability_key' => 'phone'") !== false
    && strpos($life, "'family_key' => 'contact'") !== false
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
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
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityInactive.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityWriteRejected.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordPhoneRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_phn_' . substr(md5((string) microtime(true)), 0, 8) . '_';

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

echo "\n=== 2. MySQL: escritura phone vía UC ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();

    AA_Canonical_Core_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);

    $ready_registry = (new AA_Canonical_Capability_Registry())
        ->register(new AA_Canonical_Capability_Definition('phone', AA_Canonical_Capability_Definition::SCOPE_RECORD, true))
        ->freeze();

    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $phone_repo = new CanonicalRecordPhoneRepository($wpdb);
    $handlers = new CanonicalCapabilityWriteHandlerRegistry();
    $handlers->register(new AA_Canonical_Phone_Write_Handler($ready_registry, $config, $phone_repo));
    $handlers->freeze();
    $preparer = new CanonicalCapabilityRecordWritePreparer($handlers);

    $relational = new CanonicalRelationalRepository($wpdb);
    $adapter = new AA_Canonical_Relational_Write_Adapter($relational);
    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $write_registry->register(new CanonicalReadIdentity('contact'), $adapter);
    $gateway = new CanonicalWriteGateway($write_registry);
    $record_uc = new WriteCanonicalShellRecordUseCase($gateway, $preparer);
    $container_uc = new WriteCanonicalShellContainerUseCase($gateway);

    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('contact'),
        $family_registry->family('contact')
    );

    $created_list = $container_uc->create($manifest, new CanonicalCreateContainerCommand('Lista phone', null));
    ac_assert('Lista creada', $created_list->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $container_id = (int) $created_list->receipt()->resource_id();

    $contact_id = (int) $config->resolve_family_id('contact');
    $config->insert_family_capability_if_missing($contact_id, 'phone', false);
    $config->upsert_container_capability($container_id, 'phone', true);

    $bag_set = CanonicalCapabilityWriteBag::from_present_fields(['phone' => '+5491112345678']);
    $created = $record_uc->create(
        $manifest,
        new CanonicalCreateRecordCommand($container_id, 'Con phone', null, $bag_set)
    );
    ac_assert('Create con phone → confirmed', $created->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $record_id = (int) $created->receipt()->resource_id();
    ac_assert('Persistió E.164', $phone_repo->find_phone($record_id) === '+5491112345678');

    $omit = $record_uc->update(
        $manifest,
        new CanonicalUpdateRecordCommand($container_id, $record_id, 'Sin tocar phone', null)
    );
    ac_assert(
        'Omit conserva',
        $omit->state() === CanonicalShellMutationResult::STATE_CONFIRMED
        && $phone_repo->find_phone($record_id) === '+5491112345678'
    );

    $dup = $record_uc->create(
        $manifest,
        new CanonicalCreateRecordCommand(
            $container_id,
            'Mismo phone',
            null,
            CanonicalCapabilityWriteBag::from_present_fields(['phone' => '+5491112345678'])
        )
    );
    ac_assert('Dos registros pueden compartir phone', $dup->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $dup_id = (int) $dup->receipt()->resource_id();
    ac_assert('Segundo registro con mismo phone', $phone_repo->find_phone($dup_id) === '+5491112345678');

    $clear = $record_uc->update(
        $manifest,
        new CanonicalUpdateRecordCommand(
            $container_id,
            $record_id,
            'Clear phone',
            null,
            CanonicalCapabilityWriteBag::from_present_fields(['phone' => ''])
        )
    );
    ac_assert(
        'Clear elimina fila',
        $clear->state() === CanonicalShellMutationResult::STATE_CONFIRMED
        && $phone_repo->find_phone($record_id) === null
    );

    $invalid_thrown = false;
    try {
        $record_uc->update(
            $manifest,
            new CanonicalUpdateRecordCommand(
                $container_id,
                $record_id,
                'Inválido',
                null,
                CanonicalCapabilityWriteBag::from_present_fields(['phone' => '+540111234567'])
            )
        );
    } catch (CanonicalCapabilityWriteRejected $e) {
        $invalid_thrown = true;
        ac_assert('Inválido no clear', $phone_repo->find_phone($record_id) === null);
        ac_assert('Código invalid/length', in_array($e->error_code(), ['invalid_phone', 'phone_invalid_length'], true));
    }
    ac_assert('Inválido lanza WriteRejected', $invalid_thrown);

    $config->upsert_container_capability($container_id, 'phone', false);
    $inactive_thrown = false;
    try {
        $record_uc->update(
            $manifest,
            new CanonicalUpdateRecordCommand(
                $container_id,
                $dup_id,
                'Inactive',
                null,
                CanonicalCapabilityWriteBag::from_present_fields(['phone' => '+525636299377'])
            )
        );
    } catch (CanonicalCapabilityInactive $e) {
        $inactive_thrown = true;
    }
    ac_assert('Inactive rechaza write', $inactive_thrown);
    ac_assert('Inactive conserva valor', $phone_repo->find_phone($dup_id) === '+5491112345678');

    $finance_id = (int) $config->resolve_family_id('finance');
    $finance_seed = $config->find_family_capability($finance_id, 'phone');
    ac_assert('Sin seed phone en finance', $finance_seed === null);
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
