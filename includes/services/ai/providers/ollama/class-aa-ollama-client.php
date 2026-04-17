<?php
/**
 * Ollama Client
 *
 * Adaptador HTTP para Ollama.
 * Soporta tanto runtime local (http://127.0.0.1:11434) como
 * Ollama Cloud (https://ollama.com) mediante la misma interfaz,
 * usando `Authorization: Bearer` cuando se inyecta `api_key`.
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

    /** @var string|null API key opcional (requerida para Ollama Cloud). */
    private $api_key;

    /**
     * @param string      $base_url Base URL del runtime (local o cloud).
     * @param string      $model    Modelo por defecto.
     * @param int         $timeout  Timeout HTTP en segundos.
     * @param string|null $api_key  API key opcional. Si se pasa, se envía como Bearer.
     */
    public function __construct(
        $base_url = 'http://127.0.0.1:11434',
        $model    = 'qwen2.5:3b',
        $timeout  = 120,
        $api_key  = null
    ) {
        $this->base_url = untrailingslashit((string) $base_url);
        $this->model    = (string) $model;
        $this->timeout  = (int) $timeout;
        $this->api_key  = is_string($api_key) && $api_key !== '' ? $api_key : null;
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

        $headers = ['Content-Type' => 'application/json'];

        if ($this->api_key !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }

        $response = wp_remote_post($url, [
            'headers' => $headers,
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
