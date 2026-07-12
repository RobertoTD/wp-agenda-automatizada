<?php
/**
 * AC — SyncTaskExecutionAvailablePushJobUseCase + wiring Create/UpdateTaskUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-sync-task-execution-available-push-job-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
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

$GLOBALS['aa_test_options'] = [
    'aa_timezone' => 'America/Mexico_City',
    'aa_push_task_execution_available_enabled' => 0,
];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }
        return $default;
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        $GLOBALS['aa_test_error_logs'][] = (string) $message;
    }
}

$GLOBALS['aa_test_error_logs'] = [];

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/infrastructure/backend/class-aa-push-backend-client.php';
require_once $plugin_root . '/includes/application/tasks/SyncTaskExecutionAvailablePushJobUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/UpdateTaskUseCase.php';

// ─── 6. conversión aa_timezone → ISO con offset ───────────────────

$sync_payloads = [];

$sync_uc = new SyncTaskExecutionAvailablePushJobUseCase(
    static function (array $payload) use (&$sync_payloads): array {
        $sync_payloads[] = $payload;
        return ['ok' => true, 'sync' => 'scheduled'];
    },
    static function (): string {
        return 'America/Mexico_City';
    }
);

$sync_result = $sync_uc->execute([
    'task' => [
        'id' => 123,
        'execution_available_at' => '2026-07-09 15:00:00',
    ],
]);

ac_assert('sync with date success', !empty($sync_result['success']));
ac_assert('sync sends task_id', ($sync_payloads[0]['task_id'] ?? 0) === 123);
ac_assert(
    'sync sends ISO execution_available_at with offset',
    isset($sync_payloads[0]['execution_available_at'])
    && strpos((string) $sync_payloads[0]['execution_available_at'], 'T') !== false
    && preg_match('/[+-]\d{2}:\d{2}$/', (string) $sync_payloads[0]['execution_available_at']) === 1
);

// ─── 4. update limpiando envía null ───────────────────────────────

$clear_payloads = [];

$clear_uc = new SyncTaskExecutionAvailablePushJobUseCase(
    static function (array $payload) use (&$clear_payloads): array {
        $clear_payloads[] = $payload;
        return ['ok' => true, 'sync' => 'disabled'];
    }
);

$clear_result = $clear_uc->execute([
    'task' => [
        'id' => 456,
        'execution_available_at' => null,
    ],
]);

ac_assert('clear sync success', !empty($clear_result['success']));
ac_assert('clear sends task_id', ($clear_payloads[0]['task_id'] ?? 0) === 456);
ac_assert(
    'clear sends execution_available_at null',
    array_key_exists('execution_available_at', $clear_payloads[0])
    && $clear_payloads[0]['execution_available_at'] === null
);

// ─── 10. no depende del setting ───────────────────────────────────

$sync_src = file_get_contents($plugin_root . '/includes/application/tasks/SyncTaskExecutionAvailablePushJobUseCase.php');
ac_assert(
    'use case does not read aa_push_task_execution_available_enabled',
    strpos($sync_src, 'aa_push_task_execution_available_enabled') === false
);

$setting_disabled_result = $sync_uc->execute([
    'task' => [
        'id' => 777,
        'execution_available_at' => '2026-07-09 15:00:00',
    ],
]);
ac_assert('sync still runs when setting disabled in env', !empty($setting_disabled_result['success']));

// ─── 7. fallo backend no lanza en best-effort ─────────────────────

$fail_uc = new SyncTaskExecutionAvailablePushJobUseCase(
    static function (array $payload): array {
        return [
            'ok' => false,
            'code' => 'push_backend_unavailable',
            'error' => 'timeout',
            'http_status' => 503,
        ];
    },
    static function (): string {
        return 'America/Mexico_City';
    }
);

$best_effort_threw = false;

try {
    SyncTaskExecutionAvailablePushJobUseCase::sync_after_task_persisted_best_effort([
        'id' => 999,
        'execution_available_at' => '2026-07-09 15:00:00',
    ]);
} catch (Throwable $e) {
    $best_effort_threw = true;
}

ac_assert('best-effort static does not throw on backend failure', $best_effort_threw === false);
ac_assert('best-effort static logs failures', strpos($sync_src, 'error_log(') !== false);
ac_assert(
    'best-effort static log mentions task id',
    strpos($sync_src, '$task_id') !== false
);

$fail_result = $fail_uc->execute([
    'task' => [
        'id' => 321,
        'execution_available_at' => '2026-07-09 15:00:00',
    ],
]);
ac_assert('backend failure returns structured error', empty($fail_result['success']));
ac_assert('backend failure preserves code', ($fail_result['error']['code'] ?? '') === 'push_backend_unavailable');

// ─── 9. cliente HMAC existente ────────────────────────────────────

$client_src = file_get_contents($plugin_root . '/includes/infrastructure/backend/class-aa-push-backend-client.php');
ac_assert(
    'backend client exposes syncTaskExecutionAvailableJob',
    strpos($client_src, 'syncTaskExecutionAvailableJob') !== false
);
ac_assert(
    'backend client targets task execution available sync route',
    strpos($client_src, '/push/task-execution-available-jobs/sync') !== false
);
ac_assert(
    'use case delegates to AA_Push_Backend_Client by default',
    strpos($sync_src, 'syncTaskExecutionAvailableJob') !== false
);

// ─── Create/Update wiring (1–5, 8) ────────────────────────────────

$create_task_src = file_get_contents($plugin_root . '/includes/application/tasks/CreateTaskUseCase.php');
$update_task_src = file_get_contents($plugin_root . '/includes/application/tasks/UpdateTaskUseCase.php');

ac_assert(
    'CreateTaskUseCase calls post_create_sync after persistence',
    strpos($create_task_src, 'run_post_create_sync') !== false
);
ac_assert(
    'CreateTaskUseCase skips sync without execution_available_at',
    strpos($create_task_src, "if (\$execution_at === '')") !== false
);
ac_assert(
    'UpdateTaskUseCase sync only when execution_available_at key present',
    strpos($update_task_src, "array_key_exists('execution_available_at', \$input)") !== false
);
ac_assert(
    'CreateTaskUseCase uses SyncTaskExecutionAvailablePushJobUseCase',
    strpos($create_task_src, 'SyncTaskExecutionAvailablePushJobUseCase::sync_after_task_persisted_best_effort') !== false
);
ac_assert(
    'UpdateTaskUseCase uses SyncTaskExecutionAvailablePushJobUseCase',
    strpos($update_task_src, 'SyncTaskExecutionAvailablePushJobUseCase::sync_after_task_persisted_best_effort') !== false
);

$create_sync_calls = [];
$create_with_date = new CreateTaskUseCase(
    static function (array $task) use (&$create_sync_calls): void {
        $create_sync_calls[] = $task;
    }
);
$run_post_create = (new ReflectionClass(CreateTaskUseCase::class))->getMethod('run_post_create_sync');
$run_post_create->setAccessible(true);
$run_post_create->invoke($create_with_date, [
    'id' => 501,
    'execution_available_at' => '2026-06-20 10:00:00',
]);

ac_assert('1. create with date triggers sync after persist', count($create_sync_calls) === 1);
ac_assert('1. create sync receives persisted task id', (int) ($create_sync_calls[0]['id'] ?? 0) === 501);

$create_without_date_calls = [];
$create_without_date = new CreateTaskUseCase(
    static function (array $task) use (&$create_without_date_calls): void {
        $create_without_date_calls[] = $task;
    }
);
$run_post_create->invoke($create_without_date, ['id' => 502, 'execution_available_at' => null]);

ac_assert('2. create without date does not call backend', $create_without_date_calls === []);

$update_sync_calls = [];
$update_with_date = new UpdateTaskUseCase(
    static function (array $task) use (&$update_sync_calls): void {
        $update_sync_calls[] = $task;
    }
);
$run_post_update = (new ReflectionClass(UpdateTaskUseCase::class))->getMethod('run_post_update_sync');
$run_post_update->setAccessible(true);
$run_post_update->invoke($update_with_date, [
    'id' => 503,
    'execution_available_at' => '2026-06-25 12:00:00',
]);

ac_assert('3. update with date triggers sync', count($update_sync_calls) === 1);
ac_assert(
    '3. update sync receives new execution_available_at',
    ($update_sync_calls[0]['execution_available_at'] ?? '') === '2026-06-25 12:00:00'
);

$update_clear_calls = [];
$update_clear = new UpdateTaskUseCase(
    static function (array $task) use (&$update_clear_calls): void {
        $update_clear_calls[] = $task;
    }
);
$run_post_update->invoke($update_clear, [
    'id' => 504,
    'execution_available_at' => null,
]);

ac_assert('4. update clear triggers sync with null task value', count($update_clear_calls) === 1);
ac_assert(
    '4. update clear persisted row has null execution_available_at',
    array_key_exists('execution_available_at', $update_clear_calls[0])
    && $update_clear_calls[0]['execution_available_at'] === null
);

ac_assert(
    '5. update omitting field guarded in execute source',
    strpos($update_task_src, "if (array_key_exists('execution_available_at', \$input))") !== false
    && strpos($update_task_src, '$this->run_post_update_sync($row);') !== false
);

$persistence_fail_pos = strpos($create_task_src, "return TaskUseCaseSupport::fail('persistence_failed'");
$sync_pos = strpos($create_task_src, '$this->run_post_create_sync($row);');
ac_assert('8. post_create_sync only after successful row', $persistence_fail_pos !== false && $sync_pos !== false && $sync_pos > $persistence_fail_pos);

$update_persistence_fail_pos = strpos($update_task_src, "return TaskUseCaseSupport::fail('persistence_failed'");
$update_sync_pos = strpos($update_task_src, '$this->run_post_update_sync($row);');
ac_assert('8. post_update_sync only after successful row', $update_persistence_fail_pos !== false && $update_sync_pos !== false && $update_sync_pos > $update_persistence_fail_pos);

// ─── confirmación fuera de alcance ────────────────────────────────

$controller_src = @file_get_contents($plugin_root . '/includes/controllers/WebhooksController.php') ?: '';
$worker_refs = glob($plugin_root . '/includes/**/*task*execution*push*worker*') ?: [];
ac_assert('no worker file for task execution push', $worker_refs === []);
ac_assert(
    'validate endpoint wired in WebhooksController',
    strpos($controller_src, 'execution-available-push/validate') !== false
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
