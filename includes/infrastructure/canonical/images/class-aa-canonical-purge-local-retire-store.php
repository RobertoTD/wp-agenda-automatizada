<?php
/**
 * TX local de retiro de un registro canónico tras autorización remota (IMG-5 inc. 3).
 *
 * Una transacción: DELETE images/ops del inventario, fail-closed si queda
 * inventario vivo del registro, DELETE registro, touch contenedor, corrida
 * completed. Conserva corrida e inventario. Sin HTTP. Sin BEGIN alrededor
 * de HMAC (el caller cierra el transporte antes).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__, 2) . '/wp/CanonicalSchema.php';
}
if (!class_exists('CanonicalPurgeRunsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeRunsRepository.php';
}
if (!class_exists('CanonicalPurgeInventoryItemsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeInventoryItemsRepository.php';
}
if (!class_exists('CanonicalRecordImagesRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRecordImagesRepository.php';
}
if (!class_exists('CanonicalImageUploadOperationsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalImageUploadOperationsRepository.php';
}
if (!class_exists('CanonicalPurgeLocalRetireResult')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/images/CanonicalPurgeLocalRetireResult.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 3) . '/application/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalRelationalAmbiguousOutcome')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRelationalAmbiguousOutcome.php';
}

class AA_Canonical_Purge_Local_Retire_Store {

    /** @var CanonicalPurgeRunsRepository */
    private $runs;

    /** @var CanonicalPurgeInventoryItemsRepository */
    private $inventory;

    /** @var CanonicalRecordImagesRepository */
    private $images;

    /** @var CanonicalImageUploadOperationsRepository */
    private $operations;

    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb
     */
    public function __construct(
        ?CanonicalPurgeRunsRepository $runs = null,
        ?CanonicalPurgeInventoryItemsRepository $inventory = null,
        ?CanonicalRecordImagesRepository $images = null,
        ?CanonicalImageUploadOperationsRepository $operations = null,
        $wpdb = null
    ) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
        } else {
            global $wpdb;
            $this->wpdb = $wpdb;
        }

        $this->runs = $runs ?: new CanonicalPurgeRunsRepository($this->wpdb);
        $this->inventory = $inventory ?: new CanonicalPurgeInventoryItemsRepository($this->wpdb);
        $this->images = $images ?: new CanonicalRecordImagesRepository($this->wpdb);
        $this->operations = $operations ?: new CanonicalImageUploadOperationsRepository($this->wpdb);
    }

    /**
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    public function retire_record(
        int $family_id,
        int $container_id,
        int $record_id,
        int $purge_run_id
    ): CanonicalPurgeLocalRetireResult {
        $now = gmdate('Y-m-d H:i:s');
        $mutation_possible = false;

        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                throw new CanonicalImageUploadPersistenceFailed('Failed to start local retire transaction.');
            }

            $items = $this->inventory->list_ordered_for_run($purge_run_id);
            foreach ($items as $item) {
                $op = (string) ($item['upload_operation_id'] ?? '');
                if ($op === '') {
                    $this->rollback_confirmed();
                    return CanonicalPurgeLocalRetireResult::failed('inventory_identity_missing');
                }
                $this->images->delete_by_upload_operation_id($op);
                $this->operations->delete_by_operation_id($op);
                $mutation_possible = true;
            }

            $remaining_images = $this->images->count_for_record($record_id);
            $remaining_ops = $this->operations->count_for_record($record_id);
            if ($remaining_images > 0 || $remaining_ops > 0) {
                $this->rollback_confirmed();
                return CanonicalPurgeLocalRetireResult::failed('live_rows_outside_inventory');
            }

            $records_table = AA_Canonical_Schema::records_table_name();
            $this->wpdb->last_error = '';
            $deleted = $this->wpdb->delete(
                $records_table,
                [
                    'container_id' => $container_id,
                    'id' => $record_id,
                ],
                ['%d', '%d']
            );
            if ($deleted === false || $this->wpdb->last_error !== '') {
                $this->rollback_confirmed();
                return CanonicalPurgeLocalRetireResult::failed('record_delete_failed');
            }
            if ((int) $deleted > 0) {
                $mutation_possible = true;
            }

            $this->touch_container($family_id, $container_id, $now);
            $this->runs->mark_completed($purge_run_id, $now);
            $mutation_possible = true;

            $this->wpdb->last_error = '';
            if (!$this->commit_transaction()) {
                $this->best_effort_rollback();
                throw new CanonicalRelationalAmbiguousOutcome(
                    'delete',
                    'record',
                    $record_id,
                    $container_id,
                    'COMMIT failed after local retire.'
                );
            }

            return CanonicalPurgeLocalRetireResult::confirmed();
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            throw $e;
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            if ($mutation_possible) {
                if (!$this->best_effort_rollback()) {
                    throw new CanonicalRelationalAmbiguousOutcome(
                        'delete',
                        'record',
                        $record_id,
                        $container_id,
                        'ROLLBACK failed after possible local retire mutation.'
                    );
                }
            } else {
                $this->best_effort_rollback();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($mutation_possible) {
                if (!$this->best_effort_rollback()) {
                    throw new CanonicalRelationalAmbiguousOutcome(
                        'delete',
                        'record',
                        $record_id,
                        $container_id,
                        'ROLLBACK failed after possible local retire mutation.'
                    );
                }
            } else {
                $this->best_effort_rollback();
            }
            throw new CanonicalImageUploadPersistenceFailed('Local retire transaction failed.');
        }
    }

    /**
     * Extension point for acceptance tests (COMMIT ambiguo).
     */
    protected function commit_transaction(): bool {
        $this->wpdb->last_error = '';

        return $this->wpdb->query('COMMIT') !== false;
    }

    /**
     * @throws CanonicalImageUploadPersistenceFailed
     */
    private function touch_container(int $family_id, int $container_id, string $updated_at): void {
        $table = AA_Canonical_Schema::containers_table_name();
        $this->wpdb->last_error = '';
        $result = $this->wpdb->update(
            $table,
            ['updated_at' => $updated_at],
            [
                'family_id' => $family_id,
                'id' => $container_id,
            ],
            ['%s'],
            ['%d', '%d']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('touch_container failed during local retire.');
        }
    }

    private function rollback_confirmed(): void {
        $this->wpdb->last_error = '';
        $this->wpdb->query('ROLLBACK');
    }

    private function best_effort_rollback(): bool {
        $this->wpdb->last_error = '';

        return $this->wpdb->query('ROLLBACK') !== false;
    }
}
