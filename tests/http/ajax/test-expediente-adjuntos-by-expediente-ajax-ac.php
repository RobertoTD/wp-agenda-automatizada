<?php
/**
 * AC — ExpedienteAdjuntosByExpedienteAjax::handle_sign_read (B3a).
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
ac_assert('nonce by-expediente', strpos($ajax_src, 'aa_expediente_registros_by_expediente_nonce') !== false);
ac_assert('soft nonce die=false', strpos($ajax_src, "check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false)") !== false);
ac_assert('sin nopriv en clase', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('bootstrap register', strpos($bootstrap_src, 'ExpedienteAdjuntosByExpedienteAjax::register()') !== false);
ac_assert('bootstrap require', strpos($bootstrap_src, 'ExpedienteAdjuntosByExpedienteAjax.php') !== false);
ac_assert('bootstrap sin nopriv sign for expediente', strpos($bootstrap_src, 'wp_ajax_nopriv_aa_sign_expediente_adjunto_read_for_expediente') === false);
ac_assert('registros by-expediente sin ACTION_SIGN_READ', strpos($by_exp_registros_src, 'ACTION_SIGN_READ') === false
    && strpos($by_exp_registros_src, 'aa_sign_expediente_adjunto_read_for_expediente') === false);
ac_assert('legacy action intacta', strpos($legacy_ajax_src, "ACTION_SIGN_READ = 'aa_sign_expediente_adjunto_read'") !== false);
ac_assert('detail aún no cablea sign-read canónico', strpos($detail_src, 'aa_sign_expediente_adjunto_read_for_expediente') === false);
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

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlForExpedienteUseCase.php';
require_once $plugin_root . '/includes/http/ajax/ExpedienteAdjuntosByExpedienteAjax.php';

ac_assert(
    'constants',
    ExpedienteAdjuntosByExpedienteAjax::ACTION_SIGN_READ === 'aa_sign_expediente_adjunto_read_for_expediente'
    && ExpedienteAdjuntosByExpedienteAjax::NONCE_ACTION === 'aa_expediente_registros_by_expediente_nonce'
);

$GLOBALS['aa_test_actions'] = [];
ExpedienteAdjuntosByExpedienteAjax::register();
ac_assert(
    'register solo wp_ajax_ sign-read',
    $GLOBALS['aa_test_actions'] === ['wp_ajax_aa_sign_expediente_adjunto_read_for_expediente']
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

restore_error_handler();

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
