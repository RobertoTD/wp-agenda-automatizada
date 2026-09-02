<?php
/**
 * AC Test — CanonicalPagination + typed pages (SB1-3A).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-pagination-and-pages-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPagination.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';

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

$meta = new CanonicalPagination(1, 15, 0, 0, false, false, 0);
ac_assert('Empty pagination ok', $meta->total() === 0 && $meta->page() === 1);

$bad_empty = false;
try {
    new CanonicalPagination(2, 15, 0, 0, false, false, 0);
} catch (InvalidArgumentException $e) {
    $bad_empty = true;
}
ac_assert('Empty rejects page!=1', $bad_empty === true);

$c = new AA_Canonical_Container(1, 'alpha', 'T', null, '2026-01-01T00:00:00Z');
$page = new CanonicalPage([$c], 1, 15, 1, 1, false, false);
ac_assert('CanonicalPage public API page()', $page->page() === 1);
ac_assert('CanonicalPage delegates pagination()', $page->pagination() instanceof CanonicalPagination);
ac_assert('CanonicalPage items typed', $page->items()[0] instanceof AA_Canonical_Container);

$wrong_item = false;
try {
    new CanonicalPage([new AA_Canonical_Record(1, 1, 'r', null, '2026-01-01T00:00:00Z')], 1, 15, 1, 1, false, false);
} catch (InvalidArgumentException $e) {
    $wrong_item = true;
}
ac_assert('CanonicalPage rejects Record items', $wrong_item === true);

$r = new AA_Canonical_Record(9, 2, 'R', null, '2026-01-01T00:00:00Z');
$rp = new CanonicalRecordsPage([$r], 1, 15, 1, 1, false, false);
ac_assert('RecordsPage API', $rp->total() === 1 && $rp->items()[0] instanceof AA_Canonical_Record);

$wrong_c = false;
try {
    new CanonicalRecordsPage([$c], 1, 15, 1, 1, false, false);
} catch (InvalidArgumentException $e) {
    $wrong_c = true;
}
ac_assert('RecordsPage rejects Container items', $wrong_c === true);

$pag_src = file_get_contents($plugin_root . '/includes/application/canonical/CanonicalPagination.php');
ac_assert('Pagination has no item types', strpos($pag_src, 'AA_Canonical_Container') === false
    && strpos($pag_src, 'AA_Canonical_Record') === false);
ac_assert('Pagination has no HTTP/preview', stripos($pag_src, 'preview') === false && stripos($pag_src, 'http') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
