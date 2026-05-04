<?php
/**
 * AI Booking Setup Check Use Case
 *
 * Orquesta la validación mínima previa al flujo create_booking del chat.
 * La regla vive en dominio; las lecturas viven en repositories.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
require_once dirname(__DIR__, 2) . '/domain/booking/class-aa-booking-setup-policy.php';
require_once __DIR__ . '/AI_Setup_Action_Link_Builder.php';

final class AA_AI_Booking_Setup_Check_Use_Case {

    /**
     * Ejecuta el check previo a create_booking.
     *
     * Si el setup está completo devuelve un resultado liviano con
     * `status: setup_complete`. Si bloquea, devuelve un intent_result
     * compatible con el contrato actual del chat.
     *
     * @param array<string,mixed> $parsed
     * @return array<string,mixed>
     */
    public function execute(array $parsed): array {
        $facts = [
            'active_staff_count'      => AssignmentsRepository::count_active_staff(),
            'active_service_count'    => AssignmentsRepository::count_active_services(),
            'active_area_count'       => AssignmentsRepository::count_active_service_areas(),
            'active_staff_with_service_count' => AssignmentsRepository::count_active_staff_with_active_services(),
            'registered_client_count' => ClientsRepository::count_registered_clients(),
        ];

        $setup_check = (new AA_Booking_Setup_Policy())->evaluate_for_create_booking($facts);

        if (empty($setup_check['blocking'])) {
            return [
                'status'      => 'setup_complete',
                'blocking'    => false,
                'setup_check' => $setup_check,
            ];
        }

        return $this->build_setup_incomplete_intent_result($parsed, $setup_check);
    }

    /**
     * @param array<string,mixed> $parsed
     * @param array<string,mixed> $setup_check
     * @return array<string,mixed>
     */
    private function build_setup_incomplete_intent_result(array $parsed, array $setup_check): array {
        $message = isset($setup_check['message']) && is_string($setup_check['message'])
            ? $setup_check['message']
            : 'Falta configuración inicial para crear citas.';

        $missing_setup = isset($setup_check['missing_setup']) && is_array($setup_check['missing_setup'])
            ? $setup_check['missing_setup']
            : [];

        $reply_ui = [
            'text'       => $message,
            'cta'        => 'fix_blocker',
            'highlights' => [],
            'choices'    => [],
            'draft_echo' => [
                'client'   => null,
                'service'  => null,
                'staff'    => null,
                'zone'     => null,
                'datetime' => null,
            ],
            'actions'    => $this->build_actions($missing_setup),
        ];

        return [
            'intent'     => 'create_booking',
            'status'     => 'setup_incomplete',
            'reply'      => $message,
            'resolution' => [
                'parsed_input'  => $parsed,
                'setup_check'   => $setup_check,
                'missing_setup' => $missing_setup,
                'blocking'      => true,
                'draft_state'   => null,
                'reply_ui'      => $reply_ui,
            ],
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $missing_setup
     * @return array<int, array<string,string>>
     */
    private function build_actions(array $missing_setup): array {
        return (new AA_AI_Setup_Action_Link_Builder())->build_actions($missing_setup);
    }
}
