<?php
/**
 * Delete Appointment Confirmation Task Use Case — elimina tarea tras cancelación local.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once dirname(__DIR__, 2) . '/domain/appointments/class-aa-appointment-confirmation-task-projector.php';
require_once dirname(__DIR__, 2) . '/repositories/ReservationsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskActionRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';
require_once dirname(__DIR__, 2) . '/application/tasks/TaskUseCaseSupport.php';

final class DeleteAppointmentConfirmationTaskUseCase {

    /** @var callable|null */
    private $reservation_reader;

    /** @var callable|null */
    private $task_finder;

    /** @var callable|null */
    private $actions_deleter;

    /** @var callable|null */
    private $state_deleter;

    /** @var callable|null */
    private $task_deleter;

    /**
     * @param callable|null $reservation_reader (int $reservation_id): ?array
     * @param callable|null $task_finder        (int $reservation_id): ?array
     * @param callable|null $actions_deleter    (int $task_id): int|false
     * @param callable|null $state_deleter      (int $task_id): bool
     * @param callable|null $task_deleter       (int $task_id): bool
     */
    public function __construct(
        ?callable $reservation_reader = null,
        ?callable $task_finder = null,
        ?callable $actions_deleter = null,
        ?callable $state_deleter = null,
        ?callable $task_deleter = null
    ) {
        $this->reservation_reader = $reservation_reader;
        $this->task_finder = $task_finder;
        $this->actions_deleter = $actions_deleter;
        $this->state_deleter = $state_deleter;
        $this->task_deleter = $task_deleter;
    }

    /**
     * Best-effort tras persistir aa_reservas.estado = cancelled.
     */
    public static function sync_after_local_cancellation_best_effort(int $reservation_id): void {
        $result = (new self())->execute(['reservation_id' => $reservation_id]);

        if (!empty($result['success'])) {
            return;
        }

        $code = (string) ($result['error']['code'] ?? 'unknown');
        $stage = (string) ($result['error']['stage'] ?? '');

        if (in_array($code, [
            'missing_reservation_id',
            'reservation_not_found',
            'task_deletion_failed',
        ], true)) {
            $suffix = $stage !== '' ? ' stage=' . $stage : '';
            error_log(
                '⚠️ [DeleteAppointmentConfirmation] Tarea no eliminada para reserva '
                . $reservation_id
                . ': '
                . $code
                . $suffix
            );
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string,stage?:string}}
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

        if (!$this->is_cancelled_reservation($reservation)) {
            return $this->skip($reservation_id, 'reservation_not_cancelled');
        }

        $task = $this->find_task($reservation_id);

        if ($task === null) {
            return $this->skip($reservation_id, 'task_not_found');
        }

        if (!$this->is_matching_confirmation_task($task, $reservation_id)) {
            return $this->skip($reservation_id, 'task_not_found');
        }

        $task_id = (int) ($task['id'] ?? 0);

        if ($task_id < 1) {
            return $this->deletion_failed('No se pudo eliminar la tarea de confirmación.', 'task');
        }

        $actions_deleted = $this->delete_actions($task_id);

        if ($actions_deleted === false) {
            return $this->deletion_failed('No se pudieron eliminar las acciones de la tarea de confirmación.', 'actions');
        }

        if (!$this->delete_state($task_id)) {
            return $this->deletion_failed('No se pudo eliminar el estado de la tarea de confirmación.', 'state');
        }

        if ($this->delete_task_row($task_id)) {
            return TaskUseCaseSupport::ok([
                'reservation_id' => $reservation_id,
                'skipped' => false,
                'skip_reason' => null,
                'task_deleted' => true,
                'task_id' => $task_id,
            ]);
        }

        if ($this->find_task($reservation_id) === null) {
            return TaskUseCaseSupport::ok([
                'reservation_id' => $reservation_id,
                'skipped' => false,
                'skip_reason' => null,
                'task_deleted' => true,
                'task_id' => $task_id,
            ]);
        }

        return $this->deletion_failed('No se pudo eliminar la tarea de confirmación.', 'task');
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
    private function is_cancelled_reservation(array $reservation): bool {
        return strtolower(trim((string) ($reservation['estado'] ?? ''))) === 'cancelled';
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
     * @param array<string,mixed> $task
     */
    private function is_matching_confirmation_task(array $task, int $reservation_id): bool {
        $source_category = strtolower(trim((string) ($task['source_category'] ?? '')));
        $origin_key = trim((string) ($task['origin_key'] ?? ''));

        return $source_category === AA_Appointment_Actions_Catalog::SOURCE_CATEGORY
            && $origin_key === AA_Appointment_Confirmation_Task_Projector::task_origin_key($reservation_id);
    }

    /**
     * @return int|false
     */
    private function delete_actions(int $task_id) {
        if ($this->actions_deleter !== null) {
            return call_user_func($this->actions_deleter, $task_id);
        }

        return TaskActionRepository::delete_by_task_id($task_id);
    }

    private function delete_state(int $task_id): bool {
        if ($this->state_deleter !== null) {
            return call_user_func($this->state_deleter, $task_id) === true;
        }

        return TaskStateRepository::delete_by_task_id($task_id);
    }

    private function delete_task_row(int $task_id): bool {
        if ($this->task_deleter !== null) {
            return call_user_func($this->task_deleter, $task_id) === true;
        }

        return TaskRepository::delete($task_id);
    }

    /**
     * @return array{success:false,error:array{code:string,message:string,stage:string}}
     */
    private function deletion_failed(string $message, string $stage): array {
        return [
            'success' => false,
            'error' => [
                'code' => 'task_deletion_failed',
                'message' => $message,
                'stage' => $stage,
            ],
        ];
    }

    /**
     * @return array{success:true,data:array<string,mixed>}
     */
    private function skip(int $reservation_id, string $skip_reason): array {
        return TaskUseCaseSupport::ok([
            'reservation_id' => $reservation_id,
            'skipped' => true,
            'skip_reason' => $skip_reason,
            'task_deleted' => false,
        ]);
    }
}
