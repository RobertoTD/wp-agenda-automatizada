<?php
/**
 * Ollama Client
 *
 * Adaptador HTTP para Ollama en entorno local.
 *
 * No debe conocer: SQL, lógica de citas, render del chat admin.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Ollama_Client implements AA_LLM_Client_Interface {

    /** @var string */
    private $base_url;

    /** @var string */
    private $model;

    /** @var int Timeout en segundos para wp_remote_post. */
    private $timeout;

    /**
     * @param string $base_url Base URL del runtime local de Ollama.
     * @param string $model    Modelo por defecto.
     * @param int    $timeout  Timeout HTTP en segundos.
     */
    public function __construct(
        $base_url = 'http://127.0.0.1:11434',
        $model    = 'qwen2.5:3b',
        $timeout  = 120
    ) {
        $this->base_url = untrailingslashit((string) $base_url);
        $this->model    = (string) $model;
        $this->timeout  = (int) $timeout;
    }

    /**
     * Ejecuta /api/chat contra Ollama.
     *
     * @param array $payload {
     *     @type array       $messages  Requerido. Array de {role, content}.
     *     @type string|null $format    Opcional. 'json' para forzar salida JSON.
     *     @type array       $options   Opcional. Parámetros del runtime (temperature, etc.).
     * }
     * @return array ['ok' => bool, 'data' => …] | ['ok' => false, 'error' => …, 'raw' => …]
     */
    public function chat(array $payload) {
        if (empty($payload['messages']) || !is_array($payload['messages'])) {
            return [
                'ok'    => false,
                'error' => 'El payload requiere un array "messages" no vacío.',
                'raw'   => null,
            ];
        }

        $body = [
            'model'    => $this->model,
            'messages' => $payload['messages'],
            'stream'   => false,
        ];

        if (!empty($payload['format'])) {
            $body['format'] = $payload['format'];
        }

        if (!empty($payload['options']) && is_array($payload['options'])) {
            $body['options'] = $payload['options'];
        }

        $url = $this->base_url . '/api/chat';

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return [
                'ok'    => false,
                'error' => $response->get_error_message(),
                'raw'   => null,
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            return [
                'ok'    => false,
                'error' => "Ollama respondió HTTP {$status_code}.",
                'raw'   => $raw_body,
            ];
        }

        $decoded = json_decode($raw_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'ok'    => false,
                'error' => 'Respuesta de Ollama no es JSON válido: ' . json_last_error_msg(),
                'raw'   => $raw_body,
            ];
        }

        return [
            'ok'   => true,
            'data' => $decoded,
        ];
    }
}
