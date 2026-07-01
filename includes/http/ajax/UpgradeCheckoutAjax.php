<?php
/**
 * Upgrade Checkout AJAX — admin endpoint for Stripe upgrade checkout URL.
 *
 * Translates wp_ajax_aa_create_upgrade_checkout_session → CreateUpgradeCheckoutSessionUseCase.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/account/CreateUpgradeCheckoutSessionUseCase.php';

final class UpgradeCheckoutAjax {

    public static function register(): void {
        add_action('wp_ajax_aa_create_upgrade_checkout_session', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer('aa_create_upgrade_checkout_session_nonce', '_wpnonce');

        $use_case = new CreateUpgradeCheckoutSessionUseCase();
        $result   = $use_case->execute();

        if (!empty($result['success'])) {
            wp_send_json_success($result['data']);
        }

        $error = $result['error'] ?? [];
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No pudimos abrir el checkout de Pro. Intenta de nuevo.'),
            'code'    => (string) ($error['code'] ?? 'upgrade_backend_error'),
        ]);
    }
}
