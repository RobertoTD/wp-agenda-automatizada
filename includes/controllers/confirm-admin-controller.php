<?php
/**
 * Controlador AJAX: Confirmación de Citas
 * 
 * Responsable de:
 * - Validar peticiones AJAX
 * - Validar nonces
 * - Sanitizar parámetros
 * - Delegar a confirm-backend-service.php
 * - Retornar respuestas JSON
 * 
 * NO contiene lógica de negocio, ni actualizaciones de BD, ni envío de correos.
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Controllers
 */

if (!defined('ABSPATH')) exit;

// 🔹 Incluir servicio de backend
require_once plugin_dir_path(__FILE__) . '../services/confirm-backend-service.php';

// ===============================
// 🔹 AJAX: Confirmar cita
// ===============================
add_action('wp_ajax_aa_confirmar_cita', 'aa_ajax_confirmar_cita');

function aa_ajax_confirmar_cita() {
    // ✅ USAR EL MISMO NONCE QUE LA FUNCIÓN ANTIGUA (más simple)
    check_ajax_referer('aa_confirmar_cita', '_wpnonce');
    
    // ✅ Verificar permisos
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    // ✅ Validar y sanitizar parámetros
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        wp_send_json_error(['message' => 'ID de reserva no proporcionado.']);
    }
    
    $reserva_id = intval($_POST['id']);
    
    if ($reserva_id <= 0) {
        wp_send_json_error(['message' => 'ID inválido.']);
    }
    
    // ✅ Delegar al servicio de backend
    $result = confirm_backend_service_confirmar($reserva_id);
    
    // ✅ Retornar respuesta
    if (isset($result['success']) && $result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

