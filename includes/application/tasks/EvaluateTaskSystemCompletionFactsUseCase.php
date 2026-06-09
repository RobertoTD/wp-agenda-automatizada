<?php
/**
 * Evaluate Task System Completion Facts Use Case.
 *
 * Evalúa completion_fact_key de tareas seeded agenda_app y persiste el resultado
 * en aa_task_state sin tocar status/completed_at de aa_tasks.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/infrastructure/tasks/TaskSystemCompletionFactResolver.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class EvaluateTaskSystemCompletionFactsUseCase {

    /** @var callable|null */
    private $candidates_provider;

    /** @var callable|null */
    private $facts_resolver;

    /** @var callable|null */
    private $completion_recorder;

    /** @var callable|null */
    private $state_finder;

    /**
     * @param callable|null $candidates_provider Debe devolver list<array<string,mixed>>.
     * @param callable|null $facts_resolver        Debe devolver array<string,bool>.
     * @param callable|null $completion_recorder   (int $task_id, bool $completed, string $now) => ?array
     * @param callable|null $state_finder          (int $task_id) => ?array
     */
    public function __construct(
        ?callable $candidates_provider = null,
        ?callable $facts_resolver = null,
        ?callable $completion_recorder = null,
        ?callable $state_finder = null
    ) {
        $this->candidates_provider = $candidates_provider;
        $this->facts_resolver = $facts_resolver;
        $this->completion_recorder = $completion_recorder;
        $this->state_finder = $state_finder;
    }

    /**
     * @return array{
     *     success:bool,
     *     data?:array{
     *         evaluated:int,
     *         completed:int,
     *         newly_completed:int,
     *         errors:int,
     *         skipped:int
     *     },
     *     error?:array{code:string,message:string}
     * }
     */
    public function execute(): array {
        try {
            $candidates = $this->load_candidates();
            $facts = $this->resolve_facts();
            $now = TaskUseCaseSupport::resolve_now();

            $evaluated = 0;
            $completed = 0;
            $newly_completed = 0;
            $errors = 0;
            $skipped = 0;

            foreach ($candidates as $task) {
                if (!is_array($task)) {
                    $skipped++;
                    continue;
                }

                $task_id = (int) ($task['id'] ?? 0);

                if ($task_id < 1) {
                    $skipped++;
                    continue;
                }

                $fact_key = is_string($task['completion_fact_key'] ?? null)
                    ? trim((string) $task['completion_fact_key'])
                    : '';

                if ($fact_key === '') {
                    $skipped++;
                    continue;
                }

                if (!array_key_exists($fact_key, $facts)) {
                    $errors++;
                    continue;
                }

                $is_completed = !empty($facts[$fact_key]);
                $existing_state = $this->find_state($task_id);
                $was_completed = !empty($existing_state['completed_by_system']);
                $recorded = $this->record_completion($task_id, $is_completed, $now);

                if ($recorded === null) {
                    $errors++;
                    continue;
                }

                $evaluated++;

                if ($is_completed) {
                    $completed++;
                }

                if ($is_completed && !$was_completed) {
                    $newly_completed++;
                }
            }

            return TaskUseCaseSupport::ok([
                'evaluated' => $evaluated,
                'completed' => $completed,
                'newly_completed' => $newly_completed,
                'errors' => $errors,
                'skipped' => $skipped,
            ]);
        } catch (\Throwable $exception) {
            error_log('[EvaluateTaskSystemCompletionFactsUseCase] ' . $exception->getMessage());

            return TaskUseCaseSupport::fail(
                'system_completion_evaluation_failed',
                $exception->getMessage()
            );
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function load_candidates(): array {
        if ($this->candidates_provider !== null) {
            $candidates = call_user_func($this->candidates_provider);

            return is_array($candidates) ? array_values($candidates) : [];
        }

        return TaskRepository::list_system_completion_candidates();
    }

    /**
     * @return array<string,bool>
     */
    private function resolve_facts(): array {
        if ($this->facts_resolver !== null) {
            $facts = call_user_func($this->facts_resolver);

            return is_array($facts) ? $facts : [];
        }

        return TaskSystemCompletionFactResolver::resolve_all();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function record_completion(int $task_id, bool $completed, string $now): ?array {
        if ($this->completion_recorder !== null) {
            $recorded = call_user_func($this->completion_recorder, $task_id, $completed, $now);

            return is_array($recorded) ? $recorded : null;
        }

        return TaskStateRepository::record_system_completion_evaluation($task_id, $completed, $now);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find_state(int $task_id): ?array {
        if ($this->state_finder !== null) {
            $state = call_user_func($this->state_finder, $task_id);

            return is_array($state) ? $state : null;
        }

        return TaskStateRepository::find_by_task_id($task_id);
    }
}
