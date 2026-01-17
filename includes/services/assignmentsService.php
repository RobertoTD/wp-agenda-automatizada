<?php
/**
 * Assignments Service
 * 
 * Provides AJAX endpoints for assignments management.
 * Handles assignments operations.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Register AJAX endpoints
 */
add_action('wp_ajax_aa_get_assignments', 'aa_get_assignments');
add_action('wp_ajax_aa_delete_assignment', 'aa_delete_assignment');
add_action('wp_ajax_aa_update_assignment_status', 'aa_update_assignment_status');
add_action('wp_ajax_aa_get_services', 'aa_get_services');
add_action('wp_ajax_aa_create_assignment', 'aa_create_assignment');
add_action('wp_ajax_aa_add_assignment_service', 'aa_add_assignment_service');
add_action('wp_ajax_aa_get_assignment_services', 'aa_get_assignment_services');

// Endpoints para disponibilidad basada en assignments
add_action('wp_ajax_aa_get_assignment_dates', 'aa_get_assignment_dates');
add_action('wp_ajax_aa_get_assignment_dates_by_service', 'aa_get_assignment_dates_by_service');
add_action('wp_ajax_aa_get_assignments_by_service_and_date', 'aa_get_assignments_by_service_and_date');
add_action('wp_ajax_aa_get_busy_ranges_by_assignments', 'aa_get_busy_ranges_by_assignments');

/**
 * Get assignments
 * 
 * Returns list of all assignments.
 * 
 * @return void JSON response
 */
function aa_get_assignments() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    try {
        // Llamar al modelo
        $assignments = AssignmentsModel::get_assignments();
        
        wp_send_json_success([
            'assignments' => $assignments,
            'count' => count($assignments)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener asignaciones: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener las asignaciones: ' . $e->getMessage()
        ]);
    }
}

/**
 * Hide assignment (set status = 'inactive' and is_hidden = 1)
 * 
 * Instead of deleting the record, it marks it as hidden and inactive.
 * 
 * @return void JSON response
 */
function aa_delete_assignment() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id'])) {
        wp_send_json_error(['message' => 'El ID de la asignación es requerido']);
        return;
    }
    
    $id = intval($_POST['id']);
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    try {
        // Llamar al modelo para ocultar la asignación
        $result = AssignmentsModel::delete_assignment($id);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al ocultar la asignación'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Asignación ocultada correctamente',
            'id' => $id
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al ocultar asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al ocultar la asignación: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get services list
 * 
 * Returns list of configured services from aa_google_motivo option.
 * 
 * @return void JSON response
 */
function aa_get_services() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    try {
        // Obtener servicios desde la opción
        $motivos_json = get_option('aa_google_motivo', json_encode(['Cita general']));
        $motivos = json_decode($motivos_json, true);
        
        if (!is_array($motivos) || empty($motivos)) {
            $motivos = ['Cita general'];
        }
        
        wp_send_json_success([
            'services' => $motivos,
            'count' => count($motivos)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener servicios: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener los servicios: ' . $e->getMessage()
        ]);
    }
}

/**
 * Create assignment
 * 
 * Creates a new assignment in the database.
 * Validates for collisions with existing assignments.
 * 
 * @return void JSON response
 */
