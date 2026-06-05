<?php
/**
 * Executable Lists AJAX — feed común MC7 (transporte HTTP).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/executable/GetExecutableListsFeedUseCase.php';

final class ExecutableListsAjax {

    private const NONCE_ACTION = 'aa_executable_lists_nonce';

    public static function register(): void {
        add_action('wp_ajax_aa_get_executable_lists_feed', [__CLASS__, 'handle_get_feed']);
    }

    public static function handle_get_feed(): void {
        self::authorize();

        $result = (new GetExecutableListsFeedUseCase())->execute();

        if (empty($result['success'])) {
            $error = $result['error'] ?? [];
            wp_send_json_error([
                'message' => (string) ($error['message'] ?? 'No se pudo cargar el feed de listas.'),
                'code' => (string) ($error['code'] ?? 'feed_sources_unavailable'),
                'meta' => is_array($result['meta'] ?? null) ? $result['meta'] : [],
            ], 500);
        }

        wp_send_json_success([
            'lists' => is_array($result['lists'] ?? null) ? $result['lists'] : [],
            'meta' => is_array($result['meta'] ?? null) ? $result['meta'] : [],
        ]);
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }
}
