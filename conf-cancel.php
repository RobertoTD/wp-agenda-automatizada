<?php
if (!defined('ABSPATH')) exit;

// ===============================
// 🔹 AJAX: Confirmar cita
// ===============================
add_action('wp_ajax_aa_confirmar_cita', 'aa_ajax_confirmar_cita');
function aa_ajax_confirmar_cita() {
    check_ajax_referer('aa_confirmar_cita');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $id = intval($_POST['id']);
    if (!$id) {
        wp_send_json_error(['message' => 'ID inválido.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    $updated = $wpdb->update($table, ['estado' => 'confirmed'], ['id' => $id]);
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar en BD.']);
    }
    
    error_log("✅ Cita ID $id marcada como 'confirmed' manualmente por asistente");
    
    wp_send_json_success(['message' => 'Cita confirmada correctamente.']);
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
    
    // 🔹 PASO 2: Eliminar evento de Google Calendar (si existe calendar_uid)
    $calendar_deleted = false;
    
    if (!empty($reserva->calendar_uid)) {
        error_log("🗓️ Intentando eliminar evento de Google Calendar: {$reserva->calendar_uid}");
        
        // Extraer dominio limpio
        $site_url = get_site_url();
        $parsed_url = parse_url($site_url);
        $host = $parsed_url['host'] ?? 'localhost';
        
        if (stripos($host, 'localhost') !== false || $host === '127.0.0.1') {
            $domain = 'localhost';
        } else {
            $domain = preg_replace('/^www\./', '', $host);
        }
        
        // Determinar URL del backend
        $backend_url = (strpos($site_url, 'localhost') !== false)
            ? 'http://localhost:3000/cancelaciones/cancelar-cita'
            : 'https://deoia-oauth-backend.onrender.com/cancelaciones/cancelar-cita';
        
        // Datos para enviar al backend
        $backend_data = [
            'domain' => $domain,
            'calendar_uid' => $reserva->calendar_uid,
        ];
        
        error_log("📤 Enviando solicitud de cancelación a: $backend_url");
        error_log("📦 Datos: " . json_encode($backend_data));
        
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
        error_log("ℹ️ La cita ID $id no tiene calendar_uid asociado, no se eliminará de Google Calendar");
    }
    
    wp_send_json_success([
        'message' => 'Cita cancelada correctamente.',
        'calendar_deleted' => $calendar_deleted
    ]);
}