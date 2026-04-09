<?php
/**
 * AI Chat Request Validator
 *
 * Validador del payload de entrada para el chat AI del admin.
 *
 * Responsabilidad futura:
 * - Validar shape mínimo del request.
 * - Sanitizar campos textuales del chat.
 * - Normalizar datos antes de llegar al servicio de chat.
 *
 * No debe:
 * - Conocer proveedores LLM.
 * - Ejecutar lógica de citas.
 * - Emitir respuesta HTTP.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Chat_Request_Validator {
    /**
     * Valida y normaliza un request del chat AI.
     *
     * Stub inicial para fijar la frontera de validación.
     *
     * @param array $request
     * @return array|\WP_Error
     */
    public function validate(array $request) {
        return new WP_Error(
            'aa_ai_not_implemented',
            'AA_AI_Chat_Request_Validator todavía no implementa validación real.'
        );
    }
}
