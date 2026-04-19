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
    // Intencionalmente vacío.
    //
    // Todos los métodos del padre quedan disponibles vía herencia.
    // Los métodos NUEVOS se añaden aquí.
}
