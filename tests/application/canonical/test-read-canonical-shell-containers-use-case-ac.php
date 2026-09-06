<?php
/**
 * AC Test — ReadCanonicalShellContainersUseCase.
 *
 * Ejecutar: php tests/application/canonical/test-read-canonical-shell-containers-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPagination.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/tests/support/canonical/CanonicalFixtureReadAdapter.php';

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

$empty_registry = new AA_Canonical_Read_Binding_Registry();
$pending_uc = new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($empty_registry));
$pending = $pending_uc->execute($manifest, 1);
ac_assert('Missing binding → read_adapter_pending', $pending->state() === CanonicalShellReadResult::STATE_READ_ADAPTER_PENDING);
ac_assert('Pending has null page', $pending->page() === null);

$registry = new AA_Canonical_Read_Binding_Registry();
$registry->register(
    $manifest->identity(),
    new CanonicalFixtureReadAdapter('sample', CanonicalFixtureReadAdapter::build_alpha_dataset())
);
$uc = new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($registry));
$resolved = $uc->execute($manifest, 1);
ac_assert('Bound adapter → resolved_page', $resolved->state() === CanonicalShellReadResult::STATE_RESOLVED_PAGE);
ac_assert('Resolved page has 15 items', $resolved->page() instanceof CanonicalPage && count($resolved->page()->items()) === 15);

$empty_manifest = make_manifest('emptyfam', 'Muestra');
$registry->register($empty_manifest->identity(), new CanonicalFixtureReadAdapter('emptyfam', []));
$empty_result = $uc->execute($empty_manifest, 1);
ac_assert('Empty dataset → empty', $empty_result->state() === CanonicalShellReadResult::STATE_EMPTY);

final class BadPerPageAdapter implements CanonicalReadAdapter {
    public function list_containers(int $page, int $per_page): CanonicalPage {
        return new CanonicalPage([], 1, 10, 0, 0, false, false);
    }
    public function get_container(int $container_id): AA_Canonical_Container {
        throw new CanonicalContainerNotFound('bad', $container_id);
    }
    public function list_records(int $container_id, int $page, int $per_page): CanonicalRecordsPage {
        throw new CanonicalContainerNotFound('bad', $container_id);
    }
}

$bad_manifest = make_manifest('badfam', 'Muestra');
$registry->register($bad_manifest->identity(), new BadPerPageAdapter());
$bad = $uc->execute($bad_manifest, 1);
ac_assert('Contract violation → contract_error', $bad->state() === CanonicalShellReadResult::STATE_CONTRACT_ERROR);

final class PersistFailAdapter implements CanonicalReadAdapter {
    public function list_containers(int $page, int $per_page): CanonicalPage {
        throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'forced');
    }
    public function get_container(int $container_id): AA_Canonical_Container {
        throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'forced');
    }
    public function list_records(int $container_id, int $page, int $per_page): CanonicalRecordsPage {
        throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'forced');
    }
}

$persist_manifest = make_manifest('persistfam', 'Muestra');
$registry->register($persist_manifest->identity(), new PersistFailAdapter());
$persist = $uc->execute($persist_manifest, 1);
ac_assert('Persistence failed → contract_error', $persist->state() === CanonicalShellReadResult::STATE_CONTRACT_ERROR);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
