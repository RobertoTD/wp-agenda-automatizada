<?php
/**
 * Areas Service
 * 
 * Provides AJAX endpoints for service areas (zonas de atención) management.
 * Handles service areas operations within the assignments module.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Register AJAX endpoints for service areas
 */
add_action('wp_ajax_aa_get_service_areas', 'aa_get_service_areas');
add_action('wp_ajax_aa_create_service_area', 'aa_create_service_area');
add_action('wp_ajax_aa_toggle_service_area', 'aa_toggle_service_area');
add_action('wp_ajax_aa_update_service_area_color', 'aa_update_service_area_color');
add_action('wp_ajax_aa_update_service_area_description', 'aa_update_service_area_description');
add_action('wp_ajax_aa_update_service_area_name', 'aa_update_service_area_name');

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
        error_log("❌ [areasService] Error al obtener zonas de atención: " . $e->getMessage());
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
        error_log("❌ [areasService] Error al crear zona de atención: " . $e->getMessage());
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
        error_log("❌ [areasService] Error al actualizar zona de atención: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el estado: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update service area color
 * 
 * Updates the color of a service area.
 * 
 * @return void JSON response
 */
function aa_update_service_area_color() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id'])) {
        wp_send_json_error(['message' => 'Falta el parámetro ID']);
        return;
    }
    
    $id = intval($_POST['id']);
    $color = isset($_POST['color']) ? sanitize_text_field($_POST['color']) : '';
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    // Validar formato de color si se proporciona
    if (!empty($color) && !preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
        wp_send_json_error(['message' => 'Formato de color inválido. Debe ser hexadecimal (ej: #16225b)']);
        return;
    }
    
    try {
        // Llamar al modelo para actualizar el color
        $result = AssignmentsModel::update_service_area_color($id, $color);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar el color de la zona de atención'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Color de la zona actualizado correctamente',
            'id' => $id,
            'color' => $color
        ]);
    } catch (Exception $e) {
        error_log("❌ [areasService] Error al actualizar color de zona: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el color: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update service area description
 * 
 * Updates the description of a service area.
 * 
 * @return void JSON response
 */
function aa_update_service_area_description() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id'])) {
        wp_send_json_error(['message' => 'Falta el parámetro ID']);
        return;
    }
    
    $id = intval($_POST['id']);
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    try {
        // Llamar al modelo para actualizar la descripción
        $result = AssignmentsModel::update_service_area_description($id, $description);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar la descripción de la zona de atención'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Descripción de la zona actualizada correctamente',
            'id' => $id,
            'description' => $description
        ]);
    } catch (Exception $e) {
        error_log("❌ [areasService] Error al actualizar descripción de zona: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar la descripción: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update service area name
 * 
 * Updates the name of a service area.
 * 
 * @return void JSON response
 */
function aa_update_service_area_name() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id'])) {
        wp_send_json_error(['message' => 'Falta el parámetro ID']);
        return;
    }
    
    $id = intval($_POST['id']);
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    // Validar que el nombre no esté vacío
    if (empty(trim($name))) {
        wp_send_json_error(['message' => 'El nombre no puede estar vacío']);
        return;
    }
    
    try {
        // Llamar al modelo para actualizar el nombre
        $result = AssignmentsModel::update_service_area_name($id, $name);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar el nombre de la zona de atención'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Nombre de la zona actualizado correctamente',
            'id' => $id,
            'name' => $name
        ]);
    } catch (Exception $e) {
        error_log("❌ [areasService] Error al actualizar nombre de zona: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el nombre: ' . $e->getMessage()
        ]);
    }
}
