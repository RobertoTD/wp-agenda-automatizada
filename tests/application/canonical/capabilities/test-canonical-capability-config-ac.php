<?php
/**
 * AC Test — Configuración de capacidades canónicas (C1a).
 *
 * Distingue: lifecycle insert-if-missing (no sobrescribe) vs Use Case upsert (modifica).
 * amount is_ready=false → rechazo de habilitación; consulta/desactivación permitidas.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-capability-config-ac.php
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

$ops_src = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-capability-ops.php'
);
$repo_src = (string) file_get_contents(
    $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php'
);
$boot_src = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
$life_src = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php'
);

ac_assert('Ops no menciona FinanceSchema/aa_finance', stripos($ops_src, 'aa_finance') === false && stripos($ops_src, 'FinanceSchema') === false);
ac_assert('Repo config no menciona finance', stripos($repo_src, 'finance') === false);
ac_assert('Bootstrap registra amount is_ready false', strpos($boot_src, "'amount'") !== false && preg_match("/new AA_Canonical_Capability_Definition\(\s*'amount'\s*,\s*AA_Canonical_Capability_Definition::SCOPE_RECORD\s*,\s*false\s*\)/", $boot_src) === 1);
ac_assert('Lifecycle usa insert_family_default_if_missing', strpos($life_src, 'insert_family_default_if_missing') !== false);
ac_assert('Lifecycle filtra !is_ready', strpos($life_src, 'is_ready()') !== false);
ac_assert('Ops sin AJAX/Settings', stripos($ops_src, 'wp_ajax') === false && stripos($ops_src, 'options.php') === false);
ac_assert('Ops sin bypass wp-config', stripos($ops_src, 'wp-config') === false && stripos($ops_src, 'AA_CAPABILITY') === false);

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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyUnknown.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityUnknown.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilitySchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilityConfigSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/SetFamilyCapabilityDefaultCommand.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/SetContainerCapabilityActivationCommand.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/SetFamilyCapabilityDefaultUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/SetContainerCapabilityActivationUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/ReadContainerCapabilityConfigUseCase.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_capcfg_' . substr(md5(uniqid('cf', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_capcfg_') !== 0) {
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

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);

    echo "\n=== 2. Configuración MySQL ===\n";

    AA_Canonical_Schema::install();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);
    AA_Canonical_Capability_Registry_Bootstrap::reset_for_tests();
    $capability_registry = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();

    $repo = new CanonicalCapabilityConfigRepository($wpdb);
    $finance_id = $repo->resolve_family_id('finance');
    ac_assert('Familia finance provisionada', $finance_id !== null && $finance_id >= 1);

    $amount = $capability_registry->get('amount');
    ac_assert('amount registrado', $amount->key() === 'amount');
    ac_assert('amount no ready', $amount->is_ready() === false);

    $set_default = new SetFamilyCapabilityDefaultUseCase($repo, $family_registry, $capability_registry);
    $set_container = new SetContainerCapabilityActivationUseCase($repo, $family_registry, $capability_registry);
    $read_container = new ReadContainerCapabilityConfigUseCase($repo, $family_registry, $capability_registry);

    $not_ready = false;
    try {
        $set_default->execute(new SetFamilyCapabilityDefaultCommand('finance', 'amount', true));
    } catch (CanonicalCapabilityNotReady $e) {
        $not_ready = ($e->error_code() === 'capability_not_ready');
    }
    ac_assert('Habilitar default amount → capability_not_ready', $not_ready);

    // Lifecycle insert-if-missing no aplica a amount (no ready); simular seed ready en fixture.
    $probe_registry = new AA_Canonical_Capability_Registry();
    $probe_registry->register(new AA_Canonical_Capability_Definition('amount', 'record', false));
    $probe_registry->register(new AA_Canonical_Capability_Definition('probe', 'record', true));
    $probe_registry->freeze();

    $inserted = $repo->insert_family_default_if_missing((int) $finance_id, 'probe', true);
    ac_assert('Lifecycle-style: insert probe enabled', $inserted === true);
    $row = $repo->find_family_default((int) $finance_id, 'probe');
    ac_assert('probe default is_enabled=1', is_array($row) && $row['is_enabled'] === true);

    $repo->upsert_family_default((int) $finance_id, 'probe', false);
    $row2 = $repo->find_family_default((int) $finance_id, 'probe');
    ac_assert('UC-style: upsert puede desactivar default existente', is_array($row2) && $row2['is_enabled'] === false);

    $skipped = $repo->insert_family_default_if_missing((int) $finance_id, 'probe', true);
    $row3 = $repo->find_family_default((int) $finance_id, 'probe');
    ac_assert('Lifecycle-style: no sobrescribe default guardado', $skipped === false && is_array($row3) && $row3['is_enabled'] === false);

    $set_probe = new SetFamilyCapabilityDefaultUseCase($repo, $family_registry, $probe_registry);
    $updated = $set_probe->execute(new SetFamilyCapabilityDefaultCommand('finance', 'probe', true));
    ac_assert('UC explícito re-habilita probe ready', $updated['is_enabled'] === true);

    // Contenedor + amount not ready
    $now = gmdate('Y-m-d H:i:s');
    $c_table = AA_Canonical_Schema::containers_table_name();
    $wpdb->insert($c_table, [
        'public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'family_id' => (int) $finance_id,
        'title' => 'Lista prueba',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', null, '%s', '%s']);
    $container_id = (int) $wpdb->insert_id;

    $activate_fail = false;
    try {
        $set_container->execute(
            new SetContainerCapabilityActivationCommand('finance', $container_id, 'amount', true)
        );
    } catch (CanonicalCapabilityNotReady $e) {
        $activate_fail = true;
    }
    ac_assert('Activar amount en lista → capability_not_ready', $activate_fail);

    // Fixture: fila existente desactivada para amount (simula config previa)
    $repo->upsert_container_capability($container_id, 'amount', false);
    $snap = $read_container->execute('finance', $container_id);
    ac_assert('Consulta permite amount no ready', $snap->is_assigned('amount') && !$snap->is_active('amount'));

    $deactivated = $set_container->execute(
        new SetContainerCapabilityActivationCommand('finance', $container_id, 'amount', false)
    );
    ac_assert('Desactivar amount no ready permitido', $deactivated['is_active'] === false);

    // Lifecycle declared seeds + filter
    $seeds = AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds();
    $has_amount_seed = false;
    foreach ($seeds as $seed) {
        if ($seed['capability_key'] === 'amount' && $seed['family_key'] === 'finance') {
            $has_amount_seed = true;
        }
    }
    ac_assert('Lifecycle declara seed finance/amount (pendiente de ready)', $has_amount_seed);

    $before_amount_default = $repo->find_family_default((int) $finance_id, 'amount');
    foreach ($seeds as $seed) {
        if (!$capability_registry->has($seed['capability_key'])) {
            continue;
        }
        $def = $capability_registry->get($seed['capability_key']);
        if (!$def->is_ready()) {
            continue;
        }
        $fid = $repo->resolve_family_id($seed['family_key']);
        if ($fid === null) {
            continue;
        }
        $repo->insert_family_default_if_missing($fid, $seed['capability_key'], $seed['is_enabled']);
    }
    $after_amount_default = $repo->find_family_default((int) $finance_id, 'amount');
    ac_assert(
        'Lifecycle no inserta amount mientras !ready',
        $before_amount_default === null && $after_amount_default === null
    );

} catch (\Throwable $e) {
    ac_assert('Excepción inesperada: ' . $e->getMessage(), false);
} finally {
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
