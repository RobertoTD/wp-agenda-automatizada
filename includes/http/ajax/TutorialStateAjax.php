<?php
/**
 * Tutorial State AJAX — lectura/transición del estado durable de tutoriales.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/tutorials/GetTutorialStateUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tutorials/ReconcileTutorialStateUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tutorials/TransitionTutorialStateUseCase.php';

final class TutorialStateAjax {

    public const NONCE_ACTION = 'aa_tutorial_state_nonce';

    public static function register(): void {
        add_action('wp_ajax_aa_get_tutorial_state', [__CLASS__, 'handle_get']);
        add_action('wp_ajax_aa_update_tutorial_state', [__CLASS__, 'handle_update']);
        add_action('wp_ajax_aa_reconcile_tutorial_state', [__CLASS__, 'handle_reconcile']);
    }

    public static function handle_get(): void {
        self::authorize();

        $result = (new GetTutorialStateUseCase())->execute();
        wp_send_json_success(self::state_for_json($result));
    }

    public static function handle_update(): void {
        self::authorize();

        $tutorial_id = isset($_POST['tutorial_id'])
            ? sanitize_key(wp_unslash((string) $_POST['tutorial_id']))
            : '';

        $status = isset($_POST['status'])
            ? sanitize_key(wp_unslash((string) $_POST['status']))
            : '';

        $input = [
            'tutorial_id' => $tutorial_id,
            'status' => $status,
        ];

        if (array_key_exists('current_step_id', $_POST)) {
            $raw_step = wp_unslash($_POST['current_step_id']);

            if ($raw_step === null || $raw_step === '') {
                $input['current_step_id'] = null;
            } else {
                $input['current_step_id'] = sanitize_key((string) $raw_step);
            }
        }

        $result = (new TransitionTutorialStateUseCase())->execute($input);

        self::respond_use_case($result);
    }

    public static function handle_reconcile(): void {
        self::authorize();

        $result = (new ReconcileTutorialStateUseCase())->execute();

        if (!empty($result['success'])) {
            $data = is_array($result['data'] ?? null) ? $result['data'] : [];
            $payload = self::state_for_json([
                'version' => (int) ($data['version'] ?? 1),
                'tutorials' => is_array($data['tutorials'] ?? null) ? $data['tutorials'] : [],
            ]);
            $payload['reconciled'] = !empty($data['reconciled']);

            wp_send_json_success($payload);
        }

        self::respond_reconcile_error($result);
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function state_for_json(array $state): array {
        if (!isset($state['tutorials']) || $state['tutorials'] === []) {
            $state['tutorials'] = new \stdClass();
        }

        return $state;
    }

    private static function respond_use_case(array $result): void {
        if (!empty($result['success'])) {
            wp_send_json_success(self::state_for_json($result['data'] ?? []));
        }

        $error = $result['error'] ?? [];
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo actualizar el estado del tutorial.'),
            'code' => (string) ($error['code'] ?? 'unknown_error'),
        ], 400);
    }

    private static function respond_reconcile_error(array $result): void {
        $error = $result['error'] ?? [];
        $code = (string) ($error['code'] ?? 'unknown_error');
        $status = $code === 'reservation_existence_check_failed' ? 503 : 500;

        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo reconciliar el estado del tutorial.'),
            'code' => $code,
        ], $status);
    }
}
