<?php
/**
 * Canonical Write Gateway — Orquestación de mutación canónica.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalReadIdentity')) {
    require_once __DIR__ . '/CanonicalReadIdentity.php';
}
if (!interface_exists('CanonicalWriteAdapterResolver')) {
    require_once __DIR__ . '/CanonicalWriteAdapterResolver.php';
}
if (!class_exists('CanonicalMutationReceipt')) {
    require_once __DIR__ . '/CanonicalMutationReceipt.php';
}
if (!class_exists('CanonicalWriteBindingNotFound')) {
    require_once __DIR__ . '/CanonicalWriteBindingNotFound.php';
}
if (!class_exists('CanonicalCreateContainerCommand')) {
    require_once __DIR__ . '/CanonicalCreateContainerCommand.php';
}
if (!class_exists('CanonicalUpdateContainerCommand')) {
    require_once __DIR__ . '/CanonicalUpdateContainerCommand.php';
}
if (!class_exists('CanonicalDeleteContainerCommand')) {
    require_once __DIR__ . '/CanonicalDeleteContainerCommand.php';
}
if (!class_exists('CanonicalCreateRecordCommand')) {
    require_once __DIR__ . '/CanonicalCreateRecordCommand.php';
}
if (!class_exists('CanonicalUpdateRecordCommand')) {
    require_once __DIR__ . '/CanonicalUpdateRecordCommand.php';
}
if (!class_exists('CanonicalDeleteRecordCommand')) {
    require_once __DIR__ . '/CanonicalDeleteRecordCommand.php';
}

final class CanonicalWriteGateway {

    /** @var CanonicalWriteAdapterResolver */
    private $resolver;

    public function __construct(CanonicalWriteAdapterResolver $resolver) {
        $this->resolver = $resolver;
    }

    /**
     * @throws CanonicalWriteBindingNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function create_container(
        CanonicalReadIdentity $identity,
        CanonicalCreateContainerCommand $command
    ): CanonicalMutationReceipt {
        $adapter = $this->resolver->require($identity);
        $receipt = $adapter->create_container($identity, $command);
        $this->assert_receipt_identity($identity, $receipt);
        $this->assert_container_create_receipt($receipt);

        return $receipt;
    }

    /**
     * @throws CanonicalWriteBindingNotFound
     * @throws CanonicalContainerNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function update_container(
        CanonicalReadIdentity $identity,
        CanonicalUpdateContainerCommand $command
    ): CanonicalMutationReceipt {
        $adapter = $this->resolver->require($identity);
        $receipt = $adapter->update_container($identity, $command);
        $this->assert_receipt_identity($identity, $receipt);
        $this->assert_container_mutation_receipt(
            $receipt,
            CanonicalMutationReceipt::OPERATION_UPDATE,
            $command->container_id()
        );

        return $receipt;
    }

    /**
     * @throws CanonicalWriteBindingNotFound
     * @throws CanonicalContainerNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function delete_container(
        CanonicalReadIdentity $identity,
        CanonicalDeleteContainerCommand $command
    ): CanonicalMutationReceipt {
        $adapter = $this->resolver->require($identity);
        $receipt = $adapter->delete_container($identity, $command);
        $this->assert_receipt_identity($identity, $receipt);
        $this->assert_container_mutation_receipt(
            $receipt,
            CanonicalMutationReceipt::OPERATION_DELETE,
            $command->container_id()
        );

        return $receipt;
    }

    /**
     * @throws CanonicalWriteBindingNotFound
     * @throws CanonicalContainerNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function create_record(
        CanonicalReadIdentity $identity,
        CanonicalCreateRecordCommand $command
    ): CanonicalMutationReceipt {
        $adapter = $this->resolver->require($identity);
        $receipt = $adapter->create_record($identity, $command);
        $this->assert_receipt_identity($identity, $receipt);
        $this->assert_record_create_receipt($receipt, $command->container_id());

        return $receipt;
    }

    /**
     * @throws CanonicalWriteBindingNotFound
     * @throws CanonicalContainerNotFound
     * @throws CanonicalRecordNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function update_record(
        CanonicalReadIdentity $identity,
        CanonicalUpdateRecordCommand $command
    ): CanonicalMutationReceipt {
        $adapter = $this->resolver->require($identity);
        $receipt = $adapter->update_record($identity, $command);
        $this->assert_receipt_identity($identity, $receipt);
        $this->assert_record_mutation_receipt(
            $receipt,
            CanonicalMutationReceipt::OPERATION_UPDATE,
            $command->container_id(),
            $command->record_id()
        );

        return $receipt;
    }

    /**
     * @throws CanonicalWriteBindingNotFound
     * @throws CanonicalContainerNotFound
     * @throws CanonicalRecordNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function delete_record(
        CanonicalReadIdentity $identity,
        CanonicalDeleteRecordCommand $command
    ): CanonicalMutationReceipt {
        $adapter = $this->resolver->require($identity);
        $receipt = $adapter->delete_record($identity, $command);
        $this->assert_receipt_identity($identity, $receipt);
        $this->assert_record_mutation_receipt(
            $receipt,
            CanonicalMutationReceipt::OPERATION_DELETE,
            $command->container_id(),
            $command->record_id()
        );

        return $receipt;
    }

    private function assert_receipt_identity(
        CanonicalReadIdentity $identity,
        CanonicalMutationReceipt $receipt
    ): void {
        if ($receipt->identity()->qualified_key() !== $identity->qualified_key()) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Receipt identity mismatch.');
        }
    }

    private function assert_container_create_receipt(CanonicalMutationReceipt $receipt): void {
        if ($receipt->resource_type() !== CanonicalMutationReceipt::RESOURCE_CONTAINER) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Expected container resource.');
        }
        if ($receipt->operation() !== CanonicalMutationReceipt::OPERATION_CREATE) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Expected create operation.');
        }
        if ($receipt->container_id() !== null) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Container create receipt container_id must be null.');
        }
        if ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_CONFIRMED && ($receipt->resource_id() === null || $receipt->resource_id() < 1)) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Confirmed container create requires resource_id.');
        }
    }

    private function assert_container_mutation_receipt(
        CanonicalMutationReceipt $receipt,
        string $operation,
        int $container_id
    ): void {
        if ($receipt->resource_type() !== CanonicalMutationReceipt::RESOURCE_CONTAINER) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Expected container resource.');
        }
        if ($receipt->operation() !== $operation) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Operation mismatch.');
        }
        if ($receipt->container_id() !== null) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Container receipt container_id must be null.');
        }
        if ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_CONFIRMED) {
            if ($receipt->resource_id() !== $container_id) {
                throw new \InvalidArgumentException('[invalid_mutation_contract] resource_id mismatch.');
            }
        } elseif ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN) {
            if ($receipt->resource_id() === null || $receipt->resource_id() !== $container_id) {
                throw new \InvalidArgumentException('[invalid_mutation_contract] Uncertain container receipt resource_id mismatch.');
            }
        }
    }

    private function assert_record_create_receipt(
        CanonicalMutationReceipt $receipt,
        int $container_id
    ): void {
        if ($receipt->resource_type() !== CanonicalMutationReceipt::RESOURCE_RECORD) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Expected record resource.');
        }
        if ($receipt->operation() !== CanonicalMutationReceipt::OPERATION_CREATE) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Expected create operation.');
        }
        if ($receipt->container_id() !== $container_id) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] container_id mismatch.');
        }
        if ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_CONFIRMED && ($receipt->resource_id() === null || $receipt->resource_id() < 1)) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Confirmed record create requires resource_id.');
        }
    }

    private function assert_record_mutation_receipt(
        CanonicalMutationReceipt $receipt,
        string $operation,
        int $container_id,
        int $record_id
    ): void {
        if ($receipt->resource_type() !== CanonicalMutationReceipt::RESOURCE_RECORD) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Expected record resource.');
        }
        if ($receipt->operation() !== $operation) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] Operation mismatch.');
        }
        if ($receipt->container_id() !== $container_id) {
            throw new \InvalidArgumentException('[invalid_mutation_contract] container_id mismatch.');
        }
        if ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_CONFIRMED) {
            if ($receipt->resource_id() !== $record_id) {
                throw new \InvalidArgumentException('[invalid_mutation_contract] resource_id mismatch.');
            }
        } elseif ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN) {
            if ($receipt->resource_id() === null || $receipt->resource_id() !== $record_id) {
                throw new \InvalidArgumentException('[invalid_mutation_contract] Uncertain record receipt resource_id mismatch.');
            }
        }
    }
}
