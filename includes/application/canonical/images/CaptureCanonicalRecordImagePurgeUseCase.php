<?php
/**
 * Captura puntual de inventario para retiro de una imagen (IMG-5 inc. 5).
 *
 * No pagina el registro entero. 0 o 1 identidad (upload_operation_id).
 * Metadatos incongruentes entre imagen y op → conflicto, sin inventar.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

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
if (!class_exists('CanonicalPurgeCaptureResult')) {
    require_once __DIR__ . '/CanonicalPurgeCaptureResult.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadSchemaNotReady.php';
}

final class CaptureCanonicalRecordImagePurgeUseCase {

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

    public function __construct(
        ?CanonicalPurgeRunsRepository $runs = null,
        ?CanonicalRecordImagesRepository $images = null,
        ?CanonicalImageUploadOperationsRepository $operations = null,
        ?CanonicalPurgeInventoryItemsRepository $inventory = null,
        ?AA_Canonical_Purge_Capture_Store $store = null
    ) {
        $this->runs = $runs ?: new CanonicalPurgeRunsRepository();
        $this->images = $images ?: new CanonicalRecordImagesRepository();
        $this->operations = $operations ?: new CanonicalImageUploadOperationsRepository();
        $this->inventory = $inventory ?: new CanonicalPurgeInventoryItemsRepository();
        $this->store = $store ?: new AA_Canonical_Purge_Capture_Store($this->runs, $this->inventory);
    }

    /**
     * Captura bajo GET_LOCK de contenedor ya adquirido. No adquiere ni libera.
     *
     * @param array<string, mixed>|null $image_row fila viva o null si ya ausente
     */
    public function execute_with_held_lock(
        string $family_key,
        int $image_id,
        int $record_id,
        int $container_id,
        ?array $image_row
    ): CanonicalPurgeCaptureResult {
        try {
            $run = $this->open_or_resume($family_key, $image_id, $record_id, $container_id);
            if ($run instanceof CanonicalPurgeCaptureResult) {
                return $run;
            }

            if ((int) ($run['capture_complete'] ?? 0) === 1) {
                if ((int) ($run['batches_prepared'] ?? 0) !== 1) {
                    $this->store->prepare_batches_after_capture((int) $run['id'], gmdate('Y-m-d H:i:s'));
                    $run = $this->runs->find_by_id((int) $run['id']);
                }

                return $this->result_from_run($run ?? []);
            }

            if ((string) ($run['capture_status'] ?? '') === AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT) {
                return $this->result_from_run($run);
            }

            $now = gmdate('Y-m-d H:i:s');
            $built = $this->build_single_item($image_row, $record_id);
            if ($built['conflict_code'] !== null) {
                $this->store->persist_captured_page(
                    (int) $run['id'],
                    [],
                    [
                        'images_read_after_id' => 0,
                        'ops_read_after_operation_id' => null,
                        'images_source_exhausted' => 1,
                        'ops_source_exhausted' => 1,
                        'capture_status' => AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT,
                        'capture_conflict_code' => $built['conflict_code'],
                        'capture_complete' => 0,
                        'status' => CanonicalPurgeRunsRepository::STATUS_INCOMPLETE,
                        'updated_at' => $now,
                    ]
                );
                $run = $this->runs->find_by_id((int) $run['id']);

                return $this->result_from_run($run ?? []);
            }

            $items = $built['item'] !== null ? [$built['item']] : [];
            $this->store->persist_captured_page(
                (int) $run['id'],
                $items,
                [
                    'images_read_after_id' => $image_row !== null ? (int) ($image_row['id'] ?? 0) : 0,
                    'ops_read_after_operation_id' => $built['item']['upload_operation_id'] ?? null,
                    'images_source_exhausted' => 1,
                    'ops_source_exhausted' => 1,
                    'capture_status' => AA_Canonical_Schema::PURGE_CAPTURE_STATUS_PENDING,
                    'capture_conflict_code' => null,
                    'capture_complete' => 1,
                    'status' => CanonicalPurgeRunsRepository::STATUS_INCOMPLETE,
                    'updated_at' => $now,
                ]
            );
            $this->store->prepare_batches_after_capture((int) $run['id'], $now);
            $run = $this->runs->find_by_id((int) $run['id']);

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
    private function open_or_resume(
        string $family_key,
        int $image_id,
        int $record_id,
        int $container_id
    ) {
        $existing = $this->runs->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_IMAGE,
            $image_id
        );
        if ($existing !== null) {
            return $existing;
        }

        $overlap = $this->runs->find_overlapping_open_run(
            CanonicalPurgeRunsRepository::SCOPE_IMAGE,
            $image_id,
            $container_id,
            $record_id
        );
        if ($overlap !== null) {
            return CanonicalPurgeCaptureResult::scope_overlap();
        }

        $now = gmdate('Y-m-d H:i:s');

        return $this->runs->insert_open_run([
            'scope' => CanonicalPurgeRunsRepository::SCOPE_IMAGE,
            'target_id' => $image_id,
            'container_id' => $container_id,
            'record_id' => $record_id,
            'family_key' => $family_key,
            'mandate_id' => AA_Canonical_Purge_Inventory_Identity::new_uuid_v4(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed>|null $image_row
     * @return array{item:?array<string,mixed>,conflict_code:?string}
     */
    private function build_single_item(?array $image_row, int $expected_record_id): array {
        $image_norm = null;
        if ($image_row !== null) {
            if ((int) ($image_row['record_id'] ?? 0) !== $expected_record_id) {
                return [
                    'item' => null,
                    'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA,
                ];
            }
            $image_norm = AA_Canonical_Purge_Inventory_Identity::try_normalize_row([
                'upload_operation_id' => $image_row['upload_operation_id'] ?? null,
                'wp_record_id' => $image_row['record_id'] ?? null,
                'content_sha256' => $image_row['content_sha256'] ?? null,
                'byte_size' => $image_row['byte_size'] ?? null,
                'storage_path' => $image_row['storage_path'] ?? null,
            ]);
            if ($image_norm === null) {
                return [
                    'item' => null,
                    'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_ITEM_INCOMPLETE,
                ];
            }
        }

        $op_norm = null;
        $op_id = $image_norm['upload_operation_id'] ?? null;
        if ($op_id === null && $image_row === null) {
            return ['item' => null, 'conflict_code' => null];
        }

        if (is_string($op_id) && $op_id !== '') {
            $op_row = $this->operations->find_by_operation_id($op_id);
            if ($op_row !== null) {
                $status = (string) ($op_row['status'] ?? '');
                if ($status === AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED
                    || $status === AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED
                ) {
                    if ((int) ($op_row['record_id'] ?? 0) !== $expected_record_id) {
                        return [
                            'item' => null,
                            'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA,
                        ];
                    }
                    $op_norm = AA_Canonical_Purge_Inventory_Identity::try_normalize_row([
                        'upload_operation_id' => $op_row['upload_operation_id'] ?? null,
                        'wp_record_id' => $op_row['record_id'] ?? null,
                        'content_sha256' => $op_row['content_sha256'] ?? null,
                        'byte_size' => $op_row['byte_size'] ?? null,
                        'storage_path' => $op_row['storage_path'] ?? null,
                    ]);
                    if ($op_norm === null) {
                        return [
                            'item' => null,
                            'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_ITEM_INCOMPLETE,
                        ];
                    }
                }
            }
        }

        if ($image_norm === null && $op_norm === null) {
            return ['item' => null, 'conflict_code' => null];
        }

        if ($image_norm !== null && $op_norm !== null
            && !AA_Canonical_Purge_Inventory_Identity::metadata_matches($image_norm, $op_norm)
        ) {
            return [
                'item' => null,
                'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA,
            ];
        }

        $base = $image_norm !== null ? $image_norm : $op_norm;
        $source = AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_IMAGE;
        if ($image_norm !== null && $op_norm !== null) {
            $source = AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_BOTH;
        } elseif ($op_norm !== null) {
            $source = AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_OPERATION;
        }

        return [
            'item' => [
                'upload_operation_id' => $base['upload_operation_id'],
                'wp_record_id' => $base['wp_record_id'],
                'content_sha256' => $base['content_sha256'],
                'byte_size' => $base['byte_size'],
                'storage_path' => $base['storage_path'],
                'source' => $source,
            ],
            'conflict_code' => null,
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
}
