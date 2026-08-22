<?php
/**
 * AC — Expedientes access gate (shell access === full only).
 *
 * Ejecutar: php tests/admin/ui/test-expediente-access-gate-ac.php
 *
 * Contratos de fuente + runtime de ResolveShellAccessUseCase (misma predicado
 * que URL / UX / AJAX). No carga WordPress pleno ni BD.
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
$clients_index = ac_read('includes/admin/ui/modules/clients/index.php');
$clients_js = ac_read('includes/admin/ui/modules/clients/clients-module.js');
$registros_ajax = ac_read('includes/http/ajax/ExpedienteRegistrosAjax.php');
$adjuntos_ajax = ac_read('includes/http/ajax/ExpedienteAdjuntosAjax.php');
$expedientes_ajax = ac_read('includes/http/ajax/ExpedientesAjax.php');
$clients_ajax = ac_read('includes/http/ajax/ClientsAjax.php');
$cheatsheet = ac_read('docs/00-paradigm-cheatsheet.md');
$layout = ac_read('includes/admin/ui/shared/layout.php');
$projection = ac_read('assets/js/services/shellAccessProjection.js');

// --- URL gate (antes de layout) ---
ac_assert(
    'router gates clients/expediente on ACCESS_FULL',
    strpos($router, "\$view_raw === 'expediente'") !== false
    && strpos($router, 'AA_Shell_Access::ACCESS_FULL') !== false
);
ac_assert(
    'router expediente gate before layout.php',
    strpos($router, "\$view_raw === 'expediente'") < strpos($router, 'shared/layout.php')
);
ac_assert(
    'router expediente deny uses wp_die 403',
    preg_match(
        "/view_raw === 'expediente'[\s\S]{0,500}wp_die\('Acceso denegado'[\s\S]{0,120}403/",
        $router
    ) === 1
);
ac_assert(
    'router keeps ResolveShellAccess + layout for shell fail-open path',
    strpos($router, 'ResolveShellAccessUseCase') !== false
    && strpos($router, 'shared/layout.php') !== false
);
ac_assert(
    'router expediente gate covers module=expedientes and clients/expediente',
    preg_match(
        "/\\\$active_module === 'expedientes'[\s\S]{0,160}\\\$active_module === 'clients' && \\\$view_raw === 'expediente'/",
        $router
    ) === 1
);
ac_assert(
    'router whitelist includes module=expedientes',
    strpos($router, "'expedientes'") !== false
);
ac_assert(
    'router resuelve view=detail después del gate full',
    strpos($router, 'AA_Shell_Access::ACCESS_FULL') !== false
    && strpos($router, 'GetExpedienteUseCase') !== false
    && strpos($router, 'AA_Shell_Access::ACCESS_FULL') < strpos($router, 'GetExpedienteUseCase')
    && strpos($router, 'GetExpedienteUseCase') < strpos($router, 'shared/layout.php')
);
ac_assert(
    'router view=detail no añade execute() extra',
    substr_count($router, 'ResolveShellAccessUseCase())->execute()') === 2
);

// --- Async fail-open: resolver OFF the blocking path of normal navigation ---
ac_assert(
    'router resolves shell access only in guarded branches (marker + expediente)',
    substr_count($router, 'ResolveShellAccessUseCase())->execute()') === 2
);
ac_assert(
    'router marker aa_gate=1 present',
    strpos($router, "\$_GET['aa_gate']") !== false
    && strpos($router, "=== '1'") !== false
);
ac_assert(
    'router marker validated with legal nonce (never grants access)',
    strpos($router, "wp_verify_nonce") !== false
    && strpos($router, "'aa_legal_gate_nonce'") !== false
);
ac_assert(
    'router marker renders legal-gate when still legal_gate',
    strpos($router, 'AA_Shell_Access::ACCESS_LEGAL_GATE') !== false
    && strpos($router, "legal-gate/index.php") !== false
    && strpos($router, 'AA_Shell_Access::ACCESS_LEGAL_GATE') < strrpos($router, "require __DIR__ . '/legal-gate/index.php'")
);
ac_assert(
    'router marker strips itself and redirects to canonical otherwise',
    strpos($router, 'wp_safe_redirect($aa_canonical_url)') !== false
);
ac_assert(
    'router marker branch precedes manage_options + layout',
    strpos($router, "\$_GET['aa_gate']") < strpos($router, "current_user_can('manage_options')")
    && strpos($router, "\$_GET['aa_gate']") < strpos($router, 'shared/layout.php')
);

// --- Layout wires the async projection (UX-only) ---
ac_assert(
    'layout emits AA_SHELL_ACCESS_DATA with async endpoint',
    strpos($layout, 'AA_SHELL_ACCESS_DATA') !== false
    && strpos($layout, 'aa_get_legal_gate_status') !== false
    && strpos($layout, "gateParam: 'aa_gate'") !== false
);
ac_assert(
    'layout loads shellAccessProjection.js',
    strpos($layout, 'shellAccessProjection.js') !== false
);

// --- Projection service: UX-only contract ---
ac_assert(
    'projection reuses aa_get_legal_gate_status endpoint',
    strpos($projection, 'AA_SHELL_ACCESS_DATA') !== false
    && strpos($projection, 'aa_get_legal_gate_status') !== false
);
ac_assert(
    'projection only caches full/free (not legal_gate/errors)',
    strpos($projection, "function isCacheable") !== false
    && strpos($projection, "access === 'full'") !== false
    && strpos($projection, "access === 'free'") !== false
);
ac_assert(
    'projection navigates to gate only on legal_gate',
    strpos($projection, "access === 'legal_gate'") !== false
    && strpos($projection, 'navigateToGate') !== false
);
ac_assert(
    'projection dispatches full event and never authority',
    strpos($projection, "'aa:shell-access-resolved'") !== false
);
ac_assert(
    'projection isolates cache key by blogId + authSessionId',
    strpos($projection, 'aa_shell_access:') !== false
    && strpos($projection, 'blogId') !== false
    && strpos($projection, 'authSessionId') !== false
);
ac_assert(
    'projection uses shared promise + generation epoch',
    strpos($projection, 'ns.promise') !== false
    && strpos($projection, 'ns.gen') !== false
);
ac_assert(
    'clients JS reacts to shell-access-resolved (full enables buttons)',
    strpos($clients_js, "'aa:shell-access-resolved'") !== false
    && strpos($clients_js, 'enableExpedienteButtons') !== false
);

// --- UX boolean ---
ac_assert(
    'clients index emits expedienteAccessAllowed',
    strpos($clients_index, 'expedienteAccessAllowed') !== false
);
ac_assert(
    'clients index starts expedienteAccessAllowed=false (fail-closed, JS enables)',
    strpos($clients_index, '$aa_expediente_access_allowed = false;') !== false
    && strpos($clients_index, 'AA_Shell_Access::ACCESS_FULL') === false
);
ac_assert(
    'JS checks expedienteAccessAllowed before navigate',
    strpos($clients_js, 'expedienteAccessAllowed !== true') !== false
);
ac_assert(
    'JS disables button when not allowed',
    strpos($clients_js, 'expedienteButton.disabled = true') !== false
);

// --- AJAX gate ---
ac_assert(
    'registros ajax defines require_expediente_shell_access',
    strpos($registros_ajax, 'function require_expediente_shell_access') !== false
);
ac_assert(
    'registros authorize calls require_expediente_shell_access',
    strpos($registros_ajax, 'require_expediente_shell_access()') !== false
);
ac_assert(
    'registros require checks ACCESS_FULL via ResolveShellAccessUseCase',
    strpos($registros_ajax, 'ResolveShellAccessUseCase') !== false
    && strpos($registros_ajax, 'AA_Shell_Access::ACCESS_FULL') !== false
);
ac_assert(
    'adjuntos authorize uses require_expediente_shell_access',
    strpos($adjuntos_ajax, 'function authorize') !== false
    && strpos($adjuntos_ajax, 'ExpedienteRegistrosAjax::require_expediente_shell_access') !== false
);
ac_assert(
    'adjuntos all handlers use authorize()',
    substr_count($adjuntos_ajax, 'if (!self::authorize())') >= 4
);
ac_assert(
    'expedientes ajax authorize uses require_expediente_shell_access',
    strpos($expedientes_ajax, 'function authorize') !== false
    && strpos($expedientes_ajax, 'ExpedienteRegistrosAjax::require_expediente_shell_access') !== false
);
ac_assert(
    'expedientes ajax handlers use authorize()',
    substr_count($expedientes_ajax, 'if (!self::authorize())') >= 2
);
ac_assert(
    'expedientes ajax no nopriv',
    strpos($expedientes_ajax, 'wp_ajax_nopriv_') === false
);
ac_assert(
    'expedientes ajax capability manage_options',
    strpos($expedientes_ajax, "current_user_can('manage_options')") !== false
);
ac_assert(
    'ClientsAjax get_cliente not gated by expediente shell',
    strpos($clients_ajax, 'require_expediente_shell_access') === false
    && strpos($clients_ajax, 'ResolveShellAccessUseCase') === false
);
ac_assert(
    'no account-status in expediente access paths',
    strpos($registros_ajax, 'GetAccountStatus') === false
    && strpos($adjuntos_ajax, 'GetAccountStatus') === false
    && strpos($expedientes_ajax, 'GetAccountStatus') === false
    && strpos($router, 'GetAccountStatus') === false
    && strpos($clients_index, 'GetAccountStatus') === false
);
ac_assert(
    'cheatsheet documents expediente fail-closed on full',
    strpos($cheatsheet, 'Expedientes') !== false
    && strpos($cheatsheet, 'fail-closed') !== false
);

// --- Runtime: same predicate as production (ResolveShellAccessUseCase) ---
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['aa_test_options'][$key] ?? $default;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap) {
        return !empty($GLOBALS['aa_test_can_manage']);
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

$GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret-test'];
$GLOBALS['aa_test_can_manage'] = true;

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-legal-gate-backend-client.php';
require_once $plugin_root . '/includes/domain/legal/class-aa-shell-access.php';
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-terms-consent.php';
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-privacy-consent.php';
require_once $plugin_root . '/includes/application/legal/GetLegalGateStatusUseCase.php';
require_once $plugin_root . '/includes/application/legal/ResolveShellAccessUseCase.php';

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

final class Mock_Legal_Gate_Backend_Client_For_Exp_Access extends AA_Legal_Gate_Backend_Client {
    /** @var array<string,mixed> */
    private $payload;

    public function __construct(array $payload) {
        $this->payload = $payload;
    }

    public function fetchStatus(): array {
        return $this->payload;
    }
}

