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

final class WriteCanonicalShellContainerUseCase {

    /** @var CanonicalWriteGateway */
    private $gateway;

    /** @var AA_Canonical_Capability_Defaults_Materializer|null */
    private $defaults_materializer;

    public function __construct(
        CanonicalWriteGateway $gateway,
        $defaults_materializer = null
    ) {
        $this->gateway = $gateway;
        $this->defaults_materializer = $defaults_materializer;
    }

    public function create(
        CanonicalShellManifest $manifest,
        CanonicalCreateContainerCommand $command
    ): CanonicalShellMutationResult {
        $effects = [];
        if ($this->defaults_materializer !== null) {
            $effects[] = $this->defaults_materializer->build_effect();
        }

        return $this->execute(
            $manifest,
            function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command, $effects): CanonicalMutationReceipt {
                return $gateway->create_container($identity, $command, $effects);
            }
        );
    }

    public function update(
        CanonicalShellManifest $manifest,
        CanonicalUpdateContainerCommand $command
    ): CanonicalShellMutationResult {
        return $this->execute(
            $manifest,
            static function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command): CanonicalMutationReceipt {
                return $gateway->update_container($identity, $command);
            }
        );
    }

    public function delete(
        CanonicalShellManifest $manifest,
        CanonicalDeleteContainerCommand $command
    ): CanonicalShellMutationResult {
        return $this->execute(
            $manifest,
            static function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command): CanonicalMutationReceipt {
                return $gateway->delete_container($identity, $command);
            }
        );
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
