<?php
/**
 * Assignments Service
 * 
 * Provides AJAX endpoints for assignments management.
 * Handles assignments operations.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Register AJAX endpoints
 */
add_action('wp_ajax_aa_get_assignments', 'aa_get_assignments');
add_action('wp_ajax_aa_delete_assignment', 'aa_delete_assignment');
add_action('wp_ajax_aa_remove_assignment', 'aa_remove_assignment');
add_action('wp_ajax_aa_get_assignment_active_reservations_count', 'aa_get_assignment_active_reservations_count');
add_action('wp_ajax_aa_update_assignment_status', 'aa_update_assignment_status');
add_action('wp_ajax_aa_create_assignment', 'aa_create_assignment');
add_action('wp_ajax_aa_add_assignment_service', 'aa_add_assignment_service');
add_action('wp_ajax_aa_get_assignment_services', 'aa_get_assignment_services');

// Endpoints para disponibilidad basada en assignments (públicos - lectura)
add_action('wp_ajax_aa_get_assignment_dates', 'aa_get_assignment_dates');
add_action('wp_ajax_aa_get_assignment_dates_by_service', 'aa_get_assignment_dates_by_service');
add_action('wp_ajax_aa_get_assignments_by_service_and_date', 'aa_get_assignments_by_service_and_date');
add_action('wp_ajax_aa_get_busy_ranges_by_assignments', 'aa_get_busy_ranges_by_assignments');
// Hooks para usuarios no logueados (frontend público)
add_action('wp_ajax_nopriv_aa_get_assignment_dates', 'aa_get_assignment_dates');
add_action('wp_ajax_nopriv_aa_get_assignment_dates_by_service', 'aa_get_assignment_dates_by_service');
add_action('wp_ajax_nopriv_aa_get_assignments_by_service_and_date', 'aa_get_assignments_by_service_and_date');
add_action('wp_ajax_nopriv_aa_get_busy_ranges_by_assignments', 'aa_get_busy_ranges_by_assignments');

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
        
        // Enriquecer cada asignación con sus servicios asociados
        foreach ($assignments as &$assignment) {
            $services = AssignmentsModel::get_assignment_services($assignment['id']);
            // Extraer solo los nombres de los servicios para el label
            $assignment['services'] = $services;
            $assignment['service_names'] = array_map(function($s) { 
                return $s['name']; 
            }, $services);
        }
        unset($assignment); // Romper referencia
        
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
 * Hide assignment (set status = 'inactive' and is_hidden = 1)
 * 
 * Instead of deleting the record, it marks it as hidden and inactive.
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
    
    $original_hidden = false;
    
    try {
        $original_assignment = AssignmentsModel::get_assignment_by_id($id);
        if (!$original_assignment) {
            wp_send_json_error([
                'message' => 'No se encontró la asignación a ocultar'
            ]);
            return;
        }
        
        $original_services = AssignmentsModel::get_assignment_services($id);
        $active_reservations = ReservationsModel::get_active_by_assignment_id($id);
        
        // Mantener comportamiento actual si no hay reservas activas
        if (empty($active_reservations)) {
            $result = AssignmentsModel::delete_assignment($id);
            
            if ($result === false) {
                wp_send_json_error([
                    'message' => 'Error al ocultar la asignación'
                ]);
                return;
            }
            
            wp_send_json_success([
                'message' => 'Asignación ocultada correctamente',
                'id' => $id
            ]);
            return;
        }
        
        // Ocultar primero la asignación original
        $hidden = AssignmentsModel::delete_assignment($id);
        
        if ($hidden === false) {
            wp_send_json_error([
                'message' => 'No se pudo ocultar la asignación original antes de fragmentar'
            ]);
            return;
        }
        
        $original_hidden = true;
        error_log("✅ [assignmentsService] Asignación original ocultada para fragmentación: $id");
        
        $new_assignment_ids = [];
        $fragmented_reservations = [];
        
        foreach ($active_reservations as $reservation) {
            $reservation_id = isset($reservation['id']) ? intval($reservation['id']) : 0;
            $reservation_fecha = isset($reservation['fecha']) ? $reservation['fecha'] : '';
            $reservation_duration = isset($reservation['duracion']) ? intval($reservation['duracion']) : 60;
            
            if ($reservation_id <= 0 || empty($reservation_fecha)) {
                error_log("❌ [assignmentsService] Fragmentación fallida: assignment original=$id, reservation id=$reservation_id, paso=validar datos de reserva");
                wp_send_json_error([
                    'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente al validar una reserva activa.'
                ]);
                return;
            }
            
            if ($reservation_duration <= 0) {
                $reservation_duration = 60;
            }
            
            try {
                $reservation_start = new DateTime($reservation_fecha);
                $reservation_end = clone $reservation_start;
                $reservation_end->modify('+' . $reservation_duration . ' minutes');
            } catch (Exception $e) {
                error_log("❌ [assignmentsService] Fragmentación fallida: assignment original=$id, reservation id=$reservation_id, paso=calcular horario, error=" . $e->getMessage());
                wp_send_json_error([
                    'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente al calcular el horario de una reserva.'
                ]);
                return;
            }
            
            $fragment_data = [
                'assignment_date' => $reservation_start->format('Y-m-d'),
                'start_time' => $reservation_start->format('H:i:s'),
                'end_time' => $reservation_end->format('H:i:s'),
                'staff_id' => intval($original_assignment['staff_id']),
                'service_area_id' => intval($original_assignment['service_area_id']),
                'service_key' => isset($original_assignment['service_key']) ? $original_assignment['service_key'] : '',
                'capacity' => isset($original_assignment['capacity']) ? intval($original_assignment['capacity']) : 1
            ];
            
            $new_assignment = AssignmentsModel::create_assignment($fragment_data);
            
            if ($new_assignment === false) {
                error_log("❌ [assignmentsService] Fragmentación fallida: assignment original=$id, reservation id=$reservation_id, paso=crear asignación fragmentada");
                wp_send_json_error([
                    'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente al crear una nueva asignación para una reserva activa.'
                ]);
                return;
            }
            
            if (isset($new_assignment['error'])) {
                error_log("❌ [assignmentsService] Fragmentación fallida: assignment original=$id, reservation id=$reservation_id, paso=crear asignación fragmentada, detalle=" . $new_assignment['error']);
                wp_send_json_error([
                    'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente: ' . $new_assignment['error']
                ]);
                return;
            }
            
            $new_assignment_id = isset($new_assignment['id']) ? intval($new_assignment['id']) : 0;
            if ($new_assignment_id <= 0) {
                error_log("❌ [assignmentsService] Fragmentación fallida: assignment original=$id, reservation id=$reservation_id, paso=obtener nuevo assignment_id");
                wp_send_json_error([
                    'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente al obtener el ID de una nueva asignación.'
                ]);
                return;
            }
            
            foreach ($original_services as $service) {
                $service_id = isset($service['id']) ? intval($service['id']) : 0;
                
                if ($service_id <= 0) {
                    error_log("❌ [assignmentsService] Fragmentación fallida: assignment original=$id, reservation id=$reservation_id, paso=validar servicio original");
                    wp_send_json_error([
                        'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente al copiar los servicios originales.'
                    ]);
                    return;
                }
                
                $service_added = AssignmentsModel::add_assignment_service($new_assignment_id, $service_id);
                if ($service_added === false) {
                    error_log("❌ [assignmentsService] Fragmentación fallida: assignment original=$id, reservation id=$reservation_id, paso=copiar servicio, service id=$service_id, new assignment=$new_assignment_id");
                    wp_send_json_error([
                        'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente al copiar los servicios de una nueva asignación.'
                    ]);
                    return;
                }
            }
            
            $reservation_updated = ReservationsModel::update_assignment_id($reservation_id, $new_assignment_id);
            if ($reservation_updated === false) {
                error_log("❌ [assignmentsService] Fragmentación fallida: assignment original=$id, reservation id=$reservation_id, paso=reasignar reserva, new assignment=$new_assignment_id");
                wp_send_json_error([
                    'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente al reasignar una reserva activa.'
                ]);
                return;
            }
            
            $new_assignment_ids[] = $new_assignment_id;
            $fragmented_reservations[] = $reservation_id;
        }
        
        wp_send_json_success([
            'message' => 'Asignación ocultada y fragmentada correctamente',
            'id' => $id,
            'fragmented_reservations_count' => count($fragmented_reservations),
            'fragmented_reservation_ids' => $fragmented_reservations,
            'new_assignment_ids' => $new_assignment_ids
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al ocultar/fraccionar asignación $id: " . $e->getMessage());
        
        if ($original_hidden) {
            wp_send_json_error([
                'message' => 'La asignación original fue ocultada, pero la fragmentación no terminó correctamente: ' . $e->getMessage()
            ]);
            return;
        }
        
        wp_send_json_error([
            'message' => 'Error al ocultar la asignación: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get active reservations count for an assignment.
 *
 * Used by the admin UI to build a contextual confirmation before removing
 * an assignment and cancelling its reservations.
 *
 * @return void JSON response
 */
function aa_get_assignment_active_reservations_count() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }

    if (!isset($_POST['id'])) {
        wp_send_json_error(['message' => 'El ID de la asignación es requerido']);
        return;
    }

    $id = intval($_POST['id']);

    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }

    try {
        $active_reservations = ReservationsModel::get_active_by_assignment_id($id);

        wp_send_json_success([
            'id' => $id,
            'active_reservations_count' => count($active_reservations)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener citas activas para asignación $id: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al consultar las citas activas: ' . $e->getMessage()
        ]);
    }
}

/**
 * Remove assignment without fragmentation.
 *
 * Cancels all active reservations associated to the assignment and then
 * hides the assignment (status = inactive, is_hidden = 1).
 *
 * @return void JSON response
 */
function aa_remove_assignment() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }

    if (!isset($_POST['id'])) {
        wp_send_json_error(['message' => 'El ID de la asignación es requerido']);
        return;
    }

    $id = intval($_POST['id']);

    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }

    if (!function_exists('aa_cancel_reservation_internal')) {
        error_log("❌ [assignmentsService] No se encontró aa_cancel_reservation_internal para eliminar asignación $id");
        wp_send_json_error([
            'message' => 'No está disponible la función interna para cancelar citas.'
        ]);
        return;
    }

    try {
        $assignment = AssignmentsModel::get_assignment_by_id($id);
        if (!$assignment) {
            wp_send_json_error([
                'message' => 'No se encontró la asignación a eliminar'
            ]);
            return;
        }

        $active_reservations = ReservationsModel::get_active_by_assignment_id($id);
        $cancelled_reservation_ids = [];

        foreach ($active_reservations as $reservation) {
            $reservation_id = isset($reservation['id']) ? intval($reservation['id']) : 0;

            if ($reservation_id <= 0) {
                error_log("❌ [assignmentsService] Eliminación fallida: assignment=$id, reservation id inválido");
                wp_send_json_error([
                    'message' => 'Se encontró una cita activa con ID inválido.'
                ]);
                return;
            }

            $cancel_result = aa_cancel_reservation_internal($reservation_id);

            if (empty($cancel_result['success'])) {
                $message = isset($cancel_result['message']) ? $cancel_result['message'] : 'No se pudo cancelar una de las citas activas.';
                error_log("❌ [assignmentsService] Eliminación fallida: assignment=$id, reservation=$reservation_id, error=$message");
                wp_send_json_error([
                    'message' => 'No se pudo completar la eliminación porque falló la cancelación de una cita activa: ' . $message
                ]);
                return;
            }

            $cancelled_reservation_ids[] = $reservation_id;
        }

        $hidden = AssignmentsModel::delete_assignment($id);
        if ($hidden === false) {
            error_log("❌ [assignmentsService] Error al ocultar asignación $id después de cancelar citas");
            wp_send_json_error([
                'message' => 'Las citas fueron canceladas, pero no se pudo ocultar la asignación.'
            ]);
            return;
        }

        error_log("✅ [assignmentsService] Asignación eliminada sin fragmentación: $id. Citas canceladas: " . count($cancelled_reservation_ids));

        wp_send_json_success([
            'message' => 'Asignación eliminada correctamente',
            'id' => $id,
            'cancelled_reservations_count' => count($cancelled_reservation_ids),
            'cancelled_reservation_ids' => $cancelled_reservation_ids
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al eliminar asignación $id sin fragmentación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al eliminar la asignación: ' . $e->getMessage()
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
    $required_fields = ['assignment_date', 'start_time', 'end_time', 'staff_id', 'service_area_id'];
    
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
        'service_key' => isset($_POST['service_key']) && !empty($_POST['service_key']) ? sanitize_text_field($_POST['service_key']) : '',
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

// ============================================
// ENDPOINTS PARA DISPONIBILIDAD BASADA EN ASSIGNMENTS
// ============================================

/**
 * Get all assignment dates
 * 
 * Returns all unique dates that have active assignments.
 * 
 * @return void JSON response
 */
function aa_get_assignment_dates() {
    try {
        $dates = AssignmentsModel::get_assignment_dates();
        
        wp_send_json_success([
            'dates' => $dates,
            'count' => count($dates)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener fechas de asignaciones: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener las fechas: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get assignment dates by service
 * 
 * Returns dates that have assignments for a specific service.
 * 
 * @return void JSON response
 */
function aa_get_assignment_dates_by_service() {
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
    
    // Validar: debe venir service_id (int > 0) o service_key (string no vacío)
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $service_key = isset($_POST['service_key']) ? sanitize_text_field($_POST['service_key']) : '';
    
    if ($service_id <= 0 && empty($service_key)) {
        wp_send_json_error(['message' => 'Se requiere service_id o service_key']);
        return;
    }
    
    try {
        $dates = [];
        $response_data = [];
        
        // Si viene service_id, usar el nuevo método con tabla pivote
        if ($service_id > 0) {
            $dates = AssignmentsModel::get_assignment_dates_by_service_id($service_id, $start_date, $end_date);
            $response_data = [
                'service_id' => $service_id,
                'dates' => $dates,
                'count' => count($dates)
            ];
        } else {
            // Legacy / fixed: usar service_key (sin filtro public_calendar)
            $dates = AssignmentsModel::get_assignment_dates_by_service($service_key, $start_date, $end_date);
            $response_data = [
                'service_key' => $service_key,
                'dates' => $dates,
                'count' => count($dates)
            ];
        }
        
        wp_send_json_success($response_data);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener fechas por servicio: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener las fechas: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get assignments by service and date
 * 
 * Returns all assignments for a specific service on a specific date.
 * 
 * @return void JSON response
 */
function aa_get_assignments_by_service_and_date() {
    // Validar campo date (siempre requerido)
    if (!isset($_POST['date']) || empty($_POST['date'])) {
        wp_send_json_error(['message' => 'El campo date es requerido']);
        return;
    }
    
    $date = sanitize_text_field($_POST['date']);
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
        return;
    }
    
    // Validar: debe venir service_id (int > 0) o service_key (string no vacío)
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $service_key = isset($_POST['service_key']) ? sanitize_text_field($_POST['service_key']) : '';
    
    if ($service_id <= 0 && empty($service_key)) {
        wp_send_json_error(['message' => 'Se requiere service_id o service_key']);
        return;
    }
    
    try {
        $assignments = [];
        $response_data = ['date' => $date];
        
        // Si viene service_id, usar el nuevo método con tabla pivote
        if ($service_id > 0) {
            $assignments = AssignmentsModel::get_assignments_by_service_id_and_date($service_id, $date);
            $response_data['service_id'] = $service_id;
        } else {
            // Legacy / fixed: usar service_key (sin filtro public_calendar)
            $assignments = AssignmentsModel::get_assignments_by_service_and_date($service_key, $date);
            $response_data['service_key'] = $service_key;
        }
        
        $response_data['assignments'] = $assignments;
        $response_data['count'] = count($assignments);
        
        wp_send_json_success($response_data);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener asignaciones por servicio y fecha: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener las asignaciones: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get busy ranges by assignment IDs
 * 
 * Returns busy time ranges for specific assignments on a date.
 * 
 * @return void JSON response
 */
function aa_get_busy_ranges_by_assignments() {
    // Validar campos requeridos
    if (!isset($_POST['assignment_ids']) || empty($_POST['assignment_ids'])) {
        wp_send_json_error(['message' => 'El campo assignment_ids es requerido']);
        return;
    }
    
    if (!isset($_POST['date']) || empty($_POST['date'])) {
        wp_send_json_error(['message' => 'El campo date es requerido']);
        return;
    }
    
    // Parsear assignment_ids (puede venir como JSON string)
    $assignment_ids_raw = $_POST['assignment_ids'];
    if (is_string($assignment_ids_raw)) {
        $assignment_ids = json_decode($assignment_ids_raw, true);
    } else {
        $assignment_ids = $assignment_ids_raw;
    }
    
    if (!is_array($assignment_ids) || empty($assignment_ids)) {
        wp_send_json_error(['message' => 'assignment_ids debe ser un array no vacío']);
        return;
    }
    
    $date = sanitize_text_field($_POST['date']);
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
        return;
    }
    
    try {
        $busy_ranges = AssignmentsModel::get_busy_ranges_by_assignment_ids($assignment_ids, $date);
        
        wp_send_json_success([
            'assignment_ids' => $assignment_ids,
            'date' => $date,
            'busy_ranges' => $busy_ranges,
            'count' => count($busy_ranges)
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener busy ranges: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener los rangos ocupados: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update assignment status
 * 
 * Updates the status of an assignment (active/inactive).
 * 
 * @return void JSON response
 */
function aa_update_assignment_status() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        wp_send_json_error(['message' => 'Faltan parámetros requeridos']);
        return;
    }
    
    $id = intval($_POST['id']);
    $status = sanitize_text_field($_POST['status']);
    
    // Validar ID
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    // Validar que status sea 'active' o 'inactive'
    if ($status !== 'active' && $status !== 'inactive') {
        wp_send_json_error(['message' => 'Status inválido. Debe ser "active" o "inactive"']);
        return;
    }
    
    try {
        // Llamar al modelo para actualizar el status
        $result = AssignmentsModel::update_assignment_status($id, $status);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al actualizar el status de la asignación'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Status de la asignación actualizado correctamente',
            'id' => $id,
            'status' => $status
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al actualizar status de asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al actualizar el status: ' . $e->getMessage()
        ]);
    }
}

/**
 * Add assignment service
 * 
 * Adds a relationship between an assignment and a service in the pivot table.
 * 
 * @return void JSON response
 */
function aa_add_assignment_service() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['assignment_id']) || !isset($_POST['service_id'])) {
        wp_send_json_error(['message' => 'Faltan parámetros requeridos']);
        return;
    }
    
    $assignment_id = intval($_POST['assignment_id']);
    $service_id = intval($_POST['service_id']);
    
    // Validar IDs
    if ($assignment_id <= 0 || $service_id <= 0) {
        wp_send_json_error(['message' => 'IDs inválidos']);
        return;
    }
    
    try {
        // Llamar al modelo para agregar la relación
        $result = AssignmentsModel::add_assignment_service($assignment_id, $service_id);
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Error al agregar el servicio a la asignación'
            ]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Servicio agregado a la asignación correctamente',
            'assignment_id' => $assignment_id,
            'service_id' => $service_id
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al agregar servicio a asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al agregar el servicio: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get assignment services
 * 
 * Returns list of services assigned to a specific assignment.
 * 
 * @return void JSON response
 */
function aa_get_assignment_services() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción']);
        return;
    }
    
    // Leer y validar datos POST
    if (!isset($_POST['assignment_id']) || empty($_POST['assignment_id'])) {
        wp_send_json_error(['message' => 'El ID de la asignación es requerido']);
        return;
    }
    
    $assignment_id = intval($_POST['assignment_id']);
    
    // Validar ID
    if ($assignment_id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
        return;
    }
    
    try {
        // Llamar al modelo para obtener los servicios
        $services = AssignmentsModel::get_assignment_services($assignment_id);
        
        wp_send_json_success([
            'services' => $services,
            'count' => count($services),
            'assignment_id' => $assignment_id
        ]);
    } catch (Exception $e) {
        error_log("❌ [assignmentsService] Error al obtener servicios de asignación: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al obtener los servicios: ' . $e->getMessage()
        ]);
    }
}

