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

    /**
     * Obtener personal (staff)
     * 
     * @param bool $only_active Si es true, solo retorna personal activo
     * @return array Array asociativo con el personal
     */
    public static function get_staff($only_active = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_staff';
        
        $where_clause = '';
        if ($only_active) {
            $where_clause = "WHERE active = 1";
        }
        
        $query = "SELECT id, name, active, created_at 
                  FROM $table 
                  $where_clause 
                  ORDER BY name ASC";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener personal: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Crear nuevo personal (staff)
     * 
     * @param string $name Nombre del personal
     * @return array|false Array con id y name en éxito, false en error
     */
    public static function create_staff($name) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_staff';
        
        // Sanitizar nombre
        $name = sanitize_text_field($name);
        
        // Validar que el nombre no esté vacío
        if (empty($name)) {
            error_log("❌ [AssignmentsModel] Intento de crear personal con nombre vacío");
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
            error_log("❌ [AssignmentsModel] Error al crear personal: " . $wpdb->last_error);
            return false;
        }
        
        $new_id = $wpdb->insert_id;
        
        if (!$new_id) {
            error_log("❌ [AssignmentsModel] Personal creado pero no se obtuvo insert_id");
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Personal creado ID: $new_id (nombre: $name)");
        
        return [
            'id' => $new_id,
            'name' => $name
        ];
    }

    /**
     * Actualizar estado activo de un miembro del personal
     * 
     * @param int $id ID del personal
     * @param int $active 0 para desactivar, 1 para activar
     * @return bool true en éxito, false en error
     */
    public static function set_staff_active($id, $active) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_staff';
        
        // Validar parámetros
        $id = intval($id);
        $active = intval($active);
        
        // Asegurar que active sea 0 o 1
        $active = ($active === 1) ? 1 : 0;
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para actualizar personal: $id");
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
            error_log("❌ [AssignmentsModel] Error al actualizar personal ID $id: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Personal ID $id actualizado (active = $active)");
        
        return true;
    }

    /**
     * Obtener asignaciones
     * 
     * @return array Array asociativo con las asignaciones enriquecidas con nombres de staff y zonas
     */
    public static function get_assignments() {
        global $wpdb;
        $assignments_table = $wpdb->prefix . 'aa_assignments';
        $staff_table = $wpdb->prefix . 'aa_staff';
        $service_areas_table = $wpdb->prefix . 'aa_service_areas';
        
        $query = "SELECT 
                    a.id,
                    a.assignment_date,
                    a.start_time,
                    a.end_time,
                    a.service_key,
                    a.capacity,
                    a.status,
                    s.name AS staff_name,
                    sa.name AS service_area_name
                  FROM $assignments_table a
                  LEFT JOIN $staff_table s ON s.id = a.staff_id
                  LEFT JOIN $service_areas_table sa ON sa.id = a.service_area_id
                  ORDER BY a.assignment_date ASC, a.start_time ASC";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener asignaciones: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Eliminar una asignación
     * 
     * @param int $id ID de la asignación a eliminar
     * @return bool true en éxito, false en error
     */
    public static function delete_assignment($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignments';
        
        // Validar ID
        $id = intval($id);
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para eliminar asignación: $id");
            return false;
        }
        
        // Eliminar de la base de datos
        $result = $wpdb->delete(
            $table,
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al eliminar asignación ID $id: " . $wpdb->last_error);
            return false;
        }
        
        if ($result === 0) {
            error_log("⚠️ [AssignmentsModel] No se encontró asignación con ID $id para eliminar");
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Asignación ID $id eliminada correctamente");
        
        return true;
    }
}

