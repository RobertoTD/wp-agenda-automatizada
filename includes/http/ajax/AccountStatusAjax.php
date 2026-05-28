<?php
/**
 * Account Status AJAX — admin endpoint for commercial account summary.
 *
 * Translates wp_ajax_aa_get_account_status → GetAccountStatusUseCase.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/account/GetAccountStatusUseCase.php';

final class AccountStatusAjax {

    public static function register(): void {
        add_action('wp_ajax_aa_get_account_status', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer('aa_get_account_status_nonce', '_wpnonce');

        $use_case = new GetAccountStatusUseCase();
        $result   = $use_case->execute();

        if (!empty($result['success'])) {
            wp_send_json_success($result['data']);
        }

        $error = $result['error'] ?? [];
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo consultar el estado de cuenta.'),
            'code'    => (string) ($error['code'] ?? 'account_backend_error'),
        ]);
    }
}
