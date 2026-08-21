<?php
/**
 * AC — ExpedienteAdjuntosByExpedienteAjax (B3a sign-read + B3b1 attach + B3b2 delete).
 *
 * Ejecutar: php tests/http/ajax/test-expediente-adjuntos-by-expediente-ajax-ac.php
 */

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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteAdjuntosByExpedienteAjax.php');
$legacy_ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteAdjuntosAjax.php');
$by_exp_registros_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteRegistrosByExpedienteAjax.php');
$bootstrap_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$detail_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/expedientes/detail.php');

ac_assert('ACTION_SIGN_READ', strpos($ajax_src, "ACTION_SIGN_READ = 'aa_sign_expediente_adjunto_read_for_expediente'") !== false);
ac_assert('ACTION_ATTACH', strpos($ajax_src, "ACTION_ATTACH = 'aa_attach_expediente_adjunto_for_expediente'") !== false);
ac_assert('ACTION_DELETE', strpos($ajax_src, "ACTION_DELETE = 'aa_delete_expediente_adjunto_for_expediente'") !== false);
ac_assert('nonce by-expediente', strpos($ajax_src, 'aa_expediente_registros_by_expediente_nonce') !== false);
ac_assert('soft nonce die=false', strpos($ajax_src, "check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false)") !== false);
ac_assert('sin nopriv en clase', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('bootstrap register', strpos($bootstrap_src, 'ExpedienteAdjuntosByExpedienteAjax::register()') !== false);
ac_assert('bootstrap require', strpos($bootstrap_src, 'ExpedienteAdjuntosByExpedienteAjax.php') !== false);
ac_assert('bootstrap sin nopriv attach for expediente', strpos($bootstrap_src, 'wp_ajax_nopriv_aa_attach_expediente_adjunto_for_expediente') === false);
ac_assert('bootstrap sin nopriv sign for expediente', strpos($bootstrap_src, 'wp_ajax_nopriv_aa_sign_expediente_adjunto_read_for_expediente') === false);
ac_assert('bootstrap sin nopriv delete for expediente', strpos($bootstrap_src, 'wp_ajax_nopriv_aa_delete_expediente_adjunto_for_expediente') === false);
ac_assert('registros by-expediente sin ACTION canónicos de adjuntos', strpos($by_exp_registros_src, 'aa_attach_expediente_adjunto_for_expediente') === false
    && strpos($by_exp_registros_src, 'aa_sign_expediente_adjunto_read_for_expediente') === false
    && strpos($by_exp_registros_src, 'aa_delete_expediente_adjunto_for_expediente') === false);
ac_assert('legacy attach intacto', strpos($legacy_ajax_src, "ACTION_ATTACH = 'aa_attach_expediente_registro'") !== false);
ac_assert('legacy sign intacta', strpos($legacy_ajax_src, "ACTION_SIGN_READ = 'aa_sign_expediente_adjunto_read'") !== false);
ac_assert('legacy delete intacto', strpos($legacy_ajax_src, "ACTION_DELETE = 'aa_delete_expediente_adjunto'") !== false);
ac_assert(
    'detail monta list/read/delete-adjunto (≠ create rico adoptado)',
    strpos($detail_src, 'aa_attach_expediente_adjunto_for_expediente') !== false
    && strpos($detail_src, 'aa_sign_expediente_adjunto_read_for_expediente') !== false
    && strpos($detail_src, 'aa_delete_expediente_adjunto_for_expediente') !== false
    && strpos($detail_src, 'attachRegistro') !== false
    && strpos($detail_src, 'signAdjuntoRead') !== false
    && strpos($detail_src, 'deleteAdjunto') !== false
    && strpos($detail_src, 'expediente-registros-canonical-adapter.js') !== false
    && strpos($detail_src, 'expediente-registros.js') !== false
    && strpos($detail_src, 'expediente-registros-canonical-mount.js') !== false
    && strpos($detail_src, 'expediente-registro-create-modal.js') !== false
    && strpos($detail_src, 'ExpedienteRegistros.openCreate') === false
    && strpos($detail_src, 'onCreateComplete') === false
    && strpos($detail_src, 'executable-options-menu-placement') === false
);
ac_assert('handler no lee client_id', strpos($ajax_src, "\$_POST['client_id']") === false
    && strpos($ajax_src, "\$_REQUEST['client_id']") === false);
ac_assert('sin absint', strpos($ajax_src, 'absint(') === false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['aa_test_json'] = null;
$GLOBALS['aa_test_can_manage_options'] = true;
$GLOBALS['aa_test_nonce_valid'] = true;
$GLOBALS['aa_test_actions'] = [];
$GLOBALS['aa_test_warnings'] = [];

set_error_handler(static function ($errno, $errstr) {
    if ($errno === E_WARNING || $errno === E_NOTICE || $errno === E_USER_WARNING) {
        $GLOBALS['aa_test_warnings'][] = $errstr;
    }
    return true;
});

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return $capability === 'manage_options' && !empty($GLOBALS['aa_test_can_manage_options']);
    }
}
if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg, $die = true) {
        $ok = $action === ExpedienteAdjuntosByExpedienteAjax::NONCE_ACTION
            && $query_arg === '_wpnonce'
            && !empty($GLOBALS['aa_test_nonce_valid']);
        if ($ok) {
            return 1;
        }
        if ($die) {
            throw new RuntimeException('bad_nonce');
        }
        return false;
    }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = ['success' => true, 'data' => $data, 'status' => $status_code ?? 200];
        throw new RuntimeException('json_sent');
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = ['success' => false, 'data' => $data, 'status' => $status_code];
        throw new RuntimeException('json_sent');
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['aa_test_actions'][] = $hook;
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}

