<?php
/**
 * AC Test — Configuración de capacidades canónicas (C1a).
 *
 * Distingue: lifecycle insert-if-missing (no sobrescribe) vs Use Case upsert (modifica).
 * amount is_ready=true (A1b). Cobertura not-ready vía fixture. Lifecycle insert-if-missing no sobrescribe.
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
ac_assert('Bootstrap registra amount is_ready true', strpos($boot_src, "'amount'") !== false && preg_match("/new AA_Canonical_Capability_Definition\(\s*'amount'\s*,\s*AA_Canonical_Capability_Definition::SCOPE_RECORD\s*,\s*true\s*\)/", $boot_src) === 1);
ac_assert('Bootstrap registra images is_ready false', preg_match("/new AA_Canonical_Capability_Definition\(\s*'images'\s*,\s*AA_Canonical_Capability_Definition::SCOPE_RECORD\s*,\s*false\s*\)/", $boot_src) === 1);
ac_assert('Lifecycle DEFAULTS_VERSION=2', strpos($life_src, 'DEFAULTS_VERSION = 2') !== false);
ac_assert('Lifecycle usa insert_family_capability_if_missing', strpos($life_src, 'insert_family_capability_if_missing') !== false);
ac_assert('Lifecycle filtra !is_ready', strpos($life_src, 'is_ready()') !== false);
ac_assert('Ops sin AJAX/Settings', stripos($ops_src, 'wp_ajax') === false && stripos($ops_src, 'options.php') === false);
ac_assert('Ops sin bypass wp-config', stripos($ops_src, 'wp-config') === false && stripos($ops_src, 'AA_CAPABILITY') === false);

$index_src = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);
ac_assert('Shell label Imágenes para images', strpos($index_src, "'Imágenes'") !== false);
ac_assert('Shell label Importe para amount', strpos($index_src, "'Importe'") !== false);
ac_assert('Lifecycle declara capability_key images', strpos($life_src, "'capability_key' => 'images'") !== false);
ac_assert('Lifecycle conserva capability_key amount', strpos($life_src, "'capability_key' => 'amount'") !== false);

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
    ac_assert('amount ready en producto', $amount->is_ready() === true);

    $images = $capability_registry->get('images');
    ac_assert('images registrado', $images->key() === 'images');
    ac_assert('images scope record', $images->scope() === AA_Canonical_Capability_Definition::SCOPE_RECORD);
    ac_assert('images not ready en Ciclo 1', $images->is_ready() === false);

    $declared = AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds();
    $images_defaults = [];
    $amount_seed_ok = false;
    foreach ($declared as $seed) {
        if (($seed['capability_key'] ?? '') === 'amount' && ($seed['family_key'] ?? '') === 'finance') {
            $amount_seed_ok = !empty($seed['is_default']);
        }
        if (($seed['capability_key'] ?? '') === 'images') {
            $images_defaults[(string) $seed['family_key']] = !empty($seed['is_default']);
        }
    }
    ac_assert('Lifecycle conserva seed finance/amount default on', $amount_seed_ok);
    ac_assert(
        'Lifecycle declara exactamente 4 seeds images',
        count($images_defaults) === 4
        && isset($images_defaults['archive'], $images_defaults['finance'], $images_defaults['catalog'], $images_defaults['contact'])
    );
    ac_assert('images archive is_default=1', $images_defaults['archive'] === true);
    ac_assert('images finance is_default=0', $images_defaults['finance'] === false);
    ac_assert('images catalog is_default=0', $images_defaults['catalog'] === false);
    ac_assert('images contact is_default=0', $images_defaults['contact'] === false);

    $set_default = new SetFamilyCapabilityDefaultUseCase($repo, $family_registry, $capability_registry);
    $set_container = new SetContainerCapabilityActivationUseCase($repo, $family_registry, $capability_registry);
    $read_container = new ReadContainerCapabilityConfigUseCase($repo, $family_registry, $capability_registry);

    $enabled_default = $set_default->execute(new SetFamilyCapabilityDefaultCommand('finance', 'amount', true));
    ac_assert('Habilitar default amount ready', is_array($enabled_default) && $enabled_default['is_default'] === true);

    $images_default_blocked = false;
    try {
        $set_default->execute(new SetFamilyCapabilityDefaultCommand('finance', 'images', true));
    } catch (CanonicalCapabilityNotReady $e) {
        $images_default_blocked = ($e->error_code() === 'capability_not_ready');
    }
    ac_assert('images !ready → capability_not_ready al set default', $images_default_blocked);

    $images_seed = $repo->find_family_capability((int) $finance_id, 'images');
    ac_assert('Producto not-ready: ensure no inserta seed images en finance', $images_seed === null);

    // Con catálogo ready aislado, las declaraciones images sí pueden insertarse.
    $images_ready_registry = new AA_Canonical_Capability_Registry();
    $images_ready_registry->register(new AA_Canonical_Capability_Definition('amount', 'record', true));
    $images_ready_registry->register(new AA_Canonical_Capability_Definition('images', 'record', true));
    $images_ready_registry->freeze();
    foreach (AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds() as $seed) {
        if (($seed['capability_key'] ?? '') !== 'images') {
            continue;
        }
        if (!$images_ready_registry->has('images') || !$images_ready_registry->get('images')->is_ready()) {
            continue;
        }
        $fid = $repo->resolve_family_id($seed['family_key']);
        if ($fid === null) {
            continue;
        }
        $repo->insert_family_capability_if_missing($fid, 'images', (bool) $seed['is_default']);
    }
    $archive_id = $repo->resolve_family_id('archive');
    $catalog_id = $repo->resolve_family_id('catalog');
    $contact_id = $repo->resolve_family_id('contact');
    $img_arch = $archive_id !== null ? $repo->find_family_capability((int) $archive_id, 'images') : null;
    $img_fin = $repo->find_family_capability((int) $finance_id, 'images');
    $img_cat = $catalog_id !== null ? $repo->find_family_capability((int) $catalog_id, 'images') : null;
    $img_con = $contact_id !== null ? $repo->find_family_capability((int) $contact_id, 'images') : null;
    ac_assert('Stub ready: archive/images default on', is_array($img_arch) && $img_arch['is_default'] === true);
    ac_assert('Stub ready: finance/images default off', is_array($img_fin) && $img_fin['is_default'] === false);
    ac_assert('Stub ready: catalog/images default off', is_array($img_cat) && $img_cat['is_default'] === false);
    ac_assert('Stub ready: contact/images default off', is_array($img_con) && $img_con['is_default'] === false);

    $repo->upsert_family_capability((int) $finance_id, 'images', true);
    $skipped_img = $repo->insert_family_capability_if_missing((int) $finance_id, 'images', false);
    $img_fin_guard = $repo->find_family_capability((int) $finance_id, 'images');
    ac_assert(
        'Stub ready: insert-if-missing no sobrescribe images guardado',
        $skipped_img === false && is_array($img_fin_guard) && $img_fin_guard['is_default'] === true
    );
    // Restaurar default normativo de finance/images para asserts posteriores.
    $repo->upsert_family_capability((int) $finance_id, 'images', false);

    // Fixture not-ready conserva cobertura de rechazo.
    $not_ready_registry = new AA_Canonical_Capability_Registry();
    $not_ready_registry->register(new AA_Canonical_Capability_Definition('amount', 'record', false));
    $not_ready_registry->freeze();
    $set_default_nr = new SetFamilyCapabilityDefaultUseCase($repo, $family_registry, $not_ready_registry);
    $not_ready = false;
    try {
        $set_default_nr->execute(new SetFamilyCapabilityDefaultCommand('finance', 'amount', true));
    } catch (CanonicalCapabilityNotReady $e) {
        $not_ready = ($e->error_code() === 'capability_not_ready');
    }
    ac_assert('Fixture !ready → capability_not_ready al habilitar', $not_ready);

    // Lifecycle insert-if-missing vs UC upsert.
    $probe_registry = new AA_Canonical_Capability_Registry();
    $probe_registry->register(new AA_Canonical_Capability_Definition('amount', 'record', true));
    $probe_registry->register(new AA_Canonical_Capability_Definition('probe', 'record', true));
    $probe_registry->freeze();

    $inserted = $repo->insert_family_capability_if_missing((int) $finance_id, 'probe', true);
    ac_assert('Lifecycle-style: insert probe enabled', $inserted === true);
    $row = $repo->find_family_capability((int) $finance_id, 'probe');
    ac_assert('probe default is_default=1', is_array($row) && $row['is_default'] === true);

    $repo->upsert_family_capability((int) $finance_id, 'probe', false);
    $row2 = $repo->find_family_capability((int) $finance_id, 'probe');
    ac_assert('UC-style: upsert puede desactivar default existente', is_array($row2) && $row2['is_default'] === false);

    $skipped = $repo->insert_family_capability_if_missing((int) $finance_id, 'probe', true);
    $row3 = $repo->find_family_capability((int) $finance_id, 'probe');
    ac_assert('Lifecycle-style: no sobrescribe default guardado', $skipped === false && is_array($row3) && $row3['is_default'] === false);

    $set_probe = new SetFamilyCapabilityDefaultUseCase($repo, $family_registry, $probe_registry);
    $updated = $set_probe->execute(new SetFamilyCapabilityDefaultCommand('finance', 'probe', true));
    ac_assert('UC explícito re-habilita probe ready', $updated['is_default'] === true);

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

    $activated = $set_container->execute(
        new SetContainerCapabilityActivationCommand('finance', $container_id, 'amount', true)
    );
    ac_assert('Activar amount ready en lista', is_array($activated) && $activated['is_active'] === true);

    $set_container_nr = new SetContainerCapabilityActivationUseCase($repo, $family_registry, $not_ready_registry);
    $activate_fail = false;
    try {
        $set_container_nr->execute(
            new SetContainerCapabilityActivationCommand('finance', $container_id, 'amount', true)
        );
    } catch (CanonicalCapabilityNotReady $e) {
        $activate_fail = true;
    }
    ac_assert('Fixture !ready → activar lista rechazado', $activate_fail);

    $deactivated = $set_container->execute(
        new SetContainerCapabilityActivationCommand('finance', $container_id, 'amount', false)
    );
    ac_assert('Desactivar amount permitido', $deactivated['is_active'] === false);
    $snap = $read_container->execute('finance', $container_id);
    ac_assert('Consulta asignada inactiva', $snap->is_assigned('amount') && !$snap->is_active('amount'));

    $seeds = AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds();
    $has_amount_seed = false;
    foreach ($seeds as $seed) {
        if ($seed['capability_key'] === 'amount' && $seed['family_key'] === 'finance') {
            $has_amount_seed = true;
        }
    }
    ac_assert('Lifecycle declara seed finance/amount', $has_amount_seed);

    $repo->upsert_family_capability((int) $finance_id, 'amount', false);
    $before_guard = $repo->find_family_capability((int) $finance_id, 'amount');
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
        $repo->insert_family_capability_if_missing($fid, $seed['capability_key'], $seed['is_default']);
    }
    $after_guard = $repo->find_family_capability((int) $finance_id, 'amount');
    ac_assert(
        'Lifecycle no sobrescribe amount guardado',
        is_array($before_guard) && $before_guard['is_default'] === false
        && is_array($after_guard) && $after_guard['is_default'] === false
    );

    // Ausencia: inserta si falta.
    $wpdb->delete(
        AA_Canonical_Schema::family_capabilities_table_name(),
        ['family_id' => (int) $finance_id, 'capability_key' => 'amount'],
        ['%d', '%s']
    );
    $missing = $repo->find_family_capability((int) $finance_id, 'amount');
    ac_assert('Fila amount ausente para re-seed', $missing === null);
    foreach ($seeds as $seed) {
        if ($seed['capability_key'] !== 'amount') {
            continue;
        }
        $fid = $repo->resolve_family_id($seed['family_key']);
        if ($fid === null) {
            continue;
        }
        $repo->insert_family_capability_if_missing($fid, $seed['capability_key'], $seed['is_default']);
    }
    $reseeded = $repo->find_family_capability((int) $finance_id, 'amount');
    ac_assert(
        'Lifecycle inserta amount solo si falta',
        is_array($reseeded) && $reseeded['is_default'] === true
    );

} catch (\Throwable $e) {
    ac_assert('Excepción inesperada: ' . $e->getMessage(), false);
} finally {
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
