<?php
/**
 * Complete Appointment Confirmation Task Use Case — completa tarea tras confirmación local.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once dirname(__DIR__, 2) . '/domain/appointments/class-aa-appointment-confirmation-task-projector.php';
require_once dirname(__DIR__, 2) . '/repositories/ReservationsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskRepository.php';
require_once dirname(__DIR__, 2) . '/application/tasks/TaskUseCaseSupport.php';

final class CompleteAppointmentConfirmationTaskUseCase {

    /** @var callable|null */
    private $reservation_reader;

    /** @var callable|null */
    private $task_finder;

    /** @var callable|null */
    private $task_completer;

    /**
     * @param callable|null $reservation_reader (int $reservation_id): ?array
     * @param callable|null $task_finder        (int $reservation_id): ?array
     * @param callable|null $task_completer       (int $task_id, string $completed_at): ?array
     */
    public function __construct(
        ?callable $reservation_reader = null,
        ?callable $task_finder = null,
        ?callable $task_completer = null
    ) {
        $this->reservation_reader = $reservation_reader;
        $this->task_finder = $task_finder;
        $this->task_completer = $task_completer;
    }

    /**
     * Best-effort tras persistir aa_reservas.estado = confirmed.
     */
    public static function sync_after_local_confirmation_best_effort(int $reservation_id): void {
        $result = (new self())->execute(['reservation_id' => $reservation_id]);

        if (!empty($result['success'])) {
            return;
        }

        $code = (string) ($result['error']['code'] ?? 'unknown');

        if (in_array($code, [
            'missing_reservation_id',
            'reservation_not_found',
            'task_completion_failed',
        ], true)) {
            error_log(
                '⚠️ [CompleteAppointmentConfirmation] Tarea no completada para reserva '
                . $reservation_id
                . ': '
                . $code
            );
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $reservation_id = (int) ($input['reservation_id'] ?? 0);

        if ($reservation_id < 1) {
            return TaskUseCaseSupport::fail('missing_reservation_id', 'El identificador de la cita es obligatorio.');
        }

        $reservation = $this->read_reservation($reservation_id);

        if ($reservation === null) {
            return TaskUseCaseSupport::fail('reservation_not_found', 'Cita no encontrada.');
        }

        if (!$this->is_confirmed_reservation($reservation)) {
            return $this->skip($reservation_id, 'reservation_not_confirmed');
        }

        $task = $this->find_task($reservation_id);

        if ($task === null) {
            return $this->skip($reservation_id, 'task_not_found');
        }

        $status = strtolower(trim((string) ($task['status'] ?? '')));

        if ($status === 'done') {
            return $this->skip($reservation_id, 'task_already_completed', $task);
        }

        if ($status !== 'pending') {
            return $this->skip($reservation_id, 'task_not_pending', $task);
        }

        $task_id = (int) ($task['id'] ?? 0);

        if ($task_id < 1) {
            return TaskUseCaseSupport::fail('task_completion_failed', 'No se pudo completar la tarea de confirmación.');
        }

        $completed = $this->mark_task_completed($task_id);

        if ($completed === null) {
            return TaskUseCaseSupport::fail('task_completion_failed', 'No se pudo completar la tarea de confirmación.');
        }

        return TaskUseCaseSupport::ok([
            'reservation_id' => $reservation_id,
            'skipped' => false,
            'skip_reason' => null,
            'task_completed' => true,
            'task' => $completed,
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function read_reservation(int $reservation_id): ?array {
        if ($this->reservation_reader !== null) {
            $reservation = call_user_func($this->reservation_reader, $reservation_id);

            return is_array($reservation) ? $reservation : null;
        }

        return ReservationsRepository::find_by_id($reservation_id);
    }

    /**
     * @param array<string,mixed> $reservation
     */
    private function is_confirmed_reservation(array $reservation): bool {
        return strtolower(trim((string) ($reservation['estado'] ?? ''))) === 'confirmed';
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find_task(int $reservation_id): ?array {
        if ($this->task_finder !== null) {
            $task = call_user_func($this->task_finder, $reservation_id);

            return is_array($task) ? $task : null;
        }

        return SeededTaskRepository::find_task_by_origin(
            AA_Appointment_Actions_Catalog::SOURCE_CATEGORY,
            AA_Appointment_Confirmation_Task_Projector::task_origin_key($reservation_id)
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function mark_task_completed(int $task_id): ?array {
        $completed_at = TaskUseCaseSupport::resolve_now();

        if ($this->task_completer !== null) {
            $task = call_user_func($this->task_completer, $task_id, $completed_at);

            return is_array($task) ? $task : null;
        }

        return TaskRepository::mark_completed($task_id, $completed_at);
    }

    /**
     * @param array<string,mixed>|null $task
     * @return array{success:true,data:array<string,mixed>}
     */
    private function skip(int $reservation_id, string $skip_reason, ?array $task = null): array {
        $data = [
            'reservation_id' => $reservation_id,
            'skipped' => true,
            'skip_reason' => $skip_reason,
            'task_completed' => false,
        ];

        if ($task !== null) {
            $data['task'] = $task;
        }

        return TaskUseCaseSupport::ok($data);
    }
}
