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
ac_assert('AJAX uses GetTutorialStateUseCase', strpos($ajax_src, 'GetTutorialStateUseCase') !== false);
ac_assert('AJAX uses TransitionTutorialStateUseCase', strpos($ajax_src, 'TransitionTutorialStateUseCase') !== false);
ac_assert('AJAX uses dedicated nonce', strpos($ajax_src, 'aa_tutorial_state_nonce') !== false);
ac_assert('AJAX checks manage_options capability', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('AJAX uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('AJAX accepts tutorial_id/status/current_step_id', strpos($ajax_src, 'tutorial_id') !== false && strpos($ajax_src, 'current_step_id') !== false);
ac_assert('Plugin bootstrap registers TutorialStateAjax', strpos($bootstrap_src, 'TutorialStateAjax::register()') !== false);
ac_assert('Layout exposes AA_TUTORIAL_DATA', strpos($layout_src, 'window.AA_TUTORIAL_DATA') !== false);
ac_assert('Repository uses get_option path', strpos($repo_src, 'get_option') !== false);
ac_assert('Repository does not use get_site_option', strpos($repo_src, 'get_site_option') === false);
ac_assert('Old onboarding tutor AJAX not registered', strpos($bootstrap_src, 'OnboardingTutorStateAjax::register()') === false);
ac_assert('Old AA_ONBOARDING_TUTOR_DATA not exposed', strpos($layout_src, 'window.AA_ONBOARDING_TUTOR_DATA') === false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/http/ajax/TutorialStateAjax.php';

ac_assert('TutorialStateAjax::register is callable', method_exists('TutorialStateAjax', 'register'));
ac_assert('TutorialStateAjax::handle_get is callable', method_exists('TutorialStateAjax', 'handle_get'));
ac_assert('TutorialStateAjax::handle_update is callable', method_exists('TutorialStateAjax', 'handle_update'));

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
