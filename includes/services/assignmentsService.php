<?php
/**
 * Assignments Service
 * 
 * Provides AJAX endpoints for assignments management.
 * Handles service areas, staff, and assignments operations.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Register AJAX endpoints
 */
add_action('wp_ajax_aa_get_service_areas', 'aa_get_service_areas');
add_action('wp_ajax_aa_create_service_area', 'aa_create_service_area');
add_action('wp_ajax_aa_toggle_service_area', 'aa_toggle_service_area');
add_action('wp_ajax_aa_get_staff', 'aa_get_staff');
add_action('wp_ajax_aa_create_staff', 'aa_create_staff');
add_action('wp_ajax_aa_toggle_staff', 'aa_toggle_staff');
add_action('wp_ajax_aa_get_assignments', 'aa_get_assignments');
add_action('wp_ajax_aa_delete_assignment', 'aa_delete_assignment');
add_action('wp_ajax_aa_get_services', 'aa_get_services');
add_action('wp_ajax_aa_create_assignment', 'aa_create_assignment');

/**
 * Get service areas (zonas de atención)
 * 
 * Returns list of service areas, optionally filtered by active status.
 * 
 * @return void JSON response
 */
function aa_get_service_areas() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Obtener parámetro opcional (por defecto incluir todas para UI de edición)
    $only_active = isset($_GET['only_active']) && $_GET['only_active'] === 'true';
    
    try {
        // Llamar al modelo
        $service_areas = AssignmentsModel::get_service_areas($only_active);
        
        wp_send_json_success([
            'service_areas' => $service_areas,
            'count' => count($service_areas)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener zonas de atención: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener las zonas de atención: ' . $e->getMessage()
        ]);
    }
}

/**
 * Create service area (zona de atención)
 * 
 * Creates a new service area in the database.
 * 
 * @return void JSON response
 */
function aa_create_service_area() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['name']) || empty($_POST['name'])) {
        wp_send_json_error(['message' => 'El nombre de la zona es requerido']);
        return;
    }
    
    // Sanitizar nombre
    $name = sanitize_text_field($_POST['name']);
    
    // Validar que no esté vacío después de sanitizar
    if (empty(trim($name))) {
        wp_send_json_error(['message' => 'El nombre de la zona no puede estar vacío']);
        return;
    }
    
    try {
        // Llamar al modelo para crear la zona
        $result = AssignmentsModel::create_service_area($name);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al crear la zona de atención en la base de datos'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Zona de atención creada correctamente',
            'area' => $result
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al crear zona de atención: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al crear la zona de atención: ' . $e->getMessage()
        ]);
    }
}

/**
 * Toggle service area active status
 * 
 * Activates or deactivates a service area.
 * 
 * @return void JSON response
 */
function aa_toggle_service_area() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id']) || !isset($_POST['active'])) {
        wp_send_json_error(['message' => 'Faltan parámetros requeridos']);
        return;
    }
    
    $id = intval($_POST['id']);
    $active = intval($_POST['active']);
    
    // Validar que active sea 0 o 1
    if ($active !== 0 && $active !== 1) {
        wp_send_json_error(['message' => 'El valor de active debe ser 0 o 1']);
        return;
    }
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    try {
        // Llamar al modelo para actualizar el estado
        $result = AssignmentsModel::set_service_area_active($id, $active);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar el estado de la zona de atención'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Estado de la zona actualizado correctamente',
            'id' => $id,
            'active' => $active
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al actualizar zona de atención: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el estado: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get staff (personal)
 * 
 * Returns list of staff members, optionally filtered by active status.
 * 
 * @return void JSON response
 */
function aa_get_staff() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Obtener parámetro opcional (por defecto incluir todos para UI de edición)
    $only_active = isset($_GET['only_active']) && $_GET['only_active'] === 'true';
    
    try {
        // Llamar al modelo
        $staff = AssignmentsModel::get_staff($only_active);
        
        wp_send_json_success([
            'staff' => $staff,
            'count' => count($staff)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener personal: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener el personal: ' . $e->getMessage()
        ]);
    }
}

/**
 * Create staff member (personal)
 * 
 * Creates a new staff member in the database.
 * 
 * @return void JSON response
 */
function aa_create_staff() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['name']) || empty($_POST['name'])) {
        wp_send_json_error(['message' => 'El nombre del personal es requerido']);
        return;
    }
    
    // Sanitizar nombre
    $name = sanitize_text_field($_POST['name']);
    
    // Validar que no esté vacío después de sanitizar
    if (empty(trim($name))) {
        wp_send_json_error(['message' => 'El nombre del personal no puede estar vacío']);
        return;
    }
    
    try {
        // Llamar al modelo para crear el personal
        $result = AssignmentsModel::create_staff($name);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al crear el personal en la base de datos'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Personal creado correctamente',
            'staff' => $result
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al crear personal: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al crear el personal: ' . $e->getMessage()
        ]);
    }
}

/**
 * Toggle staff active status
 * 
 * Activates or deactivates a staff member.
 * 
 * @return void JSON response
 */
function aa_toggle_staff() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id']) || !isset($_POST['active'])) {
        wp_send_json_error(['message' => 'Faltan parámetros requeridos']);
        return;
    }
    
    $id = intval($_POST['id']);
    $active = intval($_POST['active']);
    
    // Validar que active sea 0 o 1
    if ($active !== 0 && $active !== 1) {
        wp_send_json_error(['message' => 'El valor de active debe ser 0 o 1']);
        return;
    }
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    try {
        // Llamar al modelo para actualizar el estado
        $result = AssignmentsModel::set_staff_active($id, $active);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar el estado del personal'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Estado del personal actualizado correctamente',
            'id' => $id,
            'active' => $active
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al actualizar personal: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el estado: ' . $e->getMessage()
        ]);
    }
}

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
 * Delete assignment
 * 
 * Deletes an assignment from the database.
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
        // Llamar al modelo para eliminar la asignación
        $result = AssignmentsModel::delete_assignment($id);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al eliminar la asignación'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Asignación eliminada correctamente',
            'id' => $id
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al eliminar asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al eliminar la asignación: ' . $e->getMessage()
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
    $required_fields = ['assignment_date', 'start_time', 'end_time', 'staff_id', 'service_area_id', 'service_key'];
    
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
        'service_key' => sanitize_text_field($_POST['service_key']),
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

