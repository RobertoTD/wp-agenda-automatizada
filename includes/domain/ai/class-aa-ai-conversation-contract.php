<?php
/**
 * AI Conversation Contract
 *
 * Capa: `includes/domain/ai/` (contrato de dominio puro).
 *
 * Define el vocabulario cerrado que el flujo conversacional del chat
 * admin usa para clasificar turnos dentro de `create_booking`:
 *
 *   - `sub_intent`: qué está haciendo el usuario en ESTE turno respecto
 *     al borrador en curso (crear nuevo, completar, modificar, confirmar,
 *     cancelar, preguntar disponibilidad, preguntar estado, otro).
 *   - `affected_fields`: qué campos de negocio está tocando el usuario
 *     en ESTE turno (subset normalizado de los slots del draft).
 *
 * Esta pieza es SOLO un contrato:
 *   - expone los enums como constantes,
 *   - normaliza valores crudos del LLM a esos enums con defaults seguros,
 *   - no decide flujo, no decide dispatch, no consume el parsed.
 *
 * ─── Estado del rollout ──────────────────────────────────────────────
 *
 * Fase 1: `sub_intent` y `affected_fields` se EMITEN y se NORMALIZAN
 *   pero NO gobiernan todavía el flujo.
 *
 * Fase 2 (actual): el merger (`AA_AI_Parsed_Merger`) USA `affected_fields`
 *   como regla central: "este campo fue tocado por el turno actual, así
 *   que el current manda (incluso si es null, en cuyo caso limpia)". El
 *   fallback cuando `affected_fields` viene vacío replica la semántica
 *   legacy (current significativo pisa / current null preserva previous),
 *   así que el flujo no se rompe aunque el LLM omita la señal. La
 *   cancelación todavía vive en `AA_Admin_AI_Chat_Service::is_cancel_message`
 *   y la confirmación todavía vive en el frontend (`aichat.js`).
 *
 * Fases siguientes: `sub_intent` pasará a dispatch server-side para
 *   confirmación/cancelación unificadas y para la rama de
 *   `ask_availability`.
 *
 * ─── Invariantes ─────────────────────────────────────────────────────
 *
 *   - Sin `$wpdb`, `get_option`, `error_log`, hooks, LLM ni SQL.
 *   - Determinista e idempotente: misma entrada → misma salida.
 *   - Métodos estáticos (sin estado): es una tabla de valores.
 *   - Sin `require_once` de otros archivos del plugin.
 *   - No muta entradas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\AI
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Conversation_Contract {

    /**
     * Enum cerrado de subintenciones conversacionales dentro del flujo
     * de `create_booking`.
     *
     * Semántica operativa (para el prompt y para el merger en Paso 2):
     *
     *   - `new_booking`         → primera mención de una cita, sin
     *                             contexto previo.
     *   - `fill_missing_fields` → el usuario aporta datos que faltaban
     *                             sin modificar nada ya fijado.
     *   - `modify_fields`       → el usuario cambia un dato ya fijado.
     *                             Puede traer el nuevo valor ("cambia
     *                             la hora a las 6") o no ("quiero
     *                             cambiar el servicio").
     *   - `confirm_draft`       → afirmación sobre un borrador propuesto
     *                             ("sí", "ok", "confirmar").
     *   - `cancel_draft`        → abortar el borrador ("cancela", "ya
     *                             no gracias", "olvídalo").
     *   - `ask_availability`    → pregunta sobre disponibilidad ("a
     *                             qué hora tiene libre", "¿a las 5
     *                             está libre?").
     *   - `ask_draft_state`     → pregunta sobre el estado del borrador
     *                             actual ("¿qué cliente tengo?").
     *   - `other`               → cualquier otra cosa (saludo, charla,
     *                             fuera de alcance).
     */
    public const SUB_INTENT_NEW_BOOKING         = 'new_booking';
    public const SUB_INTENT_FILL_MISSING_FIELDS = 'fill_missing_fields';
    public const SUB_INTENT_MODIFY_FIELDS       = 'modify_fields';
    public const SUB_INTENT_CONFIRM_DRAFT       = 'confirm_draft';
    public const SUB_INTENT_CANCEL_DRAFT        = 'cancel_draft';
    public const SUB_INTENT_ASK_AVAILABILITY    = 'ask_availability';
    public const SUB_INTENT_ASK_DRAFT_STATE     = 'ask_draft_state';
    public const SUB_INTENT_OTHER               = 'other';

    private const VALID_SUB_INTENTS = [
        self::SUB_INTENT_NEW_BOOKING,
        self::SUB_INTENT_FILL_MISSING_FIELDS,
        self::SUB_INTENT_MODIFY_FIELDS,
        self::SUB_INTENT_CONFIRM_DRAFT,
        self::SUB_INTENT_CANCEL_DRAFT,
        self::SUB_INTENT_ASK_AVAILABILITY,
        self::SUB_INTENT_ASK_DRAFT_STATE,
        self::SUB_INTENT_OTHER,
    ];

    /**
     * Vocabulario cerrado y normalizado de campos de negocio. Es
     * intencionalmente un alias corto ("client", "service", etc.) y
     * NO los nombres internos del parsed (`client_name`, etc.) porque
     * éste es el vocabulario que consume el merger y el reply builder,
     * ambos indexados por entidad y no por campo crudo.
     */
    public const FIELD_CLIENT  = 'client';
    public const FIELD_SERVICE = 'service';
    public const FIELD_STAFF   = 'staff';
    public const FIELD_ZONE    = 'zone';
    public const FIELD_DATE    = 'date';
    public const FIELD_TIME    = 'time';
    public const FIELD_NOTES   = 'notes';

    private const VALID_AFFECTED_FIELDS = [
        self::FIELD_CLIENT,
        self::FIELD_SERVICE,
        self::FIELD_STAFF,
        self::FIELD_ZONE,
        self::FIELD_DATE,
        self::FIELD_TIME,
        self::FIELD_NOTES,
    ];

    /**
     * Mapeo determinista de alias corto de entidad (vocabulario de
     * `affected_fields`) al nombre real de la clave en el `parsed`.
     *
     * Esta tabla vive aquí (y no en el merger) porque es vocabulario
     * de dominio: el mapping alias → parsed_key es una verdad única
     * que consumen el merger, el draft aggregator y, en el futuro,
     * cualquier lógica que quiera operar sobre "los campos afectados
     * por el turno" sin acoplarse a los nombres largos del parser.
     */
    private const ALIAS_TO_PARSED_KEY = [
        self::FIELD_CLIENT  => 'client_name',
        self::FIELD_SERVICE => 'service_name',
        self::FIELD_STAFF   => 'staff_name',
        self::FIELD_ZONE    => 'zone_name',
        self::FIELD_DATE    => 'date_text',
        self::FIELD_TIME    => 'time_text',
        self::FIELD_NOTES   => 'notes',
    ];

    /**
     * Default de `sub_intent` cuando el LLM no emite uno válido.
     *
     * Se elige `other` (y no `fill_missing_fields`) para que un modelo
     * que no entienda la clasificación no empuje al sistema a asumir
     * que está pasando algo específico. En Fase 1 esto es inocuo
     * porque `sub_intent` no gobierna dispatch; en fases siguientes
     * `other` cae en la rama de heurísticas legacy, que es el
     * comportamiento actual y seguro.
     */
    public const DEFAULT_SUB_INTENT = self::SUB_INTENT_OTHER;

    /**
     * @return array<int, string>
     */
    public static function valid_sub_intents(): array {
        return self::VALID_SUB_INTENTS;
    }

    /**
     * @return array<int, string>
     */
    public static function valid_affected_fields(): array {
        return self::VALID_AFFECTED_FIELDS;
    }

    /**
     * Normaliza un valor crudo de `sub_intent` al enum cerrado.
     *
     * Política:
     *   - string en la whitelist (tras trim + lowercase) → se conserva.
     *   - cualquier otra cosa (null, array, int, string desconocido)
     *     → `DEFAULT_SUB_INTENT`.
     *
     * @param mixed $raw
     */
    public static function normalize_sub_intent($raw): string {
        if (!is_string($raw)) {
            return self::DEFAULT_SUB_INTENT;
        }

        $candidate = strtolower(trim($raw));
        if ($candidate === '') {
            return self::DEFAULT_SUB_INTENT;
        }

        if (in_array($candidate, self::VALID_SUB_INTENTS, true)) {
            return $candidate;
        }

        return self::DEFAULT_SUB_INTENT;
    }

    /**
     * Traduce una lista de `affected_fields` (alias cortos) al conjunto
     * de claves reales del `parsed` (nombres largos). Los aliases que
     * no estén en la whitelist se ignoran silenciosamente.
     *
     * Esta es la interfaz que debe usar cualquier consumer que quiera
     * operar sobre "los campos del parsed afectados por el turno"; no
     * expongamos el mapping privado directamente porque queremos poder
     * cambiar la estructura interna sin romper callers.
     *
     * @param array<int, string> $affected_fields Aliases ya normalizados.
     * @return array<int, string>                 Claves canónicas del parsed.
     */
    public static function affected_fields_as_parsed_keys(array $affected_fields): array {
        $out = [];
        foreach ($affected_fields as $alias) {
            if (!is_string($alias)) {
                continue;
            }
            if (isset(self::ALIAS_TO_PARSED_KEY[$alias])) {
                $out[] = self::ALIAS_TO_PARSED_KEY[$alias];
            }
        }
        return $out;
    }

    /**
     * Normaliza un valor crudo de `affected_fields` al conjunto cerrado.
     *
     * Política:
     *   - entrada no-array → `[]`.
     *   - cada elemento no string se descarta.
     *   - se aplica trim + lowercase.
     *   - se filtra contra la whitelist.
     *   - se deduplica preservando el primer orden de aparición.
     *
     * @param mixed $raw
     * @return array<int, string>
     */
    public static function normalize_affected_fields($raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $seen = [];
        $out  = [];

        foreach ($raw as $value) {
            if (!is_string($value)) {
                continue;
            }
            $candidate = strtolower(trim($value));
            if ($candidate === '') {
                continue;
            }
            if (!in_array($candidate, self::VALID_AFFECTED_FIELDS, true)) {
                continue;
            }
            if (isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;
            $out[] = $candidate;
        }

        return $out;
    }

    /**
     * Normaliza `confidence` al rango [0.0, 1.0] o null.
     *
     * Política:
     *   - float o int dentro de [0, 1] → float limpio.
     *   - string numérico dentro de rango → float limpio.
     *   - cualquier otra cosa (null, fuera de rango, no numérico) → null.
     *
     * @param mixed $raw
     */
    public static function normalize_confidence($raw): ?float {
        if (is_bool($raw) || $raw === null) {
            return null;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;
        if ($value < 0.0 || $value > 1.0) {
            return null;
        }

        return $value;
    }
}
