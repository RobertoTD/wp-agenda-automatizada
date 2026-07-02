<?php
/**
 * Seed Initial Setup Use Case — seed completo v2 para agendas nuevas elegibles.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/setup/class-aa-initial-setup-seed-definition.php';
require_once dirname(__DIR__, 2) . '/domain/setup/class-aa-initial-setup-seed-owner-email-resolver.php';
require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';
require_once dirname(__DIR__, 2) . '/models/AssignmentsModel.php';
require_once dirname(__DIR__, 2) . '/application/assignments/AutoAssignStaffServicesUseCase.php';

final class SeedInitialSetupUseCase {

    /**
     * @return array{
     *     status: 'completed'|'error',
     *     steps?: array<string,array<string,mixed>>,
     *     message?: string
     * }
     */
    public function execute(): array {
        $steps = [];

        $client_step = $this->resolve_client_step();
        $steps['client'] = $client_step;
        if (($client_step['status'] ?? '') === 'error') {
            return [
                'status' => 'error',
                'message' => (string) ($client_step['message'] ?? 'Client seed failed.'),
                'steps' => $steps,
            ];
        }

        $service_step = $this->resolve_service_step();
        $steps['service'] = $service_step;
        if (($service_step['status'] ?? '') === 'error') {
            return [
                'status' => 'error',
                'message' => (string) ($service_step['message'] ?? 'Service seed failed.'),
                'steps' => $steps,
            ];
        }

        $staff_step = $this->resolve_staff_step();
        $steps['staff'] = $staff_step;
        if (($staff_step['status'] ?? '') === 'error') {
            return [
                'status' => 'error',
                'message' => (string) ($staff_step['message'] ?? 'Staff seed failed.'),
                'steps' => $steps,
            ];
        }

        $area_step = $this->resolve_area_step();
        $steps['area'] = $area_step;
        if (($area_step['status'] ?? '') === 'error') {
            return [
                'status' => 'error',
                'message' => (string) ($area_step['message'] ?? 'Area seed failed.'),
                'steps' => $steps,
            ];
        }

        $service_id = (int) ($service_step['id'] ?? 0);
        $staff_id = (int) ($staff_step['id'] ?? 0);
        $link_step = $this->ensure_staff_service_link_step($service_id, $staff_id);
        $steps['staff_service_link'] = $link_step;

        if (($link_step['status'] ?? '') === 'error') {
            return [
                'status' => 'error',
                'message' => (string) ($link_step['message'] ?? 'Staff-service link failed.'),
                'steps' => $steps,
            ];
        }

        return [
            'status' => 'completed',
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolve_client_step(): array {
        $canonical_phone = AA_Initial_Setup_Seed_Definition::CLIENT_PHONE_CANONICAL;
        $existing = ClientsRepository::find_by_telefono($canonical_phone);

        if ($existing !== null) {
            return [
                'status' => 'already_exists',
                'id' => (int) ($existing['id'] ?? 0),
            ];
        }

        $client_id = ClientsRepository::insert_registered_client([
            'nombre' => AA_Initial_Setup_Seed_Definition::CLIENT_NAME,
            'telefono' => $canonical_phone,
            'correo' => AA_Initial_Setup_Seed_Owner_Email_Resolver::resolve(),
        ]);

        if (is_wp_error($client_id)) {
            return [
                'status' => 'error',
                'message' => $client_id->get_error_message(),
            ];
        }

        return [
            'status' => 'created',
            'id' => (int) $client_id,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolve_service_step(): array {
        $seed_name = AA_Initial_Setup_Seed_Definition::SERVICE_NAME;
        $existing = AssignmentsRepository::find_active_service_by_name($seed_name);

        if ($existing !== null) {
            return [
                'status' => 'already_exists',
                'id' => (int) ($existing['id'] ?? 0),
            ];
        }

        if (AssignmentsRepository::count_active_services() > 0) {
            return [
                'status' => 'skipped_existing_entities',
                'id' => AssignmentsRepository::find_first_active_service_id(),
            ];
        }

        $result = AssignmentsModel::create_service($seed_name);

        if ($result === false || empty($result['id'])) {
            return [
                'status' => 'error',
                'message' => 'No se pudo crear el servicio de prueba.',
            ];
        }

        $service_id = (int) $result['id'];

        (new AutoAssignStaffServicesUseCase())->execute([
            'trigger' => 'service_created',
            'service_id' => $service_id,
        ]);

        return [
            'status' => 'created',
            'id' => $service_id,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolve_staff_step(): array {
        $seed_name = AA_Initial_Setup_Seed_Definition::STAFF_NAME;
        $existing = AssignmentsRepository::find_active_staff_by_name($seed_name);

        if ($existing !== null) {
            return [
                'status' => 'already_exists',
                'id' => (int) ($existing['id'] ?? 0),
            ];
        }

        if (AssignmentsRepository::count_active_staff() > 0) {
            return [
                'status' => 'skipped_existing_entities',
                'id' => AssignmentsRepository::find_first_active_staff_id(),
            ];
        }

        $result = AssignmentsModel::create_staff($seed_name);

        if ($result === false || empty($result['id'])) {
            return [
                'status' => 'error',
                'message' => 'No se pudo crear el personal de prueba.',
            ];
        }

        $staff_id = (int) $result['id'];

        (new AutoAssignStaffServicesUseCase())->execute([
            'trigger' => 'staff_created',
            'staff_id' => $staff_id,
        ]);

        return [
            'status' => 'created',
            'id' => $staff_id,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolve_area_step(): array {
        $seed_name = AA_Initial_Setup_Seed_Definition::AREA_NAME;
        $existing = AssignmentsRepository::find_active_service_area_by_name($seed_name);

        if ($existing !== null) {
            return [
                'status' => 'already_exists',
                'id' => (int) ($existing['id'] ?? 0),
            ];
        }

        if (AssignmentsRepository::count_active_service_areas() > 0) {
            return [
                'status' => 'skipped_existing_entities',
                'id' => AssignmentsRepository::find_first_active_service_area_id(),
            ];
        }

        $result = AssignmentsModel::create_service_area($seed_name);

        if ($result === false || empty($result['id'])) {
            return [
                'status' => 'error',
                'message' => 'No se pudo crear la zona de atención de prueba.',
            ];
        }

        return [
            'status' => 'created',
            'id' => (int) $result['id'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function ensure_staff_service_link_step(int $service_id, int $staff_id): array {
        if ($service_id < 1 || $staff_id < 1) {
            return [
                'status' => 'skipped',
                'reason' => 'missing_ids',
            ];
        }

        (new AutoAssignStaffServicesUseCase())->execute([
            'trigger' => 'staff_created',
            'staff_id' => $staff_id,
        ]);

        $outcome = AssignmentsRepository::ensure_staff_service_link($staff_id, $service_id);

        if ($outcome === 'failed') {
            return [
                'status' => 'error',
                'message' => 'No se pudo vincular personal y servicio de prueba.',
            ];
        }

        return [
            'status' => $outcome === 'created' ? 'linked' : 'already_linked',
            'staff_id' => $staff_id,
            'service_id' => $service_id,
        ];
    }
}
