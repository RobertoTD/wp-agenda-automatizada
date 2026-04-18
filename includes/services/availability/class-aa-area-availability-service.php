<?php
/**
 * Area Availability Service
 *
 * Fuente de verdad para la disponibilidad de una zona de atención
 * frente a una propuesta de cita (zona, staff, datetime, duración).
 *
 * Responsabilidad:
 *   - Aplicar reglas de negocio sobre los datos crudos que devuelven
 *     AssignmentsModel y ReservationsModel.
 *   - NO hace SQL directo: las queries viven en los models.
 *   - NO traduce a un formato AI: eso vive en AA_AI_Zone_Feasibility_Evaluator.
 *
 * Distinción semántica (ver docs/fast-appointment-vs-assignment-availability.md):
 *   - assignment_guard: ¿hay assignment activa que reserve operativamente
 *     la zona para otro staff en ese horario?
 *   - occupancy: ¿hay reservas confirmadas que ocupen físicamente la zona
 *     en ese horario?
 * Son dos fenómenos distintos y se reportan por separado.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Area_Availability_Service {

    /**
     * Evalúa la disponibilidad de una zona en un instante.
     *
     * @param int    $zone_id          ID de la zona (service_area_id).
     * @param int    $staff_id         ID del staff propuesto para la cita.
     * @param string $start_datetime   Inicio de la cita propuesta (Y-m-d H:i:s).
     * @param int    $duration_minutes Duración de la cita (minutos, > 0).
     * @return array{assignment_guard: array, occupancy: array}
     */
    public function evaluate_zone(
        int $zone_id,
        int $staff_id,
        string $start_datetime,
        int $duration_minutes
    ): array {
        $window = $this->build_window($start_datetime, $duration_minutes);

        return [
            'assignment_guard' => $this->evaluate_assignment_guard($zone_id, $staff_id, $window),
            'occupancy'        => $this->evaluate_occupancy($zone_id, $window),
        ];
    }

    /**
     * Restricción operativa por assignment activa de la zona.
     *
     * - sin assignment activa traslapada -> compatible
     * - assignment con mismo staff       -> compatible
     * - assignment con otro staff        -> incompatible / zone_reserved_for_other_staff
     *
     * @param int   $zone_id
     * @param int   $staff_id
     * @param array $window {date, start_time, end_time, start_datetime, end_datetime}
     * @return array
     */
    private function evaluate_assignment_guard(int $zone_id, int $staff_id, array $window): array {
        if (!class_exists('AssignmentsModel')) {
            return ['status' => 'compatible'];
        }

        $assignments = \AssignmentsModel::get_active_assignments_overlapping_in_area(
            $window['date'],
            $window['start_time'],
            $window['end_time'],
            $zone_id
        );

        if (empty($assignments)) {
            return ['status' => 'compatible'];
        }

        foreach ($assignments as $assignment) {
            $assignment_staff_id = isset($assignment['staff_id']) ? (int) $assignment['staff_id'] : 0;

            if ($assignment_staff_id !== $staff_id) {
                return [
                    'status'        => 'incompatible',
                    'reason'        => 'zone_reserved_for_other_staff',
                    'assignment_id' => isset($assignment['id']) ? (int) $assignment['id'] : 0,
                    'staff_id'      => $assignment_staff_id,
                    'start_time'    => (string) ($assignment['start_time'] ?? ''),
                    'end_time'      => (string) ($assignment['end_time'] ?? ''),
                ];
            }
        }

        return ['status' => 'compatible'];
    }

    /**
     * Ocupación real por reservas confirmadas en la zona.
     *
     * - hay reserva traslapada -> incompatible / zone_busy + busy_range
     * - no hay                 -> compatible
     *
     * @param int   $zone_id
     * @param array $window
     * @return array
     */
    private function evaluate_occupancy(int $zone_id, array $window): array {
        if (!class_exists('ReservationsModel')) {
            return ['status' => 'compatible'];
        }

        $rows = \ReservationsModel::get_confirmed_overlap_in_area(
            $window['start_datetime'],
            $window['end_datetime'],
            $zone_id
        );

        if (empty($rows)) {
            return ['status' => 'compatible'];
        }

        $first = $rows[0];
        $start = (string) ($first['fecha'] ?? '');
        $end   = $this->add_minutes_to_datetime($start, isset($first['duracion']) ? (int) $first['duracion'] : 0);

        return [
            'status'     => 'incompatible',
            'reason'     => 'zone_busy',
            'busy_range' => [
                'start' => $start,
                'end'   => $end,
            ],
        ];
    }

    /**
     * @return array{date: string, start_time: string, end_time: string, start_datetime: string, end_datetime: string}
     */
    private function build_window(string $start_datetime, int $duration_minutes): array {
        $duration_minutes = $duration_minutes > 0 ? $duration_minutes : 0;

        try {
            $start = new \DateTimeImmutable($start_datetime);
        } catch (\Exception $e) {
            return [
                'date'           => '',
                'start_time'     => '',
                'end_time'       => '',
                'start_datetime' => '',
                'end_datetime'   => '',
            ];
        }

        $end = $start->modify('+' . $duration_minutes . ' minutes');

        return [
            'date'           => $start->format('Y-m-d'),
            'start_time'     => $start->format('H:i:s'),
            'end_time'       => $end->format('H:i:s'),
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime'   => $end->format('Y-m-d H:i:s'),
        ];
    }

    private function add_minutes_to_datetime(string $datetime, int $minutes): string {
        if ($datetime === '') {
            return '';
        }

        try {
            $dt = new \DateTimeImmutable($datetime);
            return $dt->modify('+' . max(0, $minutes) . ' minutes')->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $datetime;
        }
    }
}
