<?php
/**
 * Servicio: Confirmación de Citas con Backend
 * 
 * Responsable de:
 * - Obtener datos de la reserva desde WordPress
 * - Construir payload para el backend
 * - Enviar petición autenticada con HMAC
 * - Actualizar estado de la reserva en WordPress
 * - Ejecutar cancelación en cascada de citas conflictivas
 * - Enviar correos de confirmación al backend
 * 
 * NO contiene validaciones de AJAX ni permisos (eso es del controlador).
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Services
 */

if (!defined('ABSPATH')) exit;

/**
 * Transforma el valor de servicio para enviar al backend
 * 
 * - Si empieza con "fixed::", extrae solo el nombre (ej: "fixed::Informes" -> "Informes")
 * - Si es un ID numérico, busca el nombre del servicio en la BD (tabla aa_services)
 * - Si no encuentra el servicio, retorna el valor original como fallback
 * 
 * @param string $servicio_raw Valor de servicio tal como viene del formulario o BD
 * @return string Nombre legible del servicio
 */
function aa_transform_servicio_for_backend($servicio_raw) {
    // Caso 1: Servicio con prefijo "fixed::"
    if (strpos($servicio_raw, 'fixed::') === 0) {
        $nombre = substr($servicio_raw, 7); // strlen('fixed::') = 7
        error_log("🔄 [ServicioTransform] fixed:: detectado, extrayendo nombre: '$nombre'");
        return $nombre;
    }
    
    // Caso 2: ID numérico (servicio de assignment)
    if (is_numeric($servicio_raw)) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        
        $service_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $table WHERE id = %d",
            intval($servicio_raw)
        ));
        
        if ($service_name) {
            error_log("🔄 [ServicioTransform] ID $servicio_raw resuelto a nombre: '$service_name'");
            return $service_name;
        }
        
        error_log("⚠️ [ServicioTransform] No se encontró servicio con ID $servicio_raw, usando valor original");
    }
    
    // Caso 3: Ya es un nombre o valor desconocido, retornar tal cual
    return $servicio_raw;
}

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
    
    // =========================================================================
    // 🛡️ LÓGICA DE CANCELACIÓN EN CASCADA (con overlap + assignment)
    // =========================================================================
    require_once plugin_dir_path(__FILE__) . '../models/ReservationsModel.php';

    // Calcular rango de tiempo de la reserva confirmada
    $start = $reserva->fecha;
    $duracion_minutos = isset($reserva->duracion) && !empty($reserva->duracion) 
        ? intval($reserva->duracion) 
        : 60; // fallback 60 min
    $end = date('Y-m-d H:i:s', strtotime($reserva->fecha) + ($duracion_minutos * 60));
    $assignment_id = isset($reserva->assignment_id) ? $reserva->assignment_id : null;
    
    error_log("🔍 [ConfirmService] Buscando conflictos por overlap:");
    error_log("   Rango confirmado: $start → $end");
    error_log("   Assignment ID: " . ($assignment_id === null ? 'NULL (FIXED)' : $assignment_id));

    $conflictos = ReservationsModel::get_pending_conflicts_overlapping($start, $end, $assignment_id, $reserva_id);

    if (!empty($conflictos)) {
        error_log("⚔️ [ConfirmService] Se encontraron " . count($conflictos) . " citas pendientes en conflicto por overlap");
        
        foreach ($conflictos as $conflicto) {
            $cancelado = $wpdb->update(
                $table, 
                ['estado' => 'cancelled'], 
                ['id' => $conflicto->id]
            );
            
            if ($cancelado !== false) {
                error_log("🚫 [Auto-Cancel] Cita ID {$conflicto->id} ({$conflicto->nombre}) cancelada automáticamente por ocupación de slot.");
                
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
                        error_log("✅ [Auto-Cancel] Notificación ID $notification_id marcada como leída para cita cancelada ID {$conflicto->id}");
                    } else {
                        error_log("⚠️ [Auto-Cancel] Error al marcar notificación como leída: " . $wpdb->last_error);
                    }
                }
            }
        }
    }
    // =========================================================================

    // =========================================================================
    // 🔔 MARCAR NOTIFICACIÓN PENDING COMO LEÍDA (admin ya conoce la acción)
    // =========================================================================
    $notifications_table = $wpdb->prefix . 'aa_notifications';
    
    $pending_notification_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $notifications_table 
         WHERE entity_type = %s AND entity_id = %d AND type = %s AND is_read = 0",
        'reservation',
        $reserva_id,
        'pending'
    ));
    
    if ($pending_notification_id) {
        $notif_updated = $wpdb->update(
            $notifications_table,
            ['is_read' => 1],
            ['id' => $pending_notification_id],
            ['%d'],
            ['%d']
        );
        
        if ($notif_updated !== false) {
            error_log("✅ [ConfirmService] Notificación pending ID $pending_notification_id marcada como leída para reserva confirmada ID $reserva_id");
        } else {
            error_log("⚠️ [ConfirmService] Error al marcar notificación pending como leída: " . $wpdb->last_error);
        }
    }
    // =========================================================================

    // 2️⃣ Obtener configuración
    $business_name = get_option('aa_business_name', get_bloginfo('name'));
    $business_address = get_option('aa_business_address', 'No especificada');
    
    // 🔹 Obtener duración de la reserva guardada o usar configuración como fallback
    $duracion = isset($reserva->duracion) && !empty($reserva->duracion) 
        ? intval($reserva->duracion) 
        : intval(get_option('aa_slot_duration', 60));
    // Validar que sea 30, 60 o 90
    if (!in_array($duracion, [30, 60, 90])) {
        $duracion = 60;
    }
    
    // 3️⃣ Obtener dominio limpio usando la función centralizada
    $domain = aa_get_clean_domain();
    
    // 4️⃣ Determinar URL del backend
    $backend_url = (strpos(get_site_url(), 'localhost') !== false)
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
        'slot_duration' => $duracion, // Mantener compatibilidad con backend
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
        return [
            'success' => true,
            'message' => 'Cita confirmada en WordPress, pero no se pudo notificar al backend: ' . $response->get_error_message(),
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
        
        $calendar_uid = $decoded['data']['event_id'] ?? null;
        $calendar_skipped = $decoded['data']['calendarSkipped'] ?? false;
        $existed = $decoded['data']['existed'] ?? false;
        
        // 9️⃣ Actualizar calendar_uid en WordPress (solo si hay evento)
        if ($calendar_uid) {
            $wpdb->update($table, ['calendar_uid' => $calendar_uid], ['id' => $reserva_id]);
            error_log("✅ [ConfirmService] calendar_uid actualizado: $calendar_uid");
        }
        
        // Determinar mensaje según estado de Calendar
        if ($calendar_skipped) {
            $message = 'Cita confirmada. Se notificó al cliente por correo (Calendar omitido).';
        } elseif ($existed) {
            $message = 'Cita confirmada. El evento ya existía en Google Calendar.';
        } else {
            $message = 'Cita confirmada y agregada a Google Calendar.';
        }
        
        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'event_id' => $calendar_uid,
                'event_link' => $decoded['data']['event_link'] ?? null,
                'existed' => $existed,
                'calendar_sync' => !$calendar_skipped
            ]
        ];
        
    } else {
        // ❌ Backend respondió con error
        $error_message = $decoded['message'] ?? 'Error desconocido del backend.';
        
        error_log("⚠️ [ConfirmService] Backend respondió con error: $error_message");
        
        return [
            'success' => true,
            'message' => "Cita confirmada en WordPress, pero no se pudo notificar al backend: $error_message",
            'calendar_sync' => false
        ];
    }
}

