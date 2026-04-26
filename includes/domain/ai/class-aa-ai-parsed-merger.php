<?php
/**
 * AI Parsed Merger
 *
 * Capa: `includes/domain/ai/` (dominio puro).
 *
 * Fusiona el `parsed` del turno anterior con el `parsed` del turno actual
 * para soportar conversación multi-turno sobre un servidor stateless. El
 * cliente reenvía el `parsed` recibido como `previous_parsed`; el merger
 * decide campo a campo cuál valor sobrevive.
 *
 * ─── Política (Paso 2 del rollout conversacional) ────────────────────
 *
 * El merger consume `affected_fields` del turno actual como fuente
 * explícita de intención: "estos son los campos que el usuario está
 * creando, completando o modificando AHORA". La regla por cada campo
 * de datos es:
 *
 *   - Si el campo está en `affected_fields`:
 *       · current significativo        → usar current.
 *       · current nulo / no significativo → **limpiar** (null).
 *
 *     Esta es la semántica nueva: tocar un campo puede ser
 *     REEMPLAZARLO o LIMPIARLO. Antes el merger no podía limpiar, y
 *     frases como "quiero cambiar el servicio" se comían el cambio
 *     conservando el valor viejo.
 *
 *   - Si el campo NO está en `affected_fields`:
 *       · current significativo           → usar current (el LLM
 *         puede reafirmar un dato ya presente; si lo emite explícitamente,
 *         se confía).
 *       · current nulo / no significativo → preservar previous (rama
 *         de refinamiento: el usuario no tocó este campo, el draft lo
 *         conserva).
 *
 *     Esta rama es el **fallback compatible** cuando el LLM no emite
 *     `affected_fields` (p. ej. lista vacía): comportamiento idéntico
 *     al de Paso 1 (current pisa si es significativo, si no preserva
 *     previous).
 *
 * Para `intent` se mantiene la política previa: current != `unknown`
 * gana; `unknown` preserva previous.
 *
 * `sub_intent`, `affected_fields` y `confidence` son señales **por
 * turno**, nunca se heredan del previous: siempre salen tal cual del
 * current normalizado. Si current es null/ausente, quedan en sus
 * defaults (`other`, `[]`, `null`).
 *
 * ─── Invariantes ─────────────────────────────────────────────────────
 *
 *   - Sin `$wpdb`, `get_option`, `error_log`, hooks, LLM ni SQL.
 *   - Determinista e idempotente: misma entrada → misma salida.
 *   - Una sola entrada pública: `merge(?array $previous, ?array $current): array`.
 *   - Dependencia permitida: `AA_AI_Conversation_Contract`, también
 *     en `domain/ai/`. El require es explícito; no hay cross-layer.
 *   - No muta los arrays de entrada (retorna nuevos arrays).
 *   - Defensiva: claves ausentes, valores de tipo inesperado, null →
 *     tratados como no-significativos.
 *
 * ─── Relación con pasos posteriores ──────────────────────────────────
 *
 *   - Paso 3: `sub_intent` pasará a gobernar dispatch server-side
 *     (`confirm_draft`, `cancel_draft`). El merger seguirá siendo
 *     quien produce el snapshot normalizado, pero ya no será quien
 *     decida "qué hacer" con él; esa responsabilidad se moverá al
 *     orquestador del chat.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\AI
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-ai-conversation-contract.php';

final class AA_AI_Parsed_Merger {

    /**
     * Campos de datos del draft. El merger opera sobre estos con la
     * regla de `affected_fields`. `intent` y las 3 señales por turno
     * (`sub_intent`, `affected_fields`, `confidence`) viven aparte.
     */
    private const DATA_FIELDS = [
        'client_name',
        'service_name',
        'staff_name',
        'zone_name',
        'date_text',
        'time_text',
        'notes',
    ];

    /**
     * Orden canónico del output. Las claves se devuelven SIEMPRE en
     * este orden y SOLO estas claves: claves extra de la entrada se
     * ignoran.
     */
    private const CANONICAL_KEYS = [
        'intent',
        'client_name',
        'service_name',
        'staff_name',
        'zone_name',
        'date_text',
        'time_text',
        'notes',
        'sub_intent',
        'affected_fields',
        'confidence',
    ];

    /**
     * Fusiona dos snapshots de `parsed` según la política descrita en
     * el docblock de la clase.
     *
     * @param array<string,mixed>|null $previous Snapshot del turno anterior, o null.
     * @param array<string,mixed>|null $current  Snapshot del turno actual, o null.
     * @return array{
     *   intent:string,
     *   client_name:?string,
     *   service_name:?string,
     *   staff_name:?string,
     *   zone_name:?string,
     *   date_text:?string,
     *   time_text:?string,
     *   notes:?string,
     *   sub_intent:string,
     *   affected_fields:array<int,string>,
     *   confidence:?float
     * }
     */
    public function merge(?array $previous, ?array $current): array {
        $p = $this->normalize_side($previous);
        $c = $this->normalize_side($current);
        $is_read_only_sub_intent = (($c['sub_intent'] ?? '') === 'ask_availability');

        $affected_parsed_keys = AA_AI_Conversation_Contract::affected_fields_as_parsed_keys($c['affected_fields']);
        $affected_lookup      = array_fill_keys($affected_parsed_keys, true);

        $merged = [];

        $merged['intent'] = ($c['intent'] !== 'unknown') ? $c['intent'] : $p['intent'];

        foreach (self::DATA_FIELDS as $field) {
            $current_value   = $c[$field];
            $previous_value  = $p[$field];
            $current_has_val = $current_value !== null;

            if ($is_read_only_sub_intent) {
                $merged[$field] = $previous_value;
                continue;
            }

            $is_affected     = !$is_read_only_sub_intent && isset($affected_lookup[$field]);

            if ($is_affected) {
                $merged[$field] = $current_has_val ? $current_value : null;
            } else {
                $merged[$field] = $current_has_val ? $current_value : $previous_value;
            }
        }

        $merged['sub_intent']      = $c['sub_intent'];
        $merged['affected_fields'] = $c['affected_fields'];
        $merged['confidence']      = $c['confidence'];

        $ordered = [];
        foreach (self::CANONICAL_KEYS as $key) {
            $ordered[$key] = $merged[$key];
        }

        return $ordered;
    }

    /**
     * Normaliza un lado (previous o current) al shape canónico interno.
     *
     * - Data fields: trim si es string significativo, si no null.
     * - `intent`: string no vacío o fallback `'unknown'`. No se valida
     *   contra whitelist: responsabilidad del service caller.
     * - `sub_intent`, `affected_fields`, `confidence`: se delegan a
     *   `AA_AI_Conversation_Contract`, que aplica enum cerrado y
     *   defaults seguros. Así el merger no duplica esa verdad.
     *
     * @param array<string,mixed>|null $side
     * @return array<string,mixed>
     */
    private function normalize_side(?array $side): array {
        $normalized = [];

        foreach (self::DATA_FIELDS as $field) {
            $value = ($side !== null && array_key_exists($field, $side)) ? $side[$field] : null;
            $normalized[$field] = $this->is_meaningful($value) ? trim($value) : null;
        }

        $intent_raw = ($side !== null && array_key_exists('intent', $side)) ? $side['intent'] : null;
        if (is_string($intent_raw)) {
            $intent_trimmed = trim($intent_raw);
            $normalized['intent'] = $intent_trimmed !== '' ? $intent_trimmed : 'unknown';
        } else {
            $normalized['intent'] = 'unknown';
        }

        $sub_intent_raw = ($side !== null && array_key_exists('sub_intent', $side)) ? $side['sub_intent'] : null;
        $normalized['sub_intent'] = AA_AI_Conversation_Contract::normalize_sub_intent($sub_intent_raw);

        $affected_raw = ($side !== null && array_key_exists('affected_fields', $side)) ? $side['affected_fields'] : null;
        $normalized['affected_fields'] = AA_AI_Conversation_Contract::normalize_affected_fields($affected_raw);

        $confidence_raw = ($side !== null && array_key_exists('confidence', $side)) ? $side['confidence'] : null;
        $normalized['confidence'] = AA_AI_Conversation_Contract::normalize_confidence($confidence_raw);

        return $normalized;
    }

    /**
     * Un valor de data field es "significativo" si es un string no
     * vacío tras `trim`. Cualquier otro tipo (null, int, array, bool)
     * cuenta como ausente.
     *
     * @param mixed $value
     * @return bool
     */
    private function is_meaningful($value): bool {
        if (!is_string($value)) {
            return false;
        }
        return trim($value) !== '';
    }
}
