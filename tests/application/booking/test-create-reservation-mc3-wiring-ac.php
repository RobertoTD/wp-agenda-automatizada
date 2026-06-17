<?php
/**
 * AC MC3 — CreateReservationUseCase wires EnsureAppointmentConfirmationTaskUseCase.
 *
 * Ejecutar: php tests/application/booking/test-create-reservation-mc3-wiring-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

$plugin_root = dirname(__DIR__, 3);

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

// ─── WordPress / service stubs ───────────────────────────────────

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $message;

        public function __construct($code = '', $message = '') {
            $this->message = (string) $message;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $key === 'aa_timezone' ? 'America/Mexico_City' : $default;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim((string) $str);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return trim((string) $email);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return (string) $url;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-06-20 10:00:00' : time();
    }
}

if (!function_exists('aa_normalize_telefono')) {
    function aa_normalize_telefono($raw) {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        return $digits !== '' ? $digits : new WP_Error('invalid', 'Teléfono inválido');
    }
}

if (!class_exists('ClienteService')) {
    class ClienteService {
        public static function getOrCreate(array $data) {
            return 9001;
        }
    }
}

/**
 * Stub mínimo de $wpdb para el camino feliz de creación (servicio fixed::).
 */
final class Mc3_Test_Wpdb_Stub {
    public $prefix = 'wp_';
    public $last_error = '';
    public $insert_id = 4242;
    public $insert_calls = 0;
    public $insert_should_fail = false;
    public $insert_id_missing = false;

    public function insert($table, $data) {
        $this->insert_calls++;

        return $this->insert_should_fail ? false : true;
    }

    public function get_var($query) {
        return null;
    }

    public function prepare($query, ...$args) {
        return $query;
    }
}

function mc3_valid_input(): array {
    return [
        'servicio' => 'fixed::Consulta',
        'fecha'    => '2026-06-20T16:30:00.000Z',
        'nombre'   => 'Paciente Test',
        'telefono' => '5551234567',
        'correo'   => 'test@example.com',
        'duracion' => 60,
    ];
}

function mc3_invoke_ensure(CreateReservationUseCase $use_case, int $reservation_id): void {
    $ref = new ReflectionMethod(CreateReservationUseCase::class, 'ensure_confirmation_task_after_create');
    $ref->setAccessible(true);
    $ref->invoke($use_case, $reservation_id);
}

function mc3_capture_error_log(callable $callback): string {
    $log_file = tempnam(sys_get_temp_dir(), 'mc3log');
    $previous = ini_get('error_log');
    ini_set('error_log', $log_file);

    try {
        $callback();
    } finally {
        ini_set('error_log', $previous !== false ? $previous : '');
    }

    $contents = is_readable($log_file) ? (string) file_get_contents($log_file) : '';
    @unlink($log_file);

    return $contents;
}

require_once $plugin_root . '/includes/application/booking/CreateReservationUseCase.php';

$create_src = file_get_contents($plugin_root . '/includes/application/booking/CreateReservationUseCase.php');
ac_assert('CreateReservationUseCase references EnsureAppointmentConfirmationTaskUseCase', strpos($create_src, 'EnsureAppointmentConfirmationTaskUseCase') !== false);
ac_assert('ensure hook runs before success return', strpos($create_src, 'ensure_confirmation_task_after_create($reserva_id)') !== false);

// ─── AC1: creación exitosa invoca ensurer con reservation_id correcto ─

$ensurer_calls = [];
$wpdb = new Mc3_Test_Wpdb_Stub();
$GLOBALS['wpdb'] = $wpdb;

$use_case = new CreateReservationUseCase(static function (int $reservation_id) use (&$ensurer_calls): void {
    $ensurer_calls[] = $reservation_id;
});

$result = $use_case->execute(mc3_valid_input());

ac_assert(
    'AC1: creación exitosa invoca ensurer',
    $ensurer_calls === [4242],
    'calls=' . json_encode($ensurer_calls)
);

// ─── AC7: contrato de salida sin cambios ─────────────────────────

$expected_keys = ['message', 'id', 'cliente_id', 'join_token'];
$data_keys = array_keys($result['data'] ?? []);
sort($expected_keys);
sort($data_keys);

