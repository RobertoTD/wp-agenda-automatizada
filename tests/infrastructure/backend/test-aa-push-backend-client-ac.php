<?php
/**
 * AC MC4 — AA_Push_Backend_Client.
 *
 * Ejecutar: php tests/infrastructure/backend/test-aa-push-backend-client-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
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

$GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
$GLOBALS['aa_test_http_response'] = null;
$GLOBALS['aa_test_http_calls'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }
        return $default;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $message;

        public function __construct($code = '', $message = '') {
            $this->message = (string) $message;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return (string) ($response['body'] ?? '');
    }
}

if (!function_exists('aa_send_authenticated_request')) {
    function aa_send_authenticated_request($endpoint, $method, $data = null) {
        $GLOBALS['aa_test_http_calls'][] = [
            'endpoint' => $endpoint,
            'method'   => $method,
            'data'     => $data,
        ];
        return $GLOBALS['aa_test_http_response'];
    }
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-push-backend-client.php';

function reset_http(): void {
    $GLOBALS['aa_test_http_calls'] = [];
    $GLOBALS['aa_test_http_response'] = null;
    $GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
}

$client = new AA_Push_Backend_Client();

reset_http();
$result = $client->registerSubscription([
    'endpoint' => '',
    'keys'     => ['p256dh' => '', 'auth' => ''],
]);
ac_assert('register rejects empty subscription locally', ($result['code'] ?? '') === 'invalid_subscription');
ac_assert('register local invalid skips HTTP', count($GLOBALS['aa_test_http_calls']) === 0);

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body'     => json_encode([
        'ok'           => true,
        'registration' => 'created',
        'first_test'   => ['status' => 'sent'],
    ]),
];
$result = $client->registerSubscription([
    'endpoint' => 'https://push.example.test/subscription/abc',
    'keys'     => ['p256dh' => 'p256dh', 'auth' => 'auth'],
]);
ac_assert('register maps backend success', ($result['ok'] ?? false) === true);
ac_assert('register POST uses MC3 body shape', ($GLOBALS['aa_test_http_calls'][0]['data']['keys']['p256dh'] ?? '') === 'p256dh');
ac_assert('register POST targets /push/subscriptions', strpos((string) $GLOBALS['aa_test_http_calls'][0]['endpoint'], '/push/subscriptions') !== false);

reset_http();
$GLOBALS['aa_test_http_response'] = new WP_Error('http_request_failed', 'timeout');
$result = $client->registerSubscription([
    'endpoint' => 'https://push.example.test/subscription/abc',
    'keys'     => ['p256dh' => 'p256dh', 'auth' => 'auth'],
]);
ac_assert('register transport failure normalizes to push_backend_unavailable', ($result['code'] ?? '') === 'push_backend_unavailable');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 409],
    'body'     => json_encode(['ok' => false, 'error' => 'endpoint_conflict']),
];
$result = $client->registerSubscription([
    'endpoint' => 'https://push.example.test/subscription/abc',
    'keys'     => ['p256dh' => 'p256dh', 'auth' => 'auth'],
]);
ac_assert('register preserves endpoint_conflict', ($result['code'] ?? '') === 'endpoint_conflict');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body'     => json_encode([
        'ok'   => true,
        'sync' => 'scheduled',
    ]),
];
$result = $client->syncUpcomingConfirmedJob([
    'appointment_id'    => 123,
    'enabled'           => true,
    'appointment_start' => '2026-07-09T15:00:00-06:00',
    'minutes'           => 15,
]);
ac_assert('syncUpcomingConfirmedJob maps backend success', ($result['ok'] ?? false) === true);
ac_assert('syncUpcomingConfirmedJob returns sync field', ($result['sync'] ?? '') === 'scheduled');
ac_assert(
    'syncUpcomingConfirmedJob POST targets /push/upcoming-confirmed-jobs/sync',
    strpos((string) $GLOBALS['aa_test_http_calls'][0]['endpoint'], '/push/upcoming-confirmed-jobs/sync') !== false
);
ac_assert('syncUpcomingConfirmedJob uses POST', ($GLOBALS['aa_test_http_calls'][0]['method'] ?? '') === 'POST');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body'     => json_encode([
        'ok'   => true,
        'sync' => 'disabled',
    ]),
];
$result = $client->syncUpcomingConfirmedJob([
    'appointment_id' => 123,
    'enabled'        => false,
]);
ac_assert('syncUpcomingConfirmedJob disabled contract accepted', ($result['sync'] ?? '') === 'disabled');
ac_assert(
    'syncUpcomingConfirmedJob disabled sends reduced body',
    ($GLOBALS['aa_test_http_calls'][0]['data']['enabled'] ?? null) === false
    && !array_key_exists('appointment_start', $GLOBALS['aa_test_http_calls'][0]['data'])
);

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 400],
    'body'     => json_encode(['ok' => false, 'error' => 'invalid_minutes']),
];
$result = $client->syncUpcomingConfirmedJob([
    'appointment_id'    => 123,
    'enabled'           => true,
    'appointment_start' => '2026-07-09T15:00:00-06:00',
    'minutes'           => 99,
]);
ac_assert('syncUpcomingConfirmedJob preserves invalid_minutes', ($result['code'] ?? '') === 'invalid_minutes');

reset_http();
$GLOBALS['aa_test_http_response'] = new WP_Error('http_request_failed', 'timeout');
$result = $client->syncUpcomingConfirmedJob([
    'appointment_id' => 123,
    'enabled'        => false,
]);
ac_assert('syncUpcomingConfirmedJob transport failure normalizes', ($result['code'] ?? '') === 'push_backend_unavailable');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body'     => json_encode([
        'ok'   => true,
        'sync' => 'scheduled',
    ]),
];
$result = $client->syncTaskExecutionAvailableJob([
    'task_id'                => 123,
    'execution_available_at' => '2026-07-09T15:00:00-06:00',
]);
ac_assert('syncTaskExecutionAvailableJob maps backend success', ($result['ok'] ?? false) === true);
ac_assert('syncTaskExecutionAvailableJob returns sync field', ($result['sync'] ?? '') === 'scheduled');
ac_assert(
    'syncTaskExecutionAvailableJob POST targets /push/task-execution-available-jobs/sync',
    strpos((string) $GLOBALS['aa_test_http_calls'][0]['endpoint'], '/push/task-execution-available-jobs/sync') !== false
);
ac_assert('syncTaskExecutionAvailableJob uses POST', ($GLOBALS['aa_test_http_calls'][0]['method'] ?? '') === 'POST');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body'     => json_encode([
        'ok'   => true,
        'sync' => 'disabled',
    ]),
];
$result = $client->syncTaskExecutionAvailableJob([
    'task_id'                => 123,
    'execution_available_at' => null,
]);
ac_assert('syncTaskExecutionAvailableJob disabled contract accepted', ($result['sync'] ?? '') === 'disabled');
ac_assert(
    'syncTaskExecutionAvailableJob clear sends null execution_available_at',
    array_key_exists('execution_available_at', $GLOBALS['aa_test_http_calls'][0]['data'])
    && $GLOBALS['aa_test_http_calls'][0]['data']['execution_available_at'] === null
);

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 400],
    'body'     => json_encode(['ok' => false, 'error' => 'invalid_execution_available_at']),
];
$result = $client->syncTaskExecutionAvailableJob([
    'task_id'                => 123,
    'execution_available_at' => 'bad-date',
]);
ac_assert(
    'syncTaskExecutionAvailableJob preserves invalid_execution_available_at',
    ($result['code'] ?? '') === 'invalid_execution_available_at'
);

reset_http();
$GLOBALS['aa_test_http_response'] = new WP_Error('http_request_failed', 'timeout');
$result = $client->syncTaskExecutionAvailableJob([
    'task_id'                => 123,
    'execution_available_at' => null,
]);
ac_assert('syncTaskExecutionAvailableJob transport failure normalizes', ($result['code'] ?? '') === 'push_backend_unavailable');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body'     => json_encode([
        'ok'               => true,
        'vapid_public_key' => 'public-key-only',
    ]),
];
$result = $client->getVapidPublicKey();
ac_assert('getVapidPublicKey maps backend success', ($result['vapid_public_key'] ?? '') === 'public-key-only');
ac_assert('getVapidPublicKey uses GET /push/vapid-public-key', ($GLOBALS['aa_test_http_calls'][0]['method'] ?? '') === 'GET');
ac_assert(
    'getVapidPublicKey targets /push/vapid-public-key',
    strpos((string) $GLOBALS['aa_test_http_calls'][0]['endpoint'], '/push/vapid-public-key') !== false
);

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 503],
    'body'     => json_encode(['ok' => false, 'error' => 'missing_vapid_public_key']),
];
$result = $client->getVapidPublicKey();
ac_assert('getVapidPublicKey technical failure normalizes to push_backend_unavailable', ($result['code'] ?? '') === 'push_backend_unavailable');

$src = file_get_contents($plugin_root . '/includes/infrastructure/backend/class-aa-push-backend-client.php');
ac_assert('backend client does not log endpoint', strpos($src, 'error_log') === false && strpos($src, 'var_dump') === false);
ac_assert('backend client does not expose private key field', strpos($src, 'vapid_private') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
