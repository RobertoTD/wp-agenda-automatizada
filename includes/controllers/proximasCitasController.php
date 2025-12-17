<?php
/**
 * Controlador: Próximas Citas y Gestión de Estado
 * 
 * Maneja:
 * - Obtención y filtrado de próximas citas (AJAX)
 * - Confirmación manual de citas
 * - Cancelación de citas (con eliminación en Google Calendar)
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Controllers
 */

if (!defined('ABSPATH')) exit;

// ===============================
// 🔹 AJAX: Obtener citas por día (para timeline del calendario)
// ===============================
add_action('wp_ajax_aa_get_citas_por_dia', 'aa_ajax_get_citas_por_dia');

function aa_ajax_get_citas_por_dia() {
    // Verificar nonce
    check_ajax_referer('aa_proximas_citas');
    
    // Verificar permisos
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 🔹 Obtener fecha (opcional)
    $fecha = isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : '';
    
    // Si NO viene fecha, usar la fecha actual en zona horaria local
    if (empty($fecha)) {
        $timezone = get_option('aa_timezone', 'America/Mexico_City');
        $fecha = wp_date('Y-m-d', null, new DateTimeZone($timezone));
    }
    
    // Validar formato de fecha (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        wp_send_json_error(['message' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
    }
    
    // 🔹 Construir rango del día: inicio 00:00:00 y fin 23:59:59
    $fecha_inicio = $fecha . ' 00:00:00';
    $fecha_fin = $fecha . ' 23:59:59';
    
    // 🔹 Consulta: filtrar por rango del día usando BETWEEN
    $query = "SELECT *, DATE_ADD(fecha, INTERVAL IFNULL(duracion, 60) MINUTE) as fecha_fin 
              FROM $table 
              WHERE fecha BETWEEN %s AND %s 
              ORDER BY fecha ASC";
    
    $citas = $wpdb->get_results($wpdb->prepare($query, $fecha_inicio, $fecha_fin));
    
    wp_send_json_success([
        'fecha' => $fecha,
        'citas' => $citas
    ]);
}

// ===============================
// 🔹 AJAX: Cancelar cita (con eliminación en Google Calendar)
// ===============================
add_action('wp_ajax_aa_cancelar_cita', 'aa_ajax_cancelar_cita');

function aa_ajax_cancelar_cita() {
    check_ajax_referer('aa_cancelar_cita');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $id = intval($_POST['id']);
    if (!$id) {
        wp_send_json_error(['message' => 'ID inválido.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 🔹 Obtener datos de la reserva (especialmente calendar_uid)
    $reserva = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $id
    ));
    
    if (!$reserva) {
        wp_send_json_error(['message' => 'Reserva no encontrada.']);
    }
    
    // 🔹 PASO 1: Actualizar estado en WordPress
    $updated = $wpdb->update($table, ['estado' => 'cancelled'], ['id' => $id]);
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar en BD.']);
    }
    
    error_log("✅ Cita ID $id marcada como 'cancelled' en WordPress");
    
    // 🔹 PASO 2: Eliminar evento de Google Calendar (si existe calendar_uid Y hay email configurado)
    $calendar_deleted = false;
    $google_email = get_option('aa_google_email', ''); // ✅ Obtener email configurado
    
    // ✅ CONDICIÓN AGREGADA: !empty($google_email)
    if (!empty($reserva->calendar_uid) && !empty($google_email)) {
        error_log("🗓️ Intentando eliminar evento de Google Calendar: {$reserva->calendar_uid}");
        
        // 🔹 Usar la función centralizada para obtener el domain
        $domain = aa_get_clean_domain();
        
        // Determinar URL del backend
        $site_url = get_site_url();
        $backend_url = (strpos($site_url, 'localhost') !== false)
            ? 'http://localhost:3000/cancelaciones/cancelar-cita'
            : 'https://deoia-oauth-backend.onrender.com/cancelaciones/cancelar-cita';
        
        // Datos para enviar al backend
        $backend_data = [
            'domain' => $domain,
            'calendar_uid' => $reserva->calendar_uid,
        ];
        
        error_log("📤 Enviando solicitud de cancelación a: $backend_url");
        
        // Enviar petición autenticada con HMAC
        $response = aa_send_authenticated_request($backend_url, 'POST', $backend_data);
        
        if (is_wp_error($response)) {
            error_log("⚠️ Error al contactar backend para cancelar: " . $response->get_error_message());
            // No fallar aquí, la cita ya fue cancelada en WordPress
        } else {
            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);
            
            error_log("📥 Respuesta del backend (HTTP $status): " . print_r($decoded, true));
            
            if ($status >= 200 && $status < 300 && isset($decoded['success']) && $decoded['success']) {
                error_log("✅ Evento eliminado de Google Calendar exitosamente");
                $calendar_deleted = true;
            } elseif ($status === 404 || $status === 410) {
                error_log("ℹ️ El evento ya no existe en Google Calendar (puede haber sido eliminado antes)");
                $calendar_deleted = true; // Consideramos exitoso si ya no existe
            } else {
                error_log("⚠️ El backend respondió con error al intentar cancelar en Google Calendar");
            }
        }
    } else {
        // Log específico para saber por qué no se ejecutó
        if (empty($google_email)) {
            error_log("ℹ️ Cancelación LOCAL solamente: No hay 'aa_google_email' configurado.");
        } else {
            error_log("ℹ️ La cita ID $id no tiene 'calendar_uid' asociado, no se eliminará de Google Calendar");
        }
    }
    
    wp_send_json_success([
        'message' => 'Cita cancelada correctamente.',
        'calendar_deleted' => $calendar_deleted
    ]);
}