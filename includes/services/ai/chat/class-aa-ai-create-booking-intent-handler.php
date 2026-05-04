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
require_once __DIR__ . '/../../../application/ai/AI_Setup_Action_Link_Builder.php';
require_once __DIR__ . '/../../../domain/booking/class-aa-booking-draft-aggregator.php';
require_once __DIR__ . '/../../../domain/booking/class-aa-booking-assignment-resolver.php';
require_once __DIR__ . '/../../../domain/booking/class-aa-booking-reply-builder.php';

final class AA_AI_Create_Booking_Intent_Handler {
    /** Copy provisional cuando se pregunta disponibilidad por chat. */
    private const ASK_AVAILABILITY_OVERLAY_TEXT = 'Aún no puedo consultar horarios libres desde el chat. Puedes revisar la disponibilidad manualmente en el timeline del calendario o proponer otra hora.';

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

        $aa_slot_duration_raw     = get_option('aa_slot_duration', false);
        $aa_slot_duration_minutes = (is_numeric($aa_slot_duration_raw) && (int) $aa_slot_duration_raw > 0)
            ? (int) $aa_slot_duration_raw
            : null;

        $assignment_resolution = $this->resolve_assignment_if_possible(
            $resolved,
            $datetime_resolution,
            $aa_slot_duration_minutes
        );

        $aggregator  = new AA_Booking_Draft_Aggregator();
        $draft_state = $aggregator->aggregate([
            'parsed_input'          => $parsed,
            'missing_fields'        => $missing,
            'ambiguous_fields'      => $ambiguous_fields,
            'resolved'              => $resolved,
            'lookup'                => $lookup,
            'datetime_resolution'   => $datetime_resolution,
            'feasibility'           => $feasibility,
            'duration_settings'     => ['default_minutes' => $aa_slot_duration_minutes],
            'assignment_resolution' => $assignment_resolution,
        ]);

        $reply_builder = new AA_Booking_Reply_Builder();
        $reply_ui      = $reply_builder->build($draft_state);
        $reply_ui      = $this->apply_ask_availability_overlay_to_reply_ui($reply_ui, $parsed);
        $reply_ui      = $this->attach_footer_actions_when_client_no_match($reply_ui, $draft_state);

