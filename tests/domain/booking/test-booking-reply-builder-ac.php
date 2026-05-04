<?php
/**
 * Harness standalone de `AA_Booking_Reply_Builder` (AC1–AC19 + AC3b).
 *
 *   php tests/domain/booking/test-booking-reply-builder-ac.php
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Tests\Domain\Booking
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/booking/class-aa-booking-reply-builder.php';

$builder = new AA_Booking_Reply_Builder();
$passed  = 0;
$total   = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK  ' : 'FAIL ') . $label . ($ok ? '' : ' — ' . $detail) . "\n";
}

function has_highlight(array $ui, string $label, ?string $valueContains = null): bool {
    foreach ($ui['highlights'] ?? [] as $h) {
        if (($h['label'] ?? '') === $label) {
            if ($valueContains === null) {
                return true;
            }
            return strpos((string) ($h['value'] ?? ''), $valueContains) !== false;
        }
    }
    return false;
}

// ─── AC1 ─────────────────────────────────────────────────────────────
$d1 = [
    'state'              => 'ready_for_confirmation',
    'draft'              => [
        'client'   => ['id' => 1, 'nombre' => 'Roberto', 'telefono' => '1', 'correo' => ''],
        'service'  => ['id' => 3, 'name' => 'Cejas', 'duration_minutes' => 30, 'price' => 0],
        'staff'    => ['id' => 5, 'name' => 'Anahí Temoltzin'],
        'zone'     => ['id' => 1, 'name' => 'Consultorio 1'],
        'datetime' => [
            'local_date'     => '2026-04-21',
            'local_time'     => '10:00:00',
            'local_datetime' => '2026-04-21 10:00:00',
            'timezone'       => 'America/Mexico_City',
        ],
        'duration'   => ['minutes' => 30, 'source' => 'service'],
        'assignment' => ['mode' => 'reuse', 'assignment_id' => 101, 'rationale' => 'service_match', 'pending_creation' => null],
    ],
    'required_literal'       => [],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u1 = $builder->build($d1);
ac(
    'AC1 ready_for_confirmation → confirm + texto',
    $u1['cta'] === 'confirm'
        && stripos($u1['text'], 'Cejas') !== false
        && stripos($u1['text'], 'Anahí') !== false
        && stripos($u1['text'], 'Consultorio 1') !== false
        && strpos($u1['text'], '2026-04-21') !== false
        && (stripos($u1['text'], '10:00') !== false || stripos($u1['text'], '10') !== false),
    json_encode($u1, JSON_UNESCAPED_UNICODE)
);

// ─── AC2 ─────────────────────────────────────────────────────────────
$d2 = [
    'state' => 'needs_input',
    'draft' => [],
    'required_literal' => [['field' => 'time', 'reason' => 'missing', 'hint' => '']],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u2 = $builder->build($d2);
ac('AC2 collect_input time', $u2['cta'] === 'collect_input' && stripos($u2['text'], 'hora') !== false, $u2['text']);

// ─── AC3 ─────────────────────────────────────────────────────────────
$hint3 = 'ese cliente no existe; elige uno o créalo manualmente';
$d3    = [
    'state' => 'needs_input',
    'draft' => [],
    'required_literal' => [['field' => 'client', 'reason' => 'no_match', 'hint' => $hint3]],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u3 = $builder->build($d3);
ac('AC3 hint literal', $u3['cta'] === 'collect_input' && $u3['text'] === $hint3, $u3['text']);

// ─── AC3b client no_match + fecha/hora: hint primero, sin pedir “cliente” genérico ─
$hint3b = 'ese cliente no existe; elige uno o créalo manualmente';
$d3b    = [
    'state' => 'needs_input',
    'draft' => [
        'client' => ['nombre' => 'Armando Sánchez'],
    ],
    'required_literal' => [
        ['field' => 'client', 'reason' => 'no_match', 'hint' => $hint3b],
        ['field' => 'date', 'reason' => 'missing'],
        ['field' => 'time', 'reason' => 'missing'],
    ],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u3b = $builder->build($d3b);
$ok3b = $u3b['cta'] === 'collect_input'
    && strpos($u3b['text'], 'Seguimos con tu cita.') === 0
    && strpos($u3b['text'], $hint3b . '.') !== false
    && stripos($u3b['text'], 'fecha') !== false
    && stripos($u3b['text'], 'hora') !== false
    && stripos($u3b['text'], 'compárteme cliente') === false;
ac('AC3b client no_match + date/time sin lista genérica de cliente', $ok3b, $u3b['text']);

// ─── AC4 ─────────────────────────────────────────────────────────────
$cands = [
    ['id' => 1, 'nombre' => 'A'],
    ['id' => 2, 'nombre' => 'B'],
    ['id' => 3, 'nombre' => 'C'],
];
$d4 = [
    'state' => 'needs_input',
    'draft' => [],
    'required_literal' => [['field' => 'client', 'reason' => 'ambiguous', 'hint' => '', 'candidates' => $cands]],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u4 = $builder->build($d4);
$ok4 = $u4['cta'] === 'pick_ambiguous'
    && strpos($u4['text'], '3') !== false
    && isset($u4['choices'][0]['candidates'])
    && count($u4['choices'][0]['candidates']) === 3;
ac('AC4 pick_ambiguous + choices', $ok4, json_encode($u4, JSON_UNESCAPED_UNICODE));

// ─── AC5 ─────────────────────────────────────────────────────────────
$d5 = [
    'state' => 'incompatible',
    'draft' => [],
    'required_literal' => [['field' => 'time', 'reason' => 'missing', 'hint' => 'x']],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [['code' => 'staff_busy', 'field' => 'datetime']],
];
$u5 = $builder->build($d5);
ac(
    'AC5 blocker gana sobre required_literal',
    $u5['cta'] === 'fix_blocker' && strpos($u5['text'], 'cita en ese horario') !== false,
    $u5['text']
);

// ─── AC6 ─────────────────────────────────────────────────────────────
$d6 = [
    'state' => 'incompatible',
    'draft' => [],
    'required_literal' => [],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [['code' => 'assignment_out_of_turn', 'field' => 'datetime']],
];
$u6 = $builder->build($d6);
ac(
    'AC6 assignment_out_of_turn',
    $u6['cta'] === 'fix_blocker' && stripos($u6['text'], 'amplio') !== false,
    $u6['text']
);

// ─── AC7 ─────────────────────────────────────────────────────────────
$d7 = [
    'state' => 'incompatible',
    'draft' => [],
    'required_literal' => [],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [['code' => 'foo_bar']],
];
$u7 = $builder->build($d7);
ac(
    'AC7 código desconocido',
    $u7['cta'] === 'fix_blocker' && strpos($u7['text'], 'foo_bar') !== false,
    $u7['text']
);

// ─── AC8 ─────────────────────────────────────────────────────────────
$d8 = [
    'state' => 'incompatible',
    'draft' => [],
    'required_literal' => [],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [
        ['code' => 'staff_busy'],
        ['code' => 'zone_busy'],
    ],
];
$u8 = $builder->build($d8);
ac(
    'AC8 dos blockers: solo el primero + cierre (sin sufijo genérico)',
    $u8['cta'] === 'fix_blocker'
        && strpos($u8['text'], 'cita en ese horario') !== false
        && strpos($u8['text'], 'Seguimos armando la misma cita') !== false
        && strpos($u8['text'], '(y hay otros conflictos)') === false,
    $u8['text']
);

// ─── AC9 ─────────────────────────────────────────────────────────────
$d9 = [
    'state' => 'needs_input',
    'draft' => [],
    'required_literal' => [['field' => 'date', 'reason' => 'past', 'hint' => '']],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u9 = $builder->build($d9);
ac(
    'AC9 past → collect_input',
    $u9['cta'] === 'collect_input' && (stripos($u9['text'], 'pasó') !== false || stripos($u9['text'], 'paso') !== false),
    $u9['text']
);

// ─── AC10 ────────────────────────────────────────────────────────────
$u10 = $builder->build([]);
ac(
    'AC10 draft_state [] defensivo',
    $u10['cta'] === 'noop' && $u10['text'] !== '' && empty($u10['highlights']),
    json_encode($u10, JSON_UNESCAPED_UNICODE)
);

// ─── AC11 ────────────────────────────────────────────────────────────
$d11 = [
    'state' => 'needs_input',
    'draft' => [
        'client'   => ['id' => 7, 'nombre' => 'Roberto Tejada', 'telefono' => '1', 'correo' => ''],
        'service'  => ['id' => 3, 'name' => 'Cejas', 'duration_minutes' => 0, 'price' => 0],
        'staff'    => ['id' => 5, 'name' => 'Anahí Temoltzin'],
        'zone'     => ['id' => 2, 'name' => 'Consultorio 2'],
        'datetime' => null,
        'duration' => ['minutes' => 60, 'source' => 'fallback'],
        'assignment' => null,
    ],
    'required_literal' => [['field' => 'time', 'reason' => 'missing', 'hint' => '']],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u11 = $builder->build($d11);
$hl11 = $u11['highlights'] ?? [];
$has4 = has_highlight($u11, 'cliente') && has_highlight($u11, 'servicio')
    && has_highlight($u11, 'profesional') && has_highlight($u11, 'zona');
ac(
    'AC11 highlights 4 entidades + draft_echo.client',
    $u11['cta'] === 'collect_input' && $has4 && ($u11['draft_echo']['client'] ?? null) === 'Roberto Tejada',
    json_encode($u11, JSON_UNESCAPED_UNICODE)
);

// ─── AC12 ────────────────────────────────────────────────────────────
$d12 = [
    'state' => 'needs_input',
    'draft' => [
        'client' => null,
        'service' => ['id' => 3, 'name' => 'X', 'duration_minutes' => 30, 'price' => 0],
        'staff' => null,
        'zone' => null,
        'datetime' => null,
        'duration' => ['minutes' => 30, 'source' => 'service'],
        'assignment' => null,
    ],
    'required_literal' => [['field' => 'time', 'reason' => 'missing', 'hint' => '']],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u12 = $builder->build($d12);
ac(
    'AC12 duración en highlights (source service)',
    has_highlight($u12, 'duración', '30 min'),
    json_encode($u12['highlights'], JSON_UNESCAPED_UNICODE)
);

// ─── AC13 ────────────────────────────────────────────────────────────
$d13 = [
    'state' => 'ready_for_confirmation',
    'draft' => [
        'client' => null,
        'service' => ['id' => 3, 'name' => 'X', 'duration_minutes' => 0, 'price' => 0],
        'staff' => ['id' => 1, 'name' => 'S'],
        'zone' => ['id' => 1, 'name' => 'Z'],
        'datetime' => ['local_date' => '2026-04-21', 'local_time' => '09:00:00', 'local_datetime' => '', 'timezone' => 'UTC'],
        'duration' => ['minutes' => 30, 'source' => 'fallback'],
        'assignment' => null,
    ],
    'required_literal' => [],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u13 = $builder->build($d13);
ac('AC13 fallback sin duración en highlights', !has_highlight($u13, 'duración'), json_encode($u13['highlights'], JSON_UNESCAPED_UNICODE));

// ─── AC14 ────────────────────────────────────────────────────────────
$d14 = [
    'state' => 'needs_confirmation_of_proposals',
    'draft' => [],
    'required_literal' => [],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u14 = $builder->build($d14);
ac(
    'AC14 confirm_heuristics',
    $u14['cta'] === 'confirm_heuristics' && stripos($u14['text'], 'confirmar') !== false,
    $u14['text']
);

// ─── AC16 collect_input multi: cliente + servicio + hora (sin usar solo el primer hint) ─
$d16 = [
    'state' => 'needs_input',
    'draft' => [],
    'required_literal' => [
        ['field' => 'client', 'reason' => 'missing', 'hint' => 'indica el nombre del cliente'],
        ['field' => 'service', 'reason' => 'missing', 'hint' => 'indica qué servicio'],
        ['field' => 'time', 'reason' => 'missing'],
    ],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u16 = $builder->build($d16);
$exp16 = 'Para continuar, indícame cliente, servicio y hora.';
ac(
    'AC16 collect_input multi agrupa tres campos',
    $u16['cta'] === 'collect_input' && $u16['text'] === $exp16,
    $u16['text']
);

// ─── AC17 collect_input fecha + hora (copy dedicado) ──────────────────
$d17 = [
    'state' => 'needs_input',
    'draft' => [],
    'required_literal' => [
        ['field' => 'time', 'reason' => 'missing'],
        ['field' => 'date', 'reason' => 'missing'],
    ],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u17 = $builder->build($d17);
$exp17 = 'Ya casi está. Solo me faltan fecha y hora.';
ac(
    'AC17 collect_input fecha+hora',
    $u17['cta'] === 'collect_input' && $u17['text'] === $exp17,
    $u17['text']
);

// ─── AC18 collect_input dos campos (no fecha/hora) ───────────────────
$d18 = [
    'state' => 'needs_input',
    'draft' => [],
    'required_literal' => [
        ['field' => 'staff', 'reason' => 'missing', 'hint' => 'indica con qué profesional'],
        ['field' => 'zone', 'reason' => 'missing', 'hint' => 'indica en qué zona'],
    ],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u18 = $builder->build($d18);
$exp18 = 'Compárteme profesional y zona para continuar.';
ac(
    'AC18 collect_input dos campos Compárteme…',
    $u18['cta'] === 'collect_input' && $u18['text'] === $exp18,
    $u18['text']
);

// ─── AC19 ambiguous + missing: CTA sigue pick_ambiguous ───────────────
$d19 = [
    'state' => 'needs_input',
    'draft' => [],
    'required_literal' => [
        ['field' => 'client', 'reason' => 'ambiguous', 'hint' => '', 'candidates' => [['id' => 1, 'nombre' => 'A']]],
        ['field' => 'time', 'reason' => 'missing'],
    ],
    'confirmable_heuristics' => [],
    'proposals'              => [],
    'blockers'               => [],
];
$u19 = $builder->build($d19);
ac(
    'AC19 ambiguous domina sobre missing',
    $u19['cta'] === 'pick_ambiguous',
    json_encode($u19, JSON_UNESCAPED_UNICODE)
);

// ─── AC15 idempotencia estructural ───────────────────────────────────
$a = $builder->build($d1);
$b = $builder->build($d1);
ac('AC15 idempotencia', $a === $b, json_encode($a !== $b));

echo "\n{$passed}/{$total} OK\n";
exit($passed === $total ? 0 : 1);
