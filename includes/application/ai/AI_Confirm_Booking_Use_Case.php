<?php
/**
 * AI Confirm Booking Use Case
 *
 * Capa: `includes/application/ai/`.
 *
 * Caso de uso del bounded context AI que orquesta la confirmación de
 * un draft del chat: opcionalmente crea la asignación, crea la reserva
 * vía `CreateReservationUseCase` y dispara la confirmación automática
 * vía `confirm_backend_service_confirmar`. NO recrea ninguna regla del
 * `CreateReservationUseCase` (cliente, snapshot de precio, join_token,
 * notificación) ni reproduce reglas de assignment guard (las garantiza
 * `AssignmentsModel::create_assignment` vía dominio).
 *
 * ─── Diseño deliberado ──────────────────────────────────────────────
 *
 *   - Confía en los IDs que recibe (ya pasaron por draft_state +
 *     assignment_resolver). No se re-resuelve nada.
 *   - Sin rollback complejo: si la asignación se creó pero la reserva
 *     falla, la asignación queda y el admin puede limpiarla. Ese ruido
 *     revela exactamente dónde falló.
 *   - Confirmación automática NO es fatal: si el backend de confirm
 *     falla, la reserva queda en `pending` igual que cita rápida.
 *
 * ─── Contrato ───────────────────────────────────────────────────────
 *
 * Input:
 *   [
 *     'client_id'        => int (>0),
 *     'service_id'       => int (>0),       // catálogo numérico
 *     'staff_id'         => int (>0),
 *     'zone_id'          => int (>0),       // == service_area_id
 *     'start_datetime'   => 'Y-m-d H:i:s',  // hora local del negocio
 *     'duration_minutes' => int (>0),       // 30|60|90 (CreateReservationUseCase los normaliza)
 *     'assignment_mode'  => 'reuse'|'create_new',
 *     'assignment_id'    => int|null,       // requerido si mode==='reuse'
 *   ]
 *
 * Output éxito:
 *   [
 *     'status'             => 'ok',
 *     'reservation_id'     => int,
 *     'assignment_id'      => int,
 *     'created_assignment' => bool,
 *     'confirmed'          => bool,
 *     'confirm_result'     => array|null Retorno crudo de confirm_backend_service_confirmar().
 *   ]
 *
 * Output error:
 *   [
 *     'status'  => 'error',
 *     'stage'   => 'input'|'assignment'|'reservation'|'confirm',
 *     'message' => string,
 *     'detail'  => mixed|null,
 *   ]
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\AI
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Confirm_Booking_Use_Case {

    public function execute(array $input): array {
        $v = $this->validate_input($input);
        if (!$v['ok']) {
            return $this->err('input', $v['message']);
        }

        $client_id        = $v['client_id'];
        $service_id       = $v['service_id'];
        $staff_id         = $v['staff_id'];
        $zone_id          = $v['zone_id'];
        $start_datetime   = $v['start_datetime'];
        $duration_minutes = $v['duration_minutes'];
        $mode             = $v['assignment_mode'];
        $reuse_id         = $v['assignment_id'];

        $window = $this->derive_window($start_datetime, $duration_minutes);

        $effective_assignment_id = 0;
        $created_assignment      = false;

        if ($mode === 'create_new') {
            $created = $this->create_assignment_with_service(
                $staff_id,
                $zone_id,
                $service_id,
                $window['date'],
                $window['start_time'],
                $window['end_time']
            );

            if ($created['status'] !== 'ok') {
                return $this->err(
                    'assignment',
                    $created['message'],
                    $created['detail']
                );
            }

            $effective_assignment_id = $created['assignment_id'];
            $created_assignment      = true;
        } else {
            $effective_assignment_id = $reuse_id;
        }

        $cliente = $this->load_cliente($client_id);
        if ($cliente === null) {
            return $this->err('reservation', 'Cliente no encontrado', ['client_id' => $client_id]);
        }

        $reservation_input = $this->build_reservation_input(
            $service_id,
            $start_datetime,
            $cliente,
            $duration_minutes,
            $effective_assignment_id
        );

        if ($reservation_input === null) {
            return $this->err('reservation', 'Formato de fecha inválido para reserva', null);
        }

        if (!class_exists('CreateReservationUseCase')) {
            return $this->err('reservation', 'CreateReservationUseCase no disponible', null);
        }

        $res = (new CreateReservationUseCase())->execute($reservation_input);

        if (empty($res['success'])) {
            $msg    = $res['error']['message'] ?? 'Error al crear la reserva';
            $detail = $res['error']['detail'] ?? null;
            return $this->err('reservation', $msg, $detail);
        }

        $reservation_id = (int) ($res['data']['id'] ?? 0);
        if ($reservation_id <= 0) {
            return $this->err('reservation', 'Reserva creada sin id válido', $res['data'] ?? null);
        }

        $confirm_result = $this->try_auto_confirm($reservation_id);
        $confirmed      = is_array($confirm_result) && !empty($confirm_result['success']);

        return [
            'status'             => 'ok',
            'reservation_id'     => $reservation_id,
            'assignment_id'      => $effective_assignment_id,
            'created_assignment' => $created_assignment,
            'confirmed'          => $confirmed,
            'confirm_result'     => $confirm_result,
        ];
    }

    // ─── Input validation ────────────────────────────────────────────

    private function validate_input(array $i): array {
        $client_id        = isset($i['client_id']) ? (int) $i['client_id'] : 0;
        $service_id       = isset($i['service_id']) ? (int) $i['service_id'] : 0;
        $staff_id         = isset($i['staff_id']) ? (int) $i['staff_id'] : 0;
        $zone_id          = isset($i['zone_id']) ? (int) $i['zone_id'] : 0;
        $start_datetime   = isset($i['start_datetime']) ? (string) $i['start_datetime'] : '';
        $duration_minutes = isset($i['duration_minutes']) ? (int) $i['duration_minutes'] : 0;
        $mode             = isset($i['assignment_mode']) ? (string) $i['assignment_mode'] : '';
        $assignment_id    = isset($i['assignment_id']) ? (int) $i['assignment_id'] : 0;

        if ($client_id <= 0 || $service_id <= 0 || $staff_id <= 0 || $zone_id <= 0 || $duration_minutes <= 0) {
            return ['ok' => false, 'message' => 'Faltan IDs requeridos o duración inválida'];
        }

        if ($mode !== 'reuse' && $mode !== 'create_new') {
            return ['ok' => false, 'message' => 'assignment_mode inválido'];
        }

        if ($mode === 'reuse' && $assignment_id <= 0) {
            return ['ok' => false, 'message' => 'assignment_id requerido para reuse'];
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start_datetime);
        if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d H:i:s') !== $start_datetime) {
            return ['ok' => false, 'message' => 'start_datetime inválido (esperado Y-m-d H:i:s)'];
        }

        return [
            'ok'               => true,
            'client_id'        => $client_id,
            'service_id'       => $service_id,
            'staff_id'         => $staff_id,
            'zone_id'          => $zone_id,
            'start_datetime'   => $start_datetime,
            'duration_minutes' => $duration_minutes,
            'assignment_mode'  => $mode,
            'assignment_id'    => $assignment_id,
        ];
    }

    // ─── Window derivation ───────────────────────────────────────────

    /**
     * @return array{date:string,start_time:string,end_time:string}
     */
    private function derive_window(string $start_datetime, int $duration_minutes): array {
        $start = new \DateTimeImmutable($start_datetime);
        $end   = $start->modify('+' . $duration_minutes . ' minutes');
        return [
            'date'       => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i:s'),
            'end_time'   => $end->format('H:i:s'),
        ];
    }

    // ─── Assignment creation (with pivot) ────────────────────────────

    /**
     * Crea la asignación vía `AssignmentsModel::create_assignment` y
     * vincula el servicio en la pivote `aa_assignment_services`.
     *
     * @return array{status:string,assignment_id?:int,message?:string,detail?:mixed}
     */
    private function create_assignment_with_service(
        int $staff_id,
        int $zone_id,
        int $service_id,
        string $date,
        string $start_time,
        string $end_time
    ): array {
        if (!class_exists('AssignmentsModel')) {
            return ['status' => 'fail', 'message' => 'AssignmentsModel no disponible', 'detail' => null];
        }

        $created = \AssignmentsModel::create_assignment([
            'assignment_date' => $date,
            'start_time'      => $start_time,
            'end_time'        => $end_time,
            'staff_id'        => $staff_id,
            'service_area_id' => $zone_id,
            'capacity'        => 1,
        ]);

        if ($created === false) {
            return ['status' => 'fail', 'message' => 'Error al crear asignación', 'detail' => null];
        }

        if (is_array($created) && isset($created['error'])) {
            return [
                'status'  => 'fail',
                'message' => (string) $created['error'],
                'detail'  => $created['reason'] ?? null,
            ];
        }

        $assignment_id = isset($created['id']) ? (int) $created['id'] : 0;
        if ($assignment_id <= 0) {
            return ['status' => 'fail', 'message' => 'Asignación creada sin id', 'detail' => null];
        }

        $pivot_ok = \AssignmentsModel::add_assignment_service($assignment_id, $service_id);
        if (!$pivot_ok) {
            return [
                'status'  => 'fail',
                'message' => 'Asignación creada pero no se pudo vincular el servicio',
                'detail'  => ['assignment_id' => $assignment_id, 'service_id' => $service_id],
            ];
        }

        return ['status' => 'ok', 'assignment_id' => $assignment_id];
    }

    // ─── Cliente lookup ──────────────────────────────────────────────

    /**
     * Devuelve {nombre, telefono, correo} o `null` si no existe.
     *
     * @return array{nombre:string,telefono:string,correo:string}|null
     */
    private function load_cliente(int $client_id): ?array {
        if (!function_exists('aa_get_cliente_by_id')) {
            return null;
        }

        $row = \aa_get_cliente_by_id($client_id);
        if (!$row) {
            return null;
        }

        $row_arr = is_object($row) ? get_object_vars($row) : (array) $row;
        $nombre  = isset($row_arr['nombre']) ? (string) $row_arr['nombre'] : '';
        $tel     = isset($row_arr['telefono']) ? (string) $row_arr['telefono'] : '';
        $email   = isset($row_arr['correo']) ? (string) $row_arr['correo'] : '';

        if ($nombre === '' || $tel === '') {
            return null;
        }

        return ['nombre' => $nombre, 'telefono' => $tel, 'correo' => $email];
    }

    // ─── Reservation input builder ───────────────────────────────────

    /**
     * Construye el input para `CreateReservationUseCase` respetando su
     * contrato actual: `fecha` en ISO-8601 UTC (`Y-m-d\TH:i:s\Z`), tal
     * como cita rápida la envía. El use case existente la convierte de
     * regreso a local con `aa_timezone`.
     *
     * @param array{nombre:string,telefono:string,correo:string} $cliente
     * @return array<string,mixed>|null `null` si la conversión a UTC falla.
     */
    private function build_reservation_input(
        int $service_id,
        string $start_datetime_local,
        array $cliente,
        int $duration_minutes,
        int $assignment_id
    ): ?array {
        $tz_name = function_exists('get_option')
            ? (string) \get_option('aa_timezone', 'America/Mexico_City')
            : 'America/Mexico_City';

        try {
            $local = new \DateTimeImmutable($start_datetime_local, new \DateTimeZone($tz_name));
            $utc   = $local->setTimezone(new \DateTimeZone('UTC'));
            $iso   = $utc->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            return null;
        }

        return [
            'servicio'      => (string) $service_id,
            'fecha'         => $iso,
            'nombre'        => $cliente['nombre'],
            'telefono'      => $cliente['telefono'],
            'correo'        => $cliente['correo'],
            'duracion'      => $duration_minutes,
            'assignment_id' => $assignment_id,
        ];
    }

    // ─── Auto-confirm ────────────────────────────────────────────────

    /**
     * No es fatal: el caller deriva `confirmed` de `success`. El reportería
     * queda en `error_log` del propio `confirm_backend_service_confirmar`.
     *
     * @return array<string,mixed>|null Retorno crudo del servicio de confirmación.
     */
    private function try_auto_confirm(int $reservation_id): ?array {
        if (!function_exists('confirm_backend_service_confirmar')) {
            return null;
        }

        $result = \confirm_backend_service_confirmar($reservation_id);

        return is_array($result) ? $result : null;
    }

    // ─── Result helpers ──────────────────────────────────────────────

    /**
     * @param mixed $detail
     */
    private function err(string $stage, string $message, $detail = null): array {
        return [
            'status'  => 'error',
            'stage'   => $stage,
            'message' => $message,
            'detail'  => $detail,
        ];
    }
}