ac_assert('AC7: success true tras creación', !empty($result['success']));
ac_assert('AC7: claves de data sin cambios', $expected_keys === $data_keys);
ac_assert('AC7: id de reserva en data', (int) ($result['data']['id'] ?? 0) === 4242);
ac_assert('AC7: message preservado', ($result['data']['message'] ?? '') === 'Reserva almacenada correctamente.');
ac_assert('AC7: cliente_id preservado', (int) ($result['data']['cliente_id'] ?? 0) === 9001);
ac_assert('AC7: join_token null para servicio fixed', array_key_exists('join_token', $result['data']) && $result['data']['join_token'] === null);

// ─── AC2: validación fallida no invoca ensurer ───────────────────

$ensurer_calls = [];
$wpdb = new Mc3_Test_Wpdb_Stub();
$GLOBALS['wpdb'] = $wpdb;

$use_case = new CreateReservationUseCase(static function (int $reservation_id) use (&$ensurer_calls): void {
    $ensurer_calls[] = $reservation_id;
});

$invalid = mc3_valid_input();
unset($invalid['nombre']);

$validation_result = $use_case->execute($invalid);

ac_assert('AC2: validación fallida devuelve error', empty($validation_result['success']));
ac_assert('AC2: ensurer no invocado en validación fallida', $ensurer_calls === []);

// ─── AC3: insert fallido no invoca ensurer ───────────────────────

$ensurer_calls = [];
$wpdb = new Mc3_Test_Wpdb_Stub();
$wpdb->insert_should_fail = true;
$wpdb->last_error = 'simulated insert failure';
$GLOBALS['wpdb'] = $wpdb;

$use_case = new CreateReservationUseCase(static function (int $reservation_id) use (&$ensurer_calls): void {
    $ensurer_calls[] = $reservation_id;
});

$insert_fail_result = $use_case->execute(mc3_valid_input());

ac_assert('AC3: insert fallido devuelve error', empty($insert_fail_result['success']));
ac_assert('AC3: ensurer no invocado tras insert fallido', $ensurer_calls === []);

// ─── AC4: fallo del ensurer no cambia resultado exitoso ──────────

$wpdb = new Mc3_Test_Wpdb_Stub();
$GLOBALS['wpdb'] = $wpdb;

$use_case = new CreateReservationUseCase(
    null,
    static function (int $reservation_id): array {
        return [
            'success' => false,
            'error'   => ['code' => 'task_persistence_failed', 'message' => 'fallo'],
        ];
    }
);

$soft_fail_result = $use_case->execute(mc3_valid_input());

ac_assert('AC4: creación sigue exitosa con fallo MC2', !empty($soft_fail_result['success']));
ac_assert('AC4: id preservado con fallo MC2', (int) ($soft_fail_result['data']['id'] ?? 0) === 4242);

// ─── AC5: reservation_not_confirmable es skip silencioso ─────────

$skip_log = mc3_capture_error_log(static function (): void {
    $use_case = new CreateReservationUseCase(
        null,
        static function (int $reservation_id): array {
            return [
                'success' => false,
                'error'   => ['code' => 'reservation_not_confirmable', 'message' => 'skip'],
            ];
        }
    );
    mc3_invoke_ensure($use_case, 777);
});

ac_assert('AC5: reservation_not_confirmable no genera log operativo', $skip_log === '');

// ─── AC6: fallo operativo se registra sin romper creación ────────

$operational_log = mc3_capture_error_log(static function (): void {
    $use_case = new CreateReservationUseCase(
        null,
        static function (int $reservation_id): array {
            return [
                'success' => false,
                'error'   => ['code' => 'action_persistence_failed', 'message' => 'fallo'],
            ];
        }
    );
    mc3_invoke_ensure($use_case, 888);
});

ac_assert(
    'AC6: fallo operativo registrado con reservation_id y código',
    strpos($operational_log, '888') !== false
    && strpos($operational_log, 'action_persistence_failed') !== false
);

$wpdb = new Mc3_Test_Wpdb_Stub();
$GLOBALS['wpdb'] = $wpdb;

$logged_use_case = new CreateReservationUseCase(
    null,
    static function (int $reservation_id): array {
        return [
            'success' => false,
            'error'   => ['code' => 'appointment_actions_list_not_ready', 'message' => 'fallo'],
        ];
    }
);

$logged_result = $logged_use_case->execute(mc3_valid_input());

ac_assert('AC6: creación exitosa pese a fallo operativo MC2', !empty($logged_result['success']));

// ─── Resumen ───────────────────────────────────────────────────────

echo "\n=== MC3 wiring: {$passed}/{$total} passed ===\n";

if ($failed !== []) {
    echo "Failed:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
