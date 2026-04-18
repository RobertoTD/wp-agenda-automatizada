<?php
/**
 * Modelo: Reservaciones
 * 
 * Responsable de:
 * - Consultas a la tabla wp_aa_reservas
 * - Obtención de slots ocupados localmente
 * - Formateo de datos para disponibilidad
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Models
 */

if (!defined('ABSPATH')) exit;

class ReservationsModel {

    /**
     * Obtener slots ocupados desde la base de datos local (SOLO FIXED)
     * 
     * Solo retorna reservas con assignment_id IS NULL (reservas fixed/legacy).
     * Las reservas con assignment_id se manejan por el flujo de assignments
     * (AABusyRangesAssignments.getBusyRangesByAssignments).
     * 
     * @return array Array de slots ocupados con formato [start, end]
     */
    public static function get_internal_busy_slots() {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        // 🔹 Consultar solo citas confirmadas FIXED (assignment_id IS NULL) que NO han terminado
        // Usar la duración real de cada reserva (columna duracion), no aa_slot_duration
        $now = aa_get_current_datetime();
        
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                fecha as start,
                DATE_ADD(fecha, INTERVAL duracion MINUTE) as end,
                servicio,
                nombre
            FROM $table 
            WHERE estado = 'confirmed'
            AND assignment_id IS NULL
            AND DATE_ADD(fecha, INTERVAL duracion MINUTE) >= %s
            ORDER BY fecha ASC
        ", $now));
        
        if ($wpdb->last_error) {
            error_log("❌ [ReservationsModel] Error en consulta: " . $wpdb->last_error);
            return [];
        }
        
        error_log("✅ [ReservationsModel] Encontradas " . count($rows) . " citas confirmadas FIXED (sin assignment)");
        
        // 🔹 Formatear resultados en estructura compatible con Google Calendar
        return array_map(function($row) {
            return [
                'start' => $row->start,
                'end'   => $row->end,
                'title' => $row->servicio ?? 'Cita',
                'attendee' => $row->nombre ?? 'Sin nombre'
            ];
        }, $rows);
    }
    
    /**
     * Obtener todas las citas confirmadas (para debug)
     * 
     * @return array
     */
    public static function get_all_confirmed() {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        $rows = $wpdb->get_results("
            SELECT * 
            FROM $table 
            WHERE estado = 'confirmed'
            ORDER BY fecha DESC
            LIMIT 50
        ");
        
        return $rows ?? [];
    }
    
    /**
     * Contar citas confirmadas (todas)
     * 
     * @return int
     */
    public static function count_confirmed() {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $table 
            WHERE estado = 'confirmed'
        ");
        
        return intval($count);
    }

    /**
     * Contar citas confirmadas FIXED (assignment_id IS NULL)
     * 
     * Solo cuenta reservas sin assignment (flujo legacy/fixed).
     * 
     * @return int
     */
    public static function count_confirmed_fixed() {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $table 
            WHERE estado = 'confirmed'
            AND assignment_id IS NULL
        ");
        
        return intval($count);
    }

    /**
     * Obtener reservas por un array de IDs.
     *
     * @param array $ids Array de IDs (int) de reservas.
     * @return array Rows con id, estado, fecha, nombre, telefono, correo, servicio, duracion, assignment_id
     */
    public static function get_by_ids(array $ids) {
        if (empty($ids)) return [];

        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, estado, fecha, nombre, telefono, correo, servicio, duracion, assignment_id
             FROM $table
             WHERE id IN ($placeholders)",
            ...$ids
        ));

        if ($wpdb->last_error) {
            error_log("❌ [ReservationsModel] Error en get_by_ids: " . $wpdb->last_error);
            return [];
        }

