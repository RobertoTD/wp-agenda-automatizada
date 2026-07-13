<?php
/**
 * AC — AA_Push_Activation_Visibility_Policy.
 *
 * Ejecutar: php tests/domain/push/test-aa-push-activation-visibility-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/push/class-aa-push-activation-visibility-policy.php';

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

$valid_device_key = 'a1b2c3d4e5f6789012345678abcdef01';
$valid_occurrence_id = 'fedcba9876543210';
$valid_origin_key = 'enable_push:' . $valid_device_key . ':' . $valid_occurrence_id;

ac_assert(
    'Valid enable_push origin key is recognized',
    AA_Push_Activation_Visibility_Policy::is_valid_enable_push_origin_key($valid_origin_key)
);
ac_assert(
    'Malformed enable_push origin key is rejected',
    !AA_Push_Activation_Visibility_Policy::is_valid_enable_push_origin_key('enable_push:bad:bad')
);
ac_assert(
    'Similar prefix without valid hex segments is rejected',
    !AA_Push_Activation_Visibility_Policy::is_valid_enable_push_origin_key(
        'enable_push:' . $valid_device_key . ':ZZZZZZZZZZZZZZZZ'
    )
);
ac_assert(
    'Other origin keys are not classified as push',
    !AA_Push_Activation_Visibility_Policy::is_valid_enable_push_origin_key('pwa.install')
);
ac_assert(
    'should_hide_when_agenda_unlinked only for valid push origin',
    AA_Push_Activation_Visibility_Policy::should_hide_when_agenda_unlinked($valid_origin_key)
    && !AA_Push_Activation_Visibility_Policy::should_hide_when_agenda_unlinked('enable_push:bad:bad')
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
