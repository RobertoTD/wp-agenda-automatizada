<?php
/**
 * Record Executive Action Use Case — acciones ejecutivas desde Propuesta ejecutiva (MC3/MC4).
 *
 * Orquesta validación contra propuesta actual, mutaciones permitidas, sprint y recálculo.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-contract.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-sprint-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-focus-state-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-contract.php';
require_once dirname(__DIR__, 2) . '/repositories/ExecutiveSprintStateRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ExecutiveFocusStateRepository.php';
require_once __DIR__ . '/ExecutiveFocusTransitionService.php';
require_once __DIR__ . '/GetExecutiveProposalUseCase.php';
require_once __DIR__ . '/../tasks/ChangeTaskStatusUseCase.php';
require_once __DIR__ . '/../tasks/GetTaskBoardUseCase.php';
require_once __DIR__ . '/../tasks/RecordTaskDismissSignalUseCase.php';
require_once __DIR__ . '/../tasks/MarkTaskMissedUseCase.php';
require_once __DIR__ . '/../tasks/TaskUseCaseSupport.php';

final class RecordExecutiveActionUseCase {

    /** @var callable|null */
    private $proposal_reader;

    /** @var callable|null */
    private $change_status_executor;

    /** @var callable|null */
    private $dismiss_executor;

    /** @var callable|null */
    private $missed_executor;

    /** @var callable|null */
    private $sprint_reader;

    /** @var callable|null */
    private $sprint_writer;

    /** @var callable|null */
    private $user_id_resolver;

    /** @var callable|null */
    private $now_ts_resolver;

    /** @var callable|null */
    private $focus_reader;

    /** @var callable|null */
    private $focus_writer;

    /** @var callable|null */
    private $board_reader;

    /** @var callable|null */
    private $randomizer;

    /** @var ExecutiveFocusTransitionService|null */
    private $focus_transition_service;

    /**
     * @param callable|null $proposal_reader Debe devolver payload de GetExecutiveProposalUseCase.
     * @param callable|null $change_status_executor Debe aceptar input y devolver resultado de ChangeTaskStatusUseCase.
     * @param callable|null $dismiss_executor Debe aceptar input y devolver resultado de RecordTaskDismissSignalUseCase.
     * @param callable|null $sprint_reader Debe aceptar user_id y devolver estado de sprint.
     * @param callable|null $sprint_writer Debe aceptar (user_id, state) y persistir.
     * @param callable|null $user_id_resolver Debe devolver int user_id.
     * @param callable|null $now_ts_resolver Debe devolver int Unix timestamp.
     * @param callable|null $focus_reader Debe aceptar user_id y devolver estado de foco manual.
     * @param callable|null $focus_writer Debe aceptar (user_id, state) y persistir.
     * @param callable|null $board_reader Debe devolver payload de GetTaskBoardUseCase.
     * @param callable|null $randomizer Randomizer inyectable para tests.
     * @param callable|null $missed_executor Debe aceptar input y devolver resultado de MarkTaskMissedUseCase.
     */
    public function __construct(
        ?callable $proposal_reader = null,
        ?callable $change_status_executor = null,
        ?callable $dismiss_executor = null,
        ?callable $sprint_reader = null,
        ?callable $sprint_writer = null,
        ?callable $user_id_resolver = null,
        ?callable $now_ts_resolver = null,
        ?callable $focus_reader = null,
        ?callable $focus_writer = null,
        ?callable $board_reader = null,
        ?callable $randomizer = null,
        ?ExecutiveFocusTransitionService $focus_transition_service = null,
        ?callable $missed_executor = null
    ) {
        $this->proposal_reader = $proposal_reader;
        $this->change_status_executor = $change_status_executor;
        $this->dismiss_executor = $dismiss_executor;
        $this->sprint_reader = $sprint_reader;
        $this->sprint_writer = $sprint_writer;
        $this->user_id_resolver = $user_id_resolver;
        $this->now_ts_resolver = $now_ts_resolver;
        $this->focus_reader = $focus_reader;
        $this->focus_writer = $focus_writer;
        $this->board_reader = $board_reader;
        $this->randomizer = $randomizer;
        $this->focus_transition_service = $focus_transition_service;
        $this->missed_executor = $missed_executor;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $task_id = TaskUseCaseSupport::normalize_task_id($input['task_id'] ?? 0);
        $action_key = trim((string) ($input['action_key'] ?? ''));

        if ($task_id < 1 || $action_key === '') {
            return TaskUseCaseSupport::fail('invalid_request', 'Solicitud de acción ejecutiva inválida.');
        }

        try {
            $proposal = $this->read_proposal();

            if (($proposal['status'] ?? '') !== AA_Executive_Contract::STATUS_READY) {
                return TaskUseCaseSupport::fail('proposal_empty', 'No hay propuesta ejecutiva disponible.');
            }

            $current = $this->find_current_task($proposal);

            if ($current === null) {
                return TaskUseCaseSupport::fail('proposal_empty', 'No hay tarea actual en la propuesta ejecutiva.');
            }

            if ((int) ($current['task_id'] ?? 0) !== $task_id) {
                return TaskUseCaseSupport::fail('task_not_current', 'La tarea no es la acción actual del Ejecutor.');
            }

            $action = $this->find_action($current, $action_key);

            if ($action === null) {
                return TaskUseCaseSupport::fail('action_not_allowed', 'La acción no está permitida en la propuesta ejecutiva.');
            }

            $focus_list_id = (int) (($proposal['focus_list']['id'] ?? 0));
            $user_id = $this->resolve_user_id();
            $now_ts = $this->resolve_now_ts();
            $sprint = $this->read_sprint($user_id);
            $sprint_was_active = AA_Executive_Sprint_Policy::is_active($sprint, $now_ts);
            $focus_controls = is_array($proposal['meta']['focus_controls'] ?? null)
                ? $proposal['meta']['focus_controls']
                : [];
            $eligible_focus_list_ids_before_action = is_array($focus_controls['eligible_focus_list_ids'] ?? null)
                ? array_values(array_map('intval', $focus_controls['eligible_focus_list_ids']))
                : [];

            $execution = $this->execute_action($action, $task_id, $current);

            if (empty($execution['success'])) {
                $error = is_array($execution['error'] ?? null) ? $execution['error'] : [];

                return TaskUseCaseSupport::fail(
                    (string) ($error['code'] ?? 'action_failed'),
                    (string) ($error['message'] ?? 'No se pudo ejecutar la acción ejecutiva.')
                );
            }

            $this->apply_sprint_after_action(
                $action,
                $sprint,
                $focus_list_id,
                $user_id,
                $now_ts,
                $sprint_was_active
            );
            $this->apply_focus_after_action(
                $action,
                $focus_list_id,
                $user_id,
                $now_ts,
                $sprint_was_active,
                $eligible_focus_list_ids_before_action
            );

            $new_proposal = $this->read_proposal();

            return TaskUseCaseSupport::ok([
                'action' => [
                    'key' => $action_key,
                    'type' => (string) ($action['type'] ?? ''),
                    'task_id' => $task_id,
                    'mutated' => !empty($execution['mutated']),
                ],
                'proposal' => $new_proposal,
                'client_action' => $execution['client_action'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            return TaskUseCaseSupport::fail(
                'executive_action_unavailable',
                'No se pudo ejecutar la acción ejecutiva.'
            );
        }
    }

    /**
     * @param array<string,mixed> $action
     * @param array<string,mixed> $sprint
     */
    private function apply_sprint_after_action(
        array $action,
        array $sprint,
        int $focus_list_id,
        int $user_id,
        int $now_ts,
        bool $sprint_was_active
    ): void {
        if ($focus_list_id < 1 || $user_id < 1) {
            return;
        }

        if (AA_Executive_Sprint_Policy::should_renew_for_executive_action($action)) {
            $this->write_sprint(
                $user_id,
                AA_Executive_Sprint_Policy::renew($sprint, $focus_list_id, $now_ts)
            );

            return;
        }

        $type = strtolower(trim((string) ($action['type'] ?? '')));
        $key = strtolower(trim((string) ($action['key'] ?? '')));

        if ($type === AA_Executable_Contract::ACTION_INTENT && $key === 'dismiss') {
            if ($sprint_was_active) {
                return;
            }

            if ($sprint !== [] || AA_Executive_Sprint_Policy::is_expired($sprint, $now_ts)) {
                $this->write_sprint($user_id, AA_Executive_Sprint_Policy::empty_state());
            }
        }
    }

    /**
     * @param array<string,mixed> $action
     */
    private function apply_focus_after_action(
        array $action,
        int $focus_list_id,
        int $user_id,
        int $now_ts,
        bool $sprint_was_active,
        array $eligible_focus_list_ids_before_action = []
    ): void {
        if ($user_id < 1) {
            return;
        }

        $focus_state = $this->read_focus($user_id);

        if (AA_Executive_Sprint_Policy::should_renew_for_executive_action($action)) {
            $this->write_focus(
                $user_id,
                AA_Executive_Focus_State_Policy::reset_dismiss_streak($focus_state)
            );

            return;
        }

        $type = strtolower(trim((string) ($action['type'] ?? '')));
        $key = strtolower(trim((string) ($action['key'] ?? '')));

        if ($type !== AA_Executable_Contract::ACTION_INTENT || $key !== 'dismiss' || $sprint_was_active) {
            return;
        }

        $streak_result = AA_Executive_Focus_State_Policy::increment_dismiss_streak($focus_state);
        $focus_state = $streak_result['state'];

        if (!empty($streak_result['triggered'])) {
            $board = $this->read_board();
            $transition = $this->focus_transition_service ?? new ExecutiveFocusTransitionService();
            $change = $transition->apply_third_dismiss_focus_change(
                $board,
                $focus_state,
                $focus_list_id > 0 ? $focus_list_id : null,
                $now_ts,
                $this->randomizer,
                $eligible_focus_list_ids_before_action
            );

            if (!empty($change['success']) && is_array($change['focus_state'] ?? null)) {
                $focus_state = $change['focus_state'];
            }
        }

        $this->write_focus($user_id, $focus_state);
    }

    /**
     * @return array<string,mixed>
     */
    private function read_board(): array {
        if ($this->board_reader !== null) {
            $payload = call_user_func($this->board_reader);

            return is_array($payload) ? $payload : [];
        }

        return (new GetTaskBoardUseCase())->execute();
    }

    /**
     * @return array<string,mixed>
     */
    private function read_proposal(): array {
        if ($this->proposal_reader !== null) {
            $payload = call_user_func($this->proposal_reader);

            return is_array($payload) ? $payload : [];
        }

        return (new GetExecutiveProposalUseCase(
            $this->board_reader,
            $this->sprint_reader,
            $this->sprint_writer,
            $this->user_id_resolver,
            $this->now_ts_resolver,
            $this->focus_reader,
            $this->focus_writer
        ))->execute();
    }

    private function resolve_user_id(): int {
        if ($this->user_id_resolver !== null) {
            return max(0, (int) call_user_func($this->user_id_resolver));
        }

        if (function_exists('get_current_user_id')) {
            return max(0, (int) get_current_user_id());
        }

        return 0;
    }

    private function resolve_now_ts(): int {
        if ($this->now_ts_resolver !== null) {
            return max(0, (int) call_user_func($this->now_ts_resolver));
        }

        $now = TaskUseCaseSupport::resolve_now();
        $timestamp = strtotime($now);

        return $timestamp !== false ? $timestamp : 0;
    }

    /**
     * @return array<string,mixed>
     */
    private function read_sprint(int $user_id): array {
        if ($this->sprint_reader !== null) {
            $state = call_user_func($this->sprint_reader, $user_id);

            return is_array($state) ? $state : [];
        }

        return ExecutiveSprintStateRepository::find_for_user($user_id);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function write_sprint(int $user_id, array $state): void {
        if ($this->sprint_writer !== null) {
            call_user_func($this->sprint_writer, $user_id, $state);

            return;
        }

        if ($state === []) {
            ExecutiveSprintStateRepository::clear_for_user($user_id);

            return;
        }

        ExecutiveSprintStateRepository::save_for_user($user_id, $state);
    }

    /**
     * @return array<string,mixed>
     */
    private function read_focus(int $user_id): array {
        if ($this->focus_reader !== null) {
            $state = call_user_func($this->focus_reader, $user_id);

            return is_array($state) ? $state : [];
        }

        return ExecutiveFocusStateRepository::find_for_user($user_id);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function write_focus(int $user_id, array $state): void {
        if ($this->focus_writer !== null) {
            call_user_func($this->focus_writer, $user_id, $state);

            return;
        }

        ExecutiveFocusStateRepository::save_for_user($user_id, $state);
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>|null
     */
    private function find_current_task(array $proposal): ?array {
        $tasks = is_array($proposal['tasks'] ?? null) ? $proposal['tasks'] : [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            if (($task['slot'] ?? '') === AA_Executive_Contract::SLOT_CURRENT) {
                return $task;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $current
     * @return array<string,mixed>|null
     */
    private function find_action(array $current, string $action_key): ?array {
        $actions = is_array($current['executive_actions'] ?? null) ? $current['executive_actions'] : [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            if ((string) ($action['key'] ?? '') === $action_key) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $action
     * @param array<string,mixed> $current
     * @return array{success:bool,mutated?:bool,client_action?:array<string,mixed>|null,error?:array{code:string,message:string}}
     */
    private function execute_action(array $action, int $task_id, array $current): array {
        $type = strtolower(trim((string) ($action['type'] ?? '')));
        $key = strtolower(trim((string) ($action['key'] ?? '')));

        if ($type === AA_Executable_Contract::ACTION_STATUS && $key === 'complete') {
            return $this->execute_complete($task_id);
        }

        if ($type === AA_Executable_Contract::ACTION_STATUS && $key === 'missed') {
            return $this->execute_missed($task_id);
        }

        if ($type === AA_Executable_Contract::ACTION_INTENT && $key === 'dismiss') {
            return $this->execute_dismiss($task_id);
        }

        if ($type === AA_Executable_Contract::ACTION_NAVIGATE) {
            $url = trim((string) ($action['url'] ?? ''));

            if ($url === '') {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'action_failed',
                        'message' => 'La acción de navegación no tiene URL.',
                    ],
                ];
            }

            return [
                'success' => true,
                'mutated' => false,
                'client_action' => [
                    'type' => 'navigate',
                    'url' => $url,
                ],
            ];
        }

        if ($type === AA_Executable_Contract::ACTION_HANDLER) {
            $handler = trim((string) ($action['handler'] ?? ''));

            if ($handler === '') {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'action_failed',
                        'message' => 'La acción handler no está configurada.',
                    ],
                ];
            }

            return [
                'success' => true,
                'mutated' => false,
                'client_action' => [
                    'type' => 'handler',
                    'handler' => $handler,
                    'origin_key' => isset($current['origin_key']) ? (string) $current['origin_key'] : null,
                    'task_id' => $task_id,
                    'source' => isset($current['source']) ? (string) $current['source'] : null,
                    'label' => (string) ($action['label'] ?? ''),
                ],
            ];
        }

        return [
            'success' => false,
            'error' => [
                'code' => 'action_not_allowed',
                'message' => 'Tipo de acción ejecutiva no soportado.',
            ],
        ];
    }

    /**
     * @return array{success:bool,mutated?:bool,client_action?:null,error?:array{code:string,message:string}}
     */
    private function execute_complete(int $task_id): array {
        $result = $this->change_status_executor !== null
            ? call_user_func($this->change_status_executor, [
                'task_id' => $task_id,
                'status' => 'done',
            ])
            : (new ChangeTaskStatusUseCase())->execute([
                'task_id' => $task_id,
                'status' => 'done',
            ]);

        if (empty($result['success'])) {
            return [
                'success' => false,
                'error' => is_array($result['error'] ?? null) ? $result['error'] : [
                    'code' => 'action_failed',
                    'message' => 'No se pudo completar la tarea.',
                ],
            ];
        }

        return [
            'success' => true,
            'mutated' => true,
            'client_action' => null,
        ];
    }

    /**
     * @return array{success:bool,mutated?:bool,client_action?:null,error?:array{code:string,message:string}}
     */
    private function execute_missed(int $task_id): array {
        $result = $this->missed_executor !== null
            ? call_user_func($this->missed_executor, ['task_id' => $task_id])
            : (new MarkTaskMissedUseCase())->execute(['task_id' => $task_id]);

        if (empty($result['success'])) {
            return [
                'success' => false,
                'error' => is_array($result['error'] ?? null) ? $result['error'] : [
                    'code' => 'action_failed',
                    'message' => 'No se pudo marcar la tarea como no realizada.',
                ],
            ];
        }

        return [
            'success' => true,
            'mutated' => true,
            'client_action' => null,
        ];
    }

    /**
     * @return array{success:bool,mutated?:bool,client_action?:null,error?:array{code:string,message:string}}
     */
    private function execute_dismiss(int $task_id): array {
        $result = $this->dismiss_executor !== null
            ? call_user_func($this->dismiss_executor, ['task_id' => $task_id])
            : (new RecordTaskDismissSignalUseCase())->execute(['task_id' => $task_id]);

        if (empty($result['success'])) {
            return [
                'success' => false,
                'error' => is_array($result['error'] ?? null) ? $result['error'] : [
                    'code' => 'action_failed',
                    'message' => 'No se pudo registrar la señal de ignorar.',
                ],
            ];
        }

        return [
            'success' => true,
            'mutated' => true,
            'client_action' => null,
        ];
    }
}
