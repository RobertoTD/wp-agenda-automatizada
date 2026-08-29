<?php
/**
 * AC Test — AA_Canonical_Key.
 *
 * Ejecutar: php tests/domain/canonical/test-aa-canonical-key-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';

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

// 1. Valid keys
ac_assert('Valid simple key "finance"', AA_Canonical_Key::is_valid('finance') === true);
ac_assert('Valid simple key "general"', AA_Canonical_Key::is_valid('general') === true);
ac_assert('Valid key with underscore "finance_core"', AA_Canonical_Key::is_valid('finance_core') === true);
ac_assert('Valid key with digits "v1_item"', AA_Canonical_Key::is_valid('v1_item') === true);
ac_assert('Valid single letter key "a"', AA_Canonical_Key::is_valid('a') === true);

// 2. Invalid keys
ac_assert('Empty key is invalid', AA_Canonical_Key::is_valid('') === false);
ac_assert('Key with uppercase is invalid', AA_Canonical_Key::is_valid('Finance') === false);
ac_assert('Key starting with number is invalid', AA_Canonical_Key::is_valid('1finance') === false);
ac_assert('Key starting with underscore is invalid', AA_Canonical_Key::is_valid('_finance') === false);
ac_assert('Key with dot is invalid', AA_Canonical_Key::is_valid('finance.general') === false);
ac_assert('Key with hyphen is invalid', AA_Canonical_Key::is_valid('finance-general') === false);
ac_assert('Key with spaces is invalid', AA_Canonical_Key::is_valid('finance general') === false);
ac_assert('Key with exactly 64 chars is valid', AA_Canonical_Key::is_valid(str_repeat('a', 64)) === true);
ac_assert('Key exceeding 64 chars (65 chars) is invalid', AA_Canonical_Key::is_valid(str_repeat('a', 65)) === false);

// 3. assert_valid() returns key on success
ac_assert('assert_valid returns key on valid input', AA_Canonical_Key::assert_valid('finance') === 'finance');

// 4. assert_valid() throws InvalidArgumentException with [invalid_key] tag
$exception_thrown = false;
$exception_message = '';
try {
    AA_Canonical_Key::assert_valid('Invalid-Key', 'family_key');
} catch (\InvalidArgumentException $e) {
    $exception_thrown = true;
    $exception_message = $e->getMessage();
} catch (\Throwable $e) {
    $exception_thrown = false;
}
ac_assert('assert_valid throws InvalidArgumentException on invalid input', $exception_thrown);
ac_assert('assert_valid error message contains [invalid_key]', strpos($exception_message, '[invalid_key]') !== false);
ac_assert('assert_valid error message includes context and value', strpos($exception_message, 'family_key') !== false && strpos($exception_message, 'Invalid-Key') !== false);

// 5. qualified() returns "family.variant"
ac_assert('qualified formats correctly', AA_Canonical_Key::qualified('finance', 'general') === 'finance.general');

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
