<?php
/**
 * AC MC4 — ValidateUpcomingConfirmedPushAppointmentsUseCase.
 *
 * Ejecutar: php tests/application/appointments/test-validate-upcoming-confirmed-push-appointments-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        $options = [
            'aa_timezone' => 'America/Mexico_City',
            'aa_webhook_token' => 'webhook-token',
        ];
        return array_key_exists($key, $options) ? $options[$key] : $default;
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        return $known_string === $user_string;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public string $code;
        public string $message;
        public array $data;

        public function __construct($code = '', $message = '', $data = []) {
            $this->code = (string) $code;
            $this->message = (string) $message;
            $this->data = is_array($data) ? $data : [];
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private int $status;

        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = (int) $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }
    }
}

if (!class_exists('WP_REST_Controller')) {
    class WP_REST_Controller {}
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        public const CREATABLE = 'POST';
        public const READABLE = 'GET';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args) {
        $GLOBALS['aa_test_registered_routes'][] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
        ];
    }
}

final class AA_Test_Rest_Request {
    private array $params;
    private array $headers;

    public function __construct(array $params = [], array $headers = []) {
        $this->params = $params;
        $this->headers = $headers;
    }

    public function get_param($key) {
        return $this->params[$key] ?? null;
    }

    public function get_header($key) {
        return $this->headers[$key] ?? '';
    }
}

final class AA_Test_Wpdb {
    public string $prefix = 'wp_';
    public string $last_error = '';

    public function prepare($query, ...$args) {
        foreach ($args as $arg) {
            $query = preg_replace('/%d/', (string) (int) $arg, $query, 1);
        }
        return $query;
    }

    public function get_row($query) {
        if (strpos((string) $query, 'WHERE id = 123') === false) {
            return null;
        }

        return (object) [
            'id' => 123,
            'estado' => 'confirmed',
            'fecha' => '2026-07-09 15:00:00',
            'nombre' => 'Cliente de Prueba',
            'telefono' => '',
            'correo' => '',
            'servicio' => 'fixed::Consulta',
            'duracion' => 60,
            'assignment_id' => null,
        ];
    }
}

$plugin_root = dirname(__DIR__, 3);
$total = 0;
$passed = 0;
$failed = [];
$GLOBALS['aa_test_registered_routes'] = [];
global $wpdb;
$wpdb = new AA_Test_Wpdb();

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

require_once $plugin_root . '/includes/application/appointments/ValidateUpcomingConfirmedPushAppointmentsUseCase.php';
require_once $plugin_root . '/includes/controllers/WebhooksController.php';

$reservations = [
    123 => [
        'id' => 123,
        'estado' => 'confirmed',
        'fecha' => '2026-07-09 15:00:00',
        'nombre' => 'Cliente de Prueba',
        'telefono' => '',
        'correo' => '',
        'servicio' => 'svc-1',
        'duracion' => 60,
        'assignment_id' => null,
    ],
    124 => [
        'id' => 124,
        'estado' => 'cancelled',
        'fecha' => '2026-07-09 16:00:00',
        'nombre' => 'Cliente Cancelado',
        'telefono' => '',
        'correo' => '',
        'servicio' => 'fixed::Consulta',
        'duracion' => 60,
        'assignment_id' => null,
    ],
];

$use_case = new ValidateUpcomingConfirmedPushAppointmentsUseCase(
    static function (int $reservation_id) use (&$reservations): ?array {
        return $reservations[$reservation_id] ?? null;
    },
    static function (string $service_raw): string {
        return $service_raw === 'svc-1' ? 'Análisis Clínicos' : str_replace('fixed::', '', $service_raw);
    }
);

$result = $use_case->execute([
    ['appointment_id' => 123, 'expected_start' => '2026-07-09T21:00:00.000Z'],
    ['appointment_id' => 124, 'expected_start' => '2026-07-09T22:00:00.000Z'],
    ['appointment_id' => 999, 'expected_start' => '2026-07-09T23:00:00.000Z'],
]);

ac_assert('use case returns success', ($result['success'] ?? false) === true);
ac_assert('confirmed row is returned without requiring email', isset($result['valid']['123']));
ac_assert('current estado is returned', ($result['valid']['124']['estado'] ?? '') === 'cancelled');
ac_assert('appointment_start is ISO with offset', preg_match('/T15:00:00-06:00$/', (string) ($result['valid']['123']['appointment_start'] ?? '')) === 1);
ac_assert('customer_name is returned', ($result['valid']['123']['customer_name'] ?? '') === 'Cliente de Prueba');
ac_assert('service is legible', ($result['valid']['123']['service'] ?? '') === 'Análisis Clínicos');
ac_assert('not found is skipped', ($result['skipped']['999'] ?? '') === 'not_found');
ac_assert('email is not part of push validation contract', !array_key_exists('email', $result['valid']['123']));

$controller = new Webhooks_Controller();
$controller->register_routes();

$registered = array_filter(
    $GLOBALS['aa_test_registered_routes'],
    static function ($route) {
        return $route['route'] === '/webhooks/upcoming-confirmed-push/validate';
    }
);

ac_assert('REST route registered', count($registered) === 1);
ac_assert('permission accepts correct webhook token', $controller->permission_webhook_token(new AA_Test_Rest_Request([], ['X-AA-Webhook-Token' => 'webhook-token'])) === true);
ac_assert('permission rejects missing token', $controller->permission_webhook_token(new AA_Test_Rest_Request()) instanceof WP_Error);

$response = $controller->handle_upcoming_confirmed_push_validate(
    new AA_Test_Rest_Request([
        'appointments' => [
            ['appointment_id' => 123, 'expected_start' => '2026-07-09T21:00:00.000Z'],
        ],
    ])
);

ac_assert('REST handler returns WP_REST_Response', $response instanceof WP_REST_Response);
ac_assert('REST handler status 200', $response instanceof WP_REST_Response && $response->get_status() === 200);

$controller_src = file_get_contents($plugin_root . '/includes/controllers/WebhooksController.php');
ac_assert('REST endpoint does not reuse reminders-bulk handler', strpos($controller_src, 'handle_upcoming_confirmed_push_validate') !== false && strpos($controller_src, 'RemindersService::build_bulk_payload($appointments)') === false);

echo "\n";
echo "Passed: {$passed}/{$total}\n";

if ($failed !== []) {
    echo "Failed:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

echo "All tests passed.\n";
exit(0);
