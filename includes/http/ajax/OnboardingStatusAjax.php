<?php
/**
 * Onboarding Status AJAX — admin endpoint for initial activation guide state.
 *
 * Translates wp_ajax_aa_get_onboarding_status → GetOnboardingStatusUseCase.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/onboarding/GetOnboardingStatusUseCase.php';

final class OnboardingStatusAjax {

    public static function register(): void {
        add_action('wp_ajax_aa_get_onboarding_status', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer('aa_get_onboarding_status_nonce', '_wpnonce');

        $result = (new GetOnboardingStatusUseCase())->execute();
        wp_send_json_success($result);
    }
}
