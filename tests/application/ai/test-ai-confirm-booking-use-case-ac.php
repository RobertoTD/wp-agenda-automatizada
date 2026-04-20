<?php
/**
 * Harness standalone de AA_AI_Confirm_Booking_Use_Case (AC1–AC6).
 *
 * Uso:
 *   php tests/application/ai/test-ai-confirm-booking-use-case-ac.php
 *
 * Stubs en el mismo archivo: AssignmentsModel, CreateReservationUseCase,
 * aa_get_cliente_by_id, confirm_backend_service_confirmar, get_option.
 */

if (!defined('ABSPATH')) { define('ABSPATH', __DIR__); }

// ─── Stubs WP mínimos ───────────────────────────────────────────────

if (!function_exists('get_option')) {
    function get_option($key, $default = false) { return $default; }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s) { return (string) $s; }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($s) { return (string) $s; }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($s) { return (string) $s; }
}
if (!function_exists('current_time')) {
    function current_time($type) { return date('Y-m-d H:i:s'); }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($v) { return false; }
}
if (!function_exists('error_log')) {
    // PHP ya la provee; protección por si un linter pide el estabilo.
}

// ─── Stubs de colaboradores ─────────────────────────────────────────

class CreateReservationUseCase {
    public static $should_succeed  = true;
    public static $last_input      = null;
    public static $next_id         = 5555;
    public static $error_message   = 'wpdb error';
    public static $error_detail    = 'fake detail';
    public static $call_count      = 0;

    public function execute(array $input): array {
        self::$call_count++;
        self::$last_input = $input;

        if (!self::$should_succeed) {
            return [
                'success' => false,
                'error'   => [
                    'message' => self::$error_message,
                    'detail'  => self::$error_detail,
                ],
            ];
        }

        return [
            'success' => true,
            'data'    => [
                'message'    => 'ok',
                'id'         => self::$next_id,
                'cliente_id' => (int) ($input['nombre'] ? 777 : 0),
                'join_token' => null,
            ],
        ];
    }
}

class AssignmentsModel {
    /** @var array<int,int> service_ids attached, in order */
    public static $pivot_calls = [];
    public static $create_mode = 'ok'; // 'ok'|'blocked_other_staff'|'false'
    public static $next_id     = 9001;
    public static $last_create_args = null;

    public static function create_assignment($data) {
        self::$last_create_args = $data;

        if (self::$create_mode === 'false') {
            return false;
        }
        if (self::$create_mode === 'blocked_other_staff') {
            return [
                'error'    => 'La zona ya está reservada por otro staff en ese horario',
                'reason'   => 'zone_reserved_for_other_staff',
                'conflict' => [
                    'assignment_id' => 123, 'staff_id' => 999,
                    'start_time' => '18:00:00', 'end_time' => '19:00:00',
                ],
            ];
        }

        $id = self::$next_id;
        return array_merge(['id' => $id], $data);
    }

    public static function add_assignment_service($assignment_id, $service_id) {
        self::$pivot_calls[] = [(int) $assignment_id, (int) $service_id];
        return true;
    }
}

function aa_get_cliente_by_id($id) {
    if ((int) $id === 777) {
        return (object) [
            'id'       => 777,
            'nombre'   => 'Roberto Tejada',
            'telefono' => '+5215512345678',
            'correo'   => 'roberto@example.test',
        ];
    }
    return null;
}

// Confirm backend — el include lo hace el controller, pero el use case solo
// usa `function_exists`. Declaramos la función global directamente.
$GLOBALS['__confirm_should_succeed'] = true;
function confirm_backend_service_confirmar($reserva_id) {
    return [
        'success' => !empty($GLOBALS['__confirm_should_succeed']),
        'message' => !empty($GLOBALS['__confirm_should_succeed']) ? 'ok' : 'backend down',
    ];
}

// ─── Load SUT ──────────────────────────────────────────────────────

require_once __DIR__ . '/../../../includes/application/ai/AI_Confirm_Booking_Use_Case.php';

// ─── Mini runner ────────────────────────────────────────────────────

$results = [];
function ac(string $label, bool $pass, $detail = null): void {
    global $results;
    $results[] = compact('label', 'pass', 'detail');
    printf("%s %s%s\n",
        $pass ? 'OK  ' : 'FAIL',
        $label,
        $pass ? '' : "  → " . (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE))
    );
}

function reset_fixtures(): void {
    CreateReservationUseCase::$should_succeed = true;
    CreateReservationUseCase::$last_input     = null;
    CreateReservationUseCase::$next_id        = 5555;
    CreateReservationUseCase::$call_count     = 0;
    AssignmentsModel::$pivot_calls            = [];
    AssignmentsModel::$create_mode            = 'ok';
    AssignmentsModel::$next_id                = 9001;
    AssignmentsModel::$last_create_args       = null;
    $GLOBALS['__confirm_should_succeed']      = true;
}

$base_input = [
    'client_id'        => 777,
    'service_id'       => 3,
    'staff_id'         => 5,
    'zone_id'          => 2,
    'start_datetime'   => '2026-04-25 18:30:00',
    'duration_minutes' => 60,
    'assignment_mode'  => 'reuse',
    'assignment_id'    => 101,
];

// ─── AC1: reuse feliz ───────────────────────────────────────────────
reset_fixtures();
$uc = new AA_AI_Confirm_Booking_Use_Case();
$r  = $uc->execute($base_input);
ac('AC1 reuse feliz',
    $r['status'] === 'ok'
    && $r['assignment_id'] === 101
    && $r['created_assignment'] === false
    && $r['reservation_id'] > 0
    && $r['confirmed'] === true
    && empty(AssignmentsModel::$pivot_calls),
    $r
);

// ─── AC2: create_new feliz ─────────────────────────────────────────
reset_fixtures();
$input       = $base_input;
$input['assignment_mode'] = 'create_new';
$input['assignment_id']   = 0;
$r = (new AA_AI_Confirm_Booking_Use_Case())->execute($input);
ac('AC2 create_new feliz',
    $r['status'] === 'ok'
    && $r['created_assignment'] === true
    && $r['assignment_id'] === 9001
    && $r['reservation_id'] > 0
    && count(AssignmentsModel::$pivot_calls) === 1
    && AssignmentsModel::$pivot_calls[0] === [9001, 3],
    $r
);

// ─── AC3: create_new bloqueada por otro staff ──────────────────────
reset_fixtures();
AssignmentsModel::$create_mode = 'blocked_other_staff';
$input = $base_input;
$input['assignment_mode'] = 'create_new';
$input['assignment_id']   = 0;
$r = (new AA_AI_Confirm_Booking_Use_Case())->execute($input);
ac('AC3 create_new bloqueada → stage:assignment',
    $r['status'] === 'error'
    && $r['stage']  === 'assignment'
    && CreateReservationUseCase::$call_count === 0
    && empty(AssignmentsModel::$pivot_calls),
    $r
);

// ─── AC4: input inválido (staff_id:0) ──────────────────────────────
reset_fixtures();
$input = $base_input;
$input['staff_id'] = 0;
$r = (new AA_AI_Confirm_Booking_Use_Case())->execute($input);
ac('AC4 input inválido → stage:input',
    $r['status'] === 'error'
    && $r['stage']  === 'input'
    && CreateReservationUseCase::$call_count === 0
    && AssignmentsModel::$last_create_args === null,
    $r
);

// ─── AC5: CreateReservationUseCase falla en modo create_new ────────
reset_fixtures();
CreateReservationUseCase::$should_succeed = false;
$input = $base_input;
$input['assignment_mode'] = 'create_new';
$input['assignment_id']   = 0;
$r = (new AA_AI_Confirm_Booking_Use_Case())->execute($input);
ac('AC5 reservation falla → stage:reservation; asignación queda creada',
    $r['status'] === 'error'
    && $r['stage']  === 'reservation'
    && AssignmentsModel::$last_create_args !== null
    && count(AssignmentsModel::$pivot_calls) === 1,
    $r
);

// ─── AC6: confirm falla → status:ok, confirmed:false ───────────────
reset_fixtures();
$GLOBALS['__confirm_should_succeed'] = false;
$r = (new AA_AI_Confirm_Booking_Use_Case())->execute($base_input);
ac('AC6 confirm falla → status:ok, confirmed:false',
    $r['status'] === 'ok'
    && $r['confirmed'] === false
    && $r['reservation_id'] > 0,
    $r
);

// ─── Resumen ────────────────────────────────────────────────────────

$ok   = count(array_filter($results, fn($x) => $x['pass']));
$tot  = count($results);
echo "\n{$ok}/{$tot} OK\n";
exit($ok === $tot ? 0 : 1);
