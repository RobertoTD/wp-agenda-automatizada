<?php
/**
 * Booking Assignment Resolver
 *
 * Capa: `includes/domain/booking/` (regla pura de negocio sobre una query
 * pura de overlaps).
 *
 * Decide QUÉ assignment contendrá la reserva que el usuario quiere crear
 * en la ventana (staff, zona, start_datetime, duration). No ejecuta
 * nada (ni crea, ni actualiza, ni confirma): solo decide y reporta. El
 * Use Case de confirmación (Paso 3) consumirá esa decisión para crear la
 * reserva sin más consultas.
 *
 * ─── Invariantes de dominio asumidas ────────────────────────────────
 *
 *   I1. A lo más UNA assignment activa por tupla
 *       (staff, zona, overlap-temporal) — garantizada por
 *       `AA_Area_Availability_Service::is_zone_assignable_for_staff()`
 *       desde Paso 1.7.
 *   I2. La creación de una nueva assignment queda bloqueada si hay
 *       overlap en (zona, tiempo) con otra, MISMA o DISTINTA staff
 *       (Paso 1.6 + Paso 1.7). Este resolver no re-chequea ese caso;
 *       solo emite la DECISIÓN y la valida el `CreateAssignmentUseCase`
 *       que llegue en el Paso 3.
 *   I3. El `zone_feasibility` evaluator aplica la regla lenient (mismo
 *       staff convive) y detecta overlaps de OTRO staff como blocker
 *       upstream. Este resolver ignora explícitamente las assignments
 *       de otro staff: no son su responsabilidad.
 *
 * ─── Contrato ───────────────────────────────────────────────────────
 *
 * Input:
 *   [
 *     'staff_id'         => int (> 0),
 *     'service_area_id'  => int (> 0),
 *     'service_id'       => int (> 0),
 *     'start_datetime'   => 'Y-m-d H:i:s',
 *     'duration_minutes' => int (> 0),
 *   ]
 *
 * Output:
 *   [
 *     'mode'             => 'reuse' | 'create_new' | 'unresolved',
 *     'assignment_id'    => int|null,
 *     'rationale'        => 'service_match' | 'no_compatible_found'
 *                         | 'missing_inputs' | 'existing_overlap_incompatible',
 *     'pending_creation' => null | {
 *         staff_id, service_area_id, service_id, start_datetime, duration_minutes
 *     },
 *     'conflict'         => null | {
 *         code: 'service_not_offered' | 'out_of_turn',
 *         detail: {
 *             assignment_id, start_time, end_time,
 *             available_services?: [{id,name}, ...],  // solo en service_not_offered
 *         },
 *     },
 *   ]
 *
 * ─── Tabla de decisión ──────────────────────────────────────────────
 *
 *   Situación (tras filtrar same-staff)           | mode         | rationale
 *   ---------------------------------------------+--------------+------------------------------
 *   input inválido (≤0, vacío, mal formato)      | unresolved   | missing_inputs
 *   no hay overlap same-staff                    | create_new   | no_compatible_found
 *   existe `containing`, ofrece el servicio       | reuse        | service_match
 *   existe `containing`, NO ofrece el servicio    | unresolved   | existing_overlap_incompatible
 *                                                |              |   + conflict: service_not_offered
 *   no hay `containing`, sí `overlapping_only`    | unresolved   | existing_overlap_incompatible
 *                                                |              |   + conflict: out_of_turn
 *
 * ─── Invariantes de implementación ─────────────────────────────────
 *
 *   - Una sola entrada pública (`resolve`), métodos privados libres.
 *   - Sin `$wpdb` directo, sin `get_option`, sin `error_log`, sin
 *     globals ni `add_action`. La única dependencia externa son dos
 *     queries puras de `AssignmentsModel` (mismo precedente que
 *     `AA_Area_Availability_Service`).
 *   - Entradas inválidas cortan temprano: `missing_inputs` NO consulta
 *     nada (ver AC5).
 *   - El resolver NO re-valida invariantes del dominio de creación
 *     (I2); eso es responsabilidad del `CreateAssignmentUseCase`
 *     downstream. Aquí solo decidimos el `mode`.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Booking
 */

defined('ABSPATH') or die('No direct access');

final class AA_Booking_Assignment_Resolver {