final class ExpedienteRegistrosAjax {
    public static $gate_calls = 0;
    public static $access = 'full';

    public static function require_expediente_shell_access(): bool {
        self::$gate_calls++;
        if (self::$access === 'full') {
            return true;
        }
        wp_send_json_error([
            'message' => 'Acceso denegado.',
            'code' => 'expediente_access_denied',
        ], 403);
        return false;
    }
}

final class ExpedientesRepository {
    /** @var bool|null */
    public static $exists_result = true;
    public static $exists_calls = 0;
    /** @var array{id:int,client_id:?int}|null */
    public static $owner = ['id' => 7, 'client_id' => 55];
    public static $owner_calls = 0;

    public static function exists_by_id(int $id) {
        self::$exists_calls++;
        return self::$exists_result;
    }

    public static function find_owner_context_by_id(int $id): ?array {
        self::$owner_calls++;
        return self::$owner;
    }
}

final class ExpedienteRegistrosRepository {
    /** @var array|false|null */
    public static $record = [
        'id' => 10,
        'expediente_id' => 7,
        'client_id' => 55,
        'title' => 'A',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];
    public static $calls = 0;

    public static function find_by_id_for_expediente(int $record_id, int $expediente_id) {
        self::$calls++;
        return self::$record;
    }
}

final class GetExpedienteAdjuntoReadUrlUseCase {
    public static $calls = [];
    /** @var array<string,mixed> */
    public static $response = [
        'ok' => true,
        'url' => 'https://proj.supabase.co/sign/x.jpg?token=t',
        'expires_in' => 600,
        'variant' => 'summary',
    ];

    public function execute(array $input): array {
        self::$calls[] = $input;
        $resp = self::$response;
        if (!empty($resp['ok']) && isset($input['variant'])) {
            $resp['variant'] = $input['variant'];
        }
        return $resp;
    }
}

final class UploadExpedienteRegistroAdjuntoUseCase {
    public static $calls = [];
    /** @var array<string,mixed> */
    public static $response = [
        'ok' => true,
        'attachment' => [
            'id' => 301,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
            'storage_path' => 'installations/x/clients/55/records/10/550e8400-e29b-41d4-a716-446655440000.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 1024,
            'width' => 800,
            'height' => 600,
            'created_at' => '2026-08-20 13:00:00',
        ],
    ];

    public function execute(array $input): array {
        self::$calls[] = $input;
        return self::$response;
    }
}

final class DeleteExpedienteAdjuntoUseCase {
    public static $calls = [];
    /** @var list<array<string,mixed>> */
    public static $responses = [];
    public static $response_index = 0;
    /** @var array<string,mixed> */
    public static $response = [
        'ok' => true,
        'record_id' => 10,
        'deleted_attachment_id' => 301,
        'adjuntos' => [
            [
                'id' => 300,
                'width' => 100,
                'height' => 80,
                'byte_size' => 512,
                'created_at' => '2026-08-19 11:00:00',
            ],
        ],
        'adjunto' => [
            'id' => 300,
            'width' => 100,
            'height' => 80,
            'byte_size' => 512,
            'created_at' => '2026-08-19 11:00:00',
        ],
    ];

