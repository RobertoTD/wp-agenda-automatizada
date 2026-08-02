<?php
/**
 * AC — AA_Expediente_Attachments_Backend_Client (MC4a2).
 *
 * Ejecutar: php tests/infrastructure/backend/test-aa-expediente-attachments-backend-client-ac.php
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
        public function get_error_message() {
            return 'err';
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
            'method' => $method,
            'data' => $data,
        ];
        return $GLOBALS['aa_test_http_response'];
    }
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';

function reset_http(): void {
    $GLOBALS['aa_test_http_calls'] = [];
    $GLOBALS['aa_test_http_response'] = null;
    $GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
}

$client = new AA_Expediente_Attachments_Backend_Client();

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body' => wp_json_encode([
        'ok' => true,
        'status' => 'pending_upload',
        'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
        'storage_path' => 'installations/x/clients/1/records/2/550e8400-e29b-41d4-a716-446655440000.jpg',
        'signed_url' => 'https://abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/p?token=SECRET',
        'token' => 'SEPARATE_TOKEN_SECRET',
        'upload_intent' => 'intent.SECRET',
        'upload_url_ttl_seconds' => 7200,
    ]),
];

$auth = $client->authorize_upload([
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'wp_client_id' => 1,
    'wp_record_id' => 2,
    'mime_type' => 'image/jpeg',
    'byte_size' => 10,
    'width' => 1,
    'height' => 1,
    'used_bytes' => 1234,
]);

ac_assert('authorize OK', $auth['ok'] === true);
ac_assert('authorize endpoint', strpos($GLOBALS['aa_test_http_calls'][0]['endpoint'], '/expediente/attachments/authorize-upload') !== false);
ac_assert('authorize descarta token', !array_key_exists('token', $auth['result']));
ac_assert('authorize conserva signed_url interno', isset($auth['result']['signed_url']));
ac_assert(
    'payload snake_case',
    ($GLOBALS['aa_test_http_calls'][0]['data']['wp_client_id'] ?? null) === 1
    && ($GLOBALS['aa_test_http_calls'][0]['data']['upload_operation_id'] ?? '') !== ''
);
ac_assert(
    'payload incluye used_bytes',
    ($GLOBALS['aa_test_http_calls'][0]['data']['used_bytes'] ?? null) === 1234
);

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body' => json_encode([
        'ok' => true,
        'status' => 'already_uploaded',
        'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
        'storage_path' => 'path.jpg',
        'upload_intent' => 'intent',
        'signed_url' => 'https://should-be-removed',
        'token' => 'nope',
    ]),
];
$already = $client->authorize_upload([
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'wp_client_id' => 1,
    'wp_record_id' => 2,
    'mime_type' => 'image/jpeg',
    'byte_size' => 10,
    'width' => 1,
    'height' => 1,
]);
ac_assert('already_uploaded sin signed_url', $already['ok'] === true && !isset($already['result']['signed_url']));

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 409],
    'body' => json_encode(['ok' => false, 'error' => 'object_mismatch']),
];
$mm = $client->authorize_upload([
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'wp_client_id' => 1,
    'wp_record_id' => 2,
    'mime_type' => 'image/jpeg',
    'byte_size' => 10,
    'width' => 1,
    'height' => 1,
]);
ac_assert('mapea object_mismatch', $mm['ok'] === false && $mm['code'] === 'object_mismatch');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 403],
    'body' => json_encode(['ok' => false, 'error' => 'storage_not_included']),
];
$ni = $client->authorize_upload([
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'wp_client_id' => 1,
    'wp_record_id' => 2,
    'mime_type' => 'image/jpeg',
    'byte_size' => 10,
    'width' => 1,
    'height' => 1,
    'used_bytes' => 0,
]);
ac_assert('mapea storage_not_included', $ni['ok'] === false && $ni['code'] === 'storage_not_included');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 409],
    'body' => json_encode(['ok' => false, 'error' => 'storage_quota_exceeded']),
];
$qe = $client->authorize_upload([
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'wp_client_id' => 1,
    'wp_record_id' => 2,
    'mime_type' => 'image/jpeg',
    'byte_size' => 10,
    'width' => 1,
    'height' => 1,
    'used_bytes' => 0,
]);
ac_assert('mapea storage_quota_exceeded', $qe['ok'] === false && $qe['code'] === 'storage_quota_exceeded');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 400],
    'body' => json_encode(['ok' => false, 'error' => 'invalid_usage_report']),
];
$iu = $client->authorize_upload([
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'wp_client_id' => 1,
    'wp_record_id' => 2,
    'mime_type' => 'image/jpeg',
    'byte_size' => 10,
    'width' => 1,
    'height' => 1,
    'used_bytes' => -1,
]);
ac_assert(
    'invalid_usage_report colapsa (no KNOWN)',
    $iu['ok'] === false && $iu['code'] === 'expediente_attachments_backend_error'
);

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body' => json_encode([
        'ok' => true,
        'storage_path' => 'path.jpg',
        'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
        'installation_id' => 'inst',
        'mime_type' => 'image/jpeg',
        'byte_size' => 10,
        'width' => 1,
        'height' => 1,
    ]),
];
$fin = $client->finalize('intent-value');
ac_assert('finalize OK', $fin['ok'] === true);
ac_assert('finalize endpoint', strpos($GLOBALS['aa_test_http_calls'][0]['endpoint'], '/expediente/attachments/finalize') !== false);
ac_assert('finalize body upload_intent', ($GLOBALS['aa_test_http_calls'][0]['data']['upload_intent'] ?? '') === 'intent-value');

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body' => json_encode([
        'ok' => true,
        'url' => 'https://signed-read',
        'expires_in' => 600,
    ]),
];
$read = $client->sign_read('path.jpg');
ac_assert('sign_read OK', $read['ok'] === true && (int) $read['result']['expires_in'] === 600);

$client_src = file_get_contents($plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachments-backend-client.php');
ac_assert('sin error_log de secretos', !preg_match('/error_log\([^\)]*(signed_url|token|upload_intent|SECRET)/i', $client_src));

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}

echo 'Failed ' . count($failed) . "/{$total}\n";
foreach ($failed as $label) {
    echo " - {$label}\n";
}
exit(1);
