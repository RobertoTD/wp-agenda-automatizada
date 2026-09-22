<?php
/**
 * AC Test — CanonicalShellWriteAjaxSupport / CanonicalShellWriteAjaxRejection (SB1-5C1).
 *
 * Cubre solo lo que las suites de los seis endpoints no pueden observar:
 * la matriz completa de parse_positive_int, que el soporte no emite JSON ni termina
 * la petición, y que cada gateway se construye con un registry propio.
 *
 * Ejecutar: php tests/http/ajax/test-canonical-shell-write-ajax-support-ac.php
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

$support_file = $plugin_root . '/includes/http/ajax/CanonicalShellWriteAjaxSupport.php';
$rejection_file = $plugin_root . '/includes/http/ajax/CanonicalShellWriteAjaxRejection.php';
$support_src = (string) file_get_contents($support_file);
$rejection_src = (string) file_get_contents($rejection_file);
$boot_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

ac_assert('Soporte y rechazo legibles', $support_src !== '' && $rejection_src !== '');
ac_assert('Clases finales', strpos($support_src, 'final class CanonicalShellWriteAjaxSupport') !== false
    && strpos($rejection_src, 'final class CanonicalShellWriteAjaxRejection') !== false);
ac_assert('Sin estado mutable entre peticiones', preg_match('/(private|protected|public)\s+static\s+\$/', $support_src) !== 1);
ac_assert('Sin hooks propios', strpos($support_src, 'add_action') === false
    && strpos($support_src, 'wp_ajax_') === false);
ac_assert('Sin nonce ni capacidades propias', strpos($support_src, 'wp_verify_nonce') === false
    && strpos($support_src, 'current_user_can') === false);
ac_assert('Sin operation/resource_type genéricos', strpos($support_src, 'resource_type') === false
    && strpos($support_src, '$operation') === false
    && strpos($support_src, 'callable') === false);

// Carga productiva: el soporte se carga antes de los seis endpoints, sin loader de tests.
$pos_support = strpos($boot_src, "require_once __DIR__ . '/includes/http/ajax/CanonicalShellWriteAjaxSupport.php'");
$pos_rejection = strpos($boot_src, "require_once __DIR__ . '/includes/http/ajax/CanonicalShellWriteAjaxRejection.php'");
$endpoint_positions = [];
foreach ([
    'CanonicalCreateContainerAjax',
    'CanonicalUpdateContainerAjax',
    'CanonicalDeleteContainerAjax',
    'CanonicalCreateRecordAjax',
    'CanonicalUpdateRecordAjax',
    'CanonicalDeleteRecordAjax',
] as $endpoint) {
    $endpoint_positions[$endpoint] = strpos($boot_src, "require_once __DIR__ . '/includes/http/ajax/" . $endpoint . ".php'");
}
ac_assert('Loader carga rechazo y soporte', $pos_rejection !== false && $pos_support !== false
    && $pos_rejection < $pos_support);
$loaded_before_all = true;
foreach ($endpoint_positions as $endpoint => $pos) {
    if ($pos === false || $pos < $pos_support) {
        $loaded_before_all = false;
    }
}
ac_assert('Soporte disponible antes de los seis endpoints', $loaded_before_all);

$authorized_endpoints = 0;
$write_stack_endpoints = 0;
foreach (array_keys($endpoint_positions) as $endpoint) {
    $src = (string) file_get_contents($plugin_root . '/includes/http/ajax/' . $endpoint . '.php');
    $uses_write_stack = strpos($src, 'CanonicalShellWriteAjaxSupport::build_write_gateway') !== false
        || strpos($src, 'CanonicalShellWriteAjaxSupport::build_write_composition') !== false;
    if (strpos($src, 'CanonicalShellWriteAjaxSupport::authorize_identity') !== false
        && strpos($src, 'catch (CanonicalShellWriteAjaxRejection $e)') !== false
    ) {
        $authorized_endpoints++;
    }
    if ($uses_write_stack) {
        $write_stack_endpoints++;
    }
}
ac_assert('Los seis endpoints adoptan autorización compartida', $authorized_endpoints === 6, $authorized_endpoints . '/6');
ac_assert('Los cuatro endpoints de create/update construyen el write stack', $write_stack_endpoints === 4, $write_stack_endpoints . '/4');

$keeps_own_manifest = 0;
foreach (array_keys($endpoint_positions) as $endpoint) {
    $src = (string) file_get_contents($plugin_root . '/includes/http/ajax/' . $endpoint . '.php');
    if (strpos($src, 'new CanonicalReadIdentity(') !== false
        && strpos($src, 'new CanonicalShellManifest(') !== false
    ) {
        $keeps_own_manifest++;
    }
}
ac_assert('Los cuatro endpoints de create/update conservan identidad y manifest propios', $keeps_own_manifest === 4, $keeps_own_manifest . '/4');

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['aa_test_json'] = null;
$GLOBALS['aa_test_logged_in'] = true;
$GLOBALS['aa_test_caps'] = ['manage_options' => true];
$GLOBALS['aa_test_multisite'] = false;

// Sustituto fiel de wp_unslash (WordPress): desescapa addslashes de $_POST.
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return (bool) ($GLOBALS['aa_test_logged_in'] ?? false);
    }
}
if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        return (bool) ($GLOBALS['aa_test_multisite'] ?? false);
    }
}
if (!function_exists('is_user_member_of_blog')) {
    function is_user_member_of_blog(): bool {
        return true;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap): bool {
        return !empty($GLOBALS['aa_test_caps'][$cap]);
    }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null): void {
        $GLOBALS['aa_test_json'] = ['success' => true, 'data' => $data, 'status' => $status_code ?? 200];
        throw new RuntimeException('json_sent');
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null): void {
        $GLOBALS['aa_test_json'] = ['success' => false, 'data' => $data, 'status' => $status_code ?? 400];
        throw new RuntimeException('json_sent');
    }
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/ResolveCanonicalRouteUseCase.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementPort.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/tests/support/canonical/CanonicalFixtureWriteAdapter.php';

final class AA_Canonical_Family_Enablement_Store implements CanonicalFamilyEnablementPort {
    public static $mode = 'ok';
    /** @var array<string, bool> */
    public static $enabled_map = ['finance' => true, 'archive' => true];

    public function read_for_declared_families(array $family_keys): CanonicalFamilyEnablementSnapshot {
        if (self::$mode === 'schema') {
            throw new CanonicalFamilyEnablementSchemaNotReady('t');
        }
        if (self::$mode === 'persist') {
            throw new CanonicalFamilyEnablementPersistenceFailed('t');
        }
        if (self::$mode === 'boom') {
            throw new \RuntimeException('t');
        }
        $by = [];
        foreach ($family_keys as $k) {
            if (!array_key_exists($k, self::$enabled_map)) {
                $by[$k] = new CanonicalFamilyEnablementStatus($k, false, false);
            } else {
                $by[$k] = new CanonicalFamilyEnablementStatus($k, true, (bool) self::$enabled_map[$k]);
            }
        }
        return new CanonicalFamilyEnablementSnapshot($by);
    }

    public function set_enabled(string $family_key, bool $enabled): CanonicalFamilyEnablementResult {
        return new CanonicalFamilyEnablementResult($family_key, $enabled, true);
    }
}

