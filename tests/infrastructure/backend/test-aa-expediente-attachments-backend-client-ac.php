<?php
/**
 * AC — AA_Expediente_Attachments_Backend_Client (MC4a2 / 5B2 authorize v1).
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

function aa_authorize_input(array $over = []): array {
    return array_replace([
        'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
        'wp_client_id' => 1,
        'wp_record_id' => 2,
        'mime_type' => 'image/jpeg',
        'byte_size' => 10,
        'width' => 1,
        'height' => 1,
        'used_bytes' => 1234,
        'variants_manifest_version' => 1,
        'variant_byte_sizes' => [
            'summary' => 100,
            'gallery' => 200,
            'display' => 300,
        ],
    ], $over);
}

function aa_authorize_objects(array $over = []): array {
    return array_replace([
        'original' => [
            'status' => 'pending_upload',
            'signed_url' => 'https://abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/original?token=SECRET',
            'token' => 'OBJECT_TOKEN_SECRET',
        ],
        'summary' => [
            'status' => 'pending_upload',
            'signed_url' => 'https://abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/summary?token=SECRET',
        ],
        'gallery' => [
            'status' => 'already_uploaded',
        ],
        'display' => [
            'status' => 'pending_upload',
            'signed_url' => 'https://abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/display?token=SECRET',
        ],
    ], $over);
}

function aa_authorize_v1_body(array $over = []): array {
    $body = [
        'ok' => true,
        'variants_manifest_version' => 1,
        'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
        'storage_path' => 'installations/x/clients/1/records/2/550e8400-e29b-41d4-a716-446655440000.jpg',
        'upload_intent' => 'intent.SECRET',
        'upload_url_ttl_seconds' => 7200,
        'token' => 'SEPARATE_TOKEN_SECRET',
        'objects' => aa_authorize_objects(),
    ];
    foreach ($over as $key => $value) {
        $body[$key] = $value;
    }
    return $body;
}

function aa_http_ok(array $body): array {
    return [
        'response' => ['code' => 200],
        'body' => wp_json_encode($body),
    ];
}

function aa_http_err(int $status, string $error): array {
    return [
        'response' => ['code' => $status],
        'body' => wp_json_encode(['ok' => false, 'error' => $error]),
    ];
}

$client = new AA_Expediente_Attachments_Backend_Client();
$client_src = file_get_contents($plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachments-backend-client.php');

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body());
$auth = $client->authorize_upload(aa_authorize_input());
$payload = $GLOBALS['aa_test_http_calls'][0]['data'] ?? [];

ac_assert('authorize OK', $auth['ok'] === true);
ac_assert('authorize endpoint', strpos($GLOBALS['aa_test_http_calls'][0]['endpoint'], '/expediente/attachments/authorize-upload') !== false);
ac_assert(
    'payload v1 exacto',
    $payload === [
        'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
        'wp_client_id' => 1,
        'wp_record_id' => 2,
        'mime_type' => 'image/jpeg',
        'byte_size' => 10,
        'width' => 1,
        'height' => 1,
        'used_bytes' => 1234,
        'variants_manifest_version' => 1,
        'variant_byte_sizes' => [
            'summary' => 100,
            'gallery' => 200,
            'display' => 300,
        ],
    ]
);
ac_assert('manifest entero 1', ($payload['variants_manifest_version'] ?? null) === 1);
ac_assert(
    'tres tamaños enteros positivos',
    ($payload['variant_byte_sizes']['summary'] ?? 0) === 100
    && ($payload['variant_byte_sizes']['gallery'] ?? 0) === 200
    && ($payload['variant_byte_sizes']['display'] ?? 0) === 300
);
ac_assert('authorize descarta token raíz', !array_key_exists('token', $auth['result']));
ac_assert('authorize no expone signed_url en raíz', !array_key_exists('signed_url', $auth['result']));
ac_assert('authorize no expone status en raíz', !array_key_exists('status', $auth['result']));
ac_assert(
    'mezcla pending/already',
    ($auth['result']['objects']['original']['status'] ?? '') === 'pending_upload'
    && ($auth['result']['objects']['summary']['status'] ?? '') === 'pending_upload'
    && ($auth['result']['objects']['gallery'] ?? null) === ['status' => 'already_uploaded']
    && ($auth['result']['objects']['display']['status'] ?? '') === 'pending_upload'
);
ac_assert(
    'URLs solo en pendientes',
    isset($auth['result']['objects']['original']['signed_url'])
    && isset($auth['result']['objects']['summary']['signed_url'])
    && !array_key_exists('signed_url', $auth['result']['objects']['gallery'])
    && isset($auth['result']['objects']['display']['signed_url'])
);
ac_assert('token de objeto descartado', !array_key_exists('token', $auth['result']['objects']['original']));
ac_assert('ttl opcional conservado', ($auth['result']['upload_url_ttl_seconds'] ?? null) === 7200);
ac_assert(
    'exactamente cuatro objetos',
    is_array($auth['result']['objects'] ?? null)
    && array_keys($auth['result']['objects']) === ['original', 'summary', 'gallery', 'display']
);

reset_http();
$missing_manifest = $client->authorize_upload(aa_authorize_input(['variants_manifest_version' => null]));
unset($missing_manifest);
$missing_manifest = $client->authorize_upload([
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'wp_client_id' => 1,
    'wp_record_id' => 2,
    'mime_type' => 'image/jpeg',
    'byte_size' => 10,
    'width' => 1,
    'height' => 1,
    'used_bytes' => 1234,
    'variant_byte_sizes' => ['summary' => 1, 'gallery' => 1, 'display' => 1],
]);
ac_assert(
    'manifest ausente sin HTTP',
    $missing_manifest['ok'] === false
    && $missing_manifest['code'] === 'manifest_version_invalid'
    && $GLOBALS['aa_test_http_calls'] === []
);

reset_http();
$bad_manifest = $client->authorize_upload(aa_authorize_input(['variants_manifest_version' => 2]));
ac_assert(
    'manifest incorrecto sin HTTP',
    $bad_manifest['ok'] === false
    && $bad_manifest['code'] === 'manifest_version_invalid'
    && $GLOBALS['aa_test_http_calls'] === []
);

reset_http();
$str_manifest = $client->authorize_upload(aa_authorize_input(['variants_manifest_version' => '1']));
ac_assert(
    'manifest string sin HTTP',
    $str_manifest['ok'] === false
    && $str_manifest['code'] === 'manifest_version_invalid'
    && $GLOBALS['aa_test_http_calls'] === []
);

reset_http();
$bad_sizes = $client->authorize_upload(aa_authorize_input([
    'variant_byte_sizes' => ['summary' => 1, 'gallery' => 1],
]));
ac_assert(
    'tamaños incompletos sin HTTP',
    $bad_sizes['ok'] === false
    && $bad_sizes['code'] === 'invalid_variant_meta'
    && $GLOBALS['aa_test_http_calls'] === []
);

reset_http();
$zero_size = $client->authorize_upload(aa_authorize_input([
    'variant_byte_sizes' => ['summary' => 0, 'gallery' => 1, 'display' => 1],
]));
ac_assert(
    'tamaño no positivo sin HTTP',
    $zero_size['ok'] === false
    && $zero_size['code'] === 'invalid_variant_meta'
    && $GLOBALS['aa_test_http_calls'] === []
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body([
    'variants_manifest_version' => 2,
]));
$bad_resp_manifest = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'respuesta manifest incorrecto',
    $bad_resp_manifest['ok'] === false
    && $bad_resp_manifest['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$body = aa_authorize_v1_body();
unset($body['variants_manifest_version']);
$GLOBALS['aa_test_http_response'] = aa_http_ok($body);
$missing_resp_manifest = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'respuesta sin manifest',
    $missing_resp_manifest['ok'] === false
    && $missing_resp_manifest['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$objects = aa_authorize_objects();
unset($objects['display']);
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body(['objects' => $objects]));
$missing_obj = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'objeto faltante',
    $missing_obj['ok'] === false && $missing_obj['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$objects = aa_authorize_objects();
$objects['thumb'] = ['status' => 'already_uploaded'];
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body(['objects' => $objects]));
$extra_obj = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'objeto adicional',
    $extra_obj['ok'] === false && $extra_obj['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$objects = aa_authorize_objects();
$objects['original'] = ['status' => 'done', 'signed_url' => 'https://x'];
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body(['objects' => $objects]));
$unknown = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'status desconocido',
    $unknown['ok'] === false && $unknown['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$objects = aa_authorize_objects();
$objects['original'] = ['status' => 'pending_upload'];
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body(['objects' => $objects]));
$pending_no_url = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'pendiente sin URL',
    $pending_no_url['ok'] === false && $pending_no_url['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$objects = aa_authorize_objects();
$objects['original'] = ['status' => 'pending_upload', 'signed_url' => ''];
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body(['objects' => $objects]));
$pending_empty = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'pendiente con URL vacía',
    $pending_empty['ok'] === false && $pending_empty['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$objects = aa_authorize_objects();
$objects['gallery'] = ['status' => 'already_uploaded'];
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body(['objects' => $objects]));
$already_ok = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'already_uploaded sin signed_url válido',
    $already_ok['ok'] === true
    && ($already_ok['result']['objects']['gallery'] ?? null) === ['status' => 'already_uploaded']
);

foreach (['', null, 'https://should-not-be-here'] as $bad_url) {
    reset_http();
    $objects = aa_authorize_objects();
    $objects['gallery'] = ['status' => 'already_uploaded', 'signed_url' => $bad_url];
    $GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body(['objects' => $objects]));
    $already_bad = $client->authorize_upload(aa_authorize_input());
    $label = $bad_url === '' ? 'vacío' : ($bad_url === null ? 'null' : 'no vacío');
    ac_assert(
        'already_uploaded con signed_url ' . $label . ' inválido',
        $already_bad['ok'] === false && $already_bad['code'] === 'expediente_attachments_invalid_response'
    );
}

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body([
    'status' => 'pending_upload',
]));
$root_status = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'status en la raíz inválido',
    $root_status['ok'] === false && $root_status['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_authorize_v1_body([
    'signed_url' => 'https://root.example/secret',
]));
$root_url = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'signed_url en la raíz inválido',
    $root_url['ok'] === false && $root_url['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$v0_body = [
    'ok' => true,
    'status' => 'pending_upload',
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'storage_path' => 'path.jpg',
    'signed_url' => 'https://v0.example/secret',
    'upload_intent' => 'intent',
];
$GLOBALS['aa_test_http_response'] = aa_http_ok($v0_body);
$v0 = $client->authorize_upload(aa_authorize_input());
ac_assert(
    'contrato authorize v0 rechazado',
    $v0['ok'] === false && $v0['code'] === 'expediente_attachments_invalid_response'
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(409, 'object_mismatch');
$mm = $client->authorize_upload(aa_authorize_input());
ac_assert('mapea object_mismatch', $mm['ok'] === false && $mm['code'] === 'object_mismatch');

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(403, 'storage_not_included');
$ni = $client->authorize_upload(aa_authorize_input());
ac_assert('mapea storage_not_included', $ni['ok'] === false && $ni['code'] === 'storage_not_included');

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(409, 'storage_quota_exceeded');
$qe = $client->authorize_upload(aa_authorize_input());
ac_assert('mapea storage_quota_exceeded', $qe['ok'] === false && $qe['code'] === 'storage_quota_exceeded');

$new_errors = [
    'invalid_usage_report' => 400,
    'manifest_version_invalid' => 400,
    'invalid_variant_meta' => 400,
    'variant_bytes_exceeded' => 400,
    'variant_invalid' => 400,
];
foreach ($new_errors as $error => $status) {
    reset_http();
    $GLOBALS['aa_test_http_response'] = aa_http_err($status, $error);
    $got = $client->authorize_upload(aa_authorize_input());
    ac_assert(
        $error . ' reconocido',
        $got['ok'] === false && $got['code'] === $error
    );
}

$encoded_failures = wp_json_encode([
    $missing_manifest,
    $bad_manifest,
    $v0,
    $root_url,
    $root_status,
    $already_bad,
    $pending_empty,
    $pending_no_url,
]);
ac_assert(
    'errores sin secretos',
    strpos($encoded_failures, 'SECRET') === false
    && strpos($encoded_failures, 'intent.SECRET') === false
    && strpos($encoded_failures, 'https://') === false
);
ac_assert(
    'sin error_log de secretos',
    !preg_match('/error_log\([^\)]*(signed_url|token|upload_intent|SECRET)/i', $client_src)
);
ac_assert(
    'sin parser authorize v0',
    strpos($client_src, "parseJsonOk(\$response, ['status', 'upload_operation_id', 'storage_path', 'upload_intent'])") === false
);
ac_assert('sin authorize_upload_v1', strpos($client_src, 'authorize_upload_v1') === false);

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
