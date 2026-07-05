<?php
/**
 * Reconcile Tutorial State Use Case — suprime el tutorial si ya existen citas creadas.
 *
 * Operación explícita separada de la FSM pública de transiciones de usuario.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/tutorials/class-aa-tutorial-state-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/ReservationsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TutorialStateRepository.php';

final class ReconcileTutorialStateUseCase {

    /** @var callable|null */
    private $now_resolver;

    /** @var callable|null */
    private $reservation_probe;

    /**
     * @param callable|null $now_resolver Debe devolver string datetime mysql.
     * @param callable|null $reservation_probe Debe devolver array{ok:bool,exists:bool}.
     */
    public function __construct(?callable $now_resolver = null, ?callable $reservation_probe = null) {
        $this->now_resolver = $now_resolver;
        $this->reservation_probe = $reservation_probe;
    }

    /**
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(): array {
        $tutorial_id = AA_Tutorial_State_Policy::TUTORIAL_CREATE_TEST_APPOINTMENT;
        $state = TutorialStateRepository::find();
        $effective = AA_Tutorial_State_Policy::get_effective_tutorial($state, $tutorial_id);

        if (($effective['status'] ?? '') === AA_Tutorial_State_Policy::STATUS_COMPLETED) {
            return $this->success($state, false);
        }

        $probe = $this->probe_reservations();

        if (empty($probe['ok'])) {
            return $this->failure(
                'reservation_existence_check_failed',
                'No se pudo comprobar si existen citas.'
            );
        }

        if (empty($probe['exists'])) {
            return $this->success($state, false);
        }

        $result = AA_Tutorial_State_Policy::reconcile_for_reservation_existence(
            $state,
            $tutorial_id,
            true
        );

        if (empty($result['changed'])) {
            return $this->success($result['state'] ?? $state, false);
        }

        $next_state = $result['state'] ?? $state;
        $now = $this->resolve_now();
        $next_state['tutorials'][$tutorial_id] = $this->apply_reconcile_timestamps(
            $next_state['tutorials'][$tutorial_id] ?? [],
            $now
        );

        if (!TutorialStateRepository::save($next_state)) {
            return $this->failure('persist_failed', 'No se pudo guardar el estado del tutorial.');
        }

        return $this->success(TutorialStateRepository::find(), true);
    }

    /**
     * @return array{ok:bool,exists:bool}
     */
    private function probe_reservations(): array {
        if ($this->reservation_probe !== null) {
            $result = call_user_func($this->reservation_probe);

            return is_array($result) ? $result : ['ok' => false, 'exists' => false];
        }

        return ReservationsRepository::probe_has_created_reservations();
    }

    /**
     * @param array<string,mixed> $tutorial
     * @return array<string,mixed>
     */
    private function apply_reconcile_timestamps(array $tutorial, string $now): array {
        $tutorial['completed_at'] = $now;
        $tutorial['updated_at'] = $now;

        return $tutorial;
    }

    /**
     * @param array<string,mixed> $state
     * @return array{success:true,data:array<string,mixed>}
     */
    private function success(array $state, bool $reconciled): array {
        return [
            'success' => true,
            'data' => [
                'version' => (int) ($state['version'] ?? AA_Tutorial_State_Policy::STATE_VERSION),
                'tutorials' => is_array($state['tutorials'] ?? null) ? $state['tutorials'] : [],
                'reconciled' => $reconciled,
            ],
        ];
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
