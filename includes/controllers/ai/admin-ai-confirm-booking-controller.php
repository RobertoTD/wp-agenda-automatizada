<?php
/**
 * Admin AI Confirm Booking Controller
 *
 * Frontera HTTP/AJAX del bounded context AI para confirmar un draft.
 * Solo orquesta: capability, nonce, sanitización mínima del payload y
 * delegación a `AA_AI_Confirm_Booking_Use_Case`. NO contiene reglas
 * de negocio.
 *
 * Mismo patrón que `AA_Admin_AI_Chat_Controller` (admin-only, sin
 * `nopriv`, capability `manage_options`).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Controllers\AI
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/ai/AI_Confirm_Booking_Use_Case.php';
require_once dirname(__DIR__, 2) . '/application/booking/CreateReservationUseCase.php';
require_once dirname(__DIR__, 2) . '/services/confirm-backend-service.php';

final class AA_Admin_AI_Confirm_Booking_Controller {

    public static function register(): void {
        add_action('wp_ajax_aa_ai_confirm_booking', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer('aa_ai_confirm_booking_nonce', 'nonce');

        $input = self::extract_input();

        $result = (new AA_AI_Confirm_Booking_Use_Case())->execute($input);

        if (($result['status'] ?? null) === 'ok') {
            wp_send_json_success([
                'reservation_id'     => (int) ($result['reservation_id'] ?? 0),
                'assignment_id'      => (int) ($result['assignment_id'] ?? 0),
                'created_assignment' => (bool) ($result['created_assignment'] ?? false),
                'confirmed'          => (bool) ($result['confirmed'] ?? false),
            ]);
        }

        wp_send_json_error([
            'stage'   => (string) ($result['stage'] ?? 'unknown'),
            'message' => (string) ($result['message'] ?? 'Error desconocido'),
            'detail'  => $result['detail'] ?? null,
        ], 422);
    }

    /**
     * Sanitiza y normaliza el payload del request al shape exacto que
     * espera el use case. No valida invariantes (eso lo hace el use case
     * y devuelve `stage:'input'` si algo está mal).
     *
     * @return array<string,mixed>
     */
    private static function extract_input(): array {
        $start_dt_raw = isset($_POST['start_datetime'])
            ? sanitize_text_field(wp_unslash((string) $_POST['start_datetime']))
            : '';
        $mode_raw = isset($_POST['assignment_mode'])
            ? sanitize_text_field(wp_unslash((string) $_POST['assignment_mode']))
            : '';

        return [
            'client_id'        => isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0,
            'service_id'       => isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0,
            'staff_id'         => isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0,
            'zone_id'          => isset($_POST['zone_id']) ? (int) $_POST['zone_id'] : 0,
            'start_datetime'   => $start_dt_raw,
            'duration_minutes' => isset($_POST['duration_minutes']) ? (int) $_POST['duration_minutes'] : 0,
            'assignment_mode'  => $mode_raw,
            'assignment_id'    => isset($_POST['assignment_id']) ? (int) $_POST['assignment_id'] : 0,
        ];
    }
}
