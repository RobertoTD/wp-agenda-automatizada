<?php
/**
 * Staff AJAX — actualización atómica de personal (nombre + servicios).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/assignments/UpdateStaffUseCase.php';

final class StaffAjax {

    public const ACTION_UPDATE = 'aa_update_staff';
    public const NONCE_ACTION = 'aa_update_staff';

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
        $service_ids = [];

        if (isset($_POST['service_ids'])) {
            $raw = wp_unslash($_POST['service_ids']);
            if (!is_array($raw)) {
                $raw = [$raw];
            }

            foreach ($raw as $value) {
                $service_ids[] = absint($value);
            }
        }

        $result = (new UpdateStaffUseCase())->execute([
            'id' => $id,
            'name' => $name,
            'service_ids' => $service_ids,
        ]);

        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? []);
        }

        $error = is_array($result['error'] ?? null) ? $result['error'] : [];
        $code = (string) ($error['code'] ?? 'unknown_error');
        $message = (string) ($error['message'] ?? 'Error al actualizar el personal');
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
