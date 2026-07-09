<?php
/**
 * Validate Upcoming Confirmed Push Appointments Use Case.
 *
 * Devuelve el estado actual de citas para que el backend decida si una Push
 * programada sigue siendo válida. No exige email ni reutiliza reminders-bulk.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/ReservationsRepository.php';
require_once dirname(__DIR__, 2) . '/models/AssignmentsModel.php';

final class ValidateUpcomingConfirmedPushAppointmentsUseCase {

    /** @var callable|null */
    private $reservation_reader;

    /** @var callable|null */
    private $service_resolver;

    /**
     * @param callable|null $reservation_reader (int $reservation_id): ?array
     * @param callable|null $service_resolver (string $service_raw): string
     */
    public function __construct(
        ?callable $reservation_reader = null,
        ?callable $service_resolver = null
    ) {
        $this->reservation_reader = $reservation_reader;
        $this->service_resolver = $service_resolver;
    }

    /**
     * @param array<int,array<string,mixed>> $appointments
     * @return array{success:bool,valid:array<string,array<string,mixed>>,skipped:array<string,string>}
     */
    public function execute(array $appointments): array {
        $valid = [];
        $skipped = [];

        foreach ($this->normalize_appointments($appointments) as $appointment) {
            $id = (int) $appointment['appointment_id'];
            $key = (string) $id;
            $reservation = $this->read_reservation($id);

            if ($reservation === null) {
                $skipped[$key] = 'not_found';
                continue;
            }

            $appointment_start = $this->format_appointment_start($reservation);

            if ($appointment_start === null) {
                $skipped[$key] = 'invalid_appointment_start';
                continue;
            }

            $valid[$key] = [
                'appointment_id'    => (int) $reservation['id'],
                'estado'            => (string) $reservation['estado'],
                'appointment_start' => $appointment_start,
                'customer_name'     => sanitize_text_field((string) ($reservation['nombre'] ?? '')),
                'service'           => sanitize_text_field($this->resolve_service((string) ($reservation['servicio'] ?? ''))),
            ];
        }

        return [
            'success' => true,
            'valid'   => $valid,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $appointments
     * @return array<int,array{appointment_id:int,expected_start:string}>
     */
    private function normalize_appointments(array $appointments): array {
        $normalized = [];
        $seen = [];

        foreach ($appointments as $appointment) {
            if (!is_array($appointment)) {
                continue;
            }

            $id = absint($appointment['appointment_id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $normalized[] = [
                'appointment_id' => $id,
                'expected_start' => sanitize_text_field((string) ($appointment['expected_start'] ?? '')),
            ];
        }

        return array_slice($normalized, 0, 50);
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

    private function resolve_service(string $service_raw): string {
        $service_raw = trim($service_raw);

        if ($this->service_resolver !== null) {
            return (string) call_user_func($this->service_resolver, $service_raw);
        }

        if (strpos($service_raw, 'fixed::') === 0) {
            return substr($service_raw, 7);
        }

        if ($service_raw !== '' && ctype_digit($service_raw) && (int) $service_raw > 0) {
            $service = AssignmentsModel::get_service_by_id((int) $service_raw);
            if (is_array($service) && !empty($service['name'])) {
                return (string) $service['name'];
            }
        }

        return $service_raw;
    }
}
