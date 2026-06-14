<?php
/**
 * Get Executive Proposal Use Case — motor top-3 de Propuesta ejecutiva (MC1/MC4).
 *
 * Orquesta GetTaskBoardUseCase, policy de selección, sprint y mapper de payload.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-contract.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-proposal-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-sprint-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/ExecutiveSprintStateRepository.php';
require_once __DIR__ . '/ExecutiveProposalMapper.php';
require_once __DIR__ . '/../tasks/GetTaskBoardUseCase.php';
require_once __DIR__ . '/../tasks/TaskUseCaseSupport.php';

final class GetExecutiveProposalUseCase {

    /** @var callable|null */
    private $board_reader;

    /** @var callable|null */
    private $sprint_reader;

    /** @var callable|null */
    private $sprint_writer;

    /** @var callable|null */
    private $user_id_resolver;

    /** @var callable|null */
    private $now_ts_resolver;

    /**
     * @param callable|null $board_reader Debe devolver payload de GetTaskBoardUseCase::execute().
     * @param callable|null $sprint_reader Debe aceptar user_id y devolver estado de sprint.
     * @param callable|null $sprint_writer Debe aceptar (user_id, state) y persistir.
     * @param callable|null $user_id_resolver Debe devolver int user_id.
     * @param callable|null $now_ts_resolver Debe devolver int Unix timestamp.
     */
    public function __construct(
        ?callable $board_reader = null,
        ?callable $sprint_reader = null,
        ?callable $sprint_writer = null,
        ?callable $user_id_resolver = null,
        ?callable $now_ts_resolver = null
    ) {
        $this->board_reader = $board_reader;
        $this->sprint_reader = $sprint_reader;
        $this->sprint_writer = $sprint_writer;
        $this->user_id_resolver = $user_id_resolver;
        $this->now_ts_resolver = $now_ts_resolver;
    }

    /**
     * @return array<string,mixed>
     */
    public function execute(): array {
        $board = $this->read_board();
        $user_id = $this->resolve_user_id();
        $now_ts = $this->resolve_now_ts();
        $sprint = $this->read_sprint($user_id);

        if (AA_Executive_Sprint_Policy::is_expired($sprint, $now_ts)) {
            $this->write_sprint($user_id, AA_Executive_Sprint_Policy::empty_state());
            $sprint = AA_Executive_Sprint_Policy::empty_state();
        }

        $sprint_active = AA_Executive_Sprint_Policy::is_active($sprint, $now_ts);
        $preferred_focus_list_id = $sprint_active
            ? AA_Executive_Sprint_Policy::get_active_focus_list_id($sprint, $now_ts)
            : null;

        $selection = (new AA_Executive_Proposal_Policy())->propose($board, [
            'preferred_focus_list_id' => $preferred_focus_list_id,
            'sprint_active' => $sprint_active,
        ]);

        if (
            $sprint_active
            && $preferred_focus_list_id !== null
            && ($selection['status'] ?? '') === AA_Executive_Contract::STATUS_READY
            && empty($selection['preferred_focus_used'])
        ) {
            $new_focus_list_id = (int) ($selection['focus_list_id'] ?? 0);

            if ($new_focus_list_id > 0 && $new_focus_list_id !== $preferred_focus_list_id) {
                $updated = AA_Executive_Sprint_Policy::update_active_focus_without_renew(
                    $sprint,
                    $new_focus_list_id
                );
                $this->write_sprint($user_id, $updated);
            }
        }

        $now = TaskUseCaseSupport::resolve_now();
        $payload = ExecutiveProposalMapper::map($board, $selection, $now);

        return AA_Executive_Contract::normalize_proposal($payload);
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
}
