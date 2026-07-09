<?php
/**
 * Sync Upcoming Confirmed Push Job Use Case — best-effort sync tras confirmación local.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/ReservationsRepository.php';
require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-push-backend-client.php';
require_once dirname(__DIR__, 2) . '/application/tasks/TaskUseCaseSupport.php';

final class SyncUpcomingConfirmedPushJobUseCase {

    /** @var callable|null */
    private $push_client_sync;

    /** @var callable|null */
    private $reservation_reader;

    /** @var callable|null */
    private $enabled_reader;

    /** @var callable|null */
    private $minutes_reader;

    /**
     * @param callable|null $push_client_sync (array $payload): array
     * @param callable|null $reservation_reader (int $reservation_id): ?array
     * @param callable|null $enabled_reader (): bool
     * @param callable|null $minutes_reader (): int
     */
    public function __construct(
        ?callable $push_client_sync = null,
        ?callable $reservation_reader = null,
        ?callable $enabled_reader = null,
        ?callable $minutes_reader = null
    ) {
        $this->push_client_sync = $push_client_sync;
        $this->reservation_reader = $reservation_reader;
        $this->enabled_reader = $enabled_reader;
        $this->minutes_reader = $minutes_reader;
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

        error_log(
            '⚠️ [SyncUpcomingConfirmedPushJob] Sync no completado para reserva '
            . $reservation_id
            . ': '
            . $code
        );
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

        if (!$this->read_enabled()) {
            $sync_result = $this->sync_with_backend([
                'appointment_id' => $reservation_id,
                'enabled'        => false,
            ]);

            return $this->map_sync_result($sync_result);
        }

        $reservation = $this->read_reservation($reservation_id);

        if ($reservation === null) {
            return TaskUseCaseSupport::fail('reservation_not_found', 'No se encontró la cita.');
        }

        $appointment_start = $this->format_appointment_start($reservation);

        if ($appointment_start === null) {
            return TaskUseCaseSupport::fail('invalid_appointment_start', 'La fecha de la cita no es válida.');
        }

        $sync_result = $this->sync_with_backend([
            'appointment_id'    => $reservation_id,
            'enabled'           => true,
            'appointment_start' => $appointment_start,
            'minutes'           => $this->read_minutes(),
        ]);

        return $this->map_sync_result($sync_result);
    }

    /**
     * @param array<string,mixed> $sync_result
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    private function map_sync_result(array $sync_result): array {
        if (!empty($sync_result['ok'])) {
            return TaskUseCaseSupport::ok([
                'sync' => isset($sync_result['sync']) && is_string($sync_result['sync'])
                    ? $sync_result['sync']
                    : 'unknown',
            ]);
        }

        $code = isset($sync_result['code']) && is_string($sync_result['code'])
            ? trim($sync_result['code'])
            : 'push_sync_failed';

        return TaskUseCaseSupport::fail($code, 'No se pudo sincronizar el job Push.');
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function sync_with_backend(array $payload): array {
        if ($this->push_client_sync !== null) {
            return call_user_func($this->push_client_sync, $payload);
        }

        $client = new AA_Push_Backend_Client();

        return $client->syncUpcomingConfirmedJob($payload);
    }

    private function read_enabled(): bool {
        if ($this->enabled_reader !== null) {
            return (bool) call_user_func($this->enabled_reader);
        }

        return (int) get_option('aa_push_upcoming_confirmed_enabled', 1) === 1;
    }

    private function read_minutes(): int {
        if ($this->minutes_reader !== null) {
            return (int) call_user_func($this->minutes_reader);
        }

        $allowed = [
            0    => 0,
            '0'  => 0,
            5    => 5,
            '5'  => 5,
            15   => 15,
            '15' => 15,
            30   => 30,
            '30' => 30,
            60   => 60,
            '60' => 60,
        ];

        $raw = get_option('aa_push_upcoming_confirmed_minutes', 15);

        return $allowed[$raw] ?? 15;
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
    private function format_appointment_start(array $reservation): ?string {
        $fecha = isset($reservation['fecha']) ? trim((string) $reservation['fecha']) : '';

        if ($fecha === '') {
            return null;
        }

        $timezone = (string) get_option('aa_timezone', 'America/Mexico_City');

        try {
            $fecha_obj = new DateTime($fecha, new DateTimeZone($timezone));
        } catch (Exception $e) {
            return null;
        }

        return $fecha_obj->format('c');
    }
}
