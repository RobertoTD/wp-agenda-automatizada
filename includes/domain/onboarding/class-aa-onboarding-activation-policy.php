<?php
/**
 * Onboarding Activation Policy
 *
 * Regla pura para evaluar el estado de activacion inicial hacia la
 * primera cita. No consulta BD, no conoce WordPress, AJAX ni UI.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Onboarding_Activation_Policy {
    private const STEP_ORDER = [
        'client',
        'service',
        'staff',
        'area',
        'first_appointment',
    ];

    /**
     * Evalua el estado de onboarding a partir de facts ya leidos.
     *
     * @param array<string,mixed> $facts
     * @return array<string,mixed>
     */
    public function evaluate(array $facts): array {
        $registered_client_count = $this->int_fact($facts, 'registered_client_count');
        $active_service_count = $this->int_fact($facts, 'active_service_count');
        $active_staff_count = $this->int_fact($facts, 'active_staff_count');
        $active_staff_with_active_service_count = $this->int_fact($facts, 'active_staff_with_active_service_count');
        $active_area_count = $this->int_fact($facts, 'active_area_count');
        $created_reservation_count = $this->int_fact($facts, 'created_reservation_count');

        $steps = [
            'client' => $this->build_step(
                'Cliente',
                $registered_client_count,
                $registered_client_count > 0,
                'missing_registered_client'
            ),
            'service' => $this->build_step(
                'Servicio',
                $active_service_count,
                $active_service_count > 0,
                'missing_active_service'
            ),
            'staff' => $this->build_step(
                'Personal',
                $active_staff_with_active_service_count,
                $active_staff_with_active_service_count > 0,
                $active_staff_count <= 0 ? 'missing_active_staff' : 'missing_staff_service_assignment'
            ),
            'area' => $this->build_step(
                'Zona de atención',
                $active_area_count,
                $active_area_count > 0,
                'missing_active_area'
            ),
            'first_appointment' => $this->build_step(
                'Primera cita',
                $created_reservation_count,
                $created_reservation_count > 0,
                'missing_first_appointment'
            ),
        ];

        $setup_complete = $steps['client']['completed']
            && $steps['service']['completed']
            && $steps['staff']['completed']
            && $steps['area']['completed'];

        $activation_complete = $setup_complete && $steps['first_appointment']['completed'];

        return [
            'steps' => $steps,
            'setup_complete' => $setup_complete,
            'activation_complete' => $activation_complete,
            'show_activation_guide' => !$activation_complete,
            'next_step' => $this->resolve_next_step($steps),
        ];
    }

    /**
     * @param array<string,mixed> $facts
     */
    private function int_fact(array $facts, string $key): int {
        return isset($facts[$key]) ? max(0, (int) $facts[$key]) : 0;
    }

    /**
     * @return array{completed:bool,count:int,label:string,reason:string|null}
     */
    private function build_step(string $label, int $count, bool $completed, string $pending_reason): array {
        return [
            'completed' => $completed,
            'count' => $count,
            'label' => $label,
            'reason' => $completed ? null : $pending_reason,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $steps
     */
    private function resolve_next_step(array $steps): ?string {
        foreach (self::STEP_ORDER as $step_key) {
            if (empty($steps[$step_key]['completed'])) {
                return $step_key;
            }
        }

        return null;
    }
}
