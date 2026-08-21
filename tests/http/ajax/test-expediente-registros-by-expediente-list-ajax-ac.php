<?php
/**
 * AC — ExpedienteRegistrosByExpedienteAjax::handle_list (B2b + adjuntos públicos).
 *
 * Ejecutar: php tests/http/ajax/test-expediente-registros-by-expediente-list-ajax-ac.php
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
$uc_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/ListExpedienteRegistrosWithPublicAdjuntosUseCase.php'
);
$list_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/ListExpedienteRegistrosUseCase.php'
);
$detail_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/expedientes/detail.php');

ac_assert('ACTION_LIST en fuente', strpos($ajax_src, "ACTION_LIST = 'aa_list_expediente_registros_for_expediente'") !== false);
ac_assert('handle_list en fuente', strpos($ajax_src, 'function handle_list') !== false);
ac_assert('register lista wp_ajax_ list', strpos($ajax_src, 'ACTION_LIST') !== false
    && strpos($ajax_src, "add_action('wp_ajax_' . self::ACTION_LIST") !== false);
ac_assert('sin nopriv', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert(
    'handler thin: sin AdjuntosRepository / exists_by_id',
    strpos($ajax_src, 'ExpedienteAdjuntosRepository') === false
    && strpos($ajax_src, 'exists_by_id') === false
);
ac_assert(
    'handler delega UC enriquecido',
    strpos($ajax_src, 'ListExpedienteRegistrosWithPublicAdjuntosUseCase') !== false
);
ac_assert('sin GetExpedienteUseCase', strpos($ajax_src, 'GetExpedienteUseCase') === false);
ac_assert('sin absint', strpos($ajax_src, 'absint(') === false);
ac_assert('no lee client_id', strpos($ajax_src, "\$_POST['client_id']") === false
    && strpos($ajax_src, "\$_REQUEST['client_id']") === false);
ac_assert(
    'detail monta listado canónico (≠ create rico adoptado)',
    strpos($detail_src, 'aa_list_expediente_registros_for_expediente') !== false
    && strpos($detail_src, 'listRegistros') !== false
    && strpos($detail_src, 'expediente-registros-canonical-adapter.js') !== false
    && strpos($detail_src, 'expediente-registros.js') !== false
    && strpos($detail_src, 'expediente-registros-canonical-mount.js') !== false
    && strpos($detail_src, 'expediente-registro-create-modal.js') !== false
    && strpos($detail_src, 'ExpedienteRegistros.openCreate') === false
    && strpos($detail_src, 'onCreateComplete') === false
    && strpos($detail_src, 'executable-options-menu-placement') === false
);
$index_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/index.php');
ac_assert(
    'SSR textual sin UC enriquecido',
    strpos($detail_src, 'WithPublicAdjuntos') === false
    && strpos($detail_src, 'ListExpedienteRegistrosWithPublicAdjuntosUseCase') === false
    && strpos($list_src, 'WithPublicAdjuntos') === false
    && strpos($index_src, 'ListExpedienteRegistrosWithPublicAdjuntosUseCase') === false
    && strpos($index_src, 'ListExpedienteRegistrosUseCase') !== false
);
ac_assert(
    'UC enriquecido: exists + owner + bulk + DTO',
    strpos($uc_src, 'exists_by_id') !== false
    && strpos($uc_src, 'find_owner_context_by_id') !== false
    && strpos($uc_src, 'list_by_record_ids') !== false
    && strpos($uc_src, 'ExpedienteAdjuntoPublicDto') !== false
);

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
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
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

final class CreateExpedienteRegistroUseCase {
    public function execute(array $input): array {
        return ['success' => false, 'error' => ['code' => 'unused', 'message' => 'unused']];
    }
}

final class UpdateExpedienteRegistroForExpedienteUseCase {
    public function execute(array $input): array {
        return ['success' => false, 'error' => ['code' => 'unused', 'message' => 'unused']];
    }
}

final class DeleteExpedienteRegistroForExpedienteUseCase {
    public function execute(array $input): array {
        return ['success' => false, 'error' => ['code' => 'unused', 'message' => 'unused']];
    }
}

final class ExpedientesRepository {
    /** @var bool|null */
    public static $exists_result = true;
    public static $exists_calls = 0;
    /** @var int|null */
    public static $last_exists_id = null;
    public static $find_by_id_calls = 0;

    /** @var array{id:int,client_id:?int}|null */
    public static $owner = ['id' => 7, 'client_id' => 55];
    public static $owner_calls = 0;

    public static function exists_by_id(int $id) {
        self::$exists_calls++;
        self::$last_exists_id = $id;
        return self::$exists_result;
    }

    public static function find_by_id(int $id): ?array {
        self::$find_by_id_calls++;
        return null;
    }

    public static function find_owner_context_by_id(int $id): ?array {
        self::$owner_calls++;
        return self::$owner;
    }
}

