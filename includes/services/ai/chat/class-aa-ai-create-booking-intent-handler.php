<?php
/**
 * Create Booking Intent Handler
 *
 * Caso de uso real del bounded context AI para create_booking.
 * Recibe un parsed normalizado y devuelve una respuesta de negocio
 * con resolución temporal heurística y resolución de entidades contra BD.
 *
 * Frontera actual:
 * - Resuelve fecha/hora natural a datetime local del negocio.
 * - Resuelve client_name contra clientes reales (aa_search_clientes).
 * - Resuelve service_name contra servicios reales (AssignmentsModel).
 * - Resuelve staff_name contra personal real (AssignmentsModel).
 * - Resuelve zone_name contra zonas de atención reales (AssignmentsModel).
 * - Evalúa factibilidad temprana del servicio (catálogo + capacidad staff).
 * - No consulta disponibilidad real, assignments ni ocupación.
 * - No crea reservas, clientes, servicios, staff ni zonas.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-ai-datetime-resolver.php';
require_once __DIR__ . '/class-aa-ai-client-resolver.php';
require_once __DIR__ . '/class-aa-ai-service-resolver.php';
require_once __DIR__ . '/class-aa-ai-staff-resolver.php';
require_once __DIR__ . '/class-aa-ai-zone-resolver.php';
require_once __DIR__ . '/class-aa-ai-service-feasibility-evaluator.php';
require_once __DIR__ . '/class-aa-ai-staff-feasibility-evaluator.php';
require_once __DIR__ . '/class-aa-ai-staff-time-feasibility-evaluator.php';
require_once __DIR__ . '/class-aa-ai-zone-feasibility-evaluator.php';
require_once __DIR__ . '/../../availability/class-aa-area-availability-service.php';

final class AA_AI_Create_Booking_Intent_Handler {

    /**
     * Procesa un parsed normalizado de create_booking.
     *
     * @param array $parsed El array normalizado con las 8 claves del parser.
     * @return array {
     *     @type string $intent     Siempre 'create_booking'.
     *     @type string $status     Estado del flujo de negocio.
     *     @type string $reply      Texto legible para el admin.
     *     @type array  $resolution {
     *         @type array  $parsed_input            Datos crudos del parser.
     *         @type array  $missing_fields          Campos requeridos ausentes.
     *         @type array  $ambiguous_fields        Campos con más de un candidato.
     *         @type array  $resolved                Entidades resueltas contra BD.
     *         @type array  $proposed                Valores propuestos por heurística (futuro).
     *         @type bool   $ready_for_confirmation  true cuando el draft esté listo para confirmar.
     *         @type array  $datetime_resolution     Resolución temporal heurística.
     *         @type array  $lookup                  Resultados de búsqueda sin resolución exitosa.
     *     }
     * }
     */
    public function handle(array $parsed) {
        $missing = $this->detect_missing_fields($parsed);

        $dt_resolver = new AA_AI_Datetime_Resolver();
        $datetime_resolution = $dt_resolver->resolve(
            $parsed['date_text'] ?? null,
            $parsed['time_text'] ?? null
        );

        $client_resolver  = new AA_AI_Client_Resolver();
        $client_result    = $client_resolver->resolve($parsed['client_name'] ?? null);

        $service_resolver = new AA_AI_Service_Resolver();
        $service_result   = $service_resolver->resolve($parsed['service_name'] ?? null);

        $staff_resolver   = new AA_AI_Staff_Resolver();
        $staff_result     = $staff_resolver->resolve($parsed['staff_name'] ?? null);

        $zone_resolver    = new AA_AI_Zone_Resolver();
        $zone_result      = $zone_resolver->resolve($parsed['zone_name'] ?? null);

        $resolved         = [];
        $ambiguous_fields = [];
        $lookup           = [];

        $this->place_entity_result('client', $client_result, $resolved, $ambiguous_fields, $lookup);
        $this->place_entity_result('service', $service_result, $resolved, $ambiguous_fields, $lookup);
        $this->place_entity_result('staff', $staff_result, $resolved, $ambiguous_fields, $lookup);
        $this->place_entity_result('zone', $zone_result, $resolved, $ambiguous_fields, $lookup);

        $service_feasibility_evaluator = new AA_AI_Service_Feasibility_Evaluator();
        $service_feasibility = $service_feasibility_evaluator->evaluate($resolved, $ambiguous_fields, $lookup);

        $staff_feasibility_evaluator = new AA_AI_Staff_Feasibility_Evaluator();
        $staff_feasibility = $staff_feasibility_evaluator->evaluate(
            $resolved,
            $ambiguous_fields,
            $lookup,
            $service_feasibility
        );

        $staff_time_feasibility_evaluator = new AA_AI_Staff_Time_Feasibility_Evaluator();
        $staff_time_feasibility = $staff_time_feasibility_evaluator->evaluate(
            $resolved,
            $ambiguous_fields,
            $lookup,
            $datetime_resolution,
            $service_feasibility
        );

        $area_availability_service = new AA_Area_Availability_Service();
        $zone_feasibility_evaluator = new AA_AI_Zone_Feasibility_Evaluator($area_availability_service);
        $zone_feasibility = $zone_feasibility_evaluator->evaluate(
            $resolved,
            $ambiguous_fields,
            $lookup,
            $datetime_resolution,
            $service_feasibility,
            $staff_feasibility
        );

        $feasibility = array_merge(
            $service_feasibility,
            $staff_feasibility,
            $staff_time_feasibility,
            $zone_feasibility
        );

        $reply = $this->build_reply($parsed, $missing);

        return [
            'intent'     => 'create_booking',
            'status'     => 'needs_resolution',
            'reply'      => $reply,
            'resolution' => [
                'parsed_input'           => $parsed,
                'missing_fields'         => $missing,
                'ambiguous_fields'       => !empty($ambiguous_fields) ? $ambiguous_fields : new \stdClass(),
                'resolved'               => !empty($resolved) ? $resolved : new \stdClass(),
                'proposed'               => new \stdClass(),
                'ready_for_confirmation' => false,
                'datetime_resolution'    => $datetime_resolution,
                'lookup'                 => !empty($lookup) ? $lookup : new \stdClass(),
                'feasibility'            => $feasibility,
            ],
        ];
    }

    /**
     * Coloca el resultado de un resolver de entidad en el slot correcto del contrato.
     *
     * @param string $entity_key Clave de la entidad (e.g. 'client', 'service').
     */
    private function place_entity_result(string $entity_key, array $result, array &$resolved, array &$ambiguous, array &$lookup): void {
        switch ($result['status']) {
            case 'resolved':
                $resolved[$entity_key] = $result;
                break;

            case 'ambiguous':
                $ambiguous[$entity_key] = $result;
                break;

            case 'missing':
            case 'no_match':
            default:
                $lookup[$entity_key] = $result;
                break;
        }
    }

    /**
     * Detecta qué campos clave faltan para una reserva.
     *
     * @param array $parsed
     * @return string[]
     */
    private function detect_missing_fields(array $parsed) {
        $required = ['client_name', 'service_name', 'date_text', 'time_text'];
        $missing  = [];

        foreach ($required as $field) {
            if (empty($parsed[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Genera un texto de respuesta indicando el estado de la solicitud.
     *
     * @param array    $parsed
     * @param string[] $missing
     * @return string
     */
    private function build_reply(array $parsed, array $missing) {
        if (empty($missing)) {
            return 'Solicitud de reserva recibida. Se iniciará la resolución de datos.';
        }

        $labels = [
            'client_name'  => 'cliente',
            'service_name' => 'servicio',
            'date_text'    => 'fecha',
            'time_text'    => 'hora',
        ];

        $readable = array_map(
            function ($field) use ($labels) {
                return $labels[$field] ?? $field;
            },
            $missing
        );

        return 'Solicitud de reserva recibida. Faltan datos: ' . implode(', ', $readable) . '.';
    }
}
