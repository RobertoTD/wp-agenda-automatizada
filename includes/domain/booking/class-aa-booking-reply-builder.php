<?php
/**
 * Booking Reply Builder
 *
 * Capa: `includes/domain/booking/` (dominio puro).
 *
 * Transforma `draft_state` (salida de `AA_Booking_Draft_Aggregator::aggregate()`)
 * en un payload estructurado para el chat admin: texto natural breve,
 * CTA, highlights, choices y eco legible del borrador.
 *
 * ─── Invariantes ─────────────────────────────────────────────────────
 *
 *   - Sin `$wpdb`, `get_option`, `error_log`, hooks, LLM ni SQL.
 *   - Determinista e idempotente: misma entrada → misma salida.
 *   - Una sola entrada pública: `build(array $draft_state): array`.
 *   - Sin `require_once` de otros archivos del plugin.
 *   - Defensiva: claves ausentes no rompen; mínimo útil si el shape viene
 *     vacío o incompleto.
 *
 * La precedencia de `cta` se calcula solo con los arrays tal como vienen;
 * no se re-deriva `state` desde cero.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Booking
 */

defined('ABSPATH') or die('No direct access');

final class AA_Booking_Reply_Builder {

    /**
     * @param array<string,mixed> $draft_state Shape de `aggregate()`.
     * @return array{
     *   text: string,
     *   cta: string,
     *   highlights: array<int, array{label:string,value:string}>,
     *   choices: array<int, array{field:string,candidates:array<int, array{id:int,label:string}>}>,
     *   draft_echo: array{
     *     client: ?string,
     *     service: ?string,
     *     staff: ?string,
     *     zone: ?string,
     *     datetime: ?string
     *   }
     * }
     */
    public function build(array $draft_state): array {
        $draft    = isset($draft_state['draft']) && is_array($draft_state['draft']) ? $draft_state['draft'] : [];
        $blockers = isset($draft_state['blockers']) && is_array($draft_state['blockers']) ? $draft_state['blockers'] : [];
        $required = isset($draft_state['required_literal']) && is_array($draft_state['required_literal'])
            ? $draft_state['required_literal'] : [];
        $state    = isset($draft_state['state']) ? (string) $draft_state['state'] : '';

        if (!is_array($draft_state) || $draft_state === []) {
            return $this->empty_shell();
        }

        $highlights = $this->build_highlights($draft);
        $draft_echo = $this->build_draft_echo($draft);
        $cta        = $this->resolve_cta($blockers, $required, $state);
        $choices    = $cta === 'pick_ambiguous' ? $this->build_choices($required) : [];
        $text       = $this->build_text($cta, $blockers, $required, $draft_echo);

        return [
            'text'       => $text,
            'cta'        => $cta,
            'highlights' => $highlights,
            'choices'    => $choices,
            'draft_echo' => $draft_echo,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function empty_shell(): array {
        return [
            'text'       => 'No tengo datos del borrador para continuar.',
            'cta'        => 'noop',
            'highlights' => [],
            'choices'    => [],
            'draft_echo' => [
                'client'   => null,
                'service'  => null,
                'staff'    => null,
                'zone'     => null,
                'datetime' => null,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $draft
     * @return array<int, array{label:string,value:string}>
     */
    private function build_highlights(array $draft): array {
        $out = [];

        $client = isset($draft['client']) && is_array($draft['client']) ? $draft['client'] : null;
        if ($client && isset($client['nombre']) && (string) $client['nombre'] !== '') {
            $out[] = ['label' => 'cliente', 'value' => (string) $client['nombre']];
        }

        $service = isset($draft['service']) && is_array($draft['service']) ? $draft['service'] : null;
        if ($service && isset($service['name']) && (string) $service['name'] !== '') {
            $out[] = ['label' => 'servicio', 'value' => (string) $service['name']];
        }

        $staff = isset($draft['staff']) && is_array($draft['staff']) ? $draft['staff'] : null;
        if ($staff && isset($staff['name']) && (string) $staff['name'] !== '') {
            $out[] = ['label' => 'profesional', 'value' => (string) $staff['name']];
        }

        $zone = isset($draft['zone']) && is_array($draft['zone']) ? $draft['zone'] : null;
        if ($zone && isset($zone['name']) && (string) $zone['name'] !== '') {
            $out[] = ['label' => 'zona', 'value' => (string) $zone['name']];
        }

        $dt = isset($draft['datetime']) && is_array($draft['datetime']) ? $draft['datetime'] : null;
        if ($dt) {
            $ld = isset($dt['local_date']) ? (string) $dt['local_date'] : '';
            $lt = isset($dt['local_time']) ? (string) $dt['local_time'] : '';
            if ($ld !== '') {
                $out[] = ['label' => 'fecha', 'value' => $ld];
            }
            if ($lt !== '') {
                $out[] = ['label' => 'hora', 'value' => $this->format_time_display($lt)];
            }
        }

        $dur = isset($draft['duration']) && is_array($draft['duration']) ? $draft['duration'] : null;
        if ($dur) {
            $src = isset($dur['source']) ? (string) $dur['source'] : '';
            if ($src !== '' && $src !== 'fallback') {
                $min = isset($dur['minutes']) ? (int) $dur['minutes'] : 0;
                if ($min > 0) {
                    $out[] = ['label' => 'duración', 'value' => (string) $min . ' min'];
                }
            }
        }

        return $out;
    }

    private function format_time_display(string $local_time): string {
        if (preg_match('/^(\d{2}:\d{2})/', $local_time, $m)) {
            return $m[1];
        }
        return $local_time;
    }

    /**
     * @param array<string,mixed> $draft
     * @return array{client:?string,service:?string,staff:?string,zone:?string,datetime:?string}
     */
    private function build_draft_echo(array $draft): array {
        $client = isset($draft['client']) && is_array($draft['client']) ? $draft['client'] : null;
        $cname   = ($client && !empty($client['nombre'])) ? (string) $client['nombre'] : null;

        $service = isset($draft['service']) && is_array($draft['service']) ? $draft['service'] : null;
        $sname   = ($service && !empty($service['name'])) ? (string) $service['name'] : null;

        $staff = isset($draft['staff']) && is_array($draft['staff']) ? $draft['staff'] : null;
        $st    = ($staff && !empty($staff['name'])) ? (string) $staff['name'] : null;

        $zone = isset($draft['zone']) && is_array($draft['zone']) ? $draft['zone'] : null;
        $zn   = ($zone && !empty($zone['name'])) ? (string) $zone['name'] : null;

        $dt_echo = null;
        $dt      = isset($draft['datetime']) && is_array($draft['datetime']) ? $draft['datetime'] : null;
        if ($dt) {
            $ld = isset($dt['local_date']) ? (string) $dt['local_date'] : '';
            $lt = isset($dt['local_time']) ? (string) $dt['local_time'] : '';
            if ($ld !== '' && $lt !== '') {
                $dt_echo = $ld . ' ' . $this->format_time_display($lt);
            } elseif (!empty($dt['local_datetime'])) {
                $dt_echo = (string) $dt['local_datetime'];
            }
        }

        return [
            'client'   => $cname,
            'service'  => $sname,
            'staff'    => $st,
            'zone'     => $zn,
            'datetime' => $dt_echo,
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $blockers
     * @param array<int, array<string,mixed>> $required
     */
    private function resolve_cta(array $blockers, array $required, string $state): string {
        if (count($blockers) > 0) {
            return 'fix_blocker';
        }
        foreach ($required as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['reason'] ?? '') === 'ambiguous') {
                return 'pick_ambiguous';
            }
        }
        if (count($required) > 0) {
            return 'collect_input';
        }
        if ($state === 'needs_confirmation_of_proposals') {
            return 'confirm_heuristics';
        }
        if ($state === 'ready_for_confirmation') {
            return 'confirm';
        }
        return 'noop';
    }

    /**
     * @param array<int, array<string,mixed>> $required
     * @return array<int, array{field:string,candidates:array<int, array{id:int,label:string}>}>
     */
    private function build_choices(array $required): array {
        $out = [];
        foreach ($required as $row) {
            if (!is_array($row) || ($row['reason'] ?? '') !== 'ambiguous') {
                continue;
            }
            $field = isset($row['field']) ? (string) $row['field'] : '';
            $cands = isset($row['candidates']) && is_array($row['candidates']) ? $row['candidates'] : [];
            $mapped = [];
            foreach ($cands as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $id = isset($c['id']) ? (int) $c['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $label = '';
                if (isset($c['nombre'])) {
                    $label = (string) $c['nombre'];
                } elseif (isset($c['name'])) {
                    $label = (string) $c['name'];
                }
                $mapped[] = ['id' => $id, 'label' => $label];
            }
            if ($field !== '' && count($mapped) > 0) {
                $out[] = ['field' => $field, 'candidates' => $mapped];
            }
        }
        return $out;
    }

    /**
     * @param array<int, array<string,mixed>> $blockers
     * @param array<int, array<string,mixed>> $required
     * @param array{client:?string,service:?string,staff:?string,zone:?string,datetime:?string} $echo
     */
    private function build_text(
        string $cta,
        array $blockers,
        array $required,
        array $echo
    ): string {
        switch ($cta) {
            case 'fix_blocker':
                return $this->text_fix_blocker($blockers);
            case 'pick_ambiguous':
                return $this->text_pick_ambiguous($required);
            case 'collect_input':
                return $this->text_collect_input($required);
            case 'confirm_heuristics':
                return 'Hay detalles por confirmar antes de agendar la cita.';
            case 'confirm':
                return $this->text_confirm($echo);
            default:
                return 'No puedo avanzar con esta solicitud por ahora.';
        }
    }

    /**
     * @param array<int, array<string,mixed>> $blockers
     */
    private function text_fix_blocker(array $blockers): string {
        if (count($blockers) === 0) {
            return 'No puedo agendar por un conflicto desconocido.';
        }
        $first = $blockers[0];
        $code  = is_array($first) && isset($first['code']) ? (string) $first['code'] : 'unknown';
        $msg   = $this->blocker_message($code);
        if (count($blockers) > 1) {
            $msg .= ' (y hay otros conflictos)';
        }
        return $msg;
    }

    private function blocker_message(string $code): string {
        $map = [
            'service_not_found'                 => 'No encuentro ese servicio en el catálogo.',
            'staff_not_found'                   => 'No encuentro a ese profesional.',
            'zone_not_found'                    => 'No encuentro esa zona.',
            'staff_does_not_offer_service'      => 'Ese profesional no ofrece ese servicio.',
            'staff_has_no_services'             => 'Ese profesional no ofrece ese servicio.',
            'staff_service_mismatch'            => 'Ese profesional no ofrece ese servicio.',
            'no_active_staff_for_service'       => 'Ese profesional no ofrece ese servicio.',
            'staff_busy'                        => 'Ese profesional ya tiene una cita en ese horario.',
            'zone_reserved_for_other_staff'     => 'La zona está reservada por otro profesional en ese horario.',
            'zone_busy'                         => 'La zona ya está ocupada en ese horario.',
            'assignment_out_of_turn'            => 'El turno existente no es suficientemente amplio para esa cita.',
            'assignment_service_not_offered'    => 'El turno existente en esa zona no incluye ese servicio.',
            'datetime_past'                       => 'Esa fecha ya pasó. Indícame una fecha futura.',
        ];
        if (isset($map[$code])) {
            return $map[$code];
        }
        return 'No puedo agendar por: ' . $code;
    }

    /**
     * @param array<int, array<string,mixed>> $required
     */
    private function text_pick_ambiguous(array $required): string {
        foreach ($required as $row) {
            if (!is_array($row) || ($row['reason'] ?? '') !== 'ambiguous') {
                continue;
            }
            $field = isset($row['field']) ? (string) $row['field'] : 'dato';
            $cands = isset($row['candidates']) && is_array($row['candidates']) ? $row['candidates'] : [];
            $n     = count($cands);
            $label = $this->ambiguous_entity_plural($field);
            return 'Hay ' . $n . ' ' . $label . ' que coinciden con ese nombre. ¿Cuál?';
        }
        return 'Hay varias coincidencias. ¿Cuál prefieres?';
    }

    private function ambiguous_entity_plural(string $field): string {
        $m = [
            'client'  => 'clientes',
            'service' => 'servicios',
            'staff'   => 'profesionales',
            'zone'    => 'zonas',
        ];
        return $m[$field] ?? 'opciones';
    }

    /**
     * Orden fijo para enumerar faltantes simples en copy multi-campo
     * (independiente del orden en `required_literal`).
     */
    private const COLLECT_FIELD_PRIORITY = [
        'client'   => 0,
        'service'  => 1,
        'staff'    => 2,
        'zone'     => 3,
        'date'     => 4,
        'time'     => 5,
        'datetime' => 6,
    ];

    /**
     * @param array<int, array<string,mixed>> $required
     */
    private function text_collect_input(array $required): string {
        $simple = $this->extract_simple_collect_rows($required);
        if (count($simple) === 0) {
            return 'Indícame el dato que falta para continuar.';
        }

        if (count($simple) === 1) {
            $row = $simple[0];
            $hint = isset($row['hint']) ? trim((string) $row['hint']) : '';
            if ($hint !== '') {
                return $hint;
            }
            return $this->fallback_for_required($row);
        }

        $fields = [];
        foreach ($simple as $row) {
            $f = isset($row['field']) ? trim((string) $row['field']) : '';
            if ($f !== '') {
                $fields[$f] = true;
            }
        }
        $ordered = $this->order_collect_fields(array_keys($fields));
        $labels  = [];
        foreach ($ordered as $f) {
            $label = $this->human_label_for_collect_field($f);
            if ($label !== null && $label !== '') {
                $labels[] = $label;
            }
        }

        if (count($labels) === 0) {
            return 'Indícame el dato que falta para continuar.';
        }

        // Varias filas pero todas apuntan al mismo campo (poco habitual):
        // reutilizar la ruta de un solo faltante.
        if (count($labels) === 1) {
            $field = $ordered[0];
            $representative = null;
            foreach ($simple as $row) {
                $rf = isset($row['field']) ? trim((string) $row['field']) : '';
                if ($rf === $field) {
                    $representative = $row;
                    break;
                }
            }
            $representative = $representative ?? $simple[0];
            $hint = isset($representative['hint']) ? trim((string) $representative['hint']) : '';
            if ($hint !== '') {
                return $hint;
            }
            return $this->fallback_for_required($representative);
        }

        return $this->compose_multi_missing_message($ordered, $labels);
    }

    /**
     * Filas de `required_literal` que aplican a `collect_input`: no-array
     * se ignoran; `reason === 'ambiguous'` se ignora (el CTA ya sería
     * `pick_ambiguous`).
     *
     * @param array<int, array<string,mixed>> $required
     * @return array<int, array<string,mixed>>
     */
    private function extract_simple_collect_rows(array $required): array {
        $out = [];
        foreach ($required as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['reason'] ?? '') === 'ambiguous') {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param array<int, string> $fields
     * @return array<int, string>
     */
    private function order_collect_fields(array $fields): array {
        $unique = array_values(array_unique($fields));
        usort($unique, function ($a, $b) {
            $pa = self::COLLECT_FIELD_PRIORITY[$a] ?? 99;
            $pb = self::COLLECT_FIELD_PRIORITY[$b] ?? 99;
            if ($pa === $pb) {
                return strcmp($a, $b);
            }
            return $pa <=> $pb;
        });
        return $unique;
    }

    /**
     * @return string|null null si el campo no está mapeado (se omite del copy).
     */
    private function human_label_for_collect_field(string $field): ?string {
        $map = [
            'client'   => 'cliente',
            'service'  => 'servicio',
            'staff'    => 'profesional',
            'zone'     => 'zona',
            'date'     => 'fecha',
            'time'     => 'hora',
            'datetime' => 'fecha y hora',
        ];
        return array_key_exists($field, $map) ? $map[$field] : null;
    }

    /**
     * Une etiquetas en español: "a y b" o "a, b y c".
     *
     * @param array<int, string> $labels
     */
    private function join_human_labels(array $labels): string {
        $n = count($labels);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $labels[0];
        }
        if ($n === 2) {
            return $labels[0] . ' y ' . $labels[1];
        }
        $copy = $labels;
        $last = array_pop($copy);
        return implode(', ', $copy) . ' y ' . $last;
    }

    /**
     * @param array<int, string> $ordered_fields
     * @param array<int, string> $labels
     */
    private function compose_multi_missing_message(array $ordered_fields, array $labels): string {
        $joined = $this->join_human_labels($labels);
        if ($joined === '') {
            return 'Indícame el dato que falta para continuar.';
        }

        $n = count($labels);
        if ($n === 2
            && count($ordered_fields) === 2
            && $ordered_fields[0] === 'date'
            && $ordered_fields[1] === 'time'
        ) {
            return 'Ya casi está. Solo me faltan fecha y hora.';
        }

        if ($n === 2) {
            return 'Compárteme ' . $joined . ' para continuar.';
        }

        return 'Para continuar, indícame ' . $joined . '.';
    }

    /**
     * @param array<string,mixed> $row
     */
    private function fallback_for_required(array $row): string {
        $field  = isset($row['field']) ? (string) $row['field'] : '';
        $reason = isset($row['reason']) ? (string) $row['reason'] : '';

        if ($reason === 'past') {
            return 'Esa fecha ya pasó; indica una fecha futura.';
        }
        if ($reason === 'invalid') {
            return 'La fecha indicada no es válida; corrígela.';
        }
        if ($reason === 'unrecognized') {
            return 'No reconocí esa fecha u hora; indícala de otra forma.';
        }
        if ($reason === 'missing') {
            if ($field === 'time') {
                return 'Falta la hora. Indícame a qué hora.';
            }
            if ($field === 'date') {
                return 'Falta la fecha. Indícame qué día.';
            }
            if ($field === 'client') {
                return 'Indica el cliente.';
            }
            if ($field === 'service') {
                return 'Indica el servicio.';
            }
            if ($field === 'staff') {
                return 'Indica con qué profesional.';
            }
            if ($field === 'zone') {
                return 'Indica en qué zona.';
            }
            if ($field === 'datetime') {
                return 'Ajusta la fecha u hora de la cita.';
            }
            return 'Falta información; indícame el dato que falta.';
        }
        if ($reason === 'no_match') {
            return 'No hay coincidencia con lo indicado; prueba con otro nombre o dato.';
        }
        if ($reason === 'service_not_in_turn') {
            return 'El turno existente en esa zona no incluye ese servicio; elige otro servicio o ajusta el turno.';
        }
        if ($reason === 'out_of_turn') {
            return 'El staff tiene un turno en esa zona pero la hora propuesta se sale; elige una hora dentro del turno.';
        }
        return 'Indícame el dato que falta para continuar.';
    }

    /**
     * @param array{client:?string,service:?string,staff:?string,zone:?string,datetime:?string} $echo
     */
    private function text_confirm(array $echo): string {
        $parts = [];
        if ($echo['service'] !== null) {
            $parts[] = $echo['service'];
        }
        if ($echo['staff'] !== null) {
            $parts[] = 'con ' . $echo['staff'];
        }
        if ($echo['zone'] !== null) {
            $parts[] = 'en ' . $echo['zone'];
        }
        if ($echo['datetime'] !== null) {
            $parts[] = 'el ' . $echo['datetime'];
        }
        $core = count($parts) > 0 ? implode(' ', $parts) : 'la cita';
        return 'Listo para agendar ' . $core . '. ¿Confirmo?';
    }
}
