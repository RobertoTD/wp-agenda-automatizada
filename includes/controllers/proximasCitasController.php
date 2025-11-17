<?php
/**
 * Controlador: Próximas Citas
 * 
 * Maneja la lógica AJAX para obtener y filtrar próximas citas
 * pendientes/confirmadas que aún no han finalizado.
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Controllers
 */

if (!defined('ABSPATH')) exit;

/**
 * AJAX: Obtener próximas citas
 * 
 * Retorna citas que aún no han finalizado (fecha + slot_duration >= ahora)
 * con soporte para búsqueda, ordenamiento y paginación.
 */
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