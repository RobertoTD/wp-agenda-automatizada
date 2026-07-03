<?php
/**
 * Onboarding Tutor State AJAX — lectura/actualización del estado durable UX del tutor.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/onboarding/GetOnboardingTutorStateUseCase.php';
require_once dirname(__DIR__, 2) . '/application/onboarding/UpdateOnboardingTutorStateUseCase.php';

final class OnboardingTutorStateAjax {

    public const NONCE_ACTION = 'aa_onboarding_tutor_state_nonce';

    /** @var list<string> */
    private const PATCH_KEYS = [
        'intro_seen_at',
        'completed_at',
        'dismissed_at',
        'last_durable_step_id',
    ];

    public static function register(): void {
        add_action('wp_ajax_aa_get_onboarding_tutor_state', [__CLASS__, 'handle_get']);
        add_action('wp_ajax_aa_update_onboarding_tutor_state', [__CLASS__, 'handle_update']);
    }

    public static function handle_get(): void {
        self::authorize();

        $result = (new GetOnboardingTutorStateUseCase())->execute();
        wp_send_json_success($result);
    }

    public static function handle_update(): void {
        self::authorize();

        $flow_id = isset($_POST['flow_id'])
            ? sanitize_key(wp_unslash((string) $_POST['flow_id']))
            : '';

        $patch = self::collect_patch_from_post();

        $result = (new UpdateOnboardingTutorStateUseCase())->execute([
            'flow_id' => $flow_id,
            'patch' => $patch,
        ]);

        self::respond_use_case($result);
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }

    /**
     * @return array<string,mixed>
     */
    private static function collect_patch_from_post(): array {
        $patch = [];

        foreach (self::PATCH_KEYS as $key) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }

            $raw = wp_unslash($_POST[$key]);

            if ($key === 'last_durable_step_id') {
                $patch[$key] = sanitize_key((string) $raw);
                continue;
            }

            if ($raw === null || $raw === '') {
                $patch[$key] = null;
                continue;
            }

            $patch[$key] = sanitize_text_field((string) $raw);
        }

        return $patch;
    }

    /**
     * @param array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}} $result
     */
    private static function respond_use_case(array $result): void {
        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? []);
        }

        $error = $result['error'] ?? [];
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo actualizar el estado del tutor.'),
            'code' => (string) ($error['code'] ?? 'unknown_error'),
        ], 400);
    }
}
