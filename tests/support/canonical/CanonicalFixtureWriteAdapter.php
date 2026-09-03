<?php
/**
 * Canonical Fixture Write Adapter — Solo tests. Mutaciones in-memory neutrales.
 *
 * @package WP_Agenda_Automatizada
 */

if (!interface_exists('CanonicalWriteAdapter')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalWriteAdapter.php';
}
if (!class_exists('CanonicalMutationReceipt')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalMutationReceipt.php';
}
if (!class_exists('CanonicalContainerNotFound')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalContainerNotFound.php';
}
if (!class_exists('CanonicalRecordNotFound')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalRecordNotFound.php';
}
if (!class_exists('CanonicalMutationPersistenceFailed')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
}

final class CanonicalFixtureWriteAdapter implements CanonicalWriteAdapter {

    /** @var string */
    private $variant_key;

    /** @var array<int, array{title:string,details:?string}> */
    private $containers = [];

    /** @var array<int, array<int, array{title:string,details:?string}>> */
    private $records_by_container = [];

    /** @var int */
    private $next_container_id = 1;

    /** @var int */
    private $next_record_id = 1;

    /** @var string|null */
    public $uncertain_operation = null;

    public function __construct(string $variant_key) {
        $this->variant_key = $variant_key;
    }

    /**
     * @param array<int, array{title:string,details:?string}> $containers
     * @param array<int, array<int, array{title:string,details:?string}>> $records
     */
    public static function with_seed(string $variant_key, array $containers, array $records = []): self {
        $adapter = new self($variant_key);
        foreach ($containers as $id => $row) {
            $adapter->containers[(int) $id] = $row;
            if ((int) $id >= $adapter->next_container_id) {
                $adapter->next_container_id = (int) $id + 1;
            }
        }
        foreach ($records as $container_id => $record_rows) {
            foreach ($record_rows as $record_id => $row) {
                $adapter->records_by_container[(int) $container_id][(int) $record_id] = $row;
                if ((int) $record_id >= $adapter->next_record_id) {
                    $adapter->next_record_id = (int) $record_id + 1;
                }
            }
        }
        return $adapter;
    }

