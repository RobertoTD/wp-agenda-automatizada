<?php
/**
 * Admin AI Chat Service
 *
 * Caso de uso del chat AI dentro del admin.
 *
 * No debe: ejecutar SQL, renderizar UI, conocer detalles del DOM.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Admin_AI_Chat_Service {

    /** @var AA_LLM_Client_Interface */
    private $llm_client;

    private const VALID_INTENTS = [
        'create_booking',
        'check_availability',
        'find_client',
        'list_services',
        'unknown',
    ];

    /**
     * @param AA_LLM_Client_Interface $llm_client
     */
    public function __construct(AA_LLM_Client_Interface $llm_client) {
        $this->llm_client = $llm_client;
    }

    /**
     * Procesa un mensaje del admin y devuelve la respuesta del modelo.
     *
     * @param string $message Texto en lenguaje natural del admin.
     * @return array {
     *     @type bool        $ok
     *     @type string|null $reply_text  Respuesta textual del modelo.
     *     @type array|null  $parsed      Objeto estructurado extraído.
     *     @type array|null  $debug       Solo presente si hubo error de parseo.
     * }
     */
    public function handle($message) {
        $message = is_string($message) ? trim($message) : '';

        if ($message === '') {
            return [
                'ok'         => false,
                'reply_text' => null,
                'parsed'     => null,
                'error'      => 'El mensaje no puede estar vacío.',
            ];
        }

        $system_prompt = $this->build_system_prompt();

        $result = $this->llm_client->chat([
            'messages' => [
                ['role' => 'system',  'content' => $system_prompt],
                ['role' => 'user',    'content' => $message],
            ],
            'format' => 'json',
        ]);

        if (empty($result['ok'])) {
            return [
                'ok'         => false,
                'reply_text' => null,
                'parsed'     => null,
                'error'      => $result['error'] ?? 'Error desconocido del proveedor LLM.',
                'debug'      => ['provider_raw' => $result['raw'] ?? null],
            ];
        }

        $content = $result['data']['message']['content'] ?? '';

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            return [
                'ok'         => false,
                'reply_text' => $content,
                'parsed'     => null,
                'error'      => 'El modelo no devolvió JSON válido.',
                'debug'      => ['raw_content' => $content],
            ];
        }

        $parsed = $this->normalize_parsed($parsed);

        return [
            'ok'         => true,
            'reply_text' => $content,
            'parsed'     => $parsed,
        ];
    }

    /**
     * Prompt de sistema para extracción estructurada.
     *
     * @return string
     */
    private function build_system_prompt() {
        return <<<'PROMPT'
Eres un asistente de una agenda de citas. Tu trabajo es interpretar mensajes del administrador del negocio y extraer datos estructurados.

Responde SIEMPRE con un objeto JSON y nada más. El objeto debe tener exactamente estos campos:

- "intent": una de estas opciones exactas: "create_booking", "check_availability", "find_client", "list_services", "unknown"
- "client_name": nombre del cliente mencionado, o null si no se menciona
- "service_name": nombre del servicio solicitado, o null si no se menciona
- "staff_name": nombre del profesional o empleado, o null si no se menciona
- "zone_name": nombre del área o zona, o null si no se menciona
- "date_text": la fecha mencionada tal cual la dice el usuario (ej: "mañana", "el lunes", "15 de abril"), o null
- "time_text": la hora mencionada tal cual la dice el usuario (ej: "a las 4", "4pm", "16:00"), o null
- "notes": cualquier detalle adicional relevante, o null

Reglas:
- Si un dato no está presente en el mensaje, el campo debe ser null.
- No inventes datos que no estén en el mensaje.
- Si no puedes determinar la intención, usa "unknown".
- No incluyas explicaciones, solo el JSON.
PROMPT;
    }

    /**
     * Normaliza el objeto parseado para garantizar shape uniforme.
     *
     * @param array $parsed
     * @return array
     */
    private function normalize_parsed(array $parsed) {
        $fields = [
            'intent'       => null,
            'client_name'  => null,
            'service_name' => null,
            'staff_name'   => null,
            'zone_name'    => null,
            'date_text'    => null,
            'time_text'    => null,
            'notes'        => null,
        ];

        $normalized = [];
        foreach ($fields as $key => $default) {
            $value = isset($parsed[$key]) && $parsed[$key] !== '' ? $parsed[$key] : $default;
            $normalized[$key] = $value;
        }

        if (!in_array($normalized['intent'], self::VALID_INTENTS, true)) {
            $normalized['intent'] = 'unknown';
        }

        return $normalized;
    }
}
