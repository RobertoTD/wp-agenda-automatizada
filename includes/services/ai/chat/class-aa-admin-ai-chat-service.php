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
        'create_client',
        'check_availability',
        'find_client',
        'list_services',
        'unknown',
    ];

    /** Mensaje unificado cuando el proveedor LLM no entrega salida usable o falla el transporte. */
    private const AI_UNAVAILABLE_USER_MESSAGE = 'No pude conectarme con el asistente en este momento. Intenta de nuevo más tarde.';

    /**
     * Campos canónicos que forman el shape normalizado del `parsed`.
     * Incluye los 8 campos legacy del parser + los 3 campos nuevos
     * introducidos en la Fase 1 de la reorganización conversacional
     * (`sub_intent`, `affected_fields`, `confidence`). `confidence` es
     * nullable por diseño: puede no venir del LLM.
     */
    private const PARSED_DATA_FIELDS = [
        'client_name',
        'service_name',
        'staff_name',
        'zone_name',
        'date_text',
        'time_text',
        'notes',
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
     *     @type string|null $error       Mensaje de error si ok=false.
     *     @type string|null $code        p.ej. `ai_unavailable` cuando el LLM no entrega salida usable.
     *     @type array|null  $debug       Solo presente si hubo error de parseo o diagnóstico.
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
            $this->log_turn_debug([
                'message'         => $message,
                'previous_parsed' => $previous_parsed,
                'short_circuit'   => 'is_cancel_message',
            ]);
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
            return $this->build_ai_unavailable_response([
                'message'         => $message,
                'previous_parsed' => $previous_parsed,
                'reason'          => 'provider_error',
                'provider_ok'     => false,
                'provider_error'  => $result['error'] ?? null,
                'provider_raw'    => $result['raw'] ?? null,
            ]);
        }

        $content = $result['data']['message']['content'] ?? '';
        if (!is_string($content)) {
            $content = '';
        }

        if (!$this->is_llm_content_usable($content)) {
            return $this->build_ai_unavailable_response([
                'message'         => $message,
                'previous_parsed' => $previous_parsed,
                'reason'          => 'unusable_content',
                'provider_ok'     => true,
                'raw_content_len' => strlen($content),
            ]);
        }

        $json_candidate = $this->extract_json_payload(trim($content));
        $parsed_raw     = $json_candidate !== null ? json_decode($json_candidate, true) : null;

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed_raw)) {
            return $this->build_ai_unavailable_response([
                'message'          => $message,
                'previous_parsed'  => $previous_parsed,
                'reason'           => 'json_decode_mismatch',
                'provider_ok'      => true,
                'json_candidate'   => $json_candidate,
                'json_last_error'  => json_last_error_msg(),
            ]);
        }

        $parsed = $this->normalize_parsed($parsed_raw);
        $attach_create_client_assistive_notice = $this->should_attach_create_client_assistive_notice($previous_parsed, $message);
        $parsed = $this->apply_initial_create_client_intent_gate($parsed, $previous_parsed, $message);

        // Paso 3: cancelación server-side dirigida por sub_intent.
        // Ocurre ANTES del merge porque cancelar no necesita draft: si el
        // LLM clasifica "ya no gracias" / "déjalo" / etc. como
        // cancel_draft, no gastamos merge ni dispatch. Cuando la frase
        // cae también bajo `is_cancel_message` el corto-circuito legacy
        // ya la atrapó arriba; este camino cubre todo lo que la regex
        // no ve.
        $this->require_conversation_contract();
        if (($parsed['sub_intent'] ?? null) === AA_AI_Conversation_Contract::SUB_INTENT_CANCEL_DRAFT) {
            $this->log_turn_debug([
                'message'         => $message,
                'previous_parsed' => $previous_parsed,
                'parsed_raw'      => $parsed_raw,
                'parsed'          => $parsed,
                'sub_intent'      => $parsed['sub_intent'],
                'affected_fields' => $parsed['affected_fields'] ?? [],
                'short_circuit'   => 'sub_intent_cancel_draft',
            ]);
            return $this->build_cancel_success_response();
        }

        if ($previous_parsed !== null) {
            $merger = $this->build_merger();
            $parsed = $merger->merge($previous_parsed, $parsed);
        }

        // Paso 3.5 — orquestación: no romper create_booking por intent
        // check_availability; evitar confirm_draft fantasma del LLM en
        // mensajes que son correcciones ("sí, el profesional es…").
        $parsed = $this->apply_post_merge_create_booking_orchestration_v35(
            $parsed,
            $previous_parsed,
            $message
        );

        $parsed = $this->promote_initial_create_booking_intent_if_clear($parsed, $previous_parsed, $message);

        $this->log_turn_debug([
            'message'         => $message,
            'previous_parsed' => $previous_parsed,
            'parsed_raw'      => $parsed_raw,
            'parsed'          => $parsed,
            'sub_intent'      => $parsed['sub_intent'] ?? null,
            'affected_fields' => $parsed['affected_fields'] ?? [],
        ]);

        $intent_result = $this->dispatch_intent($parsed, $message);
        if ($attach_create_client_assistive_notice) {
            $this->attach_create_client_assistive_notice($intent_result);
        }

        // Paso 3: confirmación server-side dirigida por sub_intent.
        // Ocurre DESPUÉS de dispatch porque necesitamos el `draft_state`
        // ya construido por `handle_create_booking`: no confirmamos a
        // menos que el aggregator haya producido `ready_for_confirmation`.
        // Si no está listo, este método devuelve null y caemos al flujo
        // normal (el reply builder ya explicará qué falta).
        // Paso 3.5: además exige afirmación explícita del usuario y ausencia
        // de errores de resolución/feasibility (ver try_confirm_by_sub_intent).
        $confirm_outcome = $this->try_confirm_by_sub_intent($parsed, $intent_result, $message);
        if ($confirm_outcome !== null) {
            return $confirm_outcome;
        }

        $this->rewrite_first_turn_create_booking_prefix($previous_parsed, $parsed, $message, $intent_result);

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
     * Primer turno de create_booking (sin previous_parsed): sustituye el prefijo
     * "Seguimos con tu cita." por una apertura de inicio (mismo significado).
     *
     * @param array<string,mixed>|null $previous_parsed
     * @param array<string,mixed>      $parsed
     * @param array<string,mixed>      $intent_result  Por referencia: muta reply_ui.text si aplica.
     */
    private function rewrite_first_turn_create_booking_prefix(
        ?array $previous_parsed,
        array $parsed,
        string $message,
        array &$intent_result
    ): void {
        if ($previous_parsed !== null) {
            return;
        }
        if (($parsed['intent'] ?? '') !== 'create_booking') {
            return;
        }
        if (!isset($intent_result['resolution']) || !is_array($intent_result['resolution'])) {
            return;
        }
        $reply_ui = $intent_result['resolution']['reply_ui'] ?? null;
        if (!is_array($reply_ui) || !isset($reply_ui['text']) || !is_string($reply_ui['text'])) {
            return;
        }

        $prefix_old = 'Seguimos con tu cita. ';
        $text       = $reply_ui['text'];
        if ($text === '' || strncmp($text, $prefix_old, strlen($prefix_old)) !== 0) {
            return;
        }

        $variants = [
            'Claro, vamos a crear la cita. ',
            'Perfecto, empecemos con la cita. ',
            'De acuerdo, iniciemos la cita. ',
        ];
        $hash = (int) sprintf('%u', crc32($message));
        $idx  = $hash % count($variants);

        $intent_result['resolution']['reply_ui']['text'] = $variants[$idx] . substr($text, strlen($prefix_old));
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

        $this->require_conversation_contract();

        $parsed = $this->normalize_parsed([
            'intent'          => 'unknown',
            'client_name'     => null,
            'service_name'    => null,
            'staff_name'      => null,
            'zone_name'       => null,
            'date_text'       => null,
            'time_text'       => null,
            'notes'           => null,
            'sub_intent'      => AA_AI_Conversation_Contract::SUB_INTENT_CANCEL_DRAFT,
            'affected_fields' => [],
            'confidence'      => 1.0,
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
     * Paso 3.5 — ajustes de orquestación tras el merge del parsed.
     *
     *   1) Si el borrador venía de `create_booking` y el turno actual es
     *      `ask_availability`, el LLM suele emitir `intent:check_availability`
     *      lo cual desvía el dispatch y rompe el hilo. Se fuerza
     *      `intent = create_booking` para seguir en el mismo flujo.
     *
     *   2) Si el modelo etiqueta `confirm_draft` pero el texto del
     *      usuario no es una afirmación explícita de cierre (p. ej.
     *      "sí, el profesional es Adrian"), se rebaja a `sub_intent:other`
     *      para que NO dispare la confirmación server-side del Paso 3.
     *
     * @param array<string,mixed>      $parsed          Parsed ya mergeado.
     * @param array<string,mixed>|null $previous_parsed Snapshot previo o null.
     * @param string                   $message         Mensaje del usuario (trim).
     * @return array<string,mixed>
     */
    private function apply_post_merge_create_booking_orchestration_v35(
        array $parsed,
        ?array $previous_parsed,
        string $message
    ): array {
        $this->require_conversation_contract();

        if ($previous_parsed !== null
            && (($previous_parsed['intent'] ?? '') === 'create_booking')
            && (($parsed['sub_intent'] ?? '') === AA_AI_Conversation_Contract::SUB_INTENT_ASK_AVAILABILITY)
        ) {
            $parsed['intent'] = 'create_booking';
            $this->log_turn_debug([
                'message'         => $message,
                'orchestration_v35' => 'intent_pinned_create_booking_for_ask_availability',
            ]);
        }

        if (($parsed['sub_intent'] ?? null) === AA_AI_Conversation_Contract::SUB_INTENT_CONFIRM_DRAFT
            && !$this->is_message_affirmation_for_server_booking($message)
        ) {
            $parsed['sub_intent'] = AA_AI_Conversation_Contract::SUB_INTENT_OTHER;
            $this->log_turn_debug([
                'message'           => $message,
                'orchestration_v35' => 'confirm_draft_downgraded_not_affirmation',
            ]);
        }

        return $parsed;
    }

    /**
     * Refuerzo conservador para primer turno: si el LLM dejó `unknown`
     * pero el administrador pide claramente iniciar una cita, fijamos
     * `create_booking` antes del dispatch sin tocar otros intents.
     *
     * @param array<string,mixed>      $parsed
     * @param array<string,mixed>|null $previous_parsed
     * @param string                   $message
     * @return array<string,mixed>
     */
    private function promote_initial_create_booking_intent_if_clear(
        array $parsed,
        ?array $previous_parsed,
        string $message
    ): array {
        if ($previous_parsed !== null) {
            return $parsed;
        }
        if (($parsed['intent'] ?? '') !== 'unknown') {
            return $parsed;
        }
        if (!$this->is_clear_initial_create_booking_request($message)) {
            return $parsed;
        }

        $this->require_conversation_contract();

        $parsed['intent']          = 'create_booking';
        $parsed['sub_intent']      = AA_AI_Conversation_Contract::SUB_INTENT_NEW_BOOKING;
        $parsed['affected_fields'] = [];
        $parsed['confidence']      = 0.95;

        return $parsed;
    }

    /**
     * Allowlist estricta de frases cortas e inequívocas para iniciar una cita.
     * Evita preguntas, negaciones y consultas meta ("cómo crear una cita").
     */
    private function is_clear_initial_create_booking_request(string $message): bool {
        $m = mb_strtolower(trim($message), 'UTF-8');
        if ($m === '') {
            return false;
        }

        $m = preg_replace('/\s+/u', ' ', $m);
        if (!is_string($m) || $m === '') {
            return false;
        }

        if (preg_match('/[?¿]/u', $m)) {
            return false;
        }

        $deny_patterns = [
            '/\b(?:c[oó]mo|explicame|expl[ií]came|explicar|funciona|funcionan|tutorial|ayuda)\b/u',
            '/\bno\s+(?:quiero|deseo|necesito|voy\s+a|vaya\s+a)?\s*(?:crear|agendar|programar|hacer)\s+(?:una\s+)?cita\b/u',
            '/\b(?:no|nunca|jam[aá]s)\s+(?:me\s+)?(?:agendes|agendar|crees|crear|programar)\b/u',
            '/\b(?:cancelar|cancela|eliminar|borra|borrar)\s+(?:una\s+)?cita\b/u',
        ];
        foreach ($deny_patterns as $pattern) {
            if (preg_match($pattern, $m)) {
                return false;
            }
        }

        $allow_patterns = [
            '/^(?:por\s+favor\s+)?(?:crea|crear|crear(me)?|cr[eé]ame|haz|hacer)\s+(?:una\s+)?cita(?:\s+por\s+favor)?[.!…]*$/u',
            '/^(?:por\s+favor\s+)?(?:agenda|agendar|ag[eé]ndame|agendame)\s+(?:una\s+)?cita(?:\s+por\s+favor)?[.!…]*$/u',
            '/^(?:por\s+favor\s+)?(?:programa|programar|progr[aá]mame|programame)\s+(?:una\s+)?cita(?:\s+por\s+favor)?[.!…]*$/u',
            '/^(?:yo\s+)?(?:quiero|quieor|necesito|quisiera|me\s+gustar[ií]a)\s+(?:crear|agendar|programar|hacer)\s+(?:una\s+)?cita(?:\s+por\s+favor)?[.!…]*$/u',
            '/^(?:vamos\s+a|empecemos\s+a|iniciemos\s+a)\s+(?:crear|agendar|programar|hacer)\s+(?:una\s+)?cita[.!…]*$/u',
        ];
        foreach ($allow_patterns as $pattern) {
            if (preg_match($pattern, $m)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gate de clasificación inicial para `create_client`.
     *
     * - Si hay `previous_parsed`, el intent no se activa y el turno se
     *   neutraliza para no contaminar un borrador activo de cita.
     * - Si no hay contexto previo, acepta el intent del LLM o lo promueve
     *   desde `unknown`/`create_booking` solo con una frase explícita de
     *   creación/alta/registro de cliente.
     *
     * @param array<string,mixed>      $parsed
     * @param array<string,mixed>|null $previous_parsed
     * @param string                   $message
     * @return array<string,mixed>
     */
    private function apply_initial_create_client_intent_gate(
        array $parsed,
        ?array $previous_parsed,
        string $message
    ): array {
        $this->require_initial_intent_detector();
        $is_clear_create_client = AA_AI_Initial_Intent_Detector::is_clear_create_client_request($message);

        if ($previous_parsed !== null) {
            if (($parsed['intent'] ?? '') === 'create_client' || $is_clear_create_client) {
                $parsed = $this->build_neutral_current_turn_parsed();
            }
            return $parsed;
        }

        if (($parsed['intent'] ?? '') === 'create_client') {
            return $is_clear_create_client ? $parsed : $this->build_neutral_current_turn_parsed();
        }

        if (!in_array(($parsed['intent'] ?? ''), ['unknown', 'create_booking'], true)) {
            return $parsed;
        }

        if (!$is_clear_create_client) {
            return $parsed;
        }

        $this->require_conversation_contract();

        $parsed['intent']          = 'create_client';
        $parsed['sub_intent']      = AA_AI_Conversation_Contract::SUB_INTENT_OTHER;
        $parsed['affected_fields'] = [];
        $parsed['confidence']      = 0.95;

        return $parsed;
    }

    /**
     * Parsed neutral para ignorar un intent inicial no permitido dentro
     * de un flujo activo, sin cambiar el shape canónico del service.
     *
     * @return array<string,mixed>
     */
    private function build_neutral_current_turn_parsed(): array {
        $this->require_conversation_contract();

        return $this->normalize_parsed([
            'intent'          => 'unknown',
            'client_name'     => null,
            'service_name'    => null,
            'staff_name'      => null,
            'zone_name'       => null,
            'date_text'       => null,
            'time_text'       => null,
            'notes'           => null,
            'sub_intent'      => AA_AI_Conversation_Contract::SUB_INTENT_OTHER,
            'affected_fields' => [],
            'confidence'      => null,
        ]);
    }

    /**
     * Detecta una petición explícita de crear cliente mientras hay un
     * borrador de cita activo. Esto no cambia el intent ni corta el flujo:
     * solo habilita un aviso auxiliar después del dispatch normal.
     *
     * @param array<string,mixed>|null $previous_parsed
     */
    private function should_attach_create_client_assistive_notice(?array $previous_parsed, string $message): bool {
        if ($previous_parsed === null || (($previous_parsed['intent'] ?? '') !== 'create_booking')) {
            return false;
        }

        $this->require_initial_intent_detector();
        return AA_AI_Initial_Intent_Detector::is_clear_create_client_request($message);
    }

    /**
     * Añade un bloque secundario al reply normal de create_booking sin tocar
     * `cta`, `draft_state`, intent principal ni el texto de continuación.
     *
     * @param array<string,mixed> $intent_result
     */
    private function attach_create_client_assistive_notice(array &$intent_result): void {
        if (($intent_result['intent'] ?? '') !== 'create_booking') {
            return;
        }
        if (!isset($intent_result['resolution']) || !is_array($intent_result['resolution'])) {
            return;
        }
        if (!isset($intent_result['resolution']['reply_ui']) || !is_array($intent_result['resolution']['reply_ui'])) {
            return;
        }

        require_once dirname(__DIR__, 3) . '/application/ai/AI_Setup_Action_Link_Builder.php';
        $clients_action = (new AA_AI_Setup_Action_Link_Builder())->build_action_for_key('clients_create');

        $intent_result['resolution']['reply_ui']['assistive_notice'] = [
            'text'    => 'Por ahora no puedo crear clientes desde este asistente, pero puedes crearlo manualmente en la sección de Clientes.',
            'actions' => $clients_action !== null ? [$clients_action] : [],
        ];
    }

    /**
     * Paso 3.5 — puerta conservadora alineada con `isPureConfirmMessage`
     * en `aichat.js`, más frases cortas habituales en español que deben
     * poder cerrar la cita por chat sin pasar por el botón.
     *
     * Objetivo: el ÚNICO camino que ejecuta `AA_AI_Confirm_Booking_Use_Case`
     * desde el chat exige `sub_intent === confirm_draft` **y** que este
     * método devuelva true. Así evitamos falsos positivos tipo
     * "sí, el profesional es Adrian Fernandez" donde el "sí" engaña al LLM.
     *
     * @param string $message Mensaje del usuario (trim).
     */
    private function is_message_affirmation_for_server_booking(string $message): bool {
        if ($message === '') {
            return false;
        }

        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', trim($message)), 'UTF-8');

        // Frases explícitas de cierre aunque lleven coma (el filtro de
        // coma estricto de abajo las bloquearía sin este bloque previo).
        if (preg_match('/^(dale|ok|sí|si|vale)\s*,\s*(ag[ée]ndala|agendala|conf[íi]rmala|confirma)\b/u', $norm)) {
            return true;
        }
        if (preg_match('/^conf[íi]rma(r)?\s+la\s+cita\b/u', $norm)) {
            return true;
        }

        if (mb_strpos($message, ',', 0, 'UTF-8') !== false) {
            return false;
        }
        if (preg_match('/\bpero\b/u', $message)) {
            return false;
        }
        if (preg_match('/\bcambia(d|mos|r)?\b/u', $message) || preg_match('/\bcambiar\b/u', $message)) {
            return false;
        }
        if (preg_match('/\bmejor\b/u', $message)) {
            return false;
        }
        if (preg_match('/\bexcepto\b/u', $message)) {
            return false;
        }
        if (preg_match('/\bmañana\b/u', $message) || preg_match('/\bhoy\b/u', $message) || preg_match('/\bayer\b/u', $message)) {
            return false;
        }
        if (preg_match('/\bpasado\b/u', $message)) {
            return false;
        }
        if (preg_match('/\d{1,2}:\d{2}/', $message)) {
            return false;
        }
        if (preg_match('/\ba las\s+\d/u', $message)) {
            return false;
        }
        if (preg_match('/\d{1,2}\/\d{1,2}/', $message)) {
            return false;
        }
        if (preg_match('/\b(?:am|pm)\b/i', $message)) {
            return false;
        }

        // Correcciones / aclaraciones típicas: no son cierre de cita.
        if (preg_match('/\b(existe|profesional|cliente|servicio|zona|consultorio|correg|corrige|nombre)\b/u', $norm)) {
            return false;
        }

        $phrases = [
            'sí', 'si', 'confirmado', 'confirmar', 'ok', 'vale', 'dale', 'listo', 'correcto', 'de acuerdo',
            // Imperativos de una sola palabra (coincidencia exacta sobre $norm; p. ej. "agéndala para mañana" cae antes por exclusiones).
            'agéndala', 'agendala', 'agendarla', 'confírmala', 'confirmala',
        ];
        if (in_array($norm, $phrases, true)) {
            return true;
        }

        // Afirmaciones muy cortas de dos palabras ("sí dale", "ok dale").
        if (preg_match('/^(sí|si|ok|vale)\s+dale$/u', $norm)) {
            return true;
        }
        if (preg_match('/^dale\s+(sí|si|ok|vale)$/u', $norm)) {
            return true;
        }

        return false;
    }

    /**
     * Paso 3.5 — bloquea confirmación server-side si el resolution trae
     * señales de error, ambigüedad o datos aún no cerrados.
     *
     * @param array<string,mixed> $resolution Salida `intent_result.resolution` del handler.
     */
    private function resolution_blocks_server_booking_confirm(array $resolution): bool {
        $draft_state = isset($resolution['draft_state']) && is_array($resolution['draft_state'])
            ? $resolution['draft_state']
            : null;

        if ($draft_state === null) {
            return true;
        }

        $state = isset($draft_state['state']) ? (string) $draft_state['state'] : '';
        if ($state === 'incompatible') {
            return true;
        }

        $blockers = isset($draft_state['blockers']) && is_array($draft_state['blockers'])
            ? $draft_state['blockers']
            : [];
        if (count($blockers) > 0) {
            return true;
        }

        $required = isset($draft_state['required_literal']) && is_array($draft_state['required_literal'])
            ? $draft_state['required_literal']
            : [];
        if (count($required) > 0) {
            return true;
        }

        $amb = $resolution['ambiguous_fields'] ?? null;
        if (is_array($amb) && count($amb) > 0) {
            return true;
        }
        if (is_object($amb) && count(get_object_vars($amb)) > 0) {
            return true;
        }

        $feas = $resolution['feasibility'] ?? null;
        if (is_array($feas)) {
            foreach ($feas as $row) {
                if (is_array($row) && (($row['status'] ?? '') === 'incompatible')) {
                    return true;
                }
            }
        }

        $lookup = $resolution['lookup'] ?? null;
        $lookup_arr = is_array($lookup) ? $lookup : (is_object($lookup) ? get_object_vars($lookup) : []);
        foreach ($lookup_arr as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $st = isset($entry['status']) ? (string) $entry['status'] : '';
            if (in_array($st, ['no_match', 'ambiguous', 'missing'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Paso 3 — intenta cerrar el borrador server-side cuando el turno
     * actual clasifica como `confirm_draft` y el draft está listo.
     *
     * Reutiliza `AA_AI_Confirm_Booking_Use_Case`, el mismo caso de uso
     * que expone el endpoint `aa_ai_confirm_booking` al botón del
     * frontend. Este método NO reimplementa reglas de reservation,
     * assignment o auto-confirm: solo traduce `draft_state.draft` al
     * input del use case, delega, y formatea la respuesta al shape
     * del chat.
     *
     * Política:
     *   - sub_intent distinto de `confirm_draft` → null (flujo normal).
     *   - draft_state sin `ready_for_confirmation` → null (fallback al
     *     reply builder existente, que ya explica qué falta).
     *   - input inválido (faltan IDs pese a `ready_for_confirmation`)
     *     → null (defensa; el caller sigue flujo normal).
     *   - use case ok      → respuesta de éxito con parsed reseteado.
     *   - use case error   → respuesta tipo "no pude confirmar" que
     *     preserva el parsed actual para que el usuario pueda reintentar.
     *
     * Paso 3.5 — además:
     *   - el texto del usuario debe pasar `is_message_affirmation_for_server_booking`;
     *   - el resolution no debe traer blockers, lookup roto, feasibility
     *     incompatible, required_literal pendiente ni ambigüedades.
     *
     * @param array<string,mixed> $parsed        Parsed merged del turno actual.
     * @param array<string,mixed> $intent_result Salida de dispatch_intent ya calculada.
     * @param string                $message      Mensaje del usuario (trim).
     * @return array<string,mixed>|null Respuesta cerrada o null si no aplica.
     */
    private function try_confirm_by_sub_intent(array $parsed, array $intent_result, string $message): ?array {
        $this->require_conversation_contract();

        $sub_intent = $parsed['sub_intent'] ?? null;
        if ($sub_intent !== AA_AI_Conversation_Contract::SUB_INTENT_CONFIRM_DRAFT) {
            return null;
        }

        if (!$this->is_message_affirmation_for_server_booking($message)) {
            $this->log_turn_debug([
                'parsed'         => $parsed,
                'sub_intent'     => $sub_intent,
                'confirm_action' => 'rejected_not_affirmation_utterance',
            ]);
            return null;
        }

        $resolution  = isset($intent_result['resolution']) && is_array($intent_result['resolution'])
            ? $intent_result['resolution']
            : [];

        if ($this->resolution_blocks_server_booking_confirm($resolution)) {
            $this->log_turn_debug([
                'parsed'         => $parsed,
                'sub_intent'     => $sub_intent,
                'confirm_action' => 'rejected_resolution_guard',
            ]);
            return null;
        }

        $draft_state = isset($resolution['draft_state']) && is_array($resolution['draft_state'])
            ? $resolution['draft_state']
            : null;
        $state       = $draft_state !== null ? ($draft_state['state'] ?? null) : null;
        $draft       = $draft_state !== null && isset($draft_state['draft']) && is_array($draft_state['draft'])
            ? $draft_state['draft']
            : null;

        if ($state !== 'ready_for_confirmation' || $draft === null) {
            $this->log_turn_debug([
                'parsed'         => $parsed,
                'sub_intent'     => $sub_intent,
                'confirm_action' => 'rejected_not_ready',
                'draft_state'    => is_string($state) ? $state : null,
            ]);
            return null;
        }

        $input = $this->build_confirm_input_from_draft($draft);
        if ($input === null) {
            $this->log_turn_debug([
                'parsed'         => $parsed,
                'sub_intent'     => $sub_intent,
                'confirm_action' => 'rejected_invalid_draft',
            ]);
            return null;
        }

        require_once dirname(__DIR__, 3) . '/application/ai/AI_Confirm_Booking_Use_Case.php';
        require_once dirname(__DIR__, 3) . '/application/booking/CreateReservationUseCase.php';
        require_once dirname(__DIR__, 3) . '/services/confirm-backend-service.php';

        $result = (new AA_AI_Confirm_Booking_Use_Case())->execute($input);

        if (($result['status'] ?? null) === 'ok') {
            $this->log_turn_debug([
                'parsed'         => $parsed,
                'sub_intent'     => $sub_intent,
                'confirm_action' => 'ok',
                'reservation_id' => isset($result['reservation_id']) ? (int) $result['reservation_id'] : null,
                'assignment_id'  => isset($result['assignment_id']) ? (int) $result['assignment_id'] : null,
            ]);
            return $this->build_confirm_success_response($result);
        }

        $this->log_turn_debug([
            'parsed'         => $parsed,
            'sub_intent'     => $sub_intent,
            'confirm_action' => 'error',
            'stage'          => isset($result['stage']) ? (string) $result['stage'] : null,
            'error_message'  => isset($result['message']) ? (string) $result['message'] : null,
        ]);
        return $this->build_confirm_error_response($parsed, $intent_result, $result);
    }

    /**
     * Traduce el `draft_state.draft` al input exacto que espera
     * `AA_AI_Confirm_Booking_Use_Case::execute()`.
     *
     * El shape del draft es el proyectado por `AA_Booking_Draft_Aggregator`:
     *   client/service/staff/zone {id, ...}, datetime {local_datetime},
     *   duration {minutes}, assignment {mode, assignment_id?}.
     *
     * Devuelve `null` si algún ID/campo requerido no es válido; el
     * use case exige esos invariantes y preferimos fallar temprano con
     * un fallback al flujo normal antes que invocar al use case con
     * basura.
     *
     * @param array<string,mixed> $draft
     * @return array<string,mixed>|null
     */
    private function build_confirm_input_from_draft(array $draft): ?array {
        $client     = isset($draft['client'])     && is_array($draft['client'])     ? $draft['client']     : null;
        $service    = isset($draft['service'])    && is_array($draft['service'])    ? $draft['service']    : null;
        $staff      = isset($draft['staff'])      && is_array($draft['staff'])      ? $draft['staff']      : null;
        $zone       = isset($draft['zone'])       && is_array($draft['zone'])       ? $draft['zone']       : null;
        $datetime   = isset($draft['datetime'])   && is_array($draft['datetime'])   ? $draft['datetime']   : null;
        $duration   = isset($draft['duration'])   && is_array($draft['duration'])   ? $draft['duration']   : null;
        $assignment = isset($draft['assignment']) && is_array($draft['assignment']) ? $draft['assignment'] : null;

        $client_id        = $client   !== null && isset($client['id'])            ? (int) $client['id']            : 0;
        $service_id       = $service  !== null && isset($service['id'])           ? (int) $service['id']           : 0;
        $staff_id         = $staff    !== null && isset($staff['id'])             ? (int) $staff['id']             : 0;
        $zone_id          = $zone     !== null && isset($zone['id'])              ? (int) $zone['id']              : 0;
        $start_local      = $datetime !== null && isset($datetime['local_datetime']) ? (string) $datetime['local_datetime'] : '';
        $duration_minutes = $duration !== null && isset($duration['minutes'])     ? (int) $duration['minutes']     : 0;
        $mode             = $assignment !== null && isset($assignment['mode'])    ? (string) $assignment['mode']   : '';
        $assignment_id    = $assignment !== null && isset($assignment['assignment_id']) ? (int) $assignment['assignment_id'] : 0;

        if ($client_id <= 0 || $service_id <= 0 || $staff_id <= 0 || $zone_id <= 0) {
            return null;
        }
        if ($start_local === '' || $duration_minutes <= 0) {
            return null;
        }
        if ($mode !== 'reuse' && $mode !== 'create_new') {
            return null;
        }
        if ($mode === 'reuse' && $assignment_id <= 0) {
            return null;
        }

        $input = [
            'client_id'        => $client_id,
            'service_id'       => $service_id,
            'staff_id'         => $staff_id,
            'zone_id'          => $zone_id,
            'start_datetime'   => $start_local,
            'duration_minutes' => $duration_minutes,
            'assignment_mode'  => $mode,
        ];
        if ($mode === 'reuse') {
            $input['assignment_id'] = $assignment_id;
        }

        return $input;
    }

    /**
     * Respuesta de éxito tras confirmar server-side. Misma forma del
     * envelope que el flujo normal del chat + señales específicas para
     * que el cliente pueda:
     *   - limpiar `lastParsedInput` y `lastDraftState`,
     *   - disparar `aa-assignment-created` para refrescar el calendario,
     *   - deshabilitar el CTA de confirmación del turno previo.
     *
     * `intent_result.status = 'booking_confirmed'` es el discriminador
     * que consume el frontend en `aichat.js`.
     *
     * @param array<string,mixed> $use_case_result Salida ok de `AA_AI_Confirm_Booking_Use_Case::execute()`.
     */
    private function build_confirm_success_response(array $use_case_result): array {
        $text = 'Cita confirmada. Cita agendada.';

        $this->require_conversation_contract();

        $parsed = $this->normalize_parsed([
            'intent'          => 'unknown',
            'client_name'     => null,
            'service_name'    => null,
            'staff_name'      => null,
            'zone_name'       => null,
            'date_text'       => null,
            'time_text'       => null,
            'notes'           => null,
            'sub_intent'      => AA_AI_Conversation_Contract::SUB_INTENT_CONFIRM_DRAFT,
            'affected_fields' => [],
            'confidence'      => 1.0,
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

        $confirmation = [
            'reservation_id'     => isset($use_case_result['reservation_id']) ? (int) $use_case_result['reservation_id'] : 0,
            'assignment_id'      => isset($use_case_result['assignment_id']) ? (int) $use_case_result['assignment_id'] : 0,
            'created_assignment' => !empty($use_case_result['created_assignment']),
            'confirmed'          => !empty($use_case_result['confirmed']),
        ];

        return [
            'ok'            => true,
            'reply_text'    => $text,
            'parsed'        => $parsed,
            'intent_result' => [
                'intent'     => 'create_booking',
                'status'     => 'booking_confirmed',
                'reply'      => $text,
                'resolution' => [
                    'reply_ui'     => $reply_ui,
                    'draft_state'  => null,
                    'confirmation' => $confirmation,
                ],
            ],
        ];
    }

    /**
     * Respuesta cuando el use case de confirmación falla server-side.
     *
     * Política:
     *   - Preservamos el `parsed` actual para que el draft siga vivo y
     *     el usuario pueda reintentar o corregir sin perder contexto.
     *   - Sustituimos el texto del `reply_ui` por uno explícito de
     *     error (incluye `stage` para pistas rápidas en QA).
     *   - Marcamos `intent_result.status = 'booking_confirm_failed'` y
     *     anexamos `resolution.confirmation_error` para trazabilidad.
     *
     * El frontend trata esto como un mensaje de chat normal: el usuario
     * ve el aviso y el botón CTA del turno anterior sigue habilitado
     * (reintento), igual que pasa hoy con el endpoint directo cuando
     * devuelve error.
     *
     * @param array<string,mixed> $parsed          Parsed merged del turno.
     * @param array<string,mixed> $intent_result   Resultado ya calculado de dispatch.
     * @param array<string,mixed> $use_case_result Salida error del use case.
     */
    private function build_confirm_error_response(array $parsed, array $intent_result, array $use_case_result): array {
        $stage = isset($use_case_result['stage']) ? (string) $use_case_result['stage'] : 'unknown';
        $text  = 'No pude confirmar la cita (' . $stage . '). Intenta de nuevo o corrige el borrador.';

        $resolution = isset($intent_result['resolution']) && is_array($intent_result['resolution'])
            ? $intent_result['resolution']
            : [];

        $reply_ui = isset($resolution['reply_ui']) && is_array($resolution['reply_ui'])
            ? $resolution['reply_ui']
            : [
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
        $reply_ui['text'] = $text;

        $resolution['reply_ui']           = $reply_ui;
        $resolution['confirmation_error'] = [
            'stage'   => $stage,
            'message' => isset($use_case_result['message']) ? (string) $use_case_result['message'] : '',
        ];

        $intent_result['status']     = 'booking_confirm_failed';
        $intent_result['reply']      = $text;
        $intent_result['resolution'] = $resolution;

        return [
            'ok'            => true,
            'reply_text'    => $text,
            'parsed'        => $parsed,
            'intent_result' => $intent_result,
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

        if ($intent === 'create_client') {
            return $this->handle_create_client_intent($parsed);
        }

        if ($intent === 'unknown') {
            return $this->handle_unknown_intent($parsed, $user_message);
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
        require_once dirname(__DIR__, 3) . '/application/ai/AI_Booking_Setup_Check_Use_Case.php';

        $setup_result = (new AA_AI_Booking_Setup_Check_Use_Case())->execute($parsed);
        if (($setup_result['status'] ?? null) === 'setup_incomplete') {
            return $setup_result;
        }

        require_once __DIR__ . '/class-aa-ai-create-booking-intent-handler.php';

        $handler = new AA_AI_Create_Booking_Intent_Handler();

        return $handler->handle($parsed);
    }

    /**
     * Respuesta temporal controlada para creación de clientes desde chat.
     *
     * No ejecuta creación real, no abre formularios y no inicia conversación
     * multi-turn. Solo informa que la acción aún no está habilitada.
     *
     * @param array<string,mixed> $parsed
     * @return array<string,mixed>
     */
    private function handle_create_client_intent(array $parsed): array {
        $ui_text = 'Por ahora no puedo crear clientes desde este asistente, pero puedes crearlo manualmente en la sección de Clientes.';
        require_once dirname(__DIR__, 3) . '/application/ai/AI_Setup_Action_Link_Builder.php';
        $clients_action = (new AA_AI_Setup_Action_Link_Builder())->build_action_for_key('clients_create');

        $reply_ui = [
            'text'       => $ui_text,
            'cta'        => 'fix_blocker',
            'highlights' => [],
            'choices'    => [],
            'draft_echo' => [
                'client'   => null,
                'service'  => null,
                'staff'    => null,
                'zone'     => null,
                'datetime' => null,
            ],
            'actions'    => $clients_action !== null ? [$clients_action] : [],
        ];

        return [
            'intent'     => 'create_client',
            'status'     => 'not_implemented',
            'reply'      => $ui_text,
            'resolution' => [
                'parsed_input' => $parsed,
                'reply_ui'     => $reply_ui,
                'draft_state'  => null,
            ],
        ];
    }

    /**
     * Respuesta neutral cuando no se pudo clasificar la intención.
     *
     * No pide campos de cita ni sugiere que `create_booking` haya sido
     * detectado; solo abre la interacción para que el administrador
     * reformule qué quiere hacer.
     *
     * @param array  $parsed
     * @param string $user_message Mensaje original del usuario (trim).
     * @return array
     */
    private function handle_unknown_intent(array $parsed, string $user_message): array {
        $social_text = $this->resolve_simple_unknown_social_reply($user_message);
        $ui_text     = $social_text !== null ? $social_text : $this->resolve_unknown_ui_text($user_message);
        $show_intent_choices = $this->should_offer_intent_choices_for_unknown($user_message, $social_text);

        $reply_ui = [
            'text'       => $ui_text,
            'cta'        => $show_intent_choices ? 'choose_intent' : 'noop',
            'highlights' => [],
            'choices'    => $show_intent_choices ? $this->build_unknown_intent_choices() : [],
            'draft_echo' => [
                'client'   => null,
                'service'  => null,
                'staff'    => null,
                'zone'     => null,
                'datetime' => null,
            ],
        ];

        return [
            'intent'     => 'unknown',
            'status'     => 'unknown_intent',
            'reply'      => $ui_text,
            'resolution' => [
                'parsed_input' => $parsed,
                'reply_ui'     => $reply_ui,
            ],
        ];
    }

    private function should_offer_intent_choices_for_unknown(string $user_message, ?string $social_text): bool {
        if ($social_text === null) {
            return true;
        }

        $normalized = $this->normalize_simple_unknown_social_message($user_message);
        $opening_messages = [
            'hola',
            'hi',
            'hey',
            'buenos dias',
            'buen dia',
            'buenas tardes',
            'buenas noches',
            'como estas',
            'como esta',
            'que tal',
        ];

        return in_array($normalized, $opening_messages, true);
    }

    /**
     * @return array<int, array{key:string,label:string,message:string}>
     */
    private function build_unknown_intent_choices(): array {
        return [
            [
                'key'     => 'create_booking',
                'label'   => 'Crear una cita',
                'message' => 'Quiero crear una cita',
            ],
        ];
    }

    /**
     * Respuestas sociales exactas para mensajes que ya quedaron en `unknown`.
     * No usa contains ni fuzzy matching: frases compuestas caen al fallback.
     */
    private function resolve_simple_unknown_social_reply(string $user_message): ?string {
        $normalized = $this->normalize_simple_unknown_social_message($user_message);
        if ($normalized === '') {
            return null;
        }

        $replies = [
            'hola'          => 'Hola. ¿En qué puedo ayudarte?',
            'hi'            => 'Hola. ¿En qué puedo ayudarte?',
            'hey'           => 'Hola. ¿En qué puedo ayudarte?',
            'buenos dias'   => 'Buenos días. ¿En qué puedo ayudarte?',
            'buen dia'      => 'Buenos días. ¿En qué puedo ayudarte?',
            'buenas tardes' => 'Buenas tardes. ¿En qué puedo ayudarte?',
            'buenas noches' => 'Buenas noches. ¿En qué puedo ayudarte?',
            'como estas'    => 'Bien, gracias. Estoy listo para ayudarte con tu agenda. ¿Qué necesitas?',
            'como esta'     => 'Bien, gracias. Estoy listo para ayudarte con tu agenda. ¿Qué necesitas?',
            'que tal'       => 'Todo bien. ¿En qué puedo ayudarte?',
            'gracias'       => 'De nada.',
            'muchas gracias' => 'De nada.',
            'mil gracias'   => 'De nada.',
            'ok'            => 'Perfecto. ¿Qué necesitas hacer?',
            'vale'          => 'Perfecto. ¿Qué necesitas hacer?',
            'entiendo'      => 'Perfecto. ¿Qué necesitas hacer?',
            'entendido'     => 'Perfecto. ¿Qué necesitas hacer?',
            'perfecto'      => 'Perfecto. ¿Qué necesitas hacer?',
        ];

        return $replies[$normalized] ?? null;
    }

    private function normalize_simple_unknown_social_message(string $message): string {
        $normalized = mb_strtolower(trim($message), 'UTF-8');
        if ($normalized === '') {
            return '';
        }

        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);
        $normalized = preg_replace('/^[\s\.,!¡\?¿…]+|[\s\.,!¡\?¿…]+$/u', '', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', (string) $normalized);

        return is_string($normalized) ? trim($normalized) : '';
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
     * Texto neutral para intención no clasificada.
     *
     * @param string $user_message Mensaje original (trim), usado solo para
     *                             variar copy de forma determinista.
     */
    private function resolve_unknown_ui_text(string $user_message): string {
        $variants = [
            'No entendí qué quieres hacer. Puedo ayudarte con tu agenda. ¿Qué quieres hacer?',
            'No logré identificar la intención. Puedo ayudarte con tu agenda. ¿Qué necesitas hacer?',
            'No me quedó claro qué quieres hacer. Puedo ayudarte con tu agenda. Dime qué necesitas.',
        ];
        $hash = (int) sprintf('%u', crc32($user_message));
        $idx  = $hash % count($variants);

        return $variants[$idx];
    }

    /**
     * Texto breve para intents reconocidos que aún no tienen handler dedicado.
     *
     * @param string $intent         Intent normalizado.
     * @param string $user_message   Mensaje original (trim).
     */
    private function resolve_unimplemented_ui_text(string $intent, string $user_message): string {
        $fallback = 'Todavía no tengo habilitada esa acción desde el chat.';

        if ($intent === 'check_availability') {
            $variants = [
                'Aún no puedo consultar horarios libres desde el chat. Puedes revisar la disponibilidad manualmente en el timeline del calendario.',
                'Todavía no tengo habilitada la consulta automática de horarios disponibles desde este chat. Por ahora puedes revisarlos directamente en el timeline del calendario.',
                'Por ahora no puedo mostrarte disponibilidad en tiempo real desde aquí. Puedes consultar los espacios libres manualmente en el calendario.',
            ];

            $hash = (int) sprintf('%u', crc32($user_message));
            $idx  = $hash % count($variants);

            return $variants[$idx];
        }

        return $fallback;
    }

    /**
     * Prompt de sistema para extracción estructurada + clasificación
     * de subintención conversacional.
     *
     * Fase 1: el prompt ya pide `sub_intent`, `affected_fields` y
     * `confidence`, pero ESAS señales todavía NO gobiernan el dispatch.
     * El servidor las normaliza y las emite como señal estructurada,
     * lista para que el merger y el orquestador las consuman en pasos
     * posteriores.
     *
     * @return string
     */
    private function build_system_prompt() {
        return <<<'PROMPT'
Eres un parser de una agenda de citas. Tu trabajo es (a) extraer datos estructurados del mensaje del administrador y (b) clasificar qué está haciendo el usuario en este turno respecto al borrador en curso.

Responde SIEMPRE con un objeto JSON y NADA MÁS. Sin explicaciones, sin texto extra.

CAMPOS (exactamente estos 11):
- "intent": "create_booking" | "create_client" | "check_availability" | "find_client" | "list_services" | "unknown"
- "client_name": string | null
- "service_name": string | null
- "staff_name": string | null
- "zone_name": string | null
- "date_text": string | null
- "time_text": string | null
- "notes": string | null
- "sub_intent": "new_booking" | "fill_missing_fields" | "modify_fields" | "confirm_draft" | "cancel_draft" | "ask_availability" | "ask_draft_state" | "other"
- "affected_fields": array con un subconjunto de ["client","service","staff","zone","date","time","notes"]
- "confidence": número entre 0 y 1 (tu confianza en la clasificación de sub_intent)

CÓMO IDENTIFICAR ROLES EN LA ORACIÓN:
- "agendar/agenda/agéndame A [nombre]" → client_name (la persona a quien se agenda)
- "para [servicio]" → service_name (el servicio solicitado)
- "con [nombre]" → staff_name (el profesional que atiende)
- "en [lugar/zona]" → zone_name
- Referencias temporales como "mañana", "el lunes", "15 de abril" → date_text (copiar tal cual)
- Referencias de hora como "a las 5", "4pm", "16:00" → time_text (copiar tal cual)

REGLAS DE EXTRACCIÓN:
- Si un dato no aparece en el mensaje → null.
- No inventes datos.
- Si la intención de alto nivel no es clara → intent:"unknown".
- Extrae nombres propios tal cual aparecen.
- Usa intent:"create_client" solo cuando el usuario pida explícitamente crear, agregar, registrar o dar de alta un cliente. Debe aparecer una señal clara como "cliente", "nuevo cliente", "crear cliente", "registrar cliente", "dar de alta cliente" o "agregar cliente".
- No uses intent:"create_client" para citas, disponibilidad o agenda. "agenda a Juan", "crea cita para Juan" y "agrega a Juan a una cita" son de cita/agenda, no creación de cliente.

REGLAS DE SUB_INTENT:
- "new_booking": primera mención de una cita completa o casi completa, típicamente sin contexto previo.
- "fill_missing_fields": el usuario aporta datos que el sistema había pedido, sin cambiar nada ya fijado.
- "modify_fields": el usuario cambia un dato ya fijado. Aplica aunque NO traiga un nuevo valor concreto (p. ej. "quiero cambiar el servicio" → modify_fields con affected_fields:["service"] y service_name:null).
- "confirm_draft": afirmación pura sobre un borrador ya propuesto ("sí", "ok", "confirmar", "de acuerdo", "dale").
- "cancel_draft": abortar el borrador actual ("cancela", "ya no gracias", "olvídalo", "déjalo", "mejor no").
- "ask_availability": pregunta sobre disponibilidad sin proponer agendar aún ("a qué hora tiene libre", "¿a las 5 está libre?", "¿hay espacio mañana?").
- "ask_draft_state": pregunta sobre qué tiene ya el borrador actual ("¿qué cliente tengo?", "¿qué datos llevo?").
- "other": saludo, agradecimiento, charla fuera de alcance, o cualquier cosa que no encaje en las anteriores.

REGLAS DE AFFECTED_FIELDS:
- Solo incluye los campos que el usuario está creando, completando o modificando en ESTE mensaje.
- Para "confirm_draft", "cancel_draft", "ask_draft_state" y "other" debe ser [].
- Para "ask_availability" puede incluir el/los campos sobre los que pregunta (típicamente ["time"] o ["date","time"]).
- Para "modify_fields" incluye los campos que el usuario quiere cambiar aunque aún no dé el nuevo valor.
- Usa exactamente estas claves cortas: client, service, staff, zone, date, time, notes.

REGLAS DE CONFIDENCE:
- Número entre 0 y 1.
- Usa >=0.8 si el mensaje es inequívoco, 0.5-0.8 si hay alguna ambigüedad, <0.5 si dudas entre varias subintenciones.

EJEMPLOS:

Input: "crea una cita"
Output: {"intent":"create_booking","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"new_booking","affected_fields":[],"confidence":0.95}

Input: "quiero agendar una cita"
Output: {"intent":"create_booking","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"new_booking","affected_fields":[],"confidence":0.95}

Input: "programa una cita"
Output: {"intent":"create_booking","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"new_booking","affected_fields":[],"confidence":0.95}

Input: "Agéndame a José mañana a las 5 para cejas con Anahí"
Output: {"intent":"create_booking","client_name":"José","service_name":"cejas","staff_name":"Anahí","zone_name":null,"date_text":"mañana","time_text":"a las 5","notes":null,"sub_intent":"new_booking","affected_fields":["client","service","staff","date","time"],"confidence":0.95}

Input: "Agenda una cita para María López el viernes a las 10 para corte de cabello"
Output: {"intent":"create_booking","client_name":"María López","service_name":"corte de cabello","staff_name":null,"zone_name":null,"date_text":"el viernes","time_text":"a las 10","notes":null,"sub_intent":"new_booking","affected_fields":["client","service","date","time"],"confidence":0.95}

Input: "armando hoyos, en consultorio 3"
Output: {"intent":"create_booking","client_name":"armando hoyos","service_name":null,"staff_name":null,"zone_name":"consultorio 3","date_text":null,"time_text":null,"notes":null,"sub_intent":"fill_missing_fields","affected_fields":["client","zone"],"confidence":0.9}

Input: "mejor para consulta general"
Output: {"intent":"create_booking","client_name":null,"service_name":"consulta general","staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"modify_fields","affected_fields":["service"],"confidence":0.9}

Input: "quiero cambiar el servicio"
Output: {"intent":"create_booking","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"modify_fields","affected_fields":["service"],"confidence":0.9}

Input: "cambia la hora a las 6"
Output: {"intent":"create_booking","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":"a las 6","notes":null,"sub_intent":"modify_fields","affected_fields":["time"],"confidence":0.95}

Input: "sí"
Output: {"intent":"unknown","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"confirm_draft","affected_fields":[],"confidence":0.9}

Input: "ok"
Output: {"intent":"unknown","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"confirm_draft","affected_fields":[],"confidence":0.85}

Input: "ya no gracias"
Output: {"intent":"unknown","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"cancel_draft","affected_fields":[],"confidence":0.9}

Input: "cancela"
Output: {"intent":"unknown","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"cancel_draft","affected_fields":[],"confidence":0.98}

Input: "a qué hora tiene libre"
Output: {"intent":"check_availability","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"ask_availability","affected_fields":["time"],"confidence":0.9}

Input: "¿a las 5 está libre?"
Output: {"intent":"check_availability","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":"a las 5","notes":null,"sub_intent":"ask_availability","affected_fields":["time"],"confidence":0.9}

Input: "Ponle cita a Pedro con la Dra. Gómez para limpieza dental mañana a las 3 en sucursal norte"
Output: {"intent":"create_booking","client_name":"Pedro","service_name":"limpieza dental","staff_name":"Dra. Gómez","zone_name":"sucursal norte","date_text":"mañana","time_text":"a las 3","notes":null,"sub_intent":"new_booking","affected_fields":["client","service","staff","zone","date","time"],"confidence":0.95}

Input: "crea cliente Juan Pérez"
Output: {"intent":"create_client","client_name":"Juan Pérez","service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"other","affected_fields":[],"confidence":0.95}

Input: "agrega cliente María López con correo"
Output: {"intent":"create_client","client_name":"María López","service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"other","affected_fields":[],"confidence":0.95}

Input: "registra nuevo cliente Pedro Gómez"
Output: {"intent":"create_client","client_name":"Pedro Gómez","service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"other","affected_fields":[],"confidence":0.95}

Input: "agrega a Juan a una cita"
Output: {"intent":"create_booking","client_name":"Juan","service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"new_booking","affected_fields":["client"],"confidence":0.8}

Input: "Qué servicios tienen disponibles?"
Output: {"intent":"list_services","client_name":null,"service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"other","affected_fields":[],"confidence":0.8}

Input: "Busca al cliente Ana Martínez"
Output: {"intent":"find_client","client_name":"Ana Martínez","service_name":null,"staff_name":null,"zone_name":null,"date_text":null,"time_text":null,"notes":null,"sub_intent":"other","affected_fields":[],"confidence":0.8}
PROMPT;
    }

    /**
     * Normaliza el objeto parseado para garantizar shape uniforme.
     *
     * Incluye los 8 campos legacy (`intent` + 7 data fields) y los 3
     * campos nuevos de Fase 1 (`sub_intent`, `affected_fields`,
     * `confidence`), normalizados vía `AA_AI_Conversation_Contract`.
     *
     * Los 3 campos nuevos se EMITEN siempre en el output aunque el LLM
     * no los devuelva (defaults seguros). Esto blinda a consumers río
     * abajo que asumen shape estable. En esta fase NO gobiernan dispatch
     * ni merge: existen como señal estructurada lista para Paso 2.
     *
     * @param array $parsed
     * @return array
     */
    private function normalize_parsed(array $parsed) {
        $normalized = ['intent' => $parsed['intent'] ?? null];

        foreach (self::PARSED_DATA_FIELDS as $field) {
            $value = isset($parsed[$field]) && $parsed[$field] !== '' ? $parsed[$field] : null;
            $normalized[$field] = $value;
        }

        if (!in_array($normalized['intent'], self::VALID_INTENTS, true)) {
            $normalized['intent'] = 'unknown';
        }

        $this->require_conversation_contract();

        $normalized['sub_intent']      = AA_AI_Conversation_Contract::normalize_sub_intent($parsed['sub_intent'] ?? null);
        $normalized['affected_fields'] = AA_AI_Conversation_Contract::normalize_affected_fields($parsed['affected_fields'] ?? null);
        $normalized['confidence']      = AA_AI_Conversation_Contract::normalize_confidence($parsed['confidence'] ?? null);

        return $normalized;
    }

    /**
     * Lazy-require del contrato de dominio. Sigue el mismo patrón que
     * `build_merger()`: los `require_once` viven en los métodos que
     * los necesitan, no en el bootstrap del módulo AI.
     */
    private function require_conversation_contract(): void {
        require_once dirname(__DIR__, 3) . '/domain/ai/class-aa-ai-conversation-contract.php';
    }

    /**
     * Lazy-require del detector puro de clasificación inicial.
     */
    private function require_initial_intent_detector(): void {
        require_once dirname(__DIR__, 3) . '/domain/ai/class-aa-ai-initial-intent-detector.php';
    }

    /**
     * Respuesta de error homogénea cuando el asistente no está disponible o
     * la salida del modelo no es utilizable (evita merge/dispatch con draft).
     *
     * @param array<string,mixed> $context Metadatos para log_turn_debug / debug AJAX.
     * @return array<string,mixed>
     */
    private function build_ai_unavailable_response(array $context) {
        $this->log_turn_debug(array_merge(
            [
                'error_code'    => 'ai_unavailable',
                'short_circuit' => 'ai_unavailable',
            ],
            $context
        ));

        return [
            'ok'         => false,
            'reply_text' => null,
            'parsed'     => null,
            'error'      => self::AI_UNAVAILABLE_USER_MESSAGE,
            'code'       => 'ai_unavailable',
            'debug'      => array_merge(
                [
                    'error_code' => 'ai_unavailable',
                ],
                $context
            ),
        ];
    }

    /**
     * Indica si el contenido crudo del LLM es apto para continuar el flujo.
     *
     * Requiere string no vacío (tras trim), JSON extraíble y decodificable,
     * objeto/array no vacío, intersección con claves del contrato del parser
     * y al menos una señal semántica (intent/sub_intent no vacíos, dato de
     * cita, affected_fields no vacío o confidence numérico). Un objeto `{}`
     * decodificado como array vacío en PHP no pasa.
     *
     * @param mixed $content Valor de message.content del proveedor.
     */
    private function is_llm_content_usable($content): bool {
        if (!is_string($content) || trim($content) === '') {
            return false;
        }

        $json_candidate = $this->extract_json_payload(trim($content));
        if ($json_candidate === null || trim($json_candidate) === '') {
            return false;
        }

        $parsed_raw = json_decode($json_candidate, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed_raw)) {
            return false;
        }

        if ($parsed_raw === []) {
            return false;
        }

        $contract_flip = array_flip(array_merge(
            ['intent', 'sub_intent', 'affected_fields', 'confidence'],
            self::PARSED_DATA_FIELDS
        ));

        $intersect = array_intersect_key($parsed_raw, $contract_flip);
        if ($intersect === []) {
            return false;
        }

        if (isset($intersect['intent']) && is_string($intersect['intent']) && trim($intersect['intent']) !== '') {
            return true;
        }

        if (isset($intersect['sub_intent']) && is_string($intersect['sub_intent']) && trim($intersect['sub_intent']) !== '') {
            return true;
        }

        foreach (self::PARSED_DATA_FIELDS as $field) {
            if (!isset($intersect[$field])) {
                continue;
            }
            $v = $intersect[$field];
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
        }

        if (isset($intersect['affected_fields']) && is_array($intersect['affected_fields']) && count($intersect['affected_fields']) > 0) {
            return true;
        }

        if (isset($intersect['confidence']) && is_numeric($intersect['confidence'])) {
            return true;
        }

        return false;
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

    /**
     * Telemetría mínima del turno conversacional (Fase 1).
     *
     * Pensada para depurar la clasificación de `sub_intent` y el flujo
     * del parsed durante la transición al nuevo contrato. Gated por
     * `AA_AI_CHAT_DEBUG` (define PHP) para no ensuciar logs de producción.
     * Si el define no está presente o es falsy, el método es un no-op.
     *
     * Formato: una sola línea JSON por turno con una clave raíz
     * `AA_AI_CHAT_TURN` para grep fácil.
     *
     * Campos truncados/resumidos para mantener el log acotado:
     *   - `message` se limita a 300 caracteres (coincide con el cap del
     *     frontend en `aichat.js`).
     *   - `previous_parsed` se resume a las claves con valor no trivial.
     *   - `parsed_raw` solo incluye los campos que el contrato conoce;
     *     el resto del payload del LLM se descarta.
     *
     * @param array<string,mixed> $context
     */
    private function log_turn_debug(array $context): void {
        if (!defined('AA_AI_CHAT_DEBUG') || !AA_AI_CHAT_DEBUG) {
            return;
        }

        $payload = [
            'message'         => $this->truncate_for_log($context['message'] ?? null, 300),
            'previous_parsed' => $this->summarize_parsed_for_log($context['previous_parsed'] ?? null),
            'parsed_raw'      => $this->summarize_parsed_raw_for_log($context['parsed_raw'] ?? null),
            'parsed'          => $this->summarize_parsed_for_log($context['parsed'] ?? null),
            'sub_intent'      => $context['sub_intent'] ?? null,
            'affected_fields' => isset($context['affected_fields']) && is_array($context['affected_fields'])
                ? $context['affected_fields']
                : null,
        ];

        if (isset($context['short_circuit'])) {
            $payload['short_circuit'] = (string) $context['short_circuit'];
        }
        if (isset($context['error'])) {
            $payload['error'] = (string) $context['error'];
        }
        if (isset($context['raw_content'])) {
            $payload['raw_content'] = $this->truncate_for_log($context['raw_content'], 500);
        }

        // Paso 3: marcadores del nuevo camino server-side de confirm_draft.
        // `confirm_action` toma uno de:
        //   'ok' | 'error' | 'rejected_not_ready' | 'rejected_invalid_draft'
        if (isset($context['confirm_action'])) {
            $payload['confirm_action'] = (string) $context['confirm_action'];
        }
        if (isset($context['draft_state'])) {
            $payload['draft_state'] = is_string($context['draft_state']) ? $context['draft_state'] : null;
        }
        if (array_key_exists('reservation_id', $context)) {
            $payload['reservation_id'] = $context['reservation_id'];
        }
        if (array_key_exists('assignment_id', $context)) {
            $payload['assignment_id'] = $context['assignment_id'];
        }
        if (isset($context['stage'])) {
            $payload['stage'] = (string) $context['stage'];
        }
        if (isset($context['error_message'])) {
            $payload['error_message'] = $this->truncate_for_log((string) $context['error_message'], 240);
        }
        if (isset($context['orchestration_v35'])) {
            $payload['orchestration_v35'] = (string) $context['orchestration_v35'];
        }

        $line = wp_json_encode(['AA_AI_CHAT_TURN' => $payload]);
        if ($line === false) {
            return;
        }

        error_log($line);
    }

    /**
     * @param mixed $value
     */
    private function truncate_for_log($value, int $max): ?string {
        if (!is_string($value)) {
            return null;
        }
        if (mb_strlen($value, 'UTF-8') <= $max) {
            return $value;
        }
        return mb_substr($value, 0, $max, 'UTF-8') . '…';
    }

    /**
     * Resumen de un parsed normalizado: solo claves con valor no trivial
     * (no null, no string vacío, no array vacío).
     *
     * @param mixed $parsed
     */
    private function summarize_parsed_for_log($parsed): ?array {
        if (!is_array($parsed)) {
            return null;
        }

        $out = [];
        foreach ($parsed as $k => $v) {
            if ($v === null || $v === '' || (is_array($v) && $v === [])) {
                continue;
            }
            if (is_string($v)) {
                $out[$k] = $this->truncate_for_log($v, 120);
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * Resumen del parsed crudo del LLM: solo los campos del contrato
     * conocido. Descarta claves extra (p. ej. "thinking" de algunos
     * modelos) para que el log sea compacto.
     *
     * @param mixed $parsed_raw
     */
    private function summarize_parsed_raw_for_log($parsed_raw): ?array {
        if (!is_array($parsed_raw)) {
            return null;
        }

        $known_keys = array_merge(
            ['intent'],
            self::PARSED_DATA_FIELDS,
            ['sub_intent', 'affected_fields', 'confidence']
        );

        $slice = [];
        foreach ($known_keys as $k) {
            if (array_key_exists($k, $parsed_raw)) {
                $slice[$k] = $parsed_raw[$k];
            }
        }

        return $this->summarize_parsed_for_log($slice);
    }
}
