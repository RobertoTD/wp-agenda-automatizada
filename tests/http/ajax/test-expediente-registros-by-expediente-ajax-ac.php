<?php
/**
 * AC — ExpedienteRegistrosByExpedienteAjax (create hijo por expediente_id).
 *
 * Ejecutar: php tests/http/ajax/test-expediente-registros-by-expediente-ajax-ac.php
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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteRegistrosByExpedienteAjax.php');
$legacy_ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteRegistrosAjax.php');
$bootstrap_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$detail_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/expedientes/detail.php');
$schema_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/Schema.php');

ac_assert('ajax file readable', $ajax_src !== '');
ac_assert('ACTION create for expediente', strpos($ajax_src, 'aa_create_expediente_registro_for_expediente') !== false);
ac_assert('ACTION list for expediente', strpos($ajax_src, 'aa_list_expediente_registros_for_expediente') !== false);
ac_assert('nonce propio by expediente', strpos($ajax_src, 'aa_expediente_registros_by_expediente_nonce') !== false);
ac_assert('solo wp_ajax_ en register', strpos($ajax_src, "add_action('wp_ajax_'") !== false
    && strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('manage_options antes de nonce', strpos($ajax_src, "current_user_can('manage_options')") !== false
    && strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('nonce soft (die=false) → 403 JSON', strpos($ajax_src, "check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false)") !== false);
ac_assert('reutiliza gate full', strpos($ajax_src, 'ExpedienteRegistrosAjax::require_expediente_shell_access') !== false);
ac_assert('delega CreateExpedienteRegistroUseCase', strpos($ajax_src, 'CreateExpedienteRegistroUseCase') !== false);
ac_assert(
    'list delega ListExpedienteRegistrosWithPublicAdjuntosUseCase',
    strpos($ajax_src, 'ListExpedienteRegistrosWithPublicAdjuntosUseCase') !== false
);
ac_assert(
    'handler list no embebe exists_by_id (vive en UC enriquecido)',
    strpos($ajax_src, 'exists_by_id') === false
);
ac_assert('sin absint', strpos($ajax_src, 'absint(') === false);
ac_assert('sanea title text_field', strpos($ajax_src, 'sanitize_text_field') !== false);
ac_assert('sanea body textarea_field', strpos($ajax_src, 'sanitize_textarea_field') !== false);
ac_assert('no lee client_id', strpos($ajax_src, "\$_POST['client_id']") === false
    && strpos($ajax_src, "\$_REQUEST['client_id']") === false);
ac_assert('no lee blog_id/fechas/id', strpos($ajax_src, "\$_POST['blog_id']") === false
    && strpos($ajax_src, "\$_POST['recorded_at']") === false
    && strpos($ajax_src, "\$_POST['created_at']") === false
    && strpos($ajax_src, "\$_POST['id']") === false);
ac_assert('sin $wpdb', strpos($ajax_src, '$wpdb') === false);
ac_assert('bootstrap register', strpos($bootstrap_src, 'ExpedienteRegistrosByExpedienteAjax::register()') !== false);
ac_assert('bootstrap require', strpos($bootstrap_src, 'ExpedienteRegistrosByExpedienteAjax.php') !== false);
ac_assert('bootstrap sin nopriv del action', strpos($bootstrap_src, 'wp_ajax_nopriv_aa_create_expediente_registro_for_expediente') === false);
ac_assert('legacy create action intacto', strpos($legacy_ajax_src, 'aa_create_expediente_registro') !== false
    && strpos($legacy_ajax_src, 'aa_create_expediente_registro_for_expediente') === false);
ac_assert(
    'detalle cablea create by-expediente sin legacy registros JS',
    strpos($detail_src, 'ExpedienteRegistrosByExpedienteAjax') !== false
    && strpos($detail_src, 'aa_create_expediente_registro_for_expediente') !== false
    && strpos($detail_src, 'aa_expediente_registros_by_expediente_nonce') !== false
    && strpos($detail_src, 'expediente-registro-create-modal.js') !== false
    && strpos($detail_src, 'AA_EXPEDIENTE_DETAIL_DATA') !== false
    && strpos($detail_src, 'expediente-registros.js') === false
);
ac_assert('schema DB17 (ciclo materialización)', strpos($schema_src, "DB_VERSION = '17'") !== false);

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
        $ok = $action === ExpedienteRegistrosByExpedienteAjax::NONCE_ACTION
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
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        $str = (string) $str;
        $str = str_replace(["\r\n", "\r"], "\n", $str);
        return trim(strip_tags($str));
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_string($value)) {
            return stripslashes($value);
        }
        return $value;
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

final class CreateExpedienteRegistroUseCase {
    /** @var array<string,mixed>|null */
    public static $last_input = null;
    public static $calls = 0;
    /** @var array<string,mixed> */
    public static $result = [
        'success' => true,
        'data' => [
            'record' => [
                'id' => 55,
                'title' => 'Nota',
                'body' => "Línea 1\nLínea 2",
                'recorded_at' => '2026-08-20 12:00:00',
                'created_at' => '2026-08-20 12:00:00',
                'updated_at' => null,
            ],
        ],
    ];

    public function execute(array $input): array {
        self::$calls++;
        self::$last_input = $input;
        return self::$result;
    }
}

