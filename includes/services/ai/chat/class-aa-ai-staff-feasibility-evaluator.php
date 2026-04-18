<?php
/**
 * AI Staff Feasibility Evaluator
 *
 * Evaluación estructural de factibilidad del staff resuelto.
 * Responde dos preguntas del contrato de feasibility:
 *   1) ¿El staff quedó identificado? (staff_basic)
 *   2) ¿Ese staff puede ofrecer el servicio solicitado? (staff_service_match)
 *
 * FUERA DE ALCANCE en esta fase (pertenece al motor de cita rápida,
 * no a la factibilidad estructural del bounded context AI):
 *   - No consulta assignments como fuente de disponibilidad.
 *   - No consulta slots ni ventanas horarias.
 *   - No consulta reservas confirmadas ni busy ranges.
 *   - No toca fechas ni duración.
 *
 * Ver docs/fast-appointment-vs-assignment-availability.md para la
 * distinción entre assignment-based availability (legacy / público)
 * y fast appointment (admin). Ninguno de esos motores se usa aquí.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Staff_Feasibility_Evaluator {

    /**
     * @param array $resolved            Bloque resolved del contrato (puede contener 'staff' y/o 'service').
     * @param array $ambiguous_fields    Bloque ambiguous_fields del contrato.
     * @param array $lookup              Bloque lookup del contrato.
     * @param array $service_feasibility Resultado previo de AA_AI_Service_Feasibility_Evaluator
     *                                   con al menos la clave 'service_basic'.
     * @return array{staff_basic: array, staff_service_match: array}
     */
    public function evaluate(
        array $resolved,
        array $ambiguous_fields,
        array $lookup,
        array $service_feasibility
    ): array {
        $basic = $this->check_staff_basic($resolved, $ambiguous_fields, $lookup);
        $match = $this->check_service_match($resolved, $basic, $service_feasibility);

        return [
            'staff_basic'         => $basic,
            'staff_service_match' => $match,
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

    /**
     * Verifica compatibilidad estructural staff ↔ servicio.
     *
     * Prioridad:
     *   1) Si staff no está listo → staff_not_ready (no se puede matchear sin staff).
     *   2) Si staff está listo pero service no → chequeo staff-only:
     *        - staff sin servicios asignados → incompatible / staff_has_no_services
     *        - staff con servicios           → insufficient_data / service_not_ready
     *          (se incluye available_services como pista)
     *   3) Si ambos están listos → intersección de IDs.
     */
    private function check_service_match(
        array $resolved,
        array $staff_basic,
        array $service_feasibility
    ): array {
        if ($staff_basic['status'] !== 'compatible') {
            return ['status' => 'insufficient_data', 'reason' => 'staff_not_ready'];
        }

        $staff_id          = (int) $resolved['staff']['id'];
        $staff_service_ids = $this->get_staff_service_ids($staff_id);

        $service_basic_status = $service_feasibility['service_basic']['status'] ?? null;

        if ($service_basic_status !== 'compatible') {
            if (empty($staff_service_ids)) {
                return [
                    'status'   => 'incompatible',
                    'reason'   => 'staff_has_no_services',
                    'staff_id' => $staff_id,
                ];
            }

            $propagated_reason = $service_feasibility['service_basic']['reason'] ?? null;

            return [
                'status'             => 'insufficient_data',
                'reason'             => $propagated_reason ?: 'service_not_ready',
                'staff_id'           => $staff_id,
                'available_services' => $this->get_staff_services($staff_id),
            ];
        }

        $service_id = (int) $resolved['service']['id'];

        if (in_array($service_id, $staff_service_ids, true)) {
            return [
                'status'     => 'compatible',
                'staff_id'   => $staff_id,
                'service_id' => $service_id,
            ];
        }

        return [
            'status'             => 'incompatible',
            'reason'             => 'staff_does_not_offer_service',
            'staff_id'           => $staff_id,
            'service_id'         => $service_id,
            'available_services' => $this->get_staff_services($staff_id),
        ];
    }

    /**
     * @return int[]
     */
    private function get_staff_service_ids(int $staff_id): array {
        if (!class_exists('AssignmentsModel')) {
            return [];
        }

        $ids = \AssignmentsModel::get_staff_service_ids($staff_id);

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /**
     * @return array[] Servicios completos {id, name} que el staff tiene asignados.
     */
    private function get_staff_services(int $staff_id): array {
        if (!class_exists('AssignmentsModel')) {
            return [];
        }

        $services = \AssignmentsModel::get_staff_services($staff_id);

        return is_array($services) ? $services : [];
    }
}
