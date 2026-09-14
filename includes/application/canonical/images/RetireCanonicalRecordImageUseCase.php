<?php
/**
 * Retire Canonical Record Image — retiro de UNA imagen (registro vivo) vía mandatos (IMG-5 inc. 5).
 *
 * Lock del contenedor durante la petición (incluido HTTP). Sin TX SQL abierta
 * durante HMAC. Skip HMAC si inventario vacío. No exige images.is_ready.
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
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
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
if (!class_exists('AA_Canonical_Purge_Local_Retire_Store')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/canonical/images/class-aa-canonical-purge-local-retire-store.php';
}
if (!class_exists('CaptureCanonicalRecordImagePurgeUseCase')) {
    require_once __DIR__ . '/CaptureCanonicalRecordImagePurgeUseCase.php';
}
if (!class_exists('CanonicalPurgeCaptureResult')) {
    require_once __DIR__ . '/CanonicalPurgeCaptureResult.php';
}
if (!class_exists('RetireCanonicalRecordImageCommand')) {
    require_once __DIR__ . '/RetireCanonicalRecordImageCommand.php';
}
if (!class_exists('RetireCanonicalRecordImageResult')) {
    require_once __DIR__ . '/RetireCanonicalRecordImageResult.php';
}
if (!class_exists('CanonicalPurgeLocalRetireResult')) {
    require_once __DIR__ . '/CanonicalPurgeLocalRetireResult.php';
}
if (!class_exists('CanonicalPurgeRemoteMandateAdvancer')) {
    require_once __DIR__ . '/CanonicalPurgeRemoteMandateAdvancer.php';
}
if (!class_exists('CanonicalPurgeRemoteAdvanceResult')) {
    require_once __DIR__ . '/CanonicalPurgeRemoteAdvanceResult.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadSchemaNotReady.php';
}

final class RetireCanonicalRecordImageUseCase {

    /** @var CanonicalRelationalRepository */
    private $relational;

    /** @var CaptureCanonicalRecordImagePurgeUseCase */
    private $capture;

    /** @var CanonicalPurgeRunsRepository */
    private $runs;

    /** @var CanonicalPurgeInventoryItemsRepository */
    private $inventory;

    /** @var CanonicalRecordImagesRepository */
    private $images;

    /** @var AA_Canonical_Purge_Local_Retire_Store */
    private $local_store;

    /** @var AA_Expediente_Attachments_Backend_Client */
    private $client;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    /** @var CanonicalPurgeRemoteMandateAdvancer */
    private $remote;

    public function __construct(
        ?CanonicalRelationalRepository $relational = null,
        ?CaptureCanonicalRecordImagePurgeUseCase $capture = null,
        ?CanonicalPurgeRunsRepository $runs = null,
        ?CanonicalPurgeInventoryItemsRepository $inventory = null,
        ?CanonicalRecordImagesRepository $images = null,
        ?AA_Canonical_Purge_Local_Retire_Store $local_store = null,
        ?AA_Expediente_Attachments_Backend_Client $client = null,
        ?AA_Expediente_Aggregate_Lock $lock = null,
        ?CanonicalPurgeRemoteMandateAdvancer $remote = null
    ) {
        $this->relational = $relational ?: new CanonicalRelationalRepository();
        $this->runs = $runs ?: new CanonicalPurgeRunsRepository();
        $this->inventory = $inventory ?: new CanonicalPurgeInventoryItemsRepository();
        $this->images = $images ?: new CanonicalRecordImagesRepository();
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
        $this->capture = $capture ?: new CaptureCanonicalRecordImagePurgeUseCase(
            $this->runs,
            $this->images,
            null,
            $this->inventory,
            null
        );
        $this->local_store = $local_store ?: new AA_Canonical_Purge_Local_Retire_Store(
            $this->runs,
            $this->inventory,
            $this->images,
            null
        );
        $this->client = $client ?: new AA_Expediente_Attachments_Backend_Client();
        $this->remote = $remote ?: new CanonicalPurgeRemoteMandateAdvancer(
            $this->runs,
            $this->inventory,
            $this->client
        );
    }

    public function execute(RetireCanonicalRecordImageCommand $command): RetireCanonicalRecordImageResult {
        try {
            $family_id = $this->relational->resolve_family_id($command->family_key());
            if ($family_id === null) {
                return RetireCanonicalRecordImageResult::container_not_found();
            }

            $image_id = $command->image_id();
            $open = $this->runs->find_open_by_scope_target(
                CanonicalPurgeRunsRepository::SCOPE_IMAGE,
                $image_id
            );

            if ($command->is_cancel()) {
                return $this->cancel_with_resolved_context($command, $family_id, $open);
            }

            $image = $this->images->find_by_id($image_id);
            $resolved = $this->resolve_context($command, $family_id, $image, $open);
            if ($resolved instanceof RetireCanonicalRecordImageResult) {
                return $resolved;
            }

            $lease = $this->lock->acquire(
                AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
                $resolved['container_id'],
                AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
            );
            if (is_wp_error($lease)) {
                $code = $lease->get_error_code();
                if ($code === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                    return RetireCanonicalRecordImageResult::resource_busy();
                }

                return RetireCanonicalRecordImageResult::persistence_failed();
            }

            try {
                $held = $this->lock->assert_held($lease);
                if (is_wp_error($held)) {
                    return RetireCanonicalRecordImageResult::persistence_failed();
                }

                return $this->retire_under_lock(
                    $command,
                    $family_id,
                    $resolved['record_id'],
                    $resolved['container_id'],
                    $resolved['image'],
                    $open
                );
            } finally {
                $this->lock->release($lease);
            }
        } catch (CanonicalRelationalQueryFailed $e) {
            return RetireCanonicalRecordImageResult::persistence_failed();
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return RetireCanonicalRecordImageResult::persistence_failed();
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            return RetireCanonicalRecordImageResult::persistence_failed();
        }
    }

    /**
     * @param array<string, mixed>|null $image
     * @param array<string, mixed>|null $open
     * @return array{record_id:int,container_id:int,image:?array<string,mixed>}|RetireCanonicalRecordImageResult
     */
    private function resolve_context(
        RetireCanonicalRecordImageCommand $command,
        int $family_id,
        ?array $image,
        ?array $open
    ) {
        $image_id = $command->image_id();

        if ($open !== null) {
            if (!$this->run_matches_command($open, $command)) {
                return RetireCanonicalRecordImageResult::forbidden();
            }
            $record_id = (int) ($open['record_id'] ?? 0);
            $container_id = (int) ($open['container_id'] ?? 0);
            if ($record_id < 1 || $container_id < 1) {
                return RetireCanonicalRecordImageResult::persistence_failed();
            }
            $container = $this->relational->find_container($family_id, $container_id);
            if ($container === null) {
                return RetireCanonicalRecordImageResult::container_not_found($container_id);
            }
            if ($image !== null && (int) ($image['record_id'] ?? 0) !== $record_id) {
                return RetireCanonicalRecordImageResult::forbidden();
            }

            return [
                'record_id' => $record_id,
                'container_id' => $container_id,
                'image' => $image,
            ];
        }

        if ($image === null) {
            $completed = $this->runs->find_latest_completed_by_scope_target(
                CanonicalPurgeRunsRepository::SCOPE_IMAGE,
                $image_id
            );
            if ($completed !== null && $this->run_matches_command($completed, $command)) {
                $record_id = (int) ($completed['record_id'] ?? 0);
                $container_id = (int) ($completed['container_id'] ?? 0);
                if ($record_id >= 1 && $container_id >= 1) {
                    return RetireCanonicalRecordImageResult::confirmed($image_id, $record_id, $container_id);
                }
            }

            return RetireCanonicalRecordImageResult::image_not_found($image_id);
        }

        $record_id = (int) ($image['record_id'] ?? 0);
        if ($record_id < 1) {
            return RetireCanonicalRecordImageResult::image_not_found($image_id);
        }

        $record = $this->relational->find_record_by_id($record_id);
        if ($record === null) {
            return RetireCanonicalRecordImageResult::image_not_found($image_id, $record_id);
        }
        $container_id = (int) ($record['container_id'] ?? 0);
        if ($container_id < 1) {
            return RetireCanonicalRecordImageResult::persistence_failed();
        }

        $container = $this->relational->find_container($family_id, $container_id);
        if ($container === null) {
            return RetireCanonicalRecordImageResult::container_not_found($container_id);
        }

        return [
            'record_id' => $record_id,
            'container_id' => $container_id,
            'image' => $image,
        ];
    }

    /**
     * @param array<string, mixed>|null $open
     */
    private function cancel_with_resolved_context(
        RetireCanonicalRecordImageCommand $command,
        int $family_id,
        ?array $open
    ): RetireCanonicalRecordImageResult {
        if ($open === null) {
            return RetireCanonicalRecordImageResult::image_not_found($command->image_id());
        }
        if (!$this->run_matches_command($open, $command)) {
            return RetireCanonicalRecordImageResult::forbidden();
        }

        $record_id = (int) ($open['record_id'] ?? 0);
        $container_id = (int) ($open['container_id'] ?? 0);
        if ($record_id < 1 || $container_id < 1) {
            return RetireCanonicalRecordImageResult::persistence_failed();
        }

        $container = $this->relational->find_container($family_id, $container_id);
        if ($container === null) {
            return RetireCanonicalRecordImageResult::container_not_found($container_id);
        }

        $lease = $this->lock->acquire(
            AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
            $container_id,
            AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
        );
        if (is_wp_error($lease)) {
            if ($lease->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                return RetireCanonicalRecordImageResult::resource_busy();
            }

            return RetireCanonicalRecordImageResult::persistence_failed();
        }

        try {
            $held = $this->lock->assert_held($lease);
            if (is_wp_error($held)) {
                return RetireCanonicalRecordImageResult::persistence_failed();
            }

            $open = $this->runs->find_open_by_scope_target(
                CanonicalPurgeRunsRepository::SCOPE_IMAGE,
                $command->image_id()
            );
            if ($open === null) {
                return RetireCanonicalRecordImageResult::image_not_found(
                    $command->image_id(),
                    $record_id,
                    $container_id
                );
            }
            if (!$this->run_matches_command($open, $command)) {
                return RetireCanonicalRecordImageResult::forbidden();
            }
            if (CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open)) {
                return RetireCanonicalRecordImageResult::cancel_rejected(
                    $command->image_id(),
                    $record_id,
                    $container_id
                );
            }

            $this->runs->mark_cancelled((int) $open['id'], gmdate('Y-m-d H:i:s'));

            return RetireCanonicalRecordImageResult::cancelled(
                $command->image_id(),
                $record_id,
                $container_id
            );
        } finally {
            $this->lock->release($lease);
        }
    }

    /**
     * @param array<string, mixed>|null $image
     * @param array<string, mixed>|null $open_before
     */
    private function retire_under_lock(
        RetireCanonicalRecordImageCommand $command,
        int $family_id,
        int $record_id,
        int $container_id,
        ?array $image,
        ?array $open_before
    ): RetireCanonicalRecordImageResult {
        $image_id = $command->image_id();

        $open = $this->runs->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_IMAGE,
            $image_id
        );
        if ($open !== null && !$this->run_matches_command($open, $command)) {
            return RetireCanonicalRecordImageResult::forbidden();
        }

        // Releer imagen bajo lock (puede haber desaparecido).
        $image = $this->images->find_by_id($image_id);
        if ($image !== null && (int) ($image['record_id'] ?? 0) !== $record_id) {
            return RetireCanonicalRecordImageResult::forbidden();
        }

        if ($open === null && $image === null) {
            $completed = $this->runs->find_latest_completed_by_scope_target(
                CanonicalPurgeRunsRepository::SCOPE_IMAGE,
                $image_id
            );
            if ($completed !== null && $this->run_matches_command($completed, $command)) {
                return RetireCanonicalRecordImageResult::confirmed($image_id, $record_id, $container_id);
            }

            return RetireCanonicalRecordImageResult::image_not_found($image_id, $record_id, $container_id);
        }

        $capture_complete = $open !== null && (int) ($open['capture_complete'] ?? 0) === 1;
        $already_sealed = $open !== null
            && CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null) !== null;

        if (!$capture_complete && !$already_sealed) {
            if ($open === null && $image === null) {
                return RetireCanonicalRecordImageResult::image_not_found($image_id, $record_id, $container_id);
            }

            $captured = $this->capture->execute_with_held_lock(
                $command->family_key(),
                $image_id,
                $record_id,
                $container_id,
                $image
            );
            $mapped = $this->map_capture_result($captured, $image_id, $record_id, $container_id, $open);
            if ($mapped !== null) {
                return $mapped;
            }
            $open = $this->runs->find_open_by_scope_target(
                CanonicalPurgeRunsRepository::SCOPE_IMAGE,
                $image_id
            );
            if ($open === null) {
                return RetireCanonicalRecordImageResult::persistence_failed();
            }
        } elseif ($open === null) {
            return RetireCanonicalRecordImageResult::persistence_failed();
        }

        if (!$this->run_matches_command($open, $command)) {
            return RetireCanonicalRecordImageResult::forbidden();
        }

        $cancellable = !CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open);

        if ($this->inventory->has_legacy_one_based_batches((int) $open['id'])) {
            if ($cancellable) {
                return RetireCanonicalRecordImageResult::conflict($image_id, $record_id, $container_id, [
                    'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_LEGACY_BATCH_INDEX,
                    'can_cancel' => true,
                ]);
            }

            return RetireCanonicalRecordImageResult::intervention_required($image_id, $record_id, $container_id, [
                'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_LEGACY_BATCH_INDEX,
            ]);
        }

        if ((string) ($open['capture_status'] ?? '') === AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT) {
            return RetireCanonicalRecordImageResult::conflict($image_id, $record_id, $container_id, [
                'conflict_code' => (string) ($open['capture_conflict_code'] ?? ''),
                'can_cancel' => $cancellable,
            ]);
        }

        if ((int) ($open['capture_complete'] ?? 0) !== 1) {
            return RetireCanonicalRecordImageResult::incomplete($image_id, $record_id, $container_id);
        }

        $prepared = (int) ($open['prepared_batch_count'] ?? 0);
        if ($prepared === 0) {
            return $this->commit_local($family_id, $container_id, $record_id, $image_id, (int) $open['id']);
        }

        $sealed_at = CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null);
        if ($sealed_at === null) {
            $hmac = $this->advance_remote($open, $image_id, $record_id, $container_id);
            if ($hmac instanceof RetireCanonicalRecordImageResult) {
                return $hmac;
            }
            $open = $this->runs->find_by_id((int) $open['id']);
            if ($open === null) {
                return RetireCanonicalRecordImageResult::persistence_failed();
            }
            $sealed_at = CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null);
        }

        if ($sealed_at === null) {
            return RetireCanonicalRecordImageResult::incomplete($image_id, $record_id, $container_id);
        }

        return $this->commit_local($family_id, $container_id, $record_id, $image_id, (int) $open['id']);
    }

    /**
     * @param array<string, mixed>|null $open_before
     */
    private function map_capture_result(
        CanonicalPurgeCaptureResult $captured,
        int $image_id,
        int $record_id,
        int $container_id,
        ?array $open_before
    ): ?RetireCanonicalRecordImageResult {
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_RESOURCE_BUSY) {
            return RetireCanonicalRecordImageResult::resource_busy();
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_SCOPE_OVERLAP) {
            return RetireCanonicalRecordImageResult::scope_overlap($image_id, $record_id, $container_id);
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_INVALID_SCOPE
            || $captured->state() === CanonicalPurgeCaptureResult::STATE_SCHEMA_NOT_READY
            || $captured->state() === CanonicalPurgeCaptureResult::STATE_PERSISTENCE_FAILED
        ) {
            return RetireCanonicalRecordImageResult::persistence_failed();
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_CONFLICT) {
            $cancellable = $open_before === null
                || !CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open_before);

            return RetireCanonicalRecordImageResult::conflict($image_id, $record_id, $container_id, [
                'conflict_code' => $captured->capture_conflict_code()
                    ?: CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA,
                'can_cancel' => $cancellable,
            ]);
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_CAPTURE_PROGRESS) {
            return RetireCanonicalRecordImageResult::incomplete($image_id, $record_id, $container_id);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $run
     * @return RetireCanonicalRecordImageResult|null null si el sello quedó acreditado
     */
    private function advance_remote(
        array $run,
        int $image_id,
        int $record_id,
        int $container_id
    ): ?RetireCanonicalRecordImageResult {
        $outcome = $this->remote->advance($run);
        if ($outcome->is_sealed()) {
            return null;
        }
        if ($outcome->state() === CanonicalPurgeRemoteAdvanceResult::STATE_INCOMPLETE) {
            return RetireCanonicalRecordImageResult::incomplete($image_id, $record_id, $container_id);
        }
        if ($outcome->state() === CanonicalPurgeRemoteAdvanceResult::STATE_INTERVENTION) {
            return RetireCanonicalRecordImageResult::intervention_required($image_id, $record_id, $container_id, [
                'conflict_code' => $outcome->conflict_code() ?: 'remote_payload_invalid',
            ]);
        }

        return RetireCanonicalRecordImageResult::persistence_failed();
    }

    private function commit_local(
        int $family_id,
        int $container_id,
        int $record_id,
        int $image_id,
        int $purge_run_id
    ): RetireCanonicalRecordImageResult {
        $quota = $this->lock->acquire(
            AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA,
            AA_Expediente_Aggregate_Lock::STORAGE_QUOTA_SCOPE_ID,
            AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
        );
        if (is_wp_error($quota)) {
            if ($quota->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                return RetireCanonicalRecordImageResult::incomplete($image_id, $record_id, $container_id);
            }

            return RetireCanonicalRecordImageResult::persistence_failed();
        }

        try {
            $held = $this->lock->assert_held($quota);
            if (is_wp_error($held)) {
                return RetireCanonicalRecordImageResult::persistence_failed();
            }

            $local = $this->local_store->retire_image(
                $family_id,
                $container_id,
                $record_id,
                $image_id,
                $purge_run_id
            );
            if (!$local->is_confirmed()) {
                if ($local->code() === 'inventory_identity_mismatch') {
                    return RetireCanonicalRecordImageResult::intervention_required(
                        $image_id,
                        $record_id,
                        $container_id,
                        ['conflict_code' => 'inventory_identity_mismatch']
                    );
                }

                return RetireCanonicalRecordImageResult::incomplete($image_id, $record_id, $container_id);
            }

            return RetireCanonicalRecordImageResult::confirmed($image_id, $record_id, $container_id);
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return RetireCanonicalRecordImageResult::uncertain($image_id, $record_id, $container_id);
        } finally {
            $this->lock->release($quota);
        }
    }

    /**
     * @param array<string, mixed> $run
     */
    private function run_matches_command(array $run, RetireCanonicalRecordImageCommand $command): bool {
        return (string) ($run['family_key'] ?? '') === $command->family_key()
            && (string) ($run['scope'] ?? '') === CanonicalPurgeRunsRepository::SCOPE_IMAGE
            && (int) ($run['target_id'] ?? 0) === $command->image_id();
    }
}