final class AA_Canonical_Write_Binding_Bootstrap {
    public static $mode = 'fixture';
    public static $calls = 0;

    public static function register_productive(AA_Canonical_Write_Binding_Registry $registry, $repository = null): void {
        self::$calls++;
        if (self::$mode === 'schema') {
            throw new CanonicalFamilyEnablementSchemaNotReady('t');
        }
        if (self::$mode === 'persist') {
            throw new CanonicalFamilyEnablementPersistenceFailed('t');
        }
        if (self::$mode === 'boom') {
            throw new \RuntimeException('t');
        }
        $canonical = AA_Canonical_Core_Bootstrap::instance();
        foreach ($canonical->families() as $family) {
            $key = $family->key();
            if (empty(AA_Canonical_Family_Enablement_Store::$enabled_map[$key])) {
                continue;
            }
            $adapter = CanonicalFixtureWriteAdapter::with_seed($key, []);
            $registry->register(new CanonicalReadIdentity($key), $adapter);
        }
    }
}

final class AA_Canonical_Capability_Write_Bootstrap {
    /** @return array{preparer:null,materializer:null,selection_preparer:null,handlers:null} */
    public static function build_stack($wpdb = null): array {
        return [
            'preparer' => null,
            'materializer' => null,
            'selection_preparer' => null,
            'handlers' => null,
        ];
    }
}

AA_Canonical_Core_Bootstrap::bootstrap();
require_once $rejection_file;
require_once $support_file;

// --- Rechazo tipado ---
$rejection = new CanonicalShellWriteAjaxRejection('family_disabled', 'Este tipo de registro está desactivado.', 409);
ac_assert('Rechazo transporta la terna', $rejection->error_code() === 'family_disabled'
    && $rejection->error_message() === 'Este tipo de registro está desactivado.'
    && $rejection->http_status() === 409);
ac_assert('Rechazo es Throwable capturable', $rejection instanceof \RuntimeException);

/**
 * @return array{code:string,status:int,message:string}|null
 */
function aa_support_reject(callable $fn): ?array {
    $GLOBALS['aa_test_json'] = null;
    try {
        $fn();
    } catch (CanonicalShellWriteAjaxRejection $e) {
        return ['code' => $e->error_code(), 'status' => $e->http_status(), 'message' => $e->error_message()];
    }
    return null;
}

