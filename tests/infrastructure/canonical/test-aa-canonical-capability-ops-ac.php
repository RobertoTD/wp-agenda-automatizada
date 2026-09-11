<?php
/**
 * AC Test — Autorización e integración de Capability Ops (C1a).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-capability-ops-ac.php
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

echo "=== 1. Carga de clases / contención ===\n";

$files = [
    'domain/canonical/class-aa-canonical-capability-definition.php',
    'domain/canonical/class-aa-canonical-capability-registry.php',
    'infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php',
    'infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php',
    'infrastructure/canonical/capabilities/class-aa-canonical-capability-ops.php',
    'repositories/CanonicalCapabilityConfigRepository.php',
    'application/canonical/capabilities/SetFamilyCapabilityDefaultUseCase.php',
    'application/canonical/capabilities/SetContainerCapabilityActivationUseCase.php',
    'application/canonical/capabilities/ReadContainerCapabilityConfigUseCase.php',
];

foreach ($files as $rel) {
    $path = $plugin_root . '/includes/' . $rel;
    ac_assert('Existe ' . $rel, is_readable($path));
}

$main = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert('Main carga capability definition', strpos($main, 'class-aa-canonical-capability-definition.php') !== false);
ac_assert('Main bootstrap capability registry', strpos($main, 'AA_Canonical_Capability_Registry_Bootstrap::bootstrap()') !== false);
ac_assert('Main registra defaults lifecycle', strpos($main, 'AA_Canonical_Capability_Defaults_Lifecycle::register') !== false);
ac_assert('Main no registra AJAX de capabilities', stripos($main, 'CapabilityAjax') === false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL/auth real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyUnknown.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityUnknown.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilitySchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityUnauthorized.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilityConfigSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/SetFamilyCapabilityDefaultCommand.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/SetContainerCapabilityActivationCommand.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/SetFamilyCapabilityDefaultUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/SetContainerCapabilityActivationUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/ReadContainerCapabilityConfigUseCase.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-capability-ops.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_capops_' . substr(md5(uniqid('op', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_capops_') !== 0) {
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

$prev_user = get_current_user_id();

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);

    echo "\n=== 2. Auth + pertenencia ===\n";

    AA_Canonical_Schema::install();
    AA_Canonical_Core_Bootstrap::bootstrap();
    AA_Canonical_Capability_Registry_Bootstrap::reset_for_tests();
    AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);

    $repo = new CanonicalCapabilityConfigRepository($wpdb);
    $ops = new AA_Canonical_Capability_Ops(
        $repo,
        $family_registry,
        AA_Canonical_Capability_Registry_Bootstrap::instance()
    );

    wp_set_current_user(0);
    $unauth = false;
    try {
        $ops->set_family_default('finance', 'amount', false);
    } catch (CanonicalCapabilityUnauthorized $e) {
        $unauth = ($e->error_code() === 'unauthorized');
    }
    ac_assert('Sin usuario → unauthorized', $unauth);

    $admin_id = 0;
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    if (is_array($admins) && isset($admins[0])) {
        $admin_id = (int) $admins[0];
    }
    if ($admin_id < 1) {
        ac_assert('Hay administrador en la instalación de prueba', false);
    } else {
        wp_set_current_user($admin_id);
        ac_assert('Usuario admin autenticado', is_user_logged_in() && current_user_can('manage_options'));

        $enabled = $ops->set_family_default('finance', 'amount', true);
        ac_assert('Admin + enable amount ready', is_array($enabled) && !empty($enabled['is_enabled']));

        $not_ready_registry = (new AA_Canonical_Capability_Registry())
            ->register(new AA_Canonical_Capability_Definition('amount', AA_Canonical_Capability_Definition::SCOPE_RECORD, false))
            ->freeze();
        $ops_nr = new AA_Canonical_Capability_Ops($repo, $family_registry, $not_ready_registry);
        $not_ready = false;
        try {
            $ops_nr->set_family_default('finance', 'amount', true);
        } catch (CanonicalCapabilityNotReady $e) {
            $not_ready = ($e->error_code() === 'capability_not_ready');
        }
        ac_assert('Fixture !ready → capability_not_ready', $not_ready);

        $finance_id = $repo->resolve_family_id('finance');
        $now = gmdate('Y-m-d H:i:s');
        $c_table = AA_Canonical_Schema::containers_table_name();
        $wpdb->insert($c_table, [
            'public_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'family_id' => (int) $finance_id,
            'title' => 'Lista ops',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%d', '%s', null, '%s', '%s']);
        $container_id = (int) $wpdb->insert_id;

        $snap = $ops->read_container_config('finance', $container_id);
        ac_assert(
            'Consulta config con usuario autorizado',
            $snap->family_key() === 'finance'
            && $snap->container_id() === $container_id
            && array_key_exists('amount', $snap->capabilities())
            && $snap->is_assigned('amount') === false
        );

        $activated = $ops->set_container_activation('finance', $container_id, 'amount', true);
        ac_assert('Activación lista amount ready', is_array($activated) && !empty($activated['is_active']));

        $activate_fail = false;
        try {
            $ops_nr->set_container_activation('finance', $container_id, 'amount', true);
        } catch (CanonicalCapabilityNotReady $e) {
            $activate_fail = true;
        }
        ac_assert('Fixture !ready → activación lista rechazada', $activate_fail);

        $wrong_container = false;
        try {
            $ops->read_container_config('finance', 999999);
        } catch (CanonicalContainerNotFound $e) {
            $wrong_container = true;
        }
        ac_assert('Pertenencia: container inexistente → not found', $wrong_container);

        // Schema ausente
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        $wpdb->query('DROP TABLE IF EXISTS `' . AA_Canonical_Schema::container_capabilities_table_name() . '`');
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        $schema_fail = false;
        try {
            $ops->read_container_config('finance', $container_id);
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            $schema_fail = ($e->error_code() === 'capability_schema_not_ready');
        }
        ac_assert('Schema incompleto → capability_schema_not_ready', $schema_fail);
    }

} catch (\Throwable $e) {
    ac_assert('Excepción inesperada: ' . $e->getMessage(), false);
} finally {
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
    if ($prev_user > 0) {
        wp_set_current_user($prev_user);
    } else {
        wp_set_current_user(0);
    }
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
