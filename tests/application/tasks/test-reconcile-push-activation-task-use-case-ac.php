<?php
/**
 * AC — ReconcilePushActivationTaskUseCase (ensure-only).
 *
 * Ejecutar: php tests/application/tasks/test-reconcile-push-activation-task-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$use_case_file = $plugin_root . '/includes/application/tasks/ReconcilePushActivationTaskUseCase.php';

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

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php';
require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
require_once $plugin_root . '/includes/repositories/TaskActionRepository.php';
require_once $use_case_file;

$use_case_src = file_get_contents($use_case_file);
ac_assert('Use case file readable', $use_case_src !== false);
ac_assert('Use case defines ReconcilePushActivationTaskUseCase', strpos($use_case_src, 'class ReconcilePushActivationTaskUseCase') !== false);
ac_assert('Use case has ensure-only execute()', strpos($use_case_src, 'public function execute(): array') !== false);
ac_assert('Use case does not accept readiness', strpos($use_case_src, 'readiness') === false);
ac_assert('Use case does not accept device_key', strpos($use_case_src, 'device_key') === false);
ac_assert('Use case does not complete task to done', strpos($use_case_src, "'done'") === false && strpos($use_case_src, 'mark_completed') === false);
ac_assert('Use case does not expose completed_task_ids', strpos($use_case_src, 'completed_task_ids') === false);
ac_assert('Global origin key is enable_push', ReconcilePushActivationTaskUseCase::TASK_ORIGIN_KEY === 'enable_push');

$activation_list = [
    'id' => 50,
    'title' => 'Activación de tu agenda',
    'status' => 'active',
    'source_category' => 'agenda_app',
    'origin_key' => 'learning.recommendations',
];

$created_tasks = [];
$pending_calls = [];
$action_calls = [];

$create_use_case = static function () use (
    $activation_list,
    &$created_tasks,
    &$pending_calls,
    &$action_calls
): ReconcilePushActivationTaskUseCase {
    $existing = null;

    return new ReconcilePushActivationTaskUseCase(
        static function () use ($activation_list): array {
            return $activation_list;
        },
        static function (string $source_category, string $origin_key) use (&$existing): ?array {
            if ($source_category !== 'agenda_app' || $origin_key !== 'enable_push') {
                return null;
            }

            return $existing;
        },
        static function (int $list_id) use (&$existing, &$created_tasks): ?array {
            $task = [
                'id' => 901,
                'list_id' => $list_id,
                'title' => 'Activa las notificaciones en este dispositivo',
                'status' => 'pending',
                'source_category' => 'agenda_app',
                'origin_key' => 'enable_push',
                'importance' => 110,
                'default_bucket' => 'primary',
            ];
            $existing = $task;
            $created_tasks[] = $task;

            return $task;
        },
        static function (int $task_id) use (&$existing, &$pending_calls): array {
            $pending_calls[] = $task_id;

            if (is_array($existing) && (int) ($existing['id'] ?? 0) === $task_id) {
                $existing['status'] = 'pending';
            }

            return TaskUseCaseSupport::ok(['task' => $existing]);
        },
        static function (int $task_id) use (&$action_calls): ?array {
            $action_calls[] = $task_id;

            return [
                'id' => 1,
                'task_id' => $task_id,
                'handler' => 'push.activate',
                'label' => 'Activar notificaciones',
            ];
        }
    );
};

$use_case = $create_use_case();
$first = $use_case->execute();
ac_assert('Ensure creates global task when missing', !empty($first['success']));
ac_assert('Ensure marks created=true on first run', ($first['data']['created'] ?? null) === true);
ac_assert('Ensure keeps task pending', strtolower((string) ($first['data']['task']['status'] ?? '')) === 'pending');
ac_assert('Ensure persists push.activate action', $action_calls === [901]);
ac_assert('Ensure does not return completed_task_ids', !array_key_exists('completed_task_ids', $first['data'] ?? []));

$second = $use_case->execute();
ac_assert('Ensure is idempotent on existing task', !empty($second['success']));
ac_assert('Ensure marks created=false on repeat', ($second['data']['created'] ?? null) === false);
ac_assert('Ensure does not create duplicate tasks', count($created_tasks) === 1);

$done_existing = [
    'id' => 902,
    'list_id' => 50,
    'title' => 'Activa las notificaciones en este dispositivo',
    'status' => 'done',
    'source_category' => 'agenda_app',
    'origin_key' => 'enable_push',
];

$done_use_case = new ReconcilePushActivationTaskUseCase(
    static function () use ($activation_list): array {
        return $activation_list;
    },
    static function () use (&$done_existing): ?array {
        return $done_existing;
    },
    static function (): ?array {
        return null;
    },
    static function (int $task_id) use (&$done_existing, &$pending_calls): array {
        $pending_calls[] = $task_id;
        $done_existing['status'] = 'pending';

        return TaskUseCaseSupport::ok(['task' => $done_existing]);
    },
    static function (int $task_id): ?array {
        return ['task_id' => $task_id, 'handler' => 'push.activate'];
    }
);

$done_result = $done_use_case->execute();
ac_assert('Ensure resets done task back to pending', !empty($done_result['success']));
ac_assert('Ensure leaves task pending after reset', strtolower((string) ($done_result['data']['task']['status'] ?? '')) === 'pending');
ac_assert('Ensure never marks task done', strtolower((string) ($done_result['data']['task']['status'] ?? '')) !== 'done');

$missing_list = (new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return null;
    }
))->execute();
ac_assert('Missing activation list fails closed', empty($missing_list['success']));
ac_assert('Missing activation list is retryable', !empty($missing_list['error']['retryable']));

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
