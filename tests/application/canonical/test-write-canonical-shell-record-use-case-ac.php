<?php
/**
 * AC Test — WriteCanonicalShellRecordUseCase (SB1-5A1).
 *
 * Ejecutar: php tests/application/canonical/test-write-canonical-shell-record-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
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
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php';
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

function make_manifest(string $family_key, string $variant_key, string $fl, string $vl): CanonicalShellManifest {
    return new CanonicalShellManifest(
        new CanonicalReadIdentity($family_key, $variant_key),
        new AA_Canonical_Family_Definition($family_key, $fl, $variant_key),
        new AA_Canonical_Variant_Definition($family_key, $variant_key, $vl)
    );
}

$manifest = make_manifest('sample', 'alpha', 'Muestra', 'Alpha');
$empty_registry = new AA_Canonical_Write_Binding_Registry();
$pending_uc = new WriteCanonicalShellRecordUseCase(new CanonicalWriteGateway($empty_registry));
$pending = $pending_uc->create($manifest, new CanonicalCreateRecordCommand(1, 'R', null));
ac_assert('Missing binding → write_adapter_pending', $pending->state() === CanonicalShellMutationResult::STATE_WRITE_ADAPTER_PENDING);

$adapter = CanonicalFixtureWriteAdapter::with_seed('alpha', [
    1 => ['title' => 'C1', 'details' => null],
], [
    1 => [10 => ['title' => 'R10', 'details' => null]],
]);
$registry = new AA_Canonical_Write_Binding_Registry();
$registry->register($manifest->identity(), $adapter);
$uc = new WriteCanonicalShellRecordUseCase(new CanonicalWriteGateway($registry));

$confirmed = $uc->create($manifest, new CanonicalCreateRecordCommand(1, 'Nuevo', null));
ac_assert('Create → confirmed', $confirmed->state() === CanonicalShellMutationResult::STATE_CONFIRMED);

$adapter->uncertain_operation = 'delete_record';
$uncertain = $uc->delete($manifest, new CanonicalDeleteRecordCommand(1, 10));
ac_assert('Delete → uncertain', $uncertain->state() === CanonicalShellMutationResult::STATE_UNCERTAIN);
$adapter->uncertain_operation = null;

$container_missing = $uc->create($manifest, new CanonicalCreateRecordCommand(404, 'X', null));
ac_assert('Missing container → container_not_found', $container_missing->state() === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND);

$record_missing = $uc->update($manifest, new CanonicalUpdateRecordCommand(1, 999, 'X', null));
ac_assert('Missing record → record_not_found', $record_missing->state() === CanonicalShellMutationResult::STATE_RECORD_NOT_FOUND);

$adapter->uncertain_operation = 'persistence_failed';
$persist = $uc->create($manifest, new CanonicalCreateRecordCommand(1, 'Fail', null));
ac_assert('Persistence failed state', $persist->state() === CanonicalShellMutationResult::STATE_PERSISTENCE_FAILED);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
