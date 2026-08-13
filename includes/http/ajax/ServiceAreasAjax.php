<?php
/**
 * Service Areas AJAX — actualización atómica de zona de atención (nombre + color).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/assignments/UpdateServiceAreaUseCase.php';

final class ServiceAreasAjax {

    public const ACTION_UPDATE = 'aa_update_service_area';
    public const NONCE_ACTION = 'aa_update_service_area';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_UPDATE, [__CLASS__, 'handle_update']);
    }

    public static function handle_update(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $color = isset($_POST['color']) ? sanitize_text_field(wp_unslash($_POST['color'])) : '';

        $result = (new UpdateServiceAreaUseCase())->execute([
            'id' => $id,
            'name' => $name,
            'color' => $color,
        ]);

        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? []);
        }

        $error = is_array($result['error'] ?? null) ? $result['error'] : [];
        $code = (string) ($error['code'] ?? 'unknown_error');
        $message = (string) ($error['message'] ?? 'Error al actualizar la zona de atención');
        $status = 400;

        if ($code === 'not_found') {
            $status = 404;
        } elseif ($code === 'persistence_failed') {
            $status = 500;
        }

        wp_send_json_error([
            'message' => $message,
            'code' => $code,
        ], $status);
    }
}
