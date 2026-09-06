<?php
/**
 * AC Test — Canonical write gateway records (SB1-5A1).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-write-gateway-records-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/tests/support/canonical/CanonicalFixtureWriteAdapter.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }
    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

function expect_exception(string $class, callable $fn): bool {
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}

function expect_contract_violation(callable $fn): bool {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return strpos($e->getMessage(), '[invalid_mutation_contract]') === 0;
    }
    return false;
}

$identity = new CanonicalReadIdentity('sample');
$adapter = CanonicalFixtureWriteAdapter::with_seed('sample', [
    1 => ['title' => 'C1', 'details' => null],
], [
    1 => [
        10 => ['title' => 'R10', 'details' => null],
    ],
]);
$registry = new AA_Canonical_Write_Binding_Registry();
$registry->register($identity, $adapter);
$gateway = new CanonicalWriteGateway($registry);

$created = $gateway->create_record($identity, new CanonicalCreateRecordCommand(1, 'Nuevo', null));
ac_assert('Create record confirmed', $created->outcome() === CanonicalMutationReceipt::OUTCOME_CONFIRMED
    && $created->container_id() === 1
    && $created->resource_id() >= 1);

$adapter->uncertain_operation = 'create_record';
$uncertain = $gateway->create_record($identity, new CanonicalCreateRecordCommand(1, 'U', null));
ac_assert('Create record uncertain', $uncertain->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN
    && $uncertain->resource_id() === null);
$adapter->uncertain_operation = null;

ac_assert(
    'Create record missing container',
    expect_exception(CanonicalContainerNotFound::class, static function () use ($gateway, $identity): void {
        $gateway->create_record($identity, new CanonicalCreateRecordCommand(99, 'X', null));
    })
);

$updated = $gateway->update_record($identity, new CanonicalUpdateRecordCommand(1, 10, 'Edit', null));
ac_assert('Update record confirmed', $updated->resource_id() === 10);

ac_assert(
    'Update record missing record',
    expect_exception(CanonicalRecordNotFound::class, static function () use ($gateway, $identity): void {
        $gateway->update_record($identity, new CanonicalUpdateRecordCommand(1, 999, 'X', null));
    })
);

$deleted = $gateway->delete_record($identity, new CanonicalDeleteRecordCommand(1, 10));
ac_assert('Delete record confirmed', $deleted->operation() === CanonicalMutationReceipt::OPERATION_DELETE);

ac_assert(
    'Delete record missing container',
    expect_exception(CanonicalContainerNotFound::class, static function () use ($gateway, $identity): void {
        $gateway->delete_record($identity, new CanonicalDeleteRecordCommand(88, 1));
    })
);

$adapter3 = CanonicalFixtureWriteAdapter::with_seed('sample', [3 => ['title' => 'C3', 'details' => null]], [
    3 => [5 => ['title' => 'R5', 'details' => null]],
]);
$registry3 = new AA_Canonical_Write_Binding_Registry();
$registry3->register($identity, $adapter3);
$gateway3 = new CanonicalWriteGateway($registry3);
$adapter3->uncertain_operation = 'update_record';
$uncertain_up = $gateway3->update_record($identity, new CanonicalUpdateRecordCommand(3, 5, 'X', null));
ac_assert('Update record uncertain', $uncertain_up->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN);

$bad_registry = new AA_Canonical_Write_Binding_Registry();
$bad_registry->register(
    $identity,
    new class($identity) implements CanonicalWriteAdapter {
        /** @var CanonicalReadIdentity */
        private $identity;

        public function __construct(CanonicalReadIdentity $identity) {
            $this->identity = $identity;
        }

        public function create_container(CanonicalReadIdentity $identity, CanonicalCreateContainerCommand $command): CanonicalMutationReceipt {
            throw new RuntimeException('Not used');
        }

        public function update_container(CanonicalReadIdentity $identity, CanonicalUpdateContainerCommand $command): CanonicalMutationReceipt {
            throw new RuntimeException('Not used');
        }

        public function delete_container(CanonicalReadIdentity $identity, CanonicalDeleteContainerCommand $command): CanonicalMutationReceipt {
            throw new RuntimeException('Not used');
        }

        public function create_record(CanonicalReadIdentity $identity, CanonicalCreateRecordCommand $command): CanonicalMutationReceipt {
            throw new RuntimeException('Not used');
        }

        public function update_record(CanonicalReadIdentity $identity, CanonicalUpdateRecordCommand $command): CanonicalMutationReceipt {
            return CanonicalMutationReceipt::confirmed(
                $this->identity,
                CanonicalMutationReceipt::OPERATION_UPDATE,
                CanonicalMutationReceipt::RESOURCE_RECORD,
                999,
                $command->container_id()
            );
        }

        public function delete_record(CanonicalReadIdentity $identity, CanonicalDeleteRecordCommand $command): CanonicalMutationReceipt {
            throw new RuntimeException('Not used');
        }
    }
);
$bad_gateway = new CanonicalWriteGateway($bad_registry);
ac_assert(
    'Record resource_id mismatch is contract violation',
    expect_contract_violation(static function () use ($bad_gateway, $identity): void {
        $bad_gateway->update_record($identity, new CanonicalUpdateRecordCommand(3, 5, 'X', null));
    })
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