    public function execute(array $input): array {
        self::$calls[] = $input;
        if (self::$responses !== []) {
            $idx = min(self::$response_index, count(self::$responses) - 1);
            self::$response_index++;
            return self::$responses[$idx];
        }
        return self::$response;
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoPublicDto.php';
require_once $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlForExpedienteUseCase.php';
require_once $plugin_root . '/includes/http/ajax/ExpedienteAdjuntosByExpedienteAjax.php';

ac_assert(
    'constants',
    ExpedienteAdjuntosByExpedienteAjax::ACTION_SIGN_READ === 'aa_sign_expediente_adjunto_read_for_expediente'
    && ExpedienteAdjuntosByExpedienteAjax::ACTION_ATTACH === 'aa_attach_expediente_adjunto_for_expediente'
    && ExpedienteAdjuntosByExpedienteAjax::ACTION_DELETE === 'aa_delete_expediente_adjunto_for_expediente'
    && ExpedienteAdjuntosByExpedienteAjax::NONCE_ACTION === 'aa_expediente_registros_by_expediente_nonce'
);

$GLOBALS['aa_test_actions'] = [];
ExpedienteAdjuntosByExpedienteAjax::register();
ac_assert(
    'register wp_ajax_ sign-read + attach + delete',
    $GLOBALS['aa_test_actions'] === [
        'wp_ajax_aa_sign_expediente_adjunto_read_for_expediente',
        'wp_ajax_aa_attach_expediente_adjunto_for_expediente',
        'wp_ajax_aa_delete_expediente_adjunto_for_expediente',
    ]
);

/**
 * @return array<string,mixed>|null
 */
function aa_invoke_sign(): ?array {
    $GLOBALS['aa_test_json'] = null;
    try {
        ExpedienteAdjuntosByExpedienteAjax::handle_sign_read();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent' && $e->getMessage() !== 'bad_nonce') {
            throw $e;
        }
    }
    return $GLOBALS['aa_test_json'];
}

function aa_reset_sign(): void {
    $_POST = [];
    ExpedienteRegistrosAjax::$gate_calls = 0;
    ExpedienteRegistrosAjax::$access = 'full';
    ExpedientesRepository::$exists_result = true;
    ExpedientesRepository::$exists_calls = 0;
    ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
    ExpedientesRepository::$owner_calls = 0;
    ExpedienteRegistrosRepository::$record = [
        'id' => 10,
        'expediente_id' => 7,
        'client_id' => 55,
        'title' => 'A',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];
    ExpedienteRegistrosRepository::$calls = 0;
    GetExpedienteAdjuntoReadUrlUseCase::$calls = [];
    GetExpedienteAdjuntoReadUrlUseCase::$response = [
        'ok' => true,
        'url' => 'https://proj.supabase.co/sign/x.jpg?token=t',
        'expires_in' => 600,
        'variant' => 'summary',
    ];
    $GLOBALS['aa_test_can_manage_options'] = true;
    $GLOBALS['aa_test_nonce_valid'] = true;
    $GLOBALS['aa_test_json'] = null;
    $GLOBALS['aa_test_warnings'] = [];
}

function aa_post_ok(array $extra = []): array {
    return array_merge([
        'expediente_id' => '7',
        'record_id' => '10',
        'attachment_id' => '301',
        'variant' => 'summary',
    ], $extra);
}

// Auth order
aa_reset_sign();
$GLOBALS['aa_test_can_manage_options'] = false;
$cap = aa_invoke_sign();
ac_assert('cap → 403', ($cap['status'] ?? 0) === 403);
ac_assert('cap sin gate/exists/sign', ExpedienteRegistrosAjax::$gate_calls === 0
    && ExpedientesRepository::$exists_calls === 0
    && GetExpedienteAdjuntoReadUrlUseCase::$calls === []);

aa_reset_sign();
$GLOBALS['aa_test_nonce_valid'] = false;
$nonce = aa_invoke_sign();
ac_assert('nonce → 403 bad_nonce', ($nonce['status'] ?? 0) === 403
    && ($nonce['data']['code'] ?? '') === 'bad_nonce');
ac_assert('nonce sin gate/sign', ExpedienteRegistrosAjax::$gate_calls === 0
    && GetExpedienteAdjuntoReadUrlUseCase::$calls === []);

aa_reset_sign();
ExpedienteRegistrosAjax::$access = 'free';
$_POST = aa_post_ok();
$gate = aa_invoke_sign();
ac_assert('gate → 403', ($gate['status'] ?? 0) === 403
    && ($gate['data']['code'] ?? '') === 'expediente_access_denied');
ac_assert('gate sin exists/sign', ExpedientesRepository::$exists_calls === 0
    && GetExpedienteAdjuntoReadUrlUseCase::$calls === []);

// Input
aa_reset_sign();
$_POST = aa_post_ok(['expediente_id' => '01']);
ac_assert('01 → 400', ((aa_invoke_sign()['status'] ?? 0) === 400));

aa_reset_sign();
$_POST = aa_post_ok(['expediente_id' => ['7']]);
$arr = aa_invoke_sign();
ac_assert('array id → 400 sin warnings', ($arr['status'] ?? 0) === 400
    && $GLOBALS['aa_test_warnings'] === []);

aa_reset_sign();
$_POST = aa_post_ok(['variant' => 'original']);
$orig = aa_invoke_sign();
ac_assert('original → 400', ($orig['status'] ?? 0) === 400
    && ($orig['data']['code'] ?? '') === 'variant_invalid'
    && GetExpedienteAdjuntoReadUrlUseCase::$calls === []);

aa_reset_sign();
$_POST = aa_post_ok();
unset($_POST['variant']);
$noVar = aa_invoke_sign();
ac_assert('variant ausente → 400', ($noVar['status'] ?? 0) === 400
    && ($noVar['data']['code'] ?? '') === 'variant_invalid');

// Padre / registro
aa_reset_sign();
ExpedientesRepository::$exists_result = false;
$_POST = aa_post_ok();
$nf = aa_invoke_sign();
ac_assert('expediente inexistente → 404', ($nf['status'] ?? 0) === 404
    && ($nf['data']['code'] ?? '') === 'not_found');

aa_reset_sign();
ExpedientesRepository::$exists_result = null;
$_POST = aa_post_ok();
$lf = aa_invoke_sign();
ac_assert('exists SQL → 500', ($lf['status'] ?? 0) === 500
    && ($lf['data']['code'] ?? '') === 'lookup_failed');

aa_reset_sign();
ExpedientesRepository::$owner = null;
$_POST = aa_post_ok();
$own = aa_invoke_sign();
ac_assert('owner null → 500', ($own['status'] ?? 0) === 500);

aa_reset_sign();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
$_POST = aa_post_ok();
$gen = aa_invoke_sign();
ac_assert('general → 409 attachments_unavailable', ($gen['status'] ?? 0) === 409
    && ($gen['data']['code'] ?? '') === 'attachments_unavailable'
    && GetExpedienteAdjuntoReadUrlUseCase::$calls === []);

aa_reset_sign();
ExpedienteRegistrosRepository::$record = false;
$_POST = aa_post_ok();
$recNf = aa_invoke_sign();
ac_assert('registro ajeno → 404', ($recNf['status'] ?? 0) === 404
    && GetExpedienteAdjuntoReadUrlUseCase::$calls === []);

aa_reset_sign();
ExpedienteRegistrosRepository::$record = null;
$_POST = aa_post_ok();
$recSql = aa_invoke_sign();
ac_assert('registro SQL → 500', ($recSql['status'] ?? 0) === 500
    && ($recSql['data']['code'] ?? '') === 'lookup_failed');

aa_reset_sign();
ExpedienteRegistrosRepository::$record = [
    'id' => 10,
    'expediente_id' => 7,
    'client_id' => 99,
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => '2026-08-20 12:00:00',
    'created_at' => '2026-08-20 12:00:00',
    'updated_at' => null,
];
$_POST = aa_post_ok();
$mismatch = aa_invoke_sign();
ac_assert('owner mismatch → 404 sin firma', ($mismatch['status'] ?? 0) === 404
    && GetExpedienteAdjuntoReadUrlUseCase::$calls === []);

// Éxito por variante + client_id POST ignorado
foreach (['summary', 'gallery', 'display'] as $variant) {
    aa_reset_sign();
    $_POST = aa_post_ok([
        'variant' => $variant,
        'client_id' => '999',
        'storage_path' => '/evil',
        'bucket' => 'b',
    ]);
    $ok = aa_invoke_sign();
    ac_assert("éxito {$variant} 200", ($ok['success'] ?? false) === true && ($ok['status'] ?? 0) === 200);
    ac_assert(
        "{$variant} contrato público",
        ($ok['data']['url'] ?? '') !== ''
        && ($ok['data']['expires_in'] ?? 0) === 600
        && ($ok['data']['variant'] ?? '') === $variant
        && array_keys($ok['data'] ?? []) === ['url', 'expires_in', 'variant']
    );
    ac_assert(
        "{$variant} firma 1× con client padre",
        count(GetExpedienteAdjuntoReadUrlUseCase::$calls) === 1
        && (GetExpedienteAdjuntoReadUrlUseCase::$calls[0]['client_id'] ?? 0) === 55
        && !array_key_exists('storage_path', GetExpedienteAdjuntoReadUrlUseCase::$calls[0])
    );
}

// Propagación
aa_reset_sign();
GetExpedienteAdjuntoReadUrlUseCase::$response = [
    'ok' => false,
    'code' => 'object_missing',
    'message' => 'No se pudo obtener la imagen.',
];
$_POST = aa_post_ok();
$om = aa_invoke_sign();
ac_assert('object_missing → 404', ($om['status'] ?? 0) === 404
    && ($om['data']['code'] ?? '') === 'object_missing');

aa_reset_sign();
GetExpedienteAdjuntoReadUrlUseCase::$response = [
    'ok' => false,
    'code' => 'sign_failed',
    'message' => 'No se pudo obtener la imagen.',
];
$_POST = aa_post_ok();
$sf = aa_invoke_sign();
ac_assert('sign_failed → 502', ($sf['status'] ?? 0) === 502);

aa_reset_sign();
GetExpedienteAdjuntoReadUrlUseCase::$response = [
    'ok' => false,
    'code' => 'attachment_not_found',
    'message' => 'Imagen no encontrada.',
];
$_POST = aa_post_ok();
$anf = aa_invoke_sign();
ac_assert('attachment_not_found → 404', ($anf['status'] ?? 0) === 404);

aa_reset_sign();
GetExpedienteAdjuntoReadUrlUseCase::$response = [
    'ok' => false,
    'code' => 'signed_url_invalid',
    'message' => 'No se pudo obtener la imagen.',
];
$_POST = aa_post_ok();
$sui = aa_invoke_sign();
ac_assert('signed_url_invalid → 502', ($sui['status'] ?? 0) === 502);

// --- B3b1 attach ---

/**
 * @return array<string,mixed>|null
 */
function aa_invoke_attach(): ?array {
    $GLOBALS['aa_test_json'] = null;
    try {
        ExpedienteAdjuntosByExpedienteAjax::handle_attach();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent' && $e->getMessage() !== 'bad_nonce') {
            throw $e;
        }
    }
    return $GLOBALS['aa_test_json'];
}

