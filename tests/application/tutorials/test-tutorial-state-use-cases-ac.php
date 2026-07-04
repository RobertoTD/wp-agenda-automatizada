<?php
/**
 * AC — Get/Transition Tutorial State use cases.
 *
 * Ejecutar: php tests/application/tutorials/test-tutorial-state-use-cases-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

require_once __DIR__ . '/../../../includes/domain/tutorials/class-aa-tutorial-state-policy.php';
require_once __DIR__ . '/../../../includes/repositories/TutorialStateRepository.php';
require_once __DIR__ . '/../../../includes/application/tutorials/GetTutorialStateUseCase.php';
require_once __DIR__ . '/../../../includes/application/tutorials/TransitionTutorialStateUseCase.php';

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

TutorialStateRepository::set_storage_override_for_tests(
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

$tutorial_id = AA_Tutorial_State_Policy::TUTORIAL_CREATE_TEST_APPOINTMENT;

$get = new GetTutorialStateUseCase();
$empty = $get->execute();
ac_assert('get returns empty durable state', ($empty['version'] ?? null) === 1 && ($empty['tutorials'] ?? null) === []);

$transition = new TransitionTutorialStateUseCase(static function () {
    return '2026-07-03 12:05:00';
});

$result = $transition->execute([
    'tutorial_id' => $tutorial_id,
    'status' => 'in_progress',
    'current_step_id' => 'open_sidebar',
]);

ac_assert('accept success', ($result['success'] ?? false) === true);
$tutorial = $result['data']['tutorials'][$tutorial_id] ?? [];
ac_assert('accept sets accepted_at server-side', ($tutorial['accepted_at'] ?? '') === '2026-07-03 12:05:00');
ac_assert('accept sets started_at server-side', ($tutorial['started_at'] ?? '') === '2026-07-03 12:05:00');
ac_assert('accept sets updated_at server-side', ($tutorial['updated_at'] ?? '') === '2026-07-03 12:05:00');

$rejected = $transition->execute([
    'tutorial_id' => $tutorial_id,
    'status' => 'in_progress',
    'current_step_id' => 'calendar_overview',
]);
ac_assert('reject non-linear transition', ($rejected['success'] ?? true) === false);

$advance = $transition->execute([
    'tutorial_id' => $tutorial_id,
    'status' => 'in_progress',
    'current_step_id' => 'open_calendar',
]);
ac_assert('advance success', ($advance['success'] ?? false) === true);
$advanced = $advance['data']['tutorials'][$tutorial_id] ?? [];
ac_assert('advance sets current_step_id', ($advanced['current_step_id'] ?? '') === 'open_calendar');

$pause = $transition->execute([
    'tutorial_id' => $tutorial_id,
    'status' => 'paused',
]);
ac_assert('pause success', ($pause['success'] ?? false) === true);
$paused = $pause['data']['tutorials'][$tutorial_id] ?? [];
ac_assert('pause retains current_step_id', ($paused['current_step_id'] ?? '') === 'open_calendar');
ac_assert('pause sets paused_at server-side', ($paused['paused_at'] ?? '') === '2026-07-03 12:05:00');

$unknown = $transition->execute([
    'tutorial_id' => 'unknown_tutorial',
    'status' => 'in_progress',
    'current_step_id' => 'open_sidebar',
]);
ac_assert('reject unknown tutorial', ($unknown['success'] ?? true) === false);

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
