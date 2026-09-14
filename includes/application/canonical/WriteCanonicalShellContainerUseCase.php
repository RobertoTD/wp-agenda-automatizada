<?php
/**
 * Write Canonical Shell Container — Mutación tipada de contenedores vía gateway de escritura.
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
if (!class_exists('CanonicalMutationPersistenceFailed')) {
    require_once __DIR__ . '/CanonicalMutationPersistenceFailed.php';
}
if (!class_exists('CanonicalMutationReceipt')) {
    require_once __DIR__ . '/CanonicalMutationReceipt.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once __DIR__ . '/CanonicalReadIdentity.php';
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

final class WriteCanonicalShellContainerUseCase {

    /** @var CanonicalWriteGateway */
    private $gateway;

    /** @var AA_Canonical_Capability_Defaults_Materializer|null */
    private $defaults_materializer;

    /** @var CanonicalContainerCapabilitySelectionPreparer|null */
    private $selection_preparer;

    /** @var CanonicalPurgeRunsRepository|null */
    private $purge_runs;

    /** @var AA_Expediente_Aggregate_Lock|null */
    private $lock;

    public function __construct(
        CanonicalWriteGateway $gateway,
        $defaults_materializer = null,
        $selection_preparer = null,
        ?CanonicalPurgeRunsRepository $purge_runs = null,
        ?AA_Expediente_Aggregate_Lock $lock = null
    ) {
        $this->gateway = $gateway;
        $this->defaults_materializer = $defaults_materializer;
        $this->selection_preparer = $selection_preparer;
        $this->purge_runs = $purge_runs;
        $this->lock = $lock;
    }

    /**
     * @param CanonicalContainerCapabilitySelection|null $selection null = omisión
     */
    public function create(
        CanonicalShellManifest $manifest,
        CanonicalCreateContainerCommand $command,
        $selection = null
    ): CanonicalShellMutationResult {
        $effects = $this->resolve_create_effects($manifest->identity()->family_key(), $selection);

        return $this->execute(
            $manifest,
            function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command, $effects): CanonicalMutationReceipt {
                return $gateway->create_container($identity, $command, $effects);
            }
        );
    }

    /**
     * @param CanonicalContainerCapabilitySelection|null $selection null = omisión
     */
    public function update(
        CanonicalShellManifest $manifest,
        CanonicalUpdateContainerCommand $command,
        $selection = null
    ): CanonicalShellMutationResult {
        $effects = $this->resolve_update_effects(
            $manifest->identity()->family_key(),
            $command->container_id(),
            $selection
        );

        return $this->execute(
            $manifest,
            function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command, $effects): CanonicalMutationReceipt {
                return $gateway->update_container($identity, $command, $effects);
            }
        );
    }

    public function delete(
        CanonicalShellManifest $manifest,
        CanonicalDeleteContainerCommand $command
    ): CanonicalShellMutationResult {
        if ($this->purge_runs === null) {
            return $this->execute(
                $manifest,
                static function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command): CanonicalMutationReceipt {
                    return $gateway->delete_container($identity, $command);
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
                if ($this->purge_runs->has_blocking_purge_for_container($command->container_id())) {
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
                    return $gateway->delete_container($identity, $command);
                }
            );
        } finally {
            if ($this->lock !== null && $lease !== null && !(function_exists('is_wp_error') && is_wp_error($lease))) {
                $this->lock->release($lease);
            }
        }
    }

    /**
     * @param CanonicalContainerCapabilitySelection|null $selection
     * @return list<CanonicalContainerCapabilityEffect>
     */
    private function resolve_create_effects(string $family_key, $selection): array {
        if ($selection instanceof CanonicalContainerCapabilitySelection) {
            if ($this->selection_preparer === null) {
                throw new CanonicalMutationPersistenceFailed(
                    'Capability selection preparer is not available.'
                );
            }

            return [$this->selection_preparer->build_create_effect($family_key, $selection)];
        }

        if ($this->defaults_materializer !== null) {
            return [$this->defaults_materializer->build_effect()];
        }

        return [];
    }

    /**
     * @param CanonicalContainerCapabilitySelection|null $selection
     * @return list<CanonicalContainerCapabilityEffect>
     */
    private function resolve_update_effects(string $family_key, int $container_id, $selection): array {
        if (!($selection instanceof CanonicalContainerCapabilitySelection)) {
            return [];
        }
        if ($this->selection_preparer === null) {
            throw new CanonicalMutationPersistenceFailed(
                'Capability selection preparer is not available.'
            );
        }

        return [$this->selection_preparer->build_update_effect($family_key, $container_id, $selection)];
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
        } catch (CanonicalMutationPersistenceFailed $e) {
            return CanonicalShellMutationResult::persistence_failed($manifest);
        } catch (\InvalidArgumentException $e) {
            if (strpos($e->getMessage(), '[invalid_mutation_contract]') === 0) {
                throw $e;
            }
            throw $e;
        }

        /** @var CanonicalMutationReceipt $receipt */

        if ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN) {
            return CanonicalShellMutationResult::uncertain($manifest, $receipt);
        }

        return CanonicalShellMutationResult::confirmed($manifest, $receipt);
    }
}
