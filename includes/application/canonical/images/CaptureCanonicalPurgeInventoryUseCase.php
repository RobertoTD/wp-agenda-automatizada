<?php
/**
 * Capture Canonical Purge Inventory — abrir/reanudar corrida y capturar páginas (IMG-5 inc. 2).
 *
 * GET_LOCK del contenedor hasta confirmar la fila durable y terminar las páginas
 * de esta petición. Sin HTTP. Sin TX abierta fuera de cada página.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}
if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/wp/CanonicalSchema.php';
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
if (!class_exists('CanonicalRecordImagesRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRecordImagesRepository.php';
}
if (!class_exists('CanonicalImageUploadOperationsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalImageUploadOperationsRepository.php';
}
if (!class_exists('AA_Canonical_Purge_Capture_Store')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/canonical/images/class-aa-canonical-purge-capture-store.php';
}
if (!class_exists('CaptureCanonicalPurgeInventoryCommand')) {
    require_once __DIR__ . '/CaptureCanonicalPurgeInventoryCommand.php';
}
if (!class_exists('CanonicalPurgeCaptureResult')) {
    require_once __DIR__ . '/CanonicalPurgeCaptureResult.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadSchemaNotReady.php';
}

final class CaptureCanonicalPurgeInventoryUseCase {

    /** @var CanonicalPurgeRunsRepository */
    private $runs;

    /** @var CanonicalRecordImagesRepository */
    private $images;

    /** @var CanonicalImageUploadOperationsRepository */
    private $operations;

    /** @var CanonicalPurgeInventoryItemsRepository */
    private $inventory;

    /** @var AA_Canonical_Purge_Capture_Store */
    private $store;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    public function __construct(
        ?CanonicalPurgeRunsRepository $runs = null,
        ?CanonicalRecordImagesRepository $images = null,
        ?CanonicalImageUploadOperationsRepository $operations = null,
        ?CanonicalPurgeInventoryItemsRepository $inventory = null,
        ?AA_Canonical_Purge_Capture_Store $store = null,
        ?AA_Expediente_Aggregate_Lock $lock = null
    ) {
        $this->runs = $runs ?: new CanonicalPurgeRunsRepository();
        $this->images = $images ?: new CanonicalRecordImagesRepository();
        $this->operations = $operations ?: new CanonicalImageUploadOperationsRepository();
        $this->inventory = $inventory ?: new CanonicalPurgeInventoryItemsRepository();
        $this->store = $store ?: new AA_Canonical_Purge_Capture_Store($this->runs, $this->inventory);
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
    }

    public function execute(CaptureCanonicalPurgeInventoryCommand $command): CanonicalPurgeCaptureResult {
        $lease = $this->lock->acquire(
            AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
            $command->container_id(),
            AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
        );
        if (is_wp_error($lease)) {
            $code = $lease->get_error_code();
            if ($code === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                return CanonicalPurgeCaptureResult::resource_busy();
            }
            if ($code === AA_Expediente_Aggregate_Lock::ERROR_INVALID_SCOPE) {
                return CanonicalPurgeCaptureResult::invalid_scope();
            }

            return CanonicalPurgeCaptureResult::persistence_failed();
        }

        try {
            return $this->execute_with_held_lock($lease, $command);
        } finally {
            $this->lock->release($lease);
        }
    }

    /**
     * Captura reutilizando un GET_LOCK de contenedor ya adquirido.
     * No adquiere ni libera el lock.
     *
     * @param AA_Expediente_Aggregate_Lock_Lease $lease
     */
    public function execute_with_held_lock($lease, CaptureCanonicalPurgeInventoryCommand $command): CanonicalPurgeCaptureResult {
        if (!($lease instanceof AA_Expediente_Aggregate_Lock_Lease)
            || $lease->scope_kind() !== AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER
            || $lease->scope_id() !== $command->container_id()
        ) {
            return CanonicalPurgeCaptureResult::invalid_scope();
        }

        $held = $this->lock->assert_held($lease);
        if (is_wp_error($held)) {
            return CanonicalPurgeCaptureResult::persistence_failed();
        }

        try {
            $run = $this->open_or_resume($command);
            if ($run instanceof CanonicalPurgeCaptureResult) {
                return $run;
            }

            $now = gmdate('Y-m-d H:i:s');
            $pages = 0;
            while ($pages < $command->max_pages()) {
                $held = $this->lock->assert_held($lease);
                if (is_wp_error($held)) {
                    return CanonicalPurgeCaptureResult::persistence_failed();
                }

                $run = $this->runs->find_by_id((int) $run['id']);
                if ($run === null) {
                    return CanonicalPurgeCaptureResult::persistence_failed();
                }

                if ((string) ($run['capture_status'] ?? '') === AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT) {
                    return $this->result_from_run($run);
                }

                if ((int) ($run['capture_complete'] ?? 0) === 1) {
                    if ((int) ($run['batches_prepared'] ?? 0) !== 1) {
                        $this->store->prepare_batches_after_capture((int) $run['id'], $now);
                        $run = $this->runs->find_by_id((int) $run['id']);
                    }

                    return $this->result_from_run($run ?? []);
                }

                $advanced = $this->capture_one_source_page($run, $command->scope(), $command->target_id(), $now);
                if (!$advanced) {
                    break;
                }
                $pages++;
            }

            $run = $this->runs->find_by_id((int) $run['id']);
            if ($run === null) {
                return CanonicalPurgeCaptureResult::persistence_failed();
            }

            if ((string) ($run['capture_status'] ?? '') !== AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT
                && (int) ($run['images_source_exhausted'] ?? 0) === 1
                && (int) ($run['ops_source_exhausted'] ?? 0) === 1
                && (int) ($run['capture_complete'] ?? 0) !== 1
            ) {
                $this->runs->update_capture_progress((int) $run['id'], [
                    'images_read_after_id' => (int) $run['images_read_after_id'],
                    'ops_read_after_operation_id' => $this->nullable_op_id($run['ops_read_after_operation_id'] ?? null),
                    'images_source_exhausted' => 1,
                    'ops_source_exhausted' => 1,
                    'capture_status' => AA_Canonical_Schema::PURGE_CAPTURE_STATUS_PENDING,
                    'capture_conflict_code' => null,
                    'capture_complete' => 1,
                    'status' => CanonicalPurgeRunsRepository::STATUS_INCOMPLETE,
                    'updated_at' => $now,
                ]);
                $this->store->prepare_batches_after_capture((int) $run['id'], $now);
                $run = $this->runs->find_by_id((int) $run['id']);
            }

            return $this->result_from_run($run ?? []);
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return CanonicalPurgeCaptureResult::schema_not_ready();
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            if ($e->getMessage() === CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA) {
                return CanonicalPurgeCaptureResult::conflict([
                    'capture_conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA,
                ]);
            }

            return CanonicalPurgeCaptureResult::persistence_failed();
        }
    }

    /**
     * @return array<string, mixed>|CanonicalPurgeCaptureResult
     */
    private function open_or_resume(CaptureCanonicalPurgeInventoryCommand $command) {
        $existing = $this->runs->find_open_by_scope_target($command->scope(), $command->target_id());
        if ($existing !== null) {
            return $existing;
        }

        $overlap = $this->runs->find_overlapping_open_run(
            $command->scope(),
            $command->target_id(),
            $command->container_id()
        );
        if ($overlap !== null) {
            return CanonicalPurgeCaptureResult::scope_overlap();
        }

        $now = gmdate('Y-m-d H:i:s');

        return $this->runs->insert_open_run([
            'scope' => $command->scope(),
            'target_id' => $command->target_id(),
            'container_id' => $command->container_id(),
            'family_key' => $command->family_key(),
            'mandate_id' => AA_Canonical_Purge_Inventory_Identity::new_uuid_v4(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $run
     */
    private function capture_one_source_page(array $run, string $scope, int $target_id, string $now): bool {
        $purge_run_id = (int) $run['id'];
        $page_size = AA_Canonical_Schema::PURGE_PREPARED_BATCH_MAX_ITEMS;

        if ((int) ($run['images_source_exhausted'] ?? 0) !== 1) {
            return $this->capture_images_page($run, $scope, $target_id, $page_size, $now);
        }

        if ((int) ($run['ops_source_exhausted'] ?? 0) !== 1) {
            return $this->capture_ops_page($run, $scope, $target_id, $page_size, $now);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function capture_images_page(array $run, string $scope, int $target_id, int $page_size, string $now): bool {
        $after = (int) ($run['images_read_after_id'] ?? 0);
        $rows = $this->images->list_capture_page_after_id($scope, $target_id, $after, $page_size);
        $built = $this->build_page_items($run, $rows, AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_IMAGE);
        $exhausted = count($rows) < $page_size ? 1 : 0;
        $last_id = $after;
        foreach ($rows as $row) {
            $last_id = max($last_id, (int) ($row['id'] ?? 0));
        }

        $conflict = $built['conflict_code'];
        if ($conflict !== null && isset($built['last_good_image_id'])) {
            $exhausted = 0;
            $last_id = (int) $built['last_good_image_id'];
        } elseif ($conflict !== null) {
            $exhausted = 0;
            $last_id = $after;
        }

        $this->store->persist_captured_page(
            (int) $run['id'],
            $built['items'],
            [
                'images_read_after_id' => $last_id,
                'ops_read_after_operation_id' => $this->nullable_op_id($run['ops_read_after_operation_id'] ?? null),
                'images_source_exhausted' => $exhausted,
                'ops_source_exhausted' => (int) ($run['ops_source_exhausted'] ?? 0),
                'capture_status' => $conflict !== null
                    ? AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT
                    : AA_Canonical_Schema::PURGE_CAPTURE_STATUS_PENDING,
                'capture_conflict_code' => $conflict,
                'capture_complete' => 0,
                'status' => CanonicalPurgeRunsRepository::STATUS_INCOMPLETE,
                'updated_at' => $now,
            ]
        );

        return true;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function capture_ops_page(array $run, string $scope, int $target_id, int $page_size, string $now): bool {
        $after = $this->nullable_op_id($run['ops_read_after_operation_id'] ?? null);
        $rows = $this->operations->list_capture_page_after_operation_id($scope, $target_id, $after, $page_size);
        $built = $this->build_page_items($run, $rows, AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_OPERATION);
        $exhausted = count($rows) < $page_size ? 1 : 0;
        $last_op = $after;
        foreach ($rows as $row) {
            $op = AA_Canonical_Purge_Inventory_Identity::normalize_upload_operation_id($row['upload_operation_id'] ?? null);
            if ($op !== null && ($last_op === null || $op > $last_op)) {
                $last_op = $op;
            }
        }

        $conflict = $built['conflict_code'];
        if ($conflict !== null && isset($built['last_good_operation_id'])) {
            $exhausted = 0;
            $last_op = (string) $built['last_good_operation_id'];
        } elseif ($conflict !== null) {
            $exhausted = 0;
            $last_op = $after;
        }

        $this->store->persist_captured_page(
            (int) $run['id'],
            $built['items'],
            [
                'images_read_after_id' => (int) ($run['images_read_after_id'] ?? 0),
                'ops_read_after_operation_id' => $last_op,
                'images_source_exhausted' => (int) ($run['images_source_exhausted'] ?? 0),
                'ops_source_exhausted' => $exhausted,
                'capture_status' => $conflict !== null
                    ? AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT
                    : AA_Canonical_Schema::PURGE_CAPTURE_STATUS_PENDING,
                'capture_conflict_code' => $conflict,
                'capture_complete' => 0,
                'status' => CanonicalPurgeRunsRepository::STATUS_INCOMPLETE,
                'updated_at' => $now,
            ]
        );

        return true;
    }

    /**
     * @param array<string, mixed> $run
     * @param list<array<string, mixed>> $rows
     * @return array{items:list<array<string,mixed>>,conflict_code:?string,last_good_image_id?:int,last_good_operation_id?:string}
     */
    private function build_page_items(array $run, array $rows, string $source): array {
        $items = [];
        $purge_run_id = (int) $run['id'];
        $last_good_image_id = null;
        $last_good_operation_id = null;

        foreach ($rows as $row) {
            $normalized = AA_Canonical_Purge_Inventory_Identity::try_normalize_row([
                'upload_operation_id' => $row['upload_operation_id'] ?? null,
                'wp_record_id' => $row['record_id'] ?? ($row['wp_record_id'] ?? null),
                'content_sha256' => $row['content_sha256'] ?? null,
                'byte_size' => $row['byte_size'] ?? null,
                'storage_path' => $row['storage_path'] ?? null,
            ]);
            if ($normalized === null) {
                return [
                    'items' => $items,
                    'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_ITEM_INCOMPLETE,
                    'last_good_image_id' => $last_good_image_id,
                    'last_good_operation_id' => $last_good_operation_id,
                ];
            }

            $existing = $this->inventory->find_by_operation($purge_run_id, $normalized['upload_operation_id']);
            if ($existing !== null && !AA_Canonical_Purge_Inventory_Identity::metadata_matches($existing, $normalized)) {
                return [
                    'items' => $items,
                    'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA,
                    'last_good_image_id' => $last_good_image_id,
                    'last_good_operation_id' => $last_good_operation_id,
                ];
            }

            $items[] = [
                'upload_operation_id' => $normalized['upload_operation_id'],
                'wp_record_id' => $normalized['wp_record_id'],
                'content_sha256' => $normalized['content_sha256'],
                'byte_size' => $normalized['byte_size'],
                'storage_path' => $normalized['storage_path'],
                'source' => $source,
            ];

            if (isset($row['id'])) {
                $last_good_image_id = (int) $row['id'];
            }
            $last_good_operation_id = $normalized['upload_operation_id'];
        }

        return [
            'items' => $items,
            'conflict_code' => null,
            'last_good_image_id' => $last_good_image_id,
            'last_good_operation_id' => $last_good_operation_id,
        ];
    }

    /**
     * @param array<string, mixed> $run
     */
    private function result_from_run(array $run): CanonicalPurgeCaptureResult {
        $payload = [
            'purge_run_id' => (int) ($run['id'] ?? 0),
            'mandate_id' => (string) ($run['mandate_id'] ?? ''),
            'scope' => (string) ($run['scope'] ?? ''),
            'target_id' => (int) ($run['target_id'] ?? 0),
            'container_id' => (int) ($run['container_id'] ?? 0),
            'capture_complete' => (int) ($run['capture_complete'] ?? 0) === 1,
            'batches_prepared' => (int) ($run['batches_prepared'] ?? 0) === 1,
            'prepared_batch_count' => (int) ($run['prepared_batch_count'] ?? 0),
            'images_source_exhausted' => (int) ($run['images_source_exhausted'] ?? 0) === 1,
            'ops_source_exhausted' => (int) ($run['ops_source_exhausted'] ?? 0) === 1,
            'capture_status' => (string) ($run['capture_status'] ?? ''),
            'capture_conflict_code' => $run['capture_conflict_code'] ?? null,
            'last_accepted_batch_seq' => $run['last_accepted_batch_seq'] ?? null,
            'sealed_at' => $run['sealed_at'] ?? null,
        ];

        if ((string) ($run['capture_status'] ?? '') === AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT) {
            return CanonicalPurgeCaptureResult::conflict($payload);
        }
        if (!empty($payload['capture_complete'])) {
            return CanonicalPurgeCaptureResult::capture_complete($payload);
        }

        return CanonicalPurgeCaptureResult::capture_progress($payload);
    }

    private function nullable_op_id($raw): ?string {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        return strtolower(trim($raw));
    }
}
