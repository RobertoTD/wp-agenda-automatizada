<?php
/**
 * Task Execution Timing Policy — clasifica disponibilidad y vencimiento.
 *
 * Dominio puro: sin WordPress, SQL, persistencia ni lectura de configuración.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-task.php';

final class AA_Task_Execution_Timing_Policy {

    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /** @var DateTimeZone */
    private $timezone;

    public function __construct(DateTimeZone $timezone) {
        $this->timezone = $timezone;
    }

    /**
     * @return array{
     *     has_temporal_condition:bool,
     *     is_temporal_condition_pending:bool,
     *     is_temporal_condition_met:bool,
     *     temporal_layer:1|2|3|4,
     *     priority_score:float
     * }
     */
    public function evaluate(AA_Task $task, string $now): array {
        $importance = $task->importance();
        $now_dt = $this->parse_datetime($now);
        $due_dt = $this->parse_datetime($task->due_at());
        $execution_dt = $this->parse_datetime($task->execution_available_at());

        if ($now_dt === null) {
            return $this->result(false, false, false, 2, (float) $importance);
        }

        $has_temporal_condition = $execution_dt !== null;
        $is_temporal_condition_pending = $execution_dt !== null && $execution_dt > $now_dt;
        $is_temporal_condition_met = $execution_dt !== null && $execution_dt <= $now_dt;

        // El vencimiento tiene precedencia absoluta sobre la condición temporal.
        if ($due_dt !== null && $due_dt <= $now_dt) {
            return $this->result(
                $has_temporal_condition,
                $is_temporal_condition_pending,
                $is_temporal_condition_met,
                4,
                (float) $importance
            );
        }

        if ($is_temporal_condition_pending) {
            return $this->result(true, true, false, 1, (float) $importance);
        }

        if ($is_temporal_condition_met) {
            return $this->result(
                true,
                false,
                true,
                3,
                $this->calculate_priority_score($importance, $execution_dt, $due_dt, $now_dt)
            );
        }

        return $this->result(false, false, false, 2, (float) $importance);
    }

    /**
     * @param mixed $value
     */
    private function parse_datetime($value): ?DateTimeImmutable {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        $datetime = DateTimeImmutable::createFromFormat(
            self::DATETIME_FORMAT,
            $normalized,
            $this->timezone
        );
        $errors = DateTimeImmutable::getLastErrors();

        if ($datetime === false) {
            return null;
        }

        if (is_array($errors)
            && ((int) ($errors['warning_count'] ?? 0) > 0
                || (int) ($errors['error_count'] ?? 0) > 0)
        ) {
            return null;
        }

        if ($datetime->format(self::DATETIME_FORMAT) !== $normalized) {
            return null;
        }

        return $datetime;
    }

    private function calculate_priority_score(
        int $importance,
        DateTimeImmutable $execution_dt,
        ?DateTimeImmutable $due_dt,
        DateTimeImmutable $now_dt
    ): float {
        if ($due_dt === null || $due_dt <= $execution_dt) {
            return (float) $importance;
        }

        $window_seconds = $due_dt->getTimestamp() - $execution_dt->getTimestamp();
        $elapsed_seconds = $now_dt->getTimestamp() - $execution_dt->getTimestamp();

        if ($window_seconds <= 0) {
            return (float) $importance;
        }

        $progress = $this->clamp($elapsed_seconds / $window_seconds, 0.0, 1.0);
        $urgency_multiplier = 1.0 + pow($progress, 1.5);

        return (float) ($importance + abs($importance) * ($urgency_multiplier - 1.0));
    }

    private function clamp(float $value, float $minimum, float $maximum): float {
        return max($minimum, min($maximum, $value));
    }

    /**
     * @return array{
     *     has_temporal_condition:bool,
     *     is_temporal_condition_pending:bool,
     *     is_temporal_condition_met:bool,
     *     temporal_layer:1|2|3|4,
     *     priority_score:float
     * }
     */
    private function result(
        bool $has_temporal_condition,
        bool $is_temporal_condition_pending,
        bool $is_temporal_condition_met,
        int $temporal_layer,
        float $priority_score
    ): array {
        return [
            'has_temporal_condition' => $has_temporal_condition,
            'is_temporal_condition_pending' => $is_temporal_condition_pending,
            'is_temporal_condition_met' => $is_temporal_condition_met,
            'temporal_layer' => $temporal_layer,
            'priority_score' => $priority_score,
        ];
    }
}
