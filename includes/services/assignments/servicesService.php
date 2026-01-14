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
add_action('wp_ajax_aa_create_service', 'aa_create_service');

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
