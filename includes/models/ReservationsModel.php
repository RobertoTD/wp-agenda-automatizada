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
}