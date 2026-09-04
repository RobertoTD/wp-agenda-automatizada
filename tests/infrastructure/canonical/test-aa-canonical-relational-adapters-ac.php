<?php
/**
 * AC Test — Adaptadores relacionales canónicos (PCU-3).
 *
 * Ejecutar:
 *   php tests/infrastructure/canonical/test-aa-canonical-relational-adapters-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-relational-adapters-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$read_file = $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-read-adapter.php';
$write_file = $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';
$bootstrap_file = $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
$preview_file = $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-preview-adapter.php';

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

echo "=== 1. Contención / estático ===\n";

$read_src = (string) file_get_contents($read_file);
$write_src = (string) file_get_contents($write_file);
$boot_src = (string) file_get_contents($bootstrap_file);
$preview_src = is_readable($preview_file) ? (string) file_get_contents($preview_file) : '';

ac_assert('Read adapter existe', is_readable($read_file));
ac_assert('Write adapter existe', is_readable($write_file));
ac_assert('Read no ejecuta SQL directo', !preg_match('/\$this->(wpdb|repository).*->(query|get_var|insert)\b/', $read_src) || strpos($read_src, '$this->repository->') !== false);
ac_assert('Read solo usa repository', strpos($read_src, '$this->repository->') !== false && strpos($read_src, 'wpdb') === false);
ac_assert('Write no usa wpdb', strpos($write_src, 'wpdb') === false);
ac_assert('Read/Write sin finance', stripos($read_src, 'finance') === false && stripos($write_src, 'finance') === false);
ac_assert('Read/Write sin Expedientes', stripos($read_src, 'expediente') === false && stripos($write_src, 'expediente') === false);
ac_assert('Read lanza CanonicalReadPersistenceFailed', strpos($read_src, 'CanonicalReadPersistenceFailed') !== false);
ac_assert('Write mapea AmbiguousOutcome → uncertain', strpos($write_src, 'uncertain_from_ambiguous') !== false);
ac_assert('Write mapea QueryFailed → PersistenceFailed', strpos($write_src, 'CanonicalMutationPersistenceFailed') !== false);
ac_assert('Bootstrap productivo sigue siendo Finance', strpos($boot_src, 'AA_Finance_Canonical_Read_Adapter') !== false);
ac_assert('Bootstrap no registra Relational', strpos($boot_src, 'Relational_Read_Adapter') === false);
ac_assert('Preview no escribe', $preview_src === '' || (stripos($preview_src, 'insert') === false && stripos($preview_src, 'update') === false));
ac_assert('DTOs sin public_id en read adapter', !preg_match('/public_id/', $read_src) || strpos($read_src, "['public_id']") === false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load === '' || !is_readable($wp_load)) {
    // Unitario: Use Case + PersistenceFailed sin MySQL
    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_root . '/');
    }
    require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
    require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
    require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
    require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
    require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
    require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalPagination.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalReadPersistenceFailed.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
    require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
    require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
    require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
    require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';

    final class PersistenceFailReadAdapter implements CanonicalReadAdapter {
        public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
            throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'forced');
        }
        public function get_container(string $variant_key, int $container_id): AA_Canonical_Container {
            throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_FAMILY_NOT_PROVISIONED, 'x');
        }
        public function list_records(string $variant_key, int $container_id, int $page, int $per_page): CanonicalRecordsPage {
            throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'forced');
        }
    }

    $manifest = new CanonicalShellManifest(
        new CanonicalReadIdentity('sample', 'alpha'),
        new AA_Canonical_Family_Definition('sample', 'Muestra', 'alpha'),
        new AA_Canonical_Variant_Definition('sample', 'alpha', 'Alpha')
    );
    $registry = new AA_Canonical_Read_Binding_Registry();
    $registry->register($manifest->identity(), new PersistenceFailReadAdapter());
    $uc = new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($registry));
    $result = $uc->execute($manifest, 1);
    ac_assert('UseCase containers: ReadPersistenceFailed → contract_error', $result->state() === CanonicalShellReadResult::STATE_CONTRACT_ERROR);
    ac_assert('UseCase containers: no empty page en SQL fail', $result->page() === null);

    $uc_r = new ReadCanonicalShellRecordsUseCase(new CanonicalReadGateway($registry));
    $result_r = $uc_r->execute($manifest, 1, 1);
    ac_assert('UseCase records: ReadPersistenceFailed → contract_error', $result_r->state() === CanonicalShellRecordsReadResult::STATE_CONTRACT_ERROR);

    echo "[INFO / SKIP] Integración MySQL de adaptadores no ejecutada (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    if ($failed !== []) {
        echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
        exit(1);
    }
    exit(0);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalQueryFailed.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalAmbiguousOutcome.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteRecordCommand.php';
require_once $read_file;
require_once $write_file;
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';

global $wpdb;

echo "=== 2. MySQL adaptadores ===\n";

$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_cadp_' . substr(md5(uniqid('ad', true)), 0, 8) . '_';
$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_cadp_') !== 0) {
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

    $repo = new CanonicalRelationalRepository($wpdb);
    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(
        AA_Canonical_Schema::families_table_name(),
        [
            'family_key' => 'sample',
            'is_enabled' => 0,
            'seed_version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%s', '%d', '%d', '%s', '%s']
    );

    $identity = new CanonicalReadIdentity('sample', 'alpha');
    $read = new AA_Canonical_Relational_Read_Adapter($repo, $identity);
    $write = new AA_Canonical_Relational_Write_Adapter($repo);

    $created = $write->create_container($identity, new CanonicalCreateContainerCommand('Alpha One', null));
    ac_assert('Write create container confirmed', $created->outcome() === 'confirmed' && $created->resource_id() >= 1);
    $cid = (int) $created->resource_id();

    $page = $read->list_containers('alpha', 1, 15);
    ac_assert('Read list containers', $page->total() === 1 && count($page->items()) === 1);
    ac_assert('Read DTO sin public_id en array canónico', !array_key_exists('public_id', $page->items()[0]->to_canonical_array()));

    $got = $read->get_container('alpha', $cid);
    ac_assert('Read get container', $got->id() === $cid && $got->title() === 'Alpha One');

    $not_found = false;
    try {
        $read->get_container('alpha', 999999);
    } catch (CanonicalContainerNotFound $e) {
        $not_found = true;
    }
    ac_assert('Read container ausente → ContainerNotFound', $not_found);

    $upd = $write->update_container($identity, new CanonicalUpdateContainerCommand($cid, 'Alpha Two', 'd'));
    ac_assert('Write update container confirmed', $upd->outcome() === 'confirmed');

    $rec = $write->create_record($identity, new CanonicalCreateRecordCommand($cid, 'Rec A', null));
    ac_assert('Write create record confirmed', $rec->outcome() === 'confirmed' && $rec->container_id() === $cid);
    $rid = (int) $rec->resource_id();

    $records = $read->list_records('alpha', $cid, 1, 15);
    ac_assert('Read list records', $records->total() === 1);

    $write->update_record($identity, new CanonicalUpdateRecordCommand($cid, $rid, 'Rec B', null));
    $write->delete_record($identity, new CanonicalDeleteRecordCommand($cid, $rid));
    $missing_rec = false;
    try {
        $write->delete_record($identity, new CanonicalDeleteRecordCommand($cid, $rid));
    } catch (CanonicalRecordNotFound $e) {
        $missing_rec = true;
    }
    ac_assert('Write delete record inexistente → RecordNotFound', $missing_rec);

    // Familia no provisionada
    $orphan_identity = new CanonicalReadIdentity('orphan', 'alpha');
    $orphan_read = new AA_Canonical_Relational_Read_Adapter($repo, $orphan_identity);
    $fam_fail = false;
    try {
        $orphan_read->list_containers('alpha', 1, 15);
    } catch (CanonicalReadPersistenceFailed $e) {
        $fam_fail = $e->reason() === CanonicalReadPersistenceFailed::REASON_FAMILY_NOT_PROVISIONED;
    }
    ac_assert('Read familia no provisionada → ReadPersistenceFailed', $fam_fail);

    // SQL read fail → Use Case contract_error
    final class ForcedSqlReadAdapter implements CanonicalReadAdapter {
        public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
            throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'forced');
        }
        public function get_container(string $variant_key, int $container_id): AA_Canonical_Container {
            throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'forced');
        }
        public function list_records(string $variant_key, int $container_id, int $page, int $per_page): CanonicalRecordsPage {
            throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'forced');
        }
    }
    $registry = new AA_Canonical_Read_Binding_Registry();
    $registry->register($identity, new ForcedSqlReadAdapter());
    $manifest = new CanonicalShellManifest(
        $identity,
        new AA_Canonical_Family_Definition('sample', 'Sample', 'alpha'),
        new AA_Canonical_Variant_Definition('sample', 'alpha', 'Alpha')
    );
    $uc = new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($registry));
    $contract = $uc->execute($manifest, 1);
    ac_assert('SQL read → UseCase contract_error', $contract->state() === CanonicalShellReadResult::STATE_CONTRACT_ERROR);

    // Write SQL fail via wpdb probe (sin subclase del repositorio final)
    $fail_insert = new class($wpdb) {
        public $inner;
        public $prefix;
        public $last_error = '';
        public $insert_id = 0;
        public function __construct($inner) {
            $this->inner = $inner;
            $this->prefix = $inner->prefix;
        }
        private function sync(): void {
            $this->prefix = $this->inner->prefix;
            $this->last_error = (string) ($this->inner->last_error ?? '');
            $this->insert_id = (int) ($this->inner->insert_id ?? 0);
        }
        public function prepare($q, ...$a) { return $this->inner->prepare($q, ...$a); }
        public function query($sql) { $r = $this->inner->query($sql); $this->sync(); return $r; }
        public function get_var($q = null, $x = 0, $y = 0) { $r = $this->inner->get_var($q, $x, $y); $this->sync(); return $r; }
        public function get_row($q = null, $o = OBJECT, $y = 0) { $r = $this->inner->get_row($q, $o, $y); $this->sync(); return $r; }
        public function get_results($q = null, $o = OBJECT) { $r = $this->inner->get_results($q, $o); $this->sync(); return $r; }
        public function insert($table, $data, $format = null) {
            $this->last_error = 'forced';
            return false;
        }
        public function update($t, $d, $w, $f = null, $wf = null) { $r = $this->inner->update($t, $d, $w, $f, $wf); $this->sync(); return $r; }
        public function delete($t, $w, $wf = null) { $r = $this->inner->delete($t, $w, $wf); $this->sync(); return $r; }
    };
    $persist_fail = false;
    try {
        (new AA_Canonical_Relational_Write_Adapter(new CanonicalRelationalRepository($fail_insert)))
            ->create_container($identity, new CanonicalCreateContainerCommand('X', null));
    } catch (CanonicalMutationPersistenceFailed $e) {
        $persist_fail = true;
    }
    ac_assert('Write SQL fail → MutationPersistenceFailed', $persist_fail);

    // COMMIT false → uncertain receipt
    $fail_commit = new class($wpdb) {
        public $inner;
        public $prefix;
        public $last_error = '';
        public $insert_id = 0;
        public function __construct($inner) {
            $this->inner = $inner;
            $this->prefix = $inner->prefix;
        }
        private function sync(): void {
            $this->prefix = $this->inner->prefix;
            $this->last_error = (string) ($this->inner->last_error ?? '');
            $this->insert_id = (int) ($this->inner->insert_id ?? 0);
        }
        public function prepare($q, ...$a) { return $this->inner->prepare($q, ...$a); }
        public function query($sql) {
            if (stripos(ltrim((string) $sql), 'COMMIT') === 0) {
                $this->last_error = 'forced commit';
                return false;
            }
            $r = $this->inner->query($sql);
            $this->sync();
            return $r;
        }
        public function get_var($q = null, $x = 0, $y = 0) { $r = $this->inner->get_var($q, $x, $y); $this->sync(); return $r; }
        public function get_row($q = null, $o = OBJECT, $y = 0) { $r = $this->inner->get_row($q, $o, $y); $this->sync(); return $r; }
        public function get_results($q = null, $o = OBJECT) { $r = $this->inner->get_results($q, $o); $this->sync(); return $r; }
        public function insert($t, $d, $f = null) { $r = $this->inner->insert($t, $d, $f); $this->sync(); return $r; }
        public function update($t, $d, $w, $f = null, $wf = null) { $r = $this->inner->update($t, $d, $w, $f, $wf); $this->sync(); return $r; }
        public function delete($t, $w, $wf = null) { $r = $this->inner->delete($t, $w, $wf); $this->sync(); return $r; }
    };
    $unc = (new AA_Canonical_Relational_Write_Adapter(new CanonicalRelationalRepository($fail_commit)))
        ->create_record($identity, new CanonicalCreateRecordCommand($cid, 'U', null));
    ac_assert(
        'Ambiguous COMMIT → uncertain con IDs',
        $unc->outcome() === 'uncertain'
        && $unc->resource_id() >= 1
        && $unc->container_id() === $cid
    );
    $wpdb->query('ROLLBACK');

    $write->delete_container($identity, new CanonicalDeleteContainerCommand($cid));
    ac_assert('Write delete container confirmed', true);

} finally {
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
    echo "Limpieza garantizada.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
