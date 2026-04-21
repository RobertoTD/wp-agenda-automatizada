<?php
/**
 * Harness standalone de `AA_AI_Parsed_Merger` (AC1–AC13).
 *
 *   php tests/domain/ai/test-ai-parsed-merger-ac.php
 *
 * Sin PHPUnit. Sigue el patrón de tests/domain/booking/test-booking-reply-builder-ac.php.
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
    return ['intent', 'client_name', 'service_name', 'staff_name', 'zone_name', 'date_text', 'time_text', 'notes'];
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
        && $m1['notes']        === 'urgente',
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
    'AC2 previous completo + current=null → merged == previous normalizado',
    shape_ok($m2)
        && $m2['intent']       === 'create_booking'
        && $m2['client_name']  === 'Ana'
        && $m2['service_name'] === 'Corte'
        && $m2['staff_name']   === 'Juan'
        && $m2['zone_name']    === 'Sala 1'
        && $m2['date_text']    === 'mañana'
        && $m2['time_text']    === '4pm'
        && $m2['notes']        === null,
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
ac('AC6 string en blanco no pisa', $m6['client_name'] === 'Ana', json_encode($m6, JSON_UNESCAPED_UNICODE));

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
    && $m9['intent'] === 'unknown'
    && $m9['client_name']  === null
    && $m9['service_name'] === null
    && $m9['staff_name']   === null
    && $m9['zone_name']    === null
    && $m9['date_text']    === null
    && $m9['time_text']    === null
    && $m9['notes']        === null;
ac('AC9 ambos null → 8 claves canónicas', $ok9, json_encode($m9, JSON_UNESCAPED_UNICODE));

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

// extra: array como valor
$m11b = $merger->merge(['service_name' => 'Corte'], ['service_name' => ['no', 'string']]);
ac('AC11b array no-string en current preserva previous', $m11b['service_name'] === 'Corte', json_encode($m11b, JSON_UNESCAPED_UNICODE));

// ─── AC12 ────────────────────────────────────────────────────────────
$p12 = ['intent' => 'create_booking', 'client_name' => 'Ana', 'date_text' => 'mañana'];
$c12 = ['intent' => 'unknown', 'time_text' => '4pm'];
$m12_a = $merger->merge($p12, $c12);
$m12_b = $merger->merge($p12, $m12_a);
ac('AC12 idempotencia: merge(p, m1) == m1', $m12_a == $m12_b, json_encode(['m1' => $m12_a, 'm2' => $m12_b], JSON_UNESCAPED_UNICODE));

// adicional: aplicar el merger a un merged contra null tampoco lo cambia
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

// ─── Resumen ─────────────────────────────────────────────────────────
echo "\nPASSED {$passed} / {$total}\n";
exit($passed === $total ? 0 : 1);