function expediente_shell_access_for(array $backend): array {
    $legal = new GetLegalGateStatusUseCase(
        new Mock_Legal_Gate_Backend_Client_For_Exp_Access($backend)
    );
    return (new ResolveShellAccessUseCase($legal))->execute();
}

function expediente_allowed(array $shell): bool {
    return ($shell['access'] ?? '') === AA_Shell_Access::ACCESS_FULL;
}

$full = expediente_shell_access_for([
    'ok' => true,
    'status' => 'ready',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => true,
    'privacy_document' => null,
    'terms_document' => null,
]);
ac_assert('full + ready allows Expedientes', expediente_allowed($full) === true);
ac_assert('full access is ACCESS_FULL', ($full['access'] ?? '') === AA_Shell_Access::ACCESS_FULL);

$free = expediente_shell_access_for([
    'ok' => true,
    'status' => 'provisioning_request_missing',
    'subscription_active' => false,
    'privacy_accepted' => false,
    'terms_accepted' => false,
    'privacy_document' => null,
    'terms_document' => null,
]);
ac_assert('free (no subscription) blocks Expedientes', expediente_allowed($free) === false);
ac_assert('free access remains ACCESS_FREE for shell', ($free['access'] ?? '') === AA_Shell_Access::ACCESS_FREE);

