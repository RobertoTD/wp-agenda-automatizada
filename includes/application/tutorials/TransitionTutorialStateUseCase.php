<?php
/**
 * Transition Tutorial State Use Case — transición controlada del estado durable de tutoriales.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tutorials/class-aa-tutorial-state-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/TutorialStateRepository.php';

final class TransitionTutorialStateUseCase {

    /** @var callable|null */
    private $now_resolver;

    /**
     * @param callable|null $now_resolver Debe devolver string datetime mysql.
     */
    public function __construct(?callable $now_resolver = null) {
        $this->now_resolver = $now_resolver;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $tutorial_id = isset($input['tutorial_id']) ? sanitize_key((string) $input['tutorial_id']) : '';

        if ($tutorial_id === '') {
            return $this->failure('missing_tutorial_id', 'Falta tutorial_id.');
        }

        $status = isset($input['status']) ? sanitize_key((string) $input['status']) : '';

        if ($status === '') {
            return $this->failure('missing_status', 'Falta status.');
        }

        $transition_input = [
            'status' => $status,
        ];

        if (array_key_exists('current_step_id', $input)) {
            $transition_input['current_step_id'] = $input['current_step_id'];
        }

        $current = TutorialStateRepository::find();
        $result = AA_Tutorial_State_Policy::apply_transition($current, $tutorial_id, $transition_input);

        if (empty($result['ok'])) {
            $error = $result['error'] ?? [];

            return $this->failure(
                (string) ($error['code'] ?? 'invalid_transition'),
                (string) ($error['message'] ?? 'No se pudo aplicar la transición del tutorial.')
            );
        }

        $next_state = $result['state'] ?? AA_Tutorial_State_Policy::empty_state();
        $transition_kind = (string) ($result['transition_kind'] ?? '');
        $now = $this->resolve_now();

        $next_state['tutorials'][$tutorial_id] = $this->apply_timestamps(
            $next_state['tutorials'][$tutorial_id] ?? [],
            $transition_kind,
            $now
        );

        if (!TutorialStateRepository::save($next_state)) {
            return $this->failure('persist_failed', 'No se pudo guardar el estado del tutorial.');
        }

        return [
            'success' => true,
            'data' => TutorialStateRepository::find(),
        ];
    }

    /**
     * @param array<string,mixed> $tutorial
     * @return array<string,mixed>
     */
    private function apply_timestamps(array $tutorial, string $transition_kind, string $now): array {
        $tutorial['updated_at'] = $now;

        if ($transition_kind === 'accept') {
            $tutorial['accepted_at'] = $now;
            $tutorial['started_at'] = $now;
        }

        if ($transition_kind === 'pause') {
            $tutorial['paused_at'] = $now;
        }

        if ($transition_kind === 'complete') {
            $tutorial['completed_at'] = $now;
            $tutorial['current_step_id'] = null;
        }

        return $tutorial;
    }

    /**
     * @return string
     */
    private function resolve_now(): string {
        if ($this->now_resolver !== null) {
            $value = call_user_func($this->now_resolver);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if (function_exists('current_time')) {
            return current_time('mysql');
        }

        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param string $code
     * @param string $message
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function failure(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