// --- authorize_identity: cada gate con su terna exacta ---
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('nope');
});
ac_assert('Familia desconocida → unknown_identity 404', $r !== null && $r['code'] === 'unknown_identity' && $r['status'] === 404);
ac_assert('Rechazo no emitió JSON', $GLOBALS['aa_test_json'] === null);

$GLOBALS['aa_test_logged_in'] = false;
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('finance');
});
ac_assert('Sin sesión → unauthorized 401', $r !== null && $r['code'] === 'unauthorized' && $r['status'] === 401);
$GLOBALS['aa_test_logged_in'] = true;

$GLOBALS['aa_test_caps'] = [];
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('archive');
});
ac_assert('Sin manage_options en archive → forbidden 403', $r !== null && $r['code'] === 'forbidden' && $r['status'] === 403);
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('finance');
});
ac_assert('Finance no exige manage_options', $r === null);
$GLOBALS['aa_test_caps'] = ['manage_options' => true];

AA_Canonical_Family_Enablement_Store::$mode = 'schema';
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('finance');
});
ac_assert('Schema ausente → schema_not_ready 503', $r !== null && $r['code'] === 'schema_not_ready' && $r['status'] === 503);

AA_Canonical_Family_Enablement_Store::$mode = 'persist';
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('finance');
});
ac_assert('Persistencia enablement → enablement_unavailable 500', $r !== null && $r['code'] === 'enablement_unavailable' && $r['status'] === 500);

AA_Canonical_Family_Enablement_Store::$mode = 'boom';
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('finance');
});
ac_assert('Throwable enablement no se degrada a persistence_failed', $r !== null && $r['code'] === 'enablement_unavailable');
AA_Canonical_Family_Enablement_Store::$mode = 'ok';

AA_Canonical_Family_Enablement_Store::$enabled_map = ['finance' => true];
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('archive');
});
ac_assert('Familia sin fila → family_not_provisioned 409', $r !== null && $r['code'] === 'family_not_provisioned' && $r['status'] === 409);

AA_Canonical_Family_Enablement_Store::$enabled_map = ['finance' => true, 'archive' => false];
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::authorize_identity('archive');
});
ac_assert('Familia deshabilitada → family_disabled 409', $r !== null && $r['code'] === 'family_disabled' && $r['status'] === 409);
ac_assert('Deshabilitada se distingue de no provisionada', $r['code'] !== 'family_not_provisioned');
AA_Canonical_Family_Enablement_Store::$enabled_map = ['finance' => true, 'archive' => true];

// --- authorize_identity: éxito devuelve familia, no manifest ---
$authorized = CanonicalShellWriteAjaxSupport::authorize_identity('finance');
ac_assert('Devuelve definición de familia', is_array($authorized)
    && isset($authorized['family']) && $authorized['family'] instanceof AA_Canonical_Family_Definition);
ac_assert('No construye el manifest', !isset($authorized['manifest']) && count($authorized) === 1);
ac_assert('Clave resuelta disponible', $authorized['family']->key() === 'finance');
ac_assert('Éxito tampoco emite JSON', $GLOBALS['aa_test_json'] === null);

// --- build_write_gateway ---
AA_Canonical_Write_Binding_Bootstrap::$calls = 0;
$gateway_a = CanonicalShellWriteAjaxSupport::build_write_gateway();
$gateway_b = CanonicalShellWriteAjaxSupport::build_write_gateway();
ac_assert('Devuelve un CanonicalWriteGateway', $gateway_a instanceof CanonicalWriteGateway);
ac_assert('Registry propio por construcción, sin duplicate binding', $gateway_a !== $gateway_b
    && AA_Canonical_Write_Binding_Bootstrap::$calls === 2);
ac_assert('build_write_gateway no emite JSON', $GLOBALS['aa_test_json'] === null);

AA_Canonical_Write_Binding_Bootstrap::$mode = 'schema';
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::build_write_gateway();
});
ac_assert('Bootstrap schema → schema_not_ready 503', $r !== null && $r['code'] === 'schema_not_ready' && $r['status'] === 503);

AA_Canonical_Write_Binding_Bootstrap::$mode = 'persist';
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::build_write_gateway();
});
ac_assert('Bootstrap persistencia → enablement_unavailable 500', $r !== null && $r['code'] === 'enablement_unavailable' && $r['status'] === 500);

AA_Canonical_Write_Binding_Bootstrap::$mode = 'boom';
$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::build_write_gateway();
});
ac_assert('Bootstrap Throwable → persistence_failed 500', $r !== null && $r['code'] === 'persistence_failed' && $r['status'] === 500);
AA_Canonical_Write_Binding_Bootstrap::$mode = 'fixture';