$pending = expediente_shell_access_for([
    'ok' => true,
    'status' => 'needs_terms',
    'subscription_active' => true,
    'privacy_accepted' => true,
    'terms_accepted' => false,
    'privacy_document' => null,
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
]);
ac_assert('legal pending blocks Expedientes', expediente_allowed($pending) === false);
ac_assert(
    'legal pending keeps ACCESS_LEGAL_GATE for shell',
    ($pending['access'] ?? '') === AA_Shell_Access::ACCESS_LEGAL_GATE
);

$dual_pending = expediente_shell_access_for([
    'ok' => true,
    'status' => 'needs_privacy_and_terms',
    'subscription_active' => true,
    'privacy_accepted' => false,
    'terms_accepted' => false,
    'privacy_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/privacidad/',
    ],
    'terms_document' => [
        'version' => '2026-08-03.1',
        'human_url' => 'https://deoia.com/terminos/',
    ],
]);
ac_assert(
    'needs_privacy_and_terms blocks Expedientes',
    expediente_allowed($dual_pending) === false
);

$unreachable = expediente_shell_access_for([
    'ok' => false,
    'code' => 'legal_gate_backend_unreachable',
    'error' => 'timeout',
    'http_status' => 0,
]);
ac_assert(
    'backend unreachable blocks Expedientes',
    expediente_allowed($unreachable) === false
);
ac_assert(
    'backend unreachable keeps shell ACCESS_FREE (fail-open signal)',
    ($unreachable['access'] ?? '') === AA_Shell_Access::ACCESS_FREE
);

