<?php
/**
 * AI Appointment Command Mapper
 *
 * Adaptador futuro entre la salida AI y los comandos del dominio de citas.
 *
 * Responsabilidad futura:
 * - Traducir objetos del LLM a una estructura interna estable.
 * - Aislar el formato de salida del modelo del resto del plugin.
 *
 * No debe:
 * - llamar al proveedor LLM.
 * - ejecutar SQL.
 * - devolver HTML.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Appointment_Command_Mapper {
    /**
     * Convierte una salida AI a un comando interno de citas.
     *
     * Stub inicial para reservar la frontera de adaptación al dominio.
     *
     * @param array $ai_output
     * @return array|\WP_Error
     */
    public function map(array $ai_output) {
        return new WP_Error(
            'aa_ai_not_implemented',
            'AA_AI_Appointment_Command_Mapper todavía no implementa mapeo real.'
        );
    }
}