require_once $plugin_root . '/includes/http/ajax/ExpedienteRegistrosByExpedienteAjax.php';

ac_assert('class exists', class_exists('ExpedienteRegistrosByExpedienteAjax'));
ac_assert(
    'constants',
    ExpedienteRegistrosByExpedienteAjax::ACTION_CREATE === 'aa_create_expediente_registro_for_expediente'
    && ExpedienteRegistrosByExpedienteAjax::ACTION_LIST === 'aa_list_expediente_registros_for_expediente'
    && ExpedienteRegistrosByExpedienteAjax::NONCE_ACTION === 'aa_expediente_registros_by_expediente_nonce'
);

ExpedienteRegistrosByExpedienteAjax::register();
ac_assert(
    'register wp_ajax_ create una vez',
    count(array_filter(
        $GLOBALS['aa_test_actions'],
        static function ($h) {
            return $h === 'wp_ajax_aa_create_expediente_registro_for_expediente';
        }
    )) === 1
);
ac_assert(
    'register wp_ajax_ list una vez',
    count(array_filter(
        $GLOBALS['aa_test_actions'],
        static function ($h) {
            return $h === 'wp_ajax_aa_list_expediente_registros_for_expediente';
        }
    )) === 1
);
ac_assert(
    'register sin nopriv create ni list',
    !in_array('wp_ajax_nopriv_aa_create_expediente_registro_for_expediente', $GLOBALS['aa_test_actions'], true)
    && !in_array('wp_ajax_nopriv_aa_list_expediente_registros_for_expediente', $GLOBALS['aa_test_actions'], true)
);

/**
 * @return array<string,mixed>|null
 */
function aa_invoke_by_exp_ajax(callable $handler): ?array {
    $GLOBALS['aa_test_json'] = null;
    try {
        $handler();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent' && $e->getMessage() !== 'bad_nonce') {
            throw $e;
        }
        if ($e->getMessage() === 'bad_nonce') {
            return ['success' => false, 'data' => ['code' => 'bad_nonce'], 'status' => -1];
        }
    }

    return $GLOBALS['aa_test_json'];
}

function aa_reset_by_exp_ajax(): void {
    $_POST = [];
    ExpedienteRegistrosAjax::$gate_calls = 0;
    ExpedienteRegistrosAjax::$access = 'full';
    CreateExpedienteRegistroUseCase::$last_input = null;
    CreateExpedienteRegistroUseCase::$calls = 0;
    CreateExpedienteRegistroUseCase::$result = [
        'success' => true,
        'data' => [
            'record' => [
                'id' => 55,
                'title' => 'Nota',
                'body' => "Línea 1\nLínea 2",
                'recorded_at' => '2026-08-20 12:00:00',
                'created_at' => '2026-08-20 12:00:00',
                'updated_at' => null,
            ],
        ],
    ];
    $GLOBALS['aa_test_can_manage_options'] = true;
    $GLOBALS['aa_test_nonce_valid'] = true;
    $GLOBALS['aa_test_json'] = null;
    $GLOBALS['aa_test_warnings'] = [];
}

