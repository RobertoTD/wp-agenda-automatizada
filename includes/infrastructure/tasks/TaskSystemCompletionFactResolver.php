<?php
/**
 * Task System Completion Fact Resolver — resuelve facts booleanos para completion por sistema.
 *
 * Extrae la lógica de hechos usada por Learning sin depender del pipeline legacy.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TaskSystemCompletionFactResolver {

    /**
     * @return array<string,bool>
     */
    public static function resolve_all(): array {
        self::ensure_dependencies();

        $business_name = get_option('aa_business_name', '');
        $business_address = get_option('aa_business_address', '');

        return [
            'google_connected' => SyncService::has_google_connection(),
            'business_data_complete' => is_string($business_name) && trim($business_name) !== ''
                && is_string($business_address) && trim($business_address) !== '',
            'has_active_service' => AssignmentsRepository::count_active_services() > 0,
            'has_active_area' => AssignmentsRepository::count_active_service_areas() > 0,
            'has_staff_with_service' => AssignmentsRepository::count_active_staff_with_active_services() > 0,
            'has_registered_client' => ClientsRepository::count_registered_clients() > 0,
        ];
    }

    private static function ensure_dependencies(): void {
        if (!class_exists('AssignmentsRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';
        }

        if (!class_exists('ClientsRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
        }

        if (!class_exists('SyncService')) {
            require_once dirname(__DIR__, 2) . '/services/SyncService.php';
        }
    }
}
