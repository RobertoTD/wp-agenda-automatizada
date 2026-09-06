<?php
/**
 * Canonical Relational Write Adapter — Mutaciones SQL universales vía repositorio.
 *
 * Recibe CanonicalReadIdentity en cada método (contrato del puerto).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Relational
 */

defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalWriteAdapter')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalWriteAdapter.php';
}
if (!class_exists('CanonicalMutationReceipt')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalMutationReceipt.php';
}
if (!class_exists('CanonicalContainerNotFound')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalContainerNotFound.php';
}
if (!class_exists('CanonicalRecordNotFound')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalRecordNotFound.php';
}
if (!class_exists('CanonicalMutationPersistenceFailed')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalMutationPersistenceFailed.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalReadIdentity.php';
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

final class AA_Canonical_Relational_Write_Adapter implements CanonicalWriteAdapter {

    /** @var CanonicalRelationalRepository */
    private $repository;

    public function __construct(CanonicalRelationalRepository $repository) {
        $this->repository = $repository;
    }

    public function create_container(
        CanonicalReadIdentity $identity,
        CanonicalCreateContainerCommand $command
    ): CanonicalMutationReceipt {
        $family_id = $this->require_family_id($identity);

        try {
            $row = $this->repository->create_container(
                $family_id,
                $command->title(),
                $command->details()
            );
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return $this->uncertain_from_ambiguous($identity, $e);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_write_query_failed($identity, $e, null, null);
        }

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_CREATE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            (int) $row['id'],
            null
        );
    }

    public function update_container(
        CanonicalReadIdentity $identity,
        CanonicalUpdateContainerCommand $command
    ): CanonicalMutationReceipt {
        $family_id = $this->require_family_id($identity);

        try {
            $row = $this->repository->update_container(
                $family_id,
                $command->container_id(),
                $command->title(),
                $command->details()
            );
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return $this->uncertain_from_ambiguous($identity, $e);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_write_query_failed($identity, $e, $command->container_id(), null);
        }

        if ($row === null) {
            throw new CanonicalContainerNotFound($identity->family_key(), $command->container_id());
        }

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_UPDATE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            (int) $row['id'],
            null
        );
    }

    public function delete_container(
        CanonicalReadIdentity $identity,
        CanonicalDeleteContainerCommand $command
    ): CanonicalMutationReceipt {
        $family_id = $this->require_family_id($identity);

        try {
            $deleted = $this->repository->delete_container(
                $family_id,
                $command->container_id()
            );
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return $this->uncertain_from_ambiguous($identity, $e);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_write_query_failed($identity, $e, $command->container_id(), null);
        }

        if (!$deleted) {
            throw new CanonicalContainerNotFound($identity->family_key(), $command->container_id());
        }

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_DELETE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            $command->container_id(),
            null
        );
    }

    public function create_record(
        CanonicalReadIdentity $identity,
        CanonicalCreateRecordCommand $command
    ): CanonicalMutationReceipt {
        $family_id = $this->require_family_id($identity);

        try {
            $row = $this->repository->create_record(
                $family_id,
                $command->container_id(),
                $command->title(),
                $command->details()
            );
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return $this->uncertain_from_ambiguous($identity, $e);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_write_query_failed($identity, $e, $command->container_id(), null);
        }

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_CREATE,
            CanonicalMutationReceipt::RESOURCE_RECORD,
            (int) $row['id'],
            (int) $row['container_id']
        );
    }

    public function update_record(
        CanonicalReadIdentity $identity,
        CanonicalUpdateRecordCommand $command
    ): CanonicalMutationReceipt {
        $family_id = $this->require_family_id($identity);

        try {
            $row = $this->repository->update_record(
                $family_id,
                $command->container_id(),
                $command->record_id(),
                $command->title(),
                $command->details()
            );
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return $this->uncertain_from_ambiguous($identity, $e);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_write_query_failed(
                $identity,
                $e,
                $command->container_id(),
                $command->record_id()
            );
        }

        if ($row === null) {
            throw new CanonicalRecordNotFound($command->container_id(), $command->record_id());
        }

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_UPDATE,
            CanonicalMutationReceipt::RESOURCE_RECORD,
            (int) $row['id'],
            (int) $row['container_id']
        );
    }

    public function delete_record(
        CanonicalReadIdentity $identity,
        CanonicalDeleteRecordCommand $command
    ): CanonicalMutationReceipt {
        $family_id = $this->require_family_id($identity);

        try {
            $this->repository->delete_record(
                $family_id,
                $command->container_id(),
                $command->record_id()
            );
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return $this->uncertain_from_ambiguous($identity, $e);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_write_query_failed(
                $identity,
                $e,
                $command->container_id(),
                $command->record_id()
            );
        }

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_DELETE,
            CanonicalMutationReceipt::RESOURCE_RECORD,
            $command->record_id(),
            $command->container_id()
        );
    }

    private function require_family_id(CanonicalReadIdentity $identity): int {
        try {
            $family_id = $this->repository->resolve_family_id($identity->family_key());
        } catch (CanonicalRelationalQueryFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }

        if ($family_id === null) {
            throw new CanonicalMutationPersistenceFailed(
                'Family not provisioned: ' . $identity->family_key()
            );
        }

        return $family_id;
    }

    /**
     * @return never
     */
    private function map_write_query_failed(
        CanonicalReadIdentity $identity,
        CanonicalRelationalQueryFailed $e,
        ?int $container_id,
        ?int $record_id
    ) {
        if ($e->code_key() === CanonicalRelationalQueryFailed::CODE_CONTAINER_NOT_FOUND) {
            throw new CanonicalContainerNotFound(
                $identity->family_key(),
                ($container_id !== null && $container_id >= 1) ? $container_id : 1
            );
        }
        if ($e->code_key() === CanonicalRelationalQueryFailed::CODE_RECORD_NOT_FOUND) {
            throw new CanonicalRecordNotFound(
                ($container_id !== null && $container_id >= 1) ? $container_id : 1,
                ($record_id !== null && $record_id >= 1) ? $record_id : 1
            );
        }

        throw new CanonicalMutationPersistenceFailed($e->getMessage());
    }

    private function uncertain_from_ambiguous(
        CanonicalReadIdentity $identity,
        CanonicalRelationalAmbiguousOutcome $e
    ): CanonicalMutationReceipt {
        return CanonicalMutationReceipt::uncertain(
            $identity,
            $e->operation(),
            $e->resource_type(),
            $e->resource_id(),
            $e->container_id()
        );
    }
}