    /**
     * @param array $input Shape del contrato de entrada.
     * @return array Shape del contrato de salida.
     */
    public function resolve(array $input): array {
        $validated = $this->validate_input($input);

        if (!$validated['ok']) {
            return $this->unresolved('missing_inputs', null);
        }

        $staff_id         = $validated['staff_id'];
        $service_area_id  = $validated['service_area_id'];
        $service_id       = $validated['service_id'];
        $start_datetime   = $validated['start_datetime'];
        $duration_minutes = $validated['duration_minutes'];

        [$date, $start_time, $end_time] = $this->derive_window($start_datetime, $duration_minutes);

        $overlaps = $this->fetch_overlaps($date, $start_time, $end_time, $service_area_id);
        $same_staff = $this->filter_same_staff($overlaps, $staff_id);

        if (empty($same_staff)) {
            return $this->create_new(
                $staff_id,
                $service_area_id,
                $service_id,
                $start_datetime,
                $duration_minutes
            );
        }

        $classification = $this->classify_same_staff($same_staff, $start_time, $end_time);

        if ($classification['containing'] !== null) {
            $containing = $classification['containing'];
            $assignment_id = (int) ($containing['id'] ?? 0);

            $services = $this->fetch_services_for_assignment($assignment_id);

            if ($this->services_include($services, $service_id)) {
                return [
                    'mode'             => 'reuse',
                    'assignment_id'    => $assignment_id,
                    'rationale'        => 'service_match',
                    'pending_creation' => null,
                    'conflict'         => null,
                ];
            }

            return $this->unresolved('existing_overlap_incompatible', [
                'code'   => 'service_not_offered',
                'detail' => [
                    'assignment_id'      => $assignment_id,
                    'start_time'         => (string) ($containing['start_time'] ?? ''),
                    'end_time'           => (string) ($containing['end_time'] ?? ''),
                    'available_services' => $this->normalize_services($services),
                ],
            ]);
        }

        if ($classification['overlapping'] !== null) {
            $overlap = $classification['overlapping'];

            return $this->unresolved('existing_overlap_incompatible', [
                'code'   => 'out_of_turn',
                'detail' => [
                    'assignment_id' => (int) ($overlap['id'] ?? 0),
                    'start_time'    => (string) ($overlap['start_time'] ?? ''),
                    'end_time'      => (string) ($overlap['end_time'] ?? ''),
                ],
            ]);
        }

        // Defensivo: same_staff no vacío pero ninguno clasificó. En la
        // práctica no ocurre (el filtro SQL garantiza overlap), pero
        // devolvemos `create_new` para no bloquear el flujo.
        return $this->create_new(
            $staff_id,
            $service_area_id,
            $service_id,
            $start_datetime,
            $duration_minutes
        );
    }

    // ─── Input validation ────────────────────────────────────────────

    /**
     * @return array{ok:bool, staff_id:int, service_area_id:int, service_id:int, start_datetime:string, duration_minutes:int}
     */
    private function validate_input(array $input): array {
        $staff_id         = isset($input['staff_id']) ? (int) $input['staff_id'] : 0;
        $service_area_id  = isset($input['service_area_id']) ? (int) $input['service_area_id'] : 0;
        $service_id       = isset($input['service_id']) ? (int) $input['service_id'] : 0;
        $start_datetime   = isset($input['start_datetime']) ? (string) $input['start_datetime'] : '';
        $duration_minutes = isset($input['duration_minutes']) ? (int) $input['duration_minutes'] : 0;

        $ok = $staff_id > 0
            && $service_area_id > 0
            && $service_id > 0
            && $duration_minutes > 0
            && $this->is_valid_datetime_string($start_datetime);

        return [
            'ok'               => $ok,
            'staff_id'         => $staff_id,
            'service_area_id'  => $service_area_id,
            'service_id'       => $service_id,
            'start_datetime'   => $start_datetime,
            'duration_minutes' => $duration_minutes,
        ];
    }

    private function is_valid_datetime_string(string $value): bool {
        if ($value === '') {
            return false;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);

        if (!$dt instanceof \DateTimeImmutable) {
            return false;
        }

        // createFromFormat acepta desbordamientos (p. ej. '2026-02-30 10:00:00'
        // → '2026-03-02 10:00:00'); rechazamos si el roundtrip difiere.
        return $dt->format('Y-m-d H:i:s') === $value;
    }

    // ─── Window derivation ───────────────────────────────────────────

    /**
     * @return array{0:string,1:string,2:string} date, start_time, end_time (HH:MM:SS).
     */
    private function derive_window(string $start_datetime, int $duration_minutes): array {
        $start = new \DateTimeImmutable($start_datetime);
        $end   = $start->modify('+' . $duration_minutes . ' minutes');

        return [
            $start->format('Y-m-d'),
            $start->format('H:i:s'),
            $end->format('H:i:s'),
        ];
    }

    // ─── Overlap fetching ────────────────────────────────────────────

    /**
     * Query pura delegada al modelo legacy. Mismo precedente que
     * `AA_Area_Availability_Service::is_zone_assignable_for_staff()`.
     *
     * @return array<int, array<string,mixed>>
     */
    private function fetch_overlaps(string $date, string $start_time, string $end_time, int $service_area_id): array {
        if (!class_exists('AssignmentsModel')) {
            return [];
        }

        $rows = \AssignmentsModel::get_active_assignments_overlapping_in_area(
            $date,
            $start_time,
            $end_time,
            $service_area_id
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function filter_same_staff(array $overlaps, int $staff_id): array {
        $out = [];
        foreach ($overlaps as $row) {
            $row_staff = isset($row['staff_id']) ? (int) $row['staff_id'] : 0;
            if ($row_staff === $staff_id) {
                $out[] = $row;
            }
        }
        return $out;
    }

    // ─── Classification ──────────────────────────────────────────────

    /**
     * Clasifica las assignments del mismo staff en:
     *   - `containing`: la primera cuyo rango CONTIENE al slot
     *     solicitado. Por la invariante I1, a lo más habrá una.
     *   - `overlapping`: la primera que solapa pero no contiene, si
     *     no hay `containing`.
     *
     * @return array{containing: ?array<string,mixed>, overlapping: ?array<string,mixed>}
     */
    private function classify_same_staff(array $same_staff, string $slot_start, string $slot_end): array {
        $containing = null;
        $overlapping = null;

        foreach ($same_staff as $row) {
            $row_start = (string) ($row['start_time'] ?? '');
            $row_end   = (string) ($row['end_time'] ?? '');

            // Comparación lexicográfica sobre 'HH:MM:SS' equivale a
            // orden temporal dentro del mismo día.
            $contains = ($row_start <= $slot_start) && ($row_end >= $slot_end);

            if ($contains) {
                if ($containing === null) {
                    $containing = $row;
                }
            } elseif ($overlapping === null) {
                $overlapping = $row;
            }
        }

        return [
            'containing'  => $containing,
            'overlapping' => $overlapping,
        ];
    }

    // ─── Service lookup on the candidate ─────────────────────────────

    /**
     * Trae la lista de servicios de UNA sola candidata (la `containing`),
     * evitando N+1. Si el modelo no está disponible, devuelve `[]`.
     *
     * @return array<int, array<string,mixed>>
     */
    private function fetch_services_for_assignment(int $assignment_id): array {
        if ($assignment_id <= 0 || !class_exists('AssignmentsModel')) {
            return [];
        }

        $rows = \AssignmentsModel::get_assignment_services($assignment_id);

        return is_array($rows) ? $rows : [];
    }

    private function services_include(array $services, int $service_id): bool {
        foreach ($services as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['id'] ?? 0) === $service_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function normalize_services(array $services): array {
        $out = [];
        foreach ($services as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id'   => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }
        return $out;
    }

    // ─── Result builders ─────────────────────────────────────────────

    private function create_new(
        int $staff_id,
        int $service_area_id,
        int $service_id,
        string $start_datetime,
        int $duration_minutes
    ): array {
        return [
            'mode'             => 'create_new',
            'assignment_id'    => null,
            'rationale'        => 'no_compatible_found',
            'pending_creation' => [
                'staff_id'         => $staff_id,
                'service_area_id'  => $service_area_id,
                'service_id'       => $service_id,
                'start_datetime'   => $start_datetime,
                'duration_minutes' => $duration_minutes,
            ],
            'conflict'         => null,
        ];
    }

    /**
     * @param array<string,mixed>|null $conflict
     */
    private function unresolved(string $rationale, ?array $conflict): array {
        return [
            'mode'             => 'unresolved',
            'assignment_id'    => null,
            'rationale'        => $rationale,
            'pending_creation' => null,
            'conflict'         => $conflict,
        ];
    }
}
