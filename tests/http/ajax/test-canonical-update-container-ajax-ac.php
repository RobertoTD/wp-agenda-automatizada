<?php
/**
 * AC Test — CanonicalUpdateContainerAjax (SB1-5B5).
 *
 * Ejecutar: php tests/http/ajax/test-canonical-update-container-ajax-ac.php
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

$ajax_file = $plugin_root . '/includes/http/ajax/CanonicalUpdateContainerAjax.php';
$ajax_src = (string) file_get_contents($ajax_file);
$boot_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$support_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalShellWriteAjaxSupport.php');

ac_assert('Ajax file readable', $ajax_src !== '');
ac_assert('Action constante', strpos($ajax_src, "ACTION = 'aa_update_canonical_container'") !== false);
ac_assert('Nonce específico', strpos($ajax_src, "NONCE_ACTION = 'aa_update_canonical_container'") !== false);
ac_assert('Solo wp_ajax_', strpos($ajax_src, "add_action('wp_ajax_'") !== false);
ac_assert('Sin nopriv', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('Bootstrap registra', strpos($boot_src, 'CanonicalUpdateContainerAjax::register()') !== false);
ac_assert('Access Policy vía soporte SB1-5C1', strpos($support_src, 'AA_Canonical_Access_Policy::check_family_access') !== false
    && strpos($ajax_src, 'CanonicalShellWriteAjaxSupport::authorize_identity') !== false);
ac_assert('Write bootstrap vía soporte SB1-5C1', strpos($support_src, 'AA_Canonical_Write_Binding_Bootstrap::register_productive') !== false
    && strpos($ajax_src, 'CanonicalShellWriteAjaxSupport::build_write_composition') !== false);
ac_assert('UseCase update', strpos($ajax_src, 'WriteCanonicalShellContainerUseCase') !== false
    && strpos($ajax_src, '->update(') !== false);
ac_assert('Sin SQL directo', strpos($ajax_src, '$wpdb') === false
    && strpos($ajax_src, '->query(') === false
    && strpos($ajax_src, '->insert(') === false);
ac_assert('Códigos estables', strpos($ajax_src, "'uncertain'") !== false
    && strpos($ajax_src, "'write_adapter_pending'") !== false
    && strpos($ajax_src, "'invalid_title'") !== false
    && strpos($ajax_src, "'title_too_long'") !== false
    && strpos($ajax_src, "'invalid_container_id'") !== false
    && strpos($ajax_src, "'container_not_found'") !== false);
ac_assert('Códigos de identidad estables en soporte', strpos($support_src, "'unknown_identity'") !== false
    && strpos($support_src, "'family_disabled'") !== false
    && strpos($support_src, "'family_not_provisioned'") !== false
    && strpos($support_src, "'schema_not_ready'") !== false
    && strpos($support_src, "'enablement_unavailable'") !== false);
ac_assert('Sin aa_finance_', strpos($ajax_src, 'aa_finance_') === false);
ac_assert('Sin aa_expediente_', strpos($ajax_src, 'aa_expediente_') === false);
ac_assert('Sin amount', strpos($ajax_src, 'amount') === false);
ac_assert(
    'Parsea selection vía soporte',
    strpos($ajax_src, 'CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source') !== false
);
ac_assert(
    'Update pasa selection al UseCase',
    preg_match('/->update\(\s*\$manifest\s*,\s*\$command\s*,\s*\$selection\s*,\s*\$solution_effects\s*\)/', $ajax_src) === 1
);
ac_assert(
    'Acepta return_view=records',
    strpos($ajax_src, "array_key_exists('return_view', \$_POST)") !== false
    && strpos($ajax_src, "\$return_ctx['return_view'] === 'records'") !== false
    && strpos($ajax_src, 'build_records_url') !== false
);
ac_assert('Soporte contenido: sin SQL, JSON, $_POST, redirects ni commands', strpos($support_src, '$wpdb') === false
    && preg_match('/->query\(|->insert\(|->prepare\(/', $support_src) !== 1
    && strpos($support_src, 'wp_send_json') === false
    && strpos($support_src, '$_POST') === false
    && strpos($support_src, 'AA_Canonical_Shell_Base_Url_Policy') === false
    && strpos($support_src, 'Command') === false);
ac_assert('Sin delete container', strpos($ajax_src, 'delete_container') === false
    && strpos($ajax_src, 'CanonicalDeleteContainer') === false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['aa_test_json'] = null;
$GLOBALS['aa_test_logged_in'] = true;
$GLOBALS['aa_test_caps'] = ['manage_options' => true];
$GLOBALS['aa_test_nonce_valid'] = true;
$GLOBALS['aa_test_multisite'] = false;
$GLOBALS['aa_test_actions'] = [];

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
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action): bool {
        return (bool) ($GLOBALS['aa_test_nonce_valid'] ?? false) && $nonce === 'good-nonce';
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($v) {
        return is_string($v) ? stripslashes($v) : $v;
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str): string {
        return is_string($str) ? trim(strip_tags($str)) : '';
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str): string {
        return is_string($str) ? trim(strip_tags($str)) : '';
    }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback): void {
        $GLOBALS['aa_test_actions'][] = $hook;
    }
}
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args): string {
        if (count($args) === 2 && is_array($args[0])) {
            $query = http_build_query($args[0]);
            $url = $args[1];
        } elseif (count($args) === 3) {
            $query = http_build_query([$args[0] => $args[1]]);
            $url = $args[2];
        } else {
            return '';
        }
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . $query;
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
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/tests/support/canonical/CanonicalFixtureWriteAdapter.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerMutationContext.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilityEffect.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilitySelection.php';

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

    public static function register_productive(AA_Canonical_Write_Binding_Registry $registry, $repository = null): void {
        if (self::$mode === 'noop') {
            return;
        }
        $canonical = AA_Canonical_Core_Bootstrap::instance();
        foreach ($canonical->families() as $family) {
            $key = $family->key();
            if (empty(AA_Canonical_Family_Enablement_Store::$enabled_map[$key])) {
                continue;
            }
            $seed = ($key === 'finance')
                ? [
                    1 => ['title' => 'Lista Finance', 'details' => 'd'],
                    2 => ['title' => 'Otra', 'details' => null],
                ]
                : [];
            $adapter = CanonicalFixtureWriteAdapter::with_seed($key, $seed);
            if (self::$mode === 'uncertain') {
                $adapter->uncertain_operation = 'update_container';
            }
            if (self::$mode === 'persist_fail') {
                $adapter->uncertain_operation = 'persistence_failed';
            }
            $identity = new CanonicalReadIdentity($key);
            $registry->register($identity, $adapter);
        }
    }
}

final class AA_Test_Noop_Container_Capability_Effect implements CanonicalContainerCapabilityEffect {
    public function apply(CanonicalContainerMutationContext $context): void {
    }
}

final class AA_Test_Container_Capability_Selection_Preparer_Stub {
    public function build_create_effect(string $family_key, $selection) {
        return new AA_Test_Noop_Container_Capability_Effect();
    }

    public function build_update_effect(string $family_key, int $container_id, $selection) {
        return new AA_Test_Noop_Container_Capability_Effect();
    }
}

final class AA_Canonical_Capability_Write_Bootstrap {
    /**
     * @return array{
     *   preparer:null,
     *   materializer:null,
     *   selection_preparer:AA_Test_Container_Capability_Selection_Preparer_Stub,
     *   handlers:null
     * }
     */
    public static function build_stack($wpdb = null): array {
        return [
            'preparer' => null,
            'materializer' => null,
            'selection_preparer' => new AA_Test_Container_Capability_Selection_Preparer_Stub(),
            'handlers' => null,
        ];
    }
}