        return $rows ?? [];
    }

    /**
     * Obtener reservas activas de una asignación
     * 
     * Retorna reservas con estado pending o confirmed asociadas
     * a una asignación específica.
     * 
     * @param int $assignment_id ID de la asignación
     * @return array
     */
    public static function get_active_by_assignment_id($assignment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        $assignment_id = intval($assignment_id);
        
        if ($assignment_id <= 0) {
            error_log("❌ [ReservationsModel] ID inválido para obtener reservas activas por asignación: $assignment_id");
            return [];
        }
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, estado, fecha, duracion, assignment_id, nombre, telefono, correo, servicio
             FROM $table
             WHERE assignment_id = %d
             AND estado IN ('pending', 'confirmed')
             ORDER BY fecha ASC",
            $assignment_id
        ), ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("❌ [ReservationsModel] Error al obtener reservas activas de asignación $assignment_id: " . $wpdb->last_error);
            return [];
        }
        
        error_log("✅ [ReservationsModel] Encontradas " . count($rows) . " reservas activas para asignación $assignment_id");
        
        return $rows ? $rows : [];
    }

    /**
     * Reasignar una reserva a un nuevo assignment_id
     * 
     * @param int $reservation_id ID de la reserva
     * @param int $new_assignment_id Nuevo ID de asignación
     * @return bool true en éxito, false en error
     */
    public static function update_assignment_id($reservation_id, $new_assignment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        $reservation_id = intval($reservation_id);
        $new_assignment_id = intval($new_assignment_id);
        
        if ($reservation_id <= 0 || $new_assignment_id <= 0) {
            error_log("❌ [ReservationsModel] IDs inválidos para reasignar reserva: reserva=$reservation_id, assignment=$new_assignment_id");
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            ['assignment_id' => $new_assignment_id],
            ['id' => $reservation_id],
            ['%d'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("❌ [ReservationsModel] Error al reasignar reserva $reservation_id a assignment $new_assignment_id: " . $wpdb->last_error);
            return false;
        }
        
        if ($result === 0) {
            error_log("⚠️ [ReservationsModel] No se actualizó assignment_id para reserva $reservation_id");
            return false;
        }
        
        error_log("✅ [ReservationsModel] Reserva $reservation_id reasignada a assignment $new_assignment_id");
        
        return true;
    }

    /**
     * Obtener citas pendientes que coinciden en fecha/hora (para cancelación automática)
     * 
     * @deprecated Usar get_pending_conflicts_overlapping() para detección por solapamiento
     * @param string $fecha DateTime string (Y-m-d H:i:s)
     * @param int $exclude_id ID de la cita que estamos confirmando (para no cancelarla a ella misma)
     * @return array
     */
    public static function get_pending_conflicts($fecha, $exclude_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        // Buscamos citas PENDIENTES que tengan EXACTAMENTE la misma fecha de inicio
        // y que no sean la cita actual.
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT id, nombre, correo, fecha 
            FROM $table 
            WHERE estado = 'pending' 
            AND fecha = %s 
            AND id != %d
        ", $fecha, $exclude_id));
        
        return $rows ?? [];
    }

    /**
     * Obtener citas pendientes que se solapan en tiempo con un rango dado
     * 
     * Usa la regla de overlap: startA < endB AND endA > startB
     * (misma lógica que DateUtils.hasEnoughFreeTime en JS)
     * 
     * Además filtra por assignment_id:
     * - Si $assignment_id es NULL => solo cancela pending con assignment_id IS NULL
     * - Si $assignment_id tiene valor => solo cancela pending con ese assignment_id
     * 
     * @param string $start Inicio de la reserva confirmada (Y-m-d H:i:s)
     * @param string $end Fin de la reserva confirmada (Y-m-d H:i:s)
     * @param int|null $assignment_id ID del assignment (o null para FIXED)
     * @param int $exclude_id ID de la cita que estamos confirmando (para no cancelarla)
     * @return array Rows con id, nombre, correo, fecha, duracion, assignment_id
     */
    public static function get_pending_conflicts_overlapping($start, $end, $assignment_id, $exclude_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        // Overlap: fecha_pending < end_confirm AND (fecha_pending + duracion) > start_confirm
        // Equivalente a: startA < endB AND endA > startB
        
        if ($assignment_id === null) {
            // FIXED: solo cancelar pending con assignment_id IS NULL
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT id, nombre, correo, fecha, duracion, assignment_id 
                FROM $table 
                WHERE estado = 'pending' 
                AND id != %d
                AND assignment_id IS NULL
                AND fecha < %s
                AND DATE_ADD(fecha, INTERVAL duracion MINUTE) > %s
            ", $exclude_id, $end, $start));
        } else {
            // ASSIGNMENT: solo cancelar pending con el mismo assignment_id
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT id, nombre, correo, fecha, duracion, assignment_id 
                FROM $table 
                WHERE estado = 'pending' 
                AND id != %d
                AND assignment_id = %d
                AND fecha < %s
                AND DATE_ADD(fecha, INTERVAL duracion MINUTE) > %s
            ", $exclude_id, $assignment_id, $end, $start));
        }
        
        if ($wpdb->last_error) {
            error_log("❌ [ReservationsModel] Error en get_pending_conflicts_overlapping: " . $wpdb->last_error);
            return [];
        }
        
        return $rows ?? [];
    }

    /**
     * Verificar si existe una cita confirmed solapada para el mismo staff real.
     *
     * El staff se resuelve a través de aa_assignments (assignment_id -> staff_id).
     * Solo considera reservas confirmed con assignment_id resoluble.
     *
     * @param string $start Inicio de la reserva a confirmar (Y-m-d H:i:s)
     * @param string $end Fin de la reserva a confirmar (Y-m-d H:i:s)
     * @param int $staff_id ID real del staff a validar
     * @param int $exclude_id ID de la reserva que estamos confirmando
     * @return bool true si existe conflicto, false si no
     */
    public static function has_confirmed_staff_overlap($start, $end, $staff_id, $exclude_id) {
        global $wpdb;
        $reservas_table = $wpdb->prefix . 'aa_reservas';
        $assignments_table = $wpdb->prefix . 'aa_assignments';

        $staff_id = intval($staff_id);
        $exclude_id = intval($exclude_id);

        if ($staff_id <= 0 || $exclude_id <= 0 || empty($start) || empty($end)) {
            return false;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM $reservas_table r
             INNER JOIN $assignments_table a ON a.id = r.assignment_id
             WHERE r.estado = 'confirmed'
             AND r.id != %d
             AND a.staff_id = %d
             AND r.fecha < %s
             AND DATE_ADD(r.fecha, INTERVAL r.duracion MINUTE) > %s",
            $exclude_id,
            $staff_id,
            $end,
            $start
        ));

        if ($wpdb->last_error) {
            error_log("❌ [ReservationsModel] Error en has_confirmed_staff_overlap: " . $wpdb->last_error);
            return false;
        }

        return intval($count) > 0;
    }

    /**
     * Obtener reservas confirmed que solapan en tiempo dentro de una zona.
     *
     * Solo consulta. Sin lógica de negocio. La zona se resuelve via
     * assignment_id -> aa_assignments.service_area_id.
     *
     * Regla de overlap (idéntica a get_pending_conflicts_overlapping):
     *   r.fecha < $end AND DATE_ADD(r.fecha, INTERVAL r.duracion MINUTE) > $start.
     *
     * @param string $start           Inicio del rango a evaluar (Y-m-d H:i:s)
     * @param string $end             Fin del rango a evaluar (Y-m-d H:i:s)
     * @param int    $service_area_id ID de la zona
     * @return array[] Filas con id, fecha, duracion, assignment_id
     */
    public static function get_confirmed_overlap_in_area($start, $end, $service_area_id) {
        global $wpdb;
        $reservas_table = $wpdb->prefix . 'aa_reservas';
        $assignments_table = $wpdb->prefix . 'aa_assignments';

        $service_area_id = intval($service_area_id);

        if ($service_area_id <= 0 || empty($start) || empty($end)) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.fecha, r.duracion, r.assignment_id
             FROM $reservas_table r
             INNER JOIN $assignments_table a ON a.id = r.assignment_id
             WHERE r.estado = 'confirmed'
             AND a.service_area_id = %d
             AND r.fecha < %s
             AND DATE_ADD(r.fecha, INTERVAL r.duracion MINUTE) > %s
             ORDER BY r.fecha ASC",
            $service_area_id,
            $end,
            $start
        ), ARRAY_A);

        if ($wpdb->last_error) {
            error_log("❌ [ReservationsModel] Error en get_confirmed_overlap_in_area: " . $wpdb->last_error);
            return [];
        }

        return $rows ? $rows : [];
    }

    /**
     * Obtener citas pending que se solapan en tiempo para el mismo staff real.
     *
     * El staff se resuelve por assignment_id -> aa_assignments.staff_id.
     * Ignora assignment_id y zona; solo importa el staff real + overlap temporal.
     *
     * @param string $start Inicio de la reserva confirmada (Y-m-d H:i:s)
     * @param string $end Fin de la reserva confirmada (Y-m-d H:i:s)
     * @param int $staff_id ID real del staff
     * @param int $exclude_id ID de la reserva que estamos confirmando
     * @return array Rows con id, nombre, correo, fecha, duracion, assignment_id
     */
    public static function get_pending_conflicts_for_staff_overlap($start, $end, $staff_id, $exclude_id) {
        global $wpdb;
        $reservas_table = $wpdb->prefix . 'aa_reservas';
        $assignments_table = $wpdb->prefix . 'aa_assignments';

        $staff_id = intval($staff_id);
        $exclude_id = intval($exclude_id);

        if ($staff_id <= 0 || $exclude_id <= 0 || empty($start) || empty($end)) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.nombre, r.correo, r.fecha, r.duracion, r.assignment_id
             FROM $reservas_table r
             INNER JOIN $assignments_table a ON a.id = r.assignment_id
             WHERE r.estado = 'pending'
             AND r.id != %d
             AND a.staff_id = %d
             AND r.fecha < %s
             AND DATE_ADD(r.fecha, INTERVAL r.duracion MINUTE) > %s",
            $exclude_id,
            $staff_id,
            $end,
            $start
        ));

        if ($wpdb->last_error) {
            error_log("❌ [ReservationsModel] Error en get_pending_conflicts_for_staff_overlap: " . $wpdb->last_error);
            return [];
        }

        return $rows ?? [];
    }
}