<?php
/**
 * AC — OnboardingTutorStateRepository (site-scoped options).
 *
 * Ejecutar: php tests/repositories/test-onboarding-tutor-state-repository-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

require_once __DIR__ . '/../../includes/domain/onboarding/class-aa-onboarding-tutor-state-policy.php';
require_once __DIR__ . '/../../includes/repositories/OnboardingTutorStateRepository.php';

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

/** @var array<int,mixed> */
$storage = [];

OnboardingTutorStateRepository::set_storage_override_for_tests(
    static function (string $operation, int $blog_id, $payload = null) use (&$storage) {
        if ($operation === 'read') {
            return $storage[$blog_id] ?? false;
        }

        if ($operation === 'write') {
            $storage[$blog_id] = $payload;

            return true;
        }

        return false;
    }
);

$state = [
    'version' => 1,
    'flows' => [
        AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT => [
            'intro_seen_at' => '2026-07-03 12:00:00',
            'completed_at' => null,
            'dismissed_at' => null,
            'last_durable_step_id' => 'intro',
            'updated_at' => '2026-07-03 12:00:01',
        ],
    ],
];

ac_assert('save site 1', OnboardingTutorStateRepository::save($state));
$loaded_site_1 = OnboardingTutorStateRepository::find();
ac_assert(
    'roundtrip site 1 intro_seen_at',
    ($loaded_site_1['flows'][AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT]['intro_seen_at'] ?? '') === '2026-07-03 12:00:00'
);

$GLOBALS['aa_test_current_blog_id'] = 2;
if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        return (int) ($GLOBALS['aa_test_current_blog_id'] ?? 1);
    }
}

ac_assert('empty site 2 before save', OnboardingTutorStateRepository::find()['flows'] === []);

$site_2_state = [
    'version' => 1,
    'flows' => [
        AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT => [
            'intro_seen_at' => '2026-07-04 09:30:00',
            'completed_at' => null,
            'dismissed_at' => null,
            'last_durable_step_id' => 'create_button',
            'updated_at' => '2026-07-04 09:30:05',
        ],
    ],
];
ac_assert('save site 2', OnboardingTutorStateRepository::save($site_2_state));

$GLOBALS['aa_test_current_blog_id'] = 1;
$site_1_again = OnboardingTutorStateRepository::find();
ac_assert(
    'site 1 isolated from site 2',
    ($site_1_again['flows'][AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT]['last_durable_step_id'] ?? '') === 'intro'
);

$GLOBALS['aa_test_current_blog_id'] = 2;
$site_2_again = OnboardingTutorStateRepository::find();
ac_assert(
    'site 2 keeps its own state',
    ($site_2_again['flows'][AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT]['last_durable_step_id'] ?? '') === 'create_button'
);

ac_assert(
    'repository uses site option key constant',
    OnboardingTutorStateRepository::OPTION_KEY === 'aa_onboarding_tutor_state_v1'
);

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
