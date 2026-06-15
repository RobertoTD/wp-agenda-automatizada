<?php
/**
 * Change Executive Focus Use Case — controles manuales de foco (MC5).
 *
 * Orquesta cambio de foco, anterior y debug de expiración de sprint.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-contract.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-focus-state-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-proposal-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-sprint-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/ExecutiveFocusStateRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ExecutiveSprintStateRepository.php';
require_once __DIR__ . '/ExecutiveFocusTransitionService.php';
require_once __DIR__ . '/GetExecutiveProposalUseCase.php';
require_once __DIR__ . '/../tasks/GetTaskBoardUseCase.php';
require_once __DIR__ . '/../tasks/TaskUseCaseSupport.php';

final class ChangeExecutiveFocusUseCase {

    private const ACTION_CHANGE_FOCUS = 'change_focus';

    private const ACTION_PREVIOUS_FOCUS = 'previous_focus';

    private const ACTION_EXPIRE_SPRINT_DEBUG = 'expire_sprint_debug';

    /** @var callable|null */
    private $board_reader;

    /** @var callable|null */
    private $sprint_reader;

    /** @var callable|null */
    private $sprint_writer;

    /** @var callable|null */
    private $focus_reader;

    /** @var callable|null */
    private $focus_writer;

    /** @var callable|null */
    private $user_id_resolver;

    /** @var callable|null */
    private $now_ts_resolver;

    /** @var callable|null */
    private $randomizer;

    /** @var ExecutiveFocusTransitionService|null */
    private $transition_service;

    /**
     * @param callable|null $board_reader
     * @param callable|null $sprint_reader
     * @param callable|null $sprint_writer
     * @param callable|null $focus_reader
     * @param callable|null $focus_writer
     * @param callable|null $user_id_resolver
     * @param callable|null $now_ts_resolver
     * @param callable|null $randomizer
     */
    public function __construct(
        ?callable $board_reader = null,
        ?callable $sprint_reader = null,
        ?callable $sprint_writer = null,
        ?callable $focus_reader = null,
        ?callable $focus_writer = null,
        ?callable $user_id_resolver = null,
        ?callable $now_ts_resolver = null,
        ?callable $randomizer = null,
        ?ExecutiveFocusTransitionService $transition_service = null
    ) {
        $this->board_reader = $board_reader;
        $this->sprint_reader = $sprint_reader;
        $this->sprint_writer = $sprint_writer;
        $this->focus_reader = $focus_reader;
        $this->focus_writer = $focus_writer;
        $this->user_id_resolver = $user_id_resolver;
        $this->now_ts_resolver = $now_ts_resolver;
        $this->randomizer = $randomizer;
        $this->transition_service = $transition_service;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $focus_action = strtolower(trim((string) ($input['focus_action'] ?? '')));

        if (!in_array($focus_action, [
            self::ACTION_CHANGE_FOCUS,
            self::ACTION_PREVIOUS_FOCUS,
            self::ACTION_EXPIRE_SPRINT_DEBUG,
        ], true)) {
            return TaskUseCaseSupport::fail('invalid_focus_action', 'Acción de foco ejecutiva inválida.');
        }

        try {
            $board = $this->read_board();
            $user_id = $this->resolve_user_id();
            $now_ts = $this->resolve_now_ts();
            $sprint = $this->read_sprint($user_id);
            $focus_state = $this->read_focus($user_id);

            if (AA_Executive_Sprint_Policy::is_expired($sprint, $now_ts)) {
                $this->write_sprint($user_id, AA_Executive_Sprint_Policy::empty_state());
                $sprint = AA_Executive_Sprint_Policy::empty_state();
            }

            $cleared_focus = AA_Executive_Focus_State_Policy::clear_expired_manual_focus($focus_state, $now_ts);

            if ($cleared_focus !== $focus_state) {
                $this->write_focus($user_id, $cleared_focus);
                $focus_state = $cleared_focus;
            }

            $sprint_active = AA_Executive_Sprint_Policy::is_active($sprint, $now_ts);
            $current_focus_list_id = $this->resolve_current_focus_list_id(
                $board,
                $sprint,
                $focus_state,
                $now_ts,
                $sprint_active
            );
            $transition = $this->transition_service ?? new ExecutiveFocusTransitionService();
            $changed = false;
            $selected_focus_list_id = null;
            $previous_focus_list_id = null;

            if ($focus_action === self::ACTION_EXPIRE_SPRINT_DEBUG) {
                if ($sprint === []) {
                    return TaskUseCaseSupport::fail(
                        'executive_focus_unavailable',
                        'No hay sprint activo para expirar.'
                    );
                }

                $sprint = AA_Executive_Sprint_Policy::expire_for_debug($sprint, $now_ts);
                $this->write_sprint($user_id, $sprint);
                $changed = true;
            } elseif ($focus_action === self::ACTION_CHANGE_FOCUS) {
                $result = $transition->change_to_random_focus(
                    $board,
                    $sprint,
                    $focus_state,
                    $current_focus_list_id,
                    $now_ts,
                    $sprint_active,
                    $this->randomizer
                );

                if (empty($result['success'])) {
                    $error = is_array($result['error'] ?? null) ? $result['error'] : [];

                    return TaskUseCaseSupport::fail(
                        (string) ($error['code'] ?? 'executive_focus_unavailable'),
                        (string) ($error['message'] ?? 'No se pudo cambiar el foco.')
                    );
                }

                $sprint = is_array($result['sprint'] ?? null) ? $result['sprint'] : $sprint;
                $focus_state = is_array($result['focus_state'] ?? null) ? $result['focus_state'] : $focus_state;
                $selected_focus_list_id = (int) ($result['selected_focus_list_id'] ?? 0) ?: null;
                $previous_focus_list_id = isset($result['previous_focus_list_id'])
                    ? (is_int($result['previous_focus_list_id']) ? $result['previous_focus_list_id'] : null)
                    : null;
                $this->write_sprint($user_id, $sprint);
                $this->write_focus($user_id, $focus_state);
                $changed = true;
            } else {
                $result = $transition->change_to_previous_focus(
                    $board,
                    $sprint,
                    $focus_state,
                    $current_focus_list_id,
                    $now_ts,
                    $sprint_active
                );

                if (empty($result['success'])) {
                    $error = is_array($result['error'] ?? null) ? $result['error'] : [];

                    return TaskUseCaseSupport::fail(
                        (string) ($error['code'] ?? 'previous_focus_unavailable'),
                        (string) ($error['message'] ?? 'No hay foco anterior disponible.')
                    );
                }

                $sprint = is_array($result['sprint'] ?? null) ? $result['sprint'] : $sprint;
                $focus_state = is_array($result['focus_state'] ?? null) ? $result['focus_state'] : $focus_state;
                $selected_focus_list_id = (int) ($result['selected_focus_list_id'] ?? 0) ?: null;
                $previous_focus_list_id = isset($result['previous_focus_list_id'])
                    ? (is_int($result['previous_focus_list_id']) ? $result['previous_focus_list_id'] : null)
                    : null;
                $this->write_sprint($user_id, $sprint);
                $this->write_focus($user_id, $focus_state);
                $changed = true;
            }

            $proposal = (new GetExecutiveProposalUseCase(
                $this->board_reader,
                $this->sprint_reader,
                $this->sprint_writer,
                $this->user_id_resolver,
                $this->now_ts_resolver,
                $this->focus_reader,
                $this->focus_writer
            ))->execute();

            return TaskUseCaseSupport::ok([
                'focus_action' => [
                    'key' => $focus_action,
                    'changed' => $changed,
                    'selected_focus_list_id' => $selected_focus_list_id,
                    'previous_focus_list_id' => $previous_focus_list_id,
                ],
                'proposal' => $proposal,
            ]);
        } catch (\Throwable $exception) {
            return TaskUseCaseSupport::fail(
                'executive_focus_unavailable',
                'No se pudo ejecutar la acción de foco ejecutiva.'
            );
        }
    }

    /**
     * @param array<string,mixed> $board
     * @param array<string,mixed> $sprint
     * @param array<string,mixed> $focus_state
     */
    private function resolve_current_focus_list_id(
        array $board,
        array $sprint,
        array $focus_state,
        int $now_ts,
        bool $sprint_active
    ): ?int {
        $preferred_focus_list_id = null;
        $manual_focus_active = false;

        if ($sprint_active) {
            $preferred_focus_list_id = AA_Executive_Sprint_Policy::get_active_focus_list_id($sprint, $now_ts);
        } else {
            $manual_id = AA_Executive_Focus_State_Policy::get_manual_focus_list_id($focus_state, $now_ts);

            if ($manual_id !== null) {
                $preferred_focus_list_id = $manual_id;
                $manual_focus_active = true;
            }
        }

        $selection = (new AA_Executive_Proposal_Policy())->propose($board, [
            'preferred_focus_list_id' => $preferred_focus_list_id,
            'sprint_active' => $sprint_active,
            'manual_focus_active' => $manual_focus_active,
        ]);
        $focus_list_id = (int) ($selection['focus_list_id'] ?? 0);

        return $focus_list_id > 0 ? $focus_list_id : null;
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
}
