<?php
/**
 * Controlador: Confirmación de Citas
 * 
 * Responsable de:
 * - Validar peticiones AJAX para confirmación desde admin
 * - Validar peticiones AJAX para envío de correos de confirmación
 * - Gestionar endpoint REST API para confirmación desde el backend
 * - Validar nonces y permisos
 * - Sanitizar parámetros
 * - Delegar a confirm-backend-service.php
 * - Retornar respuestas JSON
 * 
 * NO contiene lógica de negocio, ni actualizaciones de BD directas.
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Controllers
 */

if (!defined('ABSPATH')) exit;

// 🔹 Incluir servicios necesarios
require_once plugin_dir_path(__FILE__) . '../services/confirm-backend-service.php';
require_once plugin_dir_path(__FILE__) . '../models/ReservationsModel.php';

// ===============================
// 🔹 AJAX: Confirmar cita desde admin
// ===============================
add_action('wp_ajax_aa_confirmar_cita', 'aa_ajax_confirmar_cita');

function aa_ajax_confirmar_cita() {
    // ✅ Validar nonce
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

// ===============================
// 🔹 AJAX: Enviar correo de confirmación
// ===============================
add_action('wp_ajax_nopriv_aa_enviar_confirmacion', 'aa_ajax_enviar_confirmacion');
add_action('wp_ajax_aa_enviar_confirmacion', 'aa_ajax_enviar_confirmacion');

function aa_ajax_enviar_confirmacion() {
    error_log("🔥 AJAX aa_enviar_confirmacion activado");
    
    // 🔹 Decodificar JSON del body
    $raw_input = file_get_contents('php://input');
    $datos = json_decode($raw_input, true);

    if (!$datos) {
        wp_send_json_error(['message' => 'JSON inválido o vacío']);
        return;
    }

    // 🔹 Validar nonce (mismo que usa saveReservation / reservationController.js)
    if (empty($datos['nonce']) || !wp_verify_nonce($datos['nonce'], 'aa_reservation_nonce')) {
        wp_send_json_error(['message' => 'Error de validación de seguridad (nonce inválido).']);
        return;
    }

    // 🔹 Rate limit por IP (10 solicitudes / 300 s)
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $rl_key = 'aa_rl_enviar_confirmacion_' . md5($ip);
    $rl_count = (int) get_transient($rl_key);

    if ($rl_count >= 10) {
        wp_send_json_error(['message' => 'Demasiadas solicitudes. Intenta más tarde.']);
        return;
    }

    set_transient($rl_key, $rl_count + 1, 300);

    // 🔹 Validar campos mínimos
    if (empty($datos['id_reserva']) || empty($datos['correo']) || empty($datos['nombre'])) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
        return;
    }

    // 🔹 Origen: admin vs frontend (para que el backend sepa si enviar correo al negocio)
    $datos['source'] = (current_user_can('aa_view_panel') || current_user_can('administrator'))
        ? 'admin'
        : 'frontend';

    error_log("📤 JSON COMPLETO QUE SE ENVÍA AL BACKEND:");
    error_log(json_encode($datos, JSON_PRETTY_PRINT));

    // ✅ Delegar al servicio
    $result = confirm_backend_service_enviar_correo($datos);
    
    // ✅ Retornar respuesta
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

// ===============================
// 🔹 REST API: Confirmar reserva desde backend
// ===============================

/**
 * Valida que la petición lleve el header X-AA-Webhook-Token con el valor
 * almacenado en wp_options (aa_webhook_token). Fail-closed: si el option
 * está vacío (OAuth aún no completado) el endpoint queda bloqueado hasta
 * que se reconecte OAuth y se setee el token.
 *
 * Patrón idéntico a WebhooksController::handle_branding().
 */
function aa_rest_permission_webhook_token(WP_REST_Request $request) {
    $provided    = $request->get_header('X-AA-Webhook-Token');
    $stored      = get_option('aa_webhook_token', '');

    if (empty($stored) || empty($provided) || !hash_equals($stored, $provided)) {
        return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
    }

    return true;
}

add_action('rest_api_init', function () {
    register_rest_route('aa/v1', '/confirmar-reserva', [
        'methods' => 'POST',
        'callback' => 'aa_rest_confirmar_reserva',
        'permission_callback' => 'aa_rest_permission_webhook_token',
    ]);
});

function aa_rest_confirmar_reserva(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    $id = intval($request['id_reserva']);

    if (!$id) {
        return new WP_REST_Response(['error' => 'id_reserva faltante'], 400);
    }

    // 🔹 Obtener datos de la reserva antes de actualizar
    $reserva = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $id
    ));
    
    if (!$reserva) {
        return new WP_REST_Response(['error' => 'Reserva no encontrada'], 404);
    }

    // 🔹 Preparar datos a actualizar
    $update_data = ['estado' => 'confirmed'];
    $update_format = ['%s'];
    
    // 🔹 Si viene calendar_uid, también lo guardamos
    $calendar_uid = sanitize_text_field($request['calendar_uid']);
    if (!empty($calendar_uid)) {
        $update_data['calendar_uid'] = $calendar_uid;
        $update_format[] = '%s';
        error_log("✅ calendar_uid recibido para reserva ID $id: $calendar_uid");
    }

    // 🔹 Si viene virtual_link (Meet u otro), guardarlo en la reserva
    $virtual_link = isset($request['virtual_link']) ? esc_url_raw($request['virtual_link']) : '';
    if (!empty($virtual_link)) {
        $update_data['virtual_link'] = $virtual_link;
        $update_format[] = '%s';
        error_log("✅ virtual_link recibido para reserva ID $id: $virtual_link");
    }

    // 🔹 Actualizar registro
    $updated = $wpdb->update(
        $table,
        $update_data,
        ['id' => $id],
        $update_format,
        ['%d']
    );
    
    if ($updated === false) {
        error_log("❌ Error al actualizar reserva ID $id: " . $wpdb->last_error);
        return new WP_REST_Response(['error' => 'Error al actualizar'], 500);
    }

    error_log("✅ Reserva ID $id actualizada: estado=confirmed" . (!empty($calendar_uid) ? ", calendar_uid=$calendar_uid" : ""));
    
    // =========================================================================
    // 🛡️ LÓGICA DE CANCELACIÓN EN CASCADA (con overlap + assignment)
    // =========================================================================
    // Calcular rango de tiempo de la reserva confirmada
    $start = $reserva->fecha;
    $duracion_minutos = isset($reserva->duracion) && !empty($reserva->duracion) 
        ? intval($reserva->duracion) 
        : 60; // fallback 60 min
    $end = date('Y-m-d H:i:s', strtotime($reserva->fecha) + ($duracion_minutos * 60));
    $assignment_id = isset($reserva->assignment_id) ? $reserva->assignment_id : null;
    
    error_log("🔍 [REST API] Buscando conflictos por overlap:");
    error_log("   Rango confirmado: $start → $end");
    error_log("   Assignment ID: " . ($assignment_id === null ? 'NULL (FIXED)' : $assignment_id));

    $conflictos = ReservationsModel::get_pending_conflicts_overlapping($start, $end, $assignment_id, $id);

    if (!empty($conflictos)) {
        error_log("⚔️ [REST API] Se encontraron " . count($conflictos) . " citas pendientes en conflicto por overlap");
        
        foreach ($conflictos as $conflicto) {
            $cancelado = $wpdb->update(
                $table, 
                ['estado' => 'cancelled'], 
                ['id' => $conflicto->id]
            );
            
            if ($cancelado !== false) {
                error_log("🚫 [Auto-Cancel REST] Cita ID {$conflicto->id} ({$conflicto->nombre}) cancelada automáticamente por ocupación de slot.");
                
                // 🔔 Marcar notificación como leída
                $notifications_table = $wpdb->prefix . 'aa_notifications';
                $notification_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $notifications_table 
                    WHERE entity_type = %s AND entity_id = %d",
                    'reservation',
                    $conflicto->id
                ));
                
                if ($notification_id) {
                    $notification_updated = $wpdb->update(
                        $notifications_table,
                        ['is_read' => 1],
                        ['id' => $notification_id],
                        ['%d'],
                        ['%d']
                    );
                    
                    if ($notification_updated !== false) {
                        error_log("✅ [Auto-Cancel REST] Notificación ID $notification_id marcada como leída para cita cancelada ID {$conflicto->id}");
                    } else {
                        error_log("⚠️ [Auto-Cancel REST] Error al marcar notificación como leída: " . $wpdb->last_error);
                    }
                }
            }
        }
    }
    // =========================================================================
    
    // =========================================================================
    // 🔔 ACTUALIZAR NOTIFICACIÓN: pending -> confirmed
    // =========================================================================
    $notifications_table = $wpdb->prefix . 'aa_notifications';
    
    // Buscar notificación existente con type='pending'
    $existing_notification_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $notifications_table 
        WHERE entity_type = %s AND entity_id = %d AND type = %s",
        'reservation',
        $id,
        'pending'
    ));
    
    // Si existe, actualizarla a 'confirmed' y marcar como no leída
    if ($existing_notification_id) {
        $notification_updated = $wpdb->update(
            $notifications_table,
            [
                'type' => 'confirmed',
                'is_read' => 0
            ],
            ['id' => $existing_notification_id],
            ['%s', '%d'],
            ['%d']
        );
        
        if ($notification_updated !== false) {
            error_log("✅ [REST API] Notificación ID $existing_notification_id actualizada: pending -> confirmed (unread)");
        } else {
            error_log("⚠️ [REST API] Error al actualizar notificación ID $existing_notification_id: " . $wpdb->last_error);
        }
    } else {
        error_log("ℹ️ [REST API] No se encontró notificación pending para reserva ID $id (fail-safe)");
    }
    // =========================================================================
    
    $response_data = [
        'success' => true,
        'id' => $id,
        'estado' => 'confirmed'
    ];
    
    if (!empty($calendar_uid)) {
        $response_data['calendar_uid'] = $calendar_uid;
    }

    return new WP_REST_Response($response_data, 200);
}

