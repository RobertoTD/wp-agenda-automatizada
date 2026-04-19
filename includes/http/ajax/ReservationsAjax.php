<?php
/**
 * Reservations AJAX — Adaptador HTTP para endpoints de reservas.
 *
 * Capa: includes/http/ajax/ (controllers AJAX).
 *
 * Responsabilidad ÚNICA: traducir entre el protocolo HTTP/AJAX de
 * WordPress y los Use Cases del dominio de reservas. Cada método
 * público es un endpoint. Solo parsea, valida transporte (nonce,
 * honeypot), delega y serializa.
 *
 * NO contiene:
 *  - Reglas de negocio (esas viven en `domain/`).
 *  - SQL (eso vive en `repositories/`).
 *  - Orquestación de varios pasos (eso vive en `application/` como
 *    Use Case y el handler lo invoca).
 *
 * Endpoints expuestos:
 *  - wp_ajax_aa_save_reservation        → save_reservation()
 *  - wp_ajax_nopriv_aa_save_reservation → save_reservation()
 *
 * Consumido por:
 *  - Formulario público (frontend) vía nopriv.
 *  - Modal admin "Reserva" vía priv.
 *  - Modal admin "Cita rápida" vía priv.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Http\Ajax
 */

defined('ABSPATH') or die('No direct access');

final class ReservationsAjax {

    /**
     * Engancha los handlers a los hooks de WordPress.
     *
     * Llamado desde el bootstrap (`wp-agenda-automatizada.php`).
     * Las action names (`aa_save_reservation`) se preservan EXACTAS:
     * cualquier cambio rompería el JS del frontend que envía
     * `action=aa_save_reservation` en sus peticiones AJAX.
     */
    public static function register(): void {
        add_action('wp_ajax_nopriv_aa_save_reservation', [__CLASS__, 'save_reservation']);
        add_action('wp_ajax_aa_save_reservation',        [__CLASS__, 'save_reservation']);
    }

    /**
     * Handler del endpoint AJAX de creación de reserva.
     *
     * Flujo:
     *  1. Lee y decodifica el body JSON.
     *  2. Valida nonce y honeypot (preocupaciones de transporte HTTP).
     *  3. Construye el input limpio para el Use Case (sin nonce/extra_field).
     *  4. Delega a `CreateReservationUseCase::execute()`.
     *  5. Serializa el resultado como `wp_send_json_success` / `wp_send_json_error`.
     *
     * Cualquier mensaje de error y cualquier clave de la respuesta JSON
     * deben mantenerse idénticos al wrapper original
     * (`aa_save_reservation()` en wp-agenda-automatizada.php) para no
     * romper el frontend ni los modales del admin.
     */
    public static function save_reservation(): void {
        // Leer cuerpo JSON enviado desde JS
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            wp_send_json_error(['message' => 'Datos inválidos.']);
        }

        // ✅ Validar nonce de seguridad
        if (empty($data['nonce']) || !wp_verify_nonce($data['nonce'], 'aa_reservation_nonce')) {
            wp_send_json_error(['message' => 'Error de validación de seguridad (nonce inválido).']);
        }

        // ✅ Validar honeypot (campo invisible anti-bot)
        if (!empty($data['extra_field'])) {
            wp_send_json_error(['message' => 'Detección de bot: envío no permitido.']);
        }

        // Construir input del Use Case (sin nonce/extra_field)
        $input = $data;
        unset($input['nonce'], $input['extra_field']);

        // Cargar y ejecutar Use Case
        require_once dirname(__FILE__, 4) . '/includes/application/booking/CreateReservationUseCase.php';
        $useCase = new CreateReservationUseCase();
        $result  = $useCase->execute($input);

        if (!empty($result['success'])) {
            wp_send_json_success($result['data']);
        }

        $error_payload = ['message' => $result['error']['message']];
        if (!empty($result['error']['detail'])) {
            $error_payload['error'] = $result['error']['detail'];
        }
        wp_send_json_error($error_payload);
    }
}
