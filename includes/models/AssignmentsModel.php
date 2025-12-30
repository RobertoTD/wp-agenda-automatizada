<?php
/**
 * Modelo: Assignments
 * 
 * Responsable de:
 * - Consultas a las tablas de assignments (aa_staff, aa_service_areas, aa_assignments)
 * - Acceso reutilizable a datos de asignaciones
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Models
 */

if (!defined('ABSPATH')) exit;

class AssignmentsModel {

    /**
     * Obtener zonas de atención (service areas)
     * 
     * @param bool $only_active Si es true, solo retorna zonas activas
     * @return array Array asociativo con las zonas de atención
     */
    public static function get_service_areas($only_active = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_service_areas';
        
        $where_clause = '';
        if ($only_active) {
            $where_clause = "WHERE active = 1";
        }
        
        $query = "SELECT id, name, description, active, created_at 
                  FROM $table 
                  $where_clause 
                  ORDER BY name ASC";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener zonas de atención: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Crear nueva zona de atención (service area)
     * 
     * @param string $name Nombre de la zona de atención
     * @return array|false Array con id y name en éxito, false en error
     */
    public static function create_service_area($name) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_service_areas';
        
        // Sanitizar nombre
        $name = sanitize_text_field($name);
        
        // Validar que el nombre no esté vacío
        if (empty($name)) {
            error_log("❌ [AssignmentsModel] Intento de crear zona con nombre vacío");
            return false;
        }
        
        // Insertar en la base de datos
        $result = $wpdb->insert(
            $table,
            [
                'name' => $name,
                'active' => 1,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al crear zona de atención: " . $wpdb->last_error);
            return false;
        }
        
        $new_id = $wpdb->insert_id;
        
        if (!$new_id) {
            error_log("❌ [AssignmentsModel] Zona creada pero no se obtuvo insert_id");
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Zona de atención creada ID: $new_id (nombre: $name)");
        
        return [
            'id' => $new_id,
            'name' => $name
        ];
    }

    /**
     * Actualizar estado activo de una zona de atención
     * 
     * @param int $id ID de la zona de atención
     * @param int $active 0 para desactivar, 1 para activar
     * @return bool true en éxito, false en error
     */
    public static function set_service_area_active($id, $active) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_service_areas';
        
        // Validar parámetros
        $id = intval($id);
        $active = intval($active);
        
        // Asegurar que active sea 0 o 1
        $active = ($active === 1) ? 1 : 0;
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para actualizar zona: $id");
            return false;
        }
        
        // Actualizar en la base de datos
        $result = $wpdb->update(
            $table,
            ['active' => $active],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al actualizar zona de atención ID $id: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Zona de atención ID $id actualizada (active = $active)");
        
        return true;
    }
}