$incomplete = expediente_shell_access_for([
    'ok' => true,
    'status' => 'ready',
    'privacy_accepted' => true,
    'terms_accepted' => true,
    'privacy_document' => null,
    'terms_document' => null,
    // subscription_active absent → unknown → free
]);
ac_assert(
    'incomplete payload blocks Expedientes',
    expediente_allowed($incomplete) === false
);


// --- D2 order guardrails (source) ---
$d2_marker = strpos($router, 'D2:');
$get_uc_pos = strpos($router, 'GetExpedienteUseCase');
$layout_pos = strpos($router, 'shared/layout.php');
$gate_full_pos = strpos($router, 'AA_Shell_Access::ACCESS_FULL');
$d2_lookup = strpos($router, 'ExpedientesRepository::find_by_client_id');
ac_assert(
    'D2 order: gate full < branch D2 < GetExpediente < layout',
    $gate_full_pos !== false
    && $d2_marker !== false
    && $get_uc_pos !== false
    && $layout_pos !== false
    && $gate_full_pos < $d2_marker
    && $d2_marker < $get_uc_pos
    && $get_uc_pos < $layout_pos
);
ac_assert(
    'D2 lookup only after ACCESS_FULL gate',
    $d2_lookup !== false
    && $gate_full_pos < $d2_lookup
);
ac_assert(
    'D2 redirect 302 then exit (source)',
    preg_match(
        '/wp_safe_redirect\(\$aa_d2_canonical_url, 302\)\)\s*\{\s*exit;/',
        $router
    ) === 1
);
ac_assert(
    'D2 false branch continues toward layout',
    strpos($router, 'false: sin padre') !== false
    && $d2_marker < $layout_pos
);
ac_assert(
    'D2 does not add ResolveShellAccess execute()',
    substr_count($router, 'ResolveShellAccessUseCase())->execute()') === 2
);

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
