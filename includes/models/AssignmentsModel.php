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
        
        $query = "SELECT id, name, description, color, active, created_at 
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
     * Actualizar color de una zona de atención
     * 
     * @param int $id ID de la zona de atención
     * @param string $color Color en formato hexadecimal (ej: #16225b)
     * @return bool true en éxito, false en error
     */
    public static function update_service_area_color($id, $color) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_service_areas';
        
        // Validar parámetros
        $id = intval($id);
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para actualizar color de zona: $id");
            return false;
        }
        
        // Validar formato de color (hexadecimal)
        $color = sanitize_text_field($color);
        if (!empty($color) && !preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
            error_log("❌ [AssignmentsModel] Formato de color inválido: $color");
            return false;
        }
        
        // Si está vacío, establecer NULL
        if (empty($color)) {
            $color = null;
        }
        
        // Actualizar en la base de datos
        $result = $wpdb->update(
            $table,
            ['color' => $color],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al actualizar color de zona ID $id: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Color de zona ID $id actualizado: $color");
        
        return true;
    }

    /**
     * Actualizar descripción de una zona de atención
     * 
     * @param int $id ID de la zona de atención
     * @param string $description Descripción de la zona
     * @return bool true en éxito, false en error
     */
    public static function update_service_area_description($id, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_service_areas';
        
        // Validar parámetros
        $id = intval($id);
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para actualizar descripción de zona: $id");
            return false;
        }
        
        // Sanitizar descripción
        $description = sanitize_textarea_field($description);
        
        // Actualizar en la base de datos
        $result = $wpdb->update(
            $table,
            ['description' => $description],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al actualizar descripción de zona ID $id: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Descripción de zona ID $id actualizada");
        
        return true;
    }

    /**
     * Actualizar nombre de una zona de atención
     * 
     * @param int $id ID de la zona de atención
     * @param string $name Nombre de la zona
     * @return bool true en éxito, false en error
     */
    public static function update_service_area_name($id, $name) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_service_areas';
        
        // Validar parámetros
        $id = intval($id);
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para actualizar nombre de zona: $id");
            return false;
        }
        
        // Sanitizar nombre
        $name = sanitize_text_field($name);
        
        // Validar que el nombre no esté vacío
        if (empty(trim($name))) {
            error_log("❌ [AssignmentsModel] Intento de actualizar zona con nombre vacío");
            return false;
        }
        
        // Actualizar en la base de datos
        $result = $wpdb->update(
            $table,
            ['name' => $name],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al actualizar nombre de zona ID $id: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Nombre de zona ID $id actualizado: $name");
        
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
     * Crear nuevo servicio
     * 
     * @param string $name Nombre del servicio
     * @return array|false Array con id y name en éxito, false en error
     */
    public static function create_service($name) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        
        // Sanitizar nombre
        $name = sanitize_text_field($name);
        
        // Validar que el nombre no esté vacío
        if (empty($name)) {
            error_log("❌ [AssignmentsModel] Intento de crear servicio con nombre vacío");
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
            error_log("❌ [AssignmentsModel] Error al crear servicio: " . $wpdb->last_error);
            return false;
        }
        
        $new_id = $wpdb->insert_id;
        
        if (!$new_id) {
            error_log("❌ [AssignmentsModel] Servicio creado pero no se obtuvo insert_id");
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Servicio creado ID: $new_id (nombre: $name)");
        
        return [
            'id' => $new_id,
            'name' => $name
        ];
    }

    /**
     * Obtener lista de servicios
     * 
     * Solo retorna servicios que no están ocultos (is_hidden = 0).
     * 
     * @param bool $only_active Si es true, solo retorna servicios activos
     * @return array Array de servicios con id, name, code, description, price, active, created_at
     */
    public static function get_services($only_active = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        
        $where_conditions = [];
        
        // Filtrar por is_hidden = 0 (solo mostrar servicios no ocultos)
        $where_conditions[] = "is_hidden = 0";
        
        if ($only_active) {
            $where_conditions[] = "active = 1";
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        }
        
        $query = "SELECT id, name, code, description, price, active, created_at 
                  FROM $table 
                  $where_clause 
                  ORDER BY name ASC";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener servicios: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Obtener un servicio por ID
     * 
     * @param int $id ID del servicio
     * @return array|false Array con los datos del servicio o false si no existe
     */
    public static function get_service_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        
        $id = intval($id);
        
        if ($id <= 0) {
            return false;
        }
        
        $query = "SELECT id, name, code, description, price, active, created_at 
                  FROM $table 
                  WHERE id = %d
                  LIMIT 1";
        
        $service = $wpdb->get_row($wpdb->prepare($query, $id), ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener servicio ID $id: " . $wpdb->last_error);
            return false;
        }
        
        return $service ? $service : false;
    }

    /**
     * Actualizar un servicio
     * 
     * @param int $id ID del servicio
     * @param array $data Array con los campos a actualizar: code, price, description
     * @return array|false Array con los datos actualizados o false en error
     */
    public static function update_service($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        
        $id = intval($id);
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para actualizar servicio: $id");
            return false;
        }
        
        // Preparar datos para actualizar
        $update_data = [];
        $format = [];
        
        // Code
        if (isset($data['code'])) {
            $update_data['code'] = sanitize_text_field($data['code']);
            $format[] = '%s';
        }
        
        // Price (convertir vacío a NULL)
        if (isset($data['price'])) {
            $price = $data['price'];
            if ($price === '' || $price === null) {
                $update_data['price'] = null;
                $format[] = null; // WordPress manejará NULL
            } else {
                $update_data['price'] = floatval($price);
                $format[] = '%f';
            }
        }
        
        // Description
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            error_log("❌ [AssignmentsModel] No hay datos para actualizar en servicio ID $id");
            return false;
        }
        
        // Actualizar en la base de datos
        // WordPress 4.4+ soporta NULL en $format para campos NULL
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al actualizar servicio ID $id: " . $wpdb->last_error);
            return false;
        }
        
        // Retornar el servicio actualizado
        $updated_service = self::get_service_by_id($id);
        if ($updated_service) {
            error_log("✅ [AssignmentsModel] Servicio ID $id actualizado correctamente");
        }
        
        return $updated_service;
    }

    /**
     * Ocultar un servicio (establece is_hidden = 1 y active = 0)
     * 
     * En lugar de eliminar el registro, se marca como oculto e inactivo
     * para mantener la integridad referencial con otras tablas.
     * 
     * @param int $id ID del servicio
     * @return bool true en éxito, false en error
     */
    public static function delete_service($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        
        $id = intval($id);
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para ocultar servicio: $id");
            return false;
        }
        
        // Actualizar is_hidden a 1 y active a 0 en lugar de eliminar
        $result = $wpdb->update(
            $table,
            [
                'is_hidden' => 1,
                'active' => 0
            ],
            ['id' => $id],
            ['%d', '%d'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al ocultar servicio ID $id: " . $wpdb->last_error);
            return false;
        }
        
        if ($result === 0) {
            error_log("⚠️ [AssignmentsModel] No se encontró servicio con ID $id para ocultar");
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Servicio ID $id ocultado correctamente (is_hidden = 1, active = 0)");
        
        return true;
    }

    /**
     * Actualizar estado activo de un servicio
     * 
     * @param int $id ID del servicio
     * @param int $active 0 para desactivar, 1 para activar
     * @return bool true en éxito, false en error
     */
    public static function set_service_active($id, $active) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        
        // Validar parámetros
        $id = intval($id);
        $active = intval($active);
        
        // Asegurar que active sea 0 o 1
        $active = ($active === 1) ? 1 : 0;
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para actualizar servicio: $id");
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
            error_log("❌ [AssignmentsModel] Error al actualizar servicio ID $id: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Servicio ID $id actualizado (active = $active)");
        
        return true;
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
     * Obtener IDs de servicios asignados a un staff
     * 
     * @param int $staff_id ID del staff
     * @return array Array de service_id
     */
    public static function get_staff_service_ids($staff_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_staff_services';
        
        $staff_id = intval($staff_id);
        
        if ($staff_id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para obtener servicios de staff: $staff_id");
            return [];
        }
        
        $query = "SELECT service_id FROM $table WHERE staff_id = %d";
        $results = $wpdb->get_col($wpdb->prepare($query, $staff_id));
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener service_ids de staff: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? array_map('intval', $results) : [];
    }

    /**
     * Obtener servicios completos asignados a un staff
     * 
     * @param int $staff_id ID del staff
     * @return array Array de servicios con id, name
     */
    public static function get_staff_services($staff_id) {
        global $wpdb;
        $pivot_table = $wpdb->prefix . 'aa_staff_services';
        $services_table = $wpdb->prefix . 'aa_services';
        
        $staff_id = intval($staff_id);
        
        if ($staff_id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para obtener servicios de staff: $staff_id");
            return [];
        }
        
        $query = "SELECT s.id, s.name 
                  FROM $services_table s
                  INNER JOIN $pivot_table ss ON s.id = ss.service_id
                  WHERE ss.staff_id = %d
                  ORDER BY s.name ASC";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $staff_id), ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener servicios de staff: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Agregar relación staff-service
     * 
     * @param int $staff_id ID del staff
     * @param int $service_id ID del servicio
     * @return bool|int true en éxito, false en error, o insert_id si se necesita
     */
    public static function add_staff_service($staff_id, $service_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_staff_services';
        
        $staff_id = intval($staff_id);
        $service_id = intval($service_id);
        
        if ($staff_id <= 0 || $service_id <= 0) {
            error_log("❌ [AssignmentsModel] IDs inválidos para agregar relación staff-service: staff=$staff_id, service=$service_id");
            return false;
        }
        
        // Insertar en la tabla pivote
        $result = $wpdb->insert(
            $table,
            [
                'staff_id' => $staff_id,
                'service_id' => $service_id
            ],
            ['%d', '%d']
        );
        
        if ($result === false) {
            // Verificar si es error de duplicado (UNIQUE constraint)
            if ($wpdb->last_error && strpos($wpdb->last_error, 'Duplicate') !== false) {
                error_log("⚠️ [AssignmentsModel] Relación staff-service ya existe: staff=$staff_id, service=$service_id");
                return false;
            }
            error_log("❌ [AssignmentsModel] Error al agregar relación staff-service: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Relación staff-service agregada: staff=$staff_id, service=$service_id");
        
        return true;
    }

    /**
     * Eliminar relación staff-service
     * 
     * @param int $staff_id ID del staff
     * @param int $service_id ID del servicio
     * @return bool true en éxito, false en error
     */
    public static function remove_staff_service($staff_id, $service_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_staff_services';
        
        $staff_id = intval($staff_id);
        $service_id = intval($service_id);
        
        if ($staff_id <= 0 || $service_id <= 0) {
            error_log("❌ [AssignmentsModel] IDs inválidos para eliminar relación staff-service: staff=$staff_id, service=$service_id");
            return false;
        }
        
        // Eliminar de la tabla pivote
        $result = $wpdb->delete(
            $table,
            [
                'staff_id' => $staff_id,
                'service_id' => $service_id
            ],
            ['%d', '%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al eliminar relación staff-service: " . $wpdb->last_error);
            return false;
        }
        
        if ($result === 0) {
            error_log("⚠️ [AssignmentsModel] No se encontró relación staff-service para eliminar: staff=$staff_id, service=$service_id");
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Relación staff-service eliminada: staff=$staff_id, service=$service_id");
        
        return true;
    }

    /**
     * Agregar relación assignment-service
     * 
     * Inserta un registro en la tabla pivote wp_aa_assignment_services.
     * 
     * @param int $assignment_id ID del assignment
     * @param int $service_id ID del servicio
     * @return bool true en éxito, false en error
     */
    public static function add_assignment_service($assignment_id, $service_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignment_services';
        
        $assignment_id = intval($assignment_id);
        $service_id = intval($service_id);
        
        if ($assignment_id <= 0 || $service_id <= 0) {
            error_log("❌ [AssignmentsModel] IDs inválidos para agregar relación assignment-service: assignment=$assignment_id, service=$service_id");
            return false;
        }
        
        // Insertar en la tabla pivote
        $result = $wpdb->insert(
            $table,
            [
                'assignment_id' => $assignment_id,
                'service_id' => $service_id
            ],
            ['%d', '%d']
        );
        
        if ($result === false) {
            // Verificar si es error de duplicado (UNIQUE constraint)
            if ($wpdb->last_error && strpos($wpdb->last_error, 'Duplicate') !== false) {
                error_log("⚠️ [AssignmentsModel] Relación assignment-service ya existe: assignment=$assignment_id, service=$service_id");
                return false;
            }
            error_log("❌ [AssignmentsModel] Error al agregar relación assignment-service: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Relación assignment-service agregada: assignment=$assignment_id, service=$service_id");
        
        return true;
    }

    /**
     * Obtener asignaciones
     * 
     * Solo retorna asignaciones con fecha actual o futura (assignment_date >= CURDATE())
     * y que no estén ocultas (is_hidden = 0).
     * Las asignaciones con fechas pasadas o marcadas como ocultas no se incluyen en los resultados.
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
                  WHERE a.assignment_date >= CURDATE()
                    AND a.is_hidden = 0
                  ORDER BY a.assignment_date ASC, a.start_time ASC";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener asignaciones: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Ocultar una asignación (establece status = 'inactive' e is_hidden = 1)
     * 
     * En lugar de eliminar el registro, se marca como oculta e inactiva
     * para mantener la integridad referencial con otras tablas.
     * 
     * @param int $id ID de la asignación a ocultar
     * @return bool true en éxito, false en error
     */
    public static function delete_assignment($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignments';
        
        // Validar ID
        $id = intval($id);
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para ocultar asignación: $id");
            return false;
        }
        
        // Actualizar status a 'inactive' e is_hidden a 1 en lugar de eliminar
        $result = $wpdb->update(
            $table,
            [
                'status' => 'inactive',
                'is_hidden' => 1
            ],
            ['id' => $id],
            ['%s', '%d'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al ocultar asignación ID $id: " . $wpdb->last_error);
            return false;
        }
        
        if ($result === 0) {
            error_log("⚠️ [AssignmentsModel] No se encontró asignación con ID $id para ocultar");
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Asignación ID $id ocultada correctamente (status = 'inactive', is_hidden = 1)");
        
        return true;
    }

    /**
     * Actualizar status de una asignación
     * 
     * @param int $id ID de la asignación
     * @param string $status Nuevo status ('active' o 'inactive')
     * @return bool true en éxito, false en error
     */
    public static function update_assignment_status($id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignments';
        
        // Validar parámetros
        $id = intval($id);
        
        if ($id <= 0) {
            error_log("❌ [AssignmentsModel] ID inválido para actualizar status de asignación: $id");
            return false;
        }
        
        // Validar que status sea 'active' o 'inactive'
        $status = sanitize_text_field($status);
        if ($status !== 'active' && $status !== 'inactive') {
            error_log("❌ [AssignmentsModel] Status inválido: $status (debe ser 'active' o 'inactive')");
            return false;
        }
        
        // Actualizar en la base de datos
        $result = $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [AssignmentsModel] Error al actualizar status de asignación ID $id: " . $wpdb->last_error);
            return false;
        }
        
        error_log("✅ [AssignmentsModel] Status de asignación ID $id actualizado: $status");
        
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
        $required = ['assignment_date', 'start_time', 'end_time', 'staff_id', 'service_area_id'];
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
            'service_key' => isset($data['service_key']) && !empty($data['service_key']) ? sanitize_text_field($data['service_key']) : '',
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

    // ============================================
    // MÉTODOS PARA DISPONIBILIDAD BASADA EN ASSIGNMENTS
    // ============================================

    /**
     * Obtener todas las fechas con asignaciones activas
     * 
     * @return array Array de fechas únicas (YYYY-MM-DD)
     */
    public static function get_assignment_dates() {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignments';
        
        $query = "SELECT DISTINCT assignment_date 
                  FROM $table 
                  WHERE status = 'active' 
                  AND assignment_date >= CURDATE()
                  ORDER BY assignment_date ASC";
        
        $results = $wpdb->get_col($query);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener fechas de asignaciones: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Obtener fechas con asignaciones para un servicio específico
     * 
     * @param string $service_key Clave del servicio
     * @param string|null $start_date Fecha inicio (YYYY-MM-DD)
     * @param string|null $end_date Fecha fin (YYYY-MM-DD)
     * @return array Array de fechas únicas
     */
    public static function get_assignment_dates_by_service($service_key, $start_date = null, $end_date = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_assignments';
        
        $where_clauses = ["status = 'active'", "service_key = %s"];
        $params = [$service_key];
        
        // Por defecto, desde hoy
        if ($start_date) {
            $where_clauses[] = "assignment_date >= %s";
            $params[] = $start_date;
        } else {
            $where_clauses[] = "assignment_date >= CURDATE()";
        }
        
        if ($end_date) {
            $where_clauses[] = "assignment_date <= %s";
            $params[] = $end_date;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT assignment_date 
             FROM $table 
             WHERE $where_sql
             ORDER BY assignment_date ASC",
            ...$params
        );
        
        $results = $wpdb->get_col($query);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener fechas por servicio: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Obtener asignaciones por servicio y fecha
     * Incluye datos enriquecidos de staff y zona
     * 
     * @param string $service_key Clave del servicio
     * @param string $date Fecha (YYYY-MM-DD)
     * @return array Array de asignaciones
     */
    public static function get_assignments_by_service_and_date($service_key, $date) {
        global $wpdb;
        $assignments_table = $wpdb->prefix . 'aa_assignments';
        $staff_table = $wpdb->prefix . 'aa_staff';
        $service_areas_table = $wpdb->prefix . 'aa_service_areas';
        
        $query = $wpdb->prepare(
            "SELECT 
                a.id,
                a.assignment_date,
                a.start_time,
                a.end_time,
                a.service_key,
                a.capacity,
                a.staff_id,
                a.service_area_id,
                s.name AS staff_name,
                sa.name AS service_area_name
             FROM $assignments_table a
             LEFT JOIN $staff_table s ON s.id = a.staff_id
             LEFT JOIN $service_areas_table sa ON sa.id = a.service_area_id
             WHERE a.service_key = %s 
             AND a.assignment_date = %s
             AND a.status = 'active'
             ORDER BY a.start_time ASC",
            $service_key,
            $date
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [AssignmentsModel] Error al obtener asignaciones por servicio y fecha: " . $wpdb->last_error);
            return [];
        }
        
        return $results ? $results : [];
    }

    /**
     * Obtener rangos ocupados para asignaciones específicas
     * Consulta reservas existentes que ocupan las asignaciones
     * 
     * @param array $assignment_ids Array de IDs de asignaciones
     * @param string $date Fecha (YYYY-MM-DD)
     * @return array Array de rangos ocupados
     */
    public static function get_busy_ranges_by_assignment_ids($assignment_ids, $date) {
        global $wpdb;
        $reservas_table = $wpdb->prefix . 'aa_reservas';
        $assignments_table = $wpdb->prefix . 'aa_assignments';
        
        if (empty($assignment_ids)) {
            return [];
        }
        
        // Sanitizar IDs
        $ids = array_map('intval', $assignment_ids);
        $ids_placeholder = implode(',', $ids);
        
        // Obtener las asignaciones primero para conocer sus horarios
        $assignments_query = "SELECT id, start_time, end_time, capacity 
                              FROM $assignments_table 
                              WHERE id IN ($ids_placeholder)";
        
        $assignments = $wpdb->get_results($assignments_query, ARRAY_A);
        
        if ($wpdb->last_error || empty($assignments)) {
            error_log("❌ [AssignmentsModel] Error al obtener asignaciones: " . $wpdb->last_error);
            return [];
        }
        
        // Por ahora, retornar los rangos de tiempo de las asignaciones
        // En el futuro, esto consultará las reservas existentes
        $busy_ranges = [];
        
        foreach ($assignments as $assignment) {
            // Consultar reservas que caen dentro del horario de esta asignación
            // IMPORTANTE: Solo considerar reservas con el mismo assignment_id
            $reservas_query = $wpdb->prepare(
                "SELECT 
                    fecha as start,
                    DATE_ADD(fecha, INTERVAL duracion MINUTE) as end,
                    servicio as title
                 FROM $reservas_table 
                 WHERE DATE(fecha) = %s
                 AND estado = 'confirmed'
                 AND assignment_id = %d
                 AND TIME(fecha) >= %s
                 AND TIME(fecha) < %s",
                $date,
                $assignment['id'],
                $assignment['start_time'],
                $assignment['end_time']
            );
            
            $reservas = $wpdb->get_results($reservas_query, ARRAY_A);
            
            if ($reservas) {
                foreach ($reservas as $reserva) {
                    $busy_ranges[] = [
                        'assignment_id' => $assignment['id'],
                        'start' => $reserva['start'],
                        'end' => $reserva['end'],
                        'title' => $reserva['title']
                    ];
                }
            }
        }
        
        return $busy_ranges;
    }
}

