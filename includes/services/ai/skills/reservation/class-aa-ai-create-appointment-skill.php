<?php
/**
 * AI Create Appointment Skill
 *
 * Skill reservada para traducir intenciones del chat en acciones
 * relacionadas con creación de citas.
 *
 * Estado actual:
 * - Es solo un archivo ancla.
 * - No debe registrarse ni cargarse hasta que exista un caso de uso real.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Create_Appointment_Skill {
    /**
     * Nombre canónico futuro de la skill.
     *
     * @return string
     */
    public function get_name() {
        return 'create_appointment';
    }
}
