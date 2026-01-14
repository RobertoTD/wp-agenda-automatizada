<?php
/**
 * Staff Service
 * 
 * Provides AJAX endpoints for staff management.
 * Handles staff operations within the assignments module.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Register AJAX endpoints for staff
 */
add_action('wp_ajax_aa_get_staff', 'aa_get_staff');
add_action('wp_ajax_aa_create_staff', 'aa_create_staff');
add_action('wp_ajax_aa_toggle_staff', 'aa_toggle_staff');

/**
 * Get list of staff
 * 
 * AJAX handler for retrieving staff list from wp_aa_staff table
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
        error_log("❌ [staffService] Error al obtener personal: " . $e->getMessage());
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
        error_log("❌ [staffService] Error al crear personal: " . $e->getMessage());
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
        error_log("❌ [staffService] Error al actualizar personal: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el estado: ' . $e->getMessage()
        ]);
    }
}
