<?php
/**
 * Assignments Repository — capa canónica de acceso SQL a tablas de assignments.
 *
 * Extiende `AssignmentsModel` mientras dura la migración por contagio,
 * de modo que todos los métodos estáticos del Model están disponibles
 * vía esta clase sin tocar consumidores existentes:
 *
 *   AssignmentsRepository::get_service_areas()  // funciona vía herencia
 *
 * Reglas (veda):
 *   - Cualquier método SQL NUEVO se añade aquí, NO en AssignmentsModel.
 *   - Cualquier método existente del Model que mezcle SQL + regla de
 *     negocio (ej. has_confirmed_staff_overlap, get_pending_conflicts_*)
 *     se DESCOMPONE cuando se toque por otra razón:
 *       · La parte SQL pura se reescribe aquí.
 *       · La regla "qué cuenta como overlap/conflicto" se mueve a un
 *         Domain Service en includes/domain/.
 *   - NO se permite agregar `if` de negocio en este archivo.
 *
 * Cuando todos los consumidores hayan migrado a `AssignmentsRepository`,
 * el archivo `AssignmentsModel.php` se podrá vaciar y, finalmente, eliminar.
 *
 * Ver:
 *  - docs/00-paradigm-cheatsheet.md (capa repositories)
 *  - docs/02-architecture-principles.md (sección "Repositories Layer")
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../models/AssignmentsModel.php';

class AssignmentsRepository extends AssignmentsModel {
    /**
     * Cuenta profesionales activos.
     *
     * Query pura para prerequisitos de reserva; la decisión de negocio
     * vive fuera del repository.
     *
     * @return int
     */
    public static function count_active_staff() {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_staff';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 1");

        if ($wpdb->last_error) {
            error_log('[AssignmentsRepository] Error al contar staff activo: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }

    /**
     * Cuenta servicios activos y no ocultos.
     *
     * Query pura para prerequisitos de reserva; la decisión de negocio
     * vive fuera del repository.
     *
     * @return int
     */
    public static function count_active_services() {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_services';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 1 AND is_hidden = 0");

        if ($wpdb->last_error) {
            error_log('[AssignmentsRepository] Error al contar servicios activos: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }

    /**
     * Cuenta zonas de atención activas.
     *
     * Query pura para prerequisitos de reserva; la decisión de negocio
     * vive fuera del repository.
     *
     * @return int
     */
    public static function count_active_service_areas() {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_service_areas';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 1");

        if ($wpdb->last_error) {
            error_log('[AssignmentsRepository] Error al contar zonas de atención activas: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }

    /**
     * Cuenta profesionales activos con al menos un servicio activo asignado.
     *
     * Query pura para prerequisitos de reserva; la decisión de negocio
     * vive fuera del repository.
     *
     * @return int
     */
    public static function count_active_staff_with_active_services() {
        global $wpdb;

        $staff_table = $wpdb->prefix . 'aa_staff';
        $staff_services_table = $wpdb->prefix . 'aa_staff_services';
        $services_table = $wpdb->prefix . 'aa_services';

        $count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT st.id)
             FROM {$staff_table} st
             INNER JOIN {$staff_services_table} ss ON ss.staff_id = st.id
             INNER JOIN {$services_table} svc ON svc.id = ss.service_id
             WHERE st.active = 1
               AND svc.active = 1
               AND svc.is_hidden = 0"
        );

        if ($wpdb->last_error) {
            error_log('[AssignmentsRepository] Error al contar staff activo con servicios activos: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }
}
