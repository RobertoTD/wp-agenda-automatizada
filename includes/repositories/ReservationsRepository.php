<?php
/**
 * Reservations Repository — capa canónica de acceso SQL a la tabla de reservas.
 *
 * Extiende `ReservationsModel` mientras dura la migración por contagio,
 * de modo que todos los métodos estáticos del Model están disponibles
 * vía esta clase sin tocar consumidores existentes:
 *
 *   ReservationsRepository::get_internal_busy_slots()  // funciona vía herencia
 *
 * Reglas (veda):
 *   - Cualquier método SQL NUEVO se añade aquí, NO en ReservationsModel.
 *   - Cualquier método existente del Model que mezcle SQL + regla de
 *     negocio (ej. get_confirmed_overlap_in_area cuando aplica criterios
 *     que no sean SQL puro) se DESCOMPONE cuando se toque por otra razón:
 *       · La parte SQL pura se reescribe aquí.
 *       · La regla "qué cuenta como overlap/ocupación" se mueve a un
 *         Domain Service en includes/domain/availability/.
 *   - NO se permite agregar `if` de negocio en este archivo.
 *
 * Cuando todos los consumidores hayan migrado a `ReservationsRepository`,
 * el archivo `ReservationsModel.php` se podrá vaciar y, finalmente, eliminar.
 *
 * Ver:
 *  - docs/00-paradigm-cheatsheet.md (capa repositories)
 *  - docs/02-architecture-principles.md (sección "Repositories Layer")
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../models/ReservationsModel.php';

class ReservationsRepository extends ReservationsModel {
    /** @var callable|null Override for acceptance tests only. */
    private static $probe_has_created_reservations_override = null;

    /**
     * @internal Acceptance tests only.
     *
     * @param callable|null $override Debe devolver array{ok:bool,exists:bool}.
     */
    public static function set_probe_has_created_reservations_override_for_tests(?callable $override): void {
        self::$probe_has_created_reservations_override = $override;
    }

    /**
     * Comprueba si existe al menos una fila en aa_reservas.
     *
     * @return array{ok:bool,exists:bool}
     */
    public static function probe_has_created_reservations(): array {
        if (self::$probe_has_created_reservations_override !== null) {
            $result = call_user_func(self::$probe_has_created_reservations_override);

            return is_array($result) ? $result : ['ok' => false, 'exists' => false];
        }

        global $wpdb;

        $table = $wpdb->prefix . 'aa_reservas';
        $row = $wpdb->get_var("SELECT 1 FROM {$table} LIMIT 1");

        if ($wpdb->last_error) {
            error_log('[ReservationsRepository] Error al comprobar existencia de reservas: ' . $wpdb->last_error);

            return [
                'ok' => false,
                'exists' => false,
            ];
        }

        return [
            'ok' => true,
            'exists' => $row !== null,
        ];
    }

    /**
     * Cuenta cualquier reserva/cita creada.
     *
     * Query pura para onboarding inicial; no filtra por estado porque el
     * objetivo es saber si el usuario ya logró crear al menos una cita.
     *
     * @return int
     */
    public static function count_created_reservations() {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_reservas';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($wpdb->last_error) {
            error_log('[ReservationsRepository] Error al contar reservas creadas: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }

    /**
     * @return array{
     *     id:int,
     *     estado:string,
     *     fecha:string,
     *     nombre:string,
     *     telefono:string,
     *     correo:string,
     *     servicio:string,
     *     duracion:int,
     *     assignment_id:int|null
     * }|null
     */
    public static function find_by_id(int $id): ?array {
        if ($id < 1) {
            return null;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'aa_reservas';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, estado, fecha, nombre, telefono, correo, servicio, duracion, assignment_id
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $id
            )
        );

        if ($wpdb->last_error) {
            error_log('[ReservationsRepository] find_by_id: ' . $wpdb->last_error);
            return null;
        }

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'estado' => (string) $row->estado,
            'fecha' => (string) $row->fecha,
            'nombre' => (string) $row->nombre,
            'telefono' => (string) $row->telefono,
            'correo' => (string) ($row->correo ?? ''),
            'servicio' => (string) $row->servicio,
            'duracion' => (int) $row->duracion,
            'assignment_id' => $row->assignment_id === null ? null : (int) $row->assignment_id,
        ];
    }
}