aa_reset_by_exp_ajax();
$GLOBALS['aa_test_can_manage_options'] = false;
$denied_cap = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('capability inválida → 403', ($denied_cap['status'] ?? 0) === 403);
ac_assert(
    'capability inválida antes de nonce/gate/UC',
    CreateExpedienteRegistroUseCase::$calls === 0
    && ExpedienteRegistrosAjax::$gate_calls === 0
);

aa_reset_by_exp_ajax();
$GLOBALS['aa_test_nonce_valid'] = false;
$denied_nonce = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('nonce inválido → 403', ($denied_nonce['status'] ?? 0) === 403
    && ($denied_nonce['data']['code'] ?? '') === 'bad_nonce');
ac_assert(
    'nonce inválido sin gate ni UC',
    CreateExpedienteRegistroUseCase::$calls === 0
    && ExpedienteRegistrosAjax::$gate_calls === 0
);

aa_reset_by_exp_ajax();
ExpedienteRegistrosAjax::$access = 'free';
$denied_gate = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('gate ≠ full → 403', ($denied_gate['status'] ?? 0) === 403
    && ($denied_gate['data']['code'] ?? '') === 'expediente_access_denied');
ac_assert('gate ≠ full sin UC', CreateExpedienteRegistroUseCase::$calls === 0);
ac_assert('gate sí se consultó', ExpedienteRegistrosAjax::$gate_calls === 1);

aa_reset_by_exp_ajax();
$_POST = [
    'expediente_id' => '7',
    'title' => '  Nota <b>x</b>  ',
    'body' => "  Línea 1\nLínea 2  ",
    'client_id' => '999',
    'blog_id' => '3',
    'recorded_at' => '1999-01-01 00:00:00',
    'created_at' => '1999-01-01 00:00:00',
    'id' => '1',
];
$ok = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('autorización completa ejecuta UC', CreateExpedienteRegistroUseCase::$calls === 1
    && ExpedienteRegistrosAjax::$gate_calls === 1);
ac_assert('éxito → data.record', ($ok['success'] ?? false) === true
    && is_array($ok['data']['record'] ?? null));
ac_assert('expediente_id sin coerción (string canónico)', (CreateExpedienteRegistroUseCase::$last_input['expediente_id'] ?? null) === '7');
ac_assert('title saneado', (CreateExpedienteRegistroUseCase::$last_input['title'] ?? '') === 'Nota x');
ac_assert('body saneado con saltos', (CreateExpedienteRegistroUseCase::$last_input['body'] ?? '') === "Línea 1\nLínea 2");
ac_assert(
    'ignora client_id/blog_id/fechas/id',
    !array_key_exists('client_id', CreateExpedienteRegistroUseCase::$last_input ?? [])
    && !array_key_exists('blog_id', CreateExpedienteRegistroUseCase::$last_input ?? [])
    && !array_key_exists('recorded_at', CreateExpedienteRegistroUseCase::$last_input ?? [])
    && !array_key_exists('created_at', CreateExpedienteRegistroUseCase::$last_input ?? [])
    && !array_key_exists('id', CreateExpedienteRegistroUseCase::$last_input ?? [])
);
ac_assert(
    'payload público sin owners',
    !array_key_exists('client_id', $ok['data']['record'] ?? [])
    && !array_key_exists('expediente_id', $ok['data']['record'] ?? [])
    && !array_key_exists('blog_id', $ok['data']['record'] ?? [])
);