function aa_create_assignment() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Validar campos requeridos
    $required_fields = ['assignment_date', 'start_time', 'end_time', 'staff_id', 'service_area_id'];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            wp_send_json_error(['message' => "El campo $field es requerido"]);
            return;
        }
    }
    
    // Sanitizar datos
    $data = [
        'assignment_date' => sanitize_text_field($_POST['assignment_date']),
        'start_time' => sanitize_text_field($_POST['start_time']),
        'end_time' => sanitize_text_field($_POST['end_time']),
        'staff_id' => intval($_POST['staff_id']),
        'service_area_id' => intval($_POST['service_area_id']),
        'service_key' => isset($_POST['service_key']) && !empty($_POST['service_key']) ? sanitize_text_field($_POST['service_key']) : '',
        'capacity' => isset($_POST['capacity']) ? intval($_POST['capacity']) : 1
    ];
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['assignment_date'])) {
        wp_send_json_error(['message' => 'Formato de fecha inválido']);
        return;
    }
    
    // Validar formato de hora
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time']) || 
        !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['end_time'])) {
        wp_send_json_error(['message' => 'Formato de hora inválido']);
        return;
    }
    
    // Validar que hora fin sea posterior a hora inicio
    if ($data['start_time'] >= $data['end_time']) {
        wp_send_json_error(['message' => 'La hora de fin debe ser posterior a la hora de inicio']);
        return;
    }
    
    // Validar IDs positivos
    if ($data['staff_id'] <= 0 || $data['service_area_id'] <= 0) {
        wp_send_json_error(['message' => 'IDs inválidos']);
        return;
    }
    
    try {
        // Llamar al modelo para crear la asignación
        $result = AssignmentsModel::create_assignment($data);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al crear la asignación en la base de datos'
            ]);
            return;
        }
        
        if (isset($result['error'])) {
            wp_send_json_error([
                'message' => $result['error']
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Asignación creada correctamente',
            'assignment' => $result
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al crear asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al crear la asignación: ' . $e->getMessage()
        ]);
    }
}

// ============================================
// ENDPOINTS PARA DISPONIBILIDAD BASADA EN ASSIGNMENTS
// ============================================

/**
 * Get all assignment dates
 * 
 * Returns all unique dates that have active assignments.
 * 
 * @return void JSON response
 */
