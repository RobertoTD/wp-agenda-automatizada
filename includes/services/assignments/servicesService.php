<?php
/**
 * Services Service
 * 
 * Provides AJAX endpoints for services management.
 * Handles services operations within the assignments module.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Register AJAX endpoints for services
 */
add_action('wp_ajax_aa_get_services_db', 'aa_get_services_db');
add_action('wp_ajax_aa_create_service', 'aa_create_service');
add_action('wp_ajax_aa_update_service_db', 'aa_update_service_db');
add_action('wp_ajax_aa_delete_service_db', 'aa_delete_service_db');
add_action('wp_ajax_aa_toggle_service', 'aa_toggle_service');

/**
 * Get list of services from database
 * 
 * AJAX handler for retrieving services list from wp_aa_services table
 * Note: This endpoint reads from the database table, not from wp_options.
 * Legacy endpoint aa_get_services (in assignmentsService.php) reads from wp_options.
 */
function aa_get_services_db() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    try {
        // Obtener servicios desde el modelo
        $services = AssignmentsModel::get_services(false);
        
        wp_send_json_success([
            'services' => $services,
            'count' => count($services)
        ]);
    } catch (Exception $e) {
        error_log("❌ [servicesService] Error al obtener servicios: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener los servicios: ' . $e->getMessage()
        ]);
    }
}

/**
 * Create a new service
 * 
 * AJAX handler for creating a new service
 */
function aa_create_service() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['name']) || empty($_POST['name'])) {
        wp_send_json_error(['message' => 'El nombre del servicio es requerido']);
        return;
    }
    
    // Sanitizar nombre
    $name = sanitize_text_field($_POST['name']);
    
    // Validar que no esté vacío después de sanitizar
    if (empty(trim($name))) {
        wp_send_json_error(['message' => 'El nombre del servicio no puede estar vacío']);
        return;
    }
    
    try {
        // Llamar al modelo para crear el servicio
        $result = AssignmentsModel::create_service($name);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al crear el servicio en la base de datos'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Servicio creado correctamente',
            'service' => $result
        ]);
    } catch (Exception $e) {
        error_log("❌ [servicesService] Error al crear servicio: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al crear el servicio: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update a service
 * 
 * AJAX handler for updating a service
 */
function aa_update_service_db() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        wp_send_json_error(['message' => 'El ID del servicio es requerido']);
        return;
    }
    
    $id = intval($_POST['id']);
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    // Preparar datos para actualizar
    $data = [];
    
    // Code
    if (isset($_POST['code'])) {
        $data['code'] = sanitize_text_field($_POST['code']);
    }
    
    // Price (permitir vacío para NULL)
    if (isset($_POST['price'])) {
        $price = $_POST['price'];
        if ($price === '' || $price === null) {
            $data['price'] = null;
        } else {
            $data['price'] = floatval($price);
        }
    }
    
    // Description
    if (isset($_POST['description'])) {
        $data['description'] = sanitize_textarea_field($_POST['description']);
    }
    
    if (empty($data)) {
        wp_send_json_error(['message' => 'No hay datos para actualizar']);
        return;
    }
    
    try {
        // Llamar al modelo para actualizar el servicio
        $result = AssignmentsModel::update_service($id, $data);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar el servicio en la base de datos'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Servicio actualizado correctamente',
            'service' => $result
        ]);
    } catch (Exception $e) {
        error_log("❌ [servicesService] Error al actualizar servicio: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el servicio: ' . $e->getMessage()
        ]);
    }
}

/**
 * Delete a service
 * 
 * AJAX handler for deleting a service
 */
function aa_delete_service_db() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        wp_send_json_error(['message' => 'El ID del servicio es requerido']);
        return;
    }
    
    $id = intval($_POST['id']);
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    try {
        // Llamar al modelo para eliminar el servicio
        $result = AssignmentsModel::delete_service($id);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al eliminar el servicio'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Servicio eliminado correctamente',
            'deleted' => true,
            'id' => $id
        ]);
    } catch (Exception $e) {
        error_log("❌ [servicesService] Error al eliminar servicio: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al eliminar el servicio: ' . $e->getMessage()
        ]);
    }
}

/**
 * Toggle service active status
 * 
 * AJAX handler for toggling service active status
 */
function aa_toggle_service() {
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
        $result = AssignmentsModel::set_service_active($id, $active);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar el estado del servicio'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Estado del servicio actualizado correctamente',
            'id' => $id,
            'active' => $active
        ]);
    } catch (Exception $e) {
        error_log("❌ [servicesService] Error al actualizar estado del servicio: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el estado del servicio: ' . $e->getMessage()
        ]);
    }
}
