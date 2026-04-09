<?php
/**
 * AI Skill Registry
 *
 * Registro reservado para skills AI del plugin.
 *
 * Responsabilidad futura:
 * - Exponer skills disponibles al caso de uso de chat.
 * - Resolver skills por nombre o capacidad.
 *
 * Regla actual:
 * - Este archivo existe para fijar la frontera, pero no debe cargarse
 *   ni operar hasta que exista más de un caso de uso real.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Skill_Registry {
    /**
     * Lista las definiciones de skills registrables.
     *
     * Stub inicial. No hay skills operativas todavía.
     *
     * @return array
     */
    public function all() {
        return [];
    }
}
