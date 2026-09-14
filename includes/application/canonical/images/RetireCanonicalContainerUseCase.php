<?php
/**
 * Retire Canonical Container — retiro de una lista vía mandatos HMAC (IMG-5 inc. 4).
 *
 * Lock del contenedor durante toda la petición (incluido HTTP). Sin TX SQL
 * abierta durante HMAC. Seal obligatorio incluso con inventario vacío
 * (expected_batch_count=0). Presupuesto: hasta 2 páginas de captura, un accept
 * (+ status) y un seal; post-sello un chunk local keyset.
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
if (!class_exists('CaptureCanonicalPurgeInventoryUseCase')) {
    require_once __DIR__ . '/CaptureCanonicalPurgeInventoryUseCase.php';
}
if (!class_exists('CaptureCanonicalPurgeInventoryCommand')) {
    require_once __DIR__ . '/CaptureCanonicalPurgeInventoryCommand.php';
}
if (!class_exists('CanonicalPurgeCaptureResult')) {
    require_once __DIR__ . '/CanonicalPurgeCaptureResult.php';
}
if (!class_exists('RetireCanonicalContainerCommand')) {
    require_once __DIR__ . '/RetireCanonicalContainerCommand.php';
}
if (!class_exists('RetireCanonicalContainerResult')) {
    require_once __DIR__ . '/RetireCanonicalContainerResult.php';
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

final class RetireCanonicalContainerUseCase {

    /** @var CanonicalRelationalRepository */
    private $relational;

    /** @var CaptureCanonicalPurgeInventoryUseCase */
    private $capture;

    /** @var CanonicalPurgeRunsRepository */
    private $runs;

    /** @var CanonicalPurgeInventoryItemsRepository */
    private $inventory;

    /** @var CanonicalRecordImagesRepository */
    private $images;

    /** @var CanonicalImageUploadOperationsRepository */
    private $operations;

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
        ?CaptureCanonicalPurgeInventoryUseCase $capture = null,
        ?CanonicalPurgeRunsRepository $runs = null,
        ?CanonicalPurgeInventoryItemsRepository $inventory = null,
        ?CanonicalRecordImagesRepository $images = null,
        ?CanonicalImageUploadOperationsRepository $operations = null,
        ?AA_Canonical_Purge_Local_Retire_Store $local_store = null,
        ?AA_Expediente_Attachments_Backend_Client $client = null,
        ?AA_Expediente_Aggregate_Lock $lock = null,
        ?CanonicalPurgeRemoteMandateAdvancer $remote = null
    ) {
        $this->relational = $relational ?: new CanonicalRelationalRepository();
        $this->runs = $runs ?: new CanonicalPurgeRunsRepository();
        $this->inventory = $inventory ?: new CanonicalPurgeInventoryItemsRepository();
        $this->images = $images ?: new CanonicalRecordImagesRepository();
        $this->operations = $operations ?: new CanonicalImageUploadOperationsRepository();
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
        $this->capture = $capture ?: new CaptureCanonicalPurgeInventoryUseCase(
            $this->runs,
            $this->images,
            $this->operations,
            $this->inventory,
            null,
            $this->lock
        );
        $this->local_store = $local_store ?: new AA_Canonical_Purge_Local_Retire_Store(
            $this->runs,
            $this->inventory,
            $this->images,
            $this->operations
        );
        $this->client = $client ?: new AA_Expediente_Attachments_Backend_Client();
        $this->remote = $remote ?: new CanonicalPurgeRemoteMandateAdvancer(
            $this->runs,
            $this->inventory,
            $this->client
        );
    }

    public function execute(RetireCanonicalContainerCommand $command): RetireCanonicalContainerResult {
        try {
            $family_id = $this->relational->resolve_family_id($command->family_key());
            if ($family_id === null) {
                return RetireCanonicalContainerResult::container_not_found($command->container_id());
            }

            $lease = $this->lock->acquire(
                AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
                $command->container_id(),
                AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
            );
            if (is_wp_error($lease)) {
                $code = $lease->get_error_code();
                if ($code === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                    return RetireCanonicalContainerResult::resource_busy();
                }

                return RetireCanonicalContainerResult::persistence_failed();
            }

            try {
                $held = $this->lock->assert_held($lease);
                if (is_wp_error($held)) {
                    return RetireCanonicalContainerResult::persistence_failed();
                }

                if ($command->is_cancel()) {
                    return $this->cancel_open_run($command);
                }

                return $this->retire_under_lock($command, $family_id, $lease);
            } finally {
                $this->lock->release($lease);
            }
        } catch (CanonicalRelationalQueryFailed $e) {
            return RetireCanonicalContainerResult::persistence_failed();
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return RetireCanonicalContainerResult::persistence_failed();
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            return RetireCanonicalContainerResult::persistence_failed();
        }
    }

    /**
     * @param AA_Expediente_Aggregate_Lock_Lease $lease
     */
    private function retire_under_lock(
        RetireCanonicalContainerCommand $command,
        int $family_id,
        $lease
    ): RetireCanonicalContainerResult {
        $container_id = $command->container_id();

        $open = $this->runs->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
            $container_id
        );
        if ($open !== null && !$this->run_matches_command($open, $command)) {
            return RetireCanonicalContainerResult::forbidden();
        }

        $container = $this->relational->find_container($family_id, $container_id);

        if ($open === null && $container === null) {
            return RetireCanonicalContainerResult::container_not_found($container_id);
        }

        $capture_complete = $open !== null && (int) ($open['capture_complete'] ?? 0) === 1;
        $already_sealed = $open !== null && CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null) !== null;

        if ($container === null && !$capture_complete && !$already_sealed) {
            return RetireCanonicalContainerResult::intervention_required($container_id, [
                'conflict_code' => 'container_missing_before_capture_complete',
            ]);
        }

        if (!$capture_complete && !$already_sealed) {
            $captured = $this->capture->execute_with_held_lock(
                $lease,
                new CaptureCanonicalPurgeInventoryCommand(
                    CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
                    $container_id,
                    $container_id,
                    $command->family_key(),
                    2
                )
            );
            $mapped = $this->map_capture_result($captured, $command, $open);
            if ($mapped !== null) {
                return $mapped;
            }
            $open = $this->runs->find_open_by_scope_target(
                CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
                $container_id
            );
            if ($open === null) {
                return RetireCanonicalContainerResult::persistence_failed();
            }
        } elseif ($open === null) {
            return RetireCanonicalContainerResult::persistence_failed();
        }

        if (!$this->run_matches_command($open, $command)) {
            return RetireCanonicalContainerResult::forbidden();
        }

        $cancellable = !CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open);

        if ($this->inventory->has_legacy_one_based_batches((int) $open['id'])) {
            if ($cancellable) {
                return RetireCanonicalContainerResult::conflict($container_id, [
                    'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_LEGACY_BATCH_INDEX,
                    'can_cancel' => true,
                ]);
            }

            return RetireCanonicalContainerResult::intervention_required($container_id, [
                'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_LEGACY_BATCH_INDEX,
            ]);
        }

        if ((string) ($open['capture_status'] ?? '') === AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT) {
            return RetireCanonicalContainerResult::conflict($container_id, [
                'conflict_code' => (string) ($open['capture_conflict_code'] ?? ''),
                'can_cancel' => $cancellable,
            ]);
        }

        if ((int) ($open['capture_complete'] ?? 0) !== 1) {
            return RetireCanonicalContainerResult::incomplete($container_id);
        }

        $prepared = (int) ($open['prepared_batch_count'] ?? 0);
        if ($prepared === 0) {
            if ($this->images->count_for_container($container_id) > 0
                || $this->operations->count_for_container($container_id) > 0
            ) {
                return RetireCanonicalContainerResult::intervention_required($container_id, [
                    'conflict_code' => 'live_rows_outside_empty_inventory',
                ]);
            }
        }

        $sealed_at = CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null);
        if ($sealed_at === null) {
            $hmac = $this->advance_remote($open, $container_id);
            if ($hmac instanceof RetireCanonicalContainerResult) {
                return $hmac;
            }
            $open = $this->runs->find_by_id((int) $open['id']);
            if ($open === null) {
                return RetireCanonicalContainerResult::persistence_failed();
            }
            $sealed_at = CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null);
        }

        if ($sealed_at === null) {
            return RetireCanonicalContainerResult::incomplete($container_id);
        }

        return $this->commit_local($family_id, $container_id, (int) $open['id']);
    }

    private function cancel_open_run(RetireCanonicalContainerCommand $command): RetireCanonicalContainerResult {
        $open = $this->runs->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
            $command->container_id()
        );
        if ($open === null) {
            return RetireCanonicalContainerResult::container_not_found($command->container_id());
        }
        if (!$this->run_matches_command($open, $command)) {
            return RetireCanonicalContainerResult::forbidden();
        }
        if (CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open)) {
            return RetireCanonicalContainerResult::cancel_rejected($command->container_id());
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->runs->mark_cancelled((int) $open['id'], $now);

        return RetireCanonicalContainerResult::cancelled($command->container_id());
    }

    /**
     * @param array<string, mixed>|null $open_before
     */
    private function map_capture_result(
        CanonicalPurgeCaptureResult $captured,
        RetireCanonicalContainerCommand $command,
        ?array $open_before
    ): ?RetireCanonicalContainerResult {
        $container_id = $command->container_id();

        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_RESOURCE_BUSY) {
            return RetireCanonicalContainerResult::resource_busy();
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_SCOPE_OVERLAP) {
            return RetireCanonicalContainerResult::scope_overlap($container_id);
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_INVALID_SCOPE
            || $captured->state() === CanonicalPurgeCaptureResult::STATE_SCHEMA_NOT_READY
            || $captured->state() === CanonicalPurgeCaptureResult::STATE_PERSISTENCE_FAILED
        ) {
            return RetireCanonicalContainerResult::persistence_failed();
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_CONFLICT) {
            $cancellable = $open_before === null
                || !CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open_before);

            return RetireCanonicalContainerResult::conflict($container_id, [
                'conflict_code' => $captured->capture_conflict_code() ?: CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA,
                'can_cancel' => $cancellable,
            ]);
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_CAPTURE_PROGRESS) {
            return RetireCanonicalContainerResult::incomplete($container_id);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $run
     * @return RetireCanonicalContainerResult|null null si el sello quedó acreditado
     */
    private function advance_remote(array $run, int $container_id): ?RetireCanonicalContainerResult {
        $outcome = $this->remote->advance($run);
        if ($outcome->is_sealed()) {
            return null;
        }
        if ($outcome->state() === CanonicalPurgeRemoteAdvanceResult::STATE_INCOMPLETE) {
            return RetireCanonicalContainerResult::incomplete($container_id);
        }
        if ($outcome->state() === CanonicalPurgeRemoteAdvanceResult::STATE_INTERVENTION) {
            return RetireCanonicalContainerResult::intervention_required($container_id, [
                'conflict_code' => $outcome->conflict_code() ?: 'remote_payload_invalid',
            ]);
        }

        return RetireCanonicalContainerResult::persistence_failed();
    }

    private function commit_local(
        int $family_id,
        int $container_id,
        int $purge_run_id
    ): RetireCanonicalContainerResult {
        $quota = $this->lock->acquire(
            AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA,
            AA_Expediente_Aggregate_Lock::STORAGE_QUOTA_SCOPE_ID,
            AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
        );
        if (is_wp_error($quota)) {
            if ($quota->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                return RetireCanonicalContainerResult::incomplete($container_id);
            }

            return RetireCanonicalContainerResult::persistence_failed();
        }

        try {
            $held = $this->lock->assert_held($quota);
            if (is_wp_error($held)) {
                return RetireCanonicalContainerResult::persistence_failed();
            }

            $local = $this->local_store->retire_container_chunk($family_id, $container_id, $purge_run_id);
            if ($local->is_incomplete()) {
                return RetireCanonicalContainerResult::incomplete($container_id);
            }
            if (!$local->is_confirmed()) {
                if ($local->code() === 'live_rows_outside_inventory') {
                    return RetireCanonicalContainerResult::intervention_required($container_id, [
                        'conflict_code' => 'live_rows_outside_inventory',
                    ]);
                }

                return RetireCanonicalContainerResult::incomplete($container_id);
            }

            return RetireCanonicalContainerResult::confirmed($container_id);
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return RetireCanonicalContainerResult::uncertain($container_id);
        } finally {
            $this->lock->release($quota);
        }
    }

    /**
     * @param array<string, mixed> $run
     */
    private function run_matches_command(array $run, RetireCanonicalContainerCommand $command): bool {
        return (int) ($run['container_id'] ?? 0) === $command->container_id()
            && (string) ($run['family_key'] ?? '') === $command->family_key()
            && (string) ($run['scope'] ?? '') === CanonicalPurgeRunsRepository::SCOPE_CONTAINER
            && (int) ($run['target_id'] ?? 0) === $command->container_id();
    }
}
