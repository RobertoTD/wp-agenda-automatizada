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

    /**
     * Crear nueva asignación
     * 
     * Valida colisiones con asignaciones existentes antes de insertar.
     * 
     * @param array $data Datos de la asignación:
     *   - assignment_date (string): Fecha YYYY-MM-DD
     *   - start_time (string): Hora inicio HH:MM
     *   - end_time (string): Hora fin HH:MM
     *   - staff_id (int): ID del personal
     *   - service_area_id (int): ID de la zona
     *   - service_key (string): Clave del servicio
     *   - capacity (int): Capacidad (default 1)
     * @return array|false Array con la asignación creada, array con error, o false
     */
    public static function create_assignment($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignments';
        
        // Validar datos requeridos
        $required = ['assignment_date', 'start_time', 'end_time', 'staff_id', 'service_area_id', 'service_key'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                error_log("❌ [AssignmentsModel] Campo requerido vacío: $field");
                return false;
            }
        }
        
        // Normalizar horas a formato HH:MM:SS
        $start_time = strlen($data['start_time']) === 5 ? $data['start_time'] . ':00' : $data['start_time'];
        $end_time = strlen($data['end_time']) === 5 ? $data['end_time'] . ':00' : $data['end_time'];
        
        // Verificar colisión por staff (mismo personal, misma fecha, horario traslapado)
        $staff_collision = self::check_staff_collision(
            $data['assignment_date'],
            $start_time,
            $end_time,
            $data['staff_id']
        );
        
        if ($staff_collision) {
            error_log("❌ [AssignmentsModel] Colisión detectada: personal ya tiene asignación en ese horario");
            return [
                'error' => 'El personal seleccionado ya tiene una asignación en ese horario'
            ];
        }
        
        // Verificar colisión por zona (misma zona, misma fecha, horario traslapado)
        $area_collision = self::check_area_collision(
            $data['assignment_date'],
            $start_time,
            $end_time,
            $data['service_area_id']
        );
        
        if ($area_collision) {
            error_log("❌ [AssignmentsModel] Colisión detectada: zona ya tiene asignación en ese horario");
            return [
                'error' => 'La zona seleccionada ya tiene una asignación en ese horario'
            ];
        }
        
        // Preparar datos para inserción
        $insert_data = [
            'assignment_date' => $data['assignment_date'],
            'start_time' => $start_time,
            'end_time' => $end_time,
            'staff_id' => intval($data['staff_id']),
            'service_area_id' => intval($data['service_area_id']),
            'service_key' => sanitize_text_field($data['service_key']),
            'capacity' => isset($data['capacity']) ? intval($data['capacity']) : 1,
            'status' => 'active',
            'created_at' => current_time('mysql')
        ];
        
        // Insertar en la base de datos
        $result = $wpdb->insert(
            $table,
            $insert_data,
            ['%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al insertar asignación: " . $wpdb->last_error);
            return false;
        }
        
        $new_id = $wpdb->insert_id;
        
        if (!$new_id) {
            error_log("❌ [AssignmentsModel] Asignación creada pero no se obtuvo insert_id");
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Asignación creada ID: $new_id");
        
        // Retornar datos de la asignación creada
        $insert_data['id'] = $new_id;
        return $insert_data;
    }

    /**
     * Verificar colisión de horario para un staff
     * 
     * @param string $date Fecha de la asignación
     * @param string $start_time Hora inicio
     * @param string $end_time Hora fin
     * @param int $staff_id ID del personal
     * @param int|null $exclude_id ID de asignación a excluir (para ediciones)
     * @return bool true si hay colisión, false si no
     */
    public static function check_staff_collision($date, $start_time, $end_time, $staff_id, $exclude_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignments';
        
        // Query para detectar traslape de horarios
        // Traslape ocurre cuando:
        // - Nuevo inicio < Existente fin Y Nuevo fin > Existente inicio
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE assignment_date = %s 
             AND staff_id = %d 
             AND status = 'active'
             AND (
                 (%s < end_time AND %s > start_time)
             )",
            $date,
            $staff_id,
            $start_time,
            $end_time
        );
        
        if ($exclude_id) {
            $query .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }
        
        $count = $wpdb->get_var($query);
        
        return intval($count) > 0;
    }

    /**
     * Verificar colisión de horario para una zona
     * 
     * @param string $date Fecha de la asignación
     * @param string $start_time Hora inicio
     * @param string $end_time Hora fin
     * @param int $service_area_id ID de la zona
     * @param int|null $exclude_id ID de asignación a excluir (para ediciones)
     * @return bool true si hay colisión, false si no
     */
    public static function check_area_collision($date, $start_time, $end_time, $service_area_id, $exclude_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignments';
        
        // Query para detectar traslape de horarios
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE assignment_date = %s 
             AND service_area_id = %d 
             AND status = 'active'
             AND (
                 (%s < end_time AND %s > start_time)
             )",
            $date,
            $service_area_id,
            $start_time,
            $end_time
        );
        
        if ($exclude_id) {
            $query .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }
        
        $count = $wpdb->get_var($query);
        
        return intval($count) > 0;
    }
}

