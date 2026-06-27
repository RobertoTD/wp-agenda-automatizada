<?php
/**
 * Ensure Appointment Confirmation Task Use Case — tarea idempotente por cita pending.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once dirname(__DIR__, 2) . '/domain/appointments/class-aa-appointment-confirmation-task-projector.php';
require_once dirname(__DIR__, 2) . '/infrastructure/appointments/class-aa-appointment-reservation-display-formatter.php';
require_once dirname(__DIR__, 2) . '/repositories/ReservationsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskActionRepository.php';
require_once dirname(__DIR__, 2) . '/application/tasks/SyncAppointmentActionsListUseCase.php';
require_once dirname(__DIR__, 2) . '/application/tasks/TaskUseCaseSupport.php';

final class EnsureAppointmentConfirmationTaskUseCase {

    private const TITLE_MAX_LENGTH = 255;

    /** @var callable|null */
    private $reservation_reader;

    /** @var callable|null */
    private $list_sync;

    /** @var callable|null */
    private $list_resolver;

    /** @var callable|null */
    private $task_upsertor;

    /** @var callable|null */
    private $action_upsertor;

    /**
     * @param callable|null $reservation_reader (int $reservation_id): ?array
     * @param callable|null $list_sync          (): void
     * @param callable|null $list_resolver      (): ?array
     * @param callable|null $task_upsertor      (array $payload): ?array
     * @param callable|null $action_upsertor    (int $task_id, array $payload): ?array
     */
    public function __construct(
        ?callable $reservation_reader = null,
        ?callable $list_sync = null,
        ?callable $list_resolver = null,
        ?callable $task_upsertor = null,
        ?callable $action_upsertor = null
    ) {
        $this->reservation_reader = $reservation_reader;
        $this->list_sync = $list_sync;
        $this->list_resolver = $list_resolver;
        $this->task_upsertor = $task_upsertor;
        $this->action_upsertor = $action_upsertor;
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

        if (!$this->is_pending_reservation($reservation)) {
            return TaskUseCaseSupport::fail('reservation_not_confirmable', 'La cita no admite tarea de confirmación.');
        }

        $list = $this->resolve_active_appointment_actions_list();

        if ($list === null) {
            return TaskUseCaseSupport::fail(
                'appointment_actions_list_not_ready',
                'La lista de acciones de citas no está disponible.'
            );
        }

        $list_id = (int) ($list['id'] ?? 0);

        if ($list_id < 1) {
            return TaskUseCaseSupport::fail(
                'appointment_actions_list_not_ready',
                'La lista de acciones de citas no está disponible.'
            );
        }

        $display = AA_Appointment_Reservation_Display_Formatter::format($reservation);
        $source_category = AA_Appointment_Actions_Catalog::SOURCE_CATEGORY;
        $origin_key = AA_Appointment_Confirmation_Task_Projector::task_origin_key($reservation_id);
        $existing_task = SeededTaskRepository::find_task_by_origin($source_category, $origin_key);
        $task_payload = $this->build_task_payload($list_id, $display, $source_category, $origin_key, $reservation);
        $task = $this->upsert_task($task_payload);

        if ($task === null) {
            $task = SeededTaskRepository::find_task_by_origin($source_category, $origin_key);
        }

        if ($task === null) {
            return TaskUseCaseSupport::fail('task_persistence_failed', 'No se pudo crear o actualizar la tarea de confirmación.');
        }

        $task_id = (int) ($task['id'] ?? 0);

        if ($task_id < 1) {
            return TaskUseCaseSupport::fail('task_persistence_failed', 'No se pudo crear o actualizar la tarea de confirmación.');
        }

        $existing_action = TaskActionRepository::find_by_task_and_key(
            $task_id,
            AA_Appointment_Actions_Catalog::TASK_ACTION_KEY
        );
        $action_payload = $this->build_action_payload($reservation_id);
        $action = $this->upsert_action($task_id, $action_payload);

        if ($action === null) {
            return TaskUseCaseSupport::fail(
                'action_persistence_failed',
                'No se pudo persistir la acción de confirmación.'
            );
        }

        $data = [
            'task' => $task,
            'action' => $action,
            'reservation_id' => $reservation_id,
        ];

        if ($existing_task === null) {
            $data['task_created'] = true;
        } elseif ($this->task_visible_fields_changed($existing_task, $task)) {
            $data['task_updated'] = true;
        }

        if ($existing_action === null) {
            $data['action_created'] = true;
        } elseif ($this->action_fields_changed($existing_action, $action)) {
            $data['action_updated'] = true;
        }

        return TaskUseCaseSupport::ok($data);
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
    private function is_pending_reservation(array $reservation): bool {
        return strtolower(trim((string) ($reservation['estado'] ?? ''))) === 'pending';
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolve_active_appointment_actions_list(): ?array {
        $list = $this->find_active_appointment_actions_list();

        if ($list !== null) {
            return $list;
        }

        $this->sync_appointment_actions_list();

        return $this->find_active_appointment_actions_list();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find_active_appointment_actions_list(): ?array {
        if ($this->list_resolver !== null) {
            $list = call_user_func($this->list_resolver);

            if (!is_array($list)) {
                return null;
            }

            if (strtolower(trim((string) ($list['status'] ?? ''))) !== 'active') {
                return null;
            }

            return $list;
        }

        $list = SeededTaskRepository::find_list_by_origin(
            AA_Appointment_Actions_Catalog::SOURCE_CATEGORY,
            AA_Appointment_Actions_Catalog::LIST_ORIGIN_KEY
        );

        if ($list === null) {
            return null;
        }

        if (strtolower(trim((string) ($list['status'] ?? ''))) !== 'active') {
            return null;
        }

        return $list;
    }

    private function sync_appointment_actions_list(): void {
        if ($this->list_sync !== null) {
            call_user_func($this->list_sync);

            return;
        }

        (new SyncAppointmentActionsListUseCase())->execute();
    }

    /**
     * @param array{
     *     client_name:string,
     *     phone:string,
     *     date_label:string,
     *     time_label:string,
     *     service:string
     * } $display
     * @return array<string,mixed>
     */
    private function build_task_payload(
        int $list_id,
        array $display,
        string $source_category,
        string $origin_key,
        array $reservation
    ): array {
        $title = AA_Appointment_Confirmation_Task_Projector::truncate_text(
            AA_Appointment_Confirmation_Task_Projector::build_title($display['client_name'] ?? ''),
            self::TITLE_MAX_LENGTH
        );
        $notes = AA_Appointment_Confirmation_Task_Projector::truncate_text(
            AA_Appointment_Confirmation_Task_Projector::build_notes($display),
            TaskUseCaseSupport::TASK_NOTES_MAX_LENGTH
        );

        return [
            'list_id' => $list_id,
            'title' => $title,
            'notes' => $notes,
            'status' => 'pending',
            'source' => 'system',
            'source_category' => $source_category,
            'origin_key' => $origin_key,
            'managed_by' => 'developer',
            'importance' => 0,
            'position' => 0,
            'default_bucket' => 'primary',
            'completion_type' => 'system',
            'completion_fact_key' => null,
            'due_at' => AA_Appointment_Confirmation_Task_Projector::resolve_due_at($reservation['fecha'] ?? null),
            'completed_at' => null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function build_action_payload(int $reservation_id): array {
        $payload = AA_Appointment_Confirmation_Task_Projector::action_definition();
        $payload['payload_json'] = wp_json_encode(['reservation_id' => $reservation_id]);

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function upsert_task(array $payload): ?array {
        if ($this->task_upsertor !== null) {
            $task = call_user_func($this->task_upsertor, $payload);

            return is_array($task) ? $task : null;
        }

        return SeededTaskRepository::upsert_seeded_task($payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function upsert_action(int $task_id, array $payload): ?array {
        if ($this->action_upsertor !== null) {
            $action = call_user_func($this->action_upsertor, $task_id, $payload);

            return is_array($action) ? $action : null;
        }

        return TaskActionRepository::upsert($task_id, $payload);
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    private function task_visible_fields_changed(array $before, array $after): bool {
        return ($before['title'] ?? '') !== ($after['title'] ?? '')
            || ($before['notes'] ?? '') !== ($after['notes'] ?? '')
            || (int) ($before['list_id'] ?? 0) !== (int) ($after['list_id'] ?? 0);
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    private function action_fields_changed(array $before, array $after): bool {
        return ($before['label'] ?? '') !== ($after['label'] ?? '')
            || ($before['handler'] ?? '') !== ($after['handler'] ?? '')
            || ($before['payload_json'] ?? '') !== ($after['payload_json'] ?? '');
    }
}
