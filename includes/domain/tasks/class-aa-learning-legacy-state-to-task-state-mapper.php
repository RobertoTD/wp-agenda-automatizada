<?php
/**
 * Learning Legacy State To Task State Mapper — intención de migración F1 (dominio puro).
 *
 * Traduce filas de aa_learning_recommendation_state hacia el modelo común de Tasks
 * sin SQL ni WordPress. No migra dismissed, aging ni list_override.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Learning_Legacy_State_To_Task_State_Mapper {

    public const RESULT_COMPLETE_MANUAL = 'complete_manual';

    public const RESULT_DEFER = 'defer';

    public const RESULT_SKIPPED_DISMISSED = 'skipped_dismissed_deferred_for_policy';

    public const RESULT_SKIPPED_AMBIGUOUS = 'skipped_ambiguous';

    public const RESULT_SKIPPED_NO_SIGNAL = 'skipped_no_signal';

    /**
     * @param array<string,mixed> $legacy_row  Fila aa_learning_recommendation_state.
     * @param array<string,mixed> $seeded_task Fila aa_tasks destino (agenda_app seeded).
     * @param string              $now         Y-m-d H:i:s fallback.
     * @return array{
     *     result:string,
     *     completed_at?:string,
     *     last_deferred_at?:string,
     *     defer_count_min?:int
     * }
     */
    public function map(array $legacy_row, array $seeded_task, string $now): array {
        $is_completed = $this->bool_value($legacy_row['is_completed'] ?? false);
        $is_ignored = $this->bool_value($legacy_row['is_ignored'] ?? false);
        $is_dismissed = $this->bool_value($legacy_row['is_dismissed'] ?? false);
        $list_override = $legacy_row['list_override'] ?? null;
        $has_list_override = $list_override !== null && $list_override !== '';
        $has_last_suggested = $this->has_datetime($legacy_row['last_suggested_at'] ?? null);

        if ($is_completed) {
            if ($this->is_manual_completion_task($seeded_task)) {
                return [
                    'result' => self::RESULT_COMPLETE_MANUAL,
                    'completed_at' => $this->resolve_timestamp(
                        $legacy_row['completed_at'] ?? null,
                        $legacy_row['updated_at'] ?? null,
                        $now
                    ),
                ];
            }

            return ['result' => self::RESULT_SKIPPED_AMBIGUOUS];
        }

        if ($is_ignored) {
            return [
                'result' => self::RESULT_DEFER,
                'last_deferred_at' => $this->resolve_timestamp(
                    $legacy_row['ignored_at'] ?? null,
                    $legacy_row['updated_at'] ?? null,
                    $now
                ),
                'defer_count_min' => 1,
            ];
        }

        if ($is_dismissed) {
            return ['result' => self::RESULT_SKIPPED_DISMISSED];
        }

        if ($has_list_override) {
            return ['result' => self::RESULT_SKIPPED_AMBIGUOUS];
        }

        if ($has_last_suggested) {
            return ['result' => self::RESULT_SKIPPED_NO_SIGNAL];
        }

        return ['result' => self::RESULT_SKIPPED_NO_SIGNAL];
    }

    /**
     * @param array<string,mixed> $seeded_task
     */
    private function is_manual_completion_task(array $seeded_task): bool {
        $completion_type = strtolower(trim((string) ($seeded_task['completion_type'] ?? 'manual')));

        return $completion_type === 'manual';
    }

    /**
     * @param mixed $primary
     * @param mixed $secondary
     */
    private function resolve_timestamp($primary, $secondary, string $fallback): string {
        if ($this->has_datetime($primary)) {
            return trim((string) $primary);
        }

        if ($this->has_datetime($secondary)) {
            return trim((string) $secondary);
        }

        return $fallback;
    }

    /**
     * @param mixed $value
     */
    private function bool_value($value): bool {
        return !empty($value);
    }

    /**
     * @param mixed $value
     */
    private function has_datetime($value): bool {
        return is_string($value) && trim($value) !== '';
    }
}
