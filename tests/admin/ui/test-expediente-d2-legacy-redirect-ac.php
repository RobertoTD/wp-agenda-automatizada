<?php
/**
 * AC — D2: redirect legacy clients&view=expediente → detail canónico.
 *
 * Ejecutar: php tests/admin/ui/test-expediente-d2-legacy-redirect-ac.php
 *
 * Mezcla guardrails de fuente + harness dinámico sin WP pleno.
 * exit tras redirect exitoso se valida en fuente (patrón AC del proyecto).
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

function ac_read(string $relative): string {
    global $plugin_root;
    $src = file_get_contents($plugin_root . '/' . $relative);
    return is_string($src) ? $src : '';
}

$router = ac_read('includes/admin/ui/index.php');
$repo = ac_read('includes/repositories/ExpedientesRepository.php');
$clients_index = ac_read('includes/admin/ui/modules/clients/index.php');
$clients_js = ac_read('includes/admin/ui/modules/clients/clients-module.js');
$detail = ac_read('includes/admin/ui/modules/expedientes/detail.php');
$bootstrap = ac_read('wp-agenda-automatizada.php');
$cheat = ac_read('docs/00-paradigm-cheatsheet.md');

$gate_full = strpos($router, 'AA_Shell_Access::ACCESS_FULL');
$d2 = strpos($router, 'D2:');
$lookup = strpos($router, 'ExpedientesRepository::find_by_client_id');
$get_uc = strpos($router, 'GetExpedienteUseCase');
$layout = strpos($router, "require __DIR__ . '/shared/layout.php'");

// --- 14. Orden fuente ---
ac_assert(
    'orden gate full < D2 < detail canónico < layout',
    $gate_full !== false && $d2 !== false && $get_uc !== false && $layout !== false
    && $gate_full < $d2 && $d2 < $get_uc && $get_uc < $layout
);
ac_assert(
    'wp_safe_redirect 302 inmediatamente seguido de exit',
    preg_match(
        '/if\s*\(\s*wp_safe_redirect\(\$aa_d2_canonical_url,\s*302\)\s*\)\s*\{\s*exit;/',
        $router
    ) === 1
);
ac_assert(
    'rama false continúa hacia layout (comentario + sin exit en false)',
    strpos($router, 'false: sin padre') !== false
    && $d2 < $layout
);

// --- Condición de lookup ---
ac_assert(
    'lookup solo clients + view expediente',
    preg_match(
        "/if\s*\(\s*\\\$active_module === 'clients' && \\\$view_raw === 'expediente'\s*\)/",
        $router
    ) === 1
);
ac_assert(
    'client_id escalar → absint; no canónico estricto en URL legacy',
    strpos($router, 'is_scalar($aa_d2_client_id_raw)') !== false
    && strpos($router, 'absint(wp_unslash((string) $aa_d2_client_id_raw))') !== false
    && preg_match(
        "/D2:[\s\S]*?AA_Expediente_Id_Policy::normalize\(\\\$aa_d2_client_id_raw/",
        $router
    ) !== 1
);
ac_assert(
    'lookup requiere client_id > 0',
    strpos($router, 'if ($aa_d2_client_id > 0)') !== false
);

// --- URL destino ---
ac_assert(
    'URL canónica solo params aprobados',
    preg_match(
        "/\\\$aa_d2_canonical_url = add_query_arg\(\s*\[\s*'action' => 'aa_iframe_content',\s*'module' => 'expedientes',\s*'view' => 'detail',\s*'expediente_id' => \\\$aa_d2_expediente_id,\s*\],\s*admin_url\('admin-post\.php'\)\s*\)/s",
        $router
    ) === 1
);
ac_assert(
    'URL D2 no copia $_GET ni client_id/records_page/nonce/aa_gate',
    preg_match(
        "/\\\$aa_d2_canonical_url = add_query_arg\([\s\S]*?\);/",
        $router,
        $url_block
    ) === 1
    && isset($url_block[0])
    && strpos($url_block[0], '$_GET') === false
    && strpos($url_block[0], "'client_id'") === false
    && strpos($url_block[0], 'client_id') === false
    && strpos($url_block[0], 'records_page') === false
    && strpos($url_block[0], 'nonce') === false
    && strpos($url_block[0], 'aa_gate') === false
);

// --- Fail-closed ---
ac_assert(
    'null / malformado / redirect false → 500 genérico',
    substr_count($router, "wp_die('No se pudo abrir el expediente.', 'Error', ['response' => 500])") >= 3
);
ac_assert(
    'mensajes D2 sin IDs/SQL',
    preg_match(
        "/D2:[\s\S]*?Parent detail/",
        $router,
        $d2_src
    ) === 1
    && isset($d2_src[0])
    && strpos($d2_src[0], 'aa_expedientes') === false
    && strpos($d2_src[0], 'last_error') === false
    && !preg_match('/client_id\s*\.\s*/', $d2_src[0])
);

