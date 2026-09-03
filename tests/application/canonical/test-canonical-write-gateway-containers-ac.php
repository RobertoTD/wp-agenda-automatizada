<?php
/**
 * AC Test — Canonical write gateway containers (SB1-5A1).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-write-gateway-containers-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/tests/support/canonical/CanonicalFixtureWriteAdapter.php';

final class ContractBreakingContainerWriteAdapter implements CanonicalWriteAdapter {
    /** @var CanonicalMutationReceipt */
    private $receipt;

    public function __construct(CanonicalMutationReceipt $receipt) {
        $this->receipt = $receipt;
    }

    public function create_container(CanonicalReadIdentity $identity, CanonicalCreateContainerCommand $command): CanonicalMutationReceipt {
        return $this->receipt;
    }

    public function update_container(CanonicalReadIdentity $identity, CanonicalUpdateContainerCommand $command): CanonicalMutationReceipt {
        return $this->receipt;
    }

    public function delete_container(CanonicalReadIdentity $identity, CanonicalDeleteContainerCommand $command): CanonicalMutationReceipt {
        return $this->receipt;
    }

    public function create_record(CanonicalReadIdentity $identity, CanonicalCreateRecordCommand $command): CanonicalMutationReceipt {
        throw new RuntimeException('Not used');
    }

    public function update_record(CanonicalReadIdentity $identity, CanonicalUpdateRecordCommand $command): CanonicalMutationReceipt {
        throw new RuntimeException('Not used');
    }

    public function delete_record(CanonicalReadIdentity $identity, CanonicalDeleteRecordCommand $command): CanonicalMutationReceipt {
        throw new RuntimeException('Not used');
    }
}

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

$identity = new CanonicalReadIdentity('sample', 'alpha');
$registry = new AA_Canonical_Write_Binding_Registry();
$adapter = CanonicalFixtureWriteAdapter::with_seed('alpha', [
    1 => ['title' => 'C1', 'details' => null],
]);
$registry->register($identity, $adapter);
$gateway = new CanonicalWriteGateway($registry);

$missing = new AA_Canonical_Write_Binding_Registry();
$missing_gateway = new CanonicalWriteGateway($missing);
ac_assert(
    'Missing binding throws',
    expect_exception(CanonicalWriteBindingNotFound::class, static function () use ($missing_gateway, $identity): void {
        $missing_gateway->create_container($identity, new CanonicalCreateContainerCommand('Nuevo', null));
    })
);

$receipt = $gateway->create_container($identity, new CanonicalCreateContainerCommand('Nuevo', 'Det'));
ac_assert('Create confirmed', $receipt->outcome() === CanonicalMutationReceipt::OUTCOME_CONFIRMED
    && $receipt->resource_id() >= 1
    && $receipt->container_id() === null);

$adapter->uncertain_operation = 'create_container';
$uncertain = $gateway->create_container($identity, new CanonicalCreateContainerCommand('U', null));
ac_assert('Create uncertain', $uncertain->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN);
$adapter->uncertain_operation = null;

$updated = $gateway->update_container($identity, new CanonicalUpdateContainerCommand(1, 'Editado', null));
ac_assert('Update confirmed', $updated->outcome() === CanonicalMutationReceipt::OUTCOME_CONFIRMED
    && $updated->resource_id() === 1);

ac_assert(
    'Update missing container',
    expect_exception(CanonicalContainerNotFound::class, static function () use ($gateway, $identity): void {
        $gateway->update_container($identity, new CanonicalUpdateContainerCommand(99, 'X', null));
    })
);

$deleted = $gateway->delete_container($identity, new CanonicalDeleteContainerCommand(1));
ac_assert('Delete confirmed', $deleted->operation() === CanonicalMutationReceipt::OPERATION_DELETE);

$adapter2 = CanonicalFixtureWriteAdapter::with_seed('alpha', [2 => ['title' => 'C2', 'details' => null]]);
$registry2 = new AA_Canonical_Write_Binding_Registry();
$registry2->register($identity, $adapter2);
$gateway2 = new CanonicalWriteGateway($registry2);
$adapter2->uncertain_operation = 'persistence_failed';
ac_assert(
    'Persistence failed propagates',
    expect_exception(CanonicalMutationPersistenceFailed::class, static function () use ($gateway2, $identity): void {
        $gateway2->create_container($identity, new CanonicalCreateContainerCommand('Fail', null));
    })
);

$wrong_identity = new CanonicalReadIdentity('sample', 'beta');
$bad_registry = new AA_Canonical_Write_Binding_Registry();
$bad_registry->register(
    $identity,
    new ContractBreakingContainerWriteAdapter(
        CanonicalMutationReceipt::confirmed(
            $wrong_identity,
            CanonicalMutationReceipt::OPERATION_CREATE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            5,
            null
        )
    )
);
$bad_gateway = new CanonicalWriteGateway($bad_registry);
ac_assert(
    'Identity mismatch is contract violation',
    expect_contract_violation(static function () use ($bad_gateway, $identity): void {
        $bad_gateway->create_container($identity, new CanonicalCreateContainerCommand('X', null));
    })
);

$bad_registry2 = new AA_Canonical_Write_Binding_Registry();
$bad_registry2->register(
    $identity,
    new ContractBreakingContainerWriteAdapter(
        CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_UPDATE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            5,
            null
        )
    )
);
$bad_gateway2 = new CanonicalWriteGateway($bad_registry2);
ac_assert(
    'Container create with wrong operation is contract violation',
    expect_contract_violation(static function () use ($bad_gateway2, $identity): void {
        $bad_gateway2->create_container($identity, new CanonicalCreateContainerCommand('X', null));
    })
);

$bad_registry3 = new AA_Canonical_Write_Binding_Registry();
$bad_registry3->register(
    $identity,
    new ContractBreakingContainerWriteAdapter(
        CanonicalMutationReceipt::confirmed(
            $identity,
            CanonicalMutationReceipt::OPERATION_UPDATE,
            CanonicalMutationReceipt::RESOURCE_CONTAINER,
            99,
            null
        )
    )
);
$bad_gateway3 = new CanonicalWriteGateway($bad_registry3);
ac_assert(
    'Container update resource_id mismatch is contract violation',
    expect_contract_violation(static function () use ($bad_gateway3, $identity): void {
        $bad_gateway3->update_container($identity, new CanonicalUpdateContainerCommand(5, 'X', null));
    })
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
