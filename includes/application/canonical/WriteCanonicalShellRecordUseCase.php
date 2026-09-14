<?php
/**
 * Write Canonical Shell Record — Mutación tipada de registros vía gateway de escritura.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalShellManifest')) {
    require_once __DIR__ . '/CanonicalShellManifest.php';
}
if (!class_exists('CanonicalShellMutationResult')) {
    require_once __DIR__ . '/CanonicalShellMutationResult.php';
}
if (!class_exists('CanonicalWriteGateway')) {
    require_once __DIR__ . '/CanonicalWriteGateway.php';
}
if (!class_exists('CanonicalWriteBindingNotFound')) {
    require_once __DIR__ . '/CanonicalWriteBindingNotFound.php';
}
if (!class_exists('CanonicalContainerNotFound')) {
    require_once __DIR__ . '/CanonicalContainerNotFound.php';
}
if (!class_exists('CanonicalRecordNotFound')) {
    require_once __DIR__ . '/CanonicalRecordNotFound.php';
}
if (!class_exists('CanonicalMutationPersistenceFailed')) {
    require_once __DIR__ . '/CanonicalMutationPersistenceFailed.php';
}
if (!class_exists('CanonicalMutationReceipt')) {
    require_once __DIR__ . '/CanonicalMutationReceipt.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once __DIR__ . '/CanonicalReadIdentity.php';
}
if (!class_exists('CanonicalCapabilityRecordWritePreparer')) {
    require_once __DIR__ . '/capabilities/CanonicalCapabilityRecordWritePreparer.php';
}
if (!class_exists('CanonicalPurgeRunsRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalPurgeRunsRepository.php';
}
if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__) . '/storage/CanonicalImageUploadSchemaNotReady.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__) . '/storage/CanonicalImageUploadPersistenceFailed.php';
}

final class WriteCanonicalShellRecordUseCase {

    /** @var CanonicalWriteGateway */
    private $gateway;

    /** @var CanonicalCapabilityRecordWritePreparer|null */
    private $capability_preparer;

    /** @var CanonicalPurgeRunsRepository|null */
    private $purge_runs;

    /** @var AA_Expediente_Aggregate_Lock|null */
    private $lock;

    public function __construct(
        CanonicalWriteGateway $gateway,
        ?CanonicalCapabilityRecordWritePreparer $capability_preparer = null,
        ?CanonicalPurgeRunsRepository $purge_runs = null,
        ?AA_Expediente_Aggregate_Lock $lock = null
    ) {
        $this->gateway = $gateway;
        $this->capability_preparer = $capability_preparer;
        $this->purge_runs = $purge_runs;
        $this->lock = $lock;
    }

    public function create(
        CanonicalShellManifest $manifest,
        CanonicalCreateRecordCommand $command
    ): CanonicalShellMutationResult {
        $effects = $this->prepare_effects(
            $manifest->identity()->family_key(),
            $command->container_id(),
            $command->capability_writes(),
            false
        );

        return $this->execute(
            $manifest,
            function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command, $effects): CanonicalMutationReceipt {
                return $gateway->create_record($identity, $command, $effects);
            }
        );
    }

    public function update(
        CanonicalShellManifest $manifest,
        CanonicalUpdateRecordCommand $command
    ): CanonicalShellMutationResult {
        $effects = $this->prepare_effects(
            $manifest->identity()->family_key(),
            $command->container_id(),
            $command->capability_writes(),
            true
        );

        return $this->execute(
            $manifest,
            function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command, $effects): CanonicalMutationReceipt {
                return $gateway->update_record($identity, $command, $effects);
            }
        );
    }

    public function delete(
        CanonicalShellManifest $manifest,
        CanonicalDeleteRecordCommand $command
    ): CanonicalShellMutationResult {
        if ($this->purge_runs === null) {
            return $this->execute(
                $manifest,
                static function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command): CanonicalMutationReceipt {
                    return $gateway->delete_record($identity, $command);
                }
            );
        }

        $lease = null;
        if ($this->lock !== null) {
            $lease = $this->lock->acquire(
                AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
                $command->container_id(),
                AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
            );
            if (function_exists('is_wp_error') && is_wp_error($lease)) {
                $code = $lease->get_error_code();
                if ($code === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                    return CanonicalShellMutationResult::resource_busy($manifest);
                }

                return CanonicalShellMutationResult::persistence_failed($manifest);
            }
        }

        try {
            if ($this->lock !== null && $lease !== null) {
                $held = $this->lock->assert_held($lease);
                if (function_exists('is_wp_error') && is_wp_error($held)) {
                    return CanonicalShellMutationResult::persistence_failed($manifest);
                }
            }

            try {
                if ($this->purge_runs->has_blocking_purge($command->record_id(), $command->container_id())) {
                    return CanonicalShellMutationResult::purge_in_progress($manifest);
                }
            } catch (CanonicalImageUploadSchemaNotReady $e) {
                return CanonicalShellMutationResult::persistence_failed($manifest);
            } catch (CanonicalImageUploadPersistenceFailed $e) {
                return CanonicalShellMutationResult::persistence_failed($manifest);
            }

            return $this->execute(
                $manifest,
                static function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command): CanonicalMutationReceipt {
                    return $gateway->delete_record($identity, $command);
                }
            );
        } finally {
            if ($this->lock !== null && $lease !== null && !(function_exists('is_wp_error') && is_wp_error($lease))) {
                $this->lock->release($lease);
            }
        }
    }

    /**
     * @return list<CanonicalRecordCapabilityEffect>
     */
    private function prepare_effects(
        string $family_key,
        int $container_id,
        CanonicalCapabilityWriteBag $bag,
        bool $is_update
    ): array {
        if ($bag->is_empty()) {
            return [];
        }
        if ($this->capability_preparer === null) {
            throw new CanonicalCapabilityWriteRejected(
                'capability_write_unavailable',
                'La escritura de capacidades no está disponible.',
                500
            );
        }

        return $this->capability_preparer->prepare($family_key, $container_id, $bag, $is_update);
    }

    /**
     * @param callable(CanonicalWriteGateway, CanonicalReadIdentity): CanonicalMutationReceipt $operation
     */
    private function execute(
        CanonicalShellManifest $manifest,
        callable $operation
    ): CanonicalShellMutationResult {
        try {
            $receipt = $operation($this->gateway, $manifest->identity());
        } catch (CanonicalWriteBindingNotFound $e) {
            return CanonicalShellMutationResult::write_adapter_pending($manifest);
        } catch (CanonicalContainerNotFound $e) {
            return CanonicalShellMutationResult::container_not_found($manifest);
        } catch (CanonicalRecordNotFound $e) {
            return CanonicalShellMutationResult::record_not_found($manifest);
        } catch (CanonicalMutationPersistenceFailed $e) {
            return CanonicalShellMutationResult::persistence_failed($manifest);
        } catch (\InvalidArgumentException $e) {
            if (strpos($e->getMessage(), '[invalid_mutation_contract]') === 0) {
                throw $e;
            }
            throw $e;
        }

        if ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN) {
            return CanonicalShellMutationResult::uncertain($manifest, $receipt);
        }

        return CanonicalShellMutationResult::confirmed($manifest, $receipt);
    }
}
