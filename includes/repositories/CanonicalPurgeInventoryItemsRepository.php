<?php
/**
 * Canonical Purge Inventory Items Repository — inventario congelado de purge (IMG-5 inc. 2).
 *
 * Sin FK a records/containers: sobrevive al DELETE futuro del recurso.
 * FK solo hacia purge_runs (CASCADE).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__) . '/infrastructure/wp/CanonicalSchema.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__) . '/application/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__) . '/application/storage/CanonicalImageUploadSchemaNotReady.php';
}

final class CanonicalPurgeInventoryItemsRepository {

    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb
     */
    public function __construct($wpdb = null) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
            return;
        }

        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * @return object
     */
    public function connection() {
        return $this->wpdb;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_by_operation(int $purge_run_id, string $upload_operation_id): ?array {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        $op = strtolower(trim($upload_operation_id));
        if ($purge_run_id < 1 || $op === '') {
            return null;
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, purge_run_id, upload_operation_id, wp_record_id, content_sha256,
                        byte_size, storage_path, source, batch_seq, position_in_batch, created_at
                 FROM `{$safe}`
                 WHERE purge_run_id = %d AND upload_operation_id = %s
                 LIMIT 1",
                $purge_run_id,
                $op
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT purge inventory item.');
        }

        return is_array($row) ? $row : null;
    }

    /**
     * INSERT de ítem capturado. Sin commit propio.
     *
     * @param array{
     *   purge_run_id:int,
     *   upload_operation_id:string,
     *   wp_record_id:int,
     *   content_sha256:string,
     *   byte_size:int,
     *   storage_path:string,
     *   source:string,
     *   created_at:string
     * } $row
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function insert_item(array $row): int {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $result = $this->wpdb->insert(
            $table,
            [
                'purge_run_id' => (int) $row['purge_run_id'],
                'upload_operation_id' => (string) $row['upload_operation_id'],
                'wp_record_id' => (int) $row['wp_record_id'],
                'content_sha256' => (string) $row['content_sha256'],
                'byte_size' => (int) $row['byte_size'],
                'storage_path' => (string) $row['storage_path'],
                'source' => (string) $row['source'],
                'created_at' => (string) $row['created_at'],
            ],
            ['%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to INSERT purge inventory item.');
        }

        $id = (int) $this->wpdb->insert_id;
        if ($id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Purge inventory insert_id invalid.');
        }

        return $id;
    }

    /**
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function update_source(int $item_id, string $source): void {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        if ($item_id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid inventory item id.');
        }

        $this->clear_error_state();
        $result = $this->wpdb->update(
            $table,
            ['source' => $source],
            ['id' => $item_id],
            ['%s'],
            ['%d']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to UPDATE purge inventory source.');
        }
    }

    /**
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function assign_batch_slot(int $item_id, int $batch_seq, int $position_in_batch): void {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        if ($item_id < 1 || $batch_seq < 0 || $position_in_batch < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid batch slot.');
        }

        $this->clear_error_state();
        $result = $this->wpdb->update(
            $table,
            [
                'batch_seq' => $batch_seq,
                'position_in_batch' => $position_in_batch,
            ],
            ['id' => $item_id],
            ['%d', '%d'],
            ['%d']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to assign purge inventory batch slot.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function list_ordered_for_run(int $purge_run_id): array {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1) {
            return [];
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, purge_run_id, upload_operation_id, wp_record_id, content_sha256,
                        byte_size, storage_path, source, batch_seq, position_in_batch, created_at
                 FROM `{$safe}`
                 WHERE purge_run_id = %d
                 ORDER BY upload_operation_id ASC",
                $purge_run_id
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '' || !is_array($rows)) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to LIST purge inventory items.');
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function list_prepared_batch(int $purge_run_id, int $batch_seq): array {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1 || $batch_seq < 0) {
            return [];
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, purge_run_id, upload_operation_id, wp_record_id, content_sha256,
                        byte_size, storage_path, source, batch_seq, position_in_batch, created_at
                 FROM `{$safe}`
                 WHERE purge_run_id = %d AND batch_seq = %d
                 ORDER BY position_in_batch ASC, upload_operation_id ASC",
                $purge_run_id,
                $batch_seq
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '' || !is_array($rows)) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to LIST prepared purge batch.');
        }

        return $rows;
    }

    /**
     * Página de inventario por id (keyset). No usa OFFSET.
     *
     * @return list<array<string, mixed>>
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function list_page_after_id(int $purge_run_id, int $after_id, int $limit): array {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1 || $after_id < 0 || $limit < 1) {
            return [];
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, purge_run_id, upload_operation_id, wp_record_id, content_sha256,
                        byte_size, storage_path, source, batch_seq, position_in_batch, created_at
                 FROM `{$safe}`
                 WHERE purge_run_id = %d AND id > %d
                 ORDER BY id ASC
                 LIMIT %d",
                $purge_run_id,
                $after_id,
                $limit
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '' || !is_array($rows)) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to LIST purge inventory page after id.');
        }

        return $rows;
    }

    /**
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function count_for_run(int $purge_run_id): int {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1) {
            return 0;
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM `{$safe}` WHERE purge_run_id = %d",
                $purge_run_id
            )
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to COUNT purge inventory items.');
        }

        return (int) $count;
    }

    /**
     * Mínimo `batch_seq` ya asignado, o null si no hay tandas.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function min_assigned_batch_seq(int $purge_run_id): ?int {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1) {
            return null;
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $min = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT MIN(batch_seq) FROM `{$safe}`
                 WHERE purge_run_id = %d AND batch_seq IS NOT NULL",
                $purge_run_id
            )
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to MIN purge inventory batch_seq.');
        }
        if ($min === null || $min === false || $min === '') {
            return null;
        }

        return (int) $min;
    }

    /**
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function has_assigned_batch_seq(int $purge_run_id, int $batch_seq): bool {
        $table = AA_Canonical_Schema::purge_inventory_items_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1 || $batch_seq < 0) {
            return false;
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM `{$safe}`
                 WHERE purge_run_id = %d AND batch_seq = %d
                 LIMIT 1",
                $purge_run_id,
                $batch_seq
            )
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to query purge inventory batch_seq.');
        }

        return $found !== null && $found !== false && (int) $found >= 1;
    }

    /**
     * True si hay tandas preparadas con índice 1-based del incremento 2
     * (MIN=1 y ninguna fila con batch_seq=0). No renumerar esas corridas.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function has_legacy_one_based_batches(int $purge_run_id): bool {
        if ($purge_run_id < 1) {
            return false;
        }

        $min = $this->min_assigned_batch_seq($purge_run_id);
        if ($min !== 1) {
            return false;
        }

        return !$this->has_assigned_batch_seq($purge_run_id, 0);
    }

    /**
     * @throws CanonicalImageUploadSchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '' || $found !== $table) {
            throw new CanonicalImageUploadSchemaNotReady('Required purge inventory table missing: ' . $table);
        }
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }
}
