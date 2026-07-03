<?php
/**
 * AC — OnboardingTutorStateAjax.
 *
 * Ejecutar: php tests/http/ajax/test-onboarding-tutor-state-ajax-ac.php
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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/OnboardingTutorStateAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$layout_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/layout.php');
$repo_src = file_get_contents($plugin_root . '/includes/repositories/OnboardingTutorStateRepository.php');

ac_assert('AJAX registers get endpoint', strpos($ajax_src, 'aa_get_onboarding_tutor_state') !== false);
ac_assert('AJAX registers update endpoint', strpos($ajax_src, 'aa_update_onboarding_tutor_state') !== false);
ac_assert('AJAX uses GetOnboardingTutorStateUseCase', strpos($ajax_src, 'GetOnboardingTutorStateUseCase') !== false);
ac_assert('AJAX uses UpdateOnboardingTutorStateUseCase', strpos($ajax_src, 'UpdateOnboardingTutorStateUseCase') !== false);
ac_assert('AJAX uses dedicated nonce', strpos($ajax_src, 'aa_onboarding_tutor_state_nonce') !== false);
ac_assert('AJAX checks manage_options capability', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('AJAX uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('AJAX whitelists patch keys', strpos($ajax_src, 'last_durable_step_id') !== false);
ac_assert('Plugin bootstrap registers OnboardingTutorStateAjax', strpos($bootstrap_src, 'OnboardingTutorStateAjax::register()') !== false);
ac_assert('Layout exposes AA_ONBOARDING_TUTOR_DATA', strpos($layout_src, 'window.AA_ONBOARDING_TUTOR_DATA') !== false);
ac_assert('Repository uses get_option path', strpos($repo_src, 'get_option') !== false);
ac_assert('Repository does not use get_site_option', strpos($repo_src, 'get_site_option') === false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/http/ajax/OnboardingTutorStateAjax.php';

ac_assert('OnboardingTutorStateAjax::register is callable', method_exists('OnboardingTutorStateAjax', 'register'));
ac_assert('OnboardingTutorStateAjax::handle_get is callable', method_exists('OnboardingTutorStateAjax', 'handle_get'));
ac_assert('OnboardingTutorStateAjax::handle_update is callable', method_exists('OnboardingTutorStateAjax', 'handle_update'));

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
