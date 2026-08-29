<?php
/**
 * AC Test — AA_Variant_Definition.
 *
 * Ejecutar: php tests/domain/canonical/test-aa-variant-definition-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-variant-definition.php';

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

// 1. Valid construction and getters
$variant = new AA_Variant_Definition('finance', 'general', 'General');
ac_assert('Variant family_key getter', $variant->family_key() === 'finance');
ac_assert('Variant key getter', $variant->key() === 'general');
ac_assert('Variant label getter', $variant->label() === 'General');
ac_assert('Variant qualified_key derived representation', $variant->qualified_key() === 'finance.general');

// 2. Trimming label
$variant_trimmed = new AA_Variant_Definition('finance', 'general', '  General  ');
ac_assert('Variant label is trimmed', $variant_trimmed->label() === 'General');

// 3. Invalid family_key throws InvalidArgumentException with [invalid_key]
$threw_invalid_family = false;
$msg_invalid_family = '';
try {
    new AA_Variant_Definition('Invalid Family', 'general', 'General');
} catch (\InvalidArgumentException $e) {
    $threw_invalid_family = true;
    $msg_invalid_family = $e->getMessage();
}
ac_assert('Invalid family key throws InvalidArgumentException', $threw_invalid_family);
ac_assert('Invalid family key message has [invalid_key]', strpos($msg_invalid_family, '[invalid_key]') !== false);

// 4. Invalid variant_key throws InvalidArgumentException with [invalid_key]
$threw_invalid_key = false;
$msg_invalid_key = '';
try {
    new AA_Variant_Definition('finance', '1general', 'General');
} catch (\InvalidArgumentException $e) {
    $threw_invalid_key = true;
    $msg_invalid_key = $e->getMessage();
}
ac_assert('Invalid variant key throws InvalidArgumentException', $threw_invalid_key);
ac_assert('Invalid variant key message has [invalid_key]', strpos($msg_invalid_key, '[invalid_key]') !== false);

// 5. Empty label throws InvalidArgumentException with [invalid_label]
$threw_invalid_label = false;
$msg_invalid_label = '';
try {
    new AA_Variant_Definition('finance', 'general', '   ');
} catch (\InvalidArgumentException $e) {
    $threw_invalid_label = true;
    $msg_invalid_label = $e->getMessage();
}
ac_assert('Empty variant label throws InvalidArgumentException', $threw_invalid_label);
ac_assert('Empty variant label message has [invalid_label]', strpos($msg_invalid_label, '[invalid_label]') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
