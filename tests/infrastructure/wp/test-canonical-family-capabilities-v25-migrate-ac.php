<?php
/**
 * AC DB 25 — migración repertorio family_capabilities + atomicidad de selección.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/wp/test-canonical-family-capabilities-v25-migrate-ac.php
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

$schema_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/Schema.php');
$canonical_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php');

ac_assert("DB_VERSION = '38'", strpos($schema_src, "DB_VERSION = '38'") !== false);
ac_assert(
    'Schema bumpea aa_db_version tras CanonicalSchema::install',
    preg_match(
        '/AA_Canonical_Schema::install\(\);[\s\S]{0,400}?update_option\(\'aa_db_version\'/',
        $schema_src
    ) === 1
);
ac_assert(
    'Canonical install termina en verify()',
    preg_match('/ensure_family_capabilities_v25\(\);[\s\S]*self::verify\(\);/', $canonical_src) === 1
);
ac_assert(
    'ensure v25 falla si legacy permanece',
    strpos($canonical_src, 'Tabla legacy de repertorio sigue presente tras v25') !== false
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
require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
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
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilitySelection.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_v25m_' . substr(md5((string) microtime(true)), 0, 8) . '_';
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

$create_legacy_defaults = static function (string $table, string $charset) use ($wpdb): void {
    $wpdb->query("CREATE TABLE `{$table}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        family_id bigint(20) unsigned NOT NULL,
        capability_key varchar(64) NOT NULL,
        is_enabled tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_family_capability (family_id, capability_key),
        KEY idx_capability_key (capability_key)
    ) ENGINE=InnoDB {$charset}");
};

echo "=== Migración repertorio v25 ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    $charset = $wpdb->get_charset_collate();
    $now = gmdate('Y-m-d H:i:s');

    // --- Fresh install ---
    AA_Canonical_Schema::install();
    AA_Canonical_Schema::verify();
    $new = AA_Canonical_Schema::family_capabilities_table_name();
    $old = $wpdb->prefix . 'aa_canonical_family_capability_defaults';
    ac_assert(
        'Fresh: solo tabla nueva existe',
        $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new)) === $new
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)) !== $old
    );
    $cols = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$new}`", ARRAY_A);
    $by = [];
    foreach ((array) $cols as $col) {
        $by[$col['Field']] = $col;
    }
    ac_assert(
        'Fresh: is_default presente, is_enabled ausente',
        isset($by['is_default']) && !isset($by['is_enabled'])
    );

    // --- Rename path: only legacy ---
    $cleanup();
    AA_Canonical_Schema::install();
    $families = AA_Canonical_Schema::families_table_name();
    $wpdb->insert($families, [
        'family_key' => 'finance',
        'is_enabled' => 1,
        'seed_version' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%d', '%s', '%s']);
    $family_id = (int) $wpdb->insert_id;

    $new = AA_Canonical_Schema::family_capabilities_table_name();
    $old = $wpdb->prefix . 'aa_canonical_family_capability_defaults';
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $new) . '`');
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
    $create_legacy_defaults($old, $charset);
    $wpdb->insert($old, [
        'family_id' => $family_id,
        'capability_key' => 'amount',
        'is_enabled' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s']);
    $wpdb->insert($old, [
        'family_id' => $family_id,
        'capability_key' => 'probe',
        'is_enabled' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s']);

    AA_Canonical_Schema::install();
    AA_Canonical_Schema::verify();
    ac_assert(
        'Rename: legacy ausente, nueva presente',
        $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)) !== $old
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new)) === $new
    );
    $amount_row = $wpdb->get_row($wpdb->prepare(
        "SELECT is_default FROM `{$new}` WHERE family_id = %d AND capability_key = %s",
        $family_id,
        'amount'
    ), ARRAY_A);
    $probe_row = $wpdb->get_row($wpdb->prepare(
        "SELECT is_default FROM `{$new}` WHERE family_id = %d AND capability_key = %s",
        $family_id,
        'probe'
    ), ARRAY_A);
    ac_assert(
        'Rename: is_enabled=1 → is_default=1',
        is_array($amount_row) && (int) $amount_row['is_default'] === 1
    );
    ac_assert(
        'Rename: is_enabled=0 no-default preservado',
        is_array($probe_row) && (int) $probe_row['is_default'] === 0
    );

    // --- Both-exist merge ---
    $cleanup();
    AA_Canonical_Schema::install();
    $families = AA_Canonical_Schema::families_table_name();
    $wpdb->insert($families, [
        'family_key' => 'finance',
        'is_enabled' => 1,
        'seed_version' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%d', '%s', '%s']);
    $family_id = (int) $wpdb->insert_id;
    $new = AA_Canonical_Schema::family_capabilities_table_name();
    $old = $wpdb->prefix . 'aa_canonical_family_capability_defaults';
    $wpdb->insert($new, [
        'family_id' => $family_id,
        'capability_key' => 'amount',
        'is_default' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s']);
    $create_legacy_defaults($old, $charset);
    $wpdb->insert($old, [
        'family_id' => $family_id,
        'capability_key' => 'amount',
        'is_enabled' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s']);
    $wpdb->insert($old, [
        'family_id' => $family_id,
        'capability_key' => 'ghost',
        'is_enabled' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s']);

    AA_Canonical_Schema::install();
    ac_assert(
        'Both-exist: legacy eliminada',
        $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)) !== $old
    );
    $ghost = $wpdb->get_row($wpdb->prepare(
        "SELECT is_default FROM `{$new}` WHERE family_id = %d AND capability_key = %s",
        $family_id,
        'ghost'
    ), ARRAY_A);
    ac_assert(
        'Both-exist: fila solo-legacy copiada (is_default=0)',
        is_array($ghost) && (int) $ghost['is_default'] === 0
    );

    // --- Failure: both-exist sin flag → no bump aa_db_version ---
    $cleanup();
    update_option('aa_db_version', '24');
    AA_Canonical_Schema::install();
    $new = AA_Canonical_Schema::family_capabilities_table_name();
    $old = $wpdb->prefix . 'aa_canonical_family_capability_defaults';
    $wpdb->query("CREATE TABLE `{$old}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        family_id bigint(20) unsigned NOT NULL,
        capability_key varchar(64) NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB {$charset}");
    $wpdb->insert($old, [
        'family_id' => 1,
        'capability_key' => 'amount',
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%s', '%s']);

    $threw = false;
    try {
        AA_Schema::install();
    } catch (\Throwable $e) {
        $threw = true;
    }
    $stored = (string) get_option('aa_db_version', '0');
    ac_assert('Fallo ensure v25 lanza', $threw);
    ac_assert('Fallo ensure: aa_db_version no bumpea (sigue 24)', $stored === '24');
    ac_assert(
        'Fallo ensure: legacy aún presente',
        $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)) === $old
    );

    // --- Atomicidad selección en TX de contenedor ---
    $cleanup();
    update_option('aa_db_version', '25');
    AA_Canonical_Schema::install();
    AA_Canonical_Core_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);

    $ready_registry = (new AA_Canonical_Capability_Registry())
        ->register(new AA_Canonical_Capability_Definition('amount', AA_Canonical_Capability_Definition::SCOPE_RECORD, true))
        ->freeze();
    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $family_id = (int) $config->resolve_family_id('finance');
    $config->upsert_family_capability($family_id, 'amount', true);

    $materializer = new AA_Canonical_Capability_Defaults_Materializer($ready_registry, $config);
    $selection_preparer = new CanonicalContainerCapabilitySelectionPreparer($config, $ready_registry);
    $relational = new CanonicalRelationalRepository($wpdb);
    $adapter = new AA_Canonical_Relational_Write_Adapter($relational);
    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $write_registry->register(new CanonicalReadIdentity('finance'), $adapter);
    $gateway = new CanonicalWriteGateway($write_registry);
    $uc = new WriteCanonicalShellContainerUseCase($gateway, $materializer, $selection_preparer);
    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('finance'),
        $family_registry->family('finance')
    );

    $empty_sel = CanonicalContainerCapabilitySelection::present([], []);
    $created_empty = $uc->create(
        $manifest,
        new CanonicalCreateContainerCommand('Lista vacía explícita', null),
        $empty_sel
    );
    ac_assert('Create explícito vacío → confirmed', $created_empty->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $empty_id = (int) $created_empty->receipt()->resource_id();
    ac_assert(
        'Create explícito vacío: sin container_capabilities (no materializer)',
        $config->list_container_capabilities($empty_id) === []
    );

    $created_def = $uc->create($manifest, new CanonicalCreateContainerCommand('Con defaults', null));
    $def_id = (int) $created_def->receipt()->resource_id();
    $def_caps = $config->list_container_capabilities($def_id);
    ac_assert('Omit create materializa amount', count($def_caps) === 1 && $def_caps[0]['capability_key'] === 'amount' && $def_caps[0]['is_active'] === true);

    $updated = $uc->update(
        $manifest,
        new CanonicalUpdateContainerCommand($def_id, 'Título + deactivate', null),
        CanonicalContainerCapabilitySelection::present(['amount'], [])
    );
    ac_assert('Update título+deactivate → confirmed', $updated->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $row = $relational->find_container($family_id, $def_id);
    $caps_after = $config->list_container_capabilities($def_id);
    ac_assert(
        'Misma TX: título actualizado y amount desactivado',
        is_array($row) && ($row['title'] ?? '') === 'Título + deactivate'
        && count($caps_after) === 1
        && $caps_after[0]['is_active'] === false
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
