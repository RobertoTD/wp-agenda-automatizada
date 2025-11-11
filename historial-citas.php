<?php
if (!defined('ABSPATH')) exit;

// ===============================
// 🔹 AJAX: Obtener historial de citas
// ===============================
add_action('wp_ajax_aa_get_historial_citas', 'aa_ajax_get_historial_citas');
function aa_ajax_get_historial_citas() {
    check_ajax_referer('aa_historial_citas');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 🔹 Obtener fecha y hora actual según aa_timezone
    $now = aa_get_current_datetime();
    
    error_log("🕐 Hora actual para historial: $now");
    
    // 🔹 Obtener slot_duration para calcular fecha de fin
    $slot_duration = intval(get_option('aa_slot_duration', 60));
    
    // 🔹 Parámetros de búsqueda
    $buscar = isset($_POST['buscar']) ? sanitize_text_field($_POST['buscar']) : '';
    $ordenar = isset($_POST['ordenar']) ? sanitize_text_field($_POST['ordenar']) : 'fecha_desc';
    $pagina = isset($_POST['pagina']) ? intval($_POST['pagina']) : 1;
    $por_pagina = 5;
    $offset = ($pagina - 1) * $por_pagina;
    
    // 🔹 Construcción de la consulta base
    // Solo mostrar citas que YA TERMINARON (fecha + slot_duration < ahora)
    $where = "WHERE DATE_ADD(fecha, INTERVAL %d MINUTE) < %s";
    $params = [$slot_duration, $now];
    
    error_log("📋 Buscando citas que terminaron antes de: $now (duración: {$slot_duration} min)");
    
    // 🔹 Filtro de búsqueda
    if (!empty($buscar)) {
        $where .= " AND (nombre LIKE %s OR telefono LIKE %s OR correo LIKE %s)";
        $like_buscar = '%' . $wpdb->esc_like($buscar) . '%';
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
        default => 'ORDER BY fecha DESC'
    };
    
    // 🔹 Contar total de registros
    $total_query = "SELECT COUNT(*) FROM $table $where";
    $total = $wpdb->get_var($wpdb->prepare($total_query, $params));
    
    // 🔹 Obtener registros paginados
    $query = "SELECT * FROM $table $where $order_by LIMIT %d OFFSET %d";
    $params[] = $por_pagina;
    $params[] = $offset;
    
    $citas = $wpdb->get_results($wpdb->prepare($query, $params));
    
    // 🔹 Calcular paginación
    $total_paginas = ceil($total / $por_pagina);
    
    wp_send_json_success([
        'citas' => $citas,
        'pagina_actual' => $pagina,
        'total_paginas' => $total_paginas,
        'total_registros' => $total
    ]);
}

// ===============================
// 🔹 AJAX: Marcar cita como "asistió"
// ===============================
add_action('wp_ajax_aa_marcar_asistencia', 'aa_ajax_marcar_asistencia');
function aa_ajax_marcar_asistencia() {
    check_ajax_referer('aa_historial_citas');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $cita_id = isset($_POST['cita_id']) ? intval($_POST['cita_id']) : 0;
    
    if (!$cita_id) {
        wp_send_json_error(['message' => 'ID de cita inválido.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 🔹 Usar zona horaria configurada
    $now = aa_get_current_datetime();
    $slot_duration = intval(get_option('aa_slot_duration', 60));
    
    // 🔹 Verificar que la cita existe y YA TERMINÓ (fecha + duración < ahora)
    $cita = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND DATE_ADD(fecha, INTERVAL %d MINUTE) < %s",
        $cita_id,
        $slot_duration,
        $now
    ));
    
    if (!$cita) {
        wp_send_json_error(['message' => 'La cita no existe o aún no ha terminado.']);
    }
    
    // 🔹 Actualizar estado a "asistió"
    $updated = $wpdb->update(
        $table,
        ['estado' => 'asistió'],
        ['id' => $cita_id]
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar la cita.']);
    }
    
    error_log("✅ Cita ID: $cita_id marcada como 'asistió'");
    
    wp_send_json_success([
        'message' => 'Asistencia registrada correctamente.',
        'cita_id' => $cita_id
    ]);
}

// ===============================
// 🔹 AJAX: Marcar cita como "no asistió"
// ===============================
add_action('wp_ajax_aa_marcar_no_asistencia', 'aa_ajax_marcar_no_asistencia');
function aa_ajax_marcar_no_asistencia() {
    check_ajax_referer('aa_historial_citas');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $cita_id = isset($_POST['cita_id']) ? intval($_POST['cita_id']) : 0;
    
    if (!$cita_id) {
        wp_send_json_error(['message' => 'ID de cita inválido.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 🔹 Usar zona horaria configurada
    $now = aa_get_current_datetime();
    $slot_duration = intval(get_option('aa_slot_duration', 60));
    
    // 🔹 Verificar que la cita existe y YA TERMINÓ (fecha + duración < ahora)
    $cita = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND DATE_ADD(fecha, INTERVAL %d MINUTE) < %s",
        $cita_id,
        $slot_duration,
        $now
    ));
    
    if (!$cita) {
        wp_send_json_error(['message' => 'La cita no existe o aún no ha terminado.']);
    }
    
    // 🔹 Actualizar estado a "no asistió"
    $updated = $wpdb->update(
        $table,
        ['estado' => 'no asistió'],
        ['id' => $cita_id]
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar la cita.']);
    }
    
    error_log("✅ Cita ID: $cita_id marcada como 'no asistió'");
    
    wp_send_json_success([
        'message' => 'No asistencia registrada correctamente.',
        'cita_id' => $cita_id
    ]);
}