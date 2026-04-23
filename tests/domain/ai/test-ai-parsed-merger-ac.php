<?php
/**
 * Harness standalone de `AA_AI_Parsed_Merger`.
 *
 *   php tests/domain/ai/test-ai-parsed-merger-ac.php
 *
 * Sin PHPUnit. Sigue el patrón de tests/domain/booking/test-booking-reply-builder-ac.php.
 *
 * Organización:
 *   - AC1-AC13b: casos legacy del merger (shape canónica, intent,
 *     refinamiento, reemplazo, inmutabilidad, idempotencia). Siguen
 *     siendo válidos porque la rama "affected_fields vacío" del nuevo
 *     merger es idéntica a la semántica anterior.
 *   - P2A-P2F: casos nuevos del Paso 2 que ejercitan `affected_fields`
 *     como regla central: clear on affected+null, replace on
 *     affected+valor, preserve on not-affected, fallback cuando la
 *     señal no es válida, inmutabilidad por turno de las señales
 *     conversacionales.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Tests\Domain\AI
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/ai/class-aa-ai-parsed-merger.php';

$merger = new AA_AI_Parsed_Merger();
$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' — ' . $detail) . "\n";
}

function canonical_keys(): array {
    return [
        'intent',
        'client_name',
        'service_name',
        'staff_name',
        'zone_name',
        'date_text',
        'time_text',
        'notes',
        'sub_intent',
        'affected_fields',
        'confidence',
    ];
}

function shape_ok(array $arr): bool {
    return array_keys($arr) === canonical_keys();
}

// ─── AC1 ─────────────────────────────────────────────────────────────
$current_full = [
    'intent'       => 'create_booking',
    'client_name'  => 'Ana',
    'service_name' => 'Corte',
    'staff_name'   => 'Juan',
    'zone_name'    => 'Sala 1',
    'date_text'    => 'mañana',
    'time_text'    => '4pm',
    'notes'        => 'urgente',
];
$m1 = $merger->merge(null, $current_full);
ac(
    'AC1 previous=null + current completo → merged == current normalizado',
    shape_ok($m1)
        && $m1['intent']       === 'create_booking'
        && $m1['client_name']  === 'Ana'
        && $m1['service_name'] === 'Corte'
        && $m1['staff_name']   === 'Juan'
        && $m1['zone_name']    === 'Sala 1'
        && $m1['date_text']    === 'mañana'
        && $m1['time_text']    === '4pm'
        && $m1['notes']        === 'urgente'
        && $m1['sub_intent']   === 'other'
        && $m1['affected_fields'] === []
        && $m1['confidence']   === null,
    json_encode($m1, JSON_UNESCAPED_UNICODE)
);

// ─── AC2 ─────────────────────────────────────────────────────────────
$previous_full = [
    'intent'       => 'create_booking',
    'client_name'  => 'Ana',
    'service_name' => 'Corte',
    'staff_name'   => 'Juan',
    'zone_name'    => 'Sala 1',
    'date_text'    => 'mañana',
    'time_text'    => '4pm',
    'notes'        => null,
];
$m2 = $merger->merge($previous_full, null);
ac(
    'AC2 previous completo + current=null → merged == previous normalizado + señales default',
    shape_ok($m2)
        && $m2['intent']       === 'create_booking'
        && $m2['client_name']  === 'Ana'
        && $m2['service_name'] === 'Corte'
        && $m2['staff_name']   === 'Juan'
        && $m2['zone_name']    === 'Sala 1'
        && $m2['date_text']    === 'mañana'
        && $m2['time_text']    === '4pm'
        && $m2['notes']        === null
        && $m2['sub_intent']   === 'other'
        && $m2['affected_fields'] === []
        && $m2['confidence']   === null,
    json_encode($m2, JSON_UNESCAPED_UNICODE)
);

// ─── AC3 ─────────────────────────────────────────────────────────────
$previous3 = [
    'intent'      => 'create_booking',
    'client_name' => 'Ana',
    'date_text'   => 'mañana',
];
$current3 = [
    'intent'      => 'unknown',
    'time_text'   => '4pm',
];
$m3 = $merger->merge($previous3, $current3);
ac(
    'AC3 refinamiento parcial → conserva client/date previos + agrega time',
    $m3['intent']       === 'create_booking'
        && $m3['client_name']  === 'Ana'
        && $m3['date_text']    === 'mañana'
        && $m3['time_text']    === '4pm'
        && $m3['service_name'] === null
        && $m3['staff_name']   === null
        && $m3['zone_name']    === null
        && $m3['notes']        === null,
    json_encode($m3, JSON_UNESCAPED_UNICODE)
);

// ─── AC4 ─────────────────────────────────────────────────────────────
$m4 = $merger->merge(['time_text' => '3pm'], ['time_text' => '4pm']);
ac('AC4 reemplazo de hora previa', $m4['time_text'] === '4pm', json_encode($m4, JSON_UNESCAPED_UNICODE));

// ─── AC5 ─────────────────────────────────────────────────────────────
$m5 = $merger->merge(['client_name' => 'Ana'], ['client_name' => 'Ana Pérez Gómez']);
ac('AC5 desambiguación de cliente', $m5['client_name'] === 'Ana Pérez Gómez', json_encode($m5, JSON_UNESCAPED_UNICODE));

// ─── AC6 ─────────────────────────────────────────────────────────────
$m6 = $merger->merge(['client_name' => 'Ana'], ['client_name' => '   ']);
ac('AC6 string en blanco no pisa (affected_fields vacío)', $m6['client_name'] === 'Ana', json_encode($m6, JSON_UNESCAPED_UNICODE));

// ─── AC7 ─────────────────────────────────────────────────────────────
$m7 = $merger->merge(
    ['intent' => 'create_booking', 'client_name' => 'Ana'],
    ['intent' => 'unknown',        'time_text'   => '4pm']
);
ac(
    'AC7 intent unknown del current preserva intent previo',
    $m7['intent'] === 'create_booking'
        && $m7['client_name'] === 'Ana'
        && $m7['time_text']   === '4pm',
    json_encode($m7, JSON_UNESCAPED_UNICODE)
);

// ─── AC8 ─────────────────────────────────────────────────────────────
$m8 = $merger->merge(
    ['intent' => 'create_booking'],
    ['intent' => 'find_client', 'client_name' => 'Ana']
);
ac(
    'AC8 reclasificación genuina respetada',
    $m8['intent'] === 'find_client' && $m8['client_name'] === 'Ana',
    json_encode($m8, JSON_UNESCAPED_UNICODE)
);

// ─── AC9 ─────────────────────────────────────────────────────────────
$m9 = $merger->merge(null, null);
$ok9 = shape_ok($m9)
    && $m9['intent']       === 'unknown'
    && $m9['client_name']  === null
    && $m9['service_name'] === null
    && $m9['staff_name']   === null
    && $m9['zone_name']    === null
    && $m9['date_text']    === null
    && $m9['time_text']    === null
    && $m9['notes']        === null
    && $m9['sub_intent']   === 'other'
    && $m9['affected_fields'] === []
    && $m9['confidence']   === null;
ac('AC9 ambos null → 11 claves canónicas con defaults seguros', $ok9, json_encode($m9, JSON_UNESCAPED_UNICODE));

// ─── AC10 ────────────────────────────────────────────────────────────
$m10 = $merger->merge(
    ['client_name' => 'Ana', 'extra_field' => 'x', 'foo' => ['bar']],
    ['time_text'   => '4pm', 'another'     => 'y']
);
ac(
    'AC10 claves extra ignoradas en la salida',
    array_keys($m10) === canonical_keys()
        && !array_key_exists('extra_field', $m10)
        && !array_key_exists('foo', $m10)
        && !array_key_exists('another', $m10)
        && $m10['client_name'] === 'Ana'
        && $m10['time_text']   === '4pm',
    json_encode($m10, JSON_UNESCAPED_UNICODE)
);

// ─── AC11 ────────────────────────────────────────────────────────────
$m11 = $merger->merge(['client_name' => 'Ana'], ['client_name' => 123]);
ac('AC11 int no-string en current preserva previous', $m11['client_name'] === 'Ana', json_encode($m11, JSON_UNESCAPED_UNICODE));

$m11b = $merger->merge(['service_name' => 'Corte'], ['service_name' => ['no', 'string']]);
ac('AC11b array no-string en current preserva previous', $m11b['service_name'] === 'Corte', json_encode($m11b, JSON_UNESCAPED_UNICODE));

// ─── AC12 ────────────────────────────────────────────────────────────
$p12 = ['intent' => 'create_booking', 'client_name' => 'Ana', 'date_text' => 'mañana'];
$c12 = ['intent' => 'unknown', 'time_text' => '4pm'];
$m12_a = $merger->merge($p12, $c12);
$m12_b = $merger->merge($p12, $m12_a);
ac('AC12 idempotencia: merge(p, m1) == m1', $m12_a == $m12_b, json_encode(['m1' => $m12_a, 'm2' => $m12_b], JSON_UNESCAPED_UNICODE));

$m12_c = $merger->merge($m12_a, null);
ac('AC12b idempotencia: merge(m1, null) == m1', $m12_a == $m12_c, json_encode(['m1' => $m12_a, 'm3' => $m12_c], JSON_UNESCAPED_UNICODE));

// ─── AC13 ────────────────────────────────────────────────────────────
$p13 = ['intent' => 'create_booking', 'client_name' => 'Ana', 'extra' => 'x'];
$c13 = ['intent' => 'unknown', 'time_text' => '4pm', 'foo' => 'y'];
$p13_before = serialize($p13);
$c13_before = serialize($c13);
$merger->merge($p13, $c13);
ac('AC13 inmutabilidad de previous',  serialize($p13) === $p13_before, 'previous fue mutado');
ac('AC13b inmutabilidad de current', serialize($c13) === $c13_before, 'current fue mutado');

// ════════════════════════════════════════════════════════════════════
// Paso 2: casos específicos de `affected_fields` + `sub_intent`
// ════════════════════════════════════════════════════════════════════

// ─── P2A ─────────────────────────────────────────────────────────────
// "quiero cambiar el servicio" → affected_fields=["service"], service_name=null
// Antes del Paso 2 esto conservaba "cejas" por inercia. Ahora debe limpiar.
$prev_a = [
    'intent'       => 'create_booking',
    'client_name'  => 'Roberto',
    'service_name' => 'cejas',
    'staff_name'   => 'Adrian',
    'zone_name'    => 'Consultorio 2',
    'date_text'    => '2026-04-23',
    'time_text'    => '11:00',
];
$curr_a = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['service'],
    'service_name'    => null,
];
$mA = $merger->merge($prev_a, $curr_a);
ac(
    'P2A modify_fields + affected=[service] + current null → service_name limpiado',
    $mA['service_name'] === null
        && $mA['client_name'] === 'Roberto'
        && $mA['staff_name']  === 'Adrian'
        && $mA['zone_name']   === 'Consultorio 2'
        && $mA['date_text']   === '2026-04-23'
        && $mA['time_text']   === '11:00'
        && $mA['intent']      === 'create_booking'
        && $mA['sub_intent']  === 'modify_fields'
        && $mA['affected_fields'] === ['service'],
    json_encode($mA, JSON_UNESCAPED_UNICODE)
);

// ─── P2B ─────────────────────────────────────────────────────────────
// "mejor para consulta general" → affected_fields=["service"], service_name="consulta general"
// Debe reemplazar el valor previo.
$curr_b = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['service'],
    'service_name'    => 'consulta general',
];
$mB = $merger->merge($prev_a, $curr_b);
ac(
    'P2B modify_fields + affected=[service] + current con valor → service_name reemplazado',
    $mB['service_name'] === 'consulta general'
        && $mB['client_name'] === 'Roberto'
        && $mB['staff_name']  === 'Adrian'
        && $mB['zone_name']   === 'Consultorio 2'
        && $mB['sub_intent']  === 'modify_fields'
        && $mB['affected_fields'] === ['service'],
    json_encode($mB, JSON_UNESCAPED_UNICODE)
);

// ─── P2C ─────────────────────────────────────────────────────────────
// Draft completo + turno que cambia solo la zona. Resto se preserva.
$prev_c = [
    'intent'       => 'create_booking',
    'client_name'  => 'Roberto',
    'service_name' => 'cejas',
    'staff_name'   => 'Adrian',
    'zone_name'    => 'Consultorio 2',
    'date_text'    => '2026-04-23',
    'time_text'    => '11:00',
    'notes'        => 'urgente',
];
$curr_c = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['zone'],
    'zone_name'       => 'consultorio 1',
];
$mC = $merger->merge($prev_c, $curr_c);
ac(
    'P2C modify_fields + affected=[zone] → solo cambia zona, resto preservado',
    $mC['zone_name']    === 'consultorio 1'
        && $mC['client_name']  === 'Roberto'
        && $mC['service_name'] === 'cejas'
        && $mC['staff_name']   === 'Adrian'
        && $mC['date_text']    === '2026-04-23'
        && $mC['time_text']    === '11:00'
        && $mC['notes']        === 'urgente'
        && $mC['sub_intent']   === 'modify_fields'
        && $mC['affected_fields'] === ['zone'],
    json_encode($mC, JSON_UNESCAPED_UNICODE)
);

// ─── P2D ─────────────────────────────────────────────────────────────
// Draft parcial + fill_missing_fields con client y zone. Completa faltantes.
$prev_d = [
    'intent'       => 'create_booking',
    'service_name' => 'cejas',
    'staff_name'   => 'Anahí',
    'date_text'    => '2026-04-29',
    'time_text'    => '17:00',
];
$curr_d = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'fill_missing_fields',
    'affected_fields' => ['client', 'zone'],
    'client_name'     => 'armando hoyos',
    'zone_name'       => 'consultorio 3',
];
$mD = $merger->merge($prev_d, $curr_d);
ac(
    'P2D fill_missing_fields → completa client+zone, preserva el resto',
    $mD['client_name']  === 'armando hoyos'
        && $mD['zone_name']    === 'consultorio 3'
        && $mD['service_name'] === 'cejas'
        && $mD['staff_name']   === 'Anahí'
        && $mD['date_text']    === '2026-04-29'
        && $mD['time_text']    === '17:00'
        && $mD['sub_intent']   === 'fill_missing_fields'
        && $mD['affected_fields'] === ['client', 'zone'],
    json_encode($mD, JSON_UNESCAPED_UNICODE)
);

// ─── P2E ─────────────────────────────────────────────────────────────
// Current sin affected_fields válidos → fallback compatible (semántica legacy).
$prev_e = [
    'intent'       => 'create_booking',
    'client_name'  => 'Roberto',
    'service_name' => 'cejas',
    'staff_name'   => 'Adrian',
];
$curr_e = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'other',
    'affected_fields' => 'no-es-array',
    'time_text'       => '12:00',
];
$mE = $merger->merge($prev_e, $curr_e);
ac(
    'P2E affected_fields inválido → se normaliza a []; fallback preserva previous + añade current significativo',
    $mE['client_name']  === 'Roberto'
        && $mE['service_name'] === 'cejas'
        && $mE['staff_name']   === 'Adrian'
        && $mE['time_text']    === '12:00'
        && $mE['affected_fields'] === []
        && $mE['sub_intent']      === 'other',
    json_encode($mE, JSON_UNESCAPED_UNICODE)
);

// Extra P2E': aliases desconocidos en affected_fields se descartan silenciosamente.
$curr_e2 = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['service', 'unicornio', 'fecha_de_nacimiento'],
    'service_name'    => null,
];
$mE2 = $merger->merge($prev_a, $curr_e2);
ac(
    "P2E' aliases desconocidos se ignoran; el válido ('service') sí actúa",
    $mE2['service_name'] === null
        && $mE2['client_name'] === 'Roberto'
        && $mE2['affected_fields'] === ['service'],
    json_encode($mE2, JSON_UNESCAPED_UNICODE)
);

// ─── P2F ─────────────────────────────────────────────────────────────
// Las señales por turno (sub_intent, affected_fields, confidence) NUNCA
// se heredan del previous: siempre reflejan el current.
$prev_f = [
    'intent'          => 'create_booking',
    'client_name'     => 'Roberto',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['service'],
    'confidence'      => 0.9,
];
$curr_f = [
    'intent'       => 'create_booking',
    'service_name' => 'cejas',
];
$mF = $merger->merge($prev_f, $curr_f);
ac(
    'P2F señales por turno no se heredan del previous',
    $mF['sub_intent']      === 'other'
        && $mF['affected_fields'] === []
        && $mF['confidence']      === null
        && $mF['client_name']     === 'Roberto'
        && $mF['service_name']    === 'cejas',
    json_encode($mF, JSON_UNESCAPED_UNICODE)
);

// Extra P2F': confidence numérico del current se preserva.
$curr_f2 = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['time'],
    'confidence'      => 0.8,
    'time_text'       => '18:00',
];
$mF2 = $merger->merge($prev_f, $curr_f2);
ac(
    "P2F' confidence del current viaja al output",
    $mF2['confidence']  === 0.8
        && $mF2['sub_intent']   === 'modify_fields'
        && $mF2['time_text']    === '18:00',
    json_encode($mF2, JSON_UNESCAPED_UNICODE)
);

// ─── P2G ─────────────────────────────────────────────────────────────
// Campo NO afectado pero current emite valor significativo → current gana
// (el LLM reafirmando un dato es confiable aquí).
$prev_g = ['client_name' => 'Ana'];
$curr_g = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'fill_missing_fields',
    'affected_fields' => ['time'],
    'client_name'     => 'Ana Pérez',
    'time_text'       => '4pm',
];
$mG = $merger->merge($prev_g, $curr_g);
ac(
    'P2G campo fuera de affected pero con valor significativo en current → current pisa',
    $mG['client_name'] === 'Ana Pérez'
        && $mG['time_text']   === '4pm',
    json_encode($mG, JSON_UNESCAPED_UNICODE)
);

// ─── P2H ─────────────────────────────────────────────────────────────
// Campo afectado con valor en blanco (solo whitespace) → se trata como
// "no significativo" y por tanto LIMPIA el campo. Evita que strings
// vacíos del LLM dejen un valor falso-positivo en el draft.
$prev_h = ['service_name' => 'cejas'];
$curr_h = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['service'],
    'service_name'    => '   ',
];
$mH = $merger->merge($prev_h, $curr_h);
ac(
    'P2H affected + current whitespace → limpia (equivalente a null)',
    $mH['service_name'] === null,
    json_encode($mH, JSON_UNESCAPED_UNICODE)
);

// ─── P2I ─────────────────────────────────────────────────────────────
// Idempotencia extendida con señales: merge(p, m1) == m1 cuando m1 ya
// trae señales normalizadas.
$p_i = [
    'intent'          => 'create_booking',
    'client_name'     => 'Ana',
    'sub_intent'      => 'other',
    'affected_fields' => [],
    'confidence'      => null,
];
$c_i = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['service'],
    'service_name'    => 'corte',
];
$mI_a = $merger->merge($p_i, $c_i);
$mI_b = $merger->merge($p_i, $mI_a);
ac(
    'P2I idempotencia incluyendo señales: merge(p, m1) == m1',
    $mI_a == $mI_b,
    json_encode(['m1' => $mI_a, 'm2' => $mI_b], JSON_UNESCAPED_UNICODE)
);

// ─── P2J ─────────────────────────────────────────────────────────────
// Inmutabilidad aún con entradas que traen las 3 señales nuevas.
$p_j = [
    'intent'          => 'create_booking',
    'client_name'     => 'Ana',
    'sub_intent'      => 'modify_fields',
    'affected_fields' => ['service'],
    'confidence'      => 0.9,
];
$c_j = [
    'intent'          => 'create_booking',
    'sub_intent'      => 'fill_missing_fields',
    'affected_fields' => ['time'],
    'time_text'       => '4pm',
    'confidence'      => 0.7,
];
$p_j_before = serialize($p_j);
$c_j_before = serialize($c_j);
$merger->merge($p_j, $c_j);
ac('P2J inmutabilidad de previous con señales', serialize($p_j) === $p_j_before, 'previous fue mutado');
ac('P2Jb inmutabilidad de current con señales', serialize($c_j) === $c_j_before, 'current fue mutado');

// ─── Resumen ─────────────────────────────────────────────────────────
echo "\nPASSED {$passed} / {$total}\n";
exit($passed === $total ? 0 : 1);
