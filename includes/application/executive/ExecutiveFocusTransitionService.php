<?php
/**
 * Executive Focus Transition Service — cambios de foco compartidos (MC5).
 *
 * Application: orquesta policy de selección y persistencia de sprint/focus state.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-focus-selection-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-focus-state-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-proposal-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-sprint-policy.php';

final class ExecutiveFocusTransitionService {

    /**
     * @param array<string,mixed> $board
     * @return list<int>
     */
    public function resolve_eligible_focus_list_ids(array $board): array {
        return (new AA_Executive_Proposal_Policy())->resolve_eligible_focus_list_ids_from_board($board);
    }

    /**
     * @param array<string,mixed> $board
     * @param array<string,mixed> $sprint
     * @param array<string,mixed> $focus_state
     * @return array{
     *     success:bool,
     *     error?:array{code:string,message:string},
     *     sprint?:array<string,mixed>,
     *     focus_state?:array<string,mixed>,
     *     selected_focus_list_id?:int,
     *     previous_focus_list_id?:int|null
     * }
     */
    public function change_to_random_focus(
        array $board,
        array $sprint,
        array $focus_state,
        ?int $current_focus_list_id,
        int $now_ts,
        bool $sprint_active,
        ?callable $randomizer = null
    ): array {
        $eligible_ids = $this->resolve_eligible_focus_list_ids($board);

        if ($eligible_ids === []) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'no_eligible_focus',
                    'message' => 'No hay listas elegibles para cambiar el foco.',
                ],
            ];
        }

        $selected_id = AA_Executive_Focus_Selection_Policy::select_random_focus(
            $eligible_ids,
            $current_focus_list_id,
            $randomizer
        );

        if ($selected_id === null) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'no_eligible_focus',
                    'message' => 'No hay listas elegibles para cambiar el foco.',
                ],
            ];
        }

        $previous_id = $current_focus_list_id !== null && $current_focus_list_id > 0
            ? $current_focus_list_id
            : null;
        $focus_state = AA_Executive_Focus_State_Policy::reset_dismiss_streak($focus_state);
        $focus_state = AA_Executive_Focus_State_Policy::set_previous_focus_list_id($focus_state, $previous_id);

        if ($sprint_active) {
            $sprint = AA_Executive_Sprint_Policy::update_active_focus_without_renew($sprint, $selected_id);
            $focus_state = AA_Executive_Focus_State_Policy::clear_manual_focus($focus_state);
        } else {
            $focus_state = AA_Executive_Focus_State_Policy::set_manual_focus(
                $focus_state,
                $selected_id,
                $previous_id,
                $now_ts
            );
        }

        return [
            'success' => true,
            'sprint' => $sprint,
            'focus_state' => $focus_state,
            'selected_focus_list_id' => $selected_id,
            'previous_focus_list_id' => $previous_id,
        ];
    }

    /**
     * @param array<string,mixed> $board
     * @param array<string,mixed> $sprint
     * @param array<string,mixed> $focus_state
     * @return array{
     *     success:bool,
     *     error?:array{code:string,message:string},
     *     sprint?:array<string,mixed>,
     *     focus_state?:array<string,mixed>,
     *     selected_focus_list_id?:int,
     *     previous_focus_list_id?:int|null
     * }
     */
    public function change_to_previous_focus(
        array $board,
        array $sprint,
        array $focus_state,
        ?int $current_focus_list_id,
        int $now_ts,
        bool $sprint_active
    ): array {
        $sanitized_focus = AA_Executive_Focus_State_Policy::sanitize($focus_state);
        $previous_id = (int) ($sanitized_focus['previous_focus_list_id'] ?? 0);

        if ($previous_id < 1) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'previous_focus_unavailable',
                    'message' => 'No hay foco anterior disponible.',
                ],
            ];
        }

        $eligible_ids = $this->resolve_eligible_focus_list_ids($board);

        if (!in_array($previous_id, $eligible_ids, true)) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'previous_focus_unavailable',
                    'message' => 'El foco anterior ya no está disponible.',
                ],
            ];
        }

        $current_id = $current_focus_list_id !== null && $current_focus_list_id > 0
            ? $current_focus_list_id
            : null;
        $focus_state = AA_Executive_Focus_State_Policy::reset_dismiss_streak($focus_state);
        $focus_state = AA_Executive_Focus_State_Policy::set_previous_focus_list_id($focus_state, $current_id);

        if ($sprint_active) {
            $sprint = AA_Executive_Sprint_Policy::update_active_focus_without_renew($sprint, $previous_id);
            $focus_state = AA_Executive_Focus_State_Policy::clear_manual_focus($focus_state);
        } else {
            $focus_state = AA_Executive_Focus_State_Policy::set_manual_focus(
                $focus_state,
                $previous_id,
                $current_id,
                $now_ts
            );
        }

        return [
            'success' => true,
            'sprint' => $sprint,
            'focus_state' => $focus_state,
            'selected_focus_list_id' => $previous_id,
            'previous_focus_list_id' => $current_id,
        ];
    }

    /**
     * @param list<int> $eligible_list_ids
     * @return array{
     *     success:bool,
     *     error?:array{code:string,message:string},
     *     focus_state?:array<string,mixed>,
     *     selected_focus_list_id?:int,
     *     previous_focus_list_id?:int|null
     * }
     */
    public function apply_third_dismiss_focus_change(
        array $board,
        array $focus_state,
        ?int $current_focus_list_id,
        int $now_ts,
        ?callable $randomizer = null,
        ?array $eligible_list_ids = null
    ): array {
        $eligible_ids = $eligible_list_ids ?? $this->resolve_eligible_focus_list_ids($board);

        if ($eligible_ids === []) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'no_eligible_focus',
                    'message' => 'No hay listas elegibles para cambiar el foco.',
                ],
            ];
        }

        $selected_id = AA_Executive_Focus_Selection_Policy::select_random_focus(
            $eligible_ids,
            $current_focus_list_id,
            $randomizer
        );

        if ($selected_id === null) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'no_eligible_focus',
                    'message' => 'No hay listas elegibles para cambiar el foco.',
                ],
            ];
        }

        $previous_id = $current_focus_list_id !== null && $current_focus_list_id > 0
            ? $current_focus_list_id
            : null;
        $focus_state = AA_Executive_Focus_State_Policy::reset_dismiss_streak($focus_state);
        $focus_state = AA_Executive_Focus_State_Policy::set_manual_focus(
            $focus_state,
            $selected_id,
            $previous_id,
            $now_ts
        );

        return [
            'success' => true,
            'focus_state' => $focus_state,
            'selected_focus_list_id' => $selected_id,
            'previous_focus_list_id' => $previous_id,
        ];
    }
}
