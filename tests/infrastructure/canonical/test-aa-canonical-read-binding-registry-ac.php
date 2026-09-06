<?php
/**
 * AC Test — AA_Canonical_Read_Binding_Registry fail-on-duplicate (PCU-5B).
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-read-binding-registry-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';

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

class StubReadAdapterA implements CanonicalReadAdapter {
    public function list_containers(int $page, int $per_page): CanonicalPage {
        throw new \LogicException('stub');
    }
    public function get_container(int $container_id): AA_Canonical_Container {
        throw new \LogicException('stub');
    }
    public function list_records(int $container_id, int $page, int $per_page): CanonicalRecordsPage {
        throw new \LogicException('stub');
    }
}

class StubReadAdapterB implements CanonicalReadAdapter {
    public function list_containers(int $page, int $per_page): CanonicalPage {
        throw new \LogicException('stub');
    }
    public function get_container(int $container_id): AA_Canonical_Container {
        throw new \LogicException('stub');
    }
    public function list_records(int $container_id, int $page, int $per_page): CanonicalRecordsPage {
        throw new \LogicException('stub');
    }
}

$registry = new AA_Canonical_Read_Binding_Registry();
$id = new CanonicalReadIdentity('finance');
$first = new StubReadAdapterA();
$second = new StubReadAdapterB();
$registry->register($id, $first);

$dup = false;
$msg = '';
try {
    $registry->register($id, $second);
} catch (\LogicException $e) {
    $dup = true;
    $msg = $e->getMessage();
}
ac_assert('Duplicate read throws', $dup);
ac_assert('Duplicate read tag', strpos($msg, '[duplicate_read_binding]') !== false);
ac_assert('Original read binding preserved', $registry->require($id) === $first);

$src = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php');
ac_assert('Source has duplicate_read_binding', strpos($src, '[duplicate_read_binding]') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
