<?php
/**
 * AC Test — AA_Canonical_Registry.
 *
 * Invariantes, resolución y sellado del registro canónico de dominio (familias).
 *
 * Ejecutar: php tests/domain/canonical/test-aa-canonical-registry-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
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

$registry_a = new AA_Canonical_Registry();
$registry_b = new AA_Canonical_Registry();
ac_assert('Registry A is not frozen initially', $registry_a->is_frozen() === false);
ac_assert('Registry B is distinct instance', $registry_a !== $registry_b);

$unfrozen_calls = [
    'has_family' => function () use ($registry_a) { $registry_a->has_family('finance'); },
    'family' => function () use ($registry_a) { $registry_a->family('finance'); },
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

$family_finance = new AA_Canonical_Family_Definition('finance', 'Finanzas');
$registry_a->register_family($family_finance);

$threw_duplicate_family = false;
$msg_duplicate_family = '';
try {
    $registry_a->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas Duplicado'));
} catch (\InvalidArgumentException $e) {
    $threw_duplicate_family = true;
    $msg_duplicate_family = $e->getMessage();
}
ac_assert('Duplicate family throws InvalidArgumentException', $threw_duplicate_family);
ac_assert('Duplicate family error message has [duplicate_family]', strpos($msg_duplicate_family, '[duplicate_family]') !== false);

$registry_a->freeze();
ac_assert('Registry is now frozen', $registry_a->is_frozen() === true);
$registry_a->freeze();
ac_assert('Second freeze is idempotent and does not throw', $registry_a->is_frozen() === true);

ac_assert('Registry has family "finance"', $registry_a->has_family('finance') === true);
ac_assert('Registry family "finance" returns definition', $registry_a->family('finance') === $family_finance);
ac_assert('Registry has not family "other"', $registry_a->has_family('other') === false);

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

$families = $registry_a->families();
ac_assert('Registry families() returns array', is_array($families) && count($families) === 1);
ac_assert('Registry families() item 0 is finance', $families[0]->key() === 'finance');

$threw_frozen_family = false;
$msg_frozen_family = '';
try {
    $registry_a->register_family(new AA_Canonical_Family_Definition('legal', 'Legal'));
} catch (\LogicException $e) {
    $threw_frozen_family = true;
    $msg_frozen_family = $e->getMessage();
}
ac_assert('register_family on frozen registry throws LogicException', $threw_frozen_family);
ac_assert('Frozen family message has [registry_frozen]', strpos($msg_frozen_family, '[registry_frozen]') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