function aa_get_assignment_dates() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    try {
        $dates = AssignmentsModel::get_assignment_dates();
        
        wp_send_json_success([
            'dates' => $dates,
            'count' => count($dates)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener fechas de asignaciones: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener las fechas: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get assignment dates by service
 * 
 * Returns dates that have assignments for a specific service.
 * 
 * @return void JSON response
 */
function aa_get_assignment_dates_by_service() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
    
    // Validar: debe venir service_id (int > 0) o service_key (string no vacío)
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $service_key = isset($_POST['service_key']) ? sanitize_text_field($_POST['service_key']) : '';
    
    if ($service_id <= 0 && empty($service_key)) {
        wp_send_json_error(['message' => 'Se requiere service_id o service_key']);
        return;
    }
    
    try {
        $dates = [];
        $response_data = [];
        
        // Si viene service_id, usar el nuevo método con tabla pivote
        if ($service_id > 0) {
            $dates = AssignmentsModel::get_assignment_dates_by_service_id($service_id, $start_date, $end_date);
            $response_data = [
                'service_id' => $service_id,
                'dates' => $dates,
                'count' => count($dates)
            ];
        } else {
            // Legacy: usar service_key
            $dates = AssignmentsModel::get_assignment_dates_by_service($service_key, $start_date, $end_date);
            $response_data = [
                'service_key' => $service_key,
                'dates' => $dates,
                'count' => count($dates)
            ];
        }
        
        wp_send_json_success($response_data);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener fechas por servicio: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener las fechas: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get assignments by service and date
 * 
 * Returns all assignments for a specific service on a specific date.
 * 
 * @return void JSON response
 */
function aa_get_assignments_by_service_and_date() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Validar campos requeridos
    if (!isset($_POST['service_key']) || empty($_POST['service_key'])) {
        wp_send_json_error(['message' => 'El campo service_key es requerido']);
        return;
    }
    
    if (!isset($_POST['date']) || empty($_POST['date'])) {
        wp_send_json_error(['message' => 'El campo date es requerido']);
        return;
    }
    
    $service_key = sanitize_text_field($_POST['service_key']);
    $date = sanitize_text_field($_POST['date']);
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
        return;
    }
    
    try {
        $assignments = AssignmentsModel::get_assignments_by_service_and_date($service_key, $date);
        
        wp_send_json_success([
            'service_key' => $service_key,
            'date' => $date,
            'assignments' => $assignments,
            'count' => count($assignments)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener asignaciones por servicio y fecha: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener las asignaciones: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get busy ranges by assignment IDs
 * 
 * Returns busy time ranges for specific assignments on a date.
 * 
 * @return void JSON response
 */
function aa_get_busy_ranges_by_assignments() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Validar campos requeridos
    if (!isset($_POST['assignment_ids']) || empty($_POST['assignment_ids'])) {
        wp_send_json_error(['message' => 'El campo assignment_ids es requerido']);
        return;
    }
    
    if (!isset($_POST['date']) || empty($_POST['date'])) {
        wp_send_json_error(['message' => 'El campo date es requerido']);
        return;
    }
    
    // Parsear assignment_ids (puede venir como JSON string)
    $assignment_ids_raw = $_POST['assignment_ids'];
    if (is_string($assignment_ids_raw)) {
        $assignment_ids = json_decode($assignment_ids_raw, true);
    } else {
        $assignment_ids = $assignment_ids_raw;
    }
    
    if (!is_array($assignment_ids) || empty($assignment_ids)) {
        wp_send_json_error(['message' => 'assignment_ids debe ser un array no vacío']);
        return;
    }
    
    $date = sanitize_text_field($_POST['date']);
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
        return;
    }
    
    try {
        $busy_ranges = AssignmentsModel::get_busy_ranges_by_assignment_ids($assignment_ids, $date);
        
        wp_send_json_success([
            'assignment_ids' => $assignment_ids,
            'date' => $date,
            'busy_ranges' => $busy_ranges,
            'count' => count($busy_ranges)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener busy ranges: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener los rangos ocupados: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update assignment status
 * 
 * Updates the status of an assignment (active/inactive).
 * 
 * @return void JSON response
 */
function aa_update_assignment_status() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        wp_send_json_error(['message' => 'Faltan parámetros requeridos']);
        return;
    }
    
    $id = intval($_POST['id']);
    $status = sanitize_text_field($_POST['status']);
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    // Validar que status sea 'active' o 'inactive'
    if ($status !== 'active' && $status !== 'inactive') {
        wp_send_json_error(['message' => 'Status inválido. Debe ser "active" o "inactive"']);
        return;
    }
    
    try {
        // Llamar al modelo para actualizar el status
        $result = AssignmentsModel::update_assignment_status($id, $status);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar el status de la asignación'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Status de la asignación actualizado correctamente',
            'id' => $id,
            'status' => $status
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al actualizar status de asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el status: ' . $e->getMessage()
        ]);
    }
}

/**
 * Add assignment service
 * 
 * Adds a relationship between an assignment and a service in the pivot table.
 * 
 * @return void JSON response
 */
function aa_add_assignment_service() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['assignment_id']) || !isset($_POST['service_id'])) {
        wp_send_json_error(['message' => 'Faltan parámetros requeridos']);
        return;
    }
    
    $assignment_id = intval($_POST['assignment_id']);
    $service_id = intval($_POST['service_id']);
    
    // Validar IDs
    if ($assignment_id <= 0 || $service_id <= 0) {
        wp_send_json_error(['message' => 'IDs inválidos']);
        return;
    }
    
    try {
        // Llamar al modelo para agregar la relación
        $result = AssignmentsModel::add_assignment_service($assignment_id, $service_id);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al agregar el servicio a la asignación'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Servicio agregado a la asignación correctamente',
            'assignment_id' => $assignment_id,
            'service_id' => $service_id
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al agregar servicio a asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al agregar el servicio: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get assignment services
 * 
 * Returns list of services assigned to a specific assignment.
 * 
 * @return void JSON response
 */
function aa_get_assignment_services() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['assignment_id']) || empty($_POST['assignment_id'])) {
        wp_send_json_error(['message' => 'El ID de la asignación es requerido']);
        return;
    }
    
    $assignment_id = intval($_POST['assignment_id']);
    
    // Validar ID
    if ($assignment_id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    try {
        // Llamar al modelo para obtener los servicios
        $services = AssignmentsModel::get_assignment_services($assignment_id);
        
        wp_send_json_success([
            'services' => $services,
            'count' => count($services),
            'assignment_id' => $assignment_id
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener servicios de asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener los servicios: ' . $e->getMessage()
        ]);
    }
}