aa_reset_by_exp_ajax();
$_POST = [
    'expediente_id' => 7,
    'title' => 'A',
    'body' => 'B',
];
aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('expediente_id int llega intacto', (CreateExpedienteRegistroUseCase::$last_input['expediente_id'] ?? null) === 7);

aa_reset_by_exp_ajax();
$_POST = [
    'expediente_id' => ['7'],
    'title' => ['x'],
    'body' => (object) ['b' => 1],
];
$non_scalar = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
$input = CreateExpedienteRegistroUseCase::$last_input;
ac_assert(
    'arrays/objetos → null al UC sin warnings',
    is_array($input)
    && array_key_exists('expediente_id', $input)
    && $input['expediente_id'] === null
    && array_key_exists('title', $input)
    && $input['title'] === null
    && array_key_exists('body', $input)
    && $input['body'] === null
    && $GLOBALS['aa_test_warnings'] === []
);
ac_assert('no-escalares aún delegan al UC (una vez)', CreateExpedienteRegistroUseCase::$calls === 1);

aa_reset_by_exp_ajax();
CreateExpedienteRegistroUseCase::$result = [
    'success' => false,
    'error' => ['code' => 'invalid_id', 'message' => 'Expediente no válido.'],
];
$_POST = ['expediente_id' => '01', 'title' => 'A', 'body' => 'B'];
$invalid_id = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('ID inválido → 400', ($invalid_id['status'] ?? 0) === 400
    && ($invalid_id['data']['code'] ?? '') === 'invalid_id');

aa_reset_by_exp_ajax();
CreateExpedienteRegistroUseCase::$result = [
    'success' => false,
    'error' => ['code' => 'not_found', 'message' => 'Expediente no encontrado.'],
];
$_POST = ['expediente_id' => '7', 'title' => 'A', 'body' => 'B'];
$missing = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('inexistente → 404', ($missing['status'] ?? 0) === 404
    && ($missing['data']['code'] ?? '') === 'not_found');

aa_reset_by_exp_ajax();
CreateExpedienteRegistroUseCase::$result = [
    'success' => false,
    'error' => ['code' => 'lookup_failed', 'message' => 'No se pudo verificar el expediente.'],
];
$lookup = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('lookup_failed → 500', ($lookup['status'] ?? 0) === 500
    && ($lookup['data']['code'] ?? '') === 'lookup_failed');

aa_reset_by_exp_ajax();
CreateExpedienteRegistroUseCase::$result = [
    'success' => false,
    'error' => ['code' => 'persistence_failed', 'message' => 'Error al guardar el registro.'],
];
$_POST = ['expediente_id' => '7', 'title' => 'A', 'body' => 'B'];
$persist = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert('persistence_failed → 500', ($persist['status'] ?? 0) === 500
    && ($persist['data']['code'] ?? '') === 'persistence_failed');

aa_reset_by_exp_ajax();
CreateExpedienteRegistroUseCase::$result = [
    'success' => true,
    'data' => [
        'record' => [
            'id' => 9,
            'title' => 'X',
            'body' => 'Y',
            'recorded_at' => '2026-08-20 12:00:00',
            'created_at' => '2026-08-20 12:00:00',
            'updated_at' => null,
            'client_id' => null,
            'expediente_id' => 7,
            'blog_id' => 1,
        ],
    ],
];
$_POST = ['expediente_id' => '7', 'title' => 'X', 'body' => 'Y'];
$stripped = aa_invoke_by_exp_ajax([ExpedienteRegistrosByExpedienteAjax::class, 'handle_create']);
ac_assert(
    'éxito elimina owners del payload aunque UC los trajera',
    ($stripped['success'] ?? false) === true
    && !array_key_exists('client_id', $stripped['data']['record'] ?? [])
    && !array_key_exists('expediente_id', $stripped['data']['record'] ?? [])
    && !array_key_exists('blog_id', $stripped['data']['record'] ?? [])
    && ($stripped['data']['record']['id'] ?? 0) === 9
);

restore_error_handler();

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
