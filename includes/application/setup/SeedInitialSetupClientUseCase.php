<?php
/**
 * Seed Initial Setup Client Use Case — crea Cliente de Prueba en agendas elegibles.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/setup/class-aa-initial-setup-seed-definition.php';
require_once dirname(__DIR__, 2) . '/domain/setup/class-aa-initial-setup-seed-owner-email-resolver.php';
require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';

final class SeedInitialSetupClientUseCase {

    /**
     * @return array{
     *     status: 'created'|'already_exists'|'error',
     *     client_id?: int,
     *     message?: string
     * }
     */
    public function execute(): array {
        $canonical_phone = AA_Initial_Setup_Seed_Definition::CLIENT_PHONE_CANONICAL;
        $existing = ClientsRepository::find_by_telefono($canonical_phone);

        if ($existing !== null) {
            return [
                'status' => 'already_exists',
                'client_id' => (int) ($existing['id'] ?? 0),
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
            'client_id' => (int) $client_id,
        ];
    }
}
