<?php
/**
 * AC Test — ReadCanonicalShellContainersUseCase (SB1-2B).
 *
 * Ejecutar: php tests/application/canonical/test-read-canonical-shell-containers-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
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

function make_manifest(string $family_key, string $variant_key, string $fl, string $vl): CanonicalShellManifest {
    return new CanonicalShellManifest(
        new CanonicalReadIdentity($family_key, $variant_key),
        new AA_Canonical_Family_Definition($family_key, $fl, $variant_key),
        new AA_Canonical_Variant_Definition($family_key, $variant_key, $vl)
    );
}

$manifest = make_manifest('sample', 'alpha', 'Muestra', 'Alpha');

// Pending: empty binding registry
$empty_registry = new AA_Canonical_Read_Binding_Registry();
$pending_uc = new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($empty_registry));
$pending = $pending_uc->execute($manifest, 1);
ac_assert('Missing binding → read_adapter_pending', $pending->state() === CanonicalShellReadResult::STATE_READ_ADAPTER_PENDING);
ac_assert('Pending has null page', $pending->page() === null);

// Resolved with fixture
$registry = new AA_Canonical_Read_Binding_Registry();
$registry->register(
    $manifest->identity(),
    new CanonicalFixtureReadAdapter('alpha', CanonicalFixtureReadAdapter::build_alpha_dataset())
);
$uc = new ReadCanonicalShellContainersUseCase(new CanonicalReadGateway($registry));
$resolved = $uc->execute($manifest, 1);
ac_assert('Bound adapter → resolved_page', $resolved->state() === CanonicalShellReadResult::STATE_RESOLVED_PAGE);
ac_assert('Resolved page has 15 items', $resolved->page() instanceof CanonicalPage && count($resolved->page()->items()) === 15);

// Empty
$empty_manifest = make_manifest('sample', 'empty', 'Muestra', 'Empty');
$registry->register($empty_manifest->identity(), new CanonicalFixtureReadAdapter('empty', []));
$empty_result = $uc->execute($empty_manifest, 1);
ac_assert('Empty dataset → empty', $empty_result->state() === CanonicalShellReadResult::STATE_EMPTY);

// Contract error via bad adapter
final class BadPerPageAdapter implements CanonicalReadAdapter {
    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
        return new CanonicalPage([], 1, 10, 0, 0, false, false);
    }
}
$bad_manifest = make_manifest('sample', 'bad', 'Muestra', 'Bad');
$registry->register($bad_manifest->identity(), new BadPerPageAdapter());
$contract = $uc->execute($bad_manifest, 1);
ac_assert('Contract violation → contract_error', $contract->state() === CanonicalShellReadResult::STATE_CONTRACT_ERROR);
ac_assert('Contract error has null page', $contract->page() === null);

// Unexpected InvalidArgumentException must propagate
final class WeirdAdapter implements CanonicalReadAdapter {
    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
        throw new InvalidArgumentException('[other_bug] Not a page contract tag.');
    }
}
$weird_manifest = make_manifest('sample', 'weird', 'Muestra', 'Weird');
$registry->register($weird_manifest->identity(), new WeirdAdapter());
$propagated = false;
try {
    $uc->execute($weird_manifest, 1);
} catch (InvalidArgumentException $e) {
    $propagated = strpos($e->getMessage(), '[other_bug]') === 0;
}
ac_assert('Unexpected InvalidArgumentException propagates', $propagated === true);

$result_src = file_get_contents($plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php');
ac_assert('Result has no preview_page state', strpos($result_src, 'preview_page') === false);
$uc_src = file_get_contents($plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php');
ac_assert('Use case does not reference Infrastructure path', strpos($uc_src, 'infrastructure/') === false);
ac_assert('Use case does not mention preview', strpos($uc_src, 'preview') === false);
ac_assert('Use case constructor takes gateway', strpos($uc_src, 'CanonicalReadGateway $gateway') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
