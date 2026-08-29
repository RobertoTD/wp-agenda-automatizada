<?php
/**
 * AC Test — AA_Canonical_Registry.
 *
 * Invariantes, resolución y sellado del registro canónico de dominio.
 *
 * Ejecutar: php tests/domain/canonical/test-aa-canonical-registry-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';

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

// 1. Registry is instantiable and independent
$registry_a = new AA_Canonical_Registry();
$registry_b = new AA_Canonical_Registry();
ac_assert('Registry A is not frozen initially', $registry_a->is_frozen() === false);
ac_assert('Registry B is distinct instance', $registry_a !== $registry_b);

// 2. Querying / resolving before freeze() throws LogicException [registry_not_frozen]
$unfrozen_calls = [
    'has_family' => function () use ($registry_a) { $registry_a->has_family('finance'); },
    'family' => function () use ($registry_a) { $registry_a->family('finance'); },
    'has_variant' => function () use ($registry_a) { $registry_a->has_variant('finance', 'general'); },
    'variant' => function () use ($registry_a) { $registry_a->variant('finance', 'general'); },
    'variants_for' => function () use ($registry_a) { $registry_a->variants_for('finance'); },
    'families' => function () use ($registry_a) { $registry_a->families(); },
];

foreach ($unfrozen_calls as $op => $fn) {
    $threw = false;
    $msg = '';
    try {
        $fn();
    } catch (\LogicException $e) {
        $threw = true;
        $msg = $e->getMessage();
    }
    ac_assert("Operation {$op} before freeze throws LogicException", $threw);
    ac_assert("Operation {$op} before freeze message has [registry_not_frozen]", strpos($msg, '[registry_not_frozen]') !== false);
}

// 3. Register family and duplicate rejection
$family_finance = new AA_Canonical_Family_Definition('finance', 'Finanzas', 'general');
$registry_a->register_family($family_finance);

$threw_duplicate_family = false;
$msg_duplicate_family = '';
try {
    $registry_a->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas Duplicado', 'general'));
} catch (\InvalidArgumentException $e) {
    $threw_duplicate_family = true;
    $msg_duplicate_family = $e->getMessage();
}
ac_assert('Duplicate family throws InvalidArgumentException', $threw_duplicate_family);
ac_assert('Duplicate family error message has [duplicate_family]', strpos($msg_duplicate_family, '[duplicate_family]') !== false);

// 4. Register variant for unknown family throws InvalidArgumentException [unknown_family]
$threw_unknown_family = false;
$msg_unknown_family = '';
try {
    $registry_a->register_variant(new AA_Canonical_Variant_Definition('unknown_family', 'general', 'General'));
} catch (\InvalidArgumentException $e) {
    $threw_unknown_family = true;
    $msg_unknown_family = $e->getMessage();
}
ac_assert('Variant for unknown family throws InvalidArgumentException', $threw_unknown_family);
ac_assert('Unknown family error message has [unknown_family]', strpos($msg_unknown_family, '[unknown_family]') !== false);

// 5. Register variant successfully and duplicate variant rejection
$variant_general = new AA_Canonical_Variant_Definition('finance', 'general', 'General');
$registry_a->register_variant($variant_general);

$threw_duplicate_variant = false;
$msg_duplicate_variant = '';
try {
    $registry_a->register_variant(new AA_Canonical_Variant_Definition('finance', 'general', 'General Duplicado'));
} catch (\InvalidArgumentException $e) {
    $threw_duplicate_variant = true;
    $msg_duplicate_variant = $e->getMessage();
}
ac_assert('Duplicate variant throws InvalidArgumentException', $threw_duplicate_variant);
ac_assert('Duplicate variant error message has [duplicate_variant]', strpos($msg_duplicate_variant, '[duplicate_variant]') !== false);

// 6. Freeze validation: missing default variant throws LogicException [missing_default_variant]
$registry_bad = new AA_Canonical_Registry();
$registry_bad->register_family(new AA_Canonical_Family_Definition('custom', 'Custom Family', 'default_v'));
$registry_bad->register_variant(new AA_Canonical_Variant_Definition('custom', 'other_v', 'Other'));
$threw_missing_default = false;
$msg_missing_default = '';
try {
    $registry_bad->freeze();
} catch (\LogicException $e) {
    $threw_missing_default = true;
    $msg_missing_default = $e->getMessage();
}
ac_assert('Freeze with missing default variant throws LogicException', $threw_missing_default);
ac_assert('Missing default variant error has [missing_default_variant]', strpos($msg_missing_default, '[missing_default_variant]') !== false);

// 7. Freeze success and idempotency
$registry_a->freeze();
ac_assert('Registry is now frozen', $registry_a->is_frozen() === true);

// Second freeze is innocuous / idempotent
$registry_a->freeze();
ac_assert('Second freeze is idempotent and does not throw', $registry_a->is_frozen() === true);

// 8. Resolving on frozen registry
ac_assert('Registry has family "finance"', $registry_a->has_family('finance') === true);
ac_assert('Registry family "finance" returns definition', $registry_a->family('finance') === $family_finance);
ac_assert('Registry has not family "other"', $registry_a->has_family('other') === false);

// Unknown family resolution on frozen registry throws OutOfBoundsException [unknown_family]
$threw_unknown_family_res = false;
$msg_unknown_family_res = '';
try {
    $registry_a->family('unknown_family');
} catch (\OutOfBoundsException $e) {
    $threw_unknown_family_res = true;
    $msg_unknown_family_res = $e->getMessage();
}
ac_assert('Unknown family resolution throws OutOfBoundsException', $threw_unknown_family_res);
ac_assert('Unknown family resolution message has [unknown_family]', strpos($msg_unknown_family_res, '[unknown_family]') !== false);

// Variants resolution
ac_assert('Registry has variant "finance.general"', $registry_a->has_variant('finance', 'general') === true);
ac_assert('Registry variant returns definition', $registry_a->variant('finance', 'general') === $variant_general);
ac_assert('Registry has not unknown variant', $registry_a->has_variant('finance', 'advanced') === false);

// Unknown variant resolution on frozen registry throws OutOfBoundsException [unknown_variant]
$threw_unknown_variant_res = false;
$msg_unknown_variant_res = '';
try {
    $registry_a->variant('finance', 'unknown_variant');
} catch (\OutOfBoundsException $e) {
    $threw_unknown_variant_res = true;
    $msg_unknown_variant_res = $e->getMessage();
}
ac_assert('Unknown variant resolution throws OutOfBoundsException', $threw_unknown_variant_res);
ac_assert('Unknown variant resolution message has [unknown_variant]', strpos($msg_unknown_variant_res, '[unknown_variant]') !== false);

// variants_for resolution
ac_assert('Registry variants_for returns array with 1 item', count($registry_a->variants_for('finance')) === 1);
ac_assert('Registry variants_for returns correct variant', $registry_a->variants_for('finance')[0] === $variant_general);

// variants_for on unknown family throws OutOfBoundsException [unknown_family]
$threw_variants_for_unknown = false;
try {
    $registry_a->variants_for('unknown');
} catch (\OutOfBoundsException $e) {
    $threw_variants_for_unknown = true;
}
ac_assert('variants_for on unknown family throws OutOfBoundsException', $threw_variants_for_unknown);

// families() resolution
$families = $registry_a->families();
ac_assert('Registry families() returns array', is_array($families) && count($families) === 1);
ac_assert('Registry families() item 0 is finance', $families[0]->key() === 'finance');

// 9. Mutating frozen registry throws LogicException [registry_frozen]
$threw_frozen_family = false;
$msg_frozen_family = '';
try {
    $registry_a->register_family(new AA_Canonical_Family_Definition('legal', 'Legal', 'default'));
} catch (\LogicException $e) {
    $threw_frozen_family = true;
    $msg_frozen_family = $e->getMessage();
}
ac_assert('register_family on frozen registry throws LogicException', $threw_frozen_family);
ac_assert('Frozen family message has [registry_frozen]', strpos($msg_frozen_family, '[registry_frozen]') !== false);

$threw_frozen_variant = false;
$msg_frozen_variant = '';
try {
    $registry_a->register_variant(new AA_Canonical_Variant_Definition('finance', 'corporate', 'Corporativo'));
} catch (\LogicException $e) {
    $threw_frozen_variant = true;
    $msg_frozen_variant = $e->getMessage();
}
ac_assert('register_variant on frozen registry throws LogicException', $threw_frozen_variant);
ac_assert('Frozen variant message has [registry_frozen]', strpos($msg_frozen_variant, '[registry_frozen]') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
