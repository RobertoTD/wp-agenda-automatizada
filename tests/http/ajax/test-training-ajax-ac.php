<?php
/**
 * AC C8A1b — TrainingAjax wiring and handlers.
 *
 * Ejecutar: php tests/http/ajax/test-training-ajax-ac.php
 */

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

$ajax_src      = file_get_contents($plugin_root . '/includes/http/ajax/TrainingAjax.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

$expected_actions = [
    'aa_get_training_status',
    'aa_enroll_training',
    'aa_unsubscribe_training',
    'aa_get_training_consent_status',
    'aa_accept_training_consent',
    'aa_revoke_training_consent',
    'aa_get_training_course',
    'aa_get_training_lesson',
];

ac_assert('TrainingAjax file readable', $ajax_src !== false);
foreach ($expected_actions as $action) {
    ac_assert("registers {$action}", strpos($ajax_src, $action) !== false);
}
ac_assert('shared nonce aa_training_nonce', strpos($ajax_src, 'aa_training_nonce') !== false);
ac_assert('checks manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('uses check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('no nopriv actions', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('does not read installationId from request', stripos($ajax_src, 'installationId') === false && strpos($ajax_src, 'installation_id') === false);
ac_assert('does not read enrollmentId from request', stripos($ajax_src, 'enrollmentId') === false && strpos($ajax_src, 'enrollment_id') === false);
ac_assert('does not read agendaClientId from request', stripos($ajax_src, 'agendaClientId') === false && strpos($ajax_src, 'agenda_client_id') === false);
ac_assert('lesson handler reads lessonKey only', strpos($ajax_src, "\$_POST['lessonKey']") !== false);
ac_assert('bootstrap registers TrainingAjax', strpos($bootstrap_src, 'TrainingAjax::register()') !== false);
ac_assert('bootstrap loads TrainingEnrollmentUseCase', strpos($bootstrap_src, 'TrainingEnrollmentUseCase.php') !== false);
ac_assert('bootstrap loads TrainingConsentUseCase', strpos($bootstrap_src, 'TrainingConsentUseCase.php') !== false);
ac_assert('bootstrap loads TrainingContentUseCase', strpos($bootstrap_src, 'TrainingContentUseCase.php') !== false);
ac_assert('bootstrap loads AA_Training_Backend_Client', strpos($bootstrap_src, 'class-aa-training-backend-client.php') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['aa_test_json'] = null;
$GLOBALS['aa_test_options'] = ['aa_client_secret' => 'secret'];
$GLOBALS['aa_test_can_manage_options'] = true;
$GLOBALS['aa_test_nonce_valid'] = true;
$GLOBALS['aa_test_die_message'] = null;

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return (bool) ($GLOBALS['aa_test_can_manage_options'] ?? true);
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg) {
        if (empty($GLOBALS['aa_test_nonce_valid'])) {
            $GLOBALS['aa_test_die_message'] = 'bad nonce';
            throw new RuntimeException('bad_nonce');
        }
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = [
            'success' => true,
            'data'    => $data,
            'status'  => $status_code,
        ];
        throw new RuntimeException('json_sent');
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        $GLOBALS['aa_test_json'] = [
            'success' => false,
            'data'    => $data,
            'status'  => $status_code,
        ];
        throw new RuntimeException('json_sent');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim((string) $str);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return $value;
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }
        return $default;
    }
}

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-training-backend-client.php';
require_once $plugin_root . '/includes/application/training/TrainingEnrollmentUseCase.php';
require_once $plugin_root . '/includes/application/training/TrainingConsentUseCase.php';
require_once $plugin_root . '/includes/application/training/TrainingContentUseCase.php';
require_once $plugin_root . '/includes/http/ajax/TrainingAjax.php';

final class Mock_Training_Enrollment_Use_Case extends TrainingEnrollmentUseCase {
    /** @var array<string,mixed> */
    public static $status_response = ['success' => true, 'data' => ['access_state' => 'active']];
    /** @var array<string,mixed> */
    public static $enroll_response = ['success' => true, 'data' => ['access_state' => 'active']];
    /** @var array<string,mixed> */
    public static $unsubscribe_response = ['success' => true, 'data' => ['access_state' => 'unsubscribed']];
    /** @var list<string> */
    public static $calls = [];

    public function get_status(): array {
        self::$calls[] = 'get_status';
        return self::$status_response;
    }

    public function enroll(): array {
        self::$calls[] = 'enroll';
        return self::$enroll_response;
    }

    public function unsubscribe(): array {
        self::$calls[] = 'unsubscribe';
        return self::$unsubscribe_response;
    }
}

final class Mock_Training_Consent_Use_Case extends TrainingConsentUseCase {
    /** @var array<string,mixed> */
    public static $status_response = ['success' => true, 'data' => ['consent' => ['status' => 'not_accepted']]];
    /** @var array<string,mixed> */
    public static $accept_response = ['success' => true, 'data' => ['consent' => ['status' => 'accepted']]];
    /** @var array<string,mixed> */
    public static $revoke_response = ['success' => true, 'data' => ['consent' => ['status' => 'revoked']]];
    /** @var list<string> */
    public static $calls = [];

    public function get_status(): array {
        self::$calls[] = 'get_status';
        return self::$status_response;
    }

    public function accept(): array {
        self::$calls[] = 'accept';
        return self::$accept_response;
    }

    public function revoke(): array {
        self::$calls[] = 'revoke';
        return self::$revoke_response;
    }
}

final class Mock_Training_Content_Use_Case extends TrainingContentUseCase {
    /** @var array<string,mixed> */
    public static $course_response = ['success' => true, 'data' => ['course' => ['key' => 'fundamentos-deoia'], 'lessons' => []]];
    /** @var array<string,mixed> */
    public static $lesson_response = ['success' => true, 'data' => ['blocks' => []]];
    /** @var string|null */
    public static $last_lesson_key = null;
    /** @var list<string> */
    public static $calls = [];

    public function get_course(): array {
        self::$calls[] = 'get_course';
        return self::$course_response;
    }

    public function get_lesson($lesson_key): array {
        self::$calls[] = 'get_lesson';
        self::$last_lesson_key = is_string($lesson_key) ? $lesson_key : null;
        return self::$lesson_response;
    }
}

final class Testable_TrainingAjax extends TrainingAjax {
    protected static function resolveEnrollmentUseCase(): TrainingEnrollmentUseCase {
        return new Mock_Training_Enrollment_Use_Case();
    }

    protected static function resolveConsentUseCase(): TrainingConsentUseCase {
        return new Mock_Training_Consent_Use_Case();
    }

    protected static function resolveContentUseCase(): TrainingContentUseCase {
        return new Mock_Training_Content_Use_Case();
    }
}

function run_ajax(callable $handler): array {
    $GLOBALS['aa_test_json'] = null;
    $GLOBALS['aa_test_die_message'] = null;
    try {
        $handler();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'json_sent' && $e->getMessage() !== 'bad_nonce') {
            throw $e;
        }
    }

    return $GLOBALS['aa_test_json'] ?? [];
}

