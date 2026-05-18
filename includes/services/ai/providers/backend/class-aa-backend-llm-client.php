<?php
/**
 * Backend Gateway LLM Client
 *
 * Único adaptador LLM del plugin: delega el chat al backend Node
 * (gateway AI). El backend guarda la API key del proveedor y decide
 * qué modelo usar.
 *
 * Este cliente no habla con proveedores LLM directamente: habla con
 * `POST /ai/parse` del backend, firmado con el mismo HMAC que el resto
 * de integraciones plugin ↔ backend (reutiliza
 * `aa_send_authenticated_request`).
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
     * Contrato de respuesta esperado por AA_Admin_AI_Chat_Service:
     *   éxito: ['ok' => true,  'data' => [ 'message' => [ 'content' => '…' ] ]]
     *   error: ['ok' => false, 'error' => '…', 'code' => '…', 'http_status' => int, 'raw' => … ]
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
                'code'  => 'ai_backend_not_configured',
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

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $decoded     = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            return $this->build_error_result($decoded, $raw_body, $status_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [
                'ok'          => false,
                'error'       => 'Respuesta del backend AI no es JSON válido: ' . json_last_error_msg(),
                'http_status' => $status_code,
                'raw'         => $raw_body,
            ];
        }

        if (empty($decoded['ok'])) {
            return $this->build_error_result($decoded, $raw_body, $status_code);
        }

        $content = $decoded['data']['message']['content'] ?? '';
        if (!is_string($content) || trim($content) === '') {
            return [
                'ok'          => false,
                'error'       => 'El backend AI devolvió message.content vacío.',
                'http_status' => $status_code,
                'raw'         => $raw_body,
            ];
        }

        return [
            'ok'   => true,
            'data' => [
                'message' => [
                    'content' => $content,
                ],
            ],
        ];
    }

    /**
     * @param mixed  $decoded
     * @param string $raw_body
     * @param int    $status_code
     * @return array{ok: false, error: string, code?: string, http_status: int, raw: string}
     */
    private function build_error_result($decoded, $raw_body, $status_code) {
        $error_message = 'Error desconocido del backend AI.';
        $code          = null;

        if (is_array($decoded)) {
            if (!empty($decoded['error']) && is_string($decoded['error'])) {
                $error_message = (string) $decoded['error'];
            }
            if (!empty($decoded['code']) && is_string($decoded['code'])) {
                $code = (string) $decoded['code'];
            }
        }

        if ($error_message === 'Error desconocido del backend AI.' && $status_code > 0) {
            $error_message = "Backend AI respondió HTTP {$status_code}.";
        }

        $result = [
            'ok'          => false,
            'error'       => $error_message,
            'http_status' => $status_code,
            'raw'         => $raw_body,
        ];

        if ($code !== null && $code !== '') {
            $result['code'] = $code;
        }

        return $result;
    }
}
