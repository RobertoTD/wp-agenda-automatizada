<?php
/**
 * AC — Get/Update Onboarding Tutor State use cases.
 *
 * Ejecutar: php tests/application/onboarding/test-onboarding-tutor-state-use-cases-ac.php
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
require_once __DIR__ . '/../../../includes/repositories/OnboardingTutorStateRepository.php';
require_once __DIR__ . '/../../../includes/application/onboarding/GetOnboardingTutorStateUseCase.php';
require_once __DIR__ . '/../../../includes/application/onboarding/UpdateOnboardingTutorStateUseCase.php';

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

$get = new GetOnboardingTutorStateUseCase();
$empty = $get->execute();
ac_assert('get returns empty durable state', ($empty['version'] ?? null) === 1 && ($empty['flows'] ?? null) === []);

$update = new UpdateOnboardingTutorStateUseCase(static function () {
    return '2026-07-03 12:05:00';
});

$result = $update->execute([
    'flow_id' => AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT,
    'patch' => [
        'intro_seen_at' => '2026-07-03 12:00:00',
        'last_durable_step_id' => 'intro',
    ],
]);

ac_assert('update success', ($result['success'] ?? false) === true);
$flow = $result['data']['flows'][AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT] ?? [];
ac_assert('update sets intro_seen_at', ($flow['intro_seen_at'] ?? '') === '2026-07-03 12:00:00');
ac_assert('update sets updated_at', ($flow['updated_at'] ?? '') === '2026-07-03 12:05:00');

$rejected = $update->execute([
    'flow_id' => AA_Onboarding_Tutor_State_Policy::FLOW_TEST_APPOINTMENT,
    'patch' => [
        'activation_complete' => '1',
    ],
]);
ac_assert('update rejects arbitrary patch', ($rejected['success'] ?? true) === false);

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
