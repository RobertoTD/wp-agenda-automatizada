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
 * - type: pending | confirmed | cancelled (optional, legacy)
 * - unread: true | false (optional, legacy)
 * - page: int (default 1)
 * - time[]: future | past (optional, panel filter)
 * - status[]: pending | confirmed | cancelled (optional, panel filter)
 * - notification[]: unread | read (optional, panel filter)
 * 
 * @return void JSON response
 */
function aa_get_appointments() {
    global $wpdb;
    
    // Fixed limit
    $limit = 20;
    
    // Get parameters - legacy filters
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $unread = isset($_GET['unread']) && $_GET['unread'] === 'true';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    
    // Get panel filters (arrays)
    $time_filters = isset($_GET['time']) && is_array($_GET['time']) 
        ? array_map('sanitize_text_field', $_GET['time']) 
        : [];
    $status_filters = isset($_GET['status']) && is_array($_GET['status']) 
        ? array_map('sanitize_text_field', $_GET['status']) 
        : [];
    $notification_filters = isset($_GET['notification']) && is_array($_GET['notification']) 
        ? array_map('sanitize_text_field', $_GET['notification']) 
        : [];
    
    // Validate type if provided (legacy)
    $valid_types = ['pending', 'confirmed', 'cancelled'];
    if ($type && !in_array($type, $valid_types)) {
        wp_send_json_error(['message' => 'Tipo de filtro inválido']);
        return;
    }
    
    // Validate panel filter values
    $valid_time = ['future', 'past'];
    $valid_status = ['pending', 'confirmed', 'cancelled'];
    $valid_notification = ['unread', 'read'];
    
    $time_filters = array_filter($time_filters, function($v) use ($valid_time) {
        return in_array($v, $valid_time);
    });
    $status_filters = array_filter($status_filters, function($v) use ($valid_status) {
        return in_array($v, $valid_status);
    });
    $notification_filters = array_filter($notification_filters, function($v) use ($valid_notification) {
        return in_array($v, $valid_notification);
    });
    
    // Tables
    $reservas_table = $wpdb->prefix . 'aa_reservas';
    $notifications_table = $wpdb->prefix . 'aa_notifications';
    $clientes_table = $wpdb->prefix . 'aa_clientes';
    $table_assignment_services = $wpdb->prefix . 'aa_assignment_services';
    $table_services = $wpdb->prefix . 'aa_services';
    
    // Get current datetime in business timezone
    $timezone = get_option('aa_timezone', 'America/Mexico_City');
    $now = new DateTime('now', new DateTimeZone($timezone));
    $now_sql = $now->format('Y-m-d H:i:s');
    
    // Build WHERE clauses
    $where_clauses = [];
    $join_clause = '';
    $needs_notification_join = false;
    
    // Legacy type filter (maps to estado in reservas) - only if no status panel filters
    if ($type && empty($status_filters)) {
        $where_clauses[] = $wpdb->prepare("r.estado = %s", $type);
    }
    
    // Panel: Status filter
    if (!empty($status_filters)) {
        $placeholders = implode(',', array_fill(0, count($status_filters), '%s'));
        $where_clauses[] = $wpdb->prepare("r.estado IN ($placeholders)", ...$status_filters);
    }
    
    // Panel: Time filter
    if (!empty($time_filters)) {
        $time_conditions = [];
        if (in_array('future', $time_filters)) {
            $time_conditions[] = $wpdb->prepare("r.fecha >= %s", $now_sql);
        }
        if (in_array('past', $time_filters)) {
            $time_conditions[] = $wpdb->prepare("r.fecha < %s", $now_sql);
        }
        if (!empty($time_conditions)) {
            $where_clauses[] = '(' . implode(' OR ', $time_conditions) . ')';
        }
    }
    
    // Panel: Notification filter
    if (!empty($notification_filters)) {
        $needs_notification_join = true;
        
        // If both unread and read are selected, no filter needed (show all)
        if (count($notification_filters) === 1) {
            if (in_array('unread', $notification_filters)) {
                $where_clauses[] = "n_filter.is_read = 0";
            } else if (in_array('read', $notification_filters)) {
                $where_clauses[] = "(n_filter.is_read = 1 OR n_filter.id IS NULL)";
            }
        }
    }
    
    // Legacy unread filter - only if no notification panel filters
    if ($unread && empty($notification_filters)) {
        $join_clause = "INNER JOIN {$notifications_table} n ON n.entity_type = 'reservation' AND n.entity_id = r.id AND n.is_read = 0";
        
        // If type is specified, also filter by notification type
        if ($type) {
            $where_clauses[] = $wpdb->prepare("n.type = %s", $type);
        }
    }
    
    // Add notification join for panel filter if needed
    if ($needs_notification_join && empty($join_clause)) {
        $join_clause = "LEFT JOIN {$notifications_table} n_filter ON n_filter.entity_type = 'reservation' AND n_filter.entity_id = r.id";
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
    
    // Resolver nombre del servicio: r.servicio numérico = service_id → aa_services.name; si no, r.servicio (legacy).
    $select_sql = "
        SELECT DISTINCT
            r.id,
            COALESCE(s.name, r.servicio) as servicio,
            r.fecha,
            r.duracion,
            r.nombre,
            r.telefono,
            r.correo,
            r.estado,
            r.created_at,
            r.id_cliente,
            r.assignment_id,
            c.nombre as cliente_nombre,
            CASE 
                WHEN EXISTS (
                    SELECT 1 
                    FROM {$notifications_table} n 
                    WHERE n.entity_type = 'reservation' 
                    AND n.entity_id = r.id 
                    AND n.is_read = 0
                ) THEN 1 
                ELSE 0 
            END as unread
        FROM {$reservas_table} r
        LEFT JOIN {$clientes_table} c ON r.id_cliente = c.id
        LEFT JOIN {$table_services} s ON s.id = CAST(r.servicio AS UNSIGNED)
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
            'fecha_raw' => $row->fecha, // MySQL datetime format for WhatsApp messages
            'duracion' => (int) $row->duracion,
            'nombre' => $row->nombre,
            'cliente_nombre' => $row->cliente_nombre ?: $row->nombre,
            'telefono' => $row->telefono,
            'correo' => $row->correo,
            'estado' => $row->estado,
            'created_at' => $row->created_at,
            'unread' => isset($row->unread) ? (bool) $row->unread : false
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
            'unread' => $unread,
            'time' => $time_filters,
            'status' => $status_filters,
            'notification' => $notification_filters
        ]
    ]);
}

