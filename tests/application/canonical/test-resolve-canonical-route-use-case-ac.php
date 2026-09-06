<?php
/**
 * AC Test — ResolveCanonicalRouteUseCase.
 *
 * Ejecutar: php tests/application/canonical/test-resolve-canonical-route-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/ResolveCanonicalRouteUseCase.php';

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

$registry = AA_Canonical_Core_Bootstrap::build_registry();
$use_case = new ResolveCanonicalRouteUseCase($registry);

$res1 = $use_case->execute([
    'family_key' => 'finance',
]);
ac_assert('Explicit finance success', $res1['success'] === true);
ac_assert('Family key matches', ($res1['data']['family'] ?? null) instanceof AA_Canonical_Family_Definition && $res1['data']['family']->key() === 'finance');
ac_assert('Family label matches', $res1['data']['family']->label() === 'Finanzas');
ac_assert('No variant in route data', !array_key_exists('variant', $res1['data']));
ac_assert('No used_default_variant in route data', !array_key_exists('used_default_variant', $res1['data']));

$res_arch = $use_case->execute([
    'family_key' => 'archive',
]);
ac_assert('Explicit archive success', $res_arch['success'] === true);
ac_assert('Archive family key', $res_arch['data']['family']->key() === 'archive');
ac_assert('Archive family label Archivo', $res_arch['data']['family']->label() === 'Archivo');

$res4_missing = $use_case->execute([]);
ac_assert('Missing family_key fails', $res4_missing['success'] === false);
ac_assert('Missing family_key code is missing_family', ($res4_missing['error']['code'] ?? '') === 'missing_family');

$res4_invalid = $use_case->execute(['family_key' => 'Invalid-Family']);
ac_assert('Invalid format family_key fails', $res4_invalid['success'] === false);
ac_assert('Invalid format family_key code is invalid_family_key', ($res4_invalid['error']['code'] ?? '') === 'invalid_family_key');

$res4_array = $use_case->execute(['family_key' => ['finance']]);
ac_assert('Array family_key fails', $res4_array['success'] === false && ($res4_array['error']['code'] ?? '') === 'invalid_family_key');

$res5_unknown_f = $use_case->execute(['family_key' => 'unknown_fam']);
ac_assert('Unknown family fails', $res5_unknown_f['success'] === false);
ac_assert('Unknown family code is unknown_family', ($res5_unknown_f['error']['code'] ?? '') === 'unknown_family');

// Obsolete variant_key in input is ignored
$res_ignore_variant = $use_case->execute([
    'family_key' => 'finance',
    'variant_key' => 'unknown_var',
]);
ac_assert('Obsolete variant_key ignored when family valid', $res_ignore_variant['success'] === true);

$unfrozen_registry = new AA_Canonical_Registry();
$unfrozen_use_case = new ResolveCanonicalRouteUseCase($unfrozen_registry);
$res7_unfrozen = $unfrozen_use_case->execute(['family_key' => 'finance']);
ac_assert('Unfrozen registry fails with canonical_unavailable', $res7_unfrozen['success'] === false && ($res7_unfrozen['error']['code'] ?? '') === 'canonical_unavailable');

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
