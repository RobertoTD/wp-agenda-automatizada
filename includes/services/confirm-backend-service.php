<?php
/**
 * Servicio: Confirmación de Citas con Backend
 * 
 * Responsable de:
 * - Obtener datos de la reserva desde WordPress
 * - Construir payload para el backend
 * - Enviar petición autenticada con HMAC
 * - Actualizar estado de la reserva en WordPress
 * 
 * NO contiene validaciones de AJAX ni permisos (eso es del controlador).
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Services
 */

if (!defined('ABSPATH')) exit;

/**
 * Confirmar una cita enviando solicitud al backend
 * 
 * @param int $reserva_id ID de la reserva
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function confirm_backend_service_confirmar($reserva_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 1️⃣ Obtener datos de la reserva
    $reserva = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $reserva_id
    ));
    
    if (!$reserva) {
        return [
            'success' => false,
            'message' => 'Reserva no encontrada.'
        ];
    }
    
    // 🔹 PASO 1: Actualizar estado en WordPress PRIMERO
    $updated = $wpdb->update($table, ['estado' => 'confirmed'], ['id' => $reserva_id]);
    
    if ($updated === false) {
        error_log("❌ [ConfirmService] Error al actualizar estado en WordPress");
        return [
            'success' => false,
            'message' => 'Error al actualizar el estado en la base de datos.'
        ];
    }
    
    error_log("✅ [ConfirmService] Cita ID $reserva_id marcada como 'confirmed' en WordPress");
    
     // ---------------------------------------------------------
    // 🛑 NUEVO CÓDIGO: Validar si existe email antes de seguir
    // ---------------------------------------------------------
    $google_email = get_option('aa_google_email', '');

    if (empty($google_email)) {
        error_log("ℹ️ [ConfirmService] Modo Local: Sin email configurado.");
        return [
            'success' => true,
            'message' => 'Cita confirmada localmente (Sin sincronización con Google Calendar).',
            'data' => [
                'existed' => false, // No aplica
                'calendar_sync' => false
            ]
        ];
    }
    // ---------------------------------------------------------

    // 2️⃣ Obtener configuración
    $slot_duration = intval(get_option('aa_slot_duration', 60));
    $business_name = get_option('aa_business_name', get_bloginfo('name'));
    $business_address = get_option('aa_business_address', 'No especificada');
    
    // 3️⃣ Extraer dominio limpio
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    $host = $parsed_url['host'] ?? 'localhost';
    
    if (stripos($host, 'localhost') !== false || $host === '127.0.0.1') {
        $domain = 'localhost';
    } else {
        $domain = preg_replace('/^www\./', '', $host);
    }
    
    // 4️⃣ Determinar URL del backend
    $backend_url = (strpos($site_url, 'localhost') !== false)
        ? 'http://localhost:3000/calendar/crear-reserva-directa'
        : 'https://deoia-oauth-backend.onrender.com/calendar/crear-reserva-directa';
    
    // 5️⃣ Formatear fecha según el backend espera (ISO 8601 con timezone)
    $timezone = get_option('aa_timezone', 'America/Mexico_City');
    
    try {
        $fecha_obj = new DateTime($reserva->fecha, new DateTimeZone($timezone));
        $fecha_iso = $fecha_obj->format('c'); // Formato: 2025-11-17T09:30:00-06:00
    } catch (Exception $e) {
        error_log("❌ [ConfirmService] Error al formatear fecha: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al formatear la fecha de la reserva.'
        ];
    }
    
    // 6️⃣ Construir payload para el backend
    $payload = [
        'domain' => $domain,
        'nombre' => $reserva->nombre,
        'servicio' => $reserva->servicio,
        'fecha' => $fecha_iso,
        'telefono' => $reserva->telefono,
        'email' => $reserva->correo,
        'slot_duration' => $slot_duration,
        'businessName' => $business_name,
        'businessAddress' => $business_address,
        'id_reserva' => $reserva_id
    ];
    
    error_log("📤 [ConfirmService] Enviando confirmación al backend:");
    error_log("   URL: $backend_url");
    error_log("   Payload: " . json_encode($payload, JSON_PRETTY_PRINT));
    
    // 7️⃣ Enviar petición autenticada con HMAC
    $response = aa_send_authenticated_request($backend_url, 'POST', $payload);
    
    if (is_wp_error($response)) {
        error_log("⚠️ [ConfirmService] Error al contactar backend: " . $response->get_error_message());
        // ⚠️ La cita YA está confirmada en WordPress, pero no en Google Calendar
        return [
            'success' => true, // ← TRUE porque sí se confirmó en WordPress
            'message' => 'Cita confirmada en WordPress, pero no se pudo sincronizar con Google Calendar: ' . $response->get_error_message(),
            'calendar_sync' => false
        ];
    }
    
    // 8️⃣ Procesar respuesta
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    
    error_log("📥 [ConfirmService] Respuesta del backend (HTTP $status):");
    error_log("   " . print_r($decoded, true));
    
    if ($status >= 200 && $status < 300 && isset($decoded['success']) && $decoded['success']) {
        // ✅ Backend confirmó exitosamente
        
        // 9️⃣ Actualizar calendar_uid en WordPress
        $calendar_uid = $decoded['data']['event_id'] ?? null;
        
        if ($calendar_uid) {
            $wpdb->update($table, ['calendar_uid' => $calendar_uid], ['id' => $reserva_id]);
            error_log("✅ [ConfirmService] calendar_uid actualizado: $calendar_uid");
        }
        
        return [
            'success' => true,
            'message' => $decoded['data']['existed'] 
                ? 'Cita confirmada. El evento ya existía en Google Calendar.' 
                : 'Cita confirmada y agregada a Google Calendar.',
            'data' => [
                'event_id' => $calendar_uid,
                'event_link' => $decoded['data']['event_link'] ?? null,
                'existed' => $decoded['data']['existed'] ?? false,
                'calendar_sync' => true
            ]
        ];
        
    } else {
        // ❌ Backend respondió con error
        $error_message = $decoded['message'] ?? 'Error desconocido del backend.';
        
        error_log("⚠️ [ConfirmService] Backend respondió con error: $error_message");
        
        // ⚠️ La cita YA está confirmada en WordPress, solo falló la sincronización
        return [
            'success' => true, // ← TRUE porque sí se confirmó en WordPress
            'message' => "Cita confirmada en WordPress, pero falló la sincronización con Google Calendar: $error_message",
            'calendar_sync' => false
        ];
    }
}