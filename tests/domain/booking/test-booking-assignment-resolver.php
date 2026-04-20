<?php
/**
 * Unit tests del `AA_Booking_Assignment_Resolver` (Paso 2).
 *
 * Harness 100% standalone: no carga WordPress, no toca BD. Define:
 *   - Constante `ABSPATH` para satisfacer el guard de carga.
 *   - Stub estático de `AssignmentsModel` con fixtures configurables.
 *
 * Corre los 6 acceptance criteria (AC1..AC6) del contrato definido en
 * `class-aa-booking-assignment-resolver.php`. Cada test imprime el
 * output real observado y un OK/FAIL contra el esperado. El objetivo es
 * poder ejecutar el contrato del resolver sin levantar WordPress.
 *
 * Ejecutar:
 *   php tests/domain/booking/test-booking-assignment-resolver.php
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Tests\Domain\Booking
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

// ─── Stub de AssignmentsModel ────────────────────────────────────────

class AssignmentsModel {
    /** @var array<int, array<string,mixed>> */
    public static $fixtures_overlapping = [];

    /** @var array<int, array<int, array{id:int,name:string}>> */
    public static $fixtures_services = [];

    public static function reset(): void {
        self::$fixtures_overlapping = [];
        self::$fixtures_services = [];
    }

    public static function get_active_assignments_overlapping_in_area(
        $date,
        $start_time,
        $end_time,
        $service_area_id
    ) {
        $out = [];
        foreach (self::$fixtures_overlapping as $row) {
            if (
                (int) $row['service_area_id'] !== (int) $service_area_id
                || (string) $row['assignment_date'] !== (string) $date
            ) {
                continue;
            }
            // Replica del predicado SQL: start < end && end > start.
            if ((string) $start_time < (string) $row['end_time']
                && (string) $end_time > (string) $row['start_time']) {
                $out[] = [
                    'id'         => (int) $row['id'],
                    'staff_id'   => (int) $row['staff_id'],
                    'start_time' => (string) $row['start_time'],
                    'end_time'   => (string) $row['end_time'],
                ];
            }
        }
        return $out;
    }

    public static function get_assignment_services($assignment_id) {
        $id = (int) $assignment_id;
        return self::$fixtures_services[$id] ?? [];
    }
}

// ─── Carga del sujeto bajo prueba ────────────────────────────────────

require_once __DIR__ . '/../../../includes/domain/booking/class-aa-booking-assignment-resolver.php';

// ─── Harness ─────────────────────────────────────────────────────────

$total   = 0;
$passed  = 0;
$failed  = [];

/**
 * @param string $label
 * @param array  $actual
 * @param array  $expected
 */
function assert_equals_array(string $label, array $actual, array $expected): void {
    global $total, $passed, $failed;
    $total++;

    ksort_recursive($actual);
    ksort_recursive($expected);

    $a = json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $e = json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($a === $e) {
        $passed++;
        echo "\n[ OK ] {$label}\n";
        echo "  output:\n" . indent($a, '    ') . "\n";
    } else {
        $failed[] = $label;
        echo "\n[FAIL] {$label}\n";
        echo "  expected:\n" . indent($e, '    ') . "\n";
        echo "  actual:\n"   . indent($a, '    ') . "\n";
    }
}

function ksort_recursive(array &$arr): void {
    if (array_keys($arr) !== range(0, count($arr) - 1)) {
        ksort($arr);
    }
    foreach ($arr as &$v) {
        if (is_array($v)) {
            ksort_recursive($v);
        }
    }
}

function indent(string $s, string $pad): string {
    $lines = explode("\n", $s);
    return $pad . implode("\n{$pad}", $lines);
}

// ─── Fecha base para todos los AC ────────────────────────────────────

$date = '2026-04-20';

// ─── AC1: containing + servicio match → reuse ────────────────────────

AssignmentsModel::reset();
AssignmentsModel::$fixtures_overlapping = [[
    'id'              => 101,
    'staff_id'        => 5,
    'service_area_id' => 2,
    'assignment_date' => $date,
    'start_time'      => '18:00:00',
    'end_time'        => '19:00:00',
]];
AssignmentsModel::$fixtures_services = [
    101 => [['id' => 3, 'name' => 'Cejas']],
];

$r = (new AA_Booking_Assignment_Resolver())->resolve([
    'staff_id'         => 5,
    'service_area_id'  => 2,
    'service_id'       => 3,
    'start_datetime'   => "{$date} 18:00:00",
    'duration_minutes' => 60,
]);

assert_equals_array('AC1: reuse por service_match', $r, [
    'mode'             => 'reuse',
    'assignment_id'    => 101,
    'rationale'        => 'service_match',
    'pending_creation' => null,
    'conflict'         => null,
]);

// ─── AC2: sin overlaps → create_new ──────────────────────────────────

AssignmentsModel::reset();

$r = (new AA_Booking_Assignment_Resolver())->resolve([
    'staff_id'         => 5,
    'service_area_id'  => 3,
    'service_id'       => 3,
    'start_datetime'   => "{$date} 15:00:00",
    'duration_minutes' => 60,
]);

assert_equals_array('AC2: create_new por no_compatible_found', $r, [
    'mode'             => 'create_new',
    'assignment_id'    => null,
    'rationale'        => 'no_compatible_found',
    'pending_creation' => [
        'staff_id'         => 5,
        'service_area_id'  => 3,
        'service_id'       => 3,
        'start_datetime'   => "{$date} 15:00:00",
        'duration_minutes' => 60,
    ],
    'conflict'         => null,
]);

// ─── AC3: overlap same-staff pero no contiene → out_of_turn ─────────

AssignmentsModel::reset();
AssignmentsModel::$fixtures_overlapping = [[
    'id'              => 101,
    'staff_id'        => 5,
    'service_area_id' => 2,
    'assignment_date' => $date,
    'start_time'      => '18:00:00',
    'end_time'        => '19:00:00',
]];

$r = (new AA_Booking_Assignment_Resolver())->resolve([
    'staff_id'         => 5,
    'service_area_id'  => 2,
    'service_id'       => 3,
    'start_datetime'   => "{$date} 18:45:00",
    'duration_minutes' => 60,
]);

assert_equals_array('AC3: unresolved out_of_turn', $r, [
    'mode'             => 'unresolved',
    'assignment_id'    => null,
    'rationale'        => 'existing_overlap_incompatible',
    'pending_creation' => null,
    'conflict'         => [
        'code'   => 'out_of_turn',
        'detail' => [
            'assignment_id' => 101,
            'start_time'    => '18:00:00',
            'end_time'      => '19:00:00',
        ],
    ],
]);

// ─── AC4: containing pero servicio no ofrecido → service_not_offered ─

AssignmentsModel::reset();
AssignmentsModel::$fixtures_overlapping = [[
    'id'              => 101,
    'staff_id'        => 5,
    'service_area_id' => 2,
    'assignment_date' => $date,
    'start_time'      => '18:00:00',
    'end_time'        => '19:00:00',
]];
AssignmentsModel::$fixtures_services = [
    101 => [['id' => 3, 'name' => 'Cejas']],
];

// Nota: el prompt original del AC4 planteaba `duration_minutes: 60`
// con slot 18:30-19:30 contra assignment 18:00-19:00. Ese slot NO
// está contenido en el turno (19:30 > 19:00), por lo que caería en
// `out_of_turn`, no en `service_not_offered`. Ajustamos a 30 min
// (18:30-19:00 SÍ contenido) para que la intención del AC —"turno
// existente contiene el slot pero no ofrece el servicio pedido"—
// sea satisfacible y se valide el branch correcto del resolver.
$r = (new AA_Booking_Assignment_Resolver())->resolve([
    'staff_id'         => 5,
    'service_area_id'  => 2,
    'service_id'       => 7,
    'start_datetime'   => "{$date} 18:30:00",
    'duration_minutes' => 30,
]);

assert_equals_array('AC4: unresolved service_not_offered', $r, [
    'mode'             => 'unresolved',
    'assignment_id'    => null,
    'rationale'        => 'existing_overlap_incompatible',
    'pending_creation' => null,
    'conflict'         => [
        'code'   => 'service_not_offered',
        'detail' => [
            'assignment_id'      => 101,
            'start_time'         => '18:00:00',
            'end_time'           => '19:00:00',
            'available_services' => [['id' => 3, 'name' => 'Cejas']],
        ],
    ],
]);

// ─── AC5: input inválido (staff_id 0) → missing_inputs sin consulta ──

AssignmentsModel::reset();
AssignmentsModel::$fixtures_overlapping = [[
    'id'              => 999,
    'staff_id'        => 5,
    'service_area_id' => 2,
    'assignment_date' => $date,
    'start_time'      => '18:00:00',
    'end_time'        => '19:00:00',
]];

$r = (new AA_Booking_Assignment_Resolver())->resolve([
    'staff_id'         => 0,
    'service_area_id'  => 2,
    'service_id'       => 3,
    'start_datetime'   => "{$date} 18:00:00",
    'duration_minutes' => 60,
]);

assert_equals_array('AC5: unresolved missing_inputs', $r, [
    'mode'             => 'unresolved',
    'assignment_id'    => null,
    'rationale'        => 'missing_inputs',
    'pending_creation' => null,
    'conflict'         => null,
]);

// ─── AC6: overlap solo de OTRO staff → create_new ────────────────────

AssignmentsModel::reset();
AssignmentsModel::$fixtures_overlapping = [[
    'id'              => 201,
    'staff_id'        => 9,
    'service_area_id' => 2,
    'assignment_date' => $date,
    'start_time'      => '18:00:00',
    'end_time'        => '19:00:00',
]];

$r = (new AA_Booking_Assignment_Resolver())->resolve([
    'staff_id'         => 5,
    'service_area_id'  => 2,
    'service_id'       => 3,
    'start_datetime'   => "{$date} 18:30:00",
    'duration_minutes' => 60,
]);

assert_equals_array('AC6: create_new (ignora overlap de otro staff)', $r, [
    'mode'             => 'create_new',
    'assignment_id'    => null,
    'rationale'        => 'no_compatible_found',
    'pending_creation' => [
        'staff_id'         => 5,
        'service_area_id'  => 2,
        'service_id'       => 3,
        'start_datetime'   => "{$date} 18:30:00",
        'duration_minutes' => 60,
    ],
    'conflict'         => null,
]);

// ─── Resumen ─────────────────────────────────────────────────────────

echo "\n────────────────────────────────────────\n";
echo "Resultado: {$passed}/{$total} pasaron.\n";
if (!empty($failed)) {
    echo "Fallaron:\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}
exit(0);
