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
     * Obtener slots ocupados desde la base de datos local
     * 
     * @return array Array de slots ocupados con formato [start, end]
     */
    public static function get_internal_busy_slots() {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        
        // 🔹 Obtener slot_duration configurado
        $slot_duration = intval(get_option('aa_slot_duration', 60));
        
        // 🔹 Consultar solo citas confirmadas que NO han terminado
        $now = aa_get_current_datetime();
        
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                fecha as start,
                DATE_ADD(fecha, INTERVAL %d MINUTE) as end,
                servicio,
                nombre
            FROM $table 
            WHERE estado = 'confirmed'
            AND DATE_ADD(fecha, INTERVAL %d MINUTE) >= %s
            ORDER BY fecha ASC
        ", $slot_duration, $slot_duration, $now));
        
        if ($wpdb->last_error) {
            error_log("❌ [ReservationsModel] Error en consulta: " . $wpdb->last_error);
            return [];
        }
        
        error_log("✅ [ReservationsModel] Encontradas " . count($rows) . " citas confirmadas");
        
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
     * Contar citas confirmadas
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
     * Obtener citas pendientes que coinciden en fecha/hora (para cancelación automática)
     * 
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
}