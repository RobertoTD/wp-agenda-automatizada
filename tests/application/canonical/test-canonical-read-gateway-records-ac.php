<?php
/**
 * AC Test — CanonicalReadGateway records (SB1-3A).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-read-gateway-records-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
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

$identity = new CanonicalReadIdentity('sample', 'alpha');
$registry = new AA_Canonical_Read_Binding_Registry();
$registry->register(
    $identity,
    new CanonicalFixtureReadAdapter(
        'alpha',
        CanonicalFixtureReadAdapter::build_alpha_dataset(),
        CanonicalFixtureReadAdapter::build_alpha_records_dataset()
    )
);
$gateway = new CanonicalReadGateway($registry);

$parent = $gateway->get_container($identity, 1);
ac_assert('get_container returns id 1', $parent->id() === 1 && $parent->variant_key() === 'alpha');

$missing = false;
try {
    $gateway->get_container($identity, 9999);
} catch (CanonicalContainerNotFound $e) {
    $missing = ($e->container_id() === 9999);
}
ac_assert('Missing container throws CanonicalContainerNotFound', $missing === true);

$page1 = $gateway->list_records($identity, 1, 1);
ac_assert('Many records page size 15', count($page1->items()) === 15 && $page1->total() > 15);
ac_assert('All records belong to container 1', array_reduce(
    $page1->items(),
    static function ($ok, $item) {
        return $ok && $item->container_id() === 1;
    },
    true
) === true);

$ordered = true;
$items = $page1->items();
for ($i = 1; $i < count($items); $i++) {
    $prev = $items[$i - 1];
    $cur = $items[$i];
    $cmp = $cur->updated_at() <=> $prev->updated_at();
    if (!(($cmp < 0) || ($cmp === 0 && $cur->id() < $prev->id()))) {
        $ordered = false;
        break;
    }
}
ac_assert('Records ordered updated_at DESC, id DESC', $ordered === true);

$null_details = false;
foreach (array_merge($page1->items(), $gateway->list_records($identity, 1, 2)->items()) as $rec) {
    if ($rec->details() === null) {
        $null_details = true;
        break;
    }
}
ac_assert('Dataset includes details=null', $null_details === true);

$empty = $gateway->list_records($identity, 2, 1);
ac_assert('Existing empty container → empty page', $empty->total() === 0 && $empty->page() === 1);

$high = $gateway->list_records($identity, 1, 99);
ac_assert('Out of range adjusts', $high->page() === $page1->total_pages());

$tie_page = $gateway->list_records($identity, 3, 1);
$tie_ids = [];
foreach ($tie_page->items() as $rec) {
    if ($rec->updated_at_canonical() === '2026-02-15T09:00:00Z') {
        $tie_ids[] = $rec->id();
    }
}
ac_assert('Tie break id DESC', $tie_ids === [33, 32, 31]);

$list_missing = false;
try {
    $gateway->list_records($identity, 9999, 1);
} catch (CanonicalContainerNotFound $e) {
    $list_missing = true;
}
ac_assert('list_records missing parent throws not empty page', $list_missing === true);

// Cross-container contamination rejected
final class CrossContainerAdapter implements CanonicalReadAdapter {
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
        $page_obj = $this->inner->list_records($variant_key, $container_id, $page, $per_page);
        $items = $page_obj->items();
        if ($items !== []) {
            $bad = new AA_Canonical_Record(999, 999, 'Intruso', null, '2026-06-01T00:00:00Z');
            $items[0] = $bad;
        }
        return new CanonicalRecordsPage(
            $items,
            $page_obj->page(),
            $page_obj->per_page(),
            $page_obj->total(),
            $page_obj->total_pages(),
            $page_obj->has_previous(),
            $page_obj->has_next()
        );
    }
}
$bad_registry = new AA_Canonical_Read_Binding_Registry();
$bad_registry->register(
    $identity,
    new CrossContainerAdapter(
        new CanonicalFixtureReadAdapter(
            'alpha',
            CanonicalFixtureReadAdapter::build_alpha_dataset(),
            CanonicalFixtureReadAdapter::build_alpha_records_dataset()
        )
    )
);
$contract = false;
try {
    (new CanonicalReadGateway($bad_registry))->list_records($identity, 1, 1);
} catch (InvalidArgumentException $e) {
    $contract = strpos($e->getMessage(), '[invalid_page_contract]') === 0;
}
ac_assert('Rejects foreign container_id in items', $contract === true);

$gw_src = file_get_contents($plugin_root . '/includes/application/canonical/CanonicalReadGateway.php');
ac_assert('Gateway has no preview/WP/Finance', stripos($gw_src, 'preview') === false
    && strpos($gw_src, '$_GET') === false
    && stripos($gw_src, 'amount') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
