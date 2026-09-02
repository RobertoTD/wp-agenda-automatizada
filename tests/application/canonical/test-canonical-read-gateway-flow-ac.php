<?php
/**
 * AC Test — Canonical read gateway end-to-end flow (SB1-2A).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-read-gateway-flow-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
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

$identity_alpha = new CanonicalReadIdentity('sample', 'alpha');
$identity_beta = new CanonicalReadIdentity('sample', 'beta');
ac_assert('Alpha qualified key', $identity_alpha->qualified_key() === 'sample.alpha');
ac_assert('Beta qualified key', $identity_beta->qualified_key() === 'sample.beta');

$registry = new AA_Canonical_Read_Binding_Registry();
$registry->register(
    $identity_alpha,
    new CanonicalFixtureReadAdapter('alpha', CanonicalFixtureReadAdapter::build_alpha_dataset())
);
$registry->register(
    $identity_beta,
    new CanonicalFixtureReadAdapter('beta', CanonicalFixtureReadAdapter::build_beta_dataset())
);

$gateway = new CanonicalReadGateway($registry);

// Integral flow
$page1 = $gateway->list_containers($identity_alpha, 1);
ac_assert('Page 1 is CanonicalPage', $page1 instanceof CanonicalPage);
ac_assert('Page 1 size is 15', count($page1->items()) === 15);
ac_assert('Page 1 per_page is 15', $page1->per_page() === 15);
ac_assert('Alpha total > 15', $page1->total() > 15);
ac_assert('Page 1 has_next', $page1->has_next() === true);
ac_assert('Page 1 has_previous false', $page1->has_previous() === false);

$items = $page1->items();
$ordered_ok = true;
for ($i = 1; $i < count($items); $i++) {
    $prev = $items[$i - 1];
    $cur = $items[$i];
    $cmp = $cur->updated_at() <=> $prev->updated_at();
    if (!(($cmp < 0) || ($cmp === 0 && $cur->id() < $prev->id()))) {
        $ordered_ok = false;
        break;
    }
}
ac_assert('Items ordered updated_at DESC, id DESC', $ordered_ok === true);

// Tie-break: same timestamp ids 3,2,1 must appear as 3 then 2 then 1 on page 1 (newest group).
$tie_ids = [];
foreach ($page1->items() as $c) {
    if ($c->updated_at_canonical() === '2026-01-10T12:00:00Z') {
        $tie_ids[] = $c->id();
    }
}
$tie_break = [];
foreach ($tie_ids as $id) {
    if (in_array($id, [1, 2, 3], true)) {
        $tie_break[] = $id;
    }
}
ac_assert('Tie group ids 3,2,1 appear in id DESC', $tie_break === [3, 2, 1]);

$page2 = $gateway->list_containers($identity_alpha, 2);
ac_assert('Page 2 has remaining items', count($page2->items()) === ($page1->total() - 15));
ac_assert('Page 2 has_previous', $page2->has_previous() === true);
ac_assert('Page 2 has_next false', $page2->has_next() === false);

$has_null_details = false;
foreach (array_merge($page1->items(), $page2->items()) as $c) {
    if ($c->details() === null) {
        $has_null_details = true;
        break;
    }
}
ac_assert('Dataset exposes details=null', $has_null_details === true);

// page < 1 normalized
$page_norm = $gateway->list_containers($identity_alpha, 0);
ac_assert('page<1 normalizes to page 1', $page_norm->page() === 1 && count($page_norm->items()) === 15);

// page beyond range adjusts inside adapter
$page_high = $gateway->list_containers($identity_alpha, 99);
ac_assert('Out-of-range adjusts to last page', $page_high->page() === $page1->total_pages());
ac_assert('Out-of-range last page item count', count($page_high->items()) === ($page1->total() - 15));

// Empty fixture
$empty_identity = new CanonicalReadIdentity('sample', 'empty');
$registry->register($empty_identity, new CanonicalFixtureReadAdapter('empty', []));
$empty_page = $gateway->list_containers($empty_identity, 5);
ac_assert('Empty total=0 forces page=1', $empty_page->page() === 1 && $empty_page->total() === 0 && $empty_page->total_pages() === 0);
ac_assert('Empty flags false', $empty_page->has_previous() === false && $empty_page->has_next() === false);

// Dual binding without gateway code change
$beta_page = $gateway->list_containers($identity_beta, 1);
ac_assert('Same gateway serves beta binding', $beta_page->total() === 2 && $beta_page->items()[0]->variant_key() === 'beta');
ac_assert('Beta first title is Catalogo uno', $beta_page->items()[0]->title() === 'Catalogo uno');

// Missing binding
$missing = new CanonicalReadIdentity('sample', 'ghost');
$threw = false;
try {
    $gateway->list_containers($missing, 1);
} catch (CanonicalReadBindingNotFound $e) {
    $threw = ($e->qualified_key() === 'sample.ghost');
}
ac_assert('Missing binding throws CanonicalReadBindingNotFound', $threw === true);

// Invalid identity keys
$invalid_id = false;
try {
    new CanonicalReadIdentity('Bad', 'alpha');
} catch (InvalidArgumentException $e) {
    $invalid_id = true;
}
ac_assert('Invalid family key rejected by CanonicalReadIdentity', $invalid_id === true);

// Architecture: Application gateway source must not import Infrastructure concrete
$gateway_src = file_get_contents($plugin_root . '/includes/application/canonical/CanonicalReadGateway.php');
ac_assert('Gateway does not reference Binding Registry class', strpos($gateway_src, 'AA_Canonical_Read_Binding_Registry') === false);
ac_assert('Gateway does not require infrastructure path', strpos($gateway_src, 'infrastructure/') === false);
ac_assert('Gateway depends on resolver port', strpos($gateway_src, 'CanonicalReadAdapterResolver') !== false);

$app_files = glob($plugin_root . '/includes/application/canonical/CanonicalRead*.php') ?: [];
$app_files[] = $plugin_root . '/includes/application/canonical/CanonicalPage.php';
$app_files[] = $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
$finance_leak = false;
foreach ($app_files as $file) {
    $src = file_get_contents($file);
    if (stripos($src, 'finance') !== false || stripos($src, 'amount') !== false || strpos($src, 'AA_FINANCE') !== false) {
        $finance_leak = true;
        break;
    }
}
ac_assert('Application read contracts have no Finance vocabulary', $finance_leak === false);

$fixture_in_plugin = false;
$plugin_main = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
if (strpos($plugin_main, 'CanonicalFixtureReadAdapter') !== false || strpos($plugin_main, 'tests/support/canonical') !== false) {
    $fixture_in_plugin = true;
}
ac_assert('Fixture not loaded from plugin bootstrap', $fixture_in_plugin === false);

$shell_root = file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
ac_assert('SB1-1 root still has no gateway/page wiring', strpos($shell_root, 'CanonicalReadGateway') === false);
ac_assert('SB1-1 root still shows Sin datos de familia message marker', strpos($shell_root, 'Sin datos de familia') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}
exit(0);
