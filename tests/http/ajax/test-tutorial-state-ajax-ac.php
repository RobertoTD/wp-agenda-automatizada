<?php
/**
 * AC — TutorialStateAjax.
 *
 * Ejecutar: php tests/http/ajax/test-tutorial-state-ajax-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/TutorialStateAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$layout_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/layout.php');
$repo_src = file_get_contents($plugin_root . '/includes/repositories/TutorialStateRepository.php');

ac_assert('AJAX registers get endpoint', strpos($ajax_src, 'aa_get_tutorial_state') !== false);
ac_assert('AJAX registers update endpoint', strpos($ajax_src, 'aa_update_tutorial_state') !== false);
ac_assert('AJAX registers reconcile endpoint', strpos($ajax_src, 'aa_reconcile_tutorial_state') !== false);
ac_assert('AJAX uses GetTutorialStateUseCase', strpos($ajax_src, 'GetTutorialStateUseCase') !== false);
ac_assert('AJAX uses ReconcileTutorialStateUseCase', strpos($ajax_src, 'ReconcileTutorialStateUseCase') !== false);
ac_assert('AJAX uses TransitionTutorialStateUseCase', strpos($ajax_src, 'TransitionTutorialStateUseCase') !== false);
ac_assert('AJAX uses dedicated nonce', strpos($ajax_src, 'aa_tutorial_state_nonce') !== false);
ac_assert('AJAX checks manage_options capability', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('AJAX uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('AJAX accepts tutorial_id/status/current_step_id', strpos($ajax_src, 'tutorial_id') !== false && strpos($ajax_src, 'current_step_id') !== false);
ac_assert('Plugin bootstrap registers TutorialStateAjax', strpos($bootstrap_src, 'TutorialStateAjax::register()') !== false);
ac_assert('Layout exposes AA_TUTORIAL_DATA', strpos($layout_src, 'window.AA_TUTORIAL_DATA') !== false);
ac_assert('Layout exposes reconcileAction', strpos($layout_src, "reconcileAction: 'aa_reconcile_tutorial_state'") !== false);
ac_assert('Repository uses get_option path', strpos($repo_src, 'get_option') !== false);
ac_assert('Repository does not use get_site_option', strpos($repo_src, 'get_site_option') === false);
ac_assert('Old onboarding tutor AJAX not registered', strpos($bootstrap_src, 'OnboardingTutorStateAjax::register()') === false);
ac_assert('Old AA_ONBOARDING_TUTOR_DATA not exposed', strpos($layout_src, 'window.AA_ONBOARDING_TUTOR_DATA') === false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

require_once $plugin_root . '/includes/http/ajax/TutorialStateAjax.php';

ac_assert('TutorialStateAjax::register is callable', method_exists('TutorialStateAjax', 'register'));
ac_assert('TutorialStateAjax::handle_get is callable', method_exists('TutorialStateAjax', 'handle_get'));
ac_assert('TutorialStateAjax::handle_update is callable', method_exists('TutorialStateAjax', 'handle_update'));
ac_assert('TutorialStateAjax::handle_reconcile is callable', method_exists('TutorialStateAjax', 'handle_reconcile'));
$handle_get_body = substr(
    $ajax_src,
    (int) strpos($ajax_src, 'function handle_get'),
    (int) strpos($ajax_src, 'function handle_update') - (int) strpos($ajax_src, 'function handle_get')
);
$handle_reconcile_body = substr(
    $ajax_src,
    (int) strpos($ajax_src, 'function handle_reconcile'),
    (int) strpos($ajax_src, 'private static function authorize') - (int) strpos($ajax_src, 'function handle_reconcile')
);
ac_assert('GET handler does not use ReconcileTutorialStateUseCase', strpos($handle_get_body, 'ReconcileTutorialStateUseCase') === false);
ac_assert('reconcile handler does not read tutorial_id from request', strpos($handle_reconcile_body, "\$_POST['tutorial_id']") === false);
ac_assert('reconcile probe error uses 503', strpos($ajax_src, 'reservation_existence_check_failed') !== false && strpos($ajax_src, '503') !== false);
ac_assert('reconcile non-probe error uses 500', strpos($ajax_src, 'respond_reconcile_error') !== false && strpos($ajax_src, ': 500') !== false);

$reflection = new ReflectionClass('TutorialStateAjax');
$stateForJson = $reflection->getMethod('state_for_json');
$stateForJson->setAccessible(true);

$emptyState = $stateForJson->invoke(null, [
    'version' => 1,
    'tutorials' => [],
]);
$emptyJson = json_encode($emptyState);
ac_assert(
    'state_for_json serializa tutorials vacio como objeto JSON',
    $emptyJson !== false && strpos($emptyJson, '"tutorials":{}') !== false,
    $emptyJson === false ? 'json_encode failed' : $emptyJson
);

$populatedState = $stateForJson->invoke(null, [
    'version' => 1,
    'tutorials' => [
        'create_test_appointment_v1' => [
            'status' => 'in_progress',
            'currentStepId' => 'open_sidebar',
        ],
    ],
]);
$populatedJson = json_encode($populatedState);
ac_assert(
    'state_for_json conserva tutorials poblado como objeto',
    $populatedJson !== false && strpos($populatedJson, '"create_test_appointment_v1"') !== false,
    $populatedJson === false ? 'json_encode failed' : $populatedJson
);
ac_assert('Layout loads tutorialDefinitions.js', strpos($layout_src, 'tutorialDefinitions.js') !== false);
ac_assert('Layout loads tutorialCoordinator.js', strpos($layout_src, 'tutorialCoordinator.js') !== false);
ac_assert(
    'tutorialDefinitions loads after tutorialStateService',
    strpos($layout_src, 'tutorialStateService.js') < strpos($layout_src, 'tutorialDefinitions.js')
);
ac_assert(
    'tutorialCoordinator loads after tutorialDefinitions',
    strpos($layout_src, 'tutorialDefinitions.js') < strpos($layout_src, 'tutorialCoordinator.js')
);
ac_assert('Layout still loads onboardingWelcome.js', strpos($layout_src, 'onboardingWelcome.js') !== false);
ac_assert(
    'Layout still loads onboardingActivationCoordinator.js',
    strpos($layout_src, 'onboardingActivationCoordinator.js') !== false
);

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

require_once $plugin_root . '/includes/domain/tutorials/class-aa-tutorial-state-policy.php';
require_once $plugin_root . '/includes/repositories/TutorialStateRepository.php';
require_once $plugin_root . '/includes/application/tutorials/TransitionTutorialStateUseCase.php';

/** @var array<int,mixed> */
$http_storage = [];