function aa_reset_attach(): void {
    aa_reset_sign();
    $_FILES = [];
    UploadExpedienteRegistroAdjuntoUseCase::$calls = [];
    UploadExpedienteRegistroAdjuntoUseCase::$response = [
        'ok' => true,
        'attachment' => [
            'id' => 301,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
            'storage_path' => 'installations/x/clients/55/records/10/550e8400-e29b-41d4-a716-446655440000.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 1024,
            'width' => 800,
            'height' => 600,
            'created_at' => '2026-08-20 13:00:00',
        ],
    ];
}

function aa_attach_post(array $extra = []): array {
    return array_merge([
        'expediente_id' => '7',
        'record_id' => '10',
        'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    ], $extra);
}

function aa_attach_file(): array {
    return [
        'name' => 'adjunto.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => '/tmp/phpXXXX',
        'error' => 0,
        'size' => 1024,
    ];
}

aa_reset_attach();
$GLOBALS['aa_test_can_manage_options'] = false;
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach cap → 403', ((aa_invoke_attach()['status'] ?? 0) === 403));
ac_assert('attach cap sin pipeline', UploadExpedienteRegistroAdjuntoUseCase::$calls === []);

aa_reset_attach();
$GLOBALS['aa_test_nonce_valid'] = false;
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
$nonceA = aa_invoke_attach();
ac_assert('attach nonce → 403 bad_nonce', ($nonceA['status'] ?? 0) === 403
    && ($nonceA['data']['code'] ?? '') === 'bad_nonce');

aa_reset_attach();
ExpedienteRegistrosAjax::$access = 'free';
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
$gateA = aa_invoke_attach();
ac_assert('attach gate → 403', ($gateA['status'] ?? 0) === 403
    && ($gateA['data']['code'] ?? '') === 'expediente_access_denied');

aa_reset_attach();
$_POST = aa_attach_post();
$_FILES = [];
$missing = aa_invoke_attach();
ac_assert('attach sin file → 400 file_missing', ($missing['status'] ?? 0) === 400
    && ($missing['data']['code'] ?? '') === 'file_missing'
    && UploadExpedienteRegistroAdjuntoUseCase::$calls === []);

aa_reset_attach();
$_POST = aa_attach_post(['expediente_id' => '01']);
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach 01 → 400', ((aa_invoke_attach()['status'] ?? 0) === 400));

aa_reset_attach();
$_POST = aa_attach_post(['expediente_id' => ['7']]);
$_FILES = ['file' => aa_attach_file()];
$arrA = aa_invoke_attach();
ac_assert('attach array id → 400 sin warnings', ($arrA['status'] ?? 0) === 400
    && $GLOBALS['aa_test_warnings'] === []);

aa_reset_attach();
ExpedientesRepository::$exists_result = false;
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach expediente inexistente → 404', ((aa_invoke_attach()['status'] ?? 0) === 404)
    && UploadExpedienteRegistroAdjuntoUseCase::$calls === []);

aa_reset_attach();
ExpedientesRepository::$exists_result = null;
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach exists SQL → 500', ((aa_invoke_attach()['status'] ?? 0) === 500));

aa_reset_attach();
ExpedientesRepository::$owner = null;
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach owner null → 500', ((aa_invoke_attach()['status'] ?? 0) === 500));

aa_reset_attach();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
$genA = aa_invoke_attach();
ac_assert('attach general → 409', ($genA['status'] ?? 0) === 409
    && ($genA['data']['code'] ?? '') === 'attachments_unavailable'
    && UploadExpedienteRegistroAdjuntoUseCase::$calls === []);

aa_reset_attach();
ExpedienteRegistrosRepository::$record = false;
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach registro ajeno → 404', ((aa_invoke_attach()['status'] ?? 0) === 404)
    && UploadExpedienteRegistroAdjuntoUseCase::$calls === []);

