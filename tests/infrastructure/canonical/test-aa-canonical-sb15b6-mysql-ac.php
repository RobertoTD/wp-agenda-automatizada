<?php
/**
 * AC Test — SB1-5B6 MySQL: delete container universal + FK CASCADE (tmp_*).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-sb15b6-mysql-ac.php
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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalDeleteContainerAjax.php');
$repo_src = (string) file_get_contents($plugin_root . '/includes/repositories/CanonicalRelationalRepository.php');
ac_assert('Ajax sin aa_finance_', strpos($ajax_src, 'aa_finance_') === false);
ac_assert('Ajax sin aa_expediente_', strpos($ajax_src, 'aa_expediente_') === false);
ac_assert('Ajax sin amount', strpos($ajax_src, 'amount') === false);
ac_assert('Repo delete_container sin delete records', preg_match(
    '/function delete_container[\s\S]*?function delete_record/',
    $repo_src
) === 1 && strpos(
    substr($repo_src, strpos($repo_src, 'function delete_container'), 800),
    'records_table'
) === false);

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
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteContainerCommand.php';
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
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_sb15b6_' . substr(md5(uniqid('s56', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_sb15b6_') !== 0) {
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

    $engine = (string) $wpdb->get_var($wpdb->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $containers
    ));
    ac_assert('Containers InnoDB', strtoupper($engine) === 'INNODB');

    $fk_name = AA_Canonical_Schema::records_foreign_key_name($temp_prefix);
    $fk_row = $wpdb->get_row($wpdb->prepare(
        "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = %s",
        $fk_name
    ), ARRAY_A);
    ac_assert('FK CASCADE presente', is_array($fk_row) && strtoupper((string) ($fk_row['DELETE_RULE'] ?? '')) === 'CASCADE');

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
            new CanonicalReadIdentity($family_key, 'general'),
            $registry->family($family_key),
            $registry->variant($family_key, 'general')
        );
    };

    $create_container = static function (string $family_key, string $title) use ($manifest_for, $write_stack): int {
        $result = (new WriteCanonicalShellContainerUseCase($write_stack()))
            ->create($manifest_for($family_key), new CanonicalCreateContainerCommand($title, null));
        return (int) $result->receipt()->resource_id();
    };

    $create_record = static function (string $family_key, int $container_id, string $title) use ($manifest_for, $write_stack): int {
        $result = (new WriteCanonicalShellRecordUseCase($write_stack()))
            ->create($manifest_for($family_key), new CanonicalCreateRecordCommand($container_id, $title, null));
        return (int) $result->receipt()->resource_id();
    };

    $delete_container = static function (string $family_key, int $container_id) use ($manifest_for, $write_stack): CanonicalShellMutationResult {
        return (new WriteCanonicalShellContainerUseCase($write_stack()))
            ->delete($manifest_for($family_key), new CanonicalDeleteContainerCommand($container_id));
    };

    $empty_id = $create_container('finance', 'Lista vacía');
    $one_id = $create_container('finance', 'Lista con uno');
    $many_id = $create_container('finance', 'Lista con varios');
    $other_id = $create_container('finance', 'Lista intacta');
    $arch_id = $create_container('archive', 'Lista Archive');

    $one_rec = $create_record('finance', $one_id, 'Único');
    $many_r1 = $create_record('finance', $many_id, 'A');
    $many_r2 = $create_record('finance', $many_id, 'B');
    $many_r3 = $create_record('finance', $many_id, 'C');
    $other_rec = $create_record('finance', $other_id, 'Otro registro');
    $arch_rec = $create_record('archive', $arch_id, 'Arch record');

    $other_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $other_id), ARRAY_A);
    $other_rec_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $other_rec), ARRAY_A);
    $family_before = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$families}` WHERE id = %d", $finance_id));

    $del_empty = $delete_container('finance', $empty_id);
    ac_assert('Empty delete confirmed', $del_empty->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('Empty gone', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$containers}` WHERE id = %d", $empty_id)) === '0');

    $del_one = $delete_container('finance', $one_id);
    ac_assert('One-record delete confirmed', $del_one->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('One container gone', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$containers}` WHERE id = %d", $one_id)) === '0');
    ac_assert('One record cascaded', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$records}` WHERE id = %d", $one_rec)) === '0');

    $del_many = $delete_container('finance', $many_id);
    ac_assert('Many-record delete confirmed', $del_many->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('Many container gone', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$containers}` WHERE id = %d", $many_id)) === '0');
    $orphans = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$records}` WHERE container_id = %d",
        $many_id
    ));
    ac_assert('Cero huérfanos many', $orphans === 0);
    ac_assert('Many r1 cascaded', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$records}` WHERE id = %d", $many_r1)) === '0');
    ac_assert('Many r2 cascaded', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$records}` WHERE id = %d", $many_r2)) === '0');
    ac_assert('Many r3 cascaded', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$records}` WHERE id = %d", $many_r3)) === '0');

    $other_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $other_id), ARRAY_A);
    $other_rec_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$records}` WHERE id = %d", $other_rec), ARRAY_A);
    ac_assert('Otra lista intacta', $other_after === $other_before);
    ac_assert('Record otra lista intacto', $other_rec_after === $other_rec_before);
    ac_assert('Familia intacta', (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$families}` WHERE id = %d", $finance_id)) === $family_before);

    $arch_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$containers}` WHERE id = %d", $arch_id), ARRAY_A);
    $del_arch = $delete_container('archive', $arch_id);
    ac_assert('Archive delete confirmed', $del_arch->state() === CanonicalShellMutationResult::STATE_CONFIRMED);
    ac_assert('Archive record cascaded', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$records}` WHERE id = %d", $arch_rec)) === '0');
    unset($arch_before);

    $second = $delete_container('finance', $many_id);
    ac_assert('Second delete → not found', $second->state() === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND);

    $cross = $delete_container('archive', $other_id);
    ac_assert('Cross-family → not found', $cross->state() === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND);

    $last = $delete_container('finance', $other_id);
    ac_assert('Last finance list deleted', $last->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

    $read_registry = new AA_Canonical_Read_Binding_Registry();
    AA_Canonical_Read_Binding_Bootstrap::register_productive($read_registry);
    $page = (new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($read_registry)))
        ->execute($manifest_for('finance'), 1);
    ac_assert('Última lista → empty state', $page->state() === CanonicalShellReadResult::STATE_EMPTY);

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
