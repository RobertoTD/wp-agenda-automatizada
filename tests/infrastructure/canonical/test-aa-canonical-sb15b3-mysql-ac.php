<?php
/**
 * AC Test — SB1-5B3 MySQL: update record universal (tmp_*).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-sb15b3-mysql-ac.php
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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalUpdateRecordAjax.php');
ac_assert('Ajax sin aa_finance_', strpos($ajax_src, 'aa_finance_') === false);
ac_assert('Ajax sin aa_expediente_', strpos($ajax_src, 'aa_expediente_') === false);
ac_assert('Ajax sin amount', strpos($ajax_src, 'amount') === false);
ac_assert('Ajax sin delete', stripos($ajax_src, 'delete') === false);

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
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateRecordCommand.php';
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
require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_sb15b3_' . substr(md5(uniqid('s53', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_sb15b3_') !== 0) {
        return;
    }
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_records`');
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_containers`');
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_families`');
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

    $manifest_for = static function (string $family_key) use ($registry): CanonicalShellManifest {
        return new CanonicalShellManifest(
            new CanonicalReadIdentity($family_key),
            $registry->family($family_key),
            /* variant removed */ null
        );
    };

    $create_container = static function (string $family_key, string $title) use ($manifest_for, $write_stack): int {
        $result = (new WriteCanonicalShellContainerUseCase($write_stack()))
            ->create($manifest_for($family_key), new CanonicalCreateContainerCommand($title, null));
        return (int) $result->receipt()->resource_id();
    };

    $create_record = static function (string $family_key, int $container_id, string $title, ?string $details) use ($manifest_for, $write_stack): CanonicalShellMutationResult {
        return (new WriteCanonicalShellRecordUseCase($write_stack()))
            ->create($manifest_for($family_key), new CanonicalCreateRecordCommand($container_id, $title, $details));
    };

    $update_record = static function (string $family_key, int $container_id, int $record_id, string $title, ?string $details) use ($manifest_for, $write_stack): CanonicalShellMutationResult {
        return (new WriteCanonicalShellRecordUseCase($write_stack()))
            ->update($manifest_for($family_key), new CanonicalUpdateRecordCommand($container_id, $record_id, $title, $details));
    };

    $fin_c1 = $create_container('finance', 'Lista Finance A');
    $fin_c2 = $create_container('finance', 'Lista Finance B');
    $arch_c1 = $create_container('archive', 'Lista Archive A');

    $wpdb->update($containers, ['updated_at' => '2020-01-01 00:00:00'], ['id' => $fin_c1]);
    $wpdb->update($containers, ['updated_at' => '2019-06-01 00:00:00'], ['id' => $fin_c2]);
    $wpdb->update($containers, ['updated_at' => '2018-01-01 00:00:00'], ['id' => $arch_c1]);

    $fin_r1 = $create_record('finance', $fin_c1, 'Registro A', 'detalle A');
    $fin_r2 = $create_record('finance', $fin_c1, 'Registro B', null);
    $arch_r1 = $create_record('archive', $arch_c1, 'Registro Archive', "arch\nline");

    ac_assert('Seed finance r1', $fin_r1->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('Seed finance r2', $fin_r2->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('Seed archive', $arch_r1->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $r1_id = (int) $fin_r1->receipt()->resource_id();
    $r2_id = (int) $fin_r2->receipt()->resource_id();
    $arch_id = (int) $arch_r1->receipt()->resource_id();

    $wpdb->update($records, ['updated_at' => '2021-01-01 00:00:00'], ['id' => $r1_id]);
    $wpdb->update($records, ['updated_at' => '2021-06-01 00:00:00'], ['id' => $r2_id]);
    $wpdb->update($containers, ['updated_at' => '2020-01-01 00:00:00'], ['id' => $fin_c1]);

    $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $r1_id), ARRAY_A);
    $r2_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $r2_id), ARRAY_A);
    $parent_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c1), ARRAY_A);
    $other_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c2), ARRAY_A);

    $public_id = (string) ($before['public_id'] ?? '');
    $created_at = (string) ($before['created_at'] ?? '');

    $upd = $update_record('finance', $fin_c1, $r1_id, 'Registro A editado', "nuevo\ndetalle");
    ac_assert('Finance update confirmed', $upd->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('Update resource_id', (int) $upd->receipt()->resource_id() === $r1_id);
    ac_assert('Update container_id', (int) $upd->receipt()->container_id() === $fin_c1);

    $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $r1_id), ARRAY_A);
    ac_assert('Title updated', ($after['title'] ?? '') === 'Registro A editado');
    ac_assert('Details updated', ($after['details'] ?? '') === "nuevo\ndetalle");
    ac_assert('public_id preserved', ($after['public_id'] ?? '') === $public_id);
    ac_assert('created_at preserved', ($after['created_at'] ?? '') === $created_at);
    ac_assert('container_id preserved', (int) ($after['container_id'] ?? 0) === $fin_c1);
    ac_assert('updated_at bumped', ($after['updated_at'] ?? '') > ($before['updated_at'] ?? ''));

    $parent_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c1), ARRAY_A);
    ac_assert('Parent updated_at bumped', ($parent_after['updated_at'] ?? '') > ($parent_before['updated_at'] ?? ''));
    ac_assert('Parent public_id intact', ($parent_after['public_id'] ?? '') === ($parent_before['public_id'] ?? ''));

    $r2_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $r2_id), ARRAY_A);
    ac_assert('Other record title intact', ($r2_after['title'] ?? '') === ($r2_before['title'] ?? ''));
    ac_assert('Other record updated_at intact', ($r2_after['updated_at'] ?? '') === ($r2_before['updated_at'] ?? ''));

    $other_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c2), ARRAY_A);
    ac_assert('Other container updated_at intact', ($other_after['updated_at'] ?? '') === ($other_before['updated_at'] ?? ''));

    $noop = $update_record('finance', $fin_c1, $r1_id, 'Registro A editado', "nuevo\ndetalle");
    ac_assert('No-op update confirmed', $noop->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $empty_details = $update_record('finance', $fin_c1, $r1_id, 'Registro A editado', '');
    ac_assert('Empty details update confirmed', $empty_details->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $after_null = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $r1_id), ARRAY_A);
    ac_assert('Details vacío → null', $after_null['details'] === null);

    $arch_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $arch_id), ARRAY_A);
    $arch_upd = $update_record('archive', $arch_c1, $arch_id, 'Registro Archive edit', null);
    ac_assert('Archive update confirmed', $arch_upd->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $arch_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $arch_id), ARRAY_A);
    ac_assert('Archive public_id preserved', ($arch_after['public_id'] ?? '') === ($arch_before['public_id'] ?? ''));
    ac_assert('Archive created_at preserved', ($arch_after['created_at'] ?? '') === ($arch_before['created_at'] ?? ''));

    $cross_container = $update_record('finance', $fin_c2, $r1_id, 'Fuga', null);
    ac_assert('Cross-container → record_not_found', $cross_container->state() === CanonicalShellMutationResult::STATE_RECORD_NOT_FOUND);

    $cross_family = $update_record('archive', $fin_c1, $r1_id, 'Fuga', null);
    ac_assert('Cross-family → container_not_found', $cross_family->state() === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND);

    $missing = $update_record('finance', $fin_c1, 999999, 'X', null);
    ac_assert('Missing record → record_not_found', $missing->state() === CanonicalShellMutationResult::STATE_RECORD_NOT_FOUND);

    $read_registry = new AA_Canonical_Read_Binding_Registry();
    AA_Canonical_Read_Binding_Bootstrap::register_productive($read_registry);
    $manifest = $manifest_for('finance');
    $page = (new ReadCanonicalShellRecordsUseCase(new CanonicalReadGateway($read_registry)))
        ->execute($manifest, $fin_c1, 1);
    $items = ($page->page() !== null) ? $page->page()->items() : [];
    $first = isset($items[0]) ? $items[0]->title() : '';
    ac_assert('Updated record first', $first === 'Registro A editado');

    $clist = (new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($read_registry)))
        ->execute($manifest, 1);
    $citems = ($clist->page() !== null) ? $clist->page()->items() : [];
    $first_c = isset($citems[0]) ? $citems[0]->id() : 0;
    ac_assert('Touched container first in list', $first_c === $fin_c1);

    $legacy_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$temp_prefix}aa_finance_containers`");
    ac_assert('Legacy intacto', $legacy_after === $legacy_before);
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
