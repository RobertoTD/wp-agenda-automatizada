<?php
/**
 * AC Test — ReadCanonicalShellAllContainersUseCase (puerto inyectable).
 *
 * Ejecutar: php tests/application/canonical/test-read-canonical-shell-all-containers-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalAggregatedContainerItem.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalAggregatedContainersPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalAggregatedContainersPort.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellAggregatedReadResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellAllContainersUseCase.php';

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

final class FakeAggregatedContainersPort implements CanonicalAggregatedContainersPort {
    /** @var int */
    public $calls = 0;
    /** @var list<string>|null */
    public $last_keys = null;
    /** @var CanonicalAggregatedContainersPage|null */
    public $page = null;
    /** @var \Throwable|null */
    public $throw = null;

    public function list_containers(
        array $family_keys,
        array $labels_by_key,
        int $page,
        int $per_page
    ): CanonicalAggregatedContainersPage {
        $this->calls++;
        $this->last_keys = $family_keys;
        if ($this->throw instanceof \Throwable) {
            throw $this->throw;
        }
        if ($this->page instanceof CanonicalAggregatedContainersPage) {
            return $this->page;
        }
        return new CanonicalAggregatedContainersPage([], 1, $per_page, 0, 0, false, false);
    }
}

$port = new FakeAggregatedContainersPort();
$uc = new ReadCanonicalShellAllContainersUseCase($port);
$result = $uc->execute([], 1);
ac_assert('Empty family set is empty state', $result->state() === CanonicalShellAggregatedReadResult::STATE_EMPTY);
ac_assert('Empty family set never calls port', $port->calls === 0);
ac_assert('Empty page total is 0', $result->page() instanceof CanonicalAggregatedContainersPage
    && $result->page()->total() === 0);

$finance = new AA_Canonical_Family_Definition('finance', 'Finanzas');
$archive = new AA_Canonical_Family_Definition('archive', 'Archivo');
$now = AA_Canonical_Instant::from(new DateTimeImmutable('2026-01-02 12:00:00', new DateTimeZone('UTC')))->to_datetime();
$container = new AA_Canonical_Container(11, 'Lista A', null, $now);
$item = new CanonicalAggregatedContainerItem($container, 'finance', 'Finanzas');
$port->page = new CanonicalAggregatedContainersPage(
    [$item],
    1,
    CanonicalReadGateway::PAGE_SIZE,
    1,
    1,
    false,
    false
);

$result = $uc->execute([$finance, $archive], 1);
ac_assert('Non-empty families call port once', $port->calls === 1);
ac_assert('Port receives both family keys', $port->last_keys === ['finance', 'archive']);
ac_assert('Resolved page keeps family association', $result->state() === CanonicalShellAggregatedReadResult::STATE_RESOLVED_PAGE
    && $result->page()->items()[0]->family_key() === 'finance'
    && $result->page()->items()[0]->family_label() === 'Finanzas');

$port->page = new CanonicalAggregatedContainersPage(
    [],
    1,
    CanonicalReadGateway::PAGE_SIZE,
    0,
    0,
    false,
    false
);
$result = $uc->execute([$finance], 1);
ac_assert('Zero containers is empty state', $result->state() === CanonicalShellAggregatedReadResult::STATE_EMPTY);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}
echo "OK\n";
exit(0);
