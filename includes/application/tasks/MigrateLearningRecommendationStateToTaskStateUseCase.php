<?php
/**
 * Migrate Learning Recommendation State To Task State Use Case (MC13O-F1).
 *
 * Migración manual/idempotente parcial: completion manual e ignored→defer.
 * No migra dismissed, aging ni list_override; no borra legacy state.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-learning-legacy-state-to-task-state-mapper.php';
require_once dirname(__DIR__, 2) . '/repositories/LearningRecommendationStateRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class MigrateLearningRecommendationStateToTaskStateUseCase {

    private const SOURCE_CATEGORY = 'agenda_app';

    /** @var callable|null */
    private $legacy_states_provider;

    /** @var callable|null */
    private $task_finder;

    /** @var callable|null */
    private $task_state_finder;

    /** @var callable|null */
    private $completion_marker;

    /** @var callable|null */
    private $defer_applier;

    /**
     * @param callable|null $legacy_states_provider Debe devolver array<string,array<string,mixed>>.
     * @param callable|null $task_finder              (string $origin_key) => ?array
     * @param callable|null $task_state_finder        (int $task_id) => ?array
     * @param callable|null $completion_marker        (int $task_id, string $completed_at) => ?array
     * @param callable|null $defer_applier            (int $task_id, string $last_deferred_at) => ?array
     */
    public function __construct(
        ?callable $legacy_states_provider = null,
        ?callable $task_finder = null,
        ?callable $task_state_finder = null,
        ?callable $completion_marker = null,
        ?callable $defer_applier = null
    ) {
        $this->legacy_states_provider = $legacy_states_provider;
        $this->task_finder = $task_finder;
        $this->task_state_finder = $task_state_finder;
        $this->completion_marker = $completion_marker;
        $this->defer_applier = $defer_applier;
    }

    /**
     * @return array{
     *     success:bool,
     *     data?:array{
     *         completed_migrated:int,
     *         defer_migrated:int,
     *         dismissed_skipped:int,
     *         skipped_no_task:int,
     *         skipped_ambiguous:int,
     *         skipped_no_signal:int,
     *         errors:int
     *     },
     *     error?:array{code:string,message:string}
     * }
     */
    public function execute(): array {
        try {
            $legacy_states = $this->load_legacy_states();
            $mapper = new AA_Learning_Legacy_State_To_Task_State_Mapper();
            $now = TaskUseCaseSupport::resolve_now();

            $counts = [
                'completed_migrated' => 0,
                'defer_migrated' => 0,
                'dismissed_skipped' => 0,
                'skipped_no_task' => 0,
                'skipped_ambiguous' => 0,
                'skipped_no_signal' => 0,
                'errors' => 0,
            ];

            foreach ($legacy_states as $legacy_row) {
                if (!is_array($legacy_row)) {
                    continue;
                }

                $recommendation_key = trim((string) ($legacy_row['recommendation_key'] ?? ''));

                if ($recommendation_key === '') {
                    continue;
                }

                $seeded_task = $this->find_seeded_task($recommendation_key);

                if ($seeded_task === null) {
                    $counts['skipped_no_task']++;
                    continue;
                }

                $intention = $mapper->map($legacy_row, $seeded_task, $now);
                $result = (string) ($intention['result'] ?? '');

                switch ($result) {
                    case AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_COMPLETE_MANUAL:
                        if ($this->apply_complete_manual($seeded_task, (string) ($intention['completed_at'] ?? $now))) {
                            $counts['completed_migrated']++;
                        } else {
                            $counts['errors']++;
                        }
                        break;

                    case AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_DEFER:
                        if ($this->apply_defer($seeded_task, (string) ($intention['last_deferred_at'] ?? $now))) {
                            $counts['defer_migrated']++;
                        } else {
                            $counts['errors']++;
                        }
                        break;

                    case AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_DISMISSED:
                        $counts['dismissed_skipped']++;
                        break;

                    case AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_AMBIGUOUS:
                        $counts['skipped_ambiguous']++;
                        break;

                    case AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_NO_SIGNAL:
                        $counts['skipped_no_signal']++;
                        break;

                    default:
                        $counts['skipped_ambiguous']++;
                        break;
                }
            }

            return TaskUseCaseSupport::ok($counts);
        } catch (\Throwable $exception) {
            error_log('[MigrateLearningRecommendationStateToTaskStateUseCase] ' . $exception->getMessage());

            return TaskUseCaseSupport::fail(
                'learning_state_migration_failed',
                $exception->getMessage()
            );
        }
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function load_legacy_states(): array {
        if ($this->legacy_states_provider !== null) {
            $states = call_user_func($this->legacy_states_provider);

            return is_array($states) ? $states : [];
        }

        return LearningRecommendationStateRepository::get_all();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find_seeded_task(string $recommendation_key): ?array {
        if ($this->task_finder !== null) {
            $task = call_user_func($this->task_finder, $recommendation_key);

            return is_array($task) ? $task : null;
        }

        return SeededTaskRepository::find_task_by_origin(self::SOURCE_CATEGORY, $recommendation_key);
    }

    /**
     * @param array<string,mixed> $seeded_task
     */
    private function apply_complete_manual(array $seeded_task, string $completed_at): bool {
        $task_id = (int) ($seeded_task['id'] ?? 0);

        if ($task_id < 1) {
            return false;
        }

        $status = strtolower(trim((string) ($seeded_task['status'] ?? '')));

        if ($status === 'done') {
            $existing_completed_at = $seeded_task['completed_at'] ?? null;

            if (is_string($existing_completed_at) && trim($existing_completed_at) !== '') {
                return true;
            }

            if ($this->completion_marker !== null) {
                $row = call_user_func($this->completion_marker, $task_id, $completed_at);

                return is_array($row);
            }

            return TaskRepository::mark_completed($task_id, $completed_at) !== null;
        }

        if ($status !== 'pending') {
            return false;
        }

        if ($this->completion_marker !== null) {
            $row = call_user_func($this->completion_marker, $task_id, $completed_at);

            return is_array($row);
        }

        return TaskRepository::mark_completed($task_id, $completed_at) !== null;
    }

    /**
     * @param array<string,mixed> $seeded_task
     */
    private function apply_defer(array $seeded_task, string $last_deferred_at): bool {
        $task_id = (int) ($seeded_task['id'] ?? 0);

        if ($task_id < 1) {
            return false;
        }

        if ($this->defer_applier !== null) {
            $row = call_user_func($this->defer_applier, $task_id, $last_deferred_at);

            return is_array($row);
        }

        return TaskStateRepository::apply_legacy_defer_migration($task_id, $last_deferred_at) !== null;
    }
}
