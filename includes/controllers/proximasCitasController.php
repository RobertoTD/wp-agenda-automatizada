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
// 🔹 AJAX: Obtener próximas citas
// ===============================
add_action('wp_ajax_aa_get_proximas_citas', 'aa_ajax_get_proximas_citas');

function aa_ajax_get_proximas_citas() {
    // Verificar nonce
    check_ajax_referer('aa_proximas_citas');
    
    // Verificar permisos
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 🔹 Obtener fecha y hora actual según aa_timezone
    $now = aa_get_current_datetime();
    
    error_log("🕐 [ProximasCitas] Hora actual: $now");
    
    // 🔹 Obtener slot_duration para calcular fecha de fin
    $slot_duration = intval(get_option('aa_slot_duration', 60));
    
    // 🔹 Parámetros de búsqueda
    $buscar = isset($_POST['buscar']) ? sanitize_text_field($_POST['buscar']) : '';
    $ordenar = isset($_POST['ordenar']) ? sanitize_text_field($_POST['ordenar']) : 'fecha_asc';
    $pagina = isset($_POST['pagina']) ? intval($_POST['pagina']) : 1;
    $por_pagina = 10;
    $offset = ($pagina - 1) * $por_pagina;
    
    // 🔹 Construcción de la consulta base
    // Solo mostrar citas que AÚN NO TERMINARON (fecha + slot_duration >= ahora)
    $where = "WHERE DATE_ADD(fecha, INTERVAL %d MINUTE) >= %s";
    $params = [$slot_duration, $now];
    
    error_log("📋 [ProximasCitas] Buscando citas que terminan después de: $now (duración: {$slot_duration} min)");
    
    // 🔹 Filtro de búsqueda
    if (!empty($buscar)) {
        $where .= " AND (nombre LIKE %s OR telefono LIKE %s OR correo LIKE %s OR servicio LIKE %s)";
        $like_buscar = '%' . $wpdb->esc_like($buscar) . '%';
        $params[] = $like_buscar;
        $params[] = $like_buscar;
        $params[] = $like_buscar;
        $params[] = $like_buscar;
    }
    
    // 🔹 Ordenamiento
    $order_by = match($ordenar) {
        'fecha_asc' => 'ORDER BY fecha ASC',
        'fecha_desc' => 'ORDER BY fecha DESC',
        'cliente_asc' => 'ORDER BY nombre ASC',
        'cliente_desc' => 'ORDER BY nombre DESC',
        'estado_asc' => 'ORDER BY estado ASC',
        'estado_desc' => 'ORDER BY estado DESC',
        default => 'ORDER BY fecha ASC'
    };
    
    // 🔹 Contar total de registros
    $total_query = "SELECT COUNT(*) FROM $table $where";
    $total = $wpdb->get_var($wpdb->prepare($total_query, $params));
    
    // 🔹 Obtener registros paginados
    $query = "SELECT *, DATE_ADD(fecha, INTERVAL %d MINUTE) as fecha_fin FROM $table $where $order_by LIMIT %d OFFSET %d";
    $params_query = array_merge([$slot_duration], $params, [$por_pagina, $offset]);
    
    $citas = $wpdb->get_results($wpdb->prepare($query, $params_query));
    
    // 🔹 Calcular paginación
    $total_paginas = ceil($total / $por_pagina);
    
    error_log("✅ [ProximasCitas] Encontradas {$total} citas, mostrando página {$pagina} de {$total_paginas}");
    
    wp_send_json_success([
        'citas' => $citas,
        'pagina_actual' => $pagina,
        'total_paginas' => $total_paginas,
        'total_registros' => $total
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

// ===============================
// 🔹 AJAX: Obtener citas del día (para módulo de calendario)
// ===============================
add_action('wp_ajax_aa_get_citas_dia', 'aa_ajax_get_citas_dia');

function aa_ajax_get_citas_dia() {
    // Verificar nonce
    check_ajax_referer('aa_calendar_citas');
    
    // Verificar permisos
    if (!current_user_can('manage_options') && !current_user_can('aa_view_panel')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 🔹 Obtener fecha del día (YYYY-MM-DD) desde POST o usar hoy
    $fecha_dia = isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : date('Y-m-d');
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_dia)) {
        wp_send_json_error(['message' => 'Formato de fecha inválido.']);
    }
    
    // 🔹 Obtener slot_duration
    $slot_duration = intval(get_option('aa_slot_duration', 60));
    
    // 🔹 Obtener todas las citas del día (sin filtrar por estado, incluye canceladas para mostrar)
    $fecha_inicio = $fecha_dia . ' 00:00:00';
    $fecha_fin = $fecha_dia . ' 23:59:59';
    
    $query = "SELECT *, DATE_ADD(fecha, INTERVAL %d MINUTE) as fecha_fin 
              FROM $table 
              WHERE fecha >= %s AND fecha <= %s 
              ORDER BY fecha ASC";
    
    $citas = $wpdb->get_results($wpdb->prepare($query, [$slot_duration, $fecha_inicio, $fecha_fin]));
    
    wp_send_json_success([
        'citas' => $citas,
        'fecha' => $fecha_dia
    ]);
}