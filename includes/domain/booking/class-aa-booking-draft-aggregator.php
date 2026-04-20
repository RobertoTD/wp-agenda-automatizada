<?php
/**
 * Booking Draft Aggregator
 *
 * Capa: includes/domain/booking/ (regla pura de negocio).
 *
 * Consolida en un único `draft_state` accionable las 9 dimensiones que
 * hoy produce `AA_AI_Create_Booking_Intent_Handler::handle()`:
 * `parsed_input`, `missing_fields`, `ambiguous_fields`, `resolved`,
 * `lookup`, `datetime_resolution` y `feasibility` (que a su vez agrupa
 * `service_basic`, `service_staff_capacity`, `staff_basic`,
 * `staff_service_match`, `staff_time_availability`, `zone_basic`,
 * `zone_assignment_guard`, `zone_time_occupancy`).
 *
 * Esta pieza traduce ese ruido a una vista de borrador conversacional:
 *   - qué tiene ya el draft (snapshot de entidades resueltas y de la
 *     duración inferida o por servicio),
 *   - qué falta literalmente (`required_literal`, lo que el usuario aún
 *     debe decir),
 *   - qué se asumió por heurística y debería confirmarse
 *     (`confirmable_heuristics`),
 *   - qué bloqueos duros impiden avanzar (`blockers`, conflictos
 *     incompatibles del feasibility o fechas en el pasado),
 *   - un `state` único que el orquestador puede usar para decidir el
 *     siguiente turno conversacional.
 *
 * ─── Contrato de entrada (`aggregate($context)`) ─────────────────────
 *
 * `$context` es exactamente el shape que ya produce el handler hoy:
 *   [
 *     'parsed_input'        => array,   // 8 campos del parser LLM
 *     'missing_fields'      => string[],
 *     'ambiguous_fields'    => array,   // map entity => result
 *     'resolved'            => array,   // map entity => result
 *     'lookup'              => array,   // map entity => result
 *     'datetime_resolution' => array,   // shape del datetime resolver
 *     'feasibility'         => array,   // 9 sub-claves (status/reason/...)
 *     'duration_settings'   => array{    // opcional, alimenta la cascada
 *         default_minutes: int|null,    // null si la opción WP no fue guardada
 *     },
 *   ]
 *
 * Cualquier clave puede faltar; el agregador es defensivo y cubre
 * `?? null` / `is_array(...)` / casts explícitos sobre cada acceso.
 *
 * ─── Contrato de salida ──────────────────────────────────────────────
 *
 *   [
 *     'state' => 'incompatible'
 *              | 'needs_input'
 *              | 'needs_confirmation_of_proposals'
 *              | 'ready_for_confirmation',
 *     'draft' => [
 *       'client'   => {id,nombre,telefono,correo}|null,
 *       'service'  => {id,name,duration_minutes,price}|null,
 *       'staff'    => {id,name}|null,
 *       'zone'     => {id,name}|null,
 *       'datetime' => {local_datetime,local_date,local_time,timezone}|null,
 *       'duration' => ['minutes'=>int,'source'=>'service'|'setting'|'fallback'],
 *     ],
 *     'required_literal' => [
 *       {field, reason, hint, candidates?}
 *     ],
 *     'confirmable_heuristics' => [
 *       {field, value, source, because}
 *     ],
 *     'proposals' => [],
 *     'blockers'  => [
 *       {code, field?, detail?}
 *     ],
 *   ]
 *
 * El `state` se calcula en este orden (primer match gana):
 *   1) `count(blockers) > 0`                  → `incompatible`
 *   2) `count(required_literal) > 0`          → `needs_input`
 *   3) `count(confirmable_heuristics) > 0`    → `needs_confirmation_of_proposals`
 *   4) otherwise                              → `ready_for_confirmation`
 *
 * ─── Invariantes ─────────────────────────────────────────────────────
 *
 *   - 100% pura: sin `$wpdb`, sin `get_option`, sin `error_log`, sin
 *     globals, sin `add_action`, sin Models/Repositories/Servicios y
 *     sin LLM. Determinista y testeable en aislamiento.
 *   - No muta `$context` (no asignaciones a referencias).
 *   - Defensiva ante claves ausentes en cualquier nivel.
 *   - Idempotente: misma entrada → misma salida.
 *   - Una sola entrada pública (`aggregate`); métodos privados libres.
 *   - Sin `require_once` de archivos del plugin.
 *
 * ─── Nota explícita sobre proposals y confirmable_heuristics ─────────
 *
 * En este paso NO se generan `proposals`: la clave existe en la salida
 * y se devuelve siempre vacía (`[]`) reservada para uso futuro
 * (alternativas de horario, staff o zona sugeridos por dominio).
 *
 * Tras el refinement del Paso 1.5, `confirmable_heuristics` también se
 * devuelve siempre vacío. La cascada de duración (service / setting /
 * fallback) y la heurística AM/PM del datetime resolver se consideran
 * determinísticas y suficientemente confiables, por lo que no requieren
 * reconfirmación humana. La trazabilidad sigue accesible vía
 * `draft.duration.source` y `datetime_resolution.meta.time_source_type`,
 * para que el reply natural pueda mencionarlas casualmente. El canal se
 * conserva como reserva para asunciones futuras que sí merezcan
 * confirmación explícita.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Booking
 */

defined('ABSPATH') or die('No direct access');

final class AA_Booking_Draft_Aggregator {

    /**
     * Tercer escalón de la cascada de duración: cuando ni el servicio
     * trae `duration_minutes > 0` ni el setting `aa_slot_duration` está
     * configurado, se cae a este valor hardcoded.
     */
    private const FALLBACK_DURATION_MINUTES = 30;

    /**
     * @param array $context Shape descrito en el docblock de la clase.
     * @return array `draft_state` con el shape descrito en el docblock.
     */
    public function aggregate(array $context): array {
        $parsed_input        = $this->safe_array($context, 'parsed_input');
        $ambiguous_fields    = $this->safe_array($context, 'ambiguous_fields');
        $resolved            = $this->safe_array($context, 'resolved');
        $lookup              = $this->safe_array($context, 'lookup');
        $datetime_resolution = $this->safe_array($context, 'datetime_resolution');
        $feasibility         = $this->safe_array($context, 'feasibility');
        $duration_settings   = $this->safe_array($context, 'duration_settings');

        $draft = $this->build_draft($resolved, $datetime_resolution, $duration_settings);

        $required_literal       = $this->build_required_literal($lookup, $ambiguous_fields, $resolved, $datetime_resolution);
        $confirmable_heuristics = $this->build_confirmable_heuristics($draft, $datetime_resolution);
        $blockers               = $this->build_blockers($feasibility, $datetime_resolution);

        $state = $this->compute_state($blockers, $required_literal, $confirmable_heuristics);

        // `parsed_input` se acepta para preservar el contrato de entrada
        // pero no influye en este paso (el draft se arma desde `resolved`
        // y `datetime_resolution`). Lo tocamos solo para evitar warnings
        // de variable no usada en linters estrictos.
        unset($parsed_input);

        return [
            'state'                  => $state,
            'draft'                  => $draft,
            'required_literal'       => $required_literal,
            'confirmable_heuristics' => $confirmable_heuristics,
            'proposals'              => [],
            'blockers'               => $blockers,
        ];
    }

    // ─── Draft snapshot ──────────────────────────────────────────────

    /**
     * @return array{
     *     client: array|null,
     *     service: array|null,
     *     staff: array|null,
     *     zone: array|null,
     *     datetime: array|null,
     *     duration: array{minutes:int,source:string}
     * }
     */
    private function build_draft(array $resolved, array $datetime_resolution, array $duration_settings): array {
        $client_resolved  = $this->safe_array($resolved, 'client');
        $service_resolved = $this->safe_array($resolved, 'service');
        $staff_resolved   = $this->safe_array($resolved, 'staff');
        $zone_resolved    = $this->safe_array($resolved, 'zone');

        return [
            'client'   => $this->snapshot_client($client_resolved),
            'service'  => $this->snapshot_service($service_resolved),
            'staff'    => $this->snapshot_staff($staff_resolved),
            'zone'     => $this->snapshot_zone($zone_resolved),
            'datetime' => $this->snapshot_datetime($datetime_resolution),
            'duration' => $this->resolve_duration($service_resolved, $duration_settings),
        ];
    }

    private function snapshot_client(array $client): ?array {
        if (empty($client)) {
            return null;
        }

        return [
            'id'       => isset($client['id']) ? (int) $client['id'] : 0,
            'nombre'   => isset($client['nombre']) ? (string) $client['nombre'] : null,
            'telefono' => isset($client['telefono']) ? (string) $client['telefono'] : null,
            'correo'   => isset($client['correo']) ? (string) $client['correo'] : null,
        ];
    }

    private function snapshot_service(array $service): ?array {
        if (empty($service)) {
            return null;
        }

        $duration = isset($service['duration_minutes']) && $service['duration_minutes'] !== null && $service['duration_minutes'] !== ''
            ? (int) $service['duration_minutes']
            : null;

        return [
            'id'               => isset($service['id']) ? (int) $service['id'] : 0,
            'name'             => isset($service['name']) ? (string) $service['name'] : null,
            'duration_minutes' => $duration,
            'price'            => $service['price'] ?? null,
        ];
    }

    private function snapshot_staff(array $staff): ?array {
        if (empty($staff)) {
            return null;
        }

        return [
            'id'   => isset($staff['id']) ? (int) $staff['id'] : 0,
            'name' => isset($staff['name']) ? (string) $staff['name'] : null,
        ];
    }

    private function snapshot_zone(array $zone): ?array {
        if (empty($zone)) {
            return null;
        }

        return [
            'id'   => isset($zone['id']) ? (int) $zone['id'] : 0,
            'name' => isset($zone['name']) ? (string) $zone['name'] : null,
        ];
    }

    private function snapshot_datetime(array $datetime_resolution): ?array {
        $status = $datetime_resolution['status'] ?? null;

        if ($status !== 'resolved') {
            return null;
        }

        $normalized = $this->safe_array($datetime_resolution, 'normalized');

        if (empty($normalized)) {
            return null;
        }

        return [
            'local_datetime' => isset($normalized['local_datetime']) ? (string) $normalized['local_datetime'] : null,
            'local_date'     => isset($normalized['local_date']) ? (string) $normalized['local_date'] : null,
            'local_time'     => isset($normalized['local_time']) ? (string) $normalized['local_time'] : null,
            'timezone'       => isset($normalized['timezone']) ? (string) $normalized['timezone'] : null,
        ];
    }

    /**
     * Cascada de duración (primer match gana):
     *
     *   1) `resolved.service.duration_minutes > 0`     → `source: 'service'`
     *   2) `duration_settings.default_minutes > 0`     → `source: 'setting'`
     *   3) hardcoded `FALLBACK_DURATION_MINUTES` (30)  → `source: 'fallback'`
     *
     * Importante: el setting puede valer 30 explícitamente (es un valor
     * permitido en la configuración del plugin). Esa lectura se reporta
     * como `source: 'setting'`, NUNCA como `'fallback'`. La distinción
     * importa porque `'setting'` significa "el dueño del negocio
     * configuró esto" y `'fallback'` significa "nadie lo configuró".
     *
     * @return array{minutes:int,source:string}
     */
    private function resolve_duration(array $service_resolved, array $duration_settings): array {
        $service_minutes = isset($service_resolved['duration_minutes'])
            ? (int) $service_resolved['duration_minutes']
            : 0;

        if ($service_minutes > 0) {
            return ['minutes' => $service_minutes, 'source' => 'service'];
        }

        $setting_raw = $duration_settings['default_minutes'] ?? null;
        if (is_int($setting_raw) && $setting_raw > 0) {
            return ['minutes' => $setting_raw, 'source' => 'setting'];
        }

        return ['minutes' => self::FALLBACK_DURATION_MINUTES, 'source' => 'fallback'];
    }

    // ─── Required literal ────────────────────────────────────────────

    /**
     * Lo que el usuario debe decir literalmente para avanzar el draft.
     *
     * @return array<int, array<string,mixed>>
     */
    private function build_required_literal(
        array $lookup,
        array $ambiguous_fields,
        array $resolved,
        array $datetime_resolution
    ): array {
        $items = [];

        $client_lookup_status = $this->lookup_status($lookup, 'client');
        if ($client_lookup_status === 'missing') {
            $items[] = [
                'field'  => 'client',
                'reason' => 'missing',
                'hint'   => 'indica el nombre del cliente',
            ];
        } elseif ($client_lookup_status === 'no_match') {
            $items[] = [
                'field'  => 'client',
                'reason' => 'no_match',
                'hint'   => 'ese cliente no existe; elige uno o créalo manualmente',
            ];
        }

        foreach (['client', 'service', 'staff', 'zone'] as $entity) {
            $ambiguous = $this->safe_array($ambiguous_fields, $entity);
            if (empty($ambiguous)) {
                continue;
            }

            $items[] = [
                'field'      => $entity,
                'reason'     => 'ambiguous',
                'hint'       => 'hay varias coincidencias; indica cuál',
                'candidates' => $this->normalize_candidates($entity, $ambiguous),
            ];
        }

        $service_lookup_status = $this->lookup_status($lookup, 'service');
        if (
            !isset($resolved['service'])
            && !isset($ambiguous_fields['service'])
        ) {
            if ($service_lookup_status === 'missing') {
                $items[] = [
                    'field'  => 'service',
                    'reason' => 'missing',
                    'hint'   => 'indica qué servicio',
                ];
            } elseif ($service_lookup_status === 'no_match') {
                $items[] = [
                    'field'  => 'service',
                    'reason' => 'no_match',
                    'hint'   => 'ese servicio no está en el catálogo; indica otro',
                ];
            }
        }

        $staff_lookup_status = $this->lookup_status($lookup, 'staff');
        if (
            !isset($resolved['staff'])
            && !isset($ambiguous_fields['staff'])
        ) {
            if ($staff_lookup_status === 'missing') {
                $items[] = [
                    'field'  => 'staff',
                    'reason' => 'missing',
                    'hint'   => 'indica con qué profesional',
                ];
            } elseif ($staff_lookup_status === 'no_match') {
                $items[] = [
                    'field'  => 'staff',
                    'reason' => 'no_match',
                    'hint'   => 'no encontré a ese profesional; indica otro',
                ];
            }
        }

        $zone_lookup_status = $this->lookup_status($lookup, 'zone');
        if (
            !isset($resolved['zone'])
            && !isset($ambiguous_fields['zone'])
        ) {
            if ($zone_lookup_status === 'missing') {
                $items[] = [
                    'field'  => 'zone',
                    'reason' => 'missing',
                    'hint'   => 'indica en qué zona',
                ];
            } elseif ($zone_lookup_status === 'no_match') {
                $items[] = [
                    'field'  => 'zone',
                    'reason' => 'no_match',
                    'hint'   => 'esa zona no existe; indica otra',
                ];
            }
        }

        $dt_status = $datetime_resolution['status'] ?? null;
        $dt_errors = $this->safe_array($datetime_resolution, 'errors');

        if ($dt_status === 'missing_date' || $dt_status === 'partial') {
            $items[] = ['field' => 'date', 'reason' => 'missing'];
        }

        if ($dt_status === 'missing_time' || $dt_status === 'partial') {
            $items[] = ['field' => 'time', 'reason' => 'missing'];
        }

        if ($dt_status === 'unrecognized') {
            $has_date_error = $this->errors_match_prefix($dt_errors, 'date:');
            $has_time_error = $this->errors_match_prefix($dt_errors, 'time:');

            if ($has_date_error) {
                $items[] = ['field' => 'date', 'reason' => 'unrecognized'];
            }
            if ($has_time_error) {
                $items[] = ['field' => 'time', 'reason' => 'unrecognized'];
            }

            // Si no hay errores específicos pero el status global es
            // 'unrecognized', cubrimos el caso reportando ambos como
            // pista mínima para el usuario.
            if (!$has_date_error && !$has_time_error) {
                $items[] = ['field' => 'date', 'reason' => 'unrecognized'];
                $items[] = ['field' => 'time', 'reason' => 'unrecognized'];
            }
        }

        if ($dt_status === 'invalid_date') {
            $items[] = ['field' => 'date', 'reason' => 'invalid'];
        }

        if ($dt_status === 'invalid_or_past') {
            $items[] = [
                'field'  => 'date',
                'reason' => 'past',
                'hint'   => 'esa fecha ya pasó; indica una fecha futura',
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string,mixed>> $ambiguous
     * @return array<int, array<string,mixed>>
     */
    private function normalize_candidates(string $entity, array $ambiguous): array {
        $candidates = $this->safe_array($ambiguous, 'candidates');

        if (empty($candidates)) {
            return [];
        }

        $normalized = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $row = ['id' => isset($candidate['id']) ? (int) $candidate['id'] : 0];

            if ($entity === 'client') {
                $row['nombre'] = isset($candidate['nombre']) ? (string) $candidate['nombre'] : null;
            } else {
                $row['name'] = isset($candidate['name']) ? (string) $candidate['name'] : null;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    // ─── Confirmable heuristics ──────────────────────────────────────

    /**
     * Canal reservado para asunciones que sí requieren confirmación
     * humana. La cascada de duración (service / setting / fallback) y
     * la heurística AM/PM se consideran determinísticas y suficientemente
     * confiables, por lo que NO se reportan aquí; siguen accesibles vía
     * `draft.duration.source` y `datetime_resolution.meta.time_source_type`
     * para que el reply natural pueda mencionarlas casualmente.
     *
     * Hoy devuelve `[]` siempre. Se mantiene el método (y la clave en
     * el output) porque es el canal natural para futuras asunciones que
     * sí merezcan diálogo explícito (p. ej. resolución probabilística
     * de cliente por nombre cercano, sugerencias de zona alternativa).
     *
     * @return array<int, array<string,mixed>>
     */
    private function build_confirmable_heuristics(array $draft, array $datetime_resolution): array {
        unset($draft, $datetime_resolution);

        return [];
    }

    // ─── Blockers ────────────────────────────────────────────────────

    /**
     * Traducción de los `incompatible` del feasibility (y del datetime
     * en el pasado) a códigos accionables del draft.
     *
     * @return array<int, array<string,mixed>>
     */
    private function build_blockers(array $feasibility, array $datetime_resolution): array {
        $blockers = [];

        $service_basic = $this->safe_array($feasibility, 'service_basic');
        if (
            ($service_basic['status'] ?? null) === 'incompatible'
            && ($service_basic['reason'] ?? null) === 'service_not_found'
        ) {
            $blockers[] = ['code' => 'service_not_found', 'field' => 'service'];
        }

        $service_staff_capacity = $this->safe_array($feasibility, 'service_staff_capacity');
        if (($service_staff_capacity['status'] ?? null) === 'incompatible') {
            $blockers[] = [
                'code'   => 'no_active_staff_for_service',
                'field'  => 'service',
                'detail' => [
                    'service_id'  => isset($service_staff_capacity['service_id']) ? (int) $service_staff_capacity['service_id'] : 0,
                    'staff_count' => isset($service_staff_capacity['staff_count']) ? (int) $service_staff_capacity['staff_count'] : 0,
                ],
            ];
        }

        $staff_basic = $this->safe_array($feasibility, 'staff_basic');
        if (
            ($staff_basic['status'] ?? null) === 'incompatible'
            && ($staff_basic['reason'] ?? null) === 'staff_not_found'
        ) {
            $blockers[] = ['code' => 'staff_not_found', 'field' => 'staff'];
        }

        $staff_service_match = $this->safe_array($feasibility, 'staff_service_match');
        if (($staff_service_match['status'] ?? null) === 'incompatible') {
            $reason = (string) ($staff_service_match['reason'] ?? 'staff_service_mismatch');

            $detail = [];
            foreach (['staff_id', 'service_id'] as $key) {
                if (isset($staff_service_match[$key])) {
                    $detail[$key] = (int) $staff_service_match[$key];
                }
            }
            if (isset($staff_service_match['available_services']) && is_array($staff_service_match['available_services'])) {
                $detail['available_services'] = $staff_service_match['available_services'];
            }

            $blocker = ['code' => $reason, 'field' => 'staff'];
            if (!empty($detail)) {
                $blocker['detail'] = $detail;
            }
            $blockers[] = $blocker;
        }

        $staff_time = $this->safe_array($feasibility, 'staff_time_availability');
        if (
            ($staff_time['status'] ?? null) === 'incompatible'
            && ($staff_time['reason'] ?? null) === 'staff_busy'
        ) {
            $detail = [
                'duration_minutes' => isset($staff_time['duration_minutes']) ? (int) $staff_time['duration_minutes'] : 0,
            ];
            if (isset($staff_time['busy_range']) && is_array($staff_time['busy_range'])) {
                $detail['busy_range'] = [
                    'start' => isset($staff_time['busy_range']['start']) ? (string) $staff_time['busy_range']['start'] : '',
                    'end'   => isset($staff_time['busy_range']['end']) ? (string) $staff_time['busy_range']['end'] : '',
                ];
            }
            if (isset($staff_time['staff_id'])) {
                $detail['staff_id'] = (int) $staff_time['staff_id'];
            }

            $blockers[] = [
                'code'   => 'staff_busy',
                'field'  => 'datetime',
                'detail' => $detail,
            ];
        }

        $zone_basic = $this->safe_array($feasibility, 'zone_basic');
        if (
            ($zone_basic['status'] ?? null) === 'incompatible'
            && ($zone_basic['reason'] ?? null) === 'zone_not_found'
        ) {
            $blockers[] = ['code' => 'zone_not_found', 'field' => 'zone'];
        }

        $zone_assignment_guard = $this->safe_array($feasibility, 'zone_assignment_guard');
        if (($zone_assignment_guard['status'] ?? null) === 'incompatible') {
            $detail = [];
            foreach (['zone_id', 'staff_id', 'assignment_id', 'blocked_by_staff_id'] as $key) {
                if (isset($zone_assignment_guard[$key])) {
                    $detail[$key] = (int) $zone_assignment_guard[$key];
                }
            }
            foreach (['start_time', 'end_time'] as $key) {
                if (isset($zone_assignment_guard[$key]) && $zone_assignment_guard[$key] !== '') {
                    $detail[$key] = (string) $zone_assignment_guard[$key];
                }
            }

            $blocker = [
                'code'  => 'zone_reserved_for_other_staff',
                'field' => 'zone',
            ];
            if (!empty($detail)) {
                $blocker['detail'] = $detail;
            }
            $blockers[] = $blocker;
        }

        $zone_time = $this->safe_array($feasibility, 'zone_time_occupancy');
        if (($zone_time['status'] ?? null) === 'incompatible') {
            $detail = [];
            if (isset($zone_time['busy_range']) && is_array($zone_time['busy_range'])) {
                $detail['busy_range'] = [
                    'start' => isset($zone_time['busy_range']['start']) ? (string) $zone_time['busy_range']['start'] : '',
                    'end'   => isset($zone_time['busy_range']['end']) ? (string) $zone_time['busy_range']['end'] : '',
                ];
            }
            if (isset($zone_time['zone_id'])) {
                $detail['zone_id'] = (int) $zone_time['zone_id'];
            }
            if (isset($zone_time['duration_minutes'])) {
                $detail['duration_minutes'] = (int) $zone_time['duration_minutes'];
            }

            $blocker = [
                'code'  => 'zone_busy',
                'field' => 'zone',
            ];
            if (!empty($detail)) {
                $blocker['detail'] = $detail;
            }
            $blockers[] = $blocker;
        }

        if (($datetime_resolution['status'] ?? null) === 'invalid_or_past') {
            $blockers[] = ['code' => 'datetime_past', 'field' => 'datetime'];
        }

        return $blockers;
    }

    // ─── State ───────────────────────────────────────────────────────

    private function compute_state(array $blockers, array $required_literal, array $confirmable_heuristics): string {
        if (count($blockers) > 0) {
            return 'incompatible';
        }

        if (count($required_literal) > 0) {
            return 'needs_input';
        }

        if (count($confirmable_heuristics) > 0) {
            return 'needs_confirmation_of_proposals';
        }

        return 'ready_for_confirmation';
    }

    // ─── Helpers defensivos ──────────────────────────────────────────

    /**
     * @return array<string,mixed>|array<int,mixed>
     */
    private function safe_array(array $haystack, string $key): array {
        $value = $haystack[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    private function lookup_status(array $lookup, string $entity): ?string {
        $entry = $this->safe_array($lookup, $entity);
        $status = $entry['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    /**
     * @param array<int,mixed> $errors
     */
    private function errors_match_prefix(array $errors, string $prefix): bool {
        foreach ($errors as $err) {
            if (is_string($err) && strncmp($err, $prefix, strlen($prefix)) === 0) {
                return true;
            }
        }

        return false;
    }
}