// --- parse_positive_int: matriz completa ---
$cases = [
    ['1', 1],
    ['15', 15],
    ['007', 7],
    [1, 1],
    [PHP_INT_MAX, PHP_INT_MAX],
    ['0', null],
    [0, null],
    [-1, null],
    ['-1', null],
    ['1.5', null],
    ['1,5', null],
    ['12abc', null],
    ['abc', null],
    [' 1', null],
    ['1 ', null],
    ['+1', null],
    ['', null],
    [null, null],
    [true, null],
    [1.0, null],
    [[1], null],
];
$parse_ok = true;
$parse_detail = '';
foreach ($cases as $case) {
    $got = CanonicalShellWriteAjaxSupport::parse_positive_int($case[0]);
    if ($got !== $case[1]) {
        $parse_ok = false;
        $parse_detail = var_export($case[0], true) . ' → ' . var_export($got, true);
        break;
    }
}
ac_assert('parse_positive_int conserva el comportamiento compartido', $parse_ok, $parse_detail);
ac_assert('parse_positive_int no lanza ni emite JSON', $GLOBALS['aa_test_json'] === null);

// --- parse_capability_selection_from_source ---
ac_assert(
    'Soporte declara parse_capability_selection_from_source',
    strpos($support_src, 'function parse_capability_selection_from_source') !== false
    && strpos($support_src, 'capability_selection_scope') !== false
);

$omit_sel = CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source(['title' => 'x']);
ac_assert('Selection: ambos ausentes → null', $omit_sel === null);

$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
        'capability_selection_scope' => '[]',
    ]);
});
ac_assert('Selection: solo scope → invalid_payload 400', $r !== null && $r['code'] === 'invalid_payload' && $r['status'] === 400);

$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
        'capability_selection' => '[]',
    ]);
});
ac_assert('Selection: solo selection → invalid_payload 400', $r !== null && $r['code'] === 'invalid_payload' && $r['status'] === 400);

$present = CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
    'capability_selection_scope' => '["amount"]',
    'capability_selection' => '["amount"]',
]);
ac_assert(
    'Selection: ambos presentes → instancia',
    $present instanceof CanonicalContainerCapabilitySelection
    && $present->scope() === ['amount']
    && $present->selection() === ['amount']
);

$empty_both = CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
    'capability_selection_scope' => '[]',
    'capability_selection' => '[]',
]);
ac_assert(
    'Selection: arrays vacíos explícitos',
    $empty_both instanceof CanonicalContainerCapabilitySelection
    && $empty_both->scope() === []
    && $empty_both->selection() === []
);

$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
        'capability_selection_scope' => '{bad}',
        'capability_selection' => '[]',
    ]);
});
ac_assert('Selection: JSON inválido → invalid_payload', $r !== null && $r['code'] === 'invalid_payload');

$r = aa_support_reject(function () {
    CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
        'capability_selection_scope' => ['amount'],
        'capability_selection' => '[]',
    ]);
});
ac_assert('Selection: tipo no string → invalid_payload', $r !== null && $r['code'] === 'invalid_payload');

// Regresión: valores escapados como en $_POST de WordPress (addslashes).
$wp_slashed_amount = addslashes('["amount"]');
$wp_slashed_empty = addslashes('[]');
ac_assert(
    'Harness: addslashes(["amount"]) invalida json_decode directo',
    json_decode($wp_slashed_amount, true) === null && strpos($wp_slashed_amount, '\\') !== false
);

$slashed_both = CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
    'capability_selection_scope' => $wp_slashed_amount,
    'capability_selection' => $wp_slashed_amount,
]);
ac_assert(
    'Regresión WP: scope+selection slashed ["amount"] → OK',
    $slashed_both instanceof CanonicalContainerCapabilitySelection
    && $slashed_both->scope() === ['amount']
    && $slashed_both->selection() === ['amount']
);

$slashed_empty_sel = CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
    'capability_selection_scope' => $wp_slashed_amount,
    'capability_selection' => $wp_slashed_empty,
]);
ac_assert(
    'Regresión WP: scope slashed ["amount"] + selection [] → OK',
    $slashed_empty_sel instanceof CanonicalContainerCapabilitySelection
    && $slashed_empty_sel->scope() === ['amount']
    && $slashed_empty_sel->selection() === []
);

$slashed_empty_both = CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source([
    'capability_selection_scope' => $wp_slashed_empty,
    'capability_selection' => $wp_slashed_empty,
]);
ac_assert(
    'Regresión WP: ambos [] (slashed no-op) → selección explícita vacía',
    $slashed_empty_both instanceof CanonicalContainerCapabilitySelection
    && $slashed_empty_both->scope() === []
    && $slashed_empty_both->selection() === []
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
