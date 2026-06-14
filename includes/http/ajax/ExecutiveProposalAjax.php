<?php
/**
 * Executive Proposal AJAX — Propuesta ejecutiva read-only (MC2).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/executive/GetExecutiveProposalUseCase.php';

final class ExecutiveProposalAjax {

    private const NONCE_ACTION = 'aa_executive_proposal_nonce';

    public static function register(): void {
        add_action('wp_ajax_aa_get_executive_proposal', [__CLASS__, 'handle_get_proposal']);
    }

    public static function handle_get_proposal(): void {
        self::authorize();

        try {
            $result = (new GetExecutiveProposalUseCase())->execute();
            wp_send_json_success($result);
        } catch (\Throwable $exception) {
            wp_send_json_error([
                'message' => 'No se pudo cargar la propuesta ejecutiva.',
                'code' => 'executive_proposal_unavailable',
            ], 500);
        }
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }
}
