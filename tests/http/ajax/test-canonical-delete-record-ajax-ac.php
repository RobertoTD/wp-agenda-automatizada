<?php
/**
 * AC Test — CanonicalDeleteRecordAjax (SB1-5B4).
 *
 * Ejecutar: php tests/http/ajax/test-canonical-delete-record-ajax-ac.php
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

$ajax_file = $plugin_root . '/includes/http/ajax/CanonicalDeleteRecordAjax.php';
$ajax_src = (string) file_get_contents($ajax_file);
$boot_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$support_src = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalShellWriteAjaxSupport.php');
$shell_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
$card_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php');
$js_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js');

ac_assert('Ajax file readable', $ajax_src !== '');
ac_assert('Action constante', strpos($ajax_src, "ACTION = 'aa_delete_canonical_record'") !== false);
ac_assert('Nonce específico', strpos($ajax_src, "NONCE_ACTION = 'aa_delete_canonical_record'") !== false);
ac_assert('Solo wp_ajax_', strpos($ajax_src, "add_action('wp_ajax_'") !== false);
ac_assert('Sin nopriv', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('Bootstrap registra', strpos($boot_src, 'CanonicalDeleteRecordAjax::register()') !== false);
ac_assert('Access Policy vía soporte SB1-5C1', strpos($support_src, 'AA_Canonical_Access_Policy::check_family_access') !== false
    && strpos($ajax_src, 'CanonicalShellWriteAjaxSupport::authorize_identity') !== false);
ac_assert('Retire UseCase', strpos($ajax_src, 'new RetireCanonicalRecordUseCase()') !== false
    && strpos($ajax_src, '->execute($command)') !== false
    && strpos($ajax_src, 'WriteCanonicalShellRecordUseCase') === false);
ac_assert('Sin mandate_id en JSON', strpos($ajax_src, "'mandate_id'") === false
    && strpos($ajax_src, '"mandate_id"') === false
    && strpos($ajax_src, "'batch_seq'") === false
    && strpos($ajax_src, '"batch_seq"') === false);
ac_assert('Códigos de identidad estables en soporte', strpos($support_src, "'unknown_identity'") !== false
    && strpos($support_src, "'family_disabled'") !== false
    && strpos($support_src, "'family_not_provisioned'") !== false
    && strpos($support_src, "'schema_not_ready'") !== false
    && strpos($support_src, "'enablement_unavailable'") !== false);
ac_assert('Soporte contenido: sin SQL, JSON, $_POST, redirects ni commands', strpos($support_src, '$wpdb') === false
    && preg_match('/->query\(|->insert\(|->prepare\(/', $support_src) !== 1
    && strpos($support_src, 'wp_send_json') === false
    && strpos($support_src, '$_POST') === false
    && strpos($support_src, 'AA_Canonical_Shell_Base_Url_Policy') === false
    && strpos($support_src, 'Command') === false);
ac_assert('Sin SQL directo', strpos($ajax_src, '$wpdb') === false
    && !preg_match('/->query\(|->insert\(/', $ajax_src));
ac_assert('Códigos estables', strpos($ajax_src, "'record_not_found'") !== false
    && strpos($ajax_src, "'invalid_record_id'") !== false
    && strpos($ajax_src, "'uncertain'") !== false
    && strpos($ajax_src, "'incomplete'") !== false
    && strpos($ajax_src, "'conflict'") !== false
    && strpos($ajax_src, "'cancel_rejected'") !== false
    && strpos($ajax_src, "'resource_busy'") !== false);
ac_assert('Sin aa_finance_', strpos($ajax_src, 'aa_finance_') === false);
ac_assert('Sin aa_expediente_', strpos($ajax_src, 'aa_expediente_') === false);
ac_assert('Sin amount', strpos($ajax_src, 'amount') === false);
ac_assert('Sin soft delete', strpos($ajax_src, 'deleted_at') === false && strpos($ajax_src, 'soft') === false);
ac_assert('Card Eliminar', strpos($card_src, 'aa-shell-delete-record-btn') !== false
    && strpos($card_src, 'Eliminar') !== false);
ac_assert('Modal delete separado', strpos($shell_src, 'aa-shell-delete-record-modal') !== false
    && strpos($shell_src, 'deleteAction') !== false);
ac_assert('JS Continuar y cancelar local', strpos($js_src, "retire_action', 'cancel'") !== false
    && strpos($js_src, "code === 'incomplete'") !== false
    && strpos($js_src, 'deleteAbortBtn') !== false
    && strpos($js_src, "Continuar") !== false);
ac_assert('JS uncertain bloquea', strpos($js_src, 'deleteBlocked') !== false
    && strpos($js_src, 'reloadAfterUncertain') !== false
    && strpos($shell_src, 'Recargar lista') !== false);

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
        return $v;
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
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
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordResult.php';

if (!class_exists('RetireCanonicalRecordUseCase')) {
    final class RetireCanonicalRecordUseCase {
        public function execute(RetireCanonicalRecordCommand $command): RetireCanonicalRecordResult {
            $handler = $GLOBALS['aa_test_retire_handler'] ?? null;
            if (!is_callable($handler)) {
                return RetireCanonicalRecordResult::persistence_failed();
            }

            return $handler($command);
        }
    }
}

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

$GLOBALS['aa_test_retire_mode'] = 'confirmed';
$GLOBALS['aa_test_retire_deleted'] = [];
$GLOBALS['aa_test_retire_handler'] = static function (RetireCanonicalRecordCommand $command): RetireCanonicalRecordResult {
    $mode = (string) ($GLOBALS['aa_test_retire_mode'] ?? 'confirmed');
    $cid = $command->container_id();
    $rid = $command->record_id();
    $key = $cid . ':' . $rid;

    if ($mode === 'forbidden') {
        return RetireCanonicalRecordResult::forbidden();
    }
    if ($cid === 99) {
        return RetireCanonicalRecordResult::container_not_found($cid);
    }
    if ($command->is_cancel()) {
        if ($mode === 'cancel_rejected') {
            return RetireCanonicalRecordResult::cancel_rejected($rid, $cid);
        }
        return RetireCanonicalRecordResult::cancelled($rid, $cid);
    }
    if ($mode === 'uncertain') {
        return RetireCanonicalRecordResult::uncertain($rid, $cid);
    }
    if ($mode === 'incomplete') {
        return RetireCanonicalRecordResult::incomplete($rid, $cid);
    }
    if ($mode === 'conflict') {
        return RetireCanonicalRecordResult::conflict($rid, $cid, ['can_cancel' => true]);
    }
    if ($mode === 'persist_fail') {
        return RetireCanonicalRecordResult::persistence_failed();
    }
    if ($mode === 'busy') {
        return RetireCanonicalRecordResult::resource_busy();
    }
    if (!empty($GLOBALS['aa_test_retire_deleted'][$key])) {
        return RetireCanonicalRecordResult::record_not_found($cid, $rid);
    }
    if ($rid !== 10 || $cid !== 1) {
        return RetireCanonicalRecordResult::record_not_found($cid, $rid);
    }
    $GLOBALS['aa_test_retire_deleted'][$key] = true;

    return RetireCanonicalRecordResult::confirmed($rid, $cid);
};

AA_Canonical_Core_Bootstrap::bootstrap();
require_once $ajax_file;

function aa_run_delete_record_ajax(array $post): ?array {
    $_POST = $post;
    $GLOBALS['aa_test_json'] = null;
    try {
        CanonicalDeleteRecordAjax::handle();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent') {
            throw $e;
        }
    }
    return $GLOBALS['aa_test_json'];
}

function aa_base_delete_post(array $over = []): array {
    return array_merge([
        'nonce' => 'good-nonce',
        'family_key' => 'finance',
        'container_id' => '1',
        'record_id' => '10',
    ], $over);
}

$GLOBALS['aa_test_logged_in'] = false;
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('No auth → unauthorized', ($r['data']['code'] ?? '') === 'unauthorized' && ($r['status'] ?? 0) === 401);
$GLOBALS['aa_test_logged_in'] = true;

$GLOBALS['aa_test_nonce_valid'] = false;
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Bad nonce → invalid_nonce', ($r['data']['code'] ?? '') === 'invalid_nonce');
$GLOBALS['aa_test_nonce_valid'] = true;

$r = aa_run_delete_record_ajax(aa_base_delete_post(['family_key' => ['x']]));
ac_assert('Non-scalar family → invalid_payload', ($r['data']['code'] ?? '') === 'invalid_payload');

$r = aa_run_delete_record_ajax(aa_base_delete_post(['family_key' => 'nope']));
ac_assert('Unknown family → unknown_identity', ($r['data']['code'] ?? '') === 'unknown_identity');

$r = aa_run_delete_record_ajax(aa_base_delete_post(['container_id' => '0']));
ac_assert('container_id 0 → invalid_container_id', ($r['data']['code'] ?? '') === 'invalid_container_id');

$r = aa_run_delete_record_ajax(aa_base_delete_post(['record_id' => '12abc']));
ac_assert('record_id dirty → invalid_record_id', ($r['data']['code'] ?? '') === 'invalid_record_id');

$GLOBALS['aa_test_caps'] = [];
$r = aa_run_delete_record_ajax(aa_base_delete_post(['family_key' => 'archive']));
ac_assert('Archive without manage_options → forbidden', ($r['data']['code'] ?? '') === 'forbidden');
$GLOBALS['aa_test_caps'] = ['manage_options' => true];

AA_Canonical_Family_Enablement_Store::$enabled_map = ['finance' => false, 'archive' => true];
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Disabled → family_disabled', ($r['data']['code'] ?? '') === 'family_disabled');

AA_Canonical_Family_Enablement_Store::$enabled_map = [];
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Not provisioned → family_not_provisioned', ($r['data']['code'] ?? '') === 'family_not_provisioned');

AA_Canonical_Family_Enablement_Store::$enabled_map = ['finance' => true, 'archive' => true];
AA_Canonical_Family_Enablement_Store::$mode = 'schema';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Schema → schema_not_ready', ($r['data']['code'] ?? '') === 'schema_not_ready');

AA_Canonical_Family_Enablement_Store::$mode = 'persist';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Enablement fail → enablement_unavailable', ($r['data']['code'] ?? '') === 'enablement_unavailable');
AA_Canonical_Family_Enablement_Store::$mode = 'ok';

$r = aa_run_delete_record_ajax(aa_base_delete_post(['container_id' => '99']));
ac_assert('Missing container → container_not_found', ($r['data']['code'] ?? '') === 'container_not_found');

$r = aa_run_delete_record_ajax(aa_base_delete_post(['record_id' => '999']));
ac_assert('Missing record → record_not_found', ($r['data']['code'] ?? '') === 'record_not_found');

$r = aa_run_delete_record_ajax(aa_base_delete_post(['container_id' => '2', 'record_id' => '10']));
ac_assert('Cross-container → record_not_found', ($r['data']['code'] ?? '') === 'record_not_found');

$GLOBALS['aa_test_retire_mode'] = 'persist_fail';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Persist fail → persistence_failed', ($r['data']['code'] ?? '') === 'persistence_failed');

$GLOBALS['aa_test_retire_mode'] = 'uncertain';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Uncertain code', ($r['data']['code'] ?? '') === 'uncertain');
ac_assert('Uncertain HTTP 409', ($r['status'] ?? 0) === 409);
ac_assert('Uncertain message reload', strpos((string) ($r['data']['message'] ?? ''), 'Recarga la lista') !== false);
ac_assert('Uncertain redirect_url', is_string($r['data']['redirect_url'] ?? null)
    && strpos($r['data']['redirect_url'], 'view=records') !== false);
ac_assert('Uncertain sin SQL', stripos((string) ($r['data']['message'] ?? ''), 'sql') === false);

$GLOBALS['aa_test_retire_mode'] = 'incomplete';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Incomplete 409', ($r['data']['code'] ?? '') === 'incomplete' && ($r['status'] ?? 0) === 409);
ac_assert('Incomplete can_continue', ($r['data']['can_continue'] ?? false) === true);
ac_assert('Incomplete sin mandate_id', !array_key_exists('mandate_id', $r['data'] ?? []));

$GLOBALS['aa_test_retire_mode'] = 'conflict';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Conflict 409', ($r['data']['code'] ?? '') === 'conflict' && ($r['data']['can_cancel'] ?? false) === true);

$GLOBALS['aa_test_retire_mode'] = 'confirmed';
$r = aa_run_delete_record_ajax(aa_base_delete_post(['retire_action' => 'cancel']));
ac_assert('Cancel success', ($r['success'] ?? false) === true && ($r['data']['status'] ?? '') === 'cancelled');

$GLOBALS['aa_test_retire_mode'] = 'cancel_rejected';
$r = aa_run_delete_record_ajax(aa_base_delete_post(['retire_action' => 'cancel']));
ac_assert('Cancel rejected', ($r['data']['code'] ?? '') === 'cancel_rejected' && ($r['status'] ?? 0) === 409);

$GLOBALS['aa_test_retire_mode'] = 'busy';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Busy → resource_busy', ($r['data']['code'] ?? '') === 'resource_busy' && ($r['status'] ?? 0) === 409);

$GLOBALS['aa_test_retire_mode'] = 'forbidden';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Corrida ajena → forbidden', ($r['data']['code'] ?? '') === 'forbidden' && ($r['status'] ?? 0) === 403);

$GLOBALS['aa_test_retire_mode'] = 'confirmed';
$r = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Confirmed success', ($r['success'] ?? false) === true);
ac_assert('Confirmed status', ($r['data']['status'] ?? '') === 'confirmed');
ac_assert('Confirmed resource_id', (int) ($r['data']['resource_id'] ?? 0) === 10);
ac_assert('Confirmed container_id', (int) ($r['data']['container_id'] ?? 0) === 1);
ac_assert('Confirmed sin mandate_id', !array_key_exists('mandate_id', $r['data'] ?? []));
ac_assert(
    'Redirect records page 1',
    is_string($r['data']['redirect_url'] ?? null)
    && strpos($r['data']['redirect_url'], 'view=records') !== false
    && strpos($r['data']['redirect_url'], 'page=') === false
    && strpos($r['data']['redirect_url'], 'containers_page=') === false
);

$r2 = aa_run_delete_record_ajax(aa_base_delete_post());
ac_assert('Second delete → record_not_found', ($r2['data']['code'] ?? '') === 'record_not_found');

CanonicalDeleteRecordAjax::register();
ac_assert('Register hook', in_array('wp_ajax_aa_delete_canonical_record', $GLOBALS['aa_test_actions'], true));

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