AA_Canonical_Core_Bootstrap::bootstrap();
require_once $ajax_file;

function aa_run_update_container_ajax(array $post): ?array {
    $_POST = $post;
    $GLOBALS['aa_test_json'] = null;
    try {
        CanonicalUpdateContainerAjax::handle();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent') {
            throw $e;
        }
    }
    return $GLOBALS['aa_test_json'];
}

function aa_base_update_post(array $over = []): array {
    return array_merge([
        'nonce' => 'good-nonce',
        'family_key' => 'finance',
        'container_id' => '1',
        'title' => 'Lista editada',
        'details' => 'detalle',
    ], $over);
}

$GLOBALS['aa_test_logged_in'] = false;
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('No auth → unauthorized', ($r['data']['code'] ?? '') === 'unauthorized' && ($r['status'] ?? 0) === 401);
$GLOBALS['aa_test_logged_in'] = true;

$GLOBALS['aa_test_nonce_valid'] = false;
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('Bad nonce → invalid_nonce', ($r['data']['code'] ?? '') === 'invalid_nonce');
$GLOBALS['aa_test_nonce_valid'] = true;

$r = aa_run_update_container_ajax(aa_base_update_post(['title' => ['x']]));
ac_assert('Non-scalar title → invalid_payload', ($r['data']['code'] ?? '') === 'invalid_payload');

