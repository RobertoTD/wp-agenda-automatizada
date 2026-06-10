<?php
/**
 * Task Signal Policy — interpreta señales defer/dismiss persistidas (dominio puro).
 *
 * Sin WordPress, SQL ni proyección de buckets/visible_actions.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-task.php';

final class AA_Task_Signal_Policy {

    /**
     * @param array<string,mixed> $input tasks, task_state_by_id, now
     * @return array<int,array<string,mixed>> task_id => evaluation
     */
    public function evaluate_all(array $input): array {
        $now = $this->resolve_now($input);
        $tasks = $this->normalize_tasks($input['tasks'] ?? []);
        $states_by_id = $this->normalize_states_by_id($input['task_state_by_id'] ?? []);
        $evaluations = [];

        foreach ($tasks as $task) {
            $task_id = $task->id();

            if ($task_id < 1) {
                continue;
            }

            $evaluations[$task_id] = $this->evaluate_task(
                $task,
                $states_by_id[$task_id] ?? null,
                $now
            );
        }

        return $evaluations;
    }

    /**
     * @param AA_Task                  $task
     * @param array<string,mixed>|null $state_row
     * @return array<string,mixed>
     */
    public function evaluate_task(AA_Task $task, ?array $state_row, string $now): array {
        $state = is_array($state_row) ? $state_row : [];

        $defer_count = max(0, (int) ($state['defer_count'] ?? 0));
        $dismiss_count = max(0, (int) ($state['dismiss_count'] ?? 0));
        $last_deferred_at = $this->nullable_datetime($state['last_deferred_at'] ?? null);
        $last_dismissed_at = $this->nullable_datetime($state['last_dismissed_at'] ?? null);
        $defer_until = $this->nullable_datetime($state['defer_until'] ?? null);
        $dismiss_until = $this->nullable_datetime($state['dismiss_until'] ?? null);

        $has_defer = $defer_count > 0 && $last_deferred_at !== null;
        $has_dismiss = $dismiss_count > 0 && $last_dismissed_at !== null;
        $is_defer_active = $defer_until !== null && $this->is_before($now, $defer_until);
        $is_dismiss_active = $dismiss_until !== null && $this->is_before($now, $dismiss_until);
        $is_dismiss_hiding = $has_dismiss && ($dismiss_until === null || $is_dismiss_active);
        $is_system_completed = !empty($state['completed_by_system']);
        $visible_in_active = true;

        $is_pending = $task->is_pending();

        return [
            'signals' => [
                'has_defer' => $has_defer,
                'has_dismiss' => $has_dismiss,
                'defer_count' => $defer_count,
                'dismiss_count' => $dismiss_count,
            ],
            'state' => [
                'is_defer_active' => $is_defer_active,
                'is_dismiss_active' => $is_dismiss_active,
                'is_dismiss_hiding' => $is_dismiss_hiding,
                'is_system_completed' => $is_system_completed,
            ],
            'capabilities' => [
                'can_defer' => false,
                'can_dismiss' => $is_pending && $visible_in_active && !$is_dismiss_active,
                'can_reactivate' => false,
            ],
            'visible_in_active' => $visible_in_active,
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    private function resolve_now(array $input): string {
        $now = $input['now'] ?? null;

        if (is_string($now) && trim($now) !== '') {
            return trim($now);
        }

        return '1970-01-01 00:00:00';
    }

    /**
     * @param mixed $raw_tasks
     * @return list<AA_Task>
     */
    private function normalize_tasks($raw_tasks): array {
        if (!is_array($raw_tasks)) {
            return [];
        }

        $tasks = [];

        foreach ($raw_tasks as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tasks[] = AA_Task::from_array($row);
        }

        return $tasks;
    }

    /**
     * @param mixed $raw_states
     * @return array<int,array<string,mixed>>
     */
    private function normalize_states_by_id($raw_states): array {
        if (!is_array($raw_states)) {
            return [];
        }

        $normalized = [];

        foreach ($raw_states as $task_id => $state) {
            if (!is_array($state)) {
                continue;
            }

            $normalized_id = (int) $task_id;

            if ($normalized_id < 1) {
                continue;
            }

            $normalized[$normalized_id] = $state;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function nullable_datetime($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? trim($value) : null;
    }

    /**
     * @param string $left  Y-m-d H:i:s
     * @param string $right Y-m-d H:i:s
     */
    private function is_before(string $left, string $right): bool {
        $left_ts = strtotime($left);
        $right_ts = strtotime($right);

        if ($left_ts === false || $right_ts === false) {
            return false;
        }

        return $left_ts < $right_ts;
    }
}
