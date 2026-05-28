<?php
/**
 * Billing Portal AJAX — admin endpoint for Stripe Billing Portal session URL.
 *
 * Translates wp_ajax_aa_create_billing_portal_session → CreateBillingPortalSessionUseCase.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/account/CreateBillingPortalSessionUseCase.php';

final class BillingPortalAjax {

    public static function register(): void {
        add_action('wp_ajax_aa_create_billing_portal_session', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer('aa_create_billing_portal_session_nonce', '_wpnonce');

        $use_case = new CreateBillingPortalSessionUseCase();
        $result   = $use_case->execute();

        if (!empty($result['success'])) {
            wp_send_json_success($result['data']);
        }

        $error = $result['error'] ?? [];
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No pudimos abrir la gestión de pago en este momento.'),
            'code'    => (string) ($error['code'] ?? 'billing_backend_error'),
        ]);
    }
}
