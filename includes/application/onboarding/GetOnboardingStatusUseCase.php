<?php
/**
 * Get Onboarding Status Use Case
 *
 * Orquesta la lectura del estado de activacion inicial hacia la primera
 * cita. Las queries viven en repositories y las reglas en domain.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ReservationsRepository.php';
require_once dirname(__DIR__, 2) . '/domain/onboarding/class-aa-onboarding-activation-policy.php';

final class GetOnboardingStatusUseCase {
    /**
     * @return array<string,mixed>
     */
    public function execute(): array {
        $facts = [
            'registered_client_count' => ClientsRepository::count_registered_clients(),
            'active_service_count' => AssignmentsRepository::count_active_services(),
            'active_staff_count' => AssignmentsRepository::count_active_staff(),
            'active_staff_with_active_service_count' => AssignmentsRepository::count_active_staff_with_active_services(),
            'active_area_count' => AssignmentsRepository::count_active_service_areas(),
            'created_reservation_count' => ReservationsRepository::count_created_reservations(),
        ];

        return (new AA_Onboarding_Activation_Policy())->evaluate($facts);
    }
}
