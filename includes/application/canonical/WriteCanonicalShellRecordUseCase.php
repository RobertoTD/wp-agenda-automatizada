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

final class WriteCanonicalShellRecordUseCase {

    /** @var CanonicalWriteGateway */
    private $gateway;

    public function __construct(CanonicalWriteGateway $gateway) {
        $this->gateway = $gateway;
    }

    public function create(
        CanonicalShellManifest $manifest,
        CanonicalCreateRecordCommand $command
    ): CanonicalShellMutationResult {
        return $this->execute(
            $manifest,
            static function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command): CanonicalMutationReceipt {
                return $gateway->create_record($identity, $command);
            }
        );
    }

    public function update(
        CanonicalShellManifest $manifest,
        CanonicalUpdateRecordCommand $command
    ): CanonicalShellMutationResult {
        return $this->execute(
            $manifest,
            static function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command): CanonicalMutationReceipt {
                return $gateway->update_record($identity, $command);
            }
        );
    }

    public function delete(
        CanonicalShellManifest $manifest,
        CanonicalDeleteRecordCommand $command
    ): CanonicalShellMutationResult {
        return $this->execute(
            $manifest,
            static function (CanonicalWriteGateway $gateway, CanonicalReadIdentity $identity) use ($command): CanonicalMutationReceipt {
                return $gateway->delete_record($identity, $command);
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
