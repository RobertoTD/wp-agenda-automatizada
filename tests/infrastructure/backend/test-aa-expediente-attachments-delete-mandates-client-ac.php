<?php
/**
 * AC — HMAC delete-mandates accept/seal/status (IMG-5 incremento 1).
 *
 * Dobles HTTP. Sin red, Storage ni SQL remoto.
 *
 * Ejecutar:
 *   php tests/infrastructure/backend/test-aa-expediente-attachments-delete-mandates-client-ac.php
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
            return 'timeout';
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

const AA_TEST_MANDATE = '8f0c2a6e-1234-4b5c-8d9e-abcdef012345';
const AA_TEST_OP = '550e8400-e29b-41d4-a716-446655440000';
const AA_TEST_OBLIGATION = '11111111-1111-4111-8111-111111111111';
const AA_TEST_SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const AA_TEST_FINGERPRINT = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const AA_TEST_PATH = 'installations/cccccccccccccccccccccccccccccccccccc/canonical/records/42/550e8400-e29b-41d4-a716-446655440000.jpg';

function reset_http(): void {
    $GLOBALS['aa_test_http_calls'] = [];
    $GLOBALS['aa_test_http_response'] = null;
    $GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
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

function aa_accept_item(array $over = []): array {
    return array_replace([
        'upload_operation_id' => AA_TEST_OP,
        'wp_record_id' => 42,
        'content_sha256' => AA_TEST_SHA,
        'byte_size' => 1024,
    ], $over);
}

function aa_accept_input(array $over = []): array {
    $base = [
        'mandate_id' => AA_TEST_MANDATE,
        'batch_seq' => 0,
        'items' => [aa_accept_item()],
        'installation_id' => 'spoofed-must-not-be-sent',
    ];
    foreach ($over as $key => $value) {
        $base[$key] = $value;
    }
    return $base;
}

function aa_accept_item_out(array $over = []): array {
    return array_replace([
        'upload_operation_id' => AA_TEST_OP,
        'outcome' => 'accepted',
        'retire_authorized' => true,
        'obligation_id' => AA_TEST_OBLIGATION,
        'storage_path' => AA_TEST_PATH,
        'wp_record_id' => 42,
        'content_sha256' => AA_TEST_SHA,
        'byte_size' => 1024,
        'prior_mandate_id' => null,
    ], $over);
}

function aa_accept_ok_body(array $over = []): array {
    $body = [
        'ok' => true,
        'mandate_id' => AA_TEST_MANDATE,
        'inventory_status' => 'open',
        'batch' => [
            'seq' => 0,
            'fingerprint' => AA_TEST_FINGERPRINT,
            'acceptance' => 'accepted',
            'contiguous_prefix_len' => 1,
            'max_seq_accepted' => 0,
        ],
        'structural_retire_authorized' => false,
        'items' => [aa_accept_item_out()],
        'reception_item_count' => 1,
        'owned_obligation_count' => 1,
        'tech_bytes_owned' => 1024,
    ];
    foreach ($over as $key => $value) {
        $body[$key] = $value;
    }
    return $body;
}

function aa_seal_ok_body(array $over = []): array {
    $body = [
        'ok' => true,
        'mandate_id' => AA_TEST_MANDATE,
        'inventory_status' => 'sealed',
        'seal' => [
            'outcome' => 'sealed',
            'expected_batch_count' => 1,
            'contiguous_prefix_len' => 1,
            'sealed_at' => '2026-09-14T00:00:00+00:00',
        ],
        'structural_retire_authorized' => true,
        'reception_item_count' => 1,
        'owned_obligation_count' => 1,
        'tech_bytes_owned' => 1024,
    ];
    foreach ($over as $key => $value) {
        $body[$key] = $value;
    }
    return $body;
}

function aa_status_found_body(array $over = []): array {
    $body = [
        'ok' => true,
        'found' => true,
        'mandate_id' => AA_TEST_MANDATE,
        'inventory_status' => 'open',
        'contiguous_prefix_len' => 1,
        'max_seq_accepted' => 0,
        'structural_retire_authorized' => false,
        'sealed_at' => null,
        'sealed_expected_batch_count' => null,
        'persisted_batch_count' => 1,
        'reception_item_count' => 1,
        'owned_obligation_count' => 1,
        'tech_bytes_owned' => 1024,
        'batch' => null,
        'items' => [
            array_replace(aa_accept_item_out(), [
                'batch_seq' => 0,
                'physical_status' => 'pending',
            ]),
        ],
        'items_page' => [
            'limit' => 50,
            'has_more' => false,
            'next_cursor' => AA_TEST_OP,
        ],
    ];
    foreach ($over as $key => $value) {
        $body[$key] = $value;
    }
    return $body;
}

function last_call(): array {
    $calls = $GLOBALS['aa_test_http_calls'];
    return $calls[count($calls) - 1] ?? [];
}

$client = new AA_Expediente_Attachments_Backend_Client();
$client_src = file_get_contents(
    $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachments-backend-client.php'
);

ac_assert('sin error_log en el cliente', strpos($client_src, 'error_log') === false);
ac_assert(
    'delete síncrono intacto',
    strpos($client_src, '/expediente/attachments/delete') !== false
        && strpos($client_src, 'function delete_object') !== false
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_accept_ok_body());
$accepted = $client->accept_delete_batch(aa_accept_input());
$accept_call = last_call();
$accept_payload = $accept_call['data'] ?? [];

ac_assert('accept ok', ($accepted['ok'] ?? false) === true);
ac_assert('accept outcome accepted', ($accepted['outcome'] ?? '') === 'accepted');
ac_assert('accept no autoriza retiro', ($accepted['retire_authorized'] ?? true) === false);
ac_assert('accept method POST', ($accept_call['method'] ?? '') === 'POST');
ac_assert(
    'accept ruta HMAC',
    strpos((string) ($accept_call['endpoint'] ?? ''), '/expediente/attachments/delete-mandates/accept') !== false
);
ac_assert(
    'accept payload canónico',
    $accept_payload === [
        'path_contract' => 'canonical_v1',
        'mandate_id' => AA_TEST_MANDATE,
        'batch_seq' => 0,
        'items' => [aa_accept_item()],
    ]
);
ac_assert('accept body sin installation_id', !array_key_exists('installation_id', $accept_payload));
ac_assert('accept body sin secretos', !isset($accept_payload['client_secret']) && !isset($accept_payload['token']));

reset_http();
$replay_body = aa_accept_ok_body();
$replay_body['batch']['acceptance'] = 'already_accepted';
$replay_body['inventory_status'] = 'sealed';
$replay_body['structural_retire_authorized'] = true;
$GLOBALS['aa_test_http_response'] = aa_http_ok($replay_body);
$replay = $client->accept_delete_batch(aa_accept_input());
ac_assert('already_accepted es éxito', ($replay['ok'] ?? false) === true && ($replay['outcome'] ?? '') === 'already_accepted');
ac_assert('already_accepted no autoriza retiro local', ($replay['retire_authorized'] ?? true) === false);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_seal_ok_body());
$sealed = $client->seal_delete_mandate([
    'mandate_id' => AA_TEST_MANDATE,
    'expected_batch_count' => 1,
    'installation_id' => 'spoofed-must-not-be-sent',
]);
$seal_call = last_call();
$seal_payload = $seal_call['data'] ?? [];
ac_assert('seal ok', ($sealed['ok'] ?? false) === true);
ac_assert('seal outcome sealed', ($sealed['outcome'] ?? '') === 'sealed');
ac_assert('seal autoriza retiro', ($sealed['retire_authorized'] ?? false) === true);
ac_assert(
    'seal ruta HMAC',
    ($seal_call['method'] ?? '') === 'POST'
        && strpos((string) ($seal_call['endpoint'] ?? ''), '/expediente/attachments/delete-mandates/seal') !== false
);
ac_assert(
    'seal payload exacto',
    $seal_payload === [
        'mandate_id' => AA_TEST_MANDATE,
        'expected_batch_count' => 1,
    ]
);
ac_assert('seal body sin installation_id', !array_key_exists('installation_id', $seal_payload));

reset_http();
$already_seal = aa_seal_ok_body();
$already_seal['seal']['outcome'] = 'already_sealed';
$GLOBALS['aa_test_http_response'] = aa_http_ok($already_seal);
$sealed_again = $client->seal_delete_mandate([
    'mandate_id' => AA_TEST_MANDATE,
    'expected_batch_count' => 1,
]);
ac_assert(
    'already_sealed es éxito',
    ($sealed_again['ok'] ?? false) === true
        && ($sealed_again['outcome'] ?? '') === 'already_sealed'
        && ($sealed_again['retire_authorized'] ?? false) === true
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_seal_ok_body([
    'seal' => [
        'outcome' => 'sealed',
        'expected_batch_count' => 0,
        'contiguous_prefix_len' => 0,
    ],
    'reception_item_count' => 0,
    'owned_obligation_count' => 0,
    'tech_bytes_owned' => 0,
]));
$empty_seal = $client->seal_delete_mandate([
    'mandate_id' => AA_TEST_MANDATE,
    'expected_batch_count' => 0,
]);
ac_assert('seal vacío expected 0', ($empty_seal['ok'] ?? false) === true && ($empty_seal['retire_authorized'] ?? false) === true);
ac_assert('seal vacío envía 0', (last_call()['data']['expected_batch_count'] ?? null) === 0);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(['ok' => true, 'found' => false]);
$status_missing = $client->get_delete_mandate_status([
    'mandate_id' => AA_TEST_MANDATE,
    'installation_id' => 'spoofed-must-not-be-sent',
]);
$status_call = last_call();
$status_payload = $status_call['data'] ?? [];
ac_assert('status not_found es consulta válida', ($status_missing['ok'] ?? false) === true);
ac_assert('status not_found outcome', ($status_missing['outcome'] ?? '') === 'not_found');
ac_assert('status not_found no acredita', ($status_missing['retire_authorized'] ?? true) === false);
ac_assert('status not_found no inventa mandate', !isset($status_missing['result']['mandate_id']));
ac_assert('status not_found no acredita tanda', ($status_missing['result']['can_credit_batch'] ?? true) === false);
ac_assert(
    'status ruta HMAC',
    ($status_call['method'] ?? '') === 'POST'
        && strpos((string) ($status_call['endpoint'] ?? ''), '/expediente/attachments/delete-mandates/status') !== false
);
ac_assert('status payload mínimo', $status_payload === ['mandate_id' => AA_TEST_MANDATE]);
ac_assert('status body sin installation_id', !array_key_exists('installation_id', $status_payload));

reset_http();
$batch_status = aa_status_found_body([
    'batch' => [
        'seq' => 0,
        'fingerprint' => AA_TEST_FINGERPRINT,
        'item_count' => 1,
        'accepted_at' => '2026-09-14T00:00:00+00:00',
        'items' => [
            array_replace(aa_accept_item_out(), [
                'physical_status' => 'pending',
            ]),
        ],
    ],
]);
$GLOBALS['aa_test_http_response'] = aa_http_ok($batch_status);
$status_batch = $client->get_delete_mandate_status([
    'mandate_id' => AA_TEST_MANDATE,
    'batch_seq' => 0,
    'item_limit' => 10,
]);
$batch_payload = last_call()['data'] ?? [];
ac_assert('status found ok', ($status_batch['ok'] ?? false) === true && ($status_batch['outcome'] ?? '') === 'found');
ac_assert('status con batch_seq acredita tanda', ($status_batch['result']['can_credit_batch'] ?? false) === true);
ac_assert('status página completa', ($status_batch['result']['inventory_page_complete'] ?? false) === true);
ac_assert('status no autoriza retiro si open', ($status_batch['retire_authorized'] ?? true) === false);
ac_assert(
    'status envía batch_seq y limit',
    ($batch_payload['mandate_id'] ?? '') === AA_TEST_MANDATE
        && ($batch_payload['batch_seq'] ?? null) === 0
        && ($batch_payload['item_limit'] ?? null) === 10
        && !array_key_exists('installation_id', $batch_payload)
);

reset_http();
$page_incomplete = aa_status_found_body([
    'items_page' => [
        'limit' => 50,
        'has_more' => true,
        'next_cursor' => AA_TEST_OP,
    ],
]);
$GLOBALS['aa_test_http_response'] = aa_http_ok($page_incomplete);
$status_page = $client->get_delete_mandate_status(['mandate_id' => AA_TEST_MANDATE]);
ac_assert(
    'status sin batch_seq no acredita tanda',
    ($status_page['ok'] ?? false) === true
        && ($status_page['result']['can_credit_batch'] ?? true) === false
);
ac_assert(
    'página incompleta no cubre inventario',
    ($status_page['result']['inventory_page_complete'] ?? true) === false
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_ok(aa_status_found_body([
    'inventory_status' => 'sealed',
    'structural_retire_authorized' => true,
    'batch_found' => false,
    'requested_batch_seq' => 3,
]));
$status_sealed_no_batch = $client->get_delete_mandate_status([
    'mandate_id' => AA_TEST_MANDATE,
    'batch_seq' => 3,
]);
ac_assert(
    'batch_found false no acredita tanda',
    ($status_sealed_no_batch['ok'] ?? false) === true
        && ($status_sealed_no_batch['result']['can_credit_batch'] ?? true) === false
        && ($status_sealed_no_batch['result']['batch_found'] ?? true) === false
);
ac_assert(
    'sellado en status autoriza retiro estructural',
    ($status_sealed_no_batch['retire_authorized'] ?? false) === true
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(409, 'batch_content_mismatch');
$content_conflict = $client->accept_delete_batch(aa_accept_input());
ac_assert(
    'conflicto de contenido',
    ($content_conflict['ok'] ?? true) === false
        && ($content_conflict['code'] ?? '') === 'batch_content_mismatch'
        && ($content_conflict['failure_class'] ?? '') === 'conflict'
        && ($content_conflict['retire_authorized'] ?? true) === false
        && (int) ($content_conflict['http_status'] ?? 0) === 409
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(409, 'batch_seq_gap');
$seq_conflict = $client->accept_delete_batch(aa_accept_input());
ac_assert('conflicto de secuencia', ($seq_conflict['failure_class'] ?? '') === 'conflict' && ($seq_conflict['code'] ?? '') === 'batch_seq_gap');

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(409, 'item_metadata_conflict');
$meta_conflict = $client->accept_delete_batch(aa_accept_input());
ac_assert('conflicto de metadata', ($meta_conflict['failure_class'] ?? '') === 'conflict');

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(409, 'seal_mismatch');
$seal_conflict = $client->seal_delete_mandate([
    'mandate_id' => AA_TEST_MANDATE,
    'expected_batch_count' => 1,
]);
ac_assert(
    'conflicto de sello',
    ($seal_conflict['failure_class'] ?? '') === 'conflict'
        && ($seal_conflict['retire_authorized'] ?? true) === false
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(401, 'unauthorized');
$auth_fail = $client->accept_delete_batch(aa_accept_input());
ac_assert(
    'error de autenticación',
    ($auth_fail['ok'] ?? true) === false
        && ($auth_fail['failure_class'] ?? '') === 'auth'
        && ($auth_fail['code'] ?? '') === 'unauthorized'
        && ($auth_fail['retire_authorized'] ?? true) === false
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(409, 'installation_missing');
$install_fail = $client->seal_delete_mandate([
    'mandate_id' => AA_TEST_MANDATE,
    'expected_batch_count' => 0,
]);
ac_assert('installation_missing es auth', ($install_fail['failure_class'] ?? '') === 'auth');

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(404, 'mandate_not_found');
$missing_seal = $client->seal_delete_mandate([
    'mandate_id' => AA_TEST_MANDATE,
    'expected_batch_count' => 1,
]);
ac_assert(
    'mandate_not_found no autoriza',
    ($missing_seal['failure_class'] ?? '') === 'not_found'
        && ($missing_seal['retire_authorized'] ?? true) === false
);

reset_http();
$GLOBALS['aa_test_http_response'] = new WP_Error();
$timeout = $client->accept_delete_batch(aa_accept_input());
ac_assert(
    'transporte WP_Error no es éxito',
    ($timeout['ok'] ?? true) === false
        && ($timeout['failure_class'] ?? '') === 'unreachable'
        && ($timeout['retire_authorized'] ?? true) === false
);

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body' => '',
];
$empty_body = $client->accept_delete_batch(aa_accept_input());
ac_assert(
    'cuerpo vacío es malformado',
    ($empty_body['ok'] ?? true) === false
        && ($empty_body['failure_class'] ?? '') === 'malformed'
        && ($empty_body['retire_authorized'] ?? true) === false
);

reset_http();
$incomplete = aa_accept_ok_body();
unset($incomplete['items']);
$GLOBALS['aa_test_http_response'] = aa_http_ok($incomplete);
$incomplete_accept = $client->accept_delete_batch(aa_accept_input());
ac_assert(
    'accept incompleto no es aceptación',
    ($incomplete_accept['ok'] ?? true) === false
        && ($incomplete_accept['failure_class'] ?? '') === 'malformed'
        && ($incomplete_accept['retire_authorized'] ?? true) === false
);

reset_http();
$bad_count = aa_accept_ok_body();
$bad_count['items'] = [];
$GLOBALS['aa_test_http_response'] = aa_http_ok($bad_count);
$mismatch_count = $client->accept_delete_batch(aa_accept_input());
ac_assert('accept con ítems de menos es malformado', ($mismatch_count['failure_class'] ?? '') === 'malformed');

reset_http();
$no_structural = aa_seal_ok_body();
unset($no_structural['structural_retire_authorized']);
$GLOBALS['aa_test_http_response'] = aa_http_ok($no_structural);
$incomplete_seal = $client->seal_delete_mandate([
    'mandate_id' => AA_TEST_MANDATE,
    'expected_batch_count' => 1,
]);
ac_assert(
    'seal incompleto no autoriza retiro',
    ($incomplete_seal['ok'] ?? true) === false
        && ($incomplete_seal['retire_authorized'] ?? true) === false
        && ($incomplete_seal['failure_class'] ?? '') === 'malformed'
);

reset_http();
$false_structural = aa_seal_ok_body();
$false_structural['structural_retire_authorized'] = false;
$GLOBALS['aa_test_http_response'] = aa_http_ok($false_structural);
$denied_seal = $client->seal_delete_mandate([
    'mandate_id' => AA_TEST_MANDATE,
    'expected_batch_count' => 1,
]);
ac_assert('seal sin structural_retire_authorized no autoriza', ($denied_seal['ok'] ?? true) === false);

reset_http();
$open_status = aa_status_found_body();
unset($open_status['items_page']);
$GLOBALS['aa_test_http_response'] = aa_http_ok($open_status);
$incomplete_status = $client->get_delete_mandate_status(['mandate_id' => AA_TEST_MANDATE]);
ac_assert(
    'status incompleto no acredita',
    ($incomplete_status['ok'] ?? true) === false
        && ($incomplete_status['retire_authorized'] ?? true) === false
        && ($incomplete_status['failure_class'] ?? '') === 'malformed'
);

reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body' => wp_json_encode(['ok' => true]),
];
$ambiguous_ok = $client->accept_delete_batch(aa_accept_input());
ac_assert('200 ok sin contrato es desconocido/malformado', ($ambiguous_ok['ok'] ?? true) === false && ($ambiguous_ok['retire_authorized'] ?? true) === false);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(418, 'not-a-known-code');
$unknown = $client->accept_delete_batch(aa_accept_input());
ac_assert(
    'error desconocido no autoriza',
    ($unknown['failure_class'] ?? '') === 'unknown'
        && ($unknown['retire_authorized'] ?? true) === false
);

reset_http();
$GLOBALS['aa_test_http_response'] = aa_http_err(502, 'delete_mandate_unavailable');
$unavailable = $client->accept_delete_batch(aa_accept_input());
ac_assert('RPC ausente es unreachable', ($unavailable['failure_class'] ?? '') === 'unreachable');

reset_http();
$bad_local = $client->accept_delete_batch([
    'mandate_id' => 'not-a-uuid',
    'batch_seq' => 0,
    'items' => [aa_accept_item()],
]);
ac_assert('mandate inválido sin HTTP', $GLOBALS['aa_test_http_calls'] === [] && ($bad_local['code'] ?? '') === 'mandate_id_required');

reset_http();
$empty_items = $client->accept_delete_batch([
    'mandate_id' => AA_TEST_MANDATE,
    'batch_seq' => 0,
    'items' => [],
]);
ac_assert('items vacíos sin HTTP', $GLOBALS['aa_test_http_calls'] === [] && ($empty_items['code'] ?? '') === 'batch_items_empty');

reset_http();
$dup = $client->accept_delete_batch([
    'mandate_id' => AA_TEST_MANDATE,
    'batch_seq' => 0,
    'items' => [aa_accept_item(), aa_accept_item()],
]);
ac_assert(
    'duplicado local es conflicto',
    $GLOBALS['aa_test_http_calls'] === []
        && ($dup['failure_class'] ?? '') === 'conflict'
        && ($dup['code'] ?? '') === 'item_duplicate_in_batch'
);

reset_http();
$too_big = [];
for ($i = 0; $i < 51; $i++) {
    $op = sprintf('550e8400-e29b-41d4-a716-44665544%04x', $i);
    $too_big[] = aa_accept_item(['upload_operation_id' => $op, 'wp_record_id' => $i + 1]);
}
$oversize = $client->accept_delete_batch([
    'mandate_id' => AA_TEST_MANDATE,
    'batch_seq' => 0,
    'items' => $too_big,
]);
ac_assert('más de 50 ítems sin HTTP', $GLOBALS['aa_test_http_calls'] === [] && ($oversize['code'] ?? '') === 'batch_too_large');

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
