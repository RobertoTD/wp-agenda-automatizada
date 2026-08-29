<?php
/**
 * AC Test — AA_Canonical_Core_Bootstrap.
 *
 * Composición concreta de finance.general y shared instance de infraestructura.
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-core-bootstrap-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';

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

// 1. Reset for test isolation
AA_Canonical_Core_Bootstrap::reset_for_tests();

// 2. Obtain shared instance
$registry = AA_Canonical_Core_Bootstrap::instance();
ac_assert('instance() returns AA_Canonical_Registry', $registry instanceof AA_Canonical_Registry);
ac_assert('bootstrap() returns same registry instance', AA_Canonical_Core_Bootstrap::bootstrap() === $registry);
ac_assert('Repeated instance() calls return same instance', AA_Canonical_Core_Bootstrap::instance() === $registry);

// 3. Verify registry is frozen
ac_assert('Shared registry is frozen', $registry->is_frozen() === true);

// 4. Verify family "finance"
$family_finance = $registry->family('finance');
ac_assert('Family "finance" exists', $family_finance instanceof AA_Family_Definition);
ac_assert('Family "finance" key is "finance"', $family_finance ? $family_finance->key() === 'finance' : false);
ac_assert('Family "finance" label is "Finanzas"', $family_finance ? $family_finance->label() === 'Finanzas' : false);
ac_assert('Family "finance" default_variant_key is "general"', $family_finance ? $family_finance->default_variant_key() === 'general' : false);

// 5. Verify only registered family is "finance"
$families = $registry->families();
ac_assert('Only 1 family registered', count($families) === 1);
ac_assert('Single family registered is finance', $families[0]->key() === 'finance');

// 6. Verify variant "finance.general"
$variant_general = $registry->variant('finance', 'general');
ac_assert('Variant "general" for "finance" exists', $variant_general instanceof AA_Variant_Definition);
ac_assert('Variant family_key is "finance"', $variant_general ? $variant_general->family_key() === 'finance' : false);
ac_assert('Variant key is "general"', $variant_general ? $variant_general->key() === 'general' : false);
ac_assert('Variant label is "General"', $variant_general ? $variant_general->label() === 'General' : false);
ac_assert('Variant qualified_key is "finance.general"', $variant_general ? $variant_general->qualified_key() === 'finance.general' : false);

// 7. Verify only 1 variant in family "finance"
$finance_variants = $registry->variants_for('finance');
ac_assert('Only 1 variant registered for finance', count($finance_variants) === 1);
ac_assert('Variant in list is general', $finance_variants[0]->key() === 'general');

// 8. Test isolation with reset_for_tests
AA_Canonical_Core_Bootstrap::reset_for_tests();
$registry_rebuilt = AA_Canonical_Core_Bootstrap::instance();
ac_assert('New instance created after reset', $registry_rebuilt !== $registry);
ac_assert('New instance has same finance family', $registry_rebuilt->has_family('finance') === true);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
