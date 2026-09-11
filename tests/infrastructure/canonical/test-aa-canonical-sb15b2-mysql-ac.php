<?php
/**
 * AC Test — SB1-5B2 MySQL: create record universal (tmp_*).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-sb15b2-mysql-ac.php
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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalCreateRecordAjax.php');
ac_assert('Ajax sin aa_finance_', strpos($ajax_src, 'aa_finance_') === false);
ac_assert('Ajax sin aa_expediente_', strpos($ajax_src, 'aa_expediente_') === false);
ac_assert('Ajax usa WriteBag de capabilities', strpos($ajax_src, 'capability_write_bag_from_source') !== false);

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
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_sb15b2_' . substr(md5(uniqid('s52', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_sb15b2_') !== 0) {
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
    AA_Canonical_Schema::install();

    $registry = AA_Canonical_Core_Bootstrap::bootstrap();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);

    $families = $temp_prefix . 'aa_canonical_families';
    $containers = $temp_prefix . 'aa_canonical_containers';
    $records = $temp_prefix . 'aa_canonical_records';

    $wpdb->update($families, ['is_enabled' => 1], ['family_key' => 'finance']);
    $wpdb->update($families, ['is_enabled' => 1], ['family_key' => 'archive']);

    $wpdb->query(
        "CREATE TABLE `{$temp_prefix}aa_finance_containers` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            PRIMARY KEY (id)
        ) {$wpdb->get_charset_collate()}"
    );
    $wpdb->insert($temp_prefix . 'aa_finance_containers', ['title' => 'legacy-seed']);
    $legacy_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$temp_prefix}aa_finance_containers`");

    $write_stack = static function () {
        $write_registry = new AA_Canonical_Write_Binding_Registry();
        AA_Canonical_Write_Binding_Bootstrap::register_productive($write_registry);
        return new CanonicalWriteGateway($write_registry);
    };

    $create_container = static function (string $family_key, string $title) use ($registry, $write_stack): int {
        $manifest = new CanonicalShellManifest(
            new CanonicalReadIdentity($family_key),
            $registry->family($family_key),
            /* variant removed */ null
        );
        $result = (new WriteCanonicalShellContainerUseCase($write_stack()))
            ->create($manifest, new CanonicalCreateContainerCommand($title, null));
        return (int) $result->receipt()->resource_id();
    };

    $create_record = static function (string $family_key, int $container_id, string $title, ?string $details) use ($registry, $write_stack): CanonicalShellMutationResult {
        $manifest = new CanonicalShellManifest(
            new CanonicalReadIdentity($family_key),
            $registry->family($family_key),
            /* variant removed */ null
        );
        return (new WriteCanonicalShellRecordUseCase($write_stack()))
            ->create($manifest, new CanonicalCreateRecordCommand($container_id, $title, $details));
    };

    $fin_c1 = $create_container('finance', 'Lista Finance A');
    $fin_c2 = $create_container('finance', 'Lista Finance B');
    $arch_c1 = $create_container('archive', 'Lista Archive A');

    // Force older updated_at so the touched parent becomes first by recency.
    $wpdb->update($containers, ['updated_at' => '2020-01-01 00:00:00'], ['id' => $fin_c1]);
    $wpdb->update($containers, ['updated_at' => '2019-06-01 00:00:00'], ['id' => $fin_c2]);
    $parent_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c1), ARRAY_A);
    $other_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c2), ARRAY_A);

    $fin_rec = $create_record('finance', $fin_c1, 'Registro Finance', '');
    ac_assert('Finance record confirmed', $fin_rec->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $fin_rec_id = (int) $fin_rec->receipt()->resource_id();
    ac_assert('Finance record resource_id', $fin_rec_id >= 1);
    ac_assert('Finance receipt container_id', (int) $fin_rec->receipt()->container_id() === $fin_c1);

    $arch_rec = $create_record('archive', $arch_c1, 'Registro Archive', "linea1\nlinea2");
    ac_assert('Archive record confirmed', $arch_rec->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $fin_rec_id), ARRAY_A);
    ac_assert('Record container_id', (int) ($row['container_id'] ?? 0) === $fin_c1);
    ac_assert('public_id UUID', (bool) preg_match('/^[0-9a-f-]{36}$/i', (string) ($row['public_id'] ?? '')));
    ac_assert('details vacío → null', $row['details'] === null);
    ac_assert(
        'UTC timestamps',
        preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($row['created_at'] ?? '')) === 1
    );

    $arch_row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$records}` WHERE id = %d",
        (int) $arch_rec->receipt()->resource_id()
    ), ARRAY_A);
    ac_assert('Archive details', ($arch_row['details'] ?? '') === "linea1\nlinea2");

    // Second record should appear first.
    $fin_rec2 = $create_record('finance', $fin_c1, 'Registro más reciente', null);
    ac_assert('Second finance record confirmed', $fin_rec2->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $read_registry = new AA_Canonical_Read_Binding_Registry();
    AA_Canonical_Read_Binding_Bootstrap::register_productive($read_registry);
    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('finance'),
        $registry->family('finance'),
        null
    );
    $page = (new ReadCanonicalShellRecordsUseCase(new CanonicalReadGateway($read_registry)))
        ->execute($manifest, $fin_c1, 1);
    $items = ($page->page() !== null) ? $page->page()->items() : [];
    $first = isset($items[0]) ? $items[0]->title() : '';
    ac_assert('Nuevo registro primero', $first === 'Registro más reciente');

    $parent_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c1), ARRAY_A);
    ac_assert('Parent updated_at bumped', ($parent_after['updated_at'] ?? '') > ($parent_before['updated_at'] ?? ''));
    ac_assert('Parent title intact', ($parent_after['title'] ?? '') === ($parent_before['title'] ?? ''));
    ac_assert('Parent details intact', ($parent_after['details'] ?? null) === ($parent_before['details'] ?? null));
    ac_assert('Parent public_id intact', ($parent_after['public_id'] ?? '') === ($parent_before['public_id'] ?? ''));

    $other_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c2), ARRAY_A);
    ac_assert('Other container updated_at intact', ($other_after['updated_at'] ?? '') === ($other_before['updated_at'] ?? ''));

    // Cross-family container_id → not found
    $cross = $create_record('archive', $fin_c1, 'Fuga', null);
    ac_assert('Cross-family → container_not_found', $cross->state() === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND);

    $legacy_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$temp_prefix}aa_finance_containers`");
    ac_assert('Legacy intacto', $legacy_after === $legacy_before);

    // Parent becomes first in container list by recency.
    require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
    require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
    $clist = (new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($read_registry)))
        ->execute($manifest, 1);
    $citems = ($clist->page() !== null) ? $clist->page()->items() : [];
    $first_c = isset($citems[0]) ? $citems[0]->id() : 0;
    ac_assert('Touched container first in list', $first_c === $fin_c1);
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
