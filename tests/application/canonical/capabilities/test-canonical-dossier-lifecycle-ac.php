<?php
/**
 * AC — dossier: persistencia / ciclo de vida (schema, seeds, 1:1, CASCADE, create+associate TX).
 *
 * Ejecutar:
 *   php tests/application/canonical/capabilities/test-canonical-dossier-lifecycle-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-dossier-lifecycle-ac.php
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

echo "=== 1. Contención estática ===\n";
$boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
ac_assert(
    'Producto dossier is_ready=true',
    preg_match("/new AA_Canonical_Capability_Definition\(\s*'dossier'\s*,\s*AA_Canonical_Capability_Definition::SCOPE_RECORD\s*,\s*true\s*\)/", $boot) === 1
);
$life = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php'
);
ac_assert('DEFAULTS_VERSION=7', strpos($life, 'DEFAULTS_VERSION = 7') !== false);
ac_assert(
    'Seed contact/dossier/false',
    preg_match(
        "/'family_key'\s*=>\s*'contact'[\s\S]{0,120}'capability_key'\s*=>\s*'dossier'[\s\S]{0,80}'is_default'\s*=>\s*false/",
        $life
    ) === 1
);
$schema = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/Schema.php');
ac_assert("DB_VERSION='35'", strpos($schema, "DB_VERSION = '35'") !== false);
$canonical = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php');
ac_assert('TABLE_CONTACT_DOSSIER', strpos($canonical, "TABLE_CONTACT_DOSSIER = 'aa_canonical_contact_dossier'") !== false);
ac_assert('uq_dossier_archive_container', strpos($canonical, 'uq_dossier_archive_container') !== false);
ac_assert(
    'Docs §13 dossier',
    strpos((string) file_get_contents($plugin_root . '/docs/05-canonical-capabilities.md'), '## 13. Capacidad `dossier`') !== false
);
$uc = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierUseCase.php'
);
ac_assert('Create usa materializer + associate effect', strpos($uc, 'AA_Canonical_Dossier_Associate_Effect') !== false);
ac_assert('has_blocking_purge origen', strpos($uc, 'has_blocking_purge(') !== false);
ac_assert('has_blocking_purge_for_container destino', strpos($uc, 'has_blocking_purge_for_container') !== false);
ac_assert('target_invalid conserva vínculo', strpos($uc, 'target_invalid') !== false);
ac_assert('Solo ausente borra asociación', strpos($uc, 'delete_by_contact_record_id') !== false);

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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-capability-write-bootstrap.php';
AA_Canonical_Capability_Write_Bootstrap::build_stack();
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalContactDossierRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalPurgeRunsRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordNotFound.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityWriteBag.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilitySchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityPersistenceFailed.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalQueryFailed.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalAmbiguousOutcome.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadSchemaNotReady.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadPersistenceFailed.php';
require_once $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-dossier-associate-effect.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierCommand.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierResult.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerMutationContext.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilityEffect.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_dos_' . substr(md5((string) microtime(true)), 0, 8) . '_';

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

try {
    echo "=== 2. MySQL: create+associate+CASCADE ===\n";
    $wpdb->prefix = $temp_prefix;
    AA_Canonical_Schema::install();

    AA_Canonical_Core_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);

    $capability_registry = AA_Canonical_Capability_Registry_Bootstrap::build_registry();
    $stack = AA_Canonical_Capability_Write_Bootstrap::build_stack($wpdb);
    $relational = new CanonicalRelationalRepository($wpdb);
    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $dossier = new CanonicalContactDossierRepository($wpdb);

    $contact_family_id = (int) $config->resolve_family_id('contact');
    $archive_family_id = (int) $config->resolve_family_id('archive');
    $config->insert_family_capability_if_missing($contact_family_id, 'dossier', false);
    $config->insert_family_capability_if_missing($archive_family_id, 'images', true);

    $adapter = new AA_Canonical_Relational_Write_Adapter($relational);
    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $write_registry->register(new CanonicalReadIdentity('contact'), $adapter);
    $write_registry->register(new CanonicalReadIdentity('archive'), $adapter);
    $gateway = new CanonicalWriteGateway($write_registry);

    $contact_manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('contact'),
        $family_registry->family('contact')
    );
    $archive_family = $family_registry->family('archive');

    $container_uc = new WriteCanonicalShellContainerUseCase(
        $gateway,
        $stack['materializer'],
        $stack['selection_preparer']
    );
    $contact_list = $container_uc->create(
        $contact_manifest,
        new CanonicalCreateContainerCommand('Lista contactos dossier', null),
        null
    );
    ac_assert('Lista contactos creada', $contact_list->state() === 'confirmed');
    $contact_container_id = (int) $contact_list->receipt()->resource_id();
    $config->upsert_container_capability($contact_container_id, 'dossier', true);

    $record_uc = new WriteCanonicalShellRecordUseCase($gateway, $stack['preparer']);
    $record_res = $record_uc->create(
        $contact_manifest,
        new CanonicalCreateRecordCommand($contact_container_id, 'Ana Pérez', null)
    );
    ac_assert('Contacto creado', $record_res->state() === 'confirmed');
    $contact_record_id = (int) $record_res->receipt()->resource_id();

    $purge = new CanonicalPurgeRunsRepository($wpdb);
    $lock = AA_Expediente_Aggregate_Lock::create_default();
    $use_case = new OpenOrCreateCanonicalContactDossierUseCase(
        $relational,
        $dossier,
        $config,
        $capability_registry,
        $gateway,
        $stack['materializer'],
        $purge,
        $lock,
        $archive_family
    );

    $created = $use_case->execute(new OpenOrCreateCanonicalContactDossierCommand(
        $contact_container_id,
        $contact_record_id
    ));
    ac_assert('Primer open crea expediente', $created->state() === OpenOrCreateCanonicalContactDossierResult::STATE_CREATED);
    $archive_id = (int) $created->archive_container_id();
    ac_assert('archive_container_id positivo', $archive_id >= 1);

    $assoc = $dossier->find_by_contact_record_id($contact_record_id);
    ac_assert('Asociación 1:1 persistida', is_array($assoc) && (int) $assoc['archive_container_id'] === $archive_id);

    $archive_row = $relational->find_container($archive_family_id, $archive_id);
    ac_assert('Lista Archivo existe', is_array($archive_row));
    ac_assert(
        'Título Exp — ',
        is_array($archive_row) && strpos((string) $archive_row['title'], 'Exp — ') === 0
    );

    $caps = $config->find_container_capability($archive_id, 'images');
    ac_assert('Defaults Archivo materializados (images)', is_array($caps) && !empty($caps['is_active']));

    $reopen = $use_case->execute(new OpenOrCreateCanonicalContactDossierCommand(
        $contact_container_id,
        $contact_record_id
    ));
    ac_assert('Reopen no duplica', $reopen->state() === OpenOrCreateCanonicalContactDossierResult::STATE_OPENED);
    ac_assert('Mismo archive id', (int) $reopen->archive_container_id() === $archive_id);

    $count_before = (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . AA_Canonical_Schema::containers_table_name() . '` WHERE family_id = ' . (int) $archive_family_id
    );

    $relational->delete_record($contact_family_id, $contact_container_id, $contact_record_id);
    $assoc_after = $dossier->find_by_contact_record_id($contact_record_id);
    ac_assert('Borrar contacto elimina asociación', $assoc_after === null);
    $archive_still = $relational->find_container($archive_family_id, $archive_id);
    ac_assert('Expediente Archivo conservado', is_array($archive_still));
    $count_after = (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . AA_Canonical_Schema::containers_table_name() . '` WHERE family_id = ' . (int) $archive_family_id
    );
    ac_assert('Sin drop extra de listas Archivo', $count_after === $count_before);

    $record2 = $record_uc->create(
        $contact_manifest,
        new CanonicalCreateRecordCommand($contact_container_id, 'Luis', null)
    );
    $rid2 = (int) $record2->receipt()->resource_id();
    $created2 = $use_case->execute(new OpenOrCreateCanonicalContactDossierCommand($contact_container_id, $rid2));
    $aid2 = (int) $created2->archive_container_id();
    ac_assert('Segundo contacto otro expediente', $aid2 >= 1 && $aid2 !== $archive_id);

    $relational->delete_container($archive_family_id, $aid2);
    $assoc2 = $dossier->find_by_contact_record_id($rid2);
    ac_assert('Hard delete Archivo elimina asociación', $assoc2 === null);
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