// --- Sin backfill / ClientsRepository ---
ac_assert(
    'D2 no consulta ClientsRepository',
    preg_match("/D2:[\s\S]*?Parent detail/", $router, $d2b) === 1
    && isset($d2b[0])
    && strpos($d2b[0], 'ClientsRepository') === false
);
ac_assert(
    'D2 sin UPDATE/INSERT/DELETE/backfill en router',
    preg_match("/D2:[\s\S]*?Parent detail/", $router, $d2c) === 1
    && isset($d2c[0])
    && stripos($d2c[0], 'INSERT') === false
    && stripos($d2c[0], 'UPDATE') === false
    && stripos($d2c[0], 'DELETE') === false
    && stripos($d2c[0], 'backfill') === false
);
ac_assert(
    'repo find_by_client_id triestado documentado',
    strpos($repo, '@return array{id:int, client_id:int}|false|null') !== false
    || strpos($repo, '@return array{id:int,client_id:int}|false|null') !== false
);

// --- D1 / legacy intactos ---
ac_assert(
    'D1 clients-module intacto (replace + detailCanonicalBaseUrl)',
    strpos($clients_js, 'detailCanonicalBaseUrl') !== false
    && strpos($clients_js, 'onCreateComplete') !== false
    && strpos($clients_js, 'location.replace') !== false
    && strpos($clients_js, 'parent.location') === false
    && strpos($clients_js, 'top.location') === false
);
ac_assert(
    'vista virtual conserva config/scripts',
    strpos($clients_index, 'aa-expediente-root') !== false
    && strpos($clients_index, 'detailCanonicalBaseUrl') !== false
    && strpos($clients_index, 'clients-module.js') !== false
);
ac_assert(
    'endpoints legacy siguen registrados',
    strpos($bootstrap, 'ClientsAjax::register()') !== false
    && strpos($bootstrap, 'ExpedienteRegistrosAjax::register()') !== false
);
ac_assert(
    'detail.php builder D1 no tocado por helper compartido nuevo',
    strpos($detail, 'detailCanonicalBaseUrl') !== false || strpos($clients_index, 'detailCanonicalBaseUrl') !== false
);
ac_assert(
    'sin redirect JS nuevo en D2 (router PHP only)',
    preg_match("/D2:[\s\S]*?Parent detail/", $router, $d2js) === 1
    && isset($d2js[0])
    && strpos($d2js[0], 'location.') === false
    && strpos($d2js[0], 'window.') === false
);
ac_assert(
    'detail canónico no llama find_by_client_id',
    preg_match(
        "/view_raw === 'detail'[\s\S]*find_by_client_id/",
        $router
    ) !== 1
);
ac_assert(
    'sin redirect canónico→legacy',
    preg_match(
        "/module' => 'clients'[\s\S]{0,80}view' => 'expediente'/",
        substr($router, $d2)
    ) !== 1
);
ac_assert(
    'cheatsheet documenta D2 + prerrequisito backfill',
    strpos($cheat, '**D2:**') !== false
    && strpos($cheat, 'prerrequisito operativo') !== false
    && strpos($cheat, 'vista virtual') !== false
);
ac_assert(
    'siguen exactamente 2 ResolveShellAccess execute()',
    substr_count($router, 'ResolveShellAccessUseCase())->execute()') === 2
);

// --- Harness dinámico ---
class AA_D2_Die extends RuntimeException {
    /** @var int */
    public $response;
    public function __construct(string $message, int $response) {
        parent::__construct($message);
        $this->response = $response;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}
if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url) {
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        $parts = [];
        foreach ($args as $k => $v) {
            $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        return $url . $sep . implode('&', $parts);
    }
}
if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) {
        $response = is_array($args) && isset($args['response']) ? (int) $args['response'] : 500;
        throw new AA_D2_Die((string) $message, $response);
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap) {
        return $cap === 'manage_options';
    }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return false;
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return (string) $str;
    }
}

