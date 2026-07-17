<?php
/**
 * Validate Task Execution Available Push Use Case.
 *
 * Responde si un job concreto de push de tarea sigue siendo válido y, cuando
 * aplica, devuelve los datos actuales mínimos para componer la notificación.
 * No consulta proyección activa, dismiss/defer ni Ejecutor.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-execution-timing-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskListRepository.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class ValidateTaskExecutionAvailablePushUseCase {

    public const STATUS_ELIGIBLE = 'eligible';

    public const STATUS_INELIGIBLE = 'ineligible';

    public const STATUS_STALE = 'stale';

    /** @var callable|null */
    private $task_reader;

    /** @var callable|null */
    private $list_reader;

    /** @var callable|null */
    private $timezone_reader;

    /** @var callable|null */
    private $now_reader;

    /** @var callable|null */
    private $push_enabled_reader;

    /**
     * @param callable|null $task_reader (int $task_id): ?array
     * @param callable|null $list_reader (int $list_id): ?array
     * @param callable|null $timezone_reader (): string
     * @param callable|null $now_reader (): DateTimeImmutable|string
     * @param callable|null $push_enabled_reader (): bool
     */
    public function __construct(
        ?callable $task_reader = null,
        ?callable $list_reader = null,
        ?callable $timezone_reader = null,
        ?callable $now_reader = null,
        ?callable $push_enabled_reader = null
    ) {
        $this->task_reader = $task_reader;
        $this->list_reader = $list_reader;
        $this->timezone_reader = $timezone_reader;
        $this->now_reader = $now_reader;
        $this->push_enabled_reader = $push_enabled_reader;
    }

    /**
     * @param array<int,array<string,mixed>> $tasks
     * @return array{
     *     success:bool,
     *     results:array<string,array{status:string,task?:array<string,mixed>}>
     * }
     */
    public function execute(array $tasks): array {
        $results = [];

        foreach ($this->normalize_tasks($tasks) as $task_input) {
            $task_id = (int) $task_input['task_id'];
            $key = (string) $task_id;
            $results[$key] = $this->evaluate_task(
                $task_id,
                (string) $task_input['expected_execution_available_at']
            );
        }

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $tasks
     * @return array<int,array{task_id:int,expected_execution_available_at:string}>
     */
    private function normalize_tasks(array $tasks): array {
        $normalized = [];
        $seen = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $task_id = absint($task['task_id'] ?? 0);
            if ($task_id < 1 || isset($seen[$task_id])) {
                continue;
            }

            $seen[$task_id] = true;
            $normalized[] = [
                'task_id' => $task_id,
                'expected_execution_available_at' => sanitize_text_field(
                    (string) ($task['expected_execution_available_at'] ?? '')
                ),
            ];
        }

        return array_slice($normalized, 0, 50);
    }

    /**
     * @return array{status:string,task?:array<string,mixed>}
     */
    private function evaluate_task(int $task_id, string $expected_execution_available_at): array {
        $task = $this->read_task($task_id);

        if ($task === null) {
            return $this->result(self::STATUS_INELIGIBLE);
        }

        if (!$this->execution_available_at_matches($task, $expected_execution_available_at)) {
            return $this->result(self::STATUS_STALE);
        }

        if (!$this->is_push_enabled()) {
            return $this->result(self::STATUS_INELIGIBLE);
        }

        if (!$this->is_pending_active_task($task)) {
            return $this->result(self::STATUS_INELIGIBLE);
        }

        if ($this->is_archived_task($task)) {
            return $this->result(self::STATUS_INELIGIBLE);
        }

        $execution_at = $this->parse_site_datetime($this->stored_execution_available_at($task));
        $now = $this->read_now();

        if ($execution_at === null || $execution_at->getTimestamp() > $now->getTimestamp()) {
            return $this->result(self::STATUS_INELIGIBLE);
        }

        if ($this->resolve_timing_policy()->is_overdue(
            AA_Task::from_array($task),
            $now->format('Y-m-d H:i:s')
        )) {
            return $this->result(self::STATUS_INELIGIBLE);
        }

        return $this->result(self::STATUS_ELIGIBLE, $this->build_eligible_task_payload($task));
    }

    /**
     * @return array{status:string,task?:array<string,mixed>}
     */
    private function result(string $status, ?array $task = null): array {
        $out = ['status' => $status];

        if ($status === self::STATUS_ELIGIBLE && $task !== null) {
            $out['task'] = $task;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $task
     * @return array{
     *     task_id:int,
     *     title:string,
     *     list_id:int,
     *     list_title:?string,
     *     due_at:?string
     * }
     */
    private function build_eligible_task_payload(array $task): array {
        $list_id = (int) ($task['list_id'] ?? 0);
        $list = $list_id > 0 ? $this->read_list($list_id) : null;
        $list_title = is_array($list)
            ? sanitize_text_field((string) ($list['title'] ?? ''))
            : null;

        if ($list_title === '') {
            $list_title = null;
        }

        return [
            'task_id' => (int) ($task['id'] ?? 0),
            'title' => sanitize_text_field((string) ($task['title'] ?? '')),
            'list_id' => $list_id,
            'list_title' => $list_title,
            'due_at' => $this->format_due_at_iso($task['due_at'] ?? null),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function read_task(int $task_id): ?array {
        if ($this->task_reader !== null) {
            $task = call_user_func($this->task_reader, $task_id);

            return is_array($task) ? $task : null;
        }

        return TaskRepository::find_by_id($task_id);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function read_list(int $list_id): ?array {
        if ($this->list_reader !== null) {
            $list = call_user_func($this->list_reader, $list_id);

            return is_array($list) ? $list : null;
        }

        return TaskListRepository::find_by_id($list_id);
    }

    /**
     * @param array<string,mixed> $task
     */
    private function execution_available_at_matches(array $task, string $expected_execution_available_at): bool {
        $stored_raw = $this->stored_execution_available_at($task);

        if ($stored_raw === null) {
            return false;
        }

        $expected = $this->parse_expected_execution_available_at($expected_execution_available_at);
        $stored = $this->parse_site_datetime($stored_raw);

        if ($expected === null || $stored === null) {
            return false;
        }

        return $expected->getTimestamp() === $stored->getTimestamp();
    }

    /**
     * @param array<string,mixed> $task
     */
    private function stored_execution_available_at(array $task): ?string {
        if (!array_key_exists('execution_available_at', $task)) {
            return null;
        }

        $value = $task['execution_available_at'];
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<string,mixed> $task
     */
    private function is_pending_active_task(array $task): bool {
        $status = isset($task['status']) ? strtolower(trim((string) $task['status'])) : '';

        if ($status !== 'pending') {
            return false;
        }

        $completed_at = $task['completed_at'] ?? null;

        return $completed_at === null || trim((string) $completed_at) === '';
    }

    /**
     * @param array<string,mixed> $task
     */
    private function is_archived_task(array $task): bool {
        $archived_at = $task['archived_at'] ?? null;

        return $archived_at !== null && trim((string) $archived_at) !== '';
    }

    private function resolve_timing_policy(): AA_Task_Execution_Timing_Policy {
        $timezone_name = trim($this->read_timezone());

        try {
            $timezone = new DateTimeZone($timezone_name !== '' ? $timezone_name : 'America/Mexico_City');
        } catch (Exception $e) {
            $timezone = new DateTimeZone('America/Mexico_City');
        }

        return new AA_Task_Execution_Timing_Policy($timezone);
    }

    private function format_due_at_iso($value): ?string {
        if ($value === null) {
            return null;
        }

        $parsed = $this->parse_site_datetime((string) $value);

        if ($parsed === null) {
            return null;
        }

        return $parsed->format('c');
    }

    private function is_push_enabled(): bool {
        if ($this->push_enabled_reader !== null) {
            return (bool) call_user_func($this->push_enabled_reader);
        }

        return (int) get_option('aa_push_task_execution_available_enabled', 1) === 1;
    }

    private function read_timezone(): string {
        if ($this->timezone_reader !== null) {
            return (string) call_user_func($this->timezone_reader);
        }

        return (string) get_option('aa_timezone', 'America/Mexico_City');
    }

    private function read_now(): DateTimeImmutable {
        if ($this->now_reader !== null) {
            $now = call_user_func($this->now_reader);

            if ($now instanceof DateTimeImmutable) {
                return $now;
            }

            if (is_string($now) && trim($now) !== '') {
                $parsed = $this->parse_site_datetime(trim($now));
                if ($parsed instanceof DateTimeImmutable) {
                    return $parsed;
                }
            }
        }

        return $this->parse_site_datetime(TaskUseCaseSupport::resolve_now())
            ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function parse_expected_execution_available_at(string $value): ?DateTimeImmutable {
        $raw = trim($value);

        if ($raw === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception $e) {
            return null;
        }
    }

    private function parse_site_datetime(?string $value): ?DateTimeImmutable {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw, new DateTimeZone($this->read_timezone()));
        } catch (Exception $e) {
            return null;
        }
    }
}
