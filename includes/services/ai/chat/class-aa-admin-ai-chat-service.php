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
     * Multi-turno (paso 6.a): si `$previous_parsed` no es null, se
     * añade un hint de contexto al system prompt y, tras parsear, el
     * resultado del LLM se fusiona con el snapshot previo mediante
     * `AA_AI_Parsed_Merger`. El campo `parsed` devuelto es siempre
     * el snapshot acumulado (merged), de modo que el cliente puede
     * reenviarlo como `previous_parsed` en el turno siguiente sin
     * preocuparse por la fusión.
     *
     * @param string                   $message         Texto en lenguaje natural del admin.
     * @param array<string,mixed>|null $previous_parsed Snapshot del turno anterior (opcional).
     * @return array {
     *     @type bool        $ok
     *     @type string|null $reply_text  Respuesta textual del modelo.
     *     @type array|null  $parsed      Objeto estructurado extraído (merged si hubo previo).
     *     @type array|null  $debug       Solo presente si hubo error de parseo.
     * }
     */
    public function handle($message, ?array $previous_parsed = null) {
        $message = is_string($message) ? trim($message) : '';

        if ($message === '') {
            return [
                'ok'         => false,
                'reply_text' => null,
                'parsed'     => null,
                'error'      => 'El mensaje no puede estar vacío.',
            ];
        }

        // Aborto conversacional explícito: sin LLM, sin merge, sin dispatch.
        // Debe devolver ok:true para que el frontend no muestre un error.
        if ($this->is_cancel_message($message)) {
            return $this->build_cancel_success_response();
        }

        $system_prompt = $this->build_system_prompt();

        if ($previous_parsed !== null) {
            $hint = $this->build_context_hint($previous_parsed);
            if ($hint !== '') {
                $system_prompt .= "\n\n" . $hint;
            }
        }

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

        $json_candidate = $this->extract_json_payload($content);
        $parsed = $json_candidate !== null ? json_decode($json_candidate, true) : null;

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            return [
                'ok'         => false,
                'reply_text' => $content,
                'parsed'     => null,
                'error'      => 'El modelo no devolvió JSON válido.',
                'debug'      => [
                    'raw_content'    => $content,
                    'json_candidate' => $json_candidate,
                ],
            ];
        }

        $parsed = $this->normalize_parsed($parsed);

        if ($previous_parsed !== null) {
            $merger = $this->build_merger();
            $parsed = $merger->merge($previous_parsed, $parsed);
        }

        $intent_result = $this->dispatch_intent($parsed, $message);

        $reply_text_out = $content;
        $resolution     = isset($intent_result['resolution']) && is_array($intent_result['resolution'])
            ? $intent_result['resolution']
            : null;
        if ($resolution !== null
            && isset($resolution['reply_ui']['text'])
            && is_string($resolution['reply_ui']['text'])
            && trim($resolution['reply_ui']['text']) !== ''
        ) {
            $reply_text_out = trim($resolution['reply_ui']['text']);
        }

        return [
            'ok'            => true,
            'reply_text'    => $reply_text_out,
            'parsed'        => $parsed,
            'intent_result' => $intent_result,
        ];
    }

    /**
     * Heurística conservadora: el usuario quiere abortar el borrador actual.
     * "ya no quiero" solo en mensaje casi literal (no subcadena en correcciones).
     * "no gracias" no se usa (demasiado ambiguo con negociación de hora).
     *
     * @param string $message Mensaje ya recortado (trim).
     */
    private function is_cancel_message($message): bool {
        if (!is_string($message) || $message === '') {
            return false;
        }

        $m = mb_strtolower($message, 'UTF-8');

        $phrases = [
            'cancela la cita',
            'cancelar la cita',
            'olvídalo',
            'olvidalo',
            'déjalo',
            'dejalo',
        ];
        foreach ($phrases as $p) {
            if (mb_strpos($m, $p, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        if (preg_match('/^ya no quiero\s*[.!…]*$/u', $m)) {
            return true;
        }

        // "mejor no" solo en mensaje corto/casi literal (evita "mejor no quiero cambiar…").
        if (preg_match('/^mejor no\s*[.!…]*$/u', $m)) {
            return true;
        }

        if (preg_match('/^(cancela|cancelar|olvida|déjalo|dejalo)\s*[.!…]*$/u', $m)) {
            return true;
        }

        if (preg_match('/\b(cancela|cancelar)\b/u', $m)) {
            return true;
        }

        return false;
    }

    /**
     * Respuesta de éxito compatible con el envelope del controller y
     * con `aichat.js` (reply_ui + draft_state null + parsed limpio).
     *
     * @return array<string,mixed>
     */
    private function build_cancel_success_response(): array {
        $text = 'De acuerdo, cancelé la operación actual.';

        $parsed = $this->normalize_parsed([
            'intent'       => 'unknown',
            'client_name'  => null,
            'service_name' => null,
            'staff_name'   => null,
            'zone_name'    => null,
            'date_text'    => null,
            'time_text'    => null,
            'notes'        => null,
        ]);

        $reply_ui = [
            'text'       => $text,
            'cta'        => 'noop',
            'highlights' => [],
            'choices'    => [],
            'draft_echo' => [
                'client'   => null,
                'service'  => null,
                'staff'    => null,
                'zone'     => null,
                'datetime' => null,
            ],
        ];

        return [
            'ok'            => true,
            'reply_text'    => $text,
            'parsed'        => $parsed,
            'intent_result' => [
                'intent'     => 'unknown',
                'status'     => 'aborted',
                'reply'      => $text,
                'resolution' => [
                    'reply_ui'    => $reply_ui,
                    'draft_state' => null,
                ],
            ],
        ];
    }

    /**
     * Lazy-require + factory del merger de dominio.
     *
     * Sigue el patrón de `handle_create_booking`: el require_once vive
     * en el método que lo necesita, no en el bootstrap del módulo.
     *
     * @return AA_AI_Parsed_Merger
     */
    private function build_merger() {
        require_once dirname(__DIR__, 3) . '/domain/ai/class-aa-ai-parsed-merger.php';
        return new AA_AI_Parsed_Merger();
    }

    /**
     * Construye un bloque de contexto para anexar al system prompt
     * cuando el cliente reenvía el snapshot del turno anterior.
     *
     * Política:
     * - Si tras filtrar no hay ningún campo significativo y el intent
     *   normalizado es `unknown`, se devuelve `''` (el caller no debe
     *   anexar hints vacíos: contaminarían el prompt).
     * - Solo se listan los campos con valor real (no se enumeran nulls).
     * - Se instruye al LLM a (a) interpretar el mensaje como
     *   refinamiento, (b) emitir `null` en los campos NO mencionados
     *   por el usuario, y (c) preservar el intent salvo cambio claro.
     *
     * Mismo hint para todos los proveedores (local / backend / cloud);
     * la optimización por modelo se deja fuera de alcance.
     *
     * @param array<string,mixed> $previous_parsed
     * @return string
     */
    private function build_context_hint(array $previous_parsed): string {
        $labels = [
            'intent'       => 'intención',
            'client_name'  => 'cliente',
            'service_name' => 'servicio',
            'staff_name'   => 'profesional',
            'zone_name'    => 'zona',
            'date_text'    => 'fecha',
            'time_text'    => 'hora',
            'notes'        => 'notas',
        ];

        $intent_raw = isset($previous_parsed['intent']) && is_string($previous_parsed['intent'])
            ? trim($previous_parsed['intent'])
            : '';
        $intent = $intent_raw !== '' ? $intent_raw : 'unknown';

        $lines = [];

        if ($intent !== 'unknown') {
            $lines[] = '- ' . $labels['intent'] . ': ' . $intent;
        }

        $data_fields = ['client_name', 'service_name', 'staff_name', 'zone_name', 'date_text', 'time_text', 'notes'];
        foreach ($data_fields as $field) {
            $value = $previous_parsed[$field] ?? null;
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $lines[] = '- ' . $labels[$field] . ': ' . $value;
        }

        if (empty($lines)) {
            return '';
        }

        $body = implode("\n", $lines);

        return <<<HINT
CONTEXTO DE CONVERSACIÓN PREVIA:
El administrador ya ha indicado los siguientes datos en mensajes anteriores:

{$body}

REGLAS DE REFINAMIENTO:
- Interpreta el mensaje actual como CORRECCIÓN o COMPLEMENTO del borrador previo, no como solicitud nueva.
- Si el usuario menciona un dato que YA estaba en el contexto, emite el nuevo valor (lo está cambiando).
- Si el usuario NO menciona un dato, emite null en ese campo: el servidor conservará el valor previo automáticamente.
- Mantén el mismo intent del contexto a menos que el mensaje sea claramente sobre otra cosa.
HINT;
    }

    /**
     * Enruta el parsed normalizado al handler correspondiente según intent.
     *
     * @param array  $parsed
     * @param string $user_message Texto original del admin (trim), para copy
     *                             seguro en intents no implementados.
     * @return array Estructura uniforme: intent, status, reply, resolution.
     */
    private function dispatch_intent(array $parsed, string $user_message) {
        $intent = $parsed['intent'] ?? 'unknown';

        if ($intent === 'create_booking') {
            return $this->handle_create_booking($parsed);
        }

        return $this->handle_unimplemented_intent($parsed, $user_message);
    }

    /**
     * Delega al handler dedicado de create_booking.
     *
     * @param array $parsed
     * @return array
     */
    private function handle_create_booking(array $parsed) {
        require_once __DIR__ . '/class-aa-ai-create-booking-intent-handler.php';

        $handler = new AA_AI_Create_Booking_Intent_Handler();

        return $handler->handle($parsed);
    }

    /**
     * Respuesta controlada para intents que aún no tienen handler dedicado.
     *
     * Incluye `resolution.reply_ui` mínimo para que el cliente del chat no
     * caiga en `reply_text` (JSON crudo del LLM) cuando `reply_ui` falta.
     *
     * @param array  $parsed
     * @param string $user_message Mensaje del usuario (trim).
     * @return array
     */
    private function handle_unimplemented_intent(array $parsed, string $user_message) {
        $intent  = $parsed['intent'] ?? 'unknown';
        $ui_text = $this->resolve_unimplemented_ui_text($intent, $user_message);

        $reply_ui = [
            'text'       => $ui_text,
            'cta'        => 'noop',
            'highlights' => [],
            'choices'    => [],
            'draft_echo' => [
                'client'   => null,
                'service'  => null,
                'staff'    => null,
                'zone'     => null,
                'datetime' => null,
            ],
        ];

        return [
            'intent'     => $intent,
            'status'     => 'not_implemented',
            'reply'      => $ui_text,
            'resolution' => [
                'parsed_input' => $parsed,
                'reply_ui'     => $reply_ui,
            ],
        ];
    }

    /**
     * Texto breve y neutro para intents sin handler. Cuatro buckets locales
     * para `unknown`: saludo simple, agradecimiento simple, acuse/cierre
     * suave (sin LLM), y guía genérica. Mensajes largos no entran en los
     * tres primeros para no pisar posibles datos de cita mal clasificados.
     *
     * @param string $intent         Intent normalizado.
     * @param string $user_message   Mensaje original (trim).
     */
    private function resolve_unimplemented_ui_text(string $intent, string $user_message): string {
        $fallback = 'Puedo ayudarte a crear una cita. Indícame cliente, servicio, fecha, hora, profesional y/o zona.';

        if ($intent !== 'unknown') {
            return $fallback;
        }

        $u = mb_strtolower(trim($user_message), 'UTF-8');
        if ($u === '') {
            return $fallback;
        }

        if (mb_strlen($u, 'UTF-8') > 52) {
            return $fallback;
        }

        if (preg_match('/^(hola|hi|hey)\s*[.!…,]*$/u', $u)) {
            return 'Hola. Estoy listo para ayudarte a crear una cita.';
        }

        if (preg_match('/^(buenos\s+d[ií]as|buenas\s+tardes|buenas\s+noches)\s*[.!…,]*$/u', $u)) {
            return 'Hola. Estoy listo para ayudarte a crear una cita.';
        }

        if (preg_match('/^(gracias|muchas gracias|mil gracias)\s*[.!…,]*$/u', $u)) {
            return 'De nada.';
        }

        if ($this->is_soft_ack_message($u)) {
            return 'Perfecto. Cuando quieras crear una cita, dime los datos.';
        }

        return $fallback;
    }

    /**
     * Acuse o cierre suave (ok, entendido, vale, etc.). Solo patrones
     * anclados y mensajes cortos; el caller ya acotó longitud.
     *
     * @param string $u Mensaje en minúsculas (trim).
     */
    private function is_soft_ack_message(string $u): bool {
        $patterns = [
            '/^ok\s*[.!…,]*$/u',
            '/^entendido\s*[.!…,]*$/u',
            '/^ya\s+entend[ií]\s*[.!…,]*$/u',
            '/^gracias\s+por\s+la\s+info\s*[.!…,]*$/u',
            '/^gracias\s+por\s+la\s+(informaci[oó]n|informacion)\s*[.!…,]*$/u',
            '/^ok\s*,?\s*gracias\s*[.!…,]*$/u',
            '/^perfecto\s*[.!…,]*$/u',
            '/^vale\s*[.!…,]*$/u',
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $u) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prompt de sistema para extracción estructurada.
     *
     * @return string
     */
    private function build_system_prompt() {
        return <<<'PROMPT'
Eres un parser de una agenda de citas. Tu ÚNICO trabajo es extraer datos estructurados del mensaje del administrador.

Responde SIEMPRE con un objeto JSON y NADA MÁS. Sin explicaciones, sin texto extra.

CAMPOS (exactamente estos 8):
- "intent": "create_booking" | "check_availability" | "find_client" | "list_services" | "unknown"
- "client_name": string | null
- "service_name": string | null
- "staff_name": string | null
- "zone_name": string | null
- "date_text": string | null
- "time_text": string | null
- "notes": string | null

CÓMO IDENTIFICAR ROLES EN LA ORACIÓN:
- "agendar/agenda/agéndame A [nombre]" → client_name (la persona a quien se agenda)
- "para [servicio]" → service_name (el servicio solicitado)
- "con [nombre]" → staff_name (el profesional que atiende)
- "en [lugar/zona]" → zone_name
- Referencias temporales como "mañana", "el lunes", "15 de abril" → date_text (copiar tal cual)
- Referencias de hora como "a las 5", "4pm", "16:00" → time_text (copiar tal cual)

REGLAS:
- Si un dato no aparece en el mensaje → null.
- No inventes datos.
- Si la intención no es clara → "unknown".
- Extrae nombres propios tal cual aparecen.

EJEMPLOS:

Input: "Agéndame a José mañana a las 5 para cejas con Anahí"
Output: {"intent":"create_booking","client_name":"José","service_name":"cejas","staff_name":"Anahí","zone_name":null,"date_text":"mañana","time_text":"a las 5","notes":null}

Input: "Agenda una cita para María López el viernes a las 10 para corte de cabello"
Output: {"intent":"create_booking","client_name":"María López","service_name":"corte de cabello","staff_name":null,"zone_name":null,"date_text":"el viernes","time_text":"a las 10","notes":null}

Input: "Ponle cita a Pedro con la Dra. Gómez para limpieza dental mañana a las 3 en sucursal norte"
Output: {"intent":"create_booking","client_name":"Pedro","service_name":"limpieza dental","staff_name":"Dra. Gómez","zone_name":"sucursal norte","date_text":"mañana","time_text":"a las 3","notes":null}

Input: "Agenda una cita mañana"
Output: {"intent":"create_booking","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":"mañana","time_text":null,"notes":null}

Input: "Qué servicios tienen disponibles?"
Output: {"intent":"list_services","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null}

Input: "Hay disponibilidad el martes en la tarde?"
Output: {"intent":"check_availability","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":"el martes","time_text":"en la tarde","notes":null}

Input: "Busca al cliente Ana Martínez"
Output: {"intent":"find_client","client_name":"Ana Martínez","service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null}
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

    /**
     * Extrae el bloque JSON utilizable del contenido devuelto por el modelo.
     *
     * Compatibilidad local/cloud:
     * - Modelos locales con format=json suelen devolver JSON puro.
     * - Modelos cloud pueden envolver el JSON en fences markdown
     *   tipo ```json ... ``` o ``` ... ```.
     * - Algunos modelos agregan texto antes o después del JSON.
     *
     * No interpreta campos tipo "thinking": si el JSON final no los
     * contiene, `normalize_parsed` ya los descarta.
     *
     * @param string $content
     * @return string|null JSON crudo listo para json_decode, o null si no hay candidato.
     */
    private function extract_json_payload($content) {
        $raw = is_string($content) ? trim($content) : '';

        if ($raw === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*?\}|\[.*?\])\s*```/si', $raw, $matches)) {
            return trim($matches[1]);
        }

        $first_brace = strpos($raw, '{');
        $last_brace  = strrpos($raw, '}');

        if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
            return trim(substr($raw, $first_brace, $last_brace - $first_brace + 1));
        }

        return $raw;
    }
}