require_once $plugin_root . '/includes/domain/legal/class-aa-shell-access.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';

$GLOBALS['aa_d2_find_calls'] = [];
$GLOBALS['aa_d2_find_result'] = false;
$GLOBALS['aa_d2_redirects'] = [];
$GLOBALS['aa_d2_redirect_ok'] = true;
$GLOBALS['aa_d2_layout_hit'] = false;
$GLOBALS['aa_d2_shell_access'] = ['access' => AA_Shell_Access::ACCESS_FULL];
$GLOBALS['aa_d2_html_before_redirect'] = false;

if (!class_exists('ResolveShellAccessUseCase', false)) {
    class ResolveShellAccessUseCase {
        public function execute() {
            return $GLOBALS['aa_d2_shell_access'];
        }
    }
}

if (!class_exists('ExpedientesRepository', false)) {
    class ExpedientesRepository {
        public static function find_by_client_id(int $client_id) {
            $GLOBALS['aa_d2_find_calls'][] = $client_id;
            return $GLOBALS['aa_d2_find_result'];
        }
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302) {
        if (ob_get_length()) {
            $GLOBALS['aa_d2_html_before_redirect'] = true;
        }
        $GLOBALS['aa_d2_redirects'][] = ['url' => (string) $location, 'status' => (int) $status];
        return (bool) $GLOBALS['aa_d2_redirect_ok'];
    }
}

/**
 * Ejecuta el fragmento D2 del router con stubs (sin include completo del layout).
 *
 * @param array<string,mixed> $get
 * @return array{outcome:string,message?:string,response?:int,redirects:list<array{url:string,status:int}>,find_calls:list<int>,layout:bool,html_before:bool}
 */