aa_reset_attach();
ExpedienteRegistrosRepository::$record = null;
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach registro SQL → 500', ((aa_invoke_attach()['status'] ?? 0) === 500));

aa_reset_attach();
ExpedienteRegistrosRepository::$record = [
    'id' => 10,
    'expediente_id' => 7,
    'client_id' => 99,
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => '2026-08-20 12:00:00',
    'created_at' => '2026-08-20 12:00:00',
    'updated_at' => null,
];
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach mismatch → 404 sin pipeline', ((aa_invoke_attach()['status'] ?? 0) === 404)
    && UploadExpedienteRegistroAdjuntoUseCase::$calls === []);

aa_reset_attach();
$_POST = aa_attach_post([
    'client_id' => '999',
    'storage_path' => '/evil',
    'attachment_id' => '1',
]);
$_FILES = ['file' => aa_attach_file()];
$okA = aa_invoke_attach();
ac_assert('attach éxito 200', ($okA['success'] ?? false) === true && ($okA['status'] ?? 0) === 200);
ac_assert(
    'attach contrato público',
    ($okA['data']['record_id'] ?? 0) === 10
    && array_keys($okA['data']['adjunto'] ?? []) === ['id', 'width', 'height', 'byte_size', 'created_at']
);
$blobA = json_encode($okA['data'] ?? []);
ac_assert(
    'attach sin owners/paths/op',
    strpos($blobA, 'client_id') === false
    && strpos($blobA, 'storage_path') === false
    && strpos($blobA, 'upload_operation_id') === false
);
ac_assert(
    'attach pipeline 1× client padre; POST ignorado',
    count(UploadExpedienteRegistroAdjuntoUseCase::$calls) === 1
    && (UploadExpedienteRegistroAdjuntoUseCase::$calls[0]['client_id'] ?? 0) === 55
    && !array_key_exists('storage_path', UploadExpedienteRegistroAdjuntoUseCase::$calls[0])
);

