<?php
/**
 * Store TX de captura de inventario de purge canónico (IMG-5 inc. 2).
 *
 * Persiste ítems capturados y el checkpoint de lectura de fuentes en la
 * misma transacción. Asigna tandas solo tras captura completa.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__, 2) . '/wp/CanonicalSchema.php';
}
if (!class_exists('AA_Canonical_Purge_Inventory_Identity')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-purge-inventory-identity.php';
}
if (!class_exists('CanonicalPurgeRunsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeRunsRepository.php';
}
if (!class_exists('CanonicalPurgeInventoryItemsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeInventoryItemsRepository.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 3) . '/application/storage/CanonicalImageUploadPersistenceFailed.php';
}

final class AA_Canonical_Purge_Capture_Store {

    /** @var CanonicalPurgeRunsRepository */
    private $runs;

    /** @var CanonicalPurgeInventoryItemsRepository */
    private $items;

    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb
     */
    public function __construct(
        ?CanonicalPurgeRunsRepository $runs = null,
        ?CanonicalPurgeInventoryItemsRepository $items = null,
        $wpdb = null
    ) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
        } else {
            global $wpdb;
            $this->wpdb = $wpdb;
        }

        $this->runs = $runs ?: new CanonicalPurgeRunsRepository($this->wpdb);
        $this->items = $items ?: new CanonicalPurgeInventoryItemsRepository($this->wpdb);
    }

    /**
     * @param list<array{
     *   upload_operation_id:string,
     *   wp_record_id:int,
     *   content_sha256:string,
     *   byte_size:int,
     *   storage_path:string,
     *   source:string
     * }> $page_items
     * @param array{
     *   images_read_after_id:int,
     *   ops_read_after_operation_id:?string,
     *   images_source_exhausted:int,
     *   ops_source_exhausted:int,
     *   capture_status:string,
     *   capture_conflict_code:?string,
     *   capture_complete:int,
     *   status:string,
     *   updated_at:string
     * } $checkpoint
     *
     * @throws CanonicalImageUploadPersistenceFailed
     */
    public function persist_captured_page(int $purge_run_id, array $page_items, array $checkpoint): void {
        $created_at = (string) $checkpoint['updated_at'];

        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                throw new CanonicalImageUploadPersistenceFailed('Failed to start purge capture transaction.');
            }

            foreach ($page_items as $item) {
                $this->upsert_item($purge_run_id, $item, $created_at);
            }

            $this->runs->update_capture_progress($purge_run_id, $checkpoint);

            $this->wpdb->last_error = '';
            if ($this->wpdb->query('COMMIT') === false) {
                $this->best_effort_rollback();
                throw new CanonicalImageUploadPersistenceFailed('Failed to COMMIT purge capture page.');
            }
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            $this->best_effort_rollback();
            throw $e;
        } catch (\Throwable $e) {
            $this->best_effort_rollback();
            throw new CanonicalImageUploadPersistenceFailed('Purge capture page transaction failed.');
        }
    }

    /**
     * Asigna tandas de hasta 50 ítems tras captura completa. Índice 0-based
     * (`batch_seq` 0 es la primera tanda). No-op si ya estaban preparadas.
     * Inventario vacío → cero tandas (ninguna fila de tanda vacía).
     *
     * @throws CanonicalImageUploadPersistenceFailed
     */
    public function prepare_batches_after_capture(int $purge_run_id, string $updated_at): int {
        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                throw new CanonicalImageUploadPersistenceFailed('Failed to start batch preparation transaction.');
            }

            $run = $this->runs->find_by_id($purge_run_id);
            if ($run === null) {
                throw new CanonicalImageUploadPersistenceFailed('Purge run missing during batch preparation.');
            }

            if ((int) ($run['batches_prepared'] ?? 0) === 1) {
                $this->wpdb->query('COMMIT');
                return (int) ($run['prepared_batch_count'] ?? 0);
            }

            if ((int) ($run['capture_complete'] ?? 0) !== 1) {
                $this->wpdb->query('ROLLBACK');
                throw new CanonicalImageUploadPersistenceFailed('Cannot prepare batches before capture_complete.');
            }

            $rows = $this->items->list_ordered_for_run($purge_run_id);
            $max = AA_Canonical_Schema::PURGE_PREPARED_BATCH_MAX_ITEMS;
            $index = 0;
            $batch_count = 0;

            foreach ($rows as $row) {
                if (isset($row['batch_seq']) && $row['batch_seq'] !== null && $row['batch_seq'] !== '') {
                    $this->wpdb->query('ROLLBACK');
                    throw new CanonicalImageUploadPersistenceFailed('Prepared batch slot already assigned.');
                }

                $batch_seq = intdiv($index, $max);
                $position = ($index % $max) + 1;
                $this->items->assign_batch_slot((int) $row['id'], $batch_seq, $position);
                $batch_count = $batch_seq + 1;
                $index++;
            }

            $this->runs->mark_batches_prepared($purge_run_id, $batch_count, $updated_at);

            $this->wpdb->last_error = '';
            if ($this->wpdb->query('COMMIT') === false) {
                $this->best_effort_rollback();
                throw new CanonicalImageUploadPersistenceFailed('Failed to COMMIT prepared purge batches.');
            }

            return $batch_count;
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            $this->best_effort_rollback();
            throw $e;
        } catch (\Throwable $e) {
            $this->best_effort_rollback();
            throw new CanonicalImageUploadPersistenceFailed('Purge batch preparation transaction failed.');
        }
    }

    /**
     * @param array{
     *   upload_operation_id:string,
     *   wp_record_id:int,
     *   content_sha256:string,
     *   byte_size:int,
     *   storage_path:string,
     *   source:string
     * } $item
     *
     * @throws CanonicalImageUploadPersistenceFailed
     */
    private function upsert_item(int $purge_run_id, array $item, string $created_at): void {
        $existing = $this->items->find_by_operation($purge_run_id, $item['upload_operation_id']);
        if ($existing === null) {
            $this->items->insert_item([
                'purge_run_id' => $purge_run_id,
                'upload_operation_id' => $item['upload_operation_id'],
                'wp_record_id' => $item['wp_record_id'],
                'content_sha256' => $item['content_sha256'],
                'byte_size' => $item['byte_size'],
                'storage_path' => $item['storage_path'],
                'source' => $item['source'],
                'created_at' => $created_at,
            ]);
            return;
        }

        if (!AA_Canonical_Purge_Inventory_Identity::metadata_matches($existing, $item)) {
            throw new CanonicalImageUploadPersistenceFailed(
                CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA
            );
        }

        $merged = $this->merge_source((string) ($existing['source'] ?? ''), (string) $item['source']);
        if ($merged !== (string) ($existing['source'] ?? '')) {
            $this->items->update_source((int) $existing['id'], $merged);
        }
    }

    private function merge_source(string $existing, string $incoming): string {
        if ($existing === $incoming || $existing === AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_BOTH) {
            return $existing === '' ? $incoming : $existing;
        }
        if ($incoming === AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_BOTH) {
            return AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_BOTH;
        }

        return AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_BOTH;
    }

    private function best_effort_rollback(): void {
        $this->wpdb->last_error = '';
        $this->wpdb->query('ROLLBACK');
    }
}