function aa_d2_run_branch(array $get): array {
    $GLOBALS['aa_d2_find_calls'] = [];
    $GLOBALS['aa_d2_redirects'] = [];
    $GLOBALS['aa_d2_layout_hit'] = false;
    $GLOBALS['aa_d2_html_before_redirect'] = false;

    $_GET = $get;
    $active_module = isset($_GET['module']) ? sanitize_key($_GET['module']) : 'calendar';
    $view_raw = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '';

    $reached_layout = false;
    $outcome = 'fallthrough';
    $message = '';
    $response = 0;

    try {
        // Gate (misma condición que producción).
        if (
            $active_module === 'expedientes'
            || ($active_module === 'clients' && $view_raw === 'expediente')
        ) {
            $shell_access = (new ResolveShellAccessUseCase())->execute();
            if (($shell_access['access'] ?? '') !== AA_Shell_Access::ACCESS_FULL) {
                wp_die('Acceso denegado', 'Error', ['response' => 403]);
            }
        }

        // Branch D2 (espejo del router; exit → outcome redirect).
        if ($active_module === 'clients' && $view_raw === 'expediente') {
            $aa_d2_client_id_raw = array_key_exists('client_id', $_GET) ? $_GET['client_id'] : null;
            $aa_d2_client_id = 0;
            if (is_scalar($aa_d2_client_id_raw) && !is_bool($aa_d2_client_id_raw)) {
                $aa_d2_client_id = absint(wp_unslash((string) $aa_d2_client_id_raw));
            }

            if ($aa_d2_client_id > 0) {
                $aa_d2_parent = ExpedientesRepository::find_by_client_id($aa_d2_client_id);

                if ($aa_d2_parent === null) {
                    wp_die('No se pudo abrir el expediente.', 'Error', ['response' => 500]);
                    return [
                        'outcome' => 'die',
                        'message' => 'No se pudo abrir el expediente.',
                        'response' => 500,
                        'redirects' => $GLOBALS['aa_d2_redirects'],
                        'find_calls' => $GLOBALS['aa_d2_find_calls'],
                        'layout' => false,
                        'html_before' => $GLOBALS['aa_d2_html_before_redirect'],
                    ];
                }

                if (is_array($aa_d2_parent)) {
                    $aa_d2_expediente_id = AA_Expediente_Id_Policy::normalize($aa_d2_parent['id'] ?? null);
                    $aa_d2_owner_id = AA_Expediente_Id_Policy::normalize($aa_d2_parent['client_id'] ?? null);

                    if (
                        $aa_d2_expediente_id === null
                        || $aa_d2_owner_id === null
                        || $aa_d2_owner_id !== $aa_d2_client_id
                    ) {
                        wp_die('No se pudo abrir el expediente.', 'Error', ['response' => 500]);
                        return [
                            'outcome' => 'die',
                            'message' => 'No se pudo abrir el expediente.',
                            'response' => 500,
                            'redirects' => $GLOBALS['aa_d2_redirects'],
                            'find_calls' => $GLOBALS['aa_d2_find_calls'],
                            'layout' => false,
                            'html_before' => $GLOBALS['aa_d2_html_before_redirect'],
                        ];
                    }

                    $aa_d2_canonical_url = add_query_arg(
                        [
                            'action' => 'aa_iframe_content',
                            'module' => 'expedientes',
                            'view' => 'detail',
                            'expediente_id' => $aa_d2_expediente_id,
                        ],
                        admin_url('admin-post.php')
                    );

                    if (wp_safe_redirect($aa_d2_canonical_url, 302)) {
                        $outcome = 'redirect_exit';
                        return [
                            'outcome' => $outcome,
                            'redirects' => $GLOBALS['aa_d2_redirects'],
                            'find_calls' => $GLOBALS['aa_d2_find_calls'],
                            'layout' => false,
                            'html_before' => $GLOBALS['aa_d2_html_before_redirect'],
                        ];
                    }

                    wp_die('No se pudo abrir el expediente.', 'Error', ['response' => 500]);
                    return [
                        'outcome' => 'die',
                        'message' => 'No se pudo abrir el expediente.',
                        'response' => 500,
                        'redirects' => $GLOBALS['aa_d2_redirects'],
                        'find_calls' => $GLOBALS['aa_d2_find_calls'],
                        'layout' => false,
                        'html_before' => $GLOBALS['aa_d2_html_before_redirect'],
                    ];
                }
            }
        }

        // Detail canónico no ejecuta find_by_client_id (assert en fuente).
        $reached_layout = true;
        $GLOBALS['aa_d2_layout_hit'] = true;
        $outcome = 'layout';
    } catch (AA_D2_Die $e) {
        $outcome = 'die';
        $message = $e->getMessage();
        $response = $e->response;
    }

    return [
        'outcome' => $outcome,
        'message' => $message,
        'response' => $response,
        'redirects' => $GLOBALS['aa_d2_redirects'],
        'find_calls' => $GLOBALS['aa_d2_find_calls'],
        'layout' => $reached_layout || $GLOBALS['aa_d2_layout_hit'],
        'html_before' => $GLOBALS['aa_d2_html_before_redirect'],
    ];
}

// 1. Gate no full → 403 y cero lookup
$GLOBALS['aa_d2_shell_access'] = ['access' => AA_Shell_Access::ACCESS_FREE];
$GLOBALS['aa_d2_find_result'] = ['id' => 9, 'client_id' => 42];
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente', 'client_id' => '42']);
ac_assert('gate no full → 403', $r['outcome'] === 'die' && $r['response'] === 403);
ac_assert('gate no full → cero lookup', $r['find_calls'] === []);
ac_assert('gate no full → cero redirects', $r['redirects'] === []);

$GLOBALS['aa_d2_shell_access'] = ['access' => AA_Shell_Access::ACCESS_FULL];

// 2. Listado de clientes → cero lookup
$r = aa_d2_run_branch(['module' => 'clients']);
ac_assert('listado clients → cero lookup', $r['find_calls'] === [] && $r['outcome'] === 'layout');

// 3. view distinta → cero lookup
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'edit', 'client_id' => '42']);
ac_assert('view≠expediente → cero lookup', $r['find_calls'] === []);

// 4. client_id ausente
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente']);
ac_assert('client_id ausente → cero lookup + layout', $r['find_calls'] === [] && $r['outcome'] === 'layout');

// 5. client_id escalar inválido
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente', 'client_id' => '0']);
ac_assert('client_id 0 → cero lookup', $r['find_calls'] === []);

// 6. array/objeto sin warnings
$prev = error_reporting(E_ALL);
set_error_handler(static function () {
    throw new RuntimeException('php warning');
});
$warn = false;
try {
    $r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente', 'client_id' => ['x']]);
} catch (RuntimeException $e) {
    $warn = true;
    $r = ['find_calls' => ['err'], 'outcome' => 'err'];
}
restore_error_handler();
error_reporting($prev);
ac_assert('client_id array → cero lookup sin warnings', !$warn && $r['find_calls'] === []);