aa_reset_attach();
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
aa_invoke_attach();
aa_invoke_attach();
ac_assert('attach retry → 2× pipeline idempotente', count(UploadExpedienteRegistroAdjuntoUseCase::$calls) === 2);

aa_reset_attach();
$_POST = aa_attach_post(['upload_operation_id' => ['x']]);
$_FILES = ['file' => aa_attach_file()];
UploadExpedienteRegistroAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'invalid_operation_id',
    'message' => 'Identificador de operación no válido.',
];
$badOp = aa_invoke_attach();
ac_assert('attach op no escalar → 400', ($badOp['status'] ?? 0) === 400
    && ($badOp['data']['code'] ?? '') === 'invalid_operation_id'
    && (UploadExpedienteRegistroAdjuntoUseCase::$calls[0]['upload_operation_id'] ?? null) === '');

aa_reset_attach();
UploadExpedienteRegistroAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'adjunto_meta_conflict',
    'message' => 'Conflicto.',
];
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach meta conflict → 409', ((aa_invoke_attach()['status'] ?? 0) === 409));

aa_reset_attach();
UploadExpedienteRegistroAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'storage_quota_exceeded',
    'message' => 'Cuota.',
];
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach cuota → 409', ((aa_invoke_attach()['status'] ?? 0) === 409));

aa_reset_attach();
UploadExpedienteRegistroAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'variant_generation_failed',
    'message' => 'Variantes.',
];
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach variantes → 500', ((aa_invoke_attach()['status'] ?? 0) === 500));

aa_reset_attach();
UploadExpedienteRegistroAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'expediente_attachments_unreachable',
    'message' => 'Backend.',
];
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach backend → 502', ((aa_invoke_attach()['status'] ?? 0) === 502));

aa_reset_attach();
UploadExpedienteRegistroAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'invalid_mime',
    'message' => 'MIME.',
];
$_POST = aa_attach_post();
$_FILES = ['file' => aa_attach_file()];
ac_assert('attach MIME → 400', ((aa_invoke_attach()['status'] ?? 0) === 400));

// --- B3b2 delete ---

/**
 * @return array<string,mixed>|null
 */
function aa_invoke_delete(): ?array {
    $GLOBALS['aa_test_json'] = null;
    try {
        ExpedienteAdjuntosByExpedienteAjax::handle_delete();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent' && $e->getMessage() !== 'bad_nonce') {
            throw $e;
        }
    }
    return $GLOBALS['aa_test_json'];
}

