<?php
/**
 * Service AJAX — actualización atómica de servicio (modal editar).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/assignments/UpdateServiceUseCase.php';

final class ServiceAjax {

    public const ACTION_UPDATE = 'aa_update_service';
    public const NONCE_ACTION = 'aa_update_service';

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
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $price = isset($_POST['price']) ? trim((string) wp_unslash($_POST['price'])) : '';
        $public_calendar = isset($_POST['public_calendar']) ? (string) wp_unslash($_POST['public_calendar']) : '0';
        $indicaciones = isset($_POST['indicaciones_cita'])
            ? sanitize_textarea_field(wp_unslash($_POST['indicaciones_cita']))
            : '';
        $duration = isset($_POST['duration_minutes']) ? trim((string) wp_unslash($_POST['duration_minutes'])) : '';
        $attendance = isset($_POST['attendance_type'])
            ? sanitize_text_field(wp_unslash($_POST['attendance_type']))
            : '';
        $channel = isset($_POST['virtual_channel'])
            ? sanitize_text_field(wp_unslash($_POST['virtual_channel']))
            : '';

        $result = (new UpdateServiceUseCase())->execute([
            'id' => $id,
            'name' => $name,
            'code' => $code,
            'price' => $price,
            'public_calendar' => $public_calendar,
            'indicaciones_cita' => $indicaciones,
            'duration_minutes' => $duration,
            'attendance_type' => $attendance,
            'virtual_channel' => $channel,
        ]);

        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? []);
        }

        $error = is_array($result['error'] ?? null) ? $result['error'] : [];
        $code_key = (string) ($error['code'] ?? 'unknown_error');
        $message = (string) ($error['message'] ?? 'Error al actualizar el servicio');
        $status = 400;

        if ($code_key === 'not_found') {
            $status = 404;
        } elseif ($code_key === 'persistence_failed') {
            $status = 500;
        }

        wp_send_json_error([
            'message' => $message,
            'code' => $code_key,
        ], $status);
    }
}
