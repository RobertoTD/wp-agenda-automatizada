<?php
/**
 * AC — C3: contrato, disponibilidad y persistencia aislada de contact_dossier.
 *
 * Ejecutar:
 *   php tests/application/canonical/solutions/test-contact-dossier-application-c2-ac.php
 */

$plugin_root = dirname(__DIR__, 4);
$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
$has_real_wp = $wp_load !== '' && is_readable($wp_load);

if ($has_real_wp) {
    require_once $wp_load;
} elseif (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-solution-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-solution-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-solution-registry-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/solutions/CanonicalSolutionApplicationRejected.php';
require_once $plugin_root . '/includes/application/canonical/solutions/contact_dossier/CanonicalContactDossierApplicationSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/solutions/contact_dossier/CanonicalContactDossierApplicationPolicy.php';
require_once $plugin_root . '/includes/application/canonical/solutions/contact_dossier/SetContactDossierApplicationCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerMutationEffect.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilityEffect.php';

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

echo "=== Definition y registry ===\n";
$registry = AA_Canonical_Solution_Registry_Bootstrap::build_registry();
$definition = $registry->get(AA_Canonical_Solution_Registry_Bootstrap::CONTACT_DOSSIER);
ac_assert('Registry congelado', $registry->is_frozen());
ac_assert('contact_dossier v1', $definition->key() === 'contact_dossier' && $definition->version() === 1);
ac_assert('Producto ready en C3', $definition->is_ready());
ac_assert('Aplicable solo a Contactos', $definition->applicable_family_keys() === ['contact']);
ac_assert('Archivo habilitado es requisito', $definition->required_enabled_family_keys() === ['archive']);
ac_assert('Sin capability artificial requerida', $definition->capability_keys() === []);
ac_assert('Lifecycle conserva recursos', $definition->deactivation_policy() === 'preserve_resources');

$ready_definition = new AA_Canonical_Solution_Definition(
    'contact_dossier',
    1,
    'Expediente',
    true,
    AA_Canonical_Solution_Definition::APPLICATION_SCOPE_CONTAINER,
    ['contact'],
    ['archive'],
    [],
    AA_Canonical_Solution_Definition::ACTIVATION_EXPLICIT,
    AA_Canonical_Solution_Definition::DEACTIVATION_PRESERVE_RESOURCES,
    AA_Canonical_Solution_Definition::LIFECYCLE_LAZY_CREATE_PRESERVE
);

$policy = new CanonicalContactDossierApplicationPolicy();
$enabled = new CanonicalFamilyEnablementSnapshot([
    'archive' => new CanonicalFamilyEnablementStatus('archive', true, true),
]);
$disabled = new CanonicalFamilyEnablementSnapshot([
    'archive' => new CanonicalFamilyEnablementStatus('archive', true, false),
]);
$unprovisioned = new CanonicalFamilyEnablementSnapshot([
    'archive' => new CanonicalFamilyEnablementStatus('archive', false, false),
]);

echo "=== Availability y estado persistido ===\n";
$not_ready = $policy->evaluate($definition, 'contact', 7, null, $enabled);
ac_assert('ready está disponible con prerequisitos', $not_ready->is_available());
ac_assert('ready no expone blocker', $not_ready->blockers() === []);

$available = $policy->evaluate($ready_definition, 'contact', 7, null, $enabled);
ac_assert('Contactos + Archivo habilitado disponible', $available->is_applicable() && $available->is_available());
ac_assert('Disponible no implica activa', !$available->is_active());

$missing_archive = $policy->evaluate($ready_definition, 'contact', 7, null, $unprovisioned);
ac_assert('Archivo no provisionado bloquea', $missing_archive->blockers() === ['required_family_not_provisioned:archive']);
$disabled_archive = $policy->evaluate($ready_definition, 'contact', 7, null, $disabled);
ac_assert('Archivo deshabilitado bloquea', $disabled_archive->blockers() === ['required_family_disabled:archive']);

$persisted_active = [
    'contact_container_id' => 7,
    'is_active' => true,
    'created_at' => '2026-09-20 12:00:00',
    'updated_at' => '2026-09-20 12:00:00',
];
$active_but_unavailable = $policy->evaluate(
    $ready_definition,
    'contact',
    7,
    $persisted_active,
    $disabled
);
ac_assert('active no se deriva de available', $active_but_unavailable->is_active() && !$active_but_unavailable->is_available());

$finance = $policy->evaluate($ready_definition, 'finance', 8, null, $enabled);
ac_assert('Otra familia es contexto inaplicable', !$finance->is_applicable() && !$finance->is_available());

echo "=== Política de transición ===\n";
$activation_rejected = false;
try {
    $policy->assert_transition_allowed($disabled_archive, true);
} catch (CanonicalSolutionApplicationRejected $e) {
    $activation_rejected = $e->reason_code() === 'required_family_disabled:archive';
}
ac_assert('Activación fail-closed sin Archivo', $activation_rejected);

$deactivation_allowed = true;
try {
    $policy->assert_transition_allowed($active_but_unavailable, false);
} catch (CanonicalSolutionApplicationRejected $e) {
    $deactivation_allowed = false;
}
ac_assert('Desactivación permitida aunque Archivo no esté disponible', $deactivation_allowed);

$inapplicable_rejected = false;
try {
    $policy->assert_transition_allowed($finance, false);
} catch (CanonicalSolutionApplicationRejected $e) {
    $inapplicable_rejected = $e->reason_code() === 'inapplicable_context';
}
ac_assert('Contexto inaplicable rechaza toda mutación', $inapplicable_rejected);

$command = new SetContactDossierApplicationCommand(7, true);
ac_assert('Command tipado por lista', $command->contact_container_id() === 7 && $command->active());

echo "=== Contención arquitectónica ===\n";
$repository_src = (string) file_get_contents(
    $plugin_root . '/includes/repositories/CanonicalContactDossierApplicationRepository.php'
);
$set_src = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/solutions/contact_dossier/SetContactDossierApplicationUseCase.php'
);
$schema_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php');
$plugin_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert('Repositorio no depende de config de capabilities', strpos($repository_src, 'CanonicalCapabilityConfigRepository') === false);
ac_assert('Repositorio persiste tabla específica', strpos($repository_src, 'contact_dossier_applications_table_name') !== false);
ac_assert('Set delega política de transición', strpos($set_src, 'assert_transition_allowed') !== false);
ac_assert('Schema específico sin solution_key', strpos($schema_src, '$contact_dossier_applications_sql') !== false);
ac_assert('Loader registra infraestructura de solution', strpos($plugin_src, 'AA_Canonical_Solution_Registry_Bootstrap::bootstrap()') !== false);
ac_assert('Efecto capability conserva compatibilidad', is_subclass_of('CanonicalContainerCapabilityEffect', 'CanonicalContainerMutationEffect'));

