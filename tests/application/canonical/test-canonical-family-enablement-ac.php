<?php
/**
 * AC Test — Canonical family enablement Application (PCU-5A).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-family-enablement-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyUnknown.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPort.php';
require_once $plugin_root . '/includes/application/canonical/SetCanonicalFamilyEnabledCommand.php';
require_once $plugin_root . '/includes/application/canonical/SetCanonicalFamilyEnabledUseCase.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';

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

final class FakeEnablementPort implements CanonicalFamilyEnablementPort {
    /** @var array<string, ?bool> null = not provisioned */
    public $rows = [];
    public $schema_ready = true;
    public $fail_persistence = false;
    public $set_calls = 0;

    public function read_for_declared_families(array $family_keys): CanonicalFamilyEnablementSnapshot {
        if (!$this->schema_ready) {
            throw new CanonicalFamilyEnablementSchemaNotReady('missing');
        }
        if ($this->fail_persistence) {
            throw new CanonicalFamilyEnablementPersistenceFailed('read');
        }
        $by_key = [];
        foreach ($family_keys as $key) {
            if (!array_key_exists($key, $this->rows) || $this->rows[$key] === null) {
                $by_key[$key] = new CanonicalFamilyEnablementStatus($key, false, false);
            } else {
                $by_key[$key] = new CanonicalFamilyEnablementStatus($key, true, (bool) $this->rows[$key]);
            }
        }
        return new CanonicalFamilyEnablementSnapshot($by_key);
    }

    public function set_enabled(string $family_key, bool $enabled): CanonicalFamilyEnablementResult {
        $this->set_calls++;
        if (!$this->schema_ready) {
            throw new CanonicalFamilyEnablementSchemaNotReady('missing');
        }
        if ($this->fail_persistence) {
            throw new CanonicalFamilyEnablementPersistenceFailed('write');
        }
        if (!array_key_exists($family_key, $this->rows) || $this->rows[$family_key] === null) {
            throw new CanonicalFamilyNotProvisioned($family_key);
        }
        $prev = (bool) $this->rows[$family_key];
        $this->rows[$family_key] = $enabled;
        return new CanonicalFamilyEnablementResult($family_key, $enabled, $prev !== $enabled);
    }
}

echo "=== Command ===\n";
$cmd = new SetCanonicalFamilyEnabledCommand('finance', true);
ac_assert('Command family_key', $cmd->family_key() === 'finance');
ac_assert('Command enabled true', $cmd->enabled() === true);

$cmd_off = new SetCanonicalFamilyEnabledCommand('archive', false);
ac_assert('Command enabled false', $cmd_off->enabled() === false);

$invalid_cmd = false;
try {
    new SetCanonicalFamilyEnabledCommand('Bad-Key', true);
} catch (\InvalidArgumentException $e) {
    $invalid_cmd = strpos($e->getMessage(), '[invalid_key]') !== false;
}
ac_assert('Command rejects invalid key', $invalid_cmd);

echo "=== Read Use Case ===\n";
$registry = AA_Canonical_Core_Bootstrap::build_registry();
$port = new FakeEnablementPort();
$port->rows = ['finance' => false, 'archive' => true];
$read = new ReadCanonicalFamilyEnablementUseCase($port);
$snap = $read->execute($registry);
ac_assert('Snapshot has finance', $snap->has('finance'));
ac_assert('Snapshot has archive', $snap->has('archive'));
ac_assert('Finance provisioned disabled', $snap->is_provisioned('finance') && !$snap->is_enabled('finance'));
ac_assert('Archive enabled', $snap->is_enabled('archive'));
ac_assert('Enabled keys only archive', $snap->enabled_family_keys() === ['archive']);

$port->rows = ['finance' => false]; // archive missing → unprovisioned
$snap2 = $read->execute($registry);
ac_assert('Archive not provisioned', !$snap2->is_provisioned('archive'));
ac_assert('Archive not enabled when missing', !$snap2->is_enabled('archive'));

$port->schema_ready = false;
$schema_threw = false;
try {
    $read->execute($registry);
} catch (CanonicalFamilyEnablementSchemaNotReady $e) {
    $schema_threw = true;
}
ac_assert('Read throws schema_not_ready', $schema_threw);
$port->schema_ready = true;

$port->fail_persistence = true;
$persist_threw = false;
try {
    $read->execute($registry);
} catch (CanonicalFamilyEnablementPersistenceFailed $e) {
    $persist_threw = true;
}
ac_assert('Read throws persistence_failed', $persist_threw);
$port->fail_persistence = false;

echo "=== Set Use Case ===\n";
$port->rows = ['finance' => false, 'archive' => false];
$set = new SetCanonicalFamilyEnabledUseCase($port, $registry);
$res = $set->execute(new SetCanonicalFamilyEnabledCommand('finance', true));
ac_assert('Set enable changed', $res->changed() === true && $res->is_enabled() === true);
$res_noop = $set->execute(new SetCanonicalFamilyEnabledCommand('finance', true));
ac_assert('Set no-op changed=false', $res_noop->changed() === false && $res_noop->is_enabled() === true);

$unknown2 = false;
try {
    $set->execute(new SetCanonicalFamilyEnabledCommand('billing', true));
} catch (CanonicalFamilyUnknown $e) {
    $unknown2 = $e->family_key() === 'billing';
}
ac_assert('Unknown declared family billing', $unknown2);

$port->rows = ['finance' => null, 'archive' => false];
$not_prov = false;
try {
    $set->execute(new SetCanonicalFamilyEnabledCommand('finance', true));
} catch (CanonicalFamilyNotProvisioned $e) {
    $not_prov = true;
}
ac_assert('Not provisioned throws', $not_prov);

$port->rows = ['finance' => false, 'archive' => false];
$port->fail_persistence = true;
$write_fail = false;
try {
    $set->execute(new SetCanonicalFamilyEnabledCommand('finance', true));
} catch (CanonicalFamilyEnablementPersistenceFailed $e) {
    $write_fail = true;
}
ac_assert('Set persistence failed', $write_fail);

echo "=== Contención WP ===\n";
$app_files = [
    'SetCanonicalFamilyEnabledCommand.php',
    'SetCanonicalFamilyEnabledUseCase.php',
    'ReadCanonicalFamilyEnablementUseCase.php',
    'CanonicalFamilyEnablementPort.php',
    'CanonicalFamilyEnablementResult.php',
    'CanonicalFamilyEnablementSnapshot.php',
];
$wp_free = true;
foreach ($app_files as $f) {
    $src = (string) file_get_contents($plugin_root . '/includes/application/canonical/' . $f);
    if (preg_match('/\b(wpdb|wp_send_json|add_action|current_user_can|check_ajax_referer)\b/', $src)) {
        $wp_free = false;
        echo "WP leak in {$f}\n";
    }
}
ac_assert('Application sin WordPress', $wp_free);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