$warn = false;
set_error_handler(static function () {
    throw new RuntimeException('php warning');
});
try {
    $r = aa_d2_run_branch([
        'module' => 'clients',
        'view' => 'expediente',
        'client_id' => (object) ['id' => 1],
    ]);
} catch (RuntimeException $e) {
    $warn = true;
}
restore_error_handler();
ac_assert('client_id objeto → cero lookup sin warnings', !$warn && $r['find_calls'] === []);

// 7. Sin padre → layout virtual
$GLOBALS['aa_d2_find_result'] = false;
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente', 'client_id' => '42']);
ac_assert('sin padre false → layout', $r['outcome'] === 'layout' && $r['layout'] === true);
ac_assert('sin padre → una llamada', $r['find_calls'] === [42]);
ac_assert('sin padre → cero redirects', $r['redirects'] === []);

// 8–15. Padre válido → redirect exacto
$GLOBALS['aa_d2_find_result'] = ['id' => 9, 'client_id' => 42];
$GLOBALS['aa_d2_redirect_ok'] = true;
$r = aa_d2_run_branch([
    'module' => 'clients',
    'view' => 'expediente',
    'client_id' => '42',
    'records_page' => '3',
    'query' => 'secret',
    'nonce' => 'n',
    'aa_gate' => '1',
    'foo' => 'bar',
]);
ac_assert('padre válido → una llamada', $r['find_calls'] === [42]);
ac_assert('padre válido → redirect_exit', $r['outcome'] === 'redirect_exit');
ac_assert('redirect status 302', count($r['redirects']) === 1 && $r['redirects'][0]['status'] === 302);
ac_assert('redirect antes de layout', $r['layout'] === false);
ac_assert('cero HTML antes del redirect', $r['html_before'] === false);
$redir = $r['redirects'][0]['url'] ?? '';
ac_assert(
    'URL params aprobados',
    strpos($redir, 'action=aa_iframe_content') !== false
    && strpos($redir, 'module=expedientes') !== false
    && strpos($redir, 'view=detail') !== false
    && strpos($redir, 'expediente_id=9') !== false
);
ac_assert('URL sin client_id', strpos($redir, 'client_id') === false);
ac_assert('URL sin records_page', strpos($redir, 'records_page') === false);
ac_assert(
    'URL sin params arbitrarios',
    strpos($redir, 'secret') === false
    && strpos($redir, 'nonce') === false
    && strpos($redir, 'aa_gate') === false
    && strpos($redir, 'foo') === false
);

// 16. SQL null → 500
$GLOBALS['aa_d2_find_result'] = null;
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente', 'client_id' => '42']);
ac_assert('null → 500', $r['outcome'] === 'die' && $r['response'] === 500);
ac_assert('null → no layout', $r['layout'] === false);

// 17. Resultado malformado → 500
$GLOBALS['aa_d2_find_result'] = ['id' => '05', 'client_id' => 42];
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente', 'client_id' => '42']);
ac_assert('malformado → 500', $r['outcome'] === 'die' && $r['response'] === 500);

// 18. Owner contradictorio → 500
$GLOBALS['aa_d2_find_result'] = ['id' => 9, 'client_id' => 99];
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente', 'client_id' => '42']);
ac_assert('owner contradictorio → 500', $r['outcome'] === 'die' && $r['response'] === 500 && $r['redirects'] === []);

// 19. wp_safe_redirect === false → 500
$GLOBALS['aa_d2_find_result'] = ['id' => 9, 'client_id' => 42];
$GLOBALS['aa_d2_redirect_ok'] = false;
$r = aa_d2_run_branch(['module' => 'clients', 'view' => 'expediente', 'client_id' => '42']);
ac_assert('redirect false → 500 no layout', $r['outcome'] === 'die' && $r['response'] === 500 && $r['layout'] === false);
$GLOBALS['aa_d2_redirect_ok'] = true;

// 21. Detail canónico directo no llama find
$r = aa_d2_run_branch(['module' => 'expedientes', 'view' => 'detail', 'expediente_id' => '9']);
ac_assert('detail canónico → cero find_by_client_id', $r['find_calls'] === []);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
