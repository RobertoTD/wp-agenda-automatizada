<?php
/**
 * AC — ExpedientesAjax (listado paginado + alta de expedientes padre).
 *
 * Ejecutar: php tests/http/ajax/test-expedientes-ajax-ac.php
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

$ajax_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/ExpedientesAjax.php');
$bootstrap_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

ac_assert('ajax file readable', $ajax_src !== '');
ac_assert('ACTION_LIST aa_list_expedientes', strpos($ajax_src, 'aa_list_expedientes') !== false);
ac_assert('ACTION_CREATE aa_create_expediente', strpos($ajax_src, 'aa_create_expediente') !== false);
ac_assert('nonce propio aa_expedientes_nonce', strpos($ajax_src, 'aa_expedientes_nonce') !== false);
ac_assert('manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('reutiliza gate full', strpos($ajax_src, 'ExpedienteRegistrosAjax::require_expediente_shell_access') !== false);
ac_assert('sin nopriv', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('bootstrap register', strpos($bootstrap_src, 'ExpedientesAjax::register()') !== false);
ac_assert('bootstrap require', strpos($bootstrap_src, 'includes/http/ajax/ExpedientesAjax.php') !== false);
ac_assert('bootstrap sin nopriv padre', strpos($bootstrap_src, 'wp_ajax_nopriv_aa_list_expedientes') === false
    && strpos($bootstrap_src, 'wp_ajax_nopriv_aa_create_expediente') === false);
ac_assert('delega ListExpedientesUseCase', strpos($ajax_src, 'ListExpedientesUseCase') !== false);
ac_assert('delega CreateExpedienteUseCase', strpos($ajax_src, 'CreateExpedienteUseCase') !== false);
ac_assert('sin GetExpedienteUseCase ni AJAX de detalle', strpos($ajax_src, 'GetExpedienteUseCase') === false);
ac_assert('responde via respond_use_case', strpos($ajax_src, 'respond_use_case($result)') !== false);
ac_assert('sin $wpdb en handler', strpos($ajax_src, '$wpdb') === false);
ac_assert('sin TITLE_MAX ni trim de negocio', strpos($ajax_src, 'TITLE_MAX') === false
    && strpos($ajax_src, 'GENERAL_SLUG') === false);
ac_assert('list no lee per_page', strpos($ajax_src, "post_string('per_page')") === false
    && strpos($ajax_src, "post_scalar('per_page')") === false
    && strpos($ajax_src, "\$_POST['per_page']") === false
    && strpos($ajax_src, "\$_REQUEST['per_page']") === false);
ac_assert('list no lee limit/offset', strpos($ajax_src, "\$_POST['limit']") === false
    && strpos($ajax_src, "\$_POST['offset']") === false);
ac_assert('no acepta blog_id', strpos($ajax_src, "\$_POST['blog_id']") === false
    && strpos($ajax_src, "\$_REQUEST['blog_id']") === false
    && strpos($ajax_src, "\$_GET['blog_id']") === false);
ac_assert('no acepta client_id', strpos($ajax_src, "\$_POST['client_id']") === false
    && strpos($ajax_src, "\$_REQUEST['client_id']") === false);
ac_assert('no acepta category_id', strpos($ajax_src, "\$_POST['category_id']") === false
    && strpos($ajax_src, "\$_REQUEST['category_id']") === false);
ac_assert('no acepta timestamps del cliente', strpos($ajax_src, "\$_POST['created_at']") === false
    && strpos($ajax_src, "\$_POST['recorded_at']") === false
    && strpos($ajax_src, "\$_POST['updated_at']") === false);
ac_assert('create lee title / description / category_slug', strpos($ajax_src, "post_string('title')") !== false
    && strpos($ajax_src, "post_textarea('description')") !== false
    && strpos($ajax_src, "post_string('category_slug')") !== false);
ac_assert('list lee solo query y page', strpos($ajax_src, "post_string('query')") !== false
    && strpos($ajax_src, "post_scalar('page')") !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['aa_test_json'] = null;
$GLOBALS['aa_test_can_manage_options'] = true;
$GLOBALS['aa_test_nonce_valid'] = true;
$GLOBALS['aa_test_actions'] = [];
$GLOBALS['aa_test_shell_full'] = true;

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return $capability === 'manage_options' && !empty($GLOBALS['aa_test_can_manage_options']);
    }
}
if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg) {
        if ($action !== ExpedientesAjax::NONCE_ACTION || $query_arg !== '_wpnonce' || empty($GLOBALS['aa_test_nonce_valid'])) {
            throw new RuntimeException('bad_nonce');
        }
    }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = ['success' => true, 'data' => $data, 'status' => $status_code];
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
        return trim((string) $str);
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim((string) $str);
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
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

final class ListExpedientesUseCase {
    /** @var array<string,mixed>|null */
    public static $last_input = null;
    public static $calls = 0;
    /** @var array<string,mixed> */
    public static $result = [
        'success' => true,
        'data' => [
            'expedientes' => [],
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

final class CreateExpedienteUseCase {
    /** @var array<string,mixed>|null */
    public static $last_input = null;
    public static $calls = 0;
    /** @var array<string,mixed> */
    public static $result = [
        'success' => true,
        'data' => [
            'id' => 7,
            'title' => 'Contrato',
            'description' => null,
            'category' => [
                'slug' => 'general',
                'name' => 'General',
            ],
            'created_at' => '2026-08-17 13:00:00',
        ],
    ];

    public function execute(array $input): array {
        self::$calls++;
        self::$last_input = $input;
        return self::$result;
    }
}

require_once $plugin_root . '/includes/http/ajax/ExpedientesAjax.php';

ac_assert('class exists', class_exists('ExpedientesAjax'));
ac_assert('constants', ExpedientesAjax::ACTION_LIST === 'aa_list_expedientes'
    && ExpedientesAjax::ACTION_CREATE === 'aa_create_expediente'
    && ExpedientesAjax::NONCE_ACTION === 'aa_expedientes_nonce');

ExpedientesAjax::register();
ac_assert('register wp_ajax_aa_list_expedientes', in_array('wp_ajax_aa_list_expedientes', $GLOBALS['aa_test_actions'], true));
ac_assert('register wp_ajax_aa_create_expediente', in_array('wp_ajax_aa_create_expediente', $GLOBALS['aa_test_actions'], true));
ac_assert('register no nopriv', !in_array('wp_ajax_nopriv_aa_list_expedientes', $GLOBALS['aa_test_actions'], true)
    && !in_array('wp_ajax_nopriv_aa_create_expediente', $GLOBALS['aa_test_actions'], true));

/**
 * @return array<string,mixed>|null
 */
function aa_invoke_expedientes_ajax(callable $handler): ?array {
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

function aa_reset_expedientes_ajax_runtime(): void {
    $_POST = [];
    ExpedienteRegistrosAjax::$gate_calls = 0;
    ExpedienteRegistrosAjax::$access = 'full';
    ListExpedientesUseCase::$last_input = null;
    ListExpedientesUseCase::$calls = 0;
    CreateExpedienteUseCase::$last_input = null;
    CreateExpedienteUseCase::$calls = 0;
    $GLOBALS['aa_test_can_manage_options'] = true;
    $GLOBALS['aa_test_nonce_valid'] = true;
    $GLOBALS['aa_test_json'] = null;
}

aa_reset_expedientes_ajax_runtime();
$GLOBALS['aa_test_can_manage_options'] = false;
$denied_cap = aa_invoke_expedientes_ajax([ExpedientesAjax::class, 'handle_list']);
ac_assert('capability insuficiente → 403', ($denied_cap['status'] ?? 0) === 403);
ac_assert('capability insuficiente no ejecuta list', ListExpedientesUseCase::$calls === 0 && ExpedienteRegistrosAjax::$gate_calls === 0);

aa_reset_expedientes_ajax_runtime();
$GLOBALS['aa_test_can_manage_options'] = false;
$denied_cap_create = aa_invoke_expedientes_ajax([ExpedientesAjax::class, 'handle_create']);
ac_assert('capability create insuficiente no ejecuta', CreateExpedienteUseCase::$calls === 0 && ($denied_cap_create['status'] ?? 0) === 403);

aa_reset_expedientes_ajax_runtime();
$GLOBALS['aa_test_nonce_valid'] = false;
$denied_nonce = aa_invoke_expedientes_ajax([ExpedientesAjax::class, 'handle_list']);
ac_assert('nonce inválido rechazado', ($denied_nonce['data']['code'] ?? '') === 'bad_nonce');
ac_assert('nonce inválido no ejecuta use case ni gate', ListExpedientesUseCase::$calls === 0 && ExpedienteRegistrosAjax::$gate_calls === 0);

aa_reset_expedientes_ajax_runtime();
ExpedienteRegistrosAjax::$access = 'free';
$denied_free = aa_invoke_expedientes_ajax([ExpedientesAjax::class, 'handle_list']);
ac_assert('gate free → 403', ($denied_free['status'] ?? 0) === 403
    && ($denied_free['data']['code'] ?? '') === 'expediente_access_denied');
ac_assert('gate free no lista', ListExpedientesUseCase::$calls === 0);

aa_reset_expedientes_ajax_runtime();
ExpedienteRegistrosAjax::$access = 'legal_gate';
$denied_legal = aa_invoke_expedientes_ajax([ExpedientesAjax::class, 'handle_create']);
ac_assert('gate legal_gate → 403', ($denied_legal['status'] ?? 0) === 403
    && ($denied_legal['data']['code'] ?? '') === 'expediente_access_denied');
ac_assert('gate legal_gate no crea', CreateExpedienteUseCase::$calls === 0);

aa_reset_expedientes_ajax_runtime();
$_POST = [
    'query' => '  Contrato  ',
    'page' => '2',
    'per_page' => '100',
    'limit' => '50',
    'offset' => '30',
    'blog_id' => '99',
];
$list_ok = aa_invoke_expedientes_ajax([ExpedientesAjax::class, 'handle_list']);
ac_assert('full permite listar', ($list_ok['success'] ?? false) === true && ListExpedientesUseCase::$calls === 1);
ac_assert('list normaliza query', (ListExpedientesUseCase::$last_input['query'] ?? '') === 'Contrato');
ac_assert('list pasa page', (string) (ListExpedientesUseCase::$last_input['page'] ?? '') === '2');
ac_assert('list ignora per_page', !array_key_exists('per_page', ListExpedientesUseCase::$last_input ?? []));
ac_assert('list ignora limit/offset/blog_id', !array_key_exists('limit', ListExpedientesUseCase::$last_input ?? [])
    && !array_key_exists('offset', ListExpedientesUseCase::$last_input ?? [])
    && !array_key_exists('blog_id', ListExpedientesUseCase::$last_input ?? []));
ac_assert(
    'contrato paginación completo',
    array_key_exists('expedientes', $list_ok['data'] ?? [])
    && ($list_ok['data']['page'] ?? null) === 1
    && ($list_ok['data']['per_page'] ?? null) === 15
    && array_key_exists('total', $list_ok['data'] ?? [])
    && array_key_exists('total_pages', $list_ok['data'] ?? [])
    && array_key_exists('has_previous', $list_ok['data'] ?? [])
    && array_key_exists('has_next', $list_ok['data'] ?? [])
);

aa_reset_expedientes_ajax_runtime();
$_POST = [
    'title' => '  Contrato  ',
    'description' => '  ',
    'category_id' => '9',
    'client_id' => '4',
    'created_at' => '1999-01-01 00:00:00',
    'blog_id' => '5',
];
$create_ok = aa_invoke_expedientes_ajax([ExpedientesAjax::class, 'handle_create']);
ac_assert('full permite crear', ($create_ok['success'] ?? false) === true && CreateExpedienteUseCase::$calls === 1);
ac_assert('create pasa title saneado', (CreateExpedienteUseCase::$last_input['title'] ?? '') === 'Contrato');
ac_assert('create pasa description', array_key_exists('description', CreateExpedienteUseCase::$last_input ?? []));
ac_assert('create omite category_slug si no vino', !array_key_exists('category_slug', CreateExpedienteUseCase::$last_input ?? []));
ac_assert('create ignora category_id/client_id/created_at/blog_id', !array_key_exists('category_id', CreateExpedienteUseCase::$last_input ?? [])
    && !array_key_exists('client_id', CreateExpedienteUseCase::$last_input ?? [])
    && !array_key_exists('created_at', CreateExpedienteUseCase::$last_input ?? [])
    && !array_key_exists('blog_id', CreateExpedienteUseCase::$last_input ?? []));
ac_assert(
    'create contrato estable',
    ($create_ok['data']['id'] ?? 0) === 7
    && ($create_ok['data']['title'] ?? '') === 'Contrato'
    && ($create_ok['data']['category']['slug'] ?? '') === 'general'
    && ($create_ok['data']['created_at'] ?? '') === '2026-08-17 13:00:00'
);

aa_reset_expedientes_ajax_runtime();
CreateExpedienteUseCase::$result = [
    'success' => false,
    'error' => [
        'code' => 'missing_title',
        'message' => 'El título es obligatorio.',
    ],
];
$_POST = ['title' => '   '];
$create_fail = aa_invoke_expedientes_ajax([ExpedientesAjax::class, 'handle_create']);
ac_assert('validación 400 sin éxito', ($create_fail['success'] ?? true) === false && ($create_fail['status'] ?? 0) === 400);
ac_assert('validación code missing_title', ($create_fail['data']['code'] ?? '') === 'missing_title');
ac_assert('validación sí delega al use case (una vez)', CreateExpedienteUseCase::$calls === 1);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