if ($has_real_wp) {
    echo "=== MySQL aislado + Application ===\n";
    require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
    require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
    require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
    require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPort.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementResult.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
    require_once $plugin_root . '/includes/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
    require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-store.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
    require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerMutationContext.php';
    require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalRecordMutationContext.php';
    require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalRecordCapabilityEffect.php';
    require_once $plugin_root . '/includes/repositories/CanonicalRelationalQueryFailed.php';
    require_once $plugin_root . '/includes/repositories/CanonicalRelationalAmbiguousOutcome.php';
    require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
    require_once $plugin_root . '/includes/application/canonical/solutions/CanonicalSolutionSchemaNotReady.php';
    require_once $plugin_root . '/includes/application/canonical/solutions/CanonicalSolutionPersistenceFailed.php';
    require_once $plugin_root . '/includes/repositories/CanonicalContactDossierApplicationRepository.php';
    require_once $plugin_root . '/includes/application/canonical/solutions/contact_dossier/ReadContactDossierApplicationUseCase.php';
    require_once $plugin_root . '/includes/application/canonical/solutions/contact_dossier/SetContactDossierApplicationUseCase.php';

    global $wpdb;
    $prior_prefix = $wpdb->prefix;
    $temp_prefix = 'tmp_sol_' . substr(md5((string) microtime(true)), 0, 8) . '_';
    $cleanup = static function () use ($wpdb, $temp_prefix): void {
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($temp_prefix) . '%'));
        foreach ((array) $tables as $table) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $table) . '`');
        }
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
    };

    try {
        $wpdb->prefix = $temp_prefix;
        AA_Canonical_Schema::install();
        $now = '2026-09-20 12:00:00';
        $families_table = AA_Canonical_Schema::families_table_name();
        foreach (['contact', 'archive'] as $family_key) {
            $wpdb->insert($families_table, [
                'family_key' => $family_key,
                'is_enabled' => 1,
                'seed_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $contact_family_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM `{$families_table}` WHERE family_key = %s", 'contact')
        );
        $containers_table = AA_Canonical_Schema::containers_table_name();
        $wpdb->insert($containers_table, [
            'public_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            'family_id' => $contact_family_id,
            'title' => 'Contactos C2',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $container_id = (int) $wpdb->insert_id;

        $ready_registry = new AA_Canonical_Solution_Registry();
        $ready_registry->register($ready_definition)->freeze();
        $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
        $relational = new CanonicalRelationalRepository($wpdb);
        $applications = new CanonicalContactDossierApplicationRepository($wpdb);
        $enablement_store = new AA_Canonical_Family_Enablement_Store($wpdb);
        $reader = new ReadContactDossierApplicationUseCase(
            $relational,
            $applications,
            $ready_registry,
            $family_registry,
            $enablement_store,
            $policy
        );
        $setter = new SetContactDossierApplicationUseCase($reader, $applications, $policy);

        $activated = $setter->execute(new SetContactDossierApplicationCommand($container_id, true));
        ac_assert('MySQL: activación persiste fuera de capabilities', $activated->is_active() && $activated->is_available());

        $wpdb->update($families_table, ['is_enabled' => 0], ['family_key' => 'archive'], ['%d'], ['%s']);
        $deactivated = $setter->execute(new SetContactDossierApplicationCommand($container_id, false));
        ac_assert('MySQL: desactivación funciona tras deshabilitar Archivo', !$deactivated->is_active() && !$deactivated->is_available());

        $activation_blocked = false;
        try {
            $setter->execute(new SetContactDossierApplicationCommand($container_id, true));
        } catch (CanonicalSolutionApplicationRejected $e) {
            $activation_blocked = $e->reason_code() === 'required_family_disabled:archive';
        }
        ac_assert('MySQL: reactivación fail-closed sin Archivo', $activation_blocked);

        $wpdb->delete($containers_table, ['id' => $container_id], ['%d']);
        ac_assert('MySQL: CASCADE elimina application con la lista', $applications->find($container_id) === null);
    } finally {
        $cleanup();
        $wpdb->prefix = $prior_prefix;
    }
} else {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