TutorialStateRepository::set_storage_override_for_tests(
    static function (string $operation, int $blog_id, $payload = null) use (&$http_storage) {
        if ($operation === 'read') {
            return $http_storage[$blog_id] ?? false;
        }

        if ($operation === 'write') {
            $http_storage[$blog_id] = $payload;

            return true;
        }

        return false;
    }
);

$http_transition = new TransitionTutorialStateUseCase(static function () {
    return '2026-07-04 10:30:00';
});

$http_skip = $http_transition->execute([
    'tutorial_id' => 'create_test_appointment_v1',
    'status' => 'skipped',
    'current_step_id' => null,
]);
ac_assert('POST flow available -> skipped success', ($http_skip['success'] ?? false) === true);
$http_skipped = $http_skip['data']['tutorials']['create_test_appointment_v1'] ?? [];
ac_assert('POST flow serializes skipped_at', ($http_skipped['skipped_at'] ?? '') === '2026-07-04 10:30:00');

$http_storage[1] = [
    'version' => 1,
    'tutorials' => [
        'create_test_appointment_v1' => [
            'status' => 'in_progress',
            'current_step_id' => 'calendar_overview',
            'accepted_at' => '2026-07-01 10:00:00',
            'started_at' => '2026-07-01 10:00:00',
            'paused_at' => null,
            'completed_at' => null,
            'updated_at' => '2026-07-01 10:00:00',
        ],
    ],
];
$http_reject = $http_transition->execute([
    'tutorial_id' => 'create_test_appointment_v1',
    'status' => 'skipped',
    'current_step_id' => null,
]);
ac_assert('POST flow rejects in_progress -> skipped', ($http_reject['success'] ?? true) === false);

$skippedJsonState = $stateForJson->invoke(null, [
    'version' => 1,
    'tutorials' => [
        'create_test_appointment_v1' => [
            'status' => 'skipped',
            'current_step_id' => null,
            'accepted_at' => null,
            'started_at' => null,
            'paused_at' => null,
            'skipped_at' => '2026-07-04 10:30:00',
            'completed_at' => null,
            'updated_at' => '2026-07-04 10:30:00',
        ],
    ],
]);
$skippedJson = json_encode($skippedJsonState);
ac_assert(
    'state_for_json serializes skipped_at',
    $skippedJson !== false && strpos($skippedJson, '"skipped_at":"2026-07-04 10:30:00"') !== false,
    $skippedJson === false ? 'json_encode failed' : $skippedJson
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