final class ListExpedienteRegistrosUseCase {
    public static $calls = 0;
    /** @var array<string,mixed>|null */
    public static $last_input = null;
    /** @var array<string,mixed> */
    public static $result = [
        'success' => true,
        'data' => [
            'records' => [],
            'page' => 1,
            'per_page' => 15,
            'total' => 0,
            'total_pages' => 0,
            'has_previous' => false,
            'has_next' => false,
        ],
    ];

    public function execute(array $input): array {
        self::$calls++;
        self::$last_input = $input;
        return self::$result;
    }
}

final class ExpedienteAdjuntosRepository {
    public static $calls = 0;
    /** @var list<array{record_ids:array,client_id:int}> */
    public static $args = [];
    /** @var array<int,list<array<string,mixed>>> */
    public static $by_record = [];

    /**
     * @param list<int> $record_ids
     * @return array<int,list<array<string,mixed>>>
     */
    public static function list_by_record_ids(array $record_ids, int $client_id): array {
        self::$calls++;
        self::$args[] = [
            'record_ids' => $record_ids,
            'client_id' => $client_id,
        ];
        return self::$by_record;
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoPublicDto.php';
require_once $plugin_root . '/includes/http/ajax/ExpedienteRegistrosByExpedienteAjax.php';

ac_assert(
    'constants list',
    ExpedienteRegistrosByExpedienteAjax::ACTION_LIST === 'aa_list_expediente_registros_for_expediente'
    && ExpedienteRegistrosByExpedienteAjax::NONCE_ACTION === 'aa_expediente_registros_by_expediente_nonce'
);

$GLOBALS['aa_test_actions'] = [];
ExpedienteRegistrosByExpedienteAjax::register();
ac_assert(
    'register create + list una vez cada uno',
    count(array_keys($GLOBALS['aa_test_actions'], 'wp_ajax_aa_create_expediente_registro_for_expediente', true)) === 1
    && count(array_keys($GLOBALS['aa_test_actions'], 'wp_ajax_aa_list_expediente_registros_for_expediente', true)) === 1
);

/**
 * @return array<string,mixed>|null
 */
function aa_invoke_list(): ?array {
    $GLOBALS['aa_test_json'] = null;
    try {
        ExpedienteRegistrosByExpedienteAjax::handle_list();
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

function aa_sample_records(): array {
    return [
        [
            'id' => 10,
            'title' => 'A',
            'body' => 'Body A',
            'recorded_at' => '2026-08-20 12:00:00',
            'created_at' => '2026-08-20 12:00:00',
            'updated_at' => null,
        ],
        [
            'id' => 9,
            'title' => 'B',
            'body' => 'Body B',
            'recorded_at' => '2026-08-19 10:00:00',
            'created_at' => '2026-08-19 10:00:00',
            'updated_at' => null,
        ],
    ];
}

function aa_reset_list(): void {
    $_POST = [];
    ExpedienteRegistrosAjax::$gate_calls = 0;
    ExpedienteRegistrosAjax::$access = 'full';
    ExpedientesRepository::$exists_result = true;
    ExpedientesRepository::$exists_calls = 0;
    ExpedientesRepository::$last_exists_id = null;
    ExpedientesRepository::$find_by_id_calls = 0;
    ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
    ExpedientesRepository::$owner_calls = 0;
    ListExpedienteRegistrosUseCase::$calls = 0;
    ListExpedienteRegistrosUseCase::$last_input = null;
    ListExpedienteRegistrosUseCase::$result = [
        'success' => true,
        'data' => [
            'records' => [],
            'page' => 1,
            'per_page' => 15,
            'total' => 0,
            'total_pages' => 0,
            'has_previous' => false,
            'has_next' => false,
        ],
    ];
    ExpedienteAdjuntosRepository::$calls = 0;
    ExpedienteAdjuntosRepository::$args = [];
    ExpedienteAdjuntosRepository::$by_record = [];
    $GLOBALS['aa_test_can_manage_options'] = true;
    $GLOBALS['aa_test_nonce_valid'] = true;
    $GLOBALS['aa_test_json'] = null;
    $GLOBALS['aa_test_warnings'] = [];
}

// --- Orden de seguridad ---

aa_reset_list();
$GLOBALS['aa_test_can_manage_options'] = false;
$denied_cap = aa_invoke_list();
ac_assert('cap → 403', ($denied_cap['status'] ?? 0) === 403);
ac_assert(
    'cap antes de nonce/gate/exists/UC',
    ExpedienteRegistrosAjax::$gate_calls === 0
    && ExpedientesRepository::$exists_calls === 0
    && ListExpedienteRegistrosUseCase::$calls === 0
    && ExpedienteAdjuntosRepository::$calls === 0
);

aa_reset_list();
$GLOBALS['aa_test_nonce_valid'] = false;
$denied_nonce = aa_invoke_list();
ac_assert('nonce → 403 bad_nonce', ($denied_nonce['status'] ?? 0) === 403
    && ($denied_nonce['data']['code'] ?? '') === 'bad_nonce');
ac_assert(
    'nonce inválido sin gate/exists/UC',
    ExpedienteRegistrosAjax::$gate_calls === 0
    && ExpedientesRepository::$exists_calls === 0
    && ListExpedienteRegistrosUseCase::$calls === 0
);

aa_reset_list();
ExpedienteRegistrosAjax::$access = 'free';
$_POST = ['expediente_id' => '7'];
$denied_gate = aa_invoke_list();
ac_assert('gate → 403', ($denied_gate['status'] ?? 0) === 403
    && ($denied_gate['data']['code'] ?? '') === 'expediente_access_denied');
ac_assert('gate consultado; sin exists/UC', ExpedienteRegistrosAjax::$gate_calls === 1
    && ExpedientesRepository::$exists_calls === 0
    && ListExpedienteRegistrosUseCase::$calls === 0);

// --- Entrada ID ---

aa_reset_list();
$_POST = ['expediente_id' => '01'];
$bad01 = aa_invoke_list();
ac_assert('01 → 400 invalid_id', ($bad01['status'] ?? 0) === 400
    && ($bad01['data']['code'] ?? '') === 'invalid_id');
ac_assert('01 sin exists/UC', ExpedientesRepository::$exists_calls === 0
    && ListExpedienteRegistrosUseCase::$calls === 0);

aa_reset_list();
$_POST = ['expediente_id' => '0'];
ac_assert('0 → 400', ((aa_invoke_list()['status'] ?? 0) === 400));

aa_reset_list();
$_POST = ['expediente_id' => '-3'];
ac_assert('negativo string → 400', ((aa_invoke_list()['status'] ?? 0) === 400));

aa_reset_list();
$_POST = ['expediente_id' => '+1'];
ac_assert('+1 → 400', ((aa_invoke_list()['status'] ?? 0) === 400));

aa_reset_list();
$_POST = ['expediente_id' => '7.5'];
ac_assert('decimal → 400', ((aa_invoke_list()['status'] ?? 0) === 400));

aa_reset_list();
$_POST = ['expediente_id' => ['7']];
$arr = aa_invoke_list();
ac_assert('array → 400 sin warnings', ($arr['status'] ?? 0) === 400
    && $GLOBALS['aa_test_warnings'] === []);

aa_reset_list();
$_POST = ['expediente_id' => (object) ['id' => 7]];
ac_assert('objeto → 400', ((aa_invoke_list()['status'] ?? 0) === 400));

aa_reset_list();
$missing = aa_invoke_list();
ac_assert('ausente → 400 invalid_id', ($missing['status'] ?? 0) === 400
    && ($missing['data']['code'] ?? '') === 'invalid_id');

// --- Padre ---

aa_reset_list();
ExpedientesRepository::$exists_result = false;
$_POST = ['expediente_id' => '7'];
$nf = aa_invoke_list();
ac_assert('inexistente → 404 not_found', ($nf['status'] ?? 0) === 404
    && ($nf['data']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin UC list/bulk', ListExpedienteRegistrosUseCase::$calls === 0
    && ExpedienteAdjuntosRepository::$calls === 0
    && ExpedientesRepository::$exists_calls === 1
    && ExpedientesRepository::$owner_calls === 0
    && ExpedientesRepository::$find_by_id_calls === 0);

aa_reset_list();
ExpedientesRepository::$exists_result = null;
$_POST = ['expediente_id' => '7'];
$lookup = aa_invoke_list();
ac_assert('exists null → 500 lookup_failed', ($lookup['status'] ?? 0) === 500
    && ($lookup['data']['code'] ?? '') === 'lookup_failed');
ac_assert('lookup sin UC list', ListExpedienteRegistrosUseCase::$calls === 0
    && ExpedientesRepository::$owner_calls === 0);

aa_reset_list();
ExpedientesRepository::$owner = null;
$_POST = ['expediente_id' => '7'];
$ownerNull = aa_invoke_list();
ac_assert('owner nulo tras exists → 500', ($ownerNull['status'] ?? 0) === 500
    && ($ownerNull['data']['code'] ?? '') === 'lookup_failed');
ac_assert('owner nulo sin list/bulk', ListExpedienteRegistrosUseCase::$calls === 0
    && ExpedienteAdjuntosRepository::$calls === 0
    && ExpedientesRepository::$owner_calls === 1);

// --- Éxito cliente + adjuntos ---

aa_reset_list();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => aa_sample_records(),
        'page' => 1,
        'per_page' => 15,
        'total' => 2,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
ExpedienteAdjuntosRepository::$by_record = [
    10 => [
        [
            'id' => 302,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => 'op-302',
            'storage_path' => '/clients/55/records/10/302.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 2048,
            'width' => 800,
            'height' => 600,
            'created_at' => '2026-08-20 13:00:00',
        ],
        [
            'id' => 301,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => 'op-301',
            'storage_path' => '/clients/55/records/10/301.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 1024,
            'width' => 400,
            'height' => 300,
            'created_at' => '2026-08-20 12:30:00',
        ],
    ],
    9 => [
        [
            'id' => 201,
            'record_id' => 9,
            'client_id' => 55,
            'storage_path' => '/secret',
            'mime_type' => 'image/jpeg',
            'byte_size' => 512,
            'width' => 100,
            'height' => 80,
            'created_at' => '2026-08-19 11:00:00',
        ],
    ],
    99 => [
        [
            'id' => 999,
            'record_id' => 99,
            'client_id' => 55,
            'byte_size' => 1,
            'width' => 1,
            'height' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'storage_path' => '/other',
        ],
    ],
];
$_POST = [
    'expediente_id' => '7',
    'page' => '1',
    'client_id' => '999',
    'blog_id' => '3',
];
$ok = aa_invoke_list();
ac_assert('éxito 200', ($ok['success'] ?? false) === true && ($ok['status'] ?? 0) === 200);
ac_assert(
    'exists + owner + list UC una vez; bulk una vez',
    ExpedientesRepository::$exists_calls === 1
    && ExpedientesRepository::$owner_calls === 1
    && ListExpedienteRegistrosUseCase::$calls === 1
    && ExpedienteAdjuntosRepository::$calls === 1
    && ExpedientesRepository::$last_exists_id === 7
);
ac_assert(
    'UC recibe expediente_id int normalizado y page',
    (ListExpedienteRegistrosUseCase::$last_input['expediente_id'] ?? null) === 7
    && (ListExpedienteRegistrosUseCase::$last_input['page'] ?? null) === '1'
);
ac_assert(
    'ignora client_id POST (bulk usa owner 55)',
    !array_key_exists('client_id', ListExpedienteRegistrosUseCase::$last_input ?? [])
    && (ExpedienteAdjuntosRepository::$args[0]['client_id'] ?? 0) === 55
    && (ExpedienteAdjuntosRepository::$args[0]['record_ids'] ?? null) === [10, 9]
);
ac_assert('metadata presente', ($ok['data']['per_page'] ?? 0) === 15
    && ($ok['data']['total'] ?? -1) === 2
    && ($ok['data']['page'] ?? 0) === 1);
ac_assert('records count', count($ok['data']['records'] ?? []) === 2);

$first = $ok['data']['records'][0] ?? [];
$second = $ok['data']['records'][1] ?? [];
ac_assert(
    'campos textuales preservados + adjuntos/adjunto',
    ($first['id'] ?? 0) === 10
    && ($first['title'] ?? '') === 'A'
    && ($first['body'] ?? '') === 'Body A'
    && array_key_exists('adjuntos', $first)
    && array_key_exists('adjunto', $first)
    && !array_key_exists('client_id', $first)
    && !array_key_exists('expediente_id', $first)
    && !array_key_exists('blog_id', $first)
);
ac_assert('varios adjuntos id DESC', count($first['adjuntos'] ?? []) === 2
    && ($first['adjuntos'][0]['id'] ?? 0) === 302
    && ($first['adjuntos'][1]['id'] ?? 0) === 301);
ac_assert('adjunto === adjuntos[0]', ($first['adjunto'] ?? null) === ($first['adjuntos'][0] ?? null));
ac_assert('agrupa por record_id', count($second['adjuntos'] ?? []) === 1
    && ($second['adjunto']['id'] ?? 0) === 201);
ac_assert(
    'DTO solo cinco campos públicos',
    array_keys($first['adjuntos'][0]) === ['id', 'width', 'height', 'byte_size', 'created_at']
);
$blob = json_encode($ok['data']);
ac_assert(
    'sin owners/paths/tokens/URLs firmadas',
    strpos($blob, 'storage_path') === false
    && strpos($blob, 'upload_operation_id') === false
    && strpos($blob, 'mime_type') === false
    && strpos($blob, '/clients/') === false
    && strpos($blob, 'signed') === false
    && strpos($blob, '"client_id"') === false
);

// --- Registro sin adjuntos ---

aa_reset_list();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => [
            [
                'id' => 11,
                'title' => 'Solo',
                'body' => 'x',
                'recorded_at' => '2026-08-01 00:00:00',
                'created_at' => '2026-08-01 00:00:00',
                'updated_at' => null,
            ],
        ],
        'page' => 1,
        'per_page' => 15,
        'total' => 1,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
ExpedienteAdjuntosRepository::$by_record = [];
$_POST = ['expediente_id' => '7'];
$noAdj = aa_invoke_list();
$nr = $noAdj['data']['records'][0] ?? [];
ac_assert('registro sin adjuntos → vacíos', ($nr['adjuntos'] ?? null) === []
    && array_key_exists('adjunto', $nr) && $nr['adjunto'] === null
    && ($noAdj['status'] ?? 0) === 200);

// --- Expediente general ---

aa_reset_list();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => aa_sample_records(),
        'page' => 1,
        'per_page' => 15,
        'total' => 2,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
$_POST = ['expediente_id' => '7', 'client_id' => '55'];
$general = aa_invoke_list();
ac_assert('general → 200 sin bulk', ($general['status'] ?? 0) === 200
    && ExpedienteAdjuntosRepository::$calls === 0);
ac_assert(
    'general colecciones vacías',
    ($general['data']['records'][0]['adjuntos'] ?? null) === []
    && array_key_exists('adjunto', $general['data']['records'][0])
    && $general['data']['records'][0]['adjunto'] === null
);

// --- Página vacía ---

aa_reset_list();
$_POST = ['expediente_id' => '7'];
$empty = aa_invoke_list();
ac_assert('página vacía → 200', ($empty['success'] ?? false) === true
    && ($empty['data']['records'] ?? null) === []);
ac_assert('página vacía sin bulk', ExpedienteAdjuntosRepository::$calls === 0);

// --- Bulk fail-soft ---

aa_reset_list();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => aa_sample_records(),
        'page' => 1,
        'per_page' => 15,
        'total' => 2,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
ExpedienteAdjuntosRepository::$by_record = [];
$_POST = ['expediente_id' => '7'];
$soft = aa_invoke_list();
ac_assert('bulk vacío fail-soft → 200', ($soft['status'] ?? 0) === 200
    && ($soft['data']['records'][0]['adjuntos'] ?? null) === []
    && array_key_exists('adjunto', $soft['data']['records'][0])
    && $soft['data']['records'][0]['adjunto'] === null);

aa_reset_list();
$_POST = ['expediente_id' => 42];
aa_invoke_list();
ac_assert('page ausente → UC page=1', (ListExpedienteRegistrosUseCase::$last_input['page'] ?? null) === 1
    && ExpedientesRepository::$last_exists_id === 42);

aa_reset_list();
$_POST = ['expediente_id' => '7', 'page' => ['2']];
aa_invoke_list();
ac_assert('page array → 1 sin warnings', (ListExpedienteRegistrosUseCase::$last_input['page'] ?? null) === 1
    && $GLOBALS['aa_test_warnings'] === []);

aa_reset_list();
$_POST = ['expediente_id' => '7', 'page' => '2'];
aa_invoke_list();
ac_assert('page string canónica llega al UC', (ListExpedienteRegistrosUseCase::$last_input['page'] ?? null) === '2');

aa_reset_list();
ListExpedienteRegistrosUseCase::$result = [
    'success' => false,
    'error' => ['code' => 'invalid_id', 'message' => 'Expediente no válido.'],
];
$_POST = ['expediente_id' => '7'];
$ucFail = aa_invoke_list();
ac_assert('UC fail mapea HTTP', ($ucFail['status'] ?? 0) === 400
    && ($ucFail['data']['code'] ?? '') === 'invalid_id');

restore_error_handler();

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