        return [
            'intent'     => 'create_booking',
            'status'     => 'needs_resolution',
            'reply'      => $reply_ui['text'],
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
                'draft_state'            => $draft_state,
                'reply_ui'               => $reply_ui,
            ],
        ];
    }

    /**
     * Invoca el `AA_Booking_Assignment_Resolver` SOLO si tenemos todas
     * las entradas mínimas (staff, zona, servicio resueltos + datetime
     * válido + duración > 0). Si falta cualquier pieza, devuelve `null`
     * y el aggregator no proyecta `draft.assignment`.
     *
     * Esto implementa AC5 a nivel de handler: cuando hay blockers
     * upstream críticos (staff no resuelto, zona no resuelta, datetime
     * inválido, etc.), omitimos la consulta al resolver para evitar
     * blockers redundantes. La cadena de responsabilidad queda clara:
     *
     *   - entidad ausente/ambigua → lookup/ambiguous_fields + required_literal.
     *   - datetime inválido/past   → datetime_resolution + blocker `datetime_past`.
     *   - assignment incompatible  → este resolver + blocker `assignment_*`.
     *
     * @param array $resolved            Salida de `place_entity_result`.
     * @param array $datetime_resolution Salida del datetime resolver.
     * @param int|null $default_duration_minutes Cascada: setting WP si
     *                                           la hay, `null` → fallback 30.
     * @return array|null Shape del output del resolver, o `null` si no
     *                    se invocó.
     */
    private function resolve_assignment_if_possible(
        array $resolved,
        array $datetime_resolution,
        ?int $default_duration_minutes
    ): ?array {
        $staff_id   = isset($resolved['staff']['id']) ? (int) $resolved['staff']['id'] : 0;
        $zone_id    = isset($resolved['zone']['id']) ? (int) $resolved['zone']['id'] : 0;
        $service_id = isset($resolved['service']['id']) ? (int) $resolved['service']['id'] : 0;

        if ($staff_id <= 0 || $zone_id <= 0 || $service_id <= 0) {
            return null;
        }

        $dt_status = $datetime_resolution['status'] ?? null;
        if ($dt_status !== 'resolved') {
            return null;
        }

        $local_datetime = isset($datetime_resolution['normalized']['local_datetime'])
            ? (string) $datetime_resolution['normalized']['local_datetime']
            : '';

        if ($local_datetime === '') {
            return null;
        }

        $start_datetime = $this->to_mysql_datetime($local_datetime);
        if ($start_datetime === null) {
            return null;
        }

        $duration_minutes = $this->resolve_duration_for_resolver($resolved, $default_duration_minutes);

        if ($duration_minutes <= 0) {
            return null;
        }

        $resolver = new AA_Booking_Assignment_Resolver();
        return $resolver->resolve([
            'staff_id'         => $staff_id,
            'service_area_id'  => $zone_id,
            'service_id'       => $service_id,
            'start_datetime'   => $start_datetime,
            'duration_minutes' => $duration_minutes,
        ]);
    }

    /**
     * Normaliza `local_datetime` al shape `Y-m-d H:i:s` que el resolver
     * exige. El datetime resolver hoy emite `Y-m-d H:i:s` pero hacemos
     * defensa en profundidad aceptando ISO-8601 con 'T' por si el
     * contrato upstream cambia.
     */
    private function to_mysql_datetime(string $local_datetime): ?string {
        $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i'];

        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $local_datetime);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /**
     * Duplica la cascada de duración del aggregator para alimentar al
     * resolver SIN cargarlo con settings. Mantener las dos cascadas
     * sincronizadas es deuda conocida; una opción es exponer un helper
     * compartido en dominio (Paso futuro).
     */
    private function resolve_duration_for_resolver(array $resolved, ?int $default_duration_minutes): int {
        $service_duration = isset($resolved['service']['duration_minutes'])
            ? (int) $resolved['service']['duration_minutes']
            : 0;

        if ($service_duration > 0) {
            return $service_duration;
        }

        if (is_int($default_duration_minutes) && $default_duration_minutes > 0) {
            return $default_duration_minutes;
        }

        return 30;
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
     * Superpone una aclaración provisional cuando el turno es ask_availability,
     * conservando el resto del flujo de create_booking (cta, draft_echo, etc.).
     *
     * @param array<string,mixed> $reply_ui
     * @param array<string,mixed> $parsed
     * @return array<string,mixed>
     */
    private function apply_ask_availability_overlay_to_reply_ui(array $reply_ui, array $parsed): array {
        if (($parsed['sub_intent'] ?? '') !== 'ask_availability') {
            return $reply_ui;
        }

        $normal_text = '';
        if (isset($reply_ui['text']) && is_string($reply_ui['text'])) {
            $normal_text = trim($reply_ui['text']);
        }

        $reply_ui['text'] = self::ASK_AVAILABILITY_OVERLAY_TEXT;
        if ($normal_text !== '') {
            $reply_ui['text'] .= "\n\n" . $normal_text;
        }

        return $reply_ui;
    }

    /**
     * Enlace compacto a Clientes cuando el borrador indica cliente inexistente (no_match).
     * No altera texto, CTA ni draft; solo añade `reply_ui.footer_actions`.
     *
     * @param array<string,mixed> $reply_ui
     * @param array<string,mixed> $draft_state
     * @return array<string,mixed>
     */
    private function attach_footer_actions_when_client_no_match(array $reply_ui, array $draft_state): array {
        $required = isset($draft_state['required_literal']) && is_array($draft_state['required_literal'])
            ? $draft_state['required_literal']
            : [];

        $has_client_no_match = false;
        foreach ($required as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['field'] ?? '') === 'client' && ($row['reason'] ?? '') === 'no_match') {
                $has_client_no_match = true;
                break;
            }
        }

        if (!$has_client_no_match) {
            return $reply_ui;
        }

        $builder = new AA_AI_Setup_Action_Link_Builder();
        $action  = $builder->build_action_for_key('clients_create');
        if ($action === null) {
            return $reply_ui;
        }

        $reply_ui['footer_actions'] = [$action];

        return $reply_ui;
    }

}
