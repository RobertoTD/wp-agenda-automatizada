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
    
    // Obtener parámetro opcional (por defecto solo activas)
    $only_active = !isset($_GET['include_inactive']) || $_GET['include_inactive'] !== 'true';
    
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

