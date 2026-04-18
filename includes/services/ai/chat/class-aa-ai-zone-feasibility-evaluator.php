<?php
/**
 * AI Zone Feasibility Evaluator
 *
 * Traduce a formato AI (status / reason) la disponibilidad de una zona
 * de atencion frente a una propuesta de cita.
 *
 * Esta clase NO contiene reglas de negocio: delega en
 * AA_Area_Availability_Service para todo lo operativo.
 *
 * Distincion semantica respetada:
 *   - zone_basic              : resolucion estructural de la zona.
 *   - zone_assignment_guard   : restriccion operativa por assignment activa
 *                               (otra staff reservo la zona en ese horario).
 *   - zone_time_occupancy     : ocupacion fisica por reservas confirmadas.
 *
 * No mezcla los dos ultimos conceptos: cada uno tiene su propio status/reason.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Zone_Feasibility_Evaluator {

    private const FALLBACK_DURATION_MINUTES = 30;

    /** @var AA_Area_Availability_Service */
    private $area_availability_service;

    public function __construct(AA_Area_Availability_Service $area_availability_service) {
        $this->area_availability_service = $area_availability_service;
    }

    /**
     * @param array $resolved
     * @param array $ambiguous_fields
     * @param array $lookup
     * @param array $datetime_resolution
     * @param array $service_feasibility
     * @param array $staff_feasibility
     * @return array{
     *     zone_basic: array,
     *     zone_assignment_guard: array,
     *     zone_time_occupancy: array
     * }
     */
    public function evaluate(
        array $resolved,
        array $ambiguous_fields,
        array $lookup,
        array $datetime_resolution,
        array $service_feasibility,
        array $staff_feasibility
    ): array {
        $zone_basic = $this->check_zone_basic($resolved, $ambiguous_fields, $lookup);

        $precondition = $this->check_preconditions($zone_basic, $staff_feasibility, $datetime_resolution);

        if ($precondition !== null) {
            return [
                'zone_basic'            => $zone_basic,
                'zone_assignment_guard' => $precondition,
                'zone_time_occupancy'   => $precondition,
            ];
        }

        $zone_id        = (int) $resolved['zone']['id'];
        $staff_id       = (int) $resolved['staff']['id'];
        $start_datetime = (string) $datetime_resolution['normalized']['local_datetime'];
        $duration       = $this->resolve_duration_minutes($resolved, $service_feasibility);

        $service_result = $this->area_availability_service->evaluate_zone(
            $zone_id,
            $staff_id,
            $start_datetime,
            $duration['minutes']
        );

        $assignment_guard = $this->map_assignment_guard(
            $service_result['assignment_guard'] ?? [],
            $zone_id,
            $staff_id
        );

        $time_occupancy = $this->map_time_occupancy(
            $service_result['occupancy'] ?? [],
            $zone_id,
            $duration
        );

        return [
            'zone_basic'            => $zone_basic,
            'zone_assignment_guard' => $assignment_guard,
            'zone_time_occupancy'   => $time_occupancy,
        ];
    }

    /**
     * Resolucion estructural de la zona (sin tocar disponibilidad).
     */
    private function check_zone_basic(array $resolved, array $ambiguous, array $lookup): array {
        if (isset($resolved['zone'])) {
            return [
                'status'  => 'compatible',
                'zone_id' => (int) $resolved['zone']['id'],
            ];
        }

        if (isset($ambiguous['zone'])) {
            return ['status' => 'insufficient_data', 'reason' => 'zone_ambiguous'];
        }

        $zone_lookup_status = $lookup['zone']['status'] ?? null;

        if ($zone_lookup_status === 'missing') {
            return ['status' => 'insufficient_data', 'reason' => 'zone_missing'];
        }

        if ($zone_lookup_status === 'no_match') {
            return ['status' => 'incompatible', 'reason' => 'zone_not_found'];
        }

        return ['status' => 'insufficient_data', 'reason' => 'zone_missing'];
    }

    /**
     * Devuelve un array uniforme cuando alguna precondicion no se cumple,
     * para que zone_assignment_guard y zone_time_occupancy hereden la causa.
     *
     * Retorna null si todas las precondiciones estan listas.
     */
    private function check_preconditions(array $zone_basic, array $staff_feasibility, array $datetime_resolution): ?array {
        if (($zone_basic['status'] ?? null) !== 'compatible') {
            return [
                'status' => 'insufficient_data',
                'reason' => 'zone_not_ready',
            ];
        }

        $staff_basic_status = $staff_feasibility['staff_basic']['status'] ?? null;

        if ($staff_basic_status !== 'compatible') {
            return [
                'status' => 'insufficient_data',
                'reason' => 'staff_not_ready',
            ];
        }

        if (!$this->is_datetime_ready($datetime_resolution)) {
            return [
                'status' => 'insufficient_data',
                'reason' => 'datetime_not_ready',
            ];
        }

        return null;
    }

    private function is_datetime_ready(array $datetime_resolution): bool {
        return ($datetime_resolution['status'] ?? null) === 'resolved'
            && !empty($datetime_resolution['normalized']['local_datetime']);
    }

    /**
     * Misma politica de duracion que staff_time_availability:
     * usa duracion del servicio si esta resuelto, si no fallback 30 min.
     *
     * @return array{minutes: int, source: string}
     */
    private function resolve_duration_minutes(array $resolved, array $service_feasibility): array {
        $service_basic_status = $service_feasibility['service_basic']['status'] ?? null;

        if ($service_basic_status !== 'compatible') {
            return [
                'minutes' => self::FALLBACK_DURATION_MINUTES,
                'source'  => 'fallback',
            ];
        }

        $duration = isset($resolved['service']['duration_minutes'])
            ? (int) $resolved['service']['duration_minutes']
            : 0;

        if ($duration <= 0 && isset($resolved['service']['id']) && class_exists('AssignmentsModel')) {
            $service = \AssignmentsModel::get_service_by_id((int) $resolved['service']['id']);
            $duration = isset($service['duration_minutes']) ? (int) $service['duration_minutes'] : 0;
        }

        if ($duration <= 0) {
            return [
                'minutes' => self::FALLBACK_DURATION_MINUTES,
                'source'  => 'fallback',
            ];
        }

        return [
            'minutes' => $duration,
            'source'  => 'service',
        ];
    }

    /**
     * Traduce el resultado del service de assignment_guard al contrato AI.
     */
    private function map_assignment_guard(array $service_assignment_guard, int $zone_id, int $staff_id): array {
        $status = $service_assignment_guard['status'] ?? 'compatible';

        if ($status === 'compatible') {
            return [
                'status'   => 'compatible',
                'zone_id'  => $zone_id,
                'staff_id' => $staff_id,
            ];
        }

        return [
            'status'        => 'incompatible',
            'reason'        => (string) ($service_assignment_guard['reason'] ?? 'zone_reserved_for_other_staff'),
            'zone_id'       => $zone_id,
            'staff_id'      => $staff_id,
            'assignment_id' => isset($service_assignment_guard['assignment_id'])
                ? (int) $service_assignment_guard['assignment_id']
                : 0,
            'blocked_by_staff_id' => isset($service_assignment_guard['staff_id'])
                ? (int) $service_assignment_guard['staff_id']
                : 0,
            'start_time'    => (string) ($service_assignment_guard['start_time'] ?? ''),
            'end_time'      => (string) ($service_assignment_guard['end_time'] ?? ''),
        ];
    }

    /**
     * Traduce el resultado del service de occupancy al contrato AI.
     *
     * @param array{minutes: int, source: string} $duration
     */
    private function map_time_occupancy(array $service_occupancy, int $zone_id, array $duration): array {
        $status = $service_occupancy['status'] ?? 'compatible';

        if ($status === 'compatible') {
            return [
                'status'           => 'compatible',
                'zone_id'          => $zone_id,
                'duration_minutes' => $duration['minutes'],
                'duration_source'  => $duration['source'],
            ];
        }

        return [
            'status'           => 'incompatible',
            'reason'           => (string) ($service_occupancy['reason'] ?? 'zone_busy'),
            'zone_id'          => $zone_id,
            'duration_minutes' => $duration['minutes'],
            'duration_source'  => $duration['source'],
            'busy_range'       => isset($service_occupancy['busy_range']) && is_array($service_occupancy['busy_range'])
                ? [
                    'start' => (string) ($service_occupancy['busy_range']['start'] ?? ''),
                    'end'   => (string) ($service_occupancy['busy_range']['end'] ?? ''),
                ]
                : ['start' => '', 'end' => ''],
        ];
    }
}
