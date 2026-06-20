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
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 1 AND is_hidden = 0");

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
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 1 AND is_hidden = 0");

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
               AND st.is_hidden = 0
               AND svc.active = 1
               AND svc.is_hidden = 0"
        );

        if ($wpdb->last_error) {
            error_log('[AssignmentsRepository] Error al contar staff activo con servicios activos: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }

    /**
     * IDs de personal activo (active = 1).
     *
     * @return array<int>
     */
    public static function list_active_staff_ids() {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_staff';
        $ids = $wpdb->get_col("SELECT id FROM {$table} WHERE active = 1 AND is_hidden = 0 ORDER BY id ASC");

        if ($wpdb->last_error) {
            error_log('[AssignmentsRepository] Error al listar staff activo: ' . $wpdb->last_error);
            return [];
        }

        if (!is_array($ids) || $ids === []) {
            return [];
        }

        return array_map('intval', $ids);
    }

    /**
     * IDs de servicios activos y no ocultos (mismo criterio que count_active_services).
     *
     * @return array<int>
     */
    public static function list_assignable_service_ids() {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_services';
        $ids = $wpdb->get_col(
            "SELECT id FROM {$table} WHERE active = 1 AND is_hidden = 0 ORDER BY id ASC"
        );

        if ($wpdb->last_error) {
            error_log('[AssignmentsRepository] Error al listar servicios asignables: ' . $wpdb->last_error);
            return [];
        }

        if (!is_array($ids) || $ids === []) {
            return [];
        }

        return array_map('intval', $ids);
    }

    /**
     * Indica si un servicio cumple el criterio de asignable (activo y no oculto).
     *
     * @param int $service_id
     * @return bool
     */
    public static function is_assignable_service($service_id) {
        $service_id = (int) $service_id;

        if ($service_id <= 0) {
            return false;
        }

        return in_array($service_id, self::list_assignable_service_ids(), true);
    }

    /**
     * Garantiza un vínculo staff-servicio sin duplicar filas.
     *
     * @param int $staff_id
     * @param int $service_id
     * @return string 'created'|'skipped'|'failed'
     */
    public static function ensure_staff_service_link($staff_id, $service_id) {
        $staff_id = (int) $staff_id;
        $service_id = (int) $service_id;

        if ($staff_id <= 0 || $service_id <= 0) {
            return 'failed';
        }

        $existing = self::get_staff_service_ids($staff_id);

        if (in_array($service_id, $existing, true)) {
            return 'skipped';
        }

        $result = self::add_staff_service($staff_id, $service_id);

        if ($result === true) {
            return 'created';
        }

        $existing_after = self::get_staff_service_ids($staff_id);

        if (in_array($service_id, $existing_after, true)) {
            return 'skipped';
        }

        return 'failed';
    }
}