function aa_reset_delete(): void {
    aa_reset_sign();
    DeleteExpedienteAdjuntoUseCase::$calls = [];
    DeleteExpedienteAdjuntoUseCase::$responses = [];
    DeleteExpedienteAdjuntoUseCase::$response_index = 0;
    DeleteExpedienteAdjuntoUseCase::$response = [
        'ok' => true,
        'record_id' => 10,
        'deleted_attachment_id' => 301,
        'adjuntos' => [
            [
                'id' => 300,
                'width' => 100,
                'height' => 80,
                'byte_size' => 512,
                'created_at' => '2026-08-19 11:00:00',
            ],
        ],
        'adjunto' => [
            'id' => 300,
            'width' => 100,
            'height' => 80,
            'byte_size' => 512,
            'created_at' => '2026-08-19 11:00:00',
        ],
    ];
}

function aa_delete_post(array $extra = []): array {
    return array_merge([
        'expediente_id' => '7',
        'record_id' => '10',
        'attachment_id' => '301',
    ], $extra);
}

aa_reset_delete();
$GLOBALS['aa_test_can_manage_options'] = false;
$_POST = aa_delete_post();
ac_assert('delete cap → 403', ((aa_invoke_delete()['status'] ?? 0) === 403));
ac_assert('delete cap sin pipeline', DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
$GLOBALS['aa_test_nonce_valid'] = false;
$_POST = aa_delete_post();
$nonceD = aa_invoke_delete();
ac_assert('delete nonce → 403 bad_nonce', ($nonceD['status'] ?? 0) === 403
    && ($nonceD['data']['code'] ?? '') === 'bad_nonce');
ac_assert('delete nonce sin pipeline', DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
ExpedienteRegistrosAjax::$access = 'free';
$_POST = aa_delete_post();
$gateD = aa_invoke_delete();
ac_assert('delete gate → 403', ($gateD['status'] ?? 0) === 403
    && ($gateD['data']['code'] ?? '') === 'expediente_access_denied');
ac_assert('delete gate sin pipeline', DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
$_POST = aa_delete_post(['expediente_id' => '01']);
ac_assert('delete 01 → 400', ((aa_invoke_delete()['status'] ?? 0) === 400));
ac_assert('delete 01 sin pipeline', DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
$_POST = aa_delete_post(['expediente_id' => ['7']]);
$arrD = aa_invoke_delete();
ac_assert('delete array id → 400 sin warnings', ($arrD['status'] ?? 0) === 400
    && $GLOBALS['aa_test_warnings'] === []);
ac_assert('delete array sin pipeline', DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
ExpedientesRepository::$exists_result = false;
$_POST = aa_delete_post();
ac_assert('delete expediente inexistente → 404', ((aa_invoke_delete()['status'] ?? 0) === 404)
    && DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
ExpedientesRepository::$exists_result = null;
$_POST = aa_delete_post();
ac_assert('delete exists SQL → 500', ((aa_invoke_delete()['status'] ?? 0) === 500)
    && DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
ExpedientesRepository::$owner = null;
$_POST = aa_delete_post();
ac_assert('delete owner null → 500', ((aa_invoke_delete()['status'] ?? 0) === 500)
    && DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
$_POST = aa_delete_post();
$genD = aa_invoke_delete();
ac_assert('delete general → 409', ($genD['status'] ?? 0) === 409
    && ($genD['data']['code'] ?? '') === 'attachments_unavailable'
    && DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
ExpedienteRegistrosRepository::$record = false;
$_POST = aa_delete_post();
ac_assert('delete registro ajeno → 404', ((aa_invoke_delete()['status'] ?? 0) === 404)
    && DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
ExpedienteRegistrosRepository::$record = null;
$_POST = aa_delete_post();
ac_assert('delete registro SQL → 500', ((aa_invoke_delete()['status'] ?? 0) === 500)
    && DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
ExpedienteRegistrosRepository::$record = [
    'id' => 10,
    'expediente_id' => 7,
    'client_id' => 99,
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => '2026-08-20 12:00:00',
    'created_at' => '2026-08-20 12:00:00',
    'updated_at' => null,
];
$_POST = aa_delete_post();
ac_assert('delete mismatch → 404 sin pipeline', ((aa_invoke_delete()['status'] ?? 0) === 404)
    && DeleteExpedienteAdjuntoUseCase::$calls === []);

aa_reset_delete();
$_POST = aa_delete_post([
    'client_id' => '999',
    'storage_path' => '/evil',
    'bucket' => 'b',
    'object_key' => 'k',
    'uuid' => 'u',
    'variant' => 'summary',
]);
$okD = aa_invoke_delete();
ac_assert('delete éxito 200', ($okD['success'] ?? false) === true && ($okD['status'] ?? 0) === 200);
ac_assert(
    'delete contrato público',
    ($okD['data']['record_id'] ?? 0) === 10
    && ($okD['data']['deleted_attachment_id'] ?? 0) === 301
    && count($okD['data']['adjuntos'] ?? []) === 1
    && ($okD['data']['adjunto'] ?? null) === ($okD['data']['adjuntos'][0] ?? null)
    && array_keys($okD['data'] ?? []) === ['record_id', 'deleted_attachment_id', 'adjuntos', 'adjunto']
);
$blobD = json_encode($okD['data'] ?? []);
ac_assert(
    'delete sin owners/paths',
    strpos($blobD, 'client_id') === false
    && strpos($blobD, 'storage_path') === false
    && strpos($blobD, 'expediente_id') === false
);
ac_assert(
    'delete pipeline 1× client padre; POST ignorado',
    count(DeleteExpedienteAdjuntoUseCase::$calls) === 1
    && (DeleteExpedienteAdjuntoUseCase::$calls[0]['client_id'] ?? 0) === 55
    && (DeleteExpedienteAdjuntoUseCase::$calls[0]['record_id'] ?? 0) === 10
    && (DeleteExpedienteAdjuntoUseCase::$calls[0]['attachment_id'] ?? 0) === 301
    && !array_key_exists('storage_path', DeleteExpedienteAdjuntoUseCase::$calls[0])
);

aa_reset_delete();
DeleteExpedienteAdjuntoUseCase::$response = [
    'ok' => true,
    'record_id' => 10,
    'deleted_attachment_id' => 301,
    'adjuntos' => [],
    'adjunto' => null,
];
$_POST = aa_delete_post();
$emptyD = aa_invoke_delete();
ac_assert('delete vacío → adjunto null', ($emptyD['data']['adjuntos'] ?? null) === []
    && array_key_exists('adjunto', $emptyD['data'])
    && $emptyD['data']['adjunto'] === null);

aa_reset_delete();
DeleteExpedienteAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'attachment_not_found',
    'message' => 'Imagen no encontrada.',
];
$_POST = aa_delete_post();
ac_assert('delete attachment_not_found → 404', ((aa_invoke_delete()['status'] ?? 0) === 404));

aa_reset_delete();
DeleteExpedienteAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'adjunto_inconsistent',
    'message' => 'Inconsistente.',
];
$_POST = aa_delete_post();
ac_assert('delete path inconsistente → 409', ((aa_invoke_delete()['status'] ?? 0) === 409));

aa_reset_delete();
DeleteExpedienteAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'expediente_attachments_unreachable',
    'message' => 'Backend.',
];
$_POST = aa_delete_post();
ac_assert('delete backend inaccesible → 502', ((aa_invoke_delete()['status'] ?? 0) === 502));

aa_reset_delete();
DeleteExpedienteAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'storage_delete_failed',
    'message' => 'Storage.',
];
$_POST = aa_delete_post();
ac_assert('delete storage_delete_failed → 502', ((aa_invoke_delete()['status'] ?? 0) === 502));

aa_reset_delete();
DeleteExpedienteAdjuntoUseCase::$response = [
    'ok' => false,
    'code' => 'local_delete_failed',
    'message' => 'Local.',
];
$_POST = aa_delete_post();
ac_assert('delete local_delete_failed → 500', ((aa_invoke_delete()['status'] ?? 0) === 500));

aa_reset_delete();
DeleteExpedienteAdjuntoUseCase::$responses = [
    [
        'ok' => true,
        'record_id' => 10,
        'deleted_attachment_id' => 301,
        'adjuntos' => [],
        'adjunto' => null,
    ],
    ['ok' => false, 'code' => 'attachment_not_found', 'message' => 'Imagen no encontrada.'],
];
$_POST = aa_delete_post();
$firstDel = aa_invoke_delete();
$secondDel = aa_invoke_delete();
ac_assert('delete primera OK', ($firstDel['success'] ?? false) === true);
ac_assert('delete segunda → 404 (no éxito)', ($secondDel['status'] ?? 0) === 404
    && ($secondDel['data']['code'] ?? '') === 'attachment_not_found');
ac_assert('delete 2× pipeline', count(DeleteExpedienteAdjuntoUseCase::$calls) === 2);

$legacy_registros_js = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/clients/expediente-registros.js');
ac_assert(
    'port JS attach/retry intacto (fuente)',
    strpos($legacy_registros_js, "callPort('attach'") !== false
    && strpos($legacy_registros_js, 'aa_attach_expediente_registro') !== false
    && strpos($legacy_registros_js, 'runAttachRetry') !== false
);
ac_assert(
    'port JS deleteAdjunto intacto (fuente)',
    strpos($legacy_registros_js, "callPort('deleteAdjunto'") !== false
    && strpos($legacy_registros_js, "deleteAdjunto: 'aa_delete_expediente_adjunto'") !== false
    && strpos($legacy_registros_js, 'aa_delete_expediente_adjunto_for_expediente') === false
);

restore_error_handler();

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
