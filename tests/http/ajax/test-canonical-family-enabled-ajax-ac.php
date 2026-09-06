<?php
/**
 * AC Test — CanonicalFamilyEnabledAjax (PCU-5A).
 *
 * Ejecutar: php tests/http/ajax/test-canonical-family-enabled-ajax-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

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

$ajax_file = $plugin_root . '/includes/http/ajax/CanonicalFamilyEnabledAjax.php';
$ajax_src = (string) file_get_contents($ajax_file);
$boot_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

ac_assert('Ajax file readable', $ajax_src !== '');
ac_assert('Action constante', strpos($ajax_src, "ACTION = 'aa_update_canonical_family_enabled'") !== false);
ac_assert('Nonce específico', strpos($ajax_src, "NONCE_ACTION = 'aa_canonical_family_enabled'") !== false);
ac_assert('Solo wp_ajax_', strpos($ajax_src, "add_action('wp_ajax_'") !== false);
ac_assert('Sin nopriv en clase', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('Bootstrap registra', strpos($boot_src, 'CanonicalFamilyEnabledAjax::register()') !== false);
ac_assert('Bootstrap sin nopriv', strpos($boot_src, 'wp_ajax_nopriv_aa_update_canonical_family_enabled') === false);
ac_assert('manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('wp_verify_nonce (JSON ante fallo)', strpos($ajax_src, 'wp_verify_nonce') !== false);
ac_assert('Códigos estables', strpos($ajax_src, "'forbidden'") !== false
    && strpos($ajax_src, "'invalid_nonce'") !== false
    && strpos($ajax_src, "'unknown_family'") !== false
    && strpos($ajax_src, "'family_not_provisioned'") !== false
    && strpos($ajax_src, "'invalid_enabled_value'") !== false
    && strpos($ajax_src, "'schema_not_ready'") !== false
    && strpos($ajax_src, "'persistence_failed'") !== false);
ac_assert('Incluye nav en éxito', strpos($ajax_src, "'nav'") !== false
    && strpos($ajax_src, 'AA_Canonical_Family_Enablement_Nav::build') !== false);
ac_assert('No SQL directo en handler', preg_match('/\$wpdb|->query\(|->update\(|->insert\(/', $ajax_src) !== 1);
ac_assert('Usa UseCase', strpos($ajax_src, 'SetCanonicalFamilyEnabledUseCase') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['aa_test_json'] = null;
$GLOBALS['aa_test_logged_in'] = true;
$GLOBALS['aa_test_can_manage'] = true;
$GLOBALS['aa_test_nonce_valid'] = true;
$GLOBALS['aa_test_actions'] = [];

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return (bool) ($GLOBALS['aa_test_logged_in'] ?? false);
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap): bool {
        return ($cap === 'manage_options') && (bool) ($GLOBALS['aa_test_can_manage'] ?? false);
    }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action): bool {
        return (bool) ($GLOBALS['aa_test_nonce_valid'] ?? false) && $nonce === 'good-nonce';
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($v) {
        return $v;
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback): void {
        $GLOBALS['aa_test_actions'][] = $hook;
    }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null): void {
        $GLOBALS['aa_test_json'] = ['success' => true, 'data' => $data, 'status' => $status_code ?? 200];
        throw new RuntimeException('json_sent');
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null): void {
        $GLOBALS['aa_test_json'] = ['success' => false, 'data' => $data, 'status' => $status_code ?? 400];
        throw new RuntimeException('json_sent');
    }
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
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

// Stub store + nav + bootstrap instance via subclassing is hard; redefine class after require of ajax
// by injecting a test double through a thin wrapper: monkey-patch by requiring a fake store first.

final class AA_Canonical_Family_Enablement_Store implements CanonicalFamilyEnablementPort {
    public static $mode = 'ok';
    public static $enabled_map = ['finance' => false, 'archive' => false];

    public function read_for_declared_families(array $family_keys): CanonicalFamilyEnablementSnapshot {
        if (self::$mode === 'schema') {
            throw new CanonicalFamilyEnablementSchemaNotReady('t');
        }
        if (self::$mode === 'persist') {
            throw new CanonicalFamilyEnablementPersistenceFailed('t');
        }
        $by = [];
        foreach ($family_keys as $k) {
            if (!array_key_exists($k, self::$enabled_map)) {
                $by[$k] = new CanonicalFamilyEnablementStatus($k, false, false);
            } else {
                $by[$k] = new CanonicalFamilyEnablementStatus($k, true, (bool) self::$enabled_map[$k]);
            }
        }
        return new CanonicalFamilyEnablementSnapshot($by);
    }

    public function set_enabled(string $family_key, bool $enabled): CanonicalFamilyEnablementResult {
        if (self::$mode === 'schema') {
            throw new CanonicalFamilyEnablementSchemaNotReady('t');
        }
        if (self::$mode === 'persist') {
            throw new CanonicalFamilyEnablementPersistenceFailed('t');
        }
        if (self::$mode === 'not_prov' || !array_key_exists($family_key, self::$enabled_map)) {
            throw new CanonicalFamilyNotProvisioned($family_key);
        }
        $prev = (bool) self::$enabled_map[$family_key];
        self::$enabled_map[$family_key] = $enabled;
        return new CanonicalFamilyEnablementResult($family_key, $enabled, $prev !== $enabled);
    }
}

final class AA_Canonical_Family_Enablement_Nav {
    public static function build($registry, $snapshot): array {
        $items = [];
        foreach ($registry->families() as $family) {
            if ($snapshot->is_enabled($family->key())) {
                $items[] = [
                    'family_key' => $family->key(),
                    'label' => $family->label(),
                    'url' => 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=' . $family->key() . '&variant=general',
                ];
            }
        }
        return $items;
    }
}

AA_Canonical_Core_Bootstrap::bootstrap();
require_once $ajax_file;

function aa_run_ajax(): ?array {
    $GLOBALS['aa_test_json'] = null;
    try {
        CanonicalFamilyEnabledAjax::handle();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent') {
            throw $e;
        }
    }
    return $GLOBALS['aa_test_json'];
}

CanonicalFamilyEnabledAjax::register();
ac_assert('Hook registrado', in_array('wp_ajax_aa_update_canonical_family_enabled', $GLOBALS['aa_test_actions'], true));

$_POST = ['nonce' => 'good-nonce', 'family_key' => 'finance', 'enabled' => '1'];
$GLOBALS['aa_test_logged_in'] = false;
$r = aa_run_ajax();
ac_assert('No autenticado → forbidden', ($r['success'] ?? null) === false && ($r['data']['code'] ?? '') === 'forbidden');
$GLOBALS['aa_test_logged_in'] = true;

$GLOBALS['aa_test_can_manage'] = false;
$r = aa_run_ajax();
ac_assert('Sin manage_options → forbidden', ($r['data']['code'] ?? '') === 'forbidden');
$GLOBALS['aa_test_can_manage'] = true;

$GLOBALS['aa_test_nonce_valid'] = false;
$r = aa_run_ajax();
ac_assert('Nonce inválido', ($r['data']['code'] ?? '') === 'invalid_nonce');
$GLOBALS['aa_test_nonce_valid'] = true;

$_POST['family_key'] = ['finance'];
$r = aa_run_ajax();
ac_assert('Array family_key rechazado', ($r['data']['code'] ?? '') === 'unknown_family');
$_POST['family_key'] = 'ghostly';
$r = aa_run_ajax();
ac_assert('Familia desconocida', ($r['data']['code'] ?? '') === 'unknown_family');

$_POST['family_key'] = 'finance';
$_POST['enabled'] = '2';
$r = aa_run_ajax();
ac_assert('enabled=2 inválido', ($r['data']['code'] ?? '') === 'invalid_enabled_value');
$_POST['enabled'] = ['1'];
$r = aa_run_ajax();
ac_assert('enabled array inválido', ($r['data']['code'] ?? '') === 'invalid_enabled_value');

AA_Canonical_Family_Enablement_Store::$mode = 'schema';
$_POST['enabled'] = '1';
$r = aa_run_ajax();
ac_assert('schema_not_ready', ($r['data']['code'] ?? '') === 'schema_not_ready');

AA_Canonical_Family_Enablement_Store::$mode = 'not_prov';
$r = aa_run_ajax();
ac_assert('family_not_provisioned', ($r['data']['code'] ?? '') === 'family_not_provisioned');

AA_Canonical_Family_Enablement_Store::$mode = 'persist';
$r = aa_run_ajax();
ac_assert('persistence_failed', ($r['data']['code'] ?? '') === 'persistence_failed');

AA_Canonical_Family_Enablement_Store::$mode = 'ok';
AA_Canonical_Family_Enablement_Store::$enabled_map = ['finance' => false, 'archive' => false];
$_POST['enabled'] = '1';
$r = aa_run_ajax();
ac_assert('Éxito enable', ($r['success'] ?? false) === true);
ac_assert('Payload family_key', ($r['data']['family_key'] ?? '') === 'finance');
ac_assert('Payload is_enabled', ($r['data']['is_enabled'] ?? false) === true);
ac_assert('Payload changed', ($r['data']['changed'] ?? false) === true);
ac_assert('Nav incluye finance', isset($r['data']['nav'][0]['family_key']) && $r['data']['nav'][0]['family_key'] === 'finance');
ac_assert('JSON sin SQL sensible', strpos(json_encode($r), 'SELECT') === false && strpos(json_encode($r), 'wpdb') === false);

$r = aa_run_ajax();
ac_assert('changed=false en no-op', ($r['data']['changed'] ?? true) === false);

$_POST['enabled'] = '0';
$r = aa_run_ajax();
ac_assert('Éxito disable', ($r['data']['is_enabled'] ?? true) === false && ($r['data']['changed'] ?? false) === true);
ac_assert('Nav vacía tras disable', isset($r['data']['nav']) && $r['data']['nav'] === []);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