function reset_mocks(): void {
    Mock_Training_Enrollment_Use_Case::$calls = [];
    Mock_Training_Consent_Use_Case::$calls = [];
    Mock_Training_Content_Use_Case::$calls = [];
    Mock_Training_Content_Use_Case::$last_lesson_key = null;
    Mock_Training_Enrollment_Use_Case::$status_response = ['success' => true, 'data' => ['access_state' => 'active', 'course_key' => 'fundamentos-deoia']];
    Mock_Training_Enrollment_Use_Case::$enroll_response = ['success' => true, 'data' => ['access_state' => 'active']];
    $GLOBALS['aa_test_can_manage_options'] = true;
    $GLOBALS['aa_test_nonce_valid'] = true;
    $_POST = [];
}

ac_assert('TrainingAjax::register callable', method_exists('TrainingAjax', 'register'));

reset_mocks();
$json = run_ajax([Testable_TrainingAjax::class, 'handle_get_status']);
ac_assert('status success envelope', ($json['success'] ?? false) === true);
ac_assert('status payload not double-wrapped', isset($json['data']['access_state']) && !isset($json['data']['success']));
ac_assert('status calls enrollment get_status', Mock_Training_Enrollment_Use_Case::$calls === ['get_status']);

reset_mocks();
$json = run_ajax([Testable_TrainingAjax::class, 'handle_enroll']);
ac_assert('enroll calls enroll use case', Mock_Training_Enrollment_Use_Case::$calls === ['enroll']);
ac_assert('enroll success', ($json['success'] ?? false) === true);

