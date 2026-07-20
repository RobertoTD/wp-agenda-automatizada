<?php
/**
 * AC C8A1a — AA_Training_Backend_Client + Training use cases.
 *
 * Ejecutar: php tests/infrastructure/backend/test-aa-training-backend-client-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);

$total  = 0;
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

$GLOBALS['aa_test_options']       = ['aa_client_secret' => 'secret-value-xyz'];
$GLOBALS['aa_test_http_response'] = null;
$GLOBALS['aa_test_http_calls']    = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }
        return $default;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $message;

        public function __construct($code = '', $message = '') {
            $this->message = (string) $message;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return (string) ($response['body'] ?? '');
    }
}

if (!function_exists('aa_send_authenticated_request')) {
    function aa_send_authenticated_request($endpoint, $method, $data = null) {
        $GLOBALS['aa_test_http_calls'][] = [
            'endpoint' => $endpoint,
            'method'   => $method,
            'data'     => $data,
        ];
        return $GLOBALS['aa_test_http_response'];
    }
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-training-backend-client.php';
require_once $plugin_root . '/includes/application/training/TrainingEnrollmentUseCase.php';
require_once $plugin_root . '/includes/application/training/TrainingConsentUseCase.php';
require_once $plugin_root . '/includes/application/training/TrainingContentUseCase.php';

function reset_http(): void {
    $GLOBALS['aa_test_http_calls']    = [];
    $GLOBALS['aa_test_http_response'] = null;
    $GLOBALS['aa_test_options']       = ['aa_client_secret' => 'secret-value-xyz'];
}

function json_response(int $code, array $body): array {
    return [
        'response' => ['code' => $code],
        'body'     => json_encode($body),
    ];
}

$client = new AA_Training_Backend_Client();

// ─── 1-2. URLs / métodos / course key fija ─────────────────────────
reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'                      => true,
    'course_key'              => 'fundamentos-deoia',
    'course_status'           => 'published',
    'training_access_allowed' => true,
    'access_state'            => 'active',
    'enrollment'              => ['status' => 'active'],
]);
$result = $client->get_status();
ac_assert('1. get_status uses GET', ($GLOBALS['aa_test_http_calls'][0]['method'] ?? '') === 'GET');
ac_assert(
    '1. get_status URL is /training/status with fixed course_key',
    ($GLOBALS['aa_test_http_calls'][0]['endpoint'] ?? '') === 'http://localhost:3000/training/status?course_key=fundamentos-deoia'
);
ac_assert('2. COURSE_KEY constant is fundamentos-deoia', AA_Training_Backend_Client::COURSE_KEY === 'fundamentos-deoia');
ac_assert('8. get_status success preserves payload fields', ($result['ok'] ?? false) === true && ($result['result']['access_state'] ?? '') === 'active');

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'         => true,
    'course_key' => 'fundamentos-deoia',
    'access_state' => 'active',
    'enrollment' => ['status' => 'active'],
    'course_status' => 'published',
    'training_access_allowed' => true,
]);
$client->enroll();
ac_assert('1. enroll uses POST', ($GLOBALS['aa_test_http_calls'][0]['method'] ?? '') === 'POST');
ac_assert(
    '1. enroll URL is /training/enroll',
    ($GLOBALS['aa_test_http_calls'][0]['endpoint'] ?? '') === 'http://localhost:3000/training/enroll'
);
ac_assert(
    '4. enroll body has fixed course_key only',
    ($GLOBALS['aa_test_http_calls'][0]['data'] ?? null) === ['course_key' => 'fundamentos-deoia']
);

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok' => true,
    'course_key' => 'fundamentos-deoia',
    'access_state' => 'unsubscribed',
    'enrollment' => ['status' => 'unsubscribed'],
    'course_status' => 'published',
    'training_access_allowed' => true,
]);
$client->unsubscribe();
ac_assert(
    '1. unsubscribe URL is /training/unsubscribe',
    ($GLOBALS['aa_test_http_calls'][0]['endpoint'] ?? '') === 'http://localhost:3000/training/unsubscribe'
);
ac_assert(
    '4. unsubscribe body has fixed course_key',
    ($GLOBALS['aa_test_http_calls'][0]['data']['course_key'] ?? '') === 'fundamentos-deoia'
);

// ─── 3-4. Consent source + bodies ──────────────────────────────────
reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'         => true,
    'course_key' => 'fundamentos-deoia',
    'consent'    => ['status' => 'not_accepted'],
    'training_access_allowed' => true,
]);
$client->get_consent_status();
ac_assert(
    '1. consent status URL',
    ($GLOBALS['aa_test_http_calls'][0]['endpoint'] ?? '') === 'http://localhost:3000/training/consent/status?course_key=fundamentos-deoia'
);

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'         => true,
    'course_key' => 'fundamentos-deoia',
    'consent'    => ['status' => 'accepted'],
    'training_access_allowed' => true,
]);
$client->accept_consent();
ac_assert(
    '3. accept_consent source is account_training_card',
    ($GLOBALS['aa_test_http_calls'][0]['data']['source'] ?? '') === 'account_training_card'
);
ac_assert(
    '4. accept_consent body includes course_key',
    ($GLOBALS['aa_test_http_calls'][0]['data']['course_key'] ?? '') === 'fundamentos-deoia'
);
ac_assert(
    '3. CONSENT_SOURCE constant matches',
    AA_Training_Backend_Client::CONSENT_SOURCE_ACCOUNT_CARD === 'account_training_card'
);

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'         => true,
    'course_key' => 'fundamentos-deoia',
    'consent'    => ['status' => 'revoked'],
    'training_access_allowed' => true,
]);
$client->revoke_consent();
ac_assert(
    '1. revoke URL is /training/consent/revoke',
    strpos((string) $GLOBALS['aa_test_http_calls'][0]['endpoint'], '/training/consent/revoke') !== false
);

// ─── 5-6. Lesson key ───────────────────────────────────────────────
reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'         => true,
    'course_key' => 'fundamentos-deoia',
    'lesson'     => ['key' => 'bienvenida', 'availability' => 'available'],
    'blocks'     => [['type' => 'rich_text', 'html' => '<p>x</p>']],
]);
$result = $client->get_lesson('bienvenida');
ac_assert('5. valid lesson key accepted', ($result['ok'] ?? false) === true);
ac_assert(
    '5. lesson URL encodes key under fundamentos-deoia',
    ($GLOBALS['aa_test_http_calls'][0]['endpoint'] ?? '') === 'http://localhost:3000/training/courses/fundamentos-deoia/lessons/bienvenida'
);
ac_assert('7. get_lesson uses aa_send_authenticated_request', count($GLOBALS['aa_test_http_calls']) === 1);

reset_http();
$result = $client->get_lesson('../etc/passwd');
ac_assert('6. traversal lesson key rejected locally', ($result['code'] ?? '') === 'training_content_lesson_key_invalid');
ac_assert('6. traversal skips HTTP', count($GLOBALS['aa_test_http_calls']) === 0);

reset_http();
$result = $client->get_lesson('BAD_KEY');
ac_assert('6. invalid lesson key rejected', ($result['code'] ?? '') === 'training_content_lesson_key_invalid');

reset_http();
$result = $client->get_lesson('path/with/slash');
ac_assert('6. slash rejected', ($result['code'] ?? '') === 'training_content_lesson_key_invalid');

reset_http();
$result = $client->get_lesson('');
ac_assert('6. empty lesson key rejected', ($result['code'] ?? '') === 'training_content_lesson_key_invalid');

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'     => true,
    'course' => ['key' => 'fundamentos-deoia', 'title' => 'T'],
    'lessons' => [],
]);
$client->get_course();
ac_assert(
    '2. get_course URL uses fixed course key',
    ($GLOBALS['aa_test_http_calls'][0]['endpoint'] ?? '') === 'http://localhost:3000/training/courses/fundamentos-deoia'
);

// ─── 9. training_* errors preserved ────────────────────────────────
reset_http();
$GLOBALS['aa_test_http_response'] = json_response(403, [
    'ok'    => false,
    'error' => 'training_not_eligible',
]);
$result = $client->enroll();
ac_assert('9. training_not_eligible preserved', ($result['code'] ?? '') === 'training_not_eligible');

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(409, [
    'ok'    => false,
    'error' => 'training_content_lesson_unavailable',
]);
$result = $client->get_lesson('planeacion');
ac_assert('9. training_content_lesson_unavailable preserved', ($result['code'] ?? '') === 'training_content_lesson_unavailable');

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(404, [
    'ok'    => false,
    'error' => 'training_enrollment_not_found',
]);
$result = $client->get_course();
ac_assert('9. training_enrollment_not_found preserved', ($result['code'] ?? '') === 'training_enrollment_not_found');

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(409, [
    'ok'    => false,
    'error' => 'training_enrollment_not_active',
]);
$result = $client->get_course();
ac_assert('9. training_enrollment_not_active preserved', ($result['code'] ?? '') === 'training_enrollment_not_active');

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(500, [
    'ok'    => false,
    'error' => 'training_content_render_failed',
]);
$result = $client->get_lesson('bienvenida');
ac_assert('9. training_content_render_failed preserved', ($result['code'] ?? '') === 'training_content_render_failed');

// ─── 10. WP_Error / timeout ────────────────────────────────────────
reset_http();
$GLOBALS['aa_test_http_response'] = new WP_Error('http_request_failed', 'cURL error 28: timeout secret-value-xyz');
$result = $client->get_status();
ac_assert('10. WP_Error normalizes to training_backend_unreachable', ($result['code'] ?? '') === 'training_backend_unreachable');
ac_assert(
    '12. transport error message does not echo secret',
    strpos(json_encode($result), 'secret-value-xyz') === false
);

// ─── 11. invalid response ──────────────────────────────────────────
reset_http();
$GLOBALS['aa_test_http_response'] = [
    'response' => ['code' => 200],
    'body'     => 'not-json',
];
$result = $client->get_status();
ac_assert('11. invalid JSON → training_backend_invalid_response', ($result['code'] ?? '') === 'training_backend_invalid_response');

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok' => true,
    // missing course_key
]);
$result = $client->get_status();
ac_assert('11. missing required fields → invalid_response', ($result['code'] ?? '') === 'training_backend_invalid_response');

// ─── Use cases ─────────────────────────────────────────────────────
reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'         => true,
    'course_key' => 'fundamentos-deoia',
    'course_status' => 'published',
    'training_access_allowed' => true,
    'access_state' => 'not_enrolled',
    'enrollment' => null,
]);
$enrollment_uc = new TrainingEnrollmentUseCase($client);
$uc_result     = $enrollment_uc->get_status();
ac_assert('use case status success shape', ($uc_result['success'] ?? false) === true);
ac_assert('use case preserves access_state', ($uc_result['data']['access_state'] ?? '') === 'not_enrolled');

reset_http();
$GLOBALS['aa_test_options']['aa_client_secret'] = '';
$uc_result = (new TrainingEnrollmentUseCase($client))->enroll();
ac_assert('use case not configured without secret', ($uc_result['error']['code'] ?? '') === 'training_backend_not_configured');
ac_assert('use case not configured skips HTTP', count($GLOBALS['aa_test_http_calls']) === 0);

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(403, [
    'ok'    => false,
    'error' => 'training_not_eligible',
]);
$uc_result = (new TrainingEnrollmentUseCase($client))->enroll();
ac_assert('use case preserves training_not_eligible', ($uc_result['error']['code'] ?? '') === 'training_not_eligible');

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'         => true,
    'course_key' => 'fundamentos-deoia',
    'consent'    => ['status' => 'accepted', 'source' => 'account_training_card'],
    'training_access_allowed' => true,
]);
$consent_uc = new TrainingConsentUseCase($client);
$uc_result  = $consent_uc->accept();
ac_assert('consent use case accept success', ($uc_result['success'] ?? false) === true);
ac_assert(
    'consent use case triggered account_training_card source',
    ($GLOBALS['aa_test_http_calls'][0]['data']['source'] ?? '') === 'account_training_card'
);

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'     => true,
    'course' => ['key' => 'fundamentos-deoia', 'title' => 'Fundamentos'],
    'lessons' => [
        ['key' => 'bienvenida', 'availability' => 'available', 'position' => 1, 'title' => 'B'],
    ],
]);
$content_uc = new TrainingContentUseCase($client);
$uc_result  = $content_uc->get_course();
ac_assert('content use case course success', ($uc_result['success'] ?? false) === true);
ac_assert('content use case has lessons', isset($uc_result['data']['lessons']) && is_array($uc_result['data']['lessons']));

reset_http();
$uc_result = $content_uc->get_lesson('../x');
ac_assert('content use case rejects traversal', ($uc_result['error']['code'] ?? '') === 'training_content_lesson_key_invalid');
ac_assert('content use case traversal skips HTTP', count($GLOBALS['aa_test_http_calls']) === 0);

reset_http();
$GLOBALS['aa_test_http_response'] = json_response(200, [
    'ok'         => true,
    'course_key' => 'fundamentos-deoia',
    'lesson'     => ['key' => 'bienvenida'],
    'blocks'     => [['type' => 'rich_text', 'html' => '<p>ok</p>']],
]);
$uc_result = $content_uc->get_lesson('bienvenida');
ac_assert('content use case lesson success', ($uc_result['success'] ?? false) === true);
ac_assert('content use case has blocks', isset($uc_result['data']['blocks']));
$encoded = json_encode($uc_result);
ac_assert('12. no secret in use case result', strpos($encoded, 'secret-value-xyz') === false);
ac_assert('12. no HMAC wording in result', stripos($encoded, 'signature') === false);

echo "\nPassed {$passed}/{$total}\n";
if ($failed !== []) {
    echo "Failed:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}

exit(0);
