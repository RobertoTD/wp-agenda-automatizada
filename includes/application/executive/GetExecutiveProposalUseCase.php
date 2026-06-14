<?php
/**
 * Get Executive Proposal Use Case — motor top-3 de Propuesta ejecutiva (MC1).
 *
 * Orquesta GetTaskBoardUseCase, policy de selección y mapper de payload.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-contract.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-proposal-policy.php';
require_once __DIR__ . '/ExecutiveProposalMapper.php';
require_once __DIR__ . '/../tasks/GetTaskBoardUseCase.php';
require_once __DIR__ . '/../tasks/TaskUseCaseSupport.php';

final class GetExecutiveProposalUseCase {

    /** @var callable|null */
    private $board_reader;

    /**
     * @param callable|null $board_reader Debe devolver payload de GetTaskBoardUseCase::execute().
     */
    public function __construct(?callable $board_reader = null) {
        $this->board_reader = $board_reader;
    }

    /**
     * @return array<string,mixed>
     */
    public function execute(): array {
        $board = $this->read_board();
        $selection = (new AA_Executive_Proposal_Policy())->propose($board);
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
}
