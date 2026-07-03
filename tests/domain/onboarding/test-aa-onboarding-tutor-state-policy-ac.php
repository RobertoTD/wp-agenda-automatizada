<?php
/**
 * AC — AA_Onboarding_Tutor_State_Policy.
 *
 * Ejecutar: php tests/domain/onboarding/test-aa-onboarding-tutor-state-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

require_once __DIR__ . '/../../../includes/domain/onboarding/class-aa-onboarding-tutor-state-policy.php';

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

$empty = AA_Onboarding_Tutor_State_Policy::empty_state();
ac_assert('empty state version', ($empty['version'] ?? null) === 1);
ac_assert('empty state flows', ($empty['flows'] ?? null) === []);

$patched = AA_Onboarding_Tutor_State_Policy::apply_flow_patch(
    $empty,
    AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT,
    [
        'intro_seen_at' => '2026-07-03 12:00:00',
        'last_durable_step_id' => 'intro',
    ]
);
ac_assert('apply valid patch ok', ($patched['ok'] ?? false) === true);
$flow = $patched['state']['flows'][AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT] ?? [];
ac_assert('patch intro_seen_at', ($flow['intro_seen_at'] ?? '') === '2026-07-03 12:00:00');
ac_assert('patch last_durable_step_id', ($flow['last_durable_step_id'] ?? '') === 'intro');

$invalid_flow = AA_Onboarding_Tutor_State_Policy::apply_flow_patch($empty, 'unknown_flow', [
    'intro_seen_at' => '2026-07-03 12:00:00',
]);
ac_assert('reject unknown flow', ($invalid_flow['ok'] ?? true) === false);

$invalid_key = AA_Onboarding_Tutor_State_Policy::apply_flow_patch($empty, AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT, [
    'activation_complete' => true,
]);
ac_assert('reject business duplicate field', ($invalid_key['ok'] ?? true) === false);

$invalid_step = AA_Onboarding_Tutor_State_Policy::apply_flow_patch($empty, AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT, [
    'last_durable_step_id' => 'not_a_real_step',
]);
ac_assert('reject invalid durable step', ($invalid_step['ok'] ?? true) === false);

$corrupt = AA_Onboarding_Tutor_State_Policy::sanitize([
    'version' => 1,
    'flows' => [
        AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT => [
            'intro_seen_at' => 'not-a-date',
        ],
    ],
]);
ac_assert('sanitize drops corrupt flow', !isset($corrupt['flows'][AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT]));

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