reset_mocks();
run_ajax([Testable_TrainingAjax::class, 'handle_unsubscribe']);
ac_assert('unsubscribe calls unsubscribe', Mock_Training_Enrollment_Use_Case::$calls === ['unsubscribe']);

reset_mocks();
run_ajax([Testable_TrainingAjax::class, 'handle_get_consent_status']);
ac_assert('consent status calls consent get_status', Mock_Training_Consent_Use_Case::$calls === ['get_status']);

reset_mocks();
run_ajax([Testable_TrainingAjax::class, 'handle_accept_consent']);
ac_assert('accept consent calls accept', Mock_Training_Consent_Use_Case::$calls === ['accept']);

reset_mocks();
run_ajax([Testable_TrainingAjax::class, 'handle_revoke_consent']);
ac_assert('revoke consent calls revoke', Mock_Training_Consent_Use_Case::$calls === ['revoke']);

reset_mocks();
run_ajax([Testable_TrainingAjax::class, 'handle_get_course']);
ac_assert('course calls get_course', Mock_Training_Content_Use_Case::$calls === ['get_course']);

reset_mocks();
$_POST['lessonKey'] = 'bienvenida';
$_POST['courseKey'] = 'should-be-ignored';
$_POST['enrollmentId'] = 'spoof';
$json = run_ajax([Testable_TrainingAjax::class, 'handle_get_lesson']);
ac_assert('lesson calls get_lesson', Mock_Training_Content_Use_Case::$calls === ['get_lesson']);
ac_assert('lesson passes lessonKey only', Mock_Training_Content_Use_Case::$last_lesson_key === 'bienvenida');
ac_assert('lesson success', ($json['success'] ?? false) === true);

reset_mocks();
Mock_Training_Enrollment_Use_Case::$enroll_response = [
    'success' => false,
    'error'   => ['code' => 'training_not_eligible', 'message' => ''],
];
$json = run_ajax([Testable_TrainingAjax::class, 'handle_enroll']);
ac_assert('error training_* preserved in data.code', ($json['data']['code'] ?? '') === 'training_not_eligible');
ac_assert('error envelope has success false', ($json['success'] ?? true) === false);
ac_assert('error not double-wrapped with nested error.success', !isset($json['data']['error']['success']) && !isset($json['data']['success']));

reset_mocks();
Mock_Training_Content_Use_Case::$lesson_response = [
    'success' => false,
    'error'   => ['code' => 'training_content_lesson_key_invalid', 'message' => ''],
];
$_POST['lessonKey'] = '../etc/passwd';
$json = run_ajax([Testable_TrainingAjax::class, 'handle_get_lesson']);
ac_assert('invalid/traversal lesson key rejected via use case code', ($json['data']['code'] ?? '') === 'training_content_lesson_key_invalid');

reset_mocks();
$GLOBALS['aa_test_can_manage_options'] = false;
$json = run_ajax([Testable_TrainingAjax::class, 'handle_get_status']);
ac_assert('capability insufficient blocks', ($json['success'] ?? true) === false);
ac_assert('capability insufficient skips use case', Mock_Training_Enrollment_Use_Case::$calls === []);

reset_mocks();
$GLOBALS['aa_test_nonce_valid'] = false;
$json = run_ajax([Testable_TrainingAjax::class, 'handle_get_status']);
ac_assert('invalid nonce blocks', $GLOBALS['aa_test_die_message'] === 'bad nonce');
ac_assert('invalid nonce skips use case', Mock_Training_Enrollment_Use_Case::$calls === []);

echo "\nPassed {$passed}/{$total}\n";
if ($failed !== []) {
    echo "Failed:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}

exit(0);
