<?php
/**
 * Appointments Service
 * 
 * Provides AJAX endpoint for fetching appointments with filters.
 * Used by the Appointments Explorer modal.
 * 
 * @package AgendaAutomatizada
 * @since 1.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Register AJAX endpoint for fetching appointments
 */
add_action('wp_ajax_aa_get_appointments', 'aa_get_appointments');

/**
 * Get appointments with optional filters
 * 
 * Parameters (via $_GET):
 * - type: pending | confirmed | cancelled (optional)
 * - unread: true | false (optional)
 * - page: int (default 1)
 * 
 * @return void JSON response
 */
function aa_get_appointments() {
    global $wpdb;
    
    // Fixed limit
    $limit = 20;
    
    // Get parameters
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $unread = isset($_GET['unread']) && $_GET['unread'] === 'true';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    
    // Validate type if provided
    $valid_types = ['pending', 'confirmed', 'cancelled'];
    if ($type && !in_array($type, $valid_types)) {
        wp_send_json_error(['message' => 'Tipo de filtro inválido']);
        return;
    }
    
    // Tables
    $reservas_table = $wpdb->prefix . 'aa_reservas';
    $notifications_table = $wpdb->prefix . 'aa_notifications';
    $clientes_table = $wpdb->prefix . 'aa_clientes';
    
    // Build WHERE clauses
    $where_clauses = [];
    $join_clause = '';
    
    // Type filter (maps to estado in reservas)
    if ($type) {
        $where_clauses[] = $wpdb->prepare("r.estado = %s", $type);
    }
    
    // Unread filter - requires join with notifications
    if ($unread) {
        $join_clause = "INNER JOIN {$notifications_table} n ON n.entity_type = 'reservation' AND n.entity_id = r.id AND n.is_read = 0";
        
        // If type is specified, also filter by notification type
        if ($type) {
            $where_clauses[] = $wpdb->prepare("n.type = %s", $type);
        }
    }
    
    // Build WHERE string
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Count total for pagination
    $count_sql = "
        SELECT COUNT(DISTINCT r.id)
        FROM {$reservas_table} r
        {$join_clause}
        {$where_sql}
    ";
    
    $total_items = (int) $wpdb->get_var($count_sql);
    $total_pages = ceil($total_items / $limit);
    
    // Ensure page is within bounds
    $page = min($page, max(1, $total_pages));
    $offset = ($page - 1) * $limit;
    
    // Get appointments
    $select_sql = "
        SELECT DISTINCT
            r.id,
            r.servicio,
            r.fecha,
            r.duracion,
            r.nombre,
            r.telefono,
            r.correo,
            r.estado,
            r.created_at,
            r.id_cliente,
            c.nombre as cliente_nombre
        FROM {$reservas_table} r
        LEFT JOIN {$clientes_table} c ON r.id_cliente = c.id
        {$join_clause}
        {$where_sql}
        ORDER BY r.created_at DESC
        LIMIT %d OFFSET %d
    ";
    
    $results = $wpdb->get_results(
        $wpdb->prepare($select_sql, $limit, $offset)
    );
    
    // Format items for frontend
    $items = [];
    foreach ($results as $row) {
        // Get timezone for date formatting
        $timezone = get_option('aa_timezone', 'America/Mexico_City');
        
        // Format date
        $fecha_formatted = '';
        $hora_formatted = '';
        
        if ($row->fecha) {
            try {
                $date = new DateTime($row->fecha, new DateTimeZone($timezone));
                $fecha_formatted = $date->format('d/m/Y');
                $hora_formatted = $date->format('H:i');
            } catch (Exception $e) {
                $fecha_formatted = $row->fecha;
                $hora_formatted = '';
            }
        }
        
        $items[] = [
            'id' => (int) $row->id,
            'servicio' => $row->servicio,
            'fecha' => $fecha_formatted,
            'hora' => $hora_formatted,
            'duracion' => (int) $row->duracion,
            'nombre' => $row->nombre,
            'cliente_nombre' => $row->cliente_nombre ?: $row->nombre,
            'telefono' => $row->telefono,
            'correo' => $row->correo,
            'estado' => $row->estado,
            'created_at' => $row->created_at
        ];
    }
    
    // Send response
    wp_send_json_success([
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total_items,
            'has_prev' => $page > 1,
            'has_next' => $page < $total_pages
        ],
        'filters' => [
            'type' => $type,
            'unread' => $unread
        ]
    ]);
}

