<?php
/**
 * AC Test — ReadCanonicalShellRecordsUseCase (SB1-3A).
 *
 * Ejecutar: php tests/application/canonical/test-read-canonical-shell-records-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPagination.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
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

function make_manifest(): CanonicalShellManifest {
    return new CanonicalShellManifest(
        new CanonicalReadIdentity('sample', 'alpha'),
        new AA_Canonical_Family_Definition('sample', 'Muestra', 'alpha'),
        new AA_Canonical_Variant_Definition('sample', 'alpha', 'Alpha')
    );
}

$manifest = make_manifest();

$empty_registry = new AA_Canonical_Read_Binding_Registry();
$pending_uc = new ReadCanonicalShellRecordsUseCase(new CanonicalReadGateway($empty_registry));
$pending = $pending_uc->execute($manifest, 1, 1);
ac_assert('No binding → read_adapter_pending', $pending->state() === CanonicalShellRecordsReadResult::STATE_READ_ADAPTER_PENDING);

$registry = new AA_Canonical_Read_Binding_Registry();
$registry->register(
    $manifest->identity(),
    new CanonicalFixtureReadAdapter(
        'alpha',
        CanonicalFixtureReadAdapter::build_alpha_dataset(),
        CanonicalFixtureReadAdapter::build_alpha_records_dataset()
    )
);
$uc = new ReadCanonicalShellRecordsUseCase(new CanonicalReadGateway($registry));

$resolved = $uc->execute($manifest, 1, 1);
ac_assert('Resolved records page', $resolved->state() === CanonicalShellRecordsReadResult::STATE_RESOLVED_PAGE);
ac_assert('Resolved has parent container', $resolved->container() instanceof AA_Canonical_Container
    && $resolved->container()->id() === 1);
ac_assert('Resolved has page', $resolved->page() instanceof CanonicalRecordsPage
    && count($resolved->page()->items()) === 15);

$empty = $uc->execute($manifest, 2, 1);
ac_assert('Empty existing container', $empty->state() === CanonicalShellRecordsReadResult::STATE_EMPTY
    && $empty->container()->id() === 2
    && $empty->page()->total() === 0);

$not_found = $uc->execute($manifest, 9999, 1);
ac_assert('Missing → container_not_found', $not_found->state() === CanonicalShellRecordsReadResult::STATE_CONTAINER_NOT_FOUND
    && $not_found->container() === null
    && $not_found->page() === null);

final class BadRecordsAdapter implements CanonicalReadAdapter {
    private $inner;
    public function __construct(CanonicalReadAdapter $inner) {
        $this->inner = $inner;
    }
    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
        return $this->inner->list_containers($variant_key, $page, $per_page);
    }
    public function get_container(string $variant_key, int $container_id): AA_Canonical_Container {
        return $this->inner->get_container($variant_key, $container_id);
    }
    public function list_records(string $variant_key, int $container_id, int $page, int $per_page): CanonicalRecordsPage {
        return new CanonicalRecordsPage([], 1, 10, 0, 0, false, false);
    }
}
$bad_reg = new AA_Canonical_Read_Binding_Registry();
$bad_reg->register(
    $manifest->identity(),
    new BadRecordsAdapter(
        new CanonicalFixtureReadAdapter(
            'alpha',
            CanonicalFixtureReadAdapter::build_alpha_dataset(),
            CanonicalFixtureReadAdapter::build_alpha_records_dataset()
        )
    )
);
$contract = (new ReadCanonicalShellRecordsUseCase(new CanonicalReadGateway($bad_reg)))->execute($manifest, 1, 1);
ac_assert('Contract error state', $contract->state() === CanonicalShellRecordsReadResult::STATE_CONTRACT_ERROR);

require_once $plugin_root . '/includes/application/canonical/CanonicalReadPersistenceFailed.php';
final class PersistenceFailRecordsAdapter implements CanonicalReadAdapter {
    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
        return new CanonicalPage([], 1, $per_page, 0, 0, false, false);
    }
    public function get_container(string $variant_key, int $container_id): AA_Canonical_Container {
        throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'boom');
    }
    public function list_records(string $variant_key, int $container_id, int $page, int $per_page): CanonicalRecordsPage {
        throw new CanonicalReadPersistenceFailed(CanonicalReadPersistenceFailed::REASON_SQL, 'boom');
    }
}
$persist_reg = new AA_Canonical_Read_Binding_Registry();
$persist_reg->register($manifest->identity(), new PersistenceFailRecordsAdapter());
$persist = (new ReadCanonicalShellRecordsUseCase(new CanonicalReadGateway($persist_reg)))->execute($manifest, 1, 1);
ac_assert('ReadPersistenceFailed → contract_error', $persist->state() === CanonicalShellRecordsReadResult::STATE_CONTRACT_ERROR);
ac_assert('ReadPersistenceFailed no empty page', $persist->page() === null && $persist->container() === null);

$uc_src = file_get_contents($plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php');
ac_assert('Use case has no preview/GET/HTTP', strpos($uc_src, 'preview') === false
    && strpos($uc_src, '$_GET') === false
    && strpos($uc_src, 'infrastructure/') === false);
$result_src = file_get_contents($plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php');
ac_assert('Result has no preview_page', strpos($result_src, 'preview_page') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
