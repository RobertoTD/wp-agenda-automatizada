<?php
/**
 * AI System Prompts
 *
 * Repositorio futuro de prompts de sistema para los casos de uso AI.
 *
 * Responsabilidad futura:
 * - Centralizar prompts base por caso de uso.
 * - Evitar prompts hardcodeados en controladores o proveedores.
 *
 * Regla:
 * - El contenido del prompt puede describir el dominio,
 *   pero esta clase no ejecuta lógica de dominio.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_System_Prompts {
    /**
     * Prompt base para el chat admin.
     *
     * Stub inicial: devuelve string vacío hasta definir el primer contrato real.
     *
     * @return string
     */
    public static function get_admin_chat_system_prompt() {
        return '';
    }
}
