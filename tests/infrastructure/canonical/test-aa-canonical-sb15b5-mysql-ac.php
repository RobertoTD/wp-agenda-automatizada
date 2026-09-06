<?php
/**
 * AC Test — SB1-5B5 MySQL: update container universal (tmp_*).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-sb15b5-mysql-ac.php
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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalUpdateContainerAjax.php');
ac_assert('Ajax sin aa_finance_', strpos($ajax_src, 'aa_finance_') === false);
ac_assert('Ajax sin aa_expediente_', strpos($ajax_src, 'aa_expediente_') === false);
ac_assert('Ajax sin amount', strpos($ajax_src, 'amount') === false);
ac_assert('Ajax sin delete container', strpos($ajax_src, 'delete_container') === false);

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
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
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
$temp_prefix = 'tmp_sb15b5_' . substr(md5(uniqid('s55', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_sb15b5_') !== 0) {
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

    $finance_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$families}` WHERE family_key = %s",
        'finance'
    ));
    $archive_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$families}` WHERE family_key = %s",
        'archive'
    ));

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

    $create_container = static function (string $family_key, string $title, ?string $details = null) use ($manifest_for, $write_stack): int {
        $result = (new WriteCanonicalShellContainerUseCase($write_stack()))
            ->create($manifest_for($family_key), new CanonicalCreateContainerCommand($title, $details));
        return (int) $result->receipt()->resource_id();
    };

    $update_container = static function (
        string $family_key,
        int $container_id,
        string $title,
        ?string $details
    ) use ($manifest_for, $write_stack): CanonicalShellMutationResult {
        return (new WriteCanonicalShellContainerUseCase($write_stack()))
            ->update($manifest_for($family_key), new CanonicalUpdateContainerCommand($container_id, $title, $details));
    };

    $create_record = static function (string $family_key, int $container_id, string $title, ?string $details) use ($manifest_for, $write_stack): int {
        $result = (new WriteCanonicalShellRecordUseCase($write_stack()))
            ->create($manifest_for($family_key), new CanonicalCreateRecordCommand($container_id, $title, $details));
        return (int) $result->receipt()->resource_id();
    };

    $fin_c1 = $create_container('finance', 'Lista Finance A', 'detalle A');
    $fin_c2 = $create_container('finance', 'Lista Finance B', null);
    $arch_c1 = $create_container('archive', 'Lista Archive A', "arch\nline");

    $wpdb->update($containers, ['updated_at' => '2020-01-01 00:00:00', 'created_at' => '2019-01-01 00:00:00'], ['id' => $fin_c1]);
    $wpdb->update($containers, ['updated_at' => '2019-06-01 00:00:00'], ['id' => $fin_c2]);
    $wpdb->update($containers, ['updated_at' => '2018-01-01 00:00:00'], ['id' => $arch_c1]);

    $fin_r1 = $create_record('finance', $fin_c1, 'Registro A', 'detalle registro');
    $fin_r2 = $create_record('finance', $fin_c1, 'Registro B', null);
    $wpdb->update($records, ['updated_at' => '2021-01-01 00:00:00', 'created_at' => '2020-06-01 00:00:00'], ['id' => $fin_r1]);
    $wpdb->update($records, ['updated_at' => '2021-06-01 00:00:00'], ['id' => $fin_r2]);
    $wpdb->update($containers, ['updated_at' => '2020-01-01 00:00:00'], ['id' => $fin_c1]);

    $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c1), ARRAY_A);
    $other_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c2), ARRAY_A);
    $r1_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $fin_r1), ARRAY_A);
    $r2_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $fin_r2), ARRAY_A);
    $records_count_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$records}`");

    $public_id = (string) ($before['public_id'] ?? '');
    $created_at = (string) ($before['created_at'] ?? '');
    $family_id_before = (int) ($before['family_id'] ?? 0);

    $upd = $update_container('finance', $fin_c1, 'Lista Finance A editada', "nuevo\ndetalle");
    ac_assert('Finance update confirmed', $upd->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('Update resource_id', (int) $upd->receipt()->resource_id() === $fin_c1);
    ac_assert('Update receipt container_id null', $upd->receipt()->container_id() === null);

    $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c1), ARRAY_A);
    ac_assert('Title updated', ($after['title'] ?? '') === 'Lista Finance A editada');
    ac_assert('Details updated', ($after['details'] ?? '') === "nuevo\ndetalle");
    ac_assert('public_id preserved', ($after['public_id'] ?? '') === $public_id);
    ac_assert('created_at preserved', ($after['created_at'] ?? '') === $created_at);
    ac_assert('family_id preserved', (int) ($after['family_id'] ?? 0) === $family_id_before
        && $family_id_before === $finance_id);
    ac_assert('Update row sin variant_key', !array_key_exists('variant_key', $after));
    ac_assert('updated_at bumped', ($after['updated_at'] ?? '') > ($before['updated_at'] ?? ''));

    $r1_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $fin_r1), ARRAY_A);
    $r2_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $fin_r2), ARRAY_A);
    ac_assert('Record1 fully intact', $r1_after === $r1_before);
    ac_assert('Record2 fully intact', $r2_after === $r2_before);
    ac_assert('Records count intact', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$records}`") === $records_count_before);

    $other_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c2), ARRAY_A);
    ac_assert('Other container intact', $other_after === $other_before);

    $noop = $update_container('finance', $fin_c1, 'Lista Finance A editada', "nuevo\ndetalle");
    ac_assert('No-op update confirmed', $noop->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $empty_details = $update_container('finance', $fin_c1, 'Lista Finance A editada', '');
    ac_assert('Empty details update confirmed', $empty_details->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $after_null = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $fin_c1), ARRAY_A);
    ac_assert('Details vacío → null', $after_null['details'] === null);

    $arch_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $arch_c1), ARRAY_A);
    $arch_upd = $update_container('archive', $arch_c1, 'Lista Archive edit', null);
    ac_assert('Archive update confirmed', $arch_upd->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    $arch_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $arch_c1), ARRAY_A);
    ac_assert('Archive public_id preserved', ($arch_after['public_id'] ?? '') === ($arch_before['public_id'] ?? ''));
    ac_assert('Archive created_at preserved', ($arch_after['created_at'] ?? '') === ($arch_before['created_at'] ?? ''));
    ac_assert('Archive family_id preserved', (int) ($arch_after['family_id'] ?? 0) === $archive_id);

    $cross_family = $update_container('archive', $fin_c1, 'Fuga', null);
    ac_assert('Cross-family → container_not_found', $cross_family->state() === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND);

    $missing = $update_container('finance', 999999, 'X', null);
    ac_assert('Missing → container_not_found', $missing->state() === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND);

    $read_registry = new AA_Canonical_Read_Binding_Registry();
    AA_Canonical_Read_Binding_Bootstrap::register_productive($read_registry);
    $manifest = $manifest_for('finance');
    $page = (new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($read_registry)))
        ->execute($manifest, 1);
    $items = ($page->page() !== null) ? $page->page()->items() : [];
    $first_id = isset($items[0]) ? $items[0]->id() : 0;
    ac_assert('Lista editada primero', $first_id === $fin_c1);

    $legacy_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$temp_prefix}aa_finance_containers`");
    ac_assert('Legacy finance intacto', $legacy_after === $legacy_before);
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
