<?php
/**
 * Backend Gateway LLM Client
 *
 * Adaptador HTTP que delega el chat LLM al backend Node (gateway AI).
 * El backend guarda la API key del proveedor cloud y decide qué modelo usar.
 *
 * Este cliente NO habla con Ollama directamente: habla con `POST /ai/parse`
 * del backend, firmado con el mismo HMAC que el resto de integraciones
 * plugin ↔ backend (reutiliza `aa_send_authenticated_request`).
 *
 * No debe conocer: SQL, lógica de citas, render del chat admin, prompts.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Backend_LLM_Client implements AA_LLM_Client_Interface {

    /** @var string URL completa del endpoint /ai/parse del backend. */
    private $endpoint;

    /**
     * @param string $endpoint URL absoluta al endpoint /ai/parse del backend.
     */
    public function __construct($endpoint) {
        $this->endpoint = (string) $endpoint;
    }

    /**
     * Reenvía el payload de chat al backend gateway.
     *
     * Contrato de respuesta alineado con AA_Ollama_Client para que
     * AA_Admin_AI_Chat_Service sea indiferente al proveedor:
     *   éxito: ['ok' => true,  'data' => [ 'message' => [ 'content' => '…' ] ]]
     *   error: ['ok' => false, 'error' => '…', 'raw' => … ]
     *
     * @param array $payload {messages[], format?, options?}
     * @return array
     */
    public function chat(array $payload) {
        if (empty($payload['messages']) || !is_array($payload['messages'])) {
            return [
                'ok'    => false,
                'error' => 'El payload requiere un array "messages" no vacío.',
                'raw'   => null,
            ];
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return [
                'ok'    => false,
                'error' => 'aa_send_authenticated_request no disponible. Revisa auth-helper.php.',
                'raw'   => null,
            ];
        }

        $body = [
            'messages' => $payload['messages'],
        ];

        if (!empty($payload['format'])) {
            $body['format'] = $payload['format'];
        }

        if (!empty($payload['options']) && is_array($payload['options'])) {
            $body['options'] = $payload['options'];
        }

        $response = aa_send_authenticated_request($this->endpoint, 'POST', $body);

        if (is_wp_error($response)) {
            return [
                'ok'    => false,
                'error' => $response->get_error_message(),
                'raw'   => null,
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $decoded     = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = is_array($decoded) && !empty($decoded['error'])
                ? (string) $decoded['error']
                : "Backend AI respondió HTTP {$status_code}.";

            return [
                'ok'    => false,
                'error' => $error_message,
                'raw'   => $raw_body,
            ];
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [
                'ok'    => false,
                'error' => 'Respuesta del backend AI no es JSON válido: ' . json_last_error_msg(),
                'raw'   => $raw_body,
            ];
        }

        if (empty($decoded['ok'])) {
            return [
                'ok'    => false,
                'error' => isset($decoded['error']) ? (string) $decoded['error'] : 'Error desconocido del backend AI.',
                'raw'   => $raw_body,
            ];
        }

        $content = $decoded['data']['message']['content'] ?? '';

        return [
            'ok'   => true,
            'data' => [
                'message' => [
                    'content' => $content,
                ],
            ],
        ];
    }
}
