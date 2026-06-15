<?php
/**
 * Executive Proposal AJAX — Propuesta ejecutiva read-only (MC2) + acciones (MC3).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/executive/GetExecutiveProposalUseCase.php';
require_once dirname(__DIR__, 2) . '/application/executive/RecordExecutiveActionUseCase.php';
require_once dirname(__DIR__, 2) . '/application/executive/ChangeExecutiveFocusUseCase.php';

final class ExecutiveProposalAjax {

    private const NONCE_ACTION = 'aa_executive_proposal_nonce';

    public static function register(): void {
        add_action('wp_ajax_aa_get_executive_proposal', [__CLASS__, 'handle_get_proposal']);
        add_action('wp_ajax_aa_executive_action', [__CLASS__, 'handle_executive_action']);
        add_action('wp_ajax_aa_executive_focus_action', [__CLASS__, 'handle_focus_action']);
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

    public static function handle_executive_action(): void {
        self::authorize();

        try {
            $result = (new RecordExecutiveActionUseCase())->execute([
                'task_id' => self::post_scalar('task_id'),
                'action_key' => self::post_string('action_key'),
            ]);

            if (empty($result['success'])) {
                $error = is_array($result['error'] ?? null) ? $result['error'] : [];
                $code = (string) ($error['code'] ?? 'executive_action_unavailable');
                $status = self::resolve_error_status($code);

                wp_send_json_error([
                    'message' => (string) ($error['message'] ?? 'No se pudo ejecutar la acción ejecutiva.'),
                    'code' => $code,
                ], $status);
            }

            wp_send_json_success($result['data'] ?? []);
        } catch (\Throwable $exception) {
            wp_send_json_error([
                'message' => 'No se pudo ejecutar la acción ejecutiva.',
                'code' => 'executive_action_unavailable',
            ], 500);
        }
    }

    public static function handle_focus_action(): void {
        self::authorize();

        try {
            $result = (new ChangeExecutiveFocusUseCase())->execute([
                'focus_action' => self::post_string('focus_action'),
            ]);

            if (empty($result['success'])) {
                $error = is_array($result['error'] ?? null) ? $result['error'] : [];
                $code = (string) ($error['code'] ?? 'executive_focus_unavailable');
                $status = self::resolve_focus_error_status($code);

                wp_send_json_error([
                    'message' => (string) ($error['message'] ?? 'No se pudo ejecutar la acción de foco.'),
                    'code' => $code,
                ], $status);
            }

            wp_send_json_success($result['data'] ?? []);
        } catch (\Throwable $exception) {
            wp_send_json_error([
                'message' => 'No se pudo ejecutar la acción de foco.',
                'code' => 'executive_focus_unavailable',
            ], 500);
        }
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }

    private static function resolve_error_status(string $code): int {
        if (in_array($code, ['proposal_empty', 'task_not_current', 'action_not_allowed', 'invalid_request'], true)) {
            return 400;
        }

        if ($code === 'action_failed') {
            return 422;
        }

        return 500;
    }

    private static function resolve_focus_error_status(string $code): int {
        if (in_array($code, ['invalid_focus_action', 'previous_focus_unavailable', 'no_eligible_focus'], true)) {
            return 400;
        }

        return 500;
    }

    /**
     * @param string $key
     */
    private static function post_scalar($key): string {
        if (!isset($_POST[$key])) {
            return '';
        }

        return is_scalar($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    }

    /**
     * @param string $key
     */
    private static function post_string($key): string {
        if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
            return '';
        }

        return trim(wp_unslash($_POST[$key]));
    }
}
