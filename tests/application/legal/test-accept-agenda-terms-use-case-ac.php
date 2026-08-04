<?php
/**
 * AC — AcceptAgendaTermsUseCase.
 *
 * Ejecutar: php tests/application/legal/test-accept-agenda-terms-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);

$GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
$GLOBALS['aa_test_can_manage_options'] = true;
$GLOBALS['aa_test_user_id'] = 42;
$GLOBALS['aa_test_transients'] = ['aa_legal_gate_ready_42' => '1'];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return array_key_exists($key, $GLOBALS['aa_test_options'])
            ? $GLOBALS['aa_test_options'][$key]
            : $default;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap) {
        return $cap === 'manage_options' && !empty($GLOBALS['aa_test_can_manage_options']);
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return (int) ($GLOBALS['aa_test_user_id'] ?? 0);
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (!function_exists('get_transient')) {
    function get_transient($key) {
        return array_key_exists($key, $GLOBALS['aa_test_transients'])
            ? $GLOBALS['aa_test_transients'][$key]
            : false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['aa_test_transients'][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['aa_test_transients'][$key]);
        return true;
    }
}
if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-legal-gate-backend-client.php';
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-terms-consent.php';
require_once $plugin_root . '/includes/application/legal/GetLegalGateStatusUseCase.php';
require_once $plugin_root . '/includes/application/legal/AcceptAgendaTermsUseCase.php';

final class Mock_Legal_Gate_Accept_Client extends AA_Legal_Gate_Backend_Client {
    /** @var array<string,mixed>|null */
    public static $last_payload = null;
    /** @var array<string,mixed> */
    public static $response = ['ok' => true, 'already_accepted' => false, 'document_version' => '2026-08-03.1', 'source' => 'agenda_legal_gate'];

    public function acceptTerms(array $payload): array {
        self::$last_payload = $payload;
        return self::$response;
    }
}

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
        return;
    }
    $failed[] = $label;
    echo "[FAIL] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
}

$uc = new AcceptAgendaTermsUseCase(new Mock_Legal_Gate_Accept_Client());
$r = $uc->execute([
    'terms_consent' => true,
    'terms_document_version' => '2026-08-03.1',
    'wp_user_id' => 9999,
    'account_id' => 'acc_should_ignore',
    'installation_id' => 'inst_should_ignore',
    'subscription_request_id' => 'sr_should_ignore',
]);
ac_assert('accept succeeds', !empty($r['success']));
ac_assert(
    'wp_user_id comes from PHP session',
    (Mock_Legal_Gate_Accept_Client::$last_payload['wp_user_id'] ?? null) === 42
);
ac_assert(
    'sends shown version',
    (Mock_Legal_Gate_Accept_Client::$last_payload['terms_document_version'] ?? '') === '2026-08-03.1'
);
ac_assert(
    'payload has no browser identity ids',
    !isset(Mock_Legal_Gate_Accept_Client::$last_payload['account_id'])
    && !isset(Mock_Legal_Gate_Accept_Client::$last_payload['installation_id'])
    && !isset(Mock_Legal_Gate_Accept_Client::$last_payload['subscription_request_id'])
);
ac_assert('clears ready cache after accept', get_transient('aa_legal_gate_ready_42') === false);

$GLOBALS['aa_test_can_manage_options'] = false;
$r = $uc->execute(['terms_consent' => true, 'terms_document_version' => '2026-08-03.1']);
ac_assert('non-admin forbidden', empty($r['success']) && ($r['error']['code'] ?? '') === 'legal_gate_forbidden');

$GLOBALS['aa_test_can_manage_options'] = true;
$r = $uc->execute(['terms_consent' => false, 'terms_document_version' => '2026-08-03.1']);
ac_assert('consent required', empty($r['success']) && ($r['error']['code'] ?? '') === 'terms_consent_required');

$r = $uc->execute(['terms_consent' => true, 'terms_document_version' => 'bad']);
ac_assert('invalid version rejected', empty($r['success']) && ($r['error']['code'] ?? '') === 'terms_document_version_invalid');

$GLOBALS['aa_test_user_id'] = 0;
$r = $uc->execute(['terms_consent' => true, 'terms_document_version' => '2026-08-03.1']);
ac_assert('unauthenticated forbidden', empty($r['success']) && ($r['error']['code'] ?? '') === 'legal_gate_forbidden');

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
