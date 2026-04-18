<?php
/**
 * AI Staff Time Feasibility Evaluator
 *
 * Evalua disponibilidad temporal real del staff usando el criterio
 * operativo de Fast Appointment:
 *   - fuente de ocupacion: reservas confirmadas
 *   - regla de conflicto: overlap de rangos de tiempo
 *   - role de assignments: puente tecnico assignment_id -> staff_id
 *
 * Importante:
 *   - NO usa slots como restriccion de negocio en esta capa
 *   - NO usa assignments como fuente de disponibilidad
 *   - NO usa zona, capacidad ni ventanas de assignment como regla extra
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Staff_Time_Feasibility_Evaluator {

    /**
     * @param array $resolved
     * @param array $ambiguous_fields
     * @param array $lookup
     * @param array $datetime_resolution
     * @param array $service_feasibility
     * @return array{staff_time_availability: array}
     */
    public function evaluate(
        array $resolved,
        array $ambiguous_fields,
        array $lookup,
        array $datetime_resolution,
        array $service_feasibility
    ): array {
        $staff_basic = $this->check_staff_basic($resolved, $ambiguous_fields, $lookup);

        if ($staff_basic['status'] !== 'compatible') {
            return [
                'staff_time_availability' => [
                    'status' => 'insufficient_data',
                    'reason' => 'staff_not_ready',
                ],
            ];
        }

        if (!$this->is_datetime_ready($datetime_resolution)) {
            return [
                'staff_time_availability' => [
                    'status' => 'insufficient_data',
                    'reason' => 'datetime_not_ready',
                ],
            ];
        }

        $staff_id = (int) $resolved['staff']['id'];
        $date     = (string) $datetime_resolution['normalized']['local_date'];
        $time     = (string) $datetime_resolution['normalized']['local_time'];

        $duration = $this->resolve_duration_minutes($resolved, $service_feasibility);
        $busy_map = $this->build_staff_busy_map($date, [$staff_id]);
        $busy_hit = $this->find_busy_overlap($staff_id, $time, $duration['minutes'], $busy_map);

        if ($busy_hit !== null) {
            return [
                'staff_time_availability' => [
                    'status'           => 'incompatible',
                    'reason'           => 'staff_busy',
                    'staff_id'         => $staff_id,
                    'duration_minutes' => $duration['minutes'],
                    'duration_source'  => $duration['source'],
                    'busy_range'       => $busy_hit,
                ],
            ];
        }

        return [
            'staff_time_availability' => [
                'status'           => 'compatible',
                'staff_id'         => $staff_id,
                'duration_minutes' => $duration['minutes'],
                'duration_source'  => $duration['source'],
            ],
        ];
    }

    private function check_staff_basic(array $resolved, array $ambiguous, array $lookup): array {
        if (isset($resolved['staff'])) {
            return ['status' => 'compatible'];
        }

        if (isset($ambiguous['staff'])) {
            return ['status' => 'insufficient_data', 'reason' => 'staff_ambiguous'];
        }

        $staff_lookup_status = $lookup['staff']['status'] ?? null;

        if ($staff_lookup_status === 'missing') {
            return ['status' => 'insufficient_data', 'reason' => 'staff_missing'];
        }

        if ($staff_lookup_status === 'no_match') {
            return ['status' => 'incompatible', 'reason' => 'staff_not_found'];
        }

        return ['status' => 'insufficient_data', 'reason' => 'staff_missing'];
    }

    private function is_datetime_ready(array $datetime_resolution): bool {
        return ($datetime_resolution['status'] ?? null) === 'resolved'
            && !empty($datetime_resolution['normalized']['local_datetime'])
            && !empty($datetime_resolution['normalized']['local_date'])
            && !empty($datetime_resolution['normalized']['local_time']);
    }

    /**
     * @return array{minutes: int, source: string}
     */
    private function resolve_duration_minutes(array $resolved, array $service_feasibility): array {
        $service_basic_status = $service_feasibility['service_basic']['status'] ?? null;

        if ($service_basic_status !== 'compatible') {
            return [
                'minutes' => 30,
                'source'  => 'fallback',
            ];
        }

        $duration = isset($resolved['service']['duration_minutes'])
            ? (int) $resolved['service']['duration_minutes']
            : 0;

        if ($duration <= 0 && isset($resolved['service']['id'])) {
            $service = $this->get_service_by_id((int) $resolved['service']['id']);
            $duration = isset($service['duration_minutes']) ? (int) $service['duration_minutes'] : 0;
        }

        if ($duration <= 0) {
            return [
                'minutes' => 30,
                'source'  => 'fallback',
            ];
        }

        return [
            'minutes' => $duration,
            'source'  => 'service',
        ];
    }

    /**
     * Replica el patron de Fast Appointment:
     * 1) assignments del dia
     * 2) bridge assignment_id -> staff_id
     * 3) busy_ranges por assignment IDs
     * 4) mapa de ocupacion por staff
     *
     * @param string $date YYYY-MM-DD
     * @param int[]  $staff_ids
     * @return array<int, array<int, array{start: string, end: string}>>
     */
    private function build_staff_busy_map(string $date, array $staff_ids): array {
        $normalized_staff_ids = array_values(array_filter(array_map('intval', $staff_ids)));
        $staff_busy = [];

        foreach ($normalized_staff_ids as $staff_id) {
            $staff_busy[$staff_id] = [];
        }

        if (empty($normalized_staff_ids) || !class_exists('AssignmentsModel')) {
            return $staff_busy;
        }

        $assignments = $this->get_assignments_for_date_and_staff($date, $normalized_staff_ids);
        $assignment_to_staff = $this->build_assignment_staff_bridge($assignments);

        if (empty($assignment_to_staff)) {
            return $staff_busy;
        }

        $busy_ranges = \AssignmentsModel::get_busy_ranges_by_assignment_ids(array_keys($assignment_to_staff), $date);

        if (!is_array($busy_ranges) || empty($busy_ranges)) {
            return $staff_busy;
        }

        foreach ($busy_ranges as $range) {
            $assignment_id = isset($range['assignment_id']) ? (int) $range['assignment_id'] : 0;
            $mapped_staff_id = $assignment_to_staff[$assignment_id] ?? null;

            if (!$mapped_staff_id) {
                continue;
            }

            $staff_busy[$mapped_staff_id][] = [
                'start' => (string) ($range['start'] ?? ''),
                'end'   => (string) ($range['end'] ?? ''),
            ];
        }

        return $staff_busy;
    }

    /**
     * @param int   $staff_id
     * @param string $time HH:MM:SS
     * @param int   $duration_minutes
     * @param array $staff_busy_map
     * @return array{start: string, end: string}|null
     */
    private function find_busy_overlap(int $staff_id, string $time, int $duration_minutes, array $staff_busy_map): ?array {
        $ranges = $staff_busy_map[$staff_id] ?? [];

        if (empty($ranges)) {
            return null;
        }

        $slot_start = $this->time_to_minutes($time);
        $slot_end   = $slot_start + $duration_minutes;

        foreach ($ranges as $range) {
            $range_start = $this->time_to_minutes($this->extract_time_part((string) ($range['start'] ?? '')));
            $range_end   = $this->time_to_minutes($this->extract_time_part((string) ($range['end'] ?? '')));

            if ($this->ranges_overlap($slot_start, $slot_end, $range_start, $range_end)) {
                return [
                    'start' => (string) ($range['start'] ?? ''),
                    'end'   => (string) ($range['end'] ?? ''),
                ];
            }
        }

        return null;
    }

    /**
     * @param array[] $assignments
     * @return array<int, int>
     */
    private function build_assignment_staff_bridge(array $assignments): array {
        $bridge = [];

        foreach ($assignments as $assignment) {
            $assignment_id = isset($assignment['id']) ? (int) $assignment['id'] : 0;
            $staff_id      = isset($assignment['staff_id']) ? (int) $assignment['staff_id'] : 0;

            if ($assignment_id > 0 && $staff_id > 0) {
                $bridge[$assignment_id] = $staff_id;
            }
        }

        return $bridge;
    }

    /**
     * @param string $date YYYY-MM-DD
     * @param int[]  $staff_ids
     * @return array[]
     */
    private function get_assignments_for_date_and_staff(string $date, array $staff_ids): array {
        $assignments = \AssignmentsModel::get_assignments();

        if (!is_array($assignments) || empty($assignments)) {
            return [];
        }

        return array_values(array_filter($assignments, function (array $assignment) use ($date, $staff_ids) {
            $assignment_date = isset($assignment['assignment_date']) ? (string) $assignment['assignment_date'] : '';
            $staff_id = isset($assignment['staff_id']) ? (int) $assignment['staff_id'] : 0;

            return $assignment_date === $date && in_array($staff_id, $staff_ids, true);
        }));
    }

    /**
     * @return array|false
     */
    private function get_service_by_id(int $service_id) {
        if (!class_exists('AssignmentsModel')) {
            return false;
        }

        return \AssignmentsModel::get_service_by_id($service_id);
    }

    private function extract_time_part(string $date_time_string): string {
        if ($date_time_string === '') {
            return '00:00';
        }

        $raw = trim($date_time_string);

        if (strpos($raw, ' ') !== false) {
            $parts = explode(' ', $raw);
            $raw = $parts[1] ?? $raw;
        }

        return substr($raw, 0, 5);
    }

    private function time_to_minutes(string $time_string): int {
        $parts = explode(':', $time_string ?: '00:00');
        $hours = isset($parts[0]) ? (int) $parts[0] : 0;
        $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

        return ($hours * 60) + $minutes;
    }

    private function ranges_overlap(int $start_a, int $end_a, int $start_b, int $end_b): bool {
        return $start_a < $end_b && $end_a > $start_b;
    }
}
