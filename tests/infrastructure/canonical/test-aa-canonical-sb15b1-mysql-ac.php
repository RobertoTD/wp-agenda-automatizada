<?php
/**
 * AC Test — SB1-5B1 MySQL: create container universal (tmp_*).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-sb15b1-mysql-ac.php
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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalCreateContainerAjax.php');
ac_assert('Ajax sin aa_finance_', strpos($ajax_src, 'aa_finance_') === false);
ac_assert('Ajax sin aa_expediente_', strpos($ajax_src, 'aa_expediente_') === false);

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
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyUnknown.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPort.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-store.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_sb15b1_' . substr(md5(uniqid('s5', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_sb15b1_') !== 0) {
        return;
    }
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_records`');
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_containers`');
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_families`');
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_finance_records`');
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_finance_containers`');
};

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);
    AA_Canonical_Schema::install();

    $registry = AA_Canonical_Core_Bootstrap::bootstrap();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);

    $families = $temp_prefix . 'aa_canonical_families';
    $containers = $temp_prefix . 'aa_canonical_containers';
    $records = $temp_prefix . 'aa_canonical_records';

    $wpdb->update($families, ['is_enabled' => 1], ['family_key' => 'finance']);
    $wpdb->update($families, ['is_enabled' => 1], ['family_key' => 'archive']);

    $finance_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$families}` WHERE family_key = %s",
        'finance'
    ));
    $archive_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$families}` WHERE family_key = %s",
        'archive'
    ));

    // Legacy tables present but must stay untouched by create.
    $wpdb->query(
        "CREATE TABLE `{$temp_prefix}aa_finance_containers` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            PRIMARY KEY (id)
        ) {$wpdb->get_charset_collate()}"
    );
    $wpdb->insert($temp_prefix . 'aa_finance_containers', ['title' => 'legacy-seed']);
    $legacy_count_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$temp_prefix}aa_finance_containers`");

    $create_for = static function (string $family_key, string $title, ?string $details) use ($registry): CanonicalShellMutationResult {
        $family = $registry->family($family_key);
        $variant = null;
        $identity = new CanonicalReadIdentity($family_key);
        $manifest = new CanonicalShellManifest($identity, $family);
        $write_registry = new AA_Canonical_Write_Binding_Registry();
        AA_Canonical_Write_Binding_Bootstrap::register_productive($write_registry);
        $uc = new WriteCanonicalShellContainerUseCase(new CanonicalWriteGateway($write_registry));
        return $uc->create($manifest, new CanonicalCreateContainerCommand($title, $details));
    };

    $fin = $create_for('finance', 'Lista Finance SB1', '');
    ac_assert('Finance create confirmed', $fin->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $fin_id = (int) $fin->receipt()->resource_id();
    ac_assert('Finance resource_id >= 1', $fin_id >= 1);

    $arch = $create_for('archive', 'Lista Archive SB1', "nota\nsegunda");
    ac_assert('Archive create confirmed', $arch->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $fin_row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$containers}` WHERE id = %d",
        $fin_id
    ), ARRAY_A);
    ac_assert('Finance family_id', (int) ($fin_row['family_id'] ?? 0) === $finance_id);
    ac_assert('Finance row sin variant_key', !array_key_exists('variant_key', $fin_row));
    ac_assert(
        'public_id UUID-ish',
        is_string($fin_row['public_id'] ?? null)
        && (bool) preg_match('/^[0-9a-f-]{36}$/i', (string) $fin_row['public_id'])
    );
    ac_assert('details vacío → null SQL', $fin_row['details'] === null);
    ac_assert(
        'timestamps UTC Z-ish',
        preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($fin_row['created_at'] ?? '')) === 1
        && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($fin_row['updated_at'] ?? '')) === 1
    );

    $arch_row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$containers}` WHERE id = %d",
        (int) $arch->receipt()->resource_id()
    ), ARRAY_A);
    ac_assert('Archive family_id', (int) ($arch_row['family_id'] ?? 0) === $archive_id);
    ac_assert('Archive details conservados', ($arch_row['details'] ?? '') === "nota\nsegunda");

    $records_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$records}`");
    ac_assert('Cero records', $records_count === 0);

    $legacy_count_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$temp_prefix}aa_finance_containers`");
    ac_assert('Legacy finance intacto', $legacy_count_after === $legacy_count_before);

    // Newer finance container should appear first (id DESC if same second).
    $fin2 = $create_for('finance', 'Lista más reciente', null);
    ac_assert('Second finance confirmed', $fin2->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $read_registry = new AA_Canonical_Read_Binding_Registry();
    AA_Canonical_Read_Binding_Bootstrap::register_productive($read_registry);
    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('finance'),
        $registry->family('finance'),
        null
    );
    $page = (new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($read_registry)))
        ->execute($manifest, 1);
    $items = $page->page() !== null ? $page->page()->items() : [];
    $first_title = isset($items[0]) ? $items[0]->title() : '';
    ac_assert('Nuevo aparece primero en página 1', $first_title === 'Lista más reciente');

    $same_chain = strpos(
        (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php'),
        'AA_Canonical_Relational_Write_Adapter'
    ) !== false;
    ac_assert('Misma cadena universal write', $same_chain);
} finally {
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
