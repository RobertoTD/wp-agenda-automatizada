<?php
/**
 * Booking Setup Policy
 *
 * Regla pura para validar si el negocio tiene la configuración mínima
 * necesaria antes de iniciar un flujo de creación de cita.
 *
 * No consulta BD, no conoce WordPress, no conoce AI ni UI.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Booking_Setup_Policy {

    /**
     * Evalúa prerequisitos mínimos para crear una cita.
     *
     * @param array<string,mixed> $facts Datos ya leídos por capas externas.
     * @return array<string,mixed>
     */
    public function evaluate_for_create_booking(array $facts): array {
        $active_staff_count = isset($facts['active_staff_count']) ? (int) $facts['active_staff_count'] : 0;
        $active_service_count = isset($facts['active_service_count']) ? (int) $facts['active_service_count'] : 0;
        $active_area_count = isset($facts['active_area_count']) ? (int) $facts['active_area_count'] : 0;
        $registered_client_count = isset($facts['registered_client_count']) ? (int) $facts['registered_client_count'] : 0;
        $active_staff_with_service_count = isset($facts['active_staff_with_service_count'])
            ? (int) $facts['active_staff_with_service_count']
            : 0;

        $missing = [];

        if ($active_staff_count <= 0) {
            $missing[] = [
                'code'       => 'no_active_staff',
                'label'      => 'No hay profesionales activos',
                'message'    => 'Crea o activa al menos un profesional antes de agendar citas.',
                'action_key' => 'assignments_staff',
            ];
        }

        if ($active_service_count <= 0) {
            $missing[] = [
                'code'       => 'no_active_service',
                'label'      => 'No hay servicios activos',
                'message'    => 'Para crear citas necesitas tener al menos un servicio activo.',
                'action_key' => 'assignments_services',
            ];
        }

        if ($active_area_count <= 0) {
            $missing[] = [
                'code'       => 'no_active_area',
                'label'      => 'No hay zonas de atención activas',
                'message'    => 'Para crear citas necesitas tener al menos una zona de atención activa.',
                'action_key' => 'assignments_areas',
            ];
        }

        if ($registered_client_count <= 0) {
            $missing[] = [
                'code'       => 'no_registered_client',
                'label'      => 'No hay clientes registrados',
                'message'    => 'Para crear citas necesitas tener al menos un cliente registrado.',
                'action_key' => 'clients_create',
            ];
        }

        if ($active_staff_count > 0 && $active_service_count > 0 && $active_staff_with_service_count <= 0) {
            $missing[] = [
                'code'       => 'no_staff_service_assignment',
                'label'      => 'No hay servicios asignados al personal',
                'message'    => 'Para crear citas necesitas asignar al menos un servicio a un profesional.',
                'action_key' => 'assignments_staff_services',
            ];
        }

        if (count($missing) > 0) {
            return [
                'status'        => 'setup_incomplete',
                'blocking'      => true,
                'missing_setup' => $missing,
                'message'       => $this->build_setup_incomplete_message($missing),
            ];
        }

        return [
            'status'        => 'setup_complete',
            'blocking'      => false,
            'missing_setup' => [],
            'message'       => '',
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $missing
     * @return string
     */
    private function build_setup_incomplete_message(array $missing): string {
        if (count($missing) === 1) {
            $code = isset($missing[0]['code']) ? (string) $missing[0]['code'] : '';

            if ($code === 'no_active_staff') {
                return 'Falta configuración inicial para crear citas. Primero crea o activa al menos un profesional.';
            }

            if ($code === 'no_active_service') {
                return 'Para crear citas necesitas tener al menos un servicio activo. Ve a Asignaciones → Servicios, crea o activa el servicio que ofrecerás, y después vuelve al chat para continuar.';
            }

            if ($code === 'no_active_area') {
                return 'Para crear citas necesitas tener al menos una zona de atención activa. Ve a Asignaciones → Zonas de atención, crea o activa la zona donde atenderás, y después vuelve al chat para continuar.';
            }

            if ($code === 'no_registered_client') {
                return 'Para crear citas necesitas tener al menos un cliente registrado. Ve a Clientes, crea el cliente, y después vuelve al chat para continuar.';
            }

            if ($code === 'no_staff_service_assignment') {
                return 'Para crear citas necesitas asignar al menos un servicio a un profesional. Ve a Asignaciones → Personal y asigna los servicios que ofrece cada profesional.';
            }
        }

        $items = [];
        foreach ($missing as $item) {
            $code = isset($item['code']) ? (string) $item['code'] : '';

            if ($code === 'no_active_staff') {
                $items[] = 'un profesional';
            } elseif ($code === 'no_active_service') {
                $items[] = 'un servicio';
            } elseif ($code === 'no_active_area') {
                $items[] = 'una zona de atención';
            } elseif ($code === 'no_registered_client') {
                $items[] = 'un cliente';
            } elseif ($code === 'no_staff_service_assignment') {
                $items[] = 'un servicio activo asignado a un profesional';
            }
        }

        return 'Falta configuración inicial para crear citas. Primero crea o activa al menos ' . $this->join_missing_items($items) . '.';
    }

    /**
     * @param array<int,string> $items
     * @return string
     */
    private function join_missing_items(array $items): string {
        if (empty($items)) {
            return 'la configuración mínima requerida';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);
        return implode(', ', $items) . ' y ' . $last;
    }
}
