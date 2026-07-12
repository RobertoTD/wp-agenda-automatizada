<?php
/**
 * AC — ValidateTaskExecutionAvailablePushUseCase + REST webhook shim.
 *
 * Ejecutar: php tests/application/tasks/test-validate-task-execution-available-push-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}

$plugin_root = dirname(__DIR__, 3);

$total = 0;
$passed = 0;
$failed = [];
$GLOBALS['aa_test_registered_routes'] = [];
$GLOBALS['aa_test_options'] = [
    'aa_timezone' => 'America/Mexico_City',
    'aa_webhook_token' => 'webhook-token',
    'aa_push_task_execution_available_enabled' => 1,
];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }
        return $default;
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        return $known_string === $user_string;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public string $code;
        public string $message;
        public array $data;

        public function __construct($code = '', $message = '', $data = []) {
            $this->code = (string) $code;
            $this->message = (string) $message;
            $this->data = is_array($data) ? $data : [];
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private int $status;

        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = (int) $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }
    }
}

if (!class_exists('WP_REST_Controller')) {
    class WP_REST_Controller {}
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        public const CREATABLE = 'POST';
        public const READABLE = 'GET';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args) {
        $GLOBALS['aa_test_registered_routes'][] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
        ];
    }
}

final class AA_Test_Rest_Request {
    private array $params;
    private array $headers;

    public function __construct(array $params = [], array $headers = []) {
        $this->params = $params;
        $this->headers = $headers;
    }

    public function get_param($key) {
        return $this->params[$key] ?? null;
    }

    public function get_header($key) {
        return $this->headers[$key] ?? '';
    }
}

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

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/application/tasks/ValidateTaskExecutionAvailablePushUseCase.php';
require_once $plugin_root . '/includes/controllers/WebhooksController.php';

$expected_utc = '2026-07-12T18:00:00.000Z';
$execution_local = '2026-07-12 12:00:00';
$now_local = '2026-07-12 14:00:00';

$lists = [
    8 => [
        'id' => 8,
        'title' => 'Administración',
        'status' => 'active',
    ],
    9 => [
        'id' => 9,
        'title' => 'Operaciones',
        'status' => 'active',
    ],
];

$tasks = [
    101 => [
        'id' => 101,
        'list_id' => 8,
        'title' => 'Llamar al contador',
        'status' => 'pending',
        'execution_available_at' => $execution_local,
        'due_at' => '2026-07-13 18:00:00',
        'archived_at' => null,
        'completed_at' => null,
    ],
    102 => [
        'id' => 102,
        'list_id' => 8,
        'title' => 'Tarea stale',
        'status' => 'pending',
        'execution_available_at' => '2026-07-13 12:00:00',
        'due_at' => null,
        'archived_at' => null,
        'completed_at' => null,
    ],
    103 => [
        'id' => 103,
        'list_id' => 8,
        'title' => 'Tarea cleared',
        'status' => 'pending',
        'execution_available_at' => null,
        'due_at' => null,
        'archived_at' => null,
        'completed_at' => null,
    ],
    104 => [
        'id' => 104,
        'list_id' => 8,
        'title' => 'Tarea futura',
        'status' => 'pending',
        'execution_available_at' => '2026-07-12 16:00:00',
        'due_at' => null,
        'archived_at' => null,
        'completed_at' => null,
    ],
    105 => [
        'id' => 105,
        'list_id' => 8,
        'title' => 'Tarea vencida',
        'status' => 'pending',
        'execution_available_at' => $execution_local,
        'due_at' => $now_local,
        'archived_at' => null,
        'completed_at' => null,
    ],
    106 => [
        'id' => 106,
        'list_id' => 8,
        'title' => 'Tarea done',
        'status' => 'done',
        'execution_available_at' => $execution_local,
        'due_at' => null,
        'archived_at' => null,
        'completed_at' => '2026-07-12 13:00:00',
    ],
    107 => [
        'id' => 107,
        'list_id' => 8,
        'title' => 'Tarea archivada',
        'status' => 'pending',
        'execution_available_at' => $execution_local,
        'due_at' => null,
        'archived_at' => '2026-07-12 09:00:00',
        'completed_at' => null,
    ],
    108 => [
        'id' => 108,
        'list_id' => 8,
        'title' => 'Tarea missed',
        'status' => 'missed',
        'execution_available_at' => $execution_local,
        'due_at' => null,
        'archived_at' => null,
        'completed_at' => null,
    ],
    109 => [
        'id' => 109,
        'list_id' => 8,
        'title' => 'Tarea con due futuro',
        'status' => 'pending',
        'execution_available_at' => $execution_local,
        'due_at' => '2026-07-13 10:00:00',
        'archived_at' => null,
        'completed_at' => null,
    ],
    110 => [
        'id' => 110,
        'list_id' => 8,
        'title' => 'Tarea setting off',
        'status' => 'pending',
        'execution_available_at' => $execution_local,
        'due_at' => null,
        'archived_at' => null,
        'completed_at' => null,
    ],
    111 => [
        'id' => 111,
        'list_id' => 999,
        'title' => 'Tarea sin lista',
        'status' => 'pending',
        'execution_available_at' => $execution_local,
        'due_at' => null,
        'archived_at' => null,
        'completed_at' => null,
    ],
    112 => [
        'id' => 112,
        'list_id' => 8,
        'title' => 'Tarea sin vencimiento',
        'status' => 'pending',
        'execution_available_at' => $execution_local,
        'due_at' => null,
        'archived_at' => null,
        'completed_at' => null,
    ],
];

$make_use_case = static function () use (&$tasks, &$lists): ValidateTaskExecutionAvailablePushUseCase {
    return new ValidateTaskExecutionAvailablePushUseCase(
        static function (int $task_id) use (&$tasks): ?array {
            return $tasks[$task_id] ?? null;
        },
        static function (int $list_id) use (&$lists): ?array {
            return $lists[$list_id] ?? null;
        },
        static function (): string {
            return 'America/Mexico_City';
        },
        static function (): DateTimeImmutable {
            return new DateTimeImmutable('2026-07-12 14:00:00', new DateTimeZone('America/Mexico_City'));
        },
        static function (): bool {
            return (int) ($GLOBALS['aa_test_options']['aa_push_task_execution_available_enabled'] ?? 1) === 1;
        }
    );
};

$use_case = $make_use_case();

$result = $use_case->execute([
    ['task_id' => 101, 'expected_execution_available_at' => $expected_utc],
    ['task_id' => 102, 'expected_execution_available_at' => $expected_utc],
    ['task_id' => 103, 'expected_execution_available_at' => $expected_utc],
    ['task_id' => 104, 'expected_execution_available_at' => '2026-07-12T22:00:00.000Z'],
    ['task_id' => 105, 'expected_execution_available_at' => $expected_utc],
    ['task_id' => 106, 'expected_execution_available_at' => $expected_utc],
    ['task_id' => 107, 'expected_execution_available_at' => $expected_utc],
    ['task_id' => 108, 'expected_execution_available_at' => $expected_utc],
    ['task_id' => 109, 'expected_execution_available_at' => $expected_utc],
    ['task_id' => 999, 'expected_execution_available_at' => $expected_utc],
]);

$eligible_101 = $result['results']['101'] ?? [];

ac_assert('use case returns success', ($result['success'] ?? false) === true);
ac_assert('1. eligible status', ($eligible_101['status'] ?? '') === 'eligible');
ac_assert('1. eligible returns current title', ($eligible_101['task']['title'] ?? '') === 'Llamar al contador');
ac_assert('2. eligible returns list_id', (int) ($eligible_101['task']['list_id'] ?? 0) === 8);
ac_assert('2. eligible returns current list_title', ($eligible_101['task']['list_title'] ?? '') === 'Administración');
ac_assert(
    '3. eligible returns due_at ISO with offset',
    isset($eligible_101['task']['due_at'])
    && preg_match('/2026-07-13T18:00:00[+-]\d{2}:\d{2}$/', (string) $eligible_101['task']['due_at']) === 1
);

$no_due = $use_case->execute([
    ['task_id' => 112, 'expected_execution_available_at' => $expected_utc],
]);
ac_assert(
    '4. eligible without due_at returns null',
    array_key_exists('due_at', $no_due['results']['112']['task'] ?? [])
    && $no_due['results']['112']['task']['due_at'] === null
);

$tasks[101]['title'] = 'Llamar al contador actualizado';
$tasks[101]['list_id'] = 9;
$updated = $use_case->execute([
    ['task_id' => 101, 'expected_execution_available_at' => $expected_utc],
]);
ac_assert(
    '5. title edit is reflected in response',
    ($updated['results']['101']['task']['title'] ?? '') === 'Llamar al contador actualizado'
);
ac_assert(
    '5. list change is reflected in response',
    (int) ($updated['results']['101']['task']['list_id'] ?? 0) === 9
    && ($updated['results']['101']['task']['list_title'] ?? '') === 'Operaciones'
);

$tasks[101]['title'] = 'Llamar al contador';
$tasks[101]['list_id'] = 8;

ac_assert('6. stale has no task payload', !array_key_exists('task', $result['results']['102'] ?? []));
ac_assert('6. stale status only', ($result['results']['102']['status'] ?? '') === 'stale');
ac_assert('7. ineligible has no task payload', !array_key_exists('task', $result['results']['106'] ?? []));
ac_assert('7. ineligible status only', ($result['results']['106']['status'] ?? '') === 'ineligible');
ac_assert('8. batch keeps independent stale result', ($result['results']['103']['status'] ?? '') === 'stale');
ac_assert('8. batch keeps independent ineligible result', ($result['results']['999']['status'] ?? '') === 'ineligible');
ac_assert('4. future execution moment is ineligible', ($result['results']['104']['status'] ?? '') === 'ineligible');
ac_assert('5b. due_at equal to now is ineligible', ($result['results']['105']['status'] ?? '') === 'ineligible');
ac_assert('7b. archived task is ineligible', ($result['results']['107']['status'] ?? '') === 'ineligible');
ac_assert('7c. missed task is ineligible', ($result['results']['108']['status'] ?? '') === 'ineligible');
ac_assert('due_at after now keeps task eligible', ($result['results']['109']['status'] ?? '') === 'eligible');

$missing_list = $use_case->execute([
    ['task_id' => 111, 'expected_execution_available_at' => $expected_utc],
]);
ac_assert(
    'missing list keeps eligible with list_id and null list_title',
    ($missing_list['results']['111']['status'] ?? '') === 'eligible'
    && (int) ($missing_list['results']['111']['task']['list_id'] ?? 0) === 999
    && array_key_exists('list_title', $missing_list['results']['111']['task'])
    && $missing_list['results']['111']['task']['list_title'] === null
);

$GLOBALS['aa_test_options']['aa_push_task_execution_available_enabled'] = 0;
$disabled_result = $use_case->execute([
    ['task_id' => 110, 'expected_execution_available_at' => $expected_utc],
]);
ac_assert('9. disabled setting is ineligible', ($disabled_result['results']['110']['status'] ?? '') === 'ineligible');
ac_assert('9. disabled ineligible has no task payload', !array_key_exists('task', $disabled_result['results']['110'] ?? []));
$GLOBALS['aa_test_options']['aa_push_task_execution_available_enabled'] = 1;

$use_case_src = file_get_contents($plugin_root . '/includes/application/tasks/ValidateTaskExecutionAvailablePushUseCase.php');
ac_assert(
    '10. use case does not consult TaskStateRepository',
    strpos($use_case_src, 'TaskStateRepository') === false
);
ac_assert(
    '10. use case does not consult visible_in_active',
    strpos($use_case_src, 'visible_in_active') === false
);
ac_assert(
    '9. use case does not expose notes importance bucket layer score executive',
    strpos($use_case_src, "'notes'") === false
    && strpos($use_case_src, 'importance') === false
    && strpos($use_case_src, 'default_bucket') === false
    && strpos($use_case_src, 'temporal_layer') === false
    && strpos($use_case_src, 'priority_score') === false
    && strpos($use_case_src, 'Executive') === false
);

$eligible_json = json_encode($eligible_101);
ac_assert('9. eligible payload excludes notes and importance', strpos($eligible_json, 'notes') === false && strpos($eligible_json, 'importance') === false);

$dismiss_eligible = $use_case->execute([
    ['task_id' => 101, 'expected_execution_available_at' => $expected_utc],
]);
ac_assert(
    '10. dismiss/ignored signals do not block eligible task',
    ($dismiss_eligible['results']['101']['status'] ?? '') === 'eligible'
);

$utc_result = $use_case->execute([
    ['task_id' => 101, 'expected_execution_available_at' => '2026-07-12T18:00:00.000Z'],
]);
ac_assert('11. UTC expected matches local aa_timezone storage', ($utc_result['results']['101']['status'] ?? '') === 'eligible');

$controller = new Webhooks_Controller();
$controller->register_routes();

$registered = array_filter(
    $GLOBALS['aa_test_registered_routes'],
    static function ($route) {
        return $route['route'] === '/webhooks/tasks/execution-available-push/validate';
    }
);

ac_assert('REST route registered', count($registered) === 1);
ac_assert(
    '12. permission accepts correct webhook token',
    $controller->permission_webhook_token(new AA_Test_Rest_Request([], ['X-AA-Webhook-Token' => 'webhook-token'])) === true
);
ac_assert(
    '12. permission rejects missing token',
    $controller->permission_webhook_token(new AA_Test_Rest_Request()) instanceof WP_Error
);

final class AA_Test_Wpdb {
    public string $prefix = 'wp_';
    public string $last_error = '';

    public function prepare($query, ...$args) {
        foreach ($args as $arg) {
            $query = preg_replace('/%d/', (string) (int) $arg, $query, 1);
        }
        return $query;
    }

    public function get_row($query) {
        $query = (string) $query;

        if (strpos($query, 'aa_tasks') !== false && strpos($query, 'WHERE id = 101') !== false) {
            return (object) [
                'id' => 101,
                'list_id' => 8,
                'title' => 'Tarea REST',
                'notes' => null,
                'status' => 'pending',
                'source' => 'user',
                'source_category' => 'user',
                'origin_key' => null,
                'managed_by' => 'user',
                'default_bucket' => 'primary',
                'completion_type' => 'manual',
                'completion_fact_key' => null,
                'importance' => 0,
                'due_at' => null,
                'execution_available_at' => '2026-07-12 12:00:00',
                'position' => 0,
                'completed_at' => null,
                'archived_at' => null,
                'created_at' => '2026-07-12 08:00:00',
                'updated_at' => '2026-07-12 08:00:00',
            ];
        }

        if (strpos($query, 'aa_task_lists') !== false && strpos($query, 'WHERE id = 8') !== false) {
            return (object) [
                'id' => 8,
                'title' => 'Administración',
                'description' => null,
                'owner_type' => 'user',
                'source_category' => 'user',
                'origin_key' => null,
                'managed_by' => 'user',
                'importance' => 0,
                'status' => 'active',
                'position' => 0,
                'created_at' => '2026-07-12 08:00:00',
                'updated_at' => '2026-07-12 08:00:00',
            ];
        }

        return null;
    }
}

global $wpdb;
$wpdb = new AA_Test_Wpdb();

if (!function_exists('current_time')) {
    function current_time($type) {
        return '2026-07-12 14:00:00';
    }
}

$response = $controller->handle_task_execution_available_push_validate(
    new AA_Test_Rest_Request([
        'tasks' => [
            ['task_id' => 101, 'expected_execution_available_at' => $expected_utc],
        ],
    ])
);

ac_assert('REST handler returns WP_REST_Response', $response instanceof WP_REST_Response);
ac_assert('REST handler status 200', $response->get_status() === 200);
$response_data = $response->get_data();
ac_assert(
    'REST handler delegates structured eligible payload',
    ($response_data['results']['101']['status'] ?? '') === 'eligible'
    && ($response_data['results']['101']['task']['title'] ?? '') === 'Tarea REST'
);

$controller_src = file_get_contents($plugin_root . '/includes/controllers/WebhooksController.php');
ac_assert(
    'controller is thin shim over use case',
    strpos($controller_src, 'ValidateTaskExecutionAvailablePushUseCase') !== false
    && strpos($controller_src, 'handle_task_execution_available_push_validate') !== false
);
ac_assert(
    'REST controller does not mention executive or layers',
    strpos($controller_src, 'temporal_layer') === false && strpos($controller_src, 'Executive') === false
);

echo "\n";
echo "Passed: {$passed}/{$total}\n";

if ($failed !== []) {
    echo "Failed:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

echo "All tests passed.\n";
exit(0);
