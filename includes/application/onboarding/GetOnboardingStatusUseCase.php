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
require_once dirname(__DIR__, 2) . '/services/SyncService.php';

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

        $status = (new AA_Onboarding_Activation_Policy())->evaluate($facts);

        $status['recommendations'] = [
            'google_calendar' => self::buildGoogleCalendarRecommendation(),
        ];

        return $status;
    }

    /**
     * Recommended Google Calendar state (non-blocking; does not affect activation flags).
     *
     * @return array{
     *     status: 'not_connected'|'connected'|'needs_reconnect',
     *     connected: bool,
     *     needs_reconnect: bool,
     *     email: string|null
     * }
     */
    private static function buildGoogleCalendarRecommendation(): array {
        $has_connection = SyncService::has_google_connection();
        $needs_reconnect = SyncService::needs_reconnect();
        $email = self::resolveGoogleCalendarEmail();

        if (!$has_connection) {
            return [
                'status' => 'not_connected',
                'connected' => false,
                'needs_reconnect' => false,
                'email' => null,
            ];
        }

        if ($needs_reconnect) {
            return [
                'status' => 'needs_reconnect',
                'connected' => true,
                'needs_reconnect' => true,
                'email' => $email,
            ];
        }

        return [
            'status' => 'connected',
            'connected' => true,
            'needs_reconnect' => false,
            'email' => $email,
        ];
    }

    /**
     * @return string|null
     */
    private static function resolveGoogleCalendarEmail(): ?string {
        $raw = get_option('aa_google_email', '');

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $email = sanitize_email($raw);

        return $email !== '' ? $email : null;
    }
}
