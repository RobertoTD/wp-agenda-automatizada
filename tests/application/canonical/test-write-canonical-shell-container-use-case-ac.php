<?php
/**
 * AC Test — WriteCanonicalShellContainerUseCase (SB1-5A1).
 *
 * Ejecutar: php tests/application/canonical/test-write-canonical-shell-container-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
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
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
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

function make_manifest(string $family_key, string $fl): CanonicalShellManifest {
    return new CanonicalShellManifest(
        new CanonicalReadIdentity($family_key),
        new AA_Canonical_Family_Definition($family_key, $fl)
    );
}

$manifest = make_manifest('sample', 'Muestra');
$empty_registry = new AA_Canonical_Write_Binding_Registry();
$pending_uc = new WriteCanonicalShellContainerUseCase(new CanonicalWriteGateway($empty_registry));
$pending = $pending_uc->create($manifest, new CanonicalCreateContainerCommand('N', null));
ac_assert('Missing binding → write_adapter_pending', $pending->state() === CanonicalShellMutationResult::STATE_WRITE_ADAPTER_PENDING);
ac_assert('Pending has null receipt', $pending->receipt() === null);

$adapter = CanonicalFixtureWriteAdapter::with_seed('sample', [1 => ['title' => 'C1', 'details' => null]]);
$registry = new AA_Canonical_Write_Binding_Registry();
$registry->register($manifest->identity(), $adapter);
$uc = new WriteCanonicalShellContainerUseCase(new CanonicalWriteGateway($registry));

$confirmed = $uc->create($manifest, new CanonicalCreateContainerCommand('Nuevo', null));
ac_assert('Create → confirmed', $confirmed->state() === CanonicalShellMutationResult::STATE_CONFIRMED
    && $confirmed->receipt() instanceof CanonicalMutationReceipt);

$adapter->uncertain_operation = 'update_container';
$uncertain = $uc->update($manifest, new CanonicalUpdateContainerCommand(1, 'Edit', null));
ac_assert('Update → uncertain', $uncertain->state() === CanonicalShellMutationResult::STATE_UNCERTAIN);
$adapter->uncertain_operation = null;

$not_found = $uc->update($manifest, new CanonicalUpdateContainerCommand(404, 'X', null));
ac_assert('Update missing → container_not_found', $not_found->state() === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND);

$adapter->uncertain_operation = 'persistence_failed';
$persist = $uc->create($manifest, new CanonicalCreateContainerCommand('Fail', null));
ac_assert('Persistence failed state', $persist->state() === CanonicalShellMutationResult::STATE_PERSISTENCE_FAILED);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