$r = aa_run_update_container_ajax(aa_base_update_post(['container_id' => ['1']]));
ac_assert('Non-scalar container_id → invalid_payload', ($r['data']['code'] ?? '') === 'invalid_payload');

$r = aa_run_update_container_ajax(aa_base_update_post(['container_id' => '1.5']));
ac_assert('Decimal container_id → invalid_container_id', ($r['data']['code'] ?? '') === 'invalid_container_id');

$r = aa_run_update_container_ajax(aa_base_update_post(['container_id' => '-1']));
ac_assert('Negative container_id → invalid_container_id', ($r['data']['code'] ?? '') === 'invalid_container_id');

$r = aa_run_update_container_ajax(aa_base_update_post(['container_id' => '12abc']));
ac_assert('Garbage container_id → invalid_container_id', ($r['data']['code'] ?? '') === 'invalid_container_id');

$r = aa_run_update_container_ajax(aa_base_update_post(['family_key' => 'nope']));
ac_assert('Unknown family → unknown_identity', ($r['data']['code'] ?? '') === 'unknown_identity');


$GLOBALS['aa_test_caps'] = [];
$r = aa_run_update_container_ajax(aa_base_update_post(['family_key' => 'archive']));
ac_assert('Archive without manage_options → forbidden', ($r['data']['code'] ?? '') === 'forbidden');
$GLOBALS['aa_test_caps'] = ['manage_options' => true];

AA_Canonical_Family_Enablement_Store::$enabled_map = ['finance' => false, 'archive' => true];
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('Disabled → family_disabled', ($r['data']['code'] ?? '') === 'family_disabled');

AA_Canonical_Family_Enablement_Store::$enabled_map = [];
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('Not provisioned → family_not_provisioned', ($r['data']['code'] ?? '') === 'family_not_provisioned');

AA_Canonical_Family_Enablement_Store::$enabled_map = ['finance' => true, 'archive' => true];
AA_Canonical_Family_Enablement_Store::$mode = 'schema';
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('Schema → schema_not_ready', ($r['data']['code'] ?? '') === 'schema_not_ready');

AA_Canonical_Family_Enablement_Store::$mode = 'persist';
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('Enablement fail → enablement_unavailable', ($r['data']['code'] ?? '') === 'enablement_unavailable');
AA_Canonical_Family_Enablement_Store::$mode = 'ok';

$r = aa_run_update_container_ajax(aa_base_update_post(['title' => '   ']));
ac_assert('Blank title → invalid_title', ($r['data']['code'] ?? '') === 'invalid_title');

$r = aa_run_update_container_ajax(aa_base_update_post(['title' => str_repeat('x', 201)]));
ac_assert('Long title → title_too_long', ($r['data']['code'] ?? '') === 'title_too_long');

AA_Canonical_Write_Binding_Bootstrap::$mode = 'noop';
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('No binding → write_adapter_pending', ($r['data']['code'] ?? '') === 'write_adapter_pending');

AA_Canonical_Write_Binding_Bootstrap::$mode = 'persist_fail';
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('Persist fail → persistence_failed', ($r['data']['code'] ?? '') === 'persistence_failed');

AA_Canonical_Write_Binding_Bootstrap::$mode = 'uncertain';
$r = aa_run_update_container_ajax(aa_base_update_post());
ac_assert('Uncertain code', ($r['data']['code'] ?? '') === 'uncertain');
ac_assert('Uncertain HTTP 409', ($r['status'] ?? 0) === 409);
ac_assert('Uncertain message safe', strpos((string) ($r['data']['message'] ?? ''), 'Revisa el listado') !== false);
ac_assert('Uncertain sin SQL', strpos(json_encode($r), 'wpdb') === false);