    public function create_container(
        CanonicalReadIdentity $identity,
        CanonicalCreateContainerCommand $command
    ): CanonicalMutationReceipt {
        $this->assert_variant($identity);
        $this->assert_not_persistence_failed();
        if ($this->uncertain_operation === 'create_container') {
            return CanonicalMutationReceipt::uncertain(
                $identity,
                CanonicalMutationReceipt::OPERATION_CREATE,
                CanonicalMutationReceipt::RESOURCE_CONTAINER,
                null,
                null
            );
        }
        if ($this->uncertain_operation === 'create_container_with_id') {
            $id = $this->next_container_id++;
            return CanonicalMutationReceipt::uncertain(
                $identity,
                CanonicalMutationReceipt::OPERATION_CREATE,
                CanonicalMutationReceipt::RESOURCE_CONTAINER,
                $id,
                null
            );
        }

        $id = $this->next_container_id++;
        $this->containers[$id] = [
            'title' => $command->title(),
            'details' => $command->details(),
        ];

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_CREATE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            $id,
            null
        );
    }

    public function update_container(
        CanonicalReadIdentity $identity,
        CanonicalUpdateContainerCommand $command
    ): CanonicalMutationReceipt {
        $this->assert_variant($identity);
        $this->assert_not_persistence_failed();
        $id = $command->container_id();
        if (!isset($this->containers[$id])) {
            throw new CanonicalContainerNotFound($identity->variant_key(), $id);
        }
        if ($this->uncertain_operation === 'update_container') {
            return CanonicalMutationReceipt::uncertain(
                $identity,
                CanonicalMutationReceipt::OPERATION_UPDATE,
                CanonicalMutationReceipt::RESOURCE_CONTAINER,
                $id,
                null
            );
        }

        $this->containers[$id] = [
            'title' => $command->title(),
            'details' => $command->details(),
        ];

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_UPDATE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            $id,
            null
        );
    }

    public function delete_container(
        CanonicalReadIdentity $identity,
        CanonicalDeleteContainerCommand $command
    ): CanonicalMutationReceipt {
        $this->assert_variant($identity);
        $this->assert_not_persistence_failed();
        $id = $command->container_id();
        if (!isset($this->containers[$id])) {
            throw new CanonicalContainerNotFound($identity->variant_key(), $id);
        }
        if ($this->uncertain_operation === 'delete_container') {
            return CanonicalMutationReceipt::uncertain(
                $identity,
                CanonicalMutationReceipt::OPERATION_DELETE,
                CanonicalMutationReceipt::RESOURCE_CONTAINER,
                $id,
                null
            );
        }

        unset($this->containers[$id]);
        unset($this->records_by_container[$id]);

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_DELETE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            $id,
            null
        );
    }

    public function create_record(
        CanonicalReadIdentity $identity,
        CanonicalCreateRecordCommand $command
    ): CanonicalMutationReceipt {
        $this->assert_variant($identity);
        $this->assert_not_persistence_failed();
        $container_id = $command->container_id();
        if (!isset($this->containers[$container_id])) {
            throw new CanonicalContainerNotFound($identity->variant_key(), $container_id);
        }
        if ($this->uncertain_operation === 'create_record') {
            return CanonicalMutationReceipt::uncertain(
                $identity,
                CanonicalMutationReceipt::OPERATION_CREATE,
                CanonicalMutationReceipt::RESOURCE_RECORD,
                null,
                $container_id
            );
        }
        if ($this->uncertain_operation === 'create_record_with_id') {
            $record_id = $this->next_record_id++;
            return CanonicalMutationReceipt::uncertain(
                $identity,
                CanonicalMutationReceipt::OPERATION_CREATE,
                CanonicalMutationReceipt::RESOURCE_RECORD,
                $record_id,
                $container_id
            );
        }

        $record_id = $this->next_record_id++;
        if (!isset($this->records_by_container[$container_id])) {
            $this->records_by_container[$container_id] = [];
        }
        $this->records_by_container[$container_id][$record_id] = [
            'title' => $command->title(),
            'details' => $command->details(),
        ];

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_CREATE,
            CanonicalMutationReceipt::RESOURCE_RECORD,
            $record_id,
            $container_id
        );
    }

    public function update_record(
        CanonicalReadIdentity $identity,
        CanonicalUpdateRecordCommand $command
    ): CanonicalMutationReceipt {
        $this->assert_variant($identity);
        $this->assert_not_persistence_failed();
        $container_id = $command->container_id();
        $record_id = $command->record_id();

        if (!isset($this->containers[$container_id])) {
            throw new CanonicalContainerNotFound($identity->variant_key(), $container_id);
        }
        if (!$this->record_exists($container_id, $record_id)) {
            throw new CanonicalRecordNotFound($container_id, $record_id);
        }
        if ($this->uncertain_operation === 'update_record') {
            return CanonicalMutationReceipt::uncertain(
                $identity,
                CanonicalMutationReceipt::OPERATION_UPDATE,
                CanonicalMutationReceipt::RESOURCE_RECORD,
                $record_id,
                $container_id
            );
        }

        $this->records_by_container[$container_id][$record_id] = [
            'title' => $command->title(),
            'details' => $command->details(),
        ];

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_UPDATE,
            CanonicalMutationReceipt::RESOURCE_RECORD,
            $record_id,
            $container_id
        );
    }

    public function delete_record(
        CanonicalReadIdentity $identity,
        CanonicalDeleteRecordCommand $command
    ): CanonicalMutationReceipt {
        $this->assert_variant($identity);
        $this->assert_not_persistence_failed();
        $container_id = $command->container_id();
        $record_id = $command->record_id();

        if (!isset($this->containers[$container_id])) {
            throw new CanonicalContainerNotFound($identity->variant_key(), $container_id);
        }
        if (!$this->record_exists($container_id, $record_id)) {
            throw new CanonicalRecordNotFound($container_id, $record_id);
        }
        if ($this->uncertain_operation === 'delete_record') {
            return CanonicalMutationReceipt::uncertain(
                $identity,
                CanonicalMutationReceipt::OPERATION_DELETE,
                CanonicalMutationReceipt::RESOURCE_RECORD,
                $record_id,
                $container_id
            );
        }

        unset($this->records_by_container[$container_id][$record_id]);

        return CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_DELETE,
            CanonicalMutationReceipt::RESOURCE_RECORD,
            $record_id,
            $container_id
        );
    }

    private function assert_variant(CanonicalReadIdentity $identity): void {
        if ($identity->variant_key() !== $this->variant_key) {
            throw new CanonicalContainerNotFound($identity->variant_key(), 1);
        }
    }

    private function assert_not_persistence_failed(): void {
        if ($this->uncertain_operation === 'persistence_failed') {
            throw new CanonicalMutationPersistenceFailed('Fixture simulated failure.');
        }
    }

    private function record_exists(int $container_id, int $record_id): bool {
        return isset($this->records_by_container[$container_id][$record_id]);
    }
}
