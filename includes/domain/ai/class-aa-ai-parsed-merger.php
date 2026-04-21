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
 * Política:
 *   - Para los 7 campos de datos (client/service/staff/zone/date/time/notes):
 *     gana el valor "significativo" del turno actual; si el actual no es
 *     significativo, se preserva el del previo.
 *   - Para `intent`: si el actual es `unknown`, se preserva el previo
 *     (refinamiento dentro del mismo intent); cualquier otro intent del
 *     actual gana (reclasificación genuina).
 *
 * ─── Invariantes ─────────────────────────────────────────────────────
 *
 *   - Sin `$wpdb`, `get_option`, `error_log`, hooks, LLM ni SQL.
 *   - Determinista e idempotente: misma entrada → misma salida.
 *   - Una sola entrada pública: `merge(?array $previous, ?array $current): array`.
 *   - Sin `require_once` de otros archivos del plugin.
 *   - No muta los arrays de entrada (retorna nuevos arrays).
 *   - Defensiva: claves ausentes, valores de tipo inesperado, null →
 *     tratados como no-significativos.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\AI
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Parsed_Merger {

    /**
     * Campos de datos sobre los que opera la regla "current pisa si es
     * significativo, si no se preserva previous". `intent` se trata aparte.
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
     * Orden canónico de salida. Las claves se devuelven SIEMPRE en este
     * orden y SOLO estas claves: claves extra de la entrada se ignoran.
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
    ];

    /**
     * Fusiona dos snapshots de `parsed`.
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
     *   notes:?string
     * }
     */
    public function merge(?array $previous, ?array $current): array {
        $p = $this->normalize_side($previous);
        $c = $this->normalize_side($current);

        $merged = [];

        foreach (self::DATA_FIELDS as $field) {
            $merged[$field] = $this->is_meaningful($c[$field]) ? $c[$field] : $p[$field];
        }

        $merged['intent'] = ($c['intent'] !== 'unknown') ? $c['intent'] : $p['intent'];

        $ordered = [];
        foreach (self::CANONICAL_KEYS as $key) {
            $ordered[$key] = $merged[$key];
        }

        return $ordered;
    }

    /**
     * Normaliza un lado (previous o current) al shape canónico interno.
     *
     * - Para campos de datos: trim si es string significativo, si no null.
     * - Para `intent`: conserva el string tal cual si existe y no es vacío;
     *   si no, fallback a `'unknown'`. NO se valida contra una whitelist
     *   de intents porque eso es responsabilidad del service caller.
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

        return $normalized;
    }

    /**
     * Un valor es "significativo" si es un string no vacío tras `trim`.
     * Cualquier otro tipo (null, int, array, bool) cuenta como ausente.
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
