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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
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

// 1. Explicit finance and general resolution
$res1 = $use_case->execute([
    'family_key' => 'finance',
    'variant_key' => 'general',
]);
ac_assert('Explicit finance.general success', $res1['success'] === true);
ac_assert('Family key matches', ($res1['data']['family'] ?? null) instanceof AA_Canonical_Family_Definition && $res1['data']['family']->key() === 'finance');
ac_assert('Family label matches', $res1['data']['family']->label() === 'Finanzas');
ac_assert('Variant key matches', ($res1['data']['variant'] ?? null) instanceof AA_Canonical_Variant_Definition && $res1['data']['variant']->key() === 'general');
ac_assert('Variant label matches', $res1['data']['variant']->label() === 'General');
ac_assert('used_default_variant is false when explicit', ($res1['data']['used_default_variant'] ?? null) === false);

// 1b. Archive resolution
$res_arch = $use_case->execute([
    'family_key' => 'archive',
    'variant_key' => 'general',
]);
ac_assert('Explicit archive.general success', $res_arch['success'] === true);
ac_assert('Archive family key', $res_arch['data']['family']->key() === 'archive');
ac_assert('Archive family label Archivo', $res_arch['data']['family']->label() === 'Archivo');
ac_assert('Archive variant general', $res_arch['data']['variant']->key() === 'general');

$res_arch_def = $use_case->execute(['family_key' => 'archive']);
ac_assert('Archive without variant uses general', $res_arch_def['success'] === true
    && $res_arch_def['data']['variant']->key() === 'general'
    && $res_arch_def['data']['used_default_variant'] === true);

// 2. Default variant resolution when variant_key is omitted (not present or null)
$res2 = $use_case->execute([
    'family_key' => 'finance',
]);
ac_assert('Omitted variant_key success', $res2['success'] === true);
ac_assert('Omitted variant resolves to default general', ($res2['data']['variant'] ?? null) && $res2['data']['variant']->key() === 'general');
ac_assert('used_default_variant is true when omitted', ($res2['data']['used_default_variant'] ?? null) === true);

$res2_null = $use_case->execute([
    'family_key' => 'finance',
    'variant_key' => null,
]);
ac_assert('Null variant_key resolves to default general', $res2_null['success'] === true && $res2_null['data']['used_default_variant'] === true);

// 3. Explicitly empty variant is invalid (does not fallback to default)
$res3_empty = $use_case->execute([
    'family_key' => 'finance',
    'variant_key' => '',
]);
ac_assert('Explicitly empty variant_key fails', $res3_empty['success'] === false);
ac_assert('Explicitly empty variant_key error code is invalid_variant_key', ($res3_empty['error']['code'] ?? '') === 'invalid_variant_key');

// 4. Missing / non-scalar / invalid family_key
$res4_missing = $use_case->execute([]);
ac_assert('Missing family_key fails', $res4_missing['success'] === false);
ac_assert('Missing family_key code is missing_family', ($res4_missing['error']['code'] ?? '') === 'missing_family');

$res4_invalid = $use_case->execute(['family_key' => 'Invalid-Family']);
ac_assert('Invalid format family_key fails', $res4_invalid['success'] === false);
ac_assert('Invalid format family_key code is invalid_family_key', ($res4_invalid['error']['code'] ?? '') === 'invalid_family_key');

$res4_array = $use_case->execute(['family_key' => ['finance']]);
ac_assert('Array family_key fails', $res4_array['success'] === false && ($res4_array['error']['code'] ?? '') === 'invalid_family_key');

// 5. Unknown family
$res5_unknown_f = $use_case->execute(['family_key' => 'unknown_fam']);
ac_assert('Unknown family fails', $res5_unknown_f['success'] === false);
ac_assert('Unknown family code is unknown_family', ($res5_unknown_f['error']['code'] ?? '') === 'unknown_family');

// 6. Unknown variant for known family
$res6_unknown_v = $use_case->execute(['family_key' => 'finance', 'variant_key' => 'unknown_var']);
ac_assert('Unknown variant fails', $res6_unknown_v['success'] === false);
ac_assert('Unknown variant code is unknown_variant', ($res6_unknown_v['error']['code'] ?? '') === 'unknown_variant');

// 7. Unfrozen / unavailable registry
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