/**
 * Register AJAX endpoint for marking a single appointment notification as read
 */
add_action('wp_ajax_aa_mark_appointment_notification_read', 'aa_mark_appointment_notification_read');

/**
 * Mark notification as read for a specific appointment
 * 
 * Parameters (via $_POST or $_GET):
 * - appointment_id: int (required)
 * 
 * @return void JSON response
 */
function aa_mark_appointment_notification_read() {
    global $wpdb;
    
    // Get appointment_id
    $appointment_id = isset($_REQUEST['appointment_id']) ? intval($_REQUEST['appointment_id']) : 0;
    
    if (!$appointment_id) {
        wp_send_json_error(['message' => 'appointment_id es requerido']);
        return;
    }
    
    $notifications_table = $wpdb->prefix . 'aa_notifications';
    
    // Mark notification as read for this specific appointment
    $result = $wpdb->update(
        $notifications_table,
        ['is_read' => 1],
        [
            'entity_type' => 'reservation',
            'entity_id' => $appointment_id,
            'is_read' => 0
        ],
        ['%d'],
        ['%s', '%d', '%d']
    );
    
    if ($result === false) {
        error_log("❌ Error marking appointment notification as read: " . $wpdb->last_error);
        wp_send_json_error(['message' => 'Error al marcar notificación como leída']);
        return;
    }
    
    // Return success (even if no rows were updated, it's not an error)
    wp_send_json_success([
        'message' => 'Notificación marcada como leída',
        'updated' => $result
    ]);
}
