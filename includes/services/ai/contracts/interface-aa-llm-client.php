<?php
/**
 * LLM Client Contract
 *
 * Contrato de infraestructura para proveedores de modelos.
 *
 * Regla arquitectónica:
 * - El proveedor LLM no conoce lógica de citas.
 * - El proveedor LLM no conoce UI ni endpoints AJAX.
 */

defined('ABSPATH') or die('No direct access');

interface AA_LLM_Client_Interface {
    /**
     * Ejecuta una interacción de chat contra un proveedor LLM.
     *
     * @param array $payload Payload normalizado por la capa de chat.
     * @return array|\WP_Error Respuesta del proveedor o error transportable.
     */
    public function chat(array $payload);
}
