<?php
/**
 * TX local de retiro canónico tras autorización remota (IMG-5 inc. 3–5).
 *
 * Registro: una TX DELETE images/ops del inventario, fail-closed, DELETE registro.
 * Contenedor: chunks keyset (inventario luego registros) + DELETE contenedor;
 * CASCADE de records nunca ilimitado en una petición. Conserva corrida e inventario.
 * Imagen: DELETE solo image+op inventariadas; conserva registro y demás.
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
if (!class_exists('CanonicalRelationalRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRelationalRepository.php';
}
if (!class_exists('CanonicalRelationalQueryFailed')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRelationalQueryFailed.php';
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

    /** @var CanonicalRelationalRepository */
    private $relational;

    /** @var int */
    private $chunk_size;

    /**
     * @param object|null $wpdb
     */
    public function __construct(
        ?CanonicalPurgeRunsRepository $runs = null,
        ?CanonicalPurgeInventoryItemsRepository $inventory = null,
        ?CanonicalRecordImagesRepository $images = null,
        ?CanonicalImageUploadOperationsRepository $operations = null,
        $wpdb = null,
        int $chunk_size = 50,
        ?CanonicalRelationalRepository $relational = null
    ) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
        } else {
            global $wpdb;
            $this->wpdb = $wpdb;
        }

        $this->chunk_size = $chunk_size >= 1 ? $chunk_size : 50;
        $this->runs = $runs ?: new CanonicalPurgeRunsRepository($this->wpdb);
        $this->inventory = $inventory ?: new CanonicalPurgeInventoryItemsRepository($this->wpdb);
        $this->images = $images ?: new CanonicalRecordImagesRepository($this->wpdb);
        $this->operations = $operations ?: new CanonicalImageUploadOperationsRepository($this->wpdb);
        $this->relational = $relational ?: new CanonicalRelationalRepository($this->wpdb);
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
     * TX local: retira solo la imagen+op inventariadas. Conserva registro y demás.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    public function retire_image(
        int $family_id,
        int $container_id,
        int $record_id,
        int $image_id,
        int $purge_run_id
    ): CanonicalPurgeLocalRetireResult {
        $now = gmdate('Y-m-d H:i:s');
        $mutation_possible = false;

        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                throw new CanonicalImageUploadPersistenceFailed('Failed to start image local retire transaction.');
            }

            $items = $this->inventory->list_ordered_for_run($purge_run_id);
            if (count($items) > 1) {
                $this->rollback_confirmed();
                return CanonicalPurgeLocalRetireResult::failed('inventory_too_large_for_image_scope');
            }

            foreach ($items as $item) {
                $op = (string) ($item['upload_operation_id'] ?? '');
                if ($op === '') {
                    $this->rollback_confirmed();
                    return CanonicalPurgeLocalRetireResult::failed('inventory_identity_missing');
                }
                if ((int) ($item['wp_record_id'] ?? 0) !== $record_id) {
                    $this->rollback_confirmed();
                    return CanonicalPurgeLocalRetireResult::failed('inventory_identity_mismatch');
                }

                $live_image = $this->images->find_by_upload_operation_id($op);
                if ($live_image !== null) {
                    if ((int) ($live_image['id'] ?? 0) !== $image_id
                        || (int) ($live_image['record_id'] ?? 0) !== $record_id
                    ) {
                        $this->rollback_confirmed();
                        return CanonicalPurgeLocalRetireResult::failed('inventory_identity_mismatch');
                    }
                }

                $this->images->delete_by_upload_operation_id($op);
                $this->operations->delete_by_operation_id($op);
                $mutation_possible = true;
            }

            $still = $this->images->find_by_id($image_id);
            if ($still !== null) {
                $this->rollback_confirmed();
                return CanonicalPurgeLocalRetireResult::failed('image_still_present');
            }

            $this->touch_container($family_id, $container_id, $now);
            $this->runs->mark_completed($purge_run_id, $now);
            $mutation_possible = true;

            $this->wpdb->last_error = '';
            if (!$this->commit_transaction()) {
                $this->best_effort_rollback();
                throw new CanonicalRelationalAmbiguousOutcome(
                    'delete',
                    'image',
                    $image_id,
                    $container_id,
                    'COMMIT failed after image local retire.'
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
                        'image',
                        $image_id,
                        $container_id,
                        'ROLLBACK failed after possible image local retire mutation.'
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
                        'image',
                        $image_id,
                        $container_id,
                        'ROLLBACK failed after possible image local retire mutation.'
                    );
                }
            } else {
                $this->best_effort_rollback();
            }
            throw new CanonicalImageUploadPersistenceFailed('Image local retire transaction failed.');
        }
    }

    /**
     * Chunk post-sello de una lista. Keyset (no OFFSET). DELETE + checkpoint
     * en la misma TX. No confirmed si queda inventario, registros o el contenedor.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalRelationalAmbiguousOutcome
     * @throws CanonicalRelationalQueryFailed
     */
    public function retire_container_chunk(
        int $family_id,
        int $container_id,
        int $purge_run_id
    ): CanonicalPurgeLocalRetireResult {
        $now = gmdate('Y-m-d H:i:s');
        $mutation_possible = false;

        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                throw new CanonicalImageUploadPersistenceFailed('Failed to start container local retire transaction.');
            }

            $run = $this->runs->find_by_id($purge_run_id);
            if ($run === null) {
                $this->rollback_confirmed();
                return CanonicalPurgeLocalRetireResult::failed('purge_run_missing');
            }

            $after_inv = (int) ($run['local_retire_after_inventory_id'] ?? 0);
            $after_rec = (int) ($run['local_retire_after_record_id'] ?? 0);
            $limit = $this->chunk_size;

            $inv_page = $this->inventory->list_page_after_id($purge_run_id, $after_inv, $limit);
            $identity_work = count($inv_page);
            if ($identity_work > 0) {
                foreach ($inv_page as $item) {
                    $op = (string) ($item['upload_operation_id'] ?? '');
                    if ($op === '') {
                        $this->rollback_confirmed();
                        return CanonicalPurgeLocalRetireResult::failed('inventory_identity_missing');
                    }
                    $this->images->delete_by_upload_operation_id($op);
                    $this->operations->delete_by_operation_id($op);
                    $mutation_possible = true;
                    $after_inv = (int) ($item['id'] ?? $after_inv);
                }

                $this->runs->persist_local_retire_checkpoints($purge_run_id, $after_inv, $after_rec, $now);
                $mutation_possible = true;

                $more_inv = $this->inventory->list_page_after_id($purge_run_id, $after_inv, 1);
                if ($more_inv !== [] || $identity_work >= $limit) {
                    return $this->commit_chunk_incomplete($container_id);
                }
            }

            $remaining_images = $this->images->count_for_container($container_id);
            $remaining_ops = $this->operations->count_for_container($container_id);
            if ($remaining_images > 0 || $remaining_ops > 0) {
                $this->rollback_confirmed();
                return CanonicalPurgeLocalRetireResult::failed('live_rows_outside_inventory');
            }

            $rec_page = $this->relational->list_record_ids_after($container_id, $after_rec, $limit);
            $record_work = count($rec_page);
            if ($record_work > 0) {
                $records_table = AA_Canonical_Schema::records_table_name();
                foreach ($rec_page as $record_id) {
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
                    $after_rec = $record_id;
                }

                $this->runs->persist_local_retire_checkpoints($purge_run_id, $after_inv, $after_rec, $now);
                $mutation_possible = true;

                $more_rec = $this->relational->list_record_ids_after($container_id, $after_rec, 1);
                if ($more_rec !== []) {
                    return $this->commit_chunk_incomplete($container_id);
                }
            }

            $containers_table = AA_Canonical_Schema::containers_table_name();
            $this->wpdb->last_error = '';
            $deleted_container = $this->wpdb->delete(
                $containers_table,
                [
                    'family_id' => $family_id,
                    'id' => $container_id,
                ],
                ['%d', '%d']
            );
            if ($deleted_container === false || $this->wpdb->last_error !== '') {
                $this->rollback_confirmed();
                return CanonicalPurgeLocalRetireResult::failed('container_delete_failed');
            }
            if ((int) $deleted_container > 0) {
                $mutation_possible = true;
            }

            $this->runs->mark_completed($purge_run_id, $now);
            $mutation_possible = true;

            $this->wpdb->last_error = '';
            if (!$this->commit_transaction()) {
                $this->best_effort_rollback();
                throw new CanonicalRelationalAmbiguousOutcome(
                    'delete',
                    'container',
                    $container_id,
                    null,
                    'COMMIT failed after container local retire.'
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
                        'container',
                        $container_id,
                        null,
                        'ROLLBACK failed after possible container local retire mutation.'
                    );
                }
            } else {
                $this->best_effort_rollback();
            }
            throw $e;
        } catch (CanonicalRelationalQueryFailed $e) {
            if ($mutation_possible) {
                if (!$this->best_effort_rollback()) {
                    throw new CanonicalRelationalAmbiguousOutcome(
                        'delete',
                        'container',
                        $container_id,
                        null,
                        'ROLLBACK failed after possible container local retire mutation.'
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
                        'container',
                        $container_id,
                        null,
                        'ROLLBACK failed after possible container local retire mutation.'
                    );
                }
            } else {
                $this->best_effort_rollback();
            }
            throw new CanonicalImageUploadPersistenceFailed('Container local retire transaction failed.');
        }
    }

    /**
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    private function commit_chunk_incomplete(int $container_id): CanonicalPurgeLocalRetireResult {
        $this->wpdb->last_error = '';
        if (!$this->commit_transaction()) {
            $this->best_effort_rollback();
            throw new CanonicalRelationalAmbiguousOutcome(
                'delete',
                'container',
                $container_id,
                null,
                'COMMIT failed after container local retire chunk.'
            );
        }

        return CanonicalPurgeLocalRetireResult::incomplete();
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
