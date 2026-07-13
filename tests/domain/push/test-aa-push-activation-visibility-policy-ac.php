<?php
/**
 * AC — AA_Push_Activation_Visibility_Policy (proyección enable_push).
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

ac_assert(
    'Recognizes global enable_push origin',
    AA_Push_Activation_Visibility_Policy::is_push_activation_task('enable_push')
);
ac_assert(
    'Rejects legacy device-scoped origin keys as global push task',
    !AA_Push_Activation_Visibility_Policy::is_push_activation_task('enable_push:a1b2c3d4e5f6789012345678abcdef01:fedcba9876543210')
);
ac_assert(
    'Recognizes legacy enable_push:* origins',
    AA_Push_Activation_Visibility_Policy::is_legacy_push_activation_task('enable_push:a1b2c3d4e5f6789012345678abcdef01:fedcba9876543210')
);
ac_assert(
    'Rejects other activation tasks',
    !AA_Push_Activation_Visibility_Policy::is_push_activation_task('pwa.install')
    && !AA_Push_Activation_Visibility_Policy::is_legacy_push_activation_task('enable_push_legacy')
);

ac_assert(
    'Inactive subscription hides global enable_push',
    AA_Push_Activation_Visibility_Policy::should_hide_for_context('enable_push', false, false)
);
ac_assert(
    'Inactive subscription hides even when push_ready true',
    AA_Push_Activation_Visibility_Policy::should_hide_for_context('enable_push', false, true)
);
ac_assert(
    'Active subscription and not push_ready shows global enable_push',
    !AA_Push_Activation_Visibility_Policy::should_hide_for_context('enable_push', true, false)
);
ac_assert(
    'Active subscription and push_ready hides global enable_push',
    AA_Push_Activation_Visibility_Policy::should_hide_for_context('enable_push', true, true)
);
ac_assert(
    'Legacy enable_push:* always hidden regardless of context',
    AA_Push_Activation_Visibility_Policy::should_hide_for_context(
        'enable_push:a1b2c3d4e5f6789012345678abcdef01:fedcba9876543210',
        true,
        false
    )
    && AA_Push_Activation_Visibility_Policy::should_hide_for_context(
        'enable_push:a1b2c3d4e5f6789012345678abcdef01:fedcba9876543210',
        false,
        false
    )
);
ac_assert(
    'Unrelated origins never hidden by push projection',
    !AA_Push_Activation_Visibility_Policy::should_hide_for_context('pwa.install', false, false)
    && !AA_Push_Activation_Visibility_Policy::should_hide_for_context('enable_push_legacy', true, false)
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