AA_Canonical_Write_Binding_Bootstrap::$mode = 'fixture';
$r = aa_run_update_container_ajax(aa_base_update_post(['container_id' => '999']));
ac_assert('Missing → container_not_found', ($r['data']['code'] ?? '') === 'container_not_found');

$r = aa_run_update_container_ajax(aa_base_update_post([
    'family_key' => 'archive',
    'container_id' => '1',
]));
ac_assert('Cross-family → container_not_found', ($r['data']['code'] ?? '') === 'container_not_found');

$r = aa_run_update_container_ajax(aa_base_update_post(['details' => '']));
ac_assert('Confirmed success', ($r['success'] ?? false) === true);
ac_assert('Confirmed status', ($r['data']['status'] ?? '') === 'confirmed');
ac_assert('Confirmed resource_id', (int) ($r['data']['resource_id'] ?? 0) === 1);
ac_assert('Confirmed container_id null', array_key_exists('container_id', $r['data']) && $r['data']['container_id'] === null);
ac_assert('Confirmed family_key', ($r['data']['family_key'] ?? '') === 'finance');
ac_assert('Confirmed without variant_key', !array_key_exists('variant_key', $r['data'] ?? []));
ac_assert(
    'Redirect page 1 sin page=',
    is_string($r['data']['redirect_url'] ?? null)
    && strpos($r['data']['redirect_url'], 'family=finance') !== false
        && strpos($r['data']['redirect_url'], 'page=') === false
);

$r = aa_run_update_container_ajax(aa_base_update_post([
    'capability_selection_scope' => addslashes('["amount"]'),
    'capability_selection' => addslashes('["amount"]'),
]));
ac_assert(
    'Update: POST WP-slashed ["amount"] no invalid_payload',
    ($r['success'] ?? false) === true && ($r['data']['status'] ?? '') === 'confirmed'
);

$r = aa_run_update_container_ajax(aa_base_update_post([
    'capability_selection_scope' => addslashes('["amount"]'),
    'capability_selection' => addslashes('[]'),
]));
ac_assert(
    'Update: scope slashed + selection [] confirmed',
    ($r['success'] ?? false) === true && ($r['data']['status'] ?? '') === 'confirmed'
);

$r = aa_run_update_container_ajax(aa_base_update_post([
    'capability_selection_scope' => addslashes('["amount"]'),
]));
ac_assert(
    'Update: solo scope → invalid_payload',
    ($r['data']['code'] ?? '') === 'invalid_payload'
);


$r = aa_run_update_container_ajax(aa_base_update_post([
    'capability_views' => ['completed' => 'completed', 'test_flag' => 'flagged'],
    'page' => '2', 'containers_page' => '3', 'lists_scope' => 'all', 'return_view' => 'records'
]));
parse_str(parse_url($r['data']['redirect_url'] ?? '', PHP_URL_QUERY) ?? '', $return_query);
ac_assert('RVC-1: retorno conserva selecciones y procedencia', ($return_query['capability_views'] ?? []) === ['completed'=>'completed','test_flag'=>'flagged']
    && ($return_query['records_view'] ?? '') === 'simple' && ($return_query['page'] ?? '') === '2'
    && ($return_query['containers_page'] ?? '') === '3' && ($return_query['lists_scope'] ?? '') === 'all');
$r = aa_run_update_container_ajax(aa_base_update_post(['capability_views' => ['completed' => ['invalid']]]));
ac_assert('RVC-1: transporte anidado inválido rechazado antes de escribir', ($r['data']['code'] ?? '') === 'invalid_payload');
ac_assert('RVC-1: validación de retorno precede mutación', strpos($ajax_src, 'parse_mutation_return_context') < strpos($ajax_src, '$result = $use_case->'));
CanonicalUpdateContainerAjax::register();
ac_assert('Register hook', in_array('wp_ajax_aa_update_canonical_container', $GLOBALS['aa_test_actions'], true));

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