/**
 * Enviar correo de confirmación al backend
 * 
 * @param array $datos Datos de la reserva desde AJAX
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function confirm_backend_service_enviar_correo($datos) {
    // 🔹 Si no hay correo del cliente, omitir envío de email
    $correo = $datos['correo'] ?? '';
    if (empty($correo)) {
        error_log("ℹ️ [EmailService] Correo vacío → envío de confirmación por email omitido");
        return [
            'success' => true,
            'message' => 'Correo no disponible, envío de email omitido.',
            'skipped' => true
        ];
    }

    // 🔹 Usar la función centralizada para obtener el domain
    $domain = aa_get_clean_domain();

    error_log("🧩 [EmailService] Dominio detectado: $domain");

    // 🔹 Obtener duración de los datos de la reserva o usar configuración como fallback
    $duracion = isset($datos['duracion']) ? intval($datos['duracion']) : intval(get_option('aa_slot_duration', 60));
    // Validar que sea 30, 60 o 90
    if (!in_array($duracion, [30, 60, 90])) {
        $duracion = 60;
    }

    // 🔄 Transformar servicio: "fixed::Nombre" -> "Nombre", "ID" -> "Nombre del servicio"
    $servicio_raw = $datos['servicio'] ?? '';
    $servicio_transformed = aa_transform_servicio_for_backend($servicio_raw);

    // 🔹 Reorganizar datos para enviar al backend
    $backend_data = [
        'domain' => $domain,
        'nombre' => $datos['nombre'] ?? '',
        'servicio' => $servicio_transformed,
        'fecha' => $datos['fecha'] ?? '',
        'telefono' => $datos['telefono'] ?? '',
        'email' => $datos['correo'] ?? '',
        'id_reserva' => $datos['id_reserva'] ?? null,
        'businessName' => get_option('aa_business_name', 'Nuestro negocio'),
        'businessAddress' => get_option('aa_business_address', 'No especificada'),
        'whatsapp' => get_option('aa_whatsapp_number', ''),
        'slot_duration' => $duracion, // Mantener compatibilidad con backend
    ];

    error_log("📦 [EmailService] Datos reorganizados para backend:");
    error_log(print_r($backend_data, true));

    // 🔹 Determinar URL del backend según entorno
    $site_url = get_site_url();
    $backend_url = (strpos($site_url, 'localhost') !== false)
        ? 'http://localhost:3000/correos/confirmacion'
        : 'https://deoia-oauth-backend.onrender.com/correos/confirmacion';

    // 🔹 Enviar petición autenticada con HMAC
    $response = aa_send_authenticated_request($backend_url, 'POST', $backend_data);

    if (is_wp_error($response)) {
        error_log("❌ [EmailService] Error al contactar backend: " . $response->get_error_message());
        return [
            'success' => false,
            'message' => 'Error de conexión con el backend',
            'error' => $response->get_error_message()
        ];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);

    error_log("📥 [EmailService] Respuesta del backend (status $status): " . print_r($decoded, true));

    if ($status >= 200 && $status < 300 && isset($decoded['success']) && $decoded['success']) {
        return [
            'success' => true,
            'message' => 'Correos enviados correctamente',
            'backend_response' => $decoded
        ];
    } else {
        return [
            'success' => false,
            'message' => 'El backend respondió con error',
            'backend_response' => $decoded
        ];
    }
}