<?php
/**
 * Retire Canonical Record — retiro de UN registro vía mandatos HMAC (IMG-5 inc. 3).
 *
 * Lock del contenedor durante toda la petición (incluido HTTP). Sin TX SQL
 * abierta durante HMAC. Presupuesto: hasta 2 páginas de captura, un accept
 * (+ status de esa tanda) y un seal. Skip HMAC si el inventario está vacío.
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
if (!class_exists('RetireCanonicalRecordCommand')) {
    require_once __DIR__ . '/RetireCanonicalRecordCommand.php';
}
if (!class_exists('RetireCanonicalRecordResult')) {
    require_once __DIR__ . '/RetireCanonicalRecordResult.php';
}
if (!class_exists('CanonicalPurgeLocalRetireResult')) {
    require_once __DIR__ . '/CanonicalPurgeLocalRetireResult.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadSchemaNotReady.php';
}

final class RetireCanonicalRecordUseCase {

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

    public function __construct(
        ?CanonicalRelationalRepository $relational = null,
        ?CaptureCanonicalPurgeInventoryUseCase $capture = null,
        ?CanonicalPurgeRunsRepository $runs = null,
        ?CanonicalPurgeInventoryItemsRepository $inventory = null,
        ?CanonicalRecordImagesRepository $images = null,
        ?CanonicalImageUploadOperationsRepository $operations = null,
        ?AA_Canonical_Purge_Local_Retire_Store $local_store = null,
        ?AA_Expediente_Attachments_Backend_Client $client = null,
        ?AA_Expediente_Aggregate_Lock $lock = null
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
    }

    public function execute(RetireCanonicalRecordCommand $command): RetireCanonicalRecordResult {
        try {
            $family_id = $this->relational->resolve_family_id($command->family_key());
            if ($family_id === null) {
                return RetireCanonicalRecordResult::container_not_found($command->container_id());
            }

            $container = $this->relational->find_container($family_id, $command->container_id());
            if ($container === null) {
                return RetireCanonicalRecordResult::container_not_found($command->container_id());
            }

            $lease = $this->lock->acquire(
                AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
                $command->container_id(),
                AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
            );
            if (is_wp_error($lease)) {
                $code = $lease->get_error_code();
                if ($code === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                    return RetireCanonicalRecordResult::resource_busy();
                }

                return RetireCanonicalRecordResult::persistence_failed();
            }

            try {
                $held = $this->lock->assert_held($lease);
                if (is_wp_error($held)) {
                    return RetireCanonicalRecordResult::persistence_failed();
                }

                if ($command->is_cancel()) {
                    return $this->cancel_open_run($command);
                }

                return $this->retire_under_lock($command, $family_id, $lease);
            } finally {
                $this->lock->release($lease);
            }
        } catch (CanonicalRelationalQueryFailed $e) {
            return RetireCanonicalRecordResult::persistence_failed();
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return RetireCanonicalRecordResult::persistence_failed();
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            return RetireCanonicalRecordResult::persistence_failed();
        }
    }

    /**
     * @param AA_Expediente_Aggregate_Lock_Lease $lease
     */
    private function retire_under_lock(
        RetireCanonicalRecordCommand $command,
        int $family_id,
        $lease
    ): RetireCanonicalRecordResult {
        $record_id = $command->record_id();
        $container_id = $command->container_id();

        $open = $this->runs->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            $record_id
        );
        if ($open !== null && !$this->run_matches_command($open, $command)) {
            return RetireCanonicalRecordResult::forbidden();
        }

        $record = $this->relational->find_record($container_id, $record_id);

        if ($open === null && $record === null) {
            return RetireCanonicalRecordResult::record_not_found($container_id, $record_id);
        }

        $capture_complete = $open !== null && (int) ($open['capture_complete'] ?? 0) === 1;
        $already_sealed = $open !== null && CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null) !== null;

        if ($record === null && !$capture_complete && !$already_sealed) {
            return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                'conflict_code' => 'record_missing_before_capture_complete',
            ]);
        }

        if (!$capture_complete && !$already_sealed) {
            $captured = $this->capture->execute_with_held_lock(
                $lease,
                new CaptureCanonicalPurgeInventoryCommand(
                    CanonicalPurgeRunsRepository::SCOPE_RECORD,
                    $record_id,
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
                CanonicalPurgeRunsRepository::SCOPE_RECORD,
                $record_id
            );
            if ($open === null) {
                return RetireCanonicalRecordResult::persistence_failed();
            }
        } elseif ($open === null) {
            return RetireCanonicalRecordResult::persistence_failed();
        }

        if (!$this->run_matches_command($open, $command)) {
            return RetireCanonicalRecordResult::forbidden();
        }

        $cancellable = !CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open);

        if ($this->inventory->has_legacy_one_based_batches((int) $open['id'])) {
            if ($cancellable) {
                return RetireCanonicalRecordResult::conflict($record_id, $container_id, [
                    'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_LEGACY_BATCH_INDEX,
                    'can_cancel' => true,
                ]);
            }

            return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                'conflict_code' => CanonicalPurgeRunsRepository::CONFLICT_LEGACY_BATCH_INDEX,
            ]);
        }

        if ((string) ($open['capture_status'] ?? '') === AA_Canonical_Schema::PURGE_CAPTURE_STATUS_CONFLICT) {
            return RetireCanonicalRecordResult::conflict($record_id, $container_id, [
                'conflict_code' => (string) ($open['capture_conflict_code'] ?? ''),
                'can_cancel' => $cancellable,
            ]);
        }

        if ((int) ($open['capture_complete'] ?? 0) !== 1) {
            return RetireCanonicalRecordResult::incomplete($record_id, $container_id);
        }

        $prepared = (int) ($open['prepared_batch_count'] ?? 0);
        if ($prepared === 0) {
            if ($this->images->count_for_record($record_id) > 0
                || $this->operations->count_for_record($record_id) > 0
            ) {
                return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                    'conflict_code' => 'live_rows_outside_empty_inventory',
                ]);
            }

            return $this->commit_local($family_id, $container_id, $record_id, (int) $open['id']);
        }

        $sealed_at = CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null);
        if ($sealed_at === null) {
            $hmac = $this->advance_remote($open, $record_id, $container_id);
            if ($hmac instanceof RetireCanonicalRecordResult) {
                return $hmac;
            }
            $open = $this->runs->find_by_id((int) $open['id']);
            if ($open === null) {
                return RetireCanonicalRecordResult::persistence_failed();
            }
            $sealed_at = CanonicalPurgeRunsRepository::nullable_string($open['sealed_at'] ?? null);
        }

        if ($sealed_at === null) {
            return RetireCanonicalRecordResult::incomplete($record_id, $container_id);
        }

        return $this->commit_local($family_id, $container_id, $record_id, (int) $open['id']);
    }

    private function cancel_open_run(RetireCanonicalRecordCommand $command): RetireCanonicalRecordResult {
        $open = $this->runs->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            $command->record_id()
        );
        if ($open === null) {
            return RetireCanonicalRecordResult::record_not_found(
                $command->container_id(),
                $command->record_id()
            );
        }
        if (!$this->run_matches_command($open, $command)) {
            return RetireCanonicalRecordResult::forbidden();
        }
        if (CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open)) {
            return RetireCanonicalRecordResult::cancel_rejected(
                $command->record_id(),
                $command->container_id()
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->runs->mark_cancelled((int) $open['id'], $now);

        return RetireCanonicalRecordResult::cancelled($command->record_id(), $command->container_id());
    }

    /**
     * @param array<string, mixed>|null $open_before
     */
    private function map_capture_result(
        CanonicalPurgeCaptureResult $captured,
        RetireCanonicalRecordCommand $command,
        ?array $open_before
    ): ?RetireCanonicalRecordResult {
        $record_id = $command->record_id();
        $container_id = $command->container_id();

        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_RESOURCE_BUSY) {
            return RetireCanonicalRecordResult::resource_busy();
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_SCOPE_OVERLAP) {
            return RetireCanonicalRecordResult::scope_overlap($record_id, $container_id);
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_INVALID_SCOPE
            || $captured->state() === CanonicalPurgeCaptureResult::STATE_SCHEMA_NOT_READY
            || $captured->state() === CanonicalPurgeCaptureResult::STATE_PERSISTENCE_FAILED
        ) {
            return RetireCanonicalRecordResult::persistence_failed();
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_CONFLICT) {
            $cancellable = $open_before === null
                || !CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open_before);

            return RetireCanonicalRecordResult::conflict($record_id, $container_id, [
                'conflict_code' => $captured->capture_conflict_code() ?: CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA,
                'can_cancel' => $cancellable,
            ]);
        }
        if ($captured->state() === CanonicalPurgeCaptureResult::STATE_CAPTURE_PROGRESS) {
            return RetireCanonicalRecordResult::incomplete($record_id, $container_id);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $run
     * @return RetireCanonicalRecordResult|null null si el sello quedó acreditado
     */
    private function advance_remote(array $run, int $record_id, int $container_id): ?RetireCanonicalRecordResult {
        $purge_run_id = (int) $run['id'];
        $mandate_id = (string) ($run['mandate_id'] ?? '');
        $prepared = (int) ($run['prepared_batch_count'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        $last_accepted = CanonicalPurgeRunsRepository::nullable_int($run['last_accepted_batch_seq'] ?? null);
        $next_seq = $last_accepted === null ? 0 : $last_accepted + 1;

        if ($next_seq < $prepared) {
            $credited = $this->credit_or_accept_batch($run, $next_seq, $mandate_id, $now);
            if ($credited instanceof RetireCanonicalRecordResult) {
                return $credited;
            }
            $run = $this->runs->find_by_id($purge_run_id);
            if ($run === null) {
                return RetireCanonicalRecordResult::persistence_failed();
            }
            $last_accepted = CanonicalPurgeRunsRepository::nullable_int($run['last_accepted_batch_seq'] ?? null);
            $next_seq = $last_accepted === null ? 0 : $last_accepted + 1;
            if ($next_seq < $prepared) {
                return RetireCanonicalRecordResult::incomplete($record_id, $container_id);
            }
        }

        return $this->seal_or_recover($run, $mandate_id, $prepared, $record_id, $container_id, $now);
    }

    /**
     * @param array<string, mixed> $run
     * @return RetireCanonicalRecordResult|null
     */
    private function credit_or_accept_batch(
        array $run,
        int $batch_seq,
        string $mandate_id,
        string $now
    ): ?RetireCanonicalRecordResult {
        $record_id = (int) $run['target_id'];
        $container_id = (int) $run['container_id'];
        $purge_run_id = (int) $run['id'];
        $local = $this->inventory->list_prepared_batch($purge_run_id, $batch_seq);
        if ($local === []) {
            return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                'conflict_code' => 'prepared_batch_missing',
            ]);
        }

        $intent_seq = CanonicalPurgeRunsRepository::nullable_int($run['accept_intent_batch_seq'] ?? null);
        $unknown = $intent_seq !== null && $intent_seq === $batch_seq;

        if ($unknown) {
            $status = $this->client->get_delete_mandate_status([
                'mandate_id' => $mandate_id,
                'batch_seq' => $batch_seq,
            ]);
            if (($status['ok'] ?? false) === true) {
                $remote_items = $this->status_credit_items($status);
                if ($remote_items !== null) {
                    if (!$this->items_match_local($local, $remote_items)) {
                        return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                            'conflict_code' => 'remote_identity_mismatch',
                        ]);
                    }
                    $this->runs->credit_last_accepted_batch($purge_run_id, $batch_seq, $now);
                    return null;
                }
                if (($status['result']['found'] ?? null) === false
                    || ($status['result']['batch_found'] ?? null) === false
                ) {
                    return $this->dispatch_accept($run, $batch_seq, $mandate_id, $local, $now, false);
                }
            }
            if (($status['ok'] ?? false) !== true) {
                return $this->hmac_failed_result($status, $record_id, $container_id);
            }

            return $this->dispatch_accept($run, $batch_seq, $mandate_id, $local, $now, false);
        }

        return $this->dispatch_accept($run, $batch_seq, $mandate_id, $local, $now, true);
    }

    /**
     * @param array<string, mixed> $run
     * @param list<array<string, mixed>> $local
     * @return RetireCanonicalRecordResult|null
     */
    private function dispatch_accept(
        array $run,
        int $batch_seq,
        string $mandate_id,
        array $local,
        string $now,
        bool $persist_intent
    ): ?RetireCanonicalRecordResult {
        $record_id = (int) $run['target_id'];
        $container_id = (int) $run['container_id'];
        $purge_run_id = (int) $run['id'];

        if ($persist_intent) {
            $this->runs->persist_accept_intent($purge_run_id, $batch_seq, $now);
        }

        $accepted = $this->client->accept_delete_batch([
            'mandate_id' => $mandate_id,
            'batch_seq' => $batch_seq,
            'items' => $this->accept_items_from_local($local),
        ]);
        if (($accepted['ok'] ?? false) !== true) {
            return $this->hmac_failed_result($accepted, $record_id, $container_id);
        }

        $remote_items = is_array($accepted['result']['items'] ?? null) ? $accepted['result']['items'] : null;
        if ($remote_items === null || !$this->items_match_local($local, $remote_items)) {
            return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                'conflict_code' => 'remote_identity_mismatch',
            ]);
        }

        $this->runs->credit_last_accepted_batch($purge_run_id, $batch_seq, $now);

        return null;
    }

    /**
     * @param array<string, mixed> $run
     * @return RetireCanonicalRecordResult|null
     */
    private function seal_or_recover(
        array $run,
        string $mandate_id,
        int $expected_batch_count,
        int $record_id,
        int $container_id,
        string $now
    ): ?RetireCanonicalRecordResult {
        $purge_run_id = (int) $run['id'];
        $seal_intent = CanonicalPurgeRunsRepository::nullable_string($run['seal_intent_at'] ?? null);

        if ($seal_intent !== null) {
            $status = $this->client->get_delete_mandate_status([
                'mandate_id' => $mandate_id,
            ]);
            if (($status['ok'] ?? false) !== true) {
                return $this->hmac_failed_result($status, $record_id, $container_id);
            }
            if ($this->is_structurally_sealed($status)) {
                $this->runs->mark_sealed($purge_run_id, $now);
                return null;
            }
        } else {
            $this->runs->persist_seal_intent($purge_run_id, $now);
        }

        $sealed = $this->client->seal_delete_mandate([
            'mandate_id' => $mandate_id,
            'expected_batch_count' => $expected_batch_count,
        ]);
        if (($sealed['ok'] ?? false) !== true) {
            return $this->hmac_failed_result($sealed, $record_id, $container_id);
        }
        if (!$this->is_structurally_sealed($sealed)) {
            return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                'conflict_code' => 'seal_not_structural',
            ]);
        }

        $this->runs->mark_sealed($purge_run_id, $now);

        return null;
    }

    private function commit_local(
        int $family_id,
        int $container_id,
        int $record_id,
        int $purge_run_id
    ): RetireCanonicalRecordResult {
        $quota = $this->lock->acquire(
            AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA,
            AA_Expediente_Aggregate_Lock::STORAGE_QUOTA_SCOPE_ID,
            AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
        );
        if (is_wp_error($quota)) {
            if ($quota->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                return RetireCanonicalRecordResult::incomplete($record_id, $container_id);
            }

            return RetireCanonicalRecordResult::persistence_failed();
        }

        try {
            $held = $this->lock->assert_held($quota);
            if (is_wp_error($held)) {
                return RetireCanonicalRecordResult::persistence_failed();
            }

            $local = $this->local_store->retire_record($family_id, $container_id, $record_id, $purge_run_id);
            if (!$local->is_confirmed()) {
                if ($local->code() === 'live_rows_outside_inventory') {
                    return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                        'conflict_code' => 'live_rows_outside_inventory',
                    ]);
                }

                return RetireCanonicalRecordResult::incomplete($record_id, $container_id);
            }

            return RetireCanonicalRecordResult::confirmed($record_id, $container_id);
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return RetireCanonicalRecordResult::uncertain($record_id, $container_id);
        } finally {
            $this->lock->release($quota);
        }
    }

    /**
     * @param array<string, mixed> $run
     */
    private function run_matches_command(array $run, RetireCanonicalRecordCommand $command): bool {
        return (int) ($run['container_id'] ?? 0) === $command->container_id()
            && (string) ($run['family_key'] ?? '') === $command->family_key()
            && (string) ($run['scope'] ?? '') === CanonicalPurgeRunsRepository::SCOPE_RECORD
            && (int) ($run['target_id'] ?? 0) === $command->record_id();
    }

    /**
     * @param list<array<string, mixed>> $local
     * @return list<array<string, mixed>>
     */
    private function accept_items_from_local(array $local): array {
        $items = [];
        foreach ($local as $row) {
            $item = [
                'upload_operation_id' => (string) $row['upload_operation_id'],
                'wp_record_id' => (int) $row['wp_record_id'],
                'content_sha256' => (string) $row['content_sha256'],
                'byte_size' => (int) $row['byte_size'],
            ];
            if (isset($row['storage_path']) && is_string($row['storage_path']) && $row['storage_path'] !== '') {
                $item['storage_path'] = $row['storage_path'];
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $local
     * @param list<array<string, mixed>> $remote
     */
    private function items_match_local(array $local, array $remote): bool {
        if (count($local) !== count($remote)) {
            return false;
        }

        $by_op = [];
        foreach ($remote as $item) {
            if (!is_array($item)) {
                return false;
            }
            $op = strtolower((string) ($item['upload_operation_id'] ?? ''));
            if ($op === '' || isset($by_op[$op])) {
                return false;
            }
            $by_op[$op] = $item;
        }

        foreach ($local as $row) {
            $op = strtolower((string) ($row['upload_operation_id'] ?? ''));
            if (!isset($by_op[$op])) {
                return false;
            }
            $remote_item = $by_op[$op];
            if ((int) ($remote_item['wp_record_id'] ?? 0) !== (int) $row['wp_record_id']) {
                return false;
            }
            if (strtolower((string) ($remote_item['content_sha256'] ?? '')) !== strtolower((string) $row['content_sha256'])) {
                return false;
            }
            if ((int) ($remote_item['byte_size'] ?? 0) !== (int) $row['byte_size']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $hmac
     * @return list<array<string, mixed>>|null
     */
    private function status_credit_items(array $hmac): ?array {
        if (($hmac['ok'] ?? false) !== true) {
            return null;
        }
        $result = $hmac['result'] ?? [];
        if (empty($result['can_credit_batch'])) {
            return null;
        }
        if (isset($result['batch']['items']) && is_array($result['batch']['items'])) {
            return $result['batch']['items'];
        }
        if (!empty($result['inventory_page_complete']) && isset($result['items']) && is_array($result['items'])) {
            return $result['items'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $hmac
     */
    private function is_structurally_sealed(array $hmac): bool {
        if (($hmac['ok'] ?? false) !== true) {
            return false;
        }
        if (($hmac['retire_authorized'] ?? false) !== true) {
            return false;
        }
        $result = $hmac['result'] ?? [];

        return ($result['inventory_status'] ?? '') === 'sealed'
            && ($result['structural_retire_authorized'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $hmac
     */
    private function hmac_failed_result(array $hmac, int $record_id, int $container_id): RetireCanonicalRecordResult {
        $class = (string) ($hmac['failure_class'] ?? '');
        $code = (string) ($hmac['code'] ?? '');
        if ($class === 'malformed' || $code === 'expediente_attachments_invalid_response') {
            return RetireCanonicalRecordResult::intervention_required($record_id, $container_id, [
                'conflict_code' => 'remote_payload_invalid',
            ]);
        }

        return RetireCanonicalRecordResult::incomplete($record_id, $container_id);
    }
}
