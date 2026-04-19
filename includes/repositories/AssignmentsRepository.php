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
    // Intencionalmente vacío.
    //
    // Todos los métodos del padre quedan disponibles vía herencia.
    // Los métodos NUEVOS se añaden aquí.
}
