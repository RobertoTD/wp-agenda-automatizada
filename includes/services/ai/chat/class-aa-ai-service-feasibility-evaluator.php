<?php
/**
 * AI Service Feasibility Evaluator
 *
 * Evaluación de factibilidad temprana del servicio resuelto.
 * Determina si, desde el punto de vista del catálogo y la capacidad
 * de staff, vale la pena seguir avanzando con la solicitud de cita.
 *
 * No consulta disponibilidad real, assignments, ocupación ni zona.
 * Solo responde:
 *   1) ¿El servicio quedó identificado? (service_basic)
 *   2) ¿Algún staff activo lo tiene asignado? (service_staff_capacity)
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Service_Feasibility_Evaluator {

    /**
     * @param array $resolved        Bloque resolved del contrato (puede contener 'service').
     * @param array $ambiguous_fields Bloque ambiguous_fields del contrato.
     * @param array $lookup          Bloque lookup del contrato.
     * @return array{service_basic: array, service_staff_capacity: array}
     */
    public function evaluate(array $resolved, array $ambiguous_fields, array $lookup): array {
        $basic = $this->check_service_basic($resolved, $ambiguous_fields, $lookup);

        $staff_capacity = $basic['status'] === 'compatible'
            ? $this->check_staff_capacity((int) $resolved['service']['id'])
            : ['status' => 'insufficient_data', 'reason' => 'service_not_ready'];

        return [
            'service_basic'          => $basic,
            'service_staff_capacity' => $staff_capacity,
        ];
    }

    private function check_service_basic(array $resolved, array $ambiguous, array $lookup): array {
        if (isset($resolved['service'])) {
            return ['status' => 'compatible'];
        }

        if (isset($ambiguous['service'])) {
            return ['status' => 'insufficient_data', 'reason' => 'service_ambiguous'];
        }

        $service_lookup_status = $lookup['service']['status'] ?? null;

        if ($service_lookup_status === 'missing') {
            return ['status' => 'insufficient_data', 'reason' => 'service_missing'];
        }

        if ($service_lookup_status === 'no_match') {
            return ['status' => 'incompatible', 'reason' => 'service_not_found'];
        }

        return ['status' => 'insufficient_data', 'reason' => 'service_missing'];
    }

    private function check_staff_capacity(int $service_id): array {
        $active_staff = $this->get_active_staff();

        if (empty($active_staff)) {
            return [
                'status'     => 'incompatible',
                'reason'     => 'no_active_staff_for_service',
                'service_id' => $service_id,
                'staff_count' => 0,
            ];
        }

        $count = 0;

        foreach ($active_staff as $member) {
            $service_ids = \AssignmentsModel::get_staff_service_ids((int) $member['id']);
            if (in_array($service_id, $service_ids, true)) {
                $count++;
            }
        }

        if ($count === 0) {
            return [
                'status'      => 'incompatible',
                'reason'      => 'no_active_staff_for_service',
                'service_id'  => $service_id,
                'staff_count' => 0,
            ];
        }

        return [
            'status'      => 'compatible',
            'service_id'  => $service_id,
            'staff_count' => $count,
        ];
    }

    /**
     * @return array[] Staff activo.
     */
    private function get_active_staff(): array {
        if (!class_exists('AssignmentsModel')) {
            return [];
        }

        return \AssignmentsModel::get_staff(true);
    }
}
