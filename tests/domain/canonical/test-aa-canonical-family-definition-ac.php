<?php
/**
 * AC Test — AA_Canonical_Family_Definition.
 *
 * Ejecutar: php tests/domain/canonical/test-aa-canonical-family-definition-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';

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

$family = new AA_Canonical_Family_Definition('finance', 'Finanzas');
ac_assert('Family key getter', $family->key() === 'finance');
ac_assert('Family label getter', $family->label() === 'Finanzas');

$family_trimmed = new AA_Canonical_Family_Definition('finance', '  Finanzas  ');
ac_assert('Family label is trimmed', $family_trimmed->label() === 'Finanzas');

$threw_invalid_key = false;
$msg_invalid_key = '';
try {
    new AA_Canonical_Family_Definition('Invalid Key', 'Finanzas');
} catch (\InvalidArgumentException $e) {
    $threw_invalid_key = true;
    $msg_invalid_key = $e->getMessage();
}
ac_assert('Invalid family key throws InvalidArgumentException', $threw_invalid_key);
ac_assert('Invalid family key message has [invalid_key]', strpos($msg_invalid_key, '[invalid_key]') !== false);

$threw_invalid_label = false;
$msg_invalid_label = '';
try {
    new AA_Canonical_Family_Definition('finance', '   ');
} catch (\InvalidArgumentException $e) {
    $threw_invalid_label = true;
    $msg_invalid_label = $e->getMessage();
}
ac_assert('Empty family label throws InvalidArgumentException', $threw_invalid_label);
ac_assert('Empty family label message has [invalid_label]', strpos($msg_invalid_label, '[invalid_label]') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
