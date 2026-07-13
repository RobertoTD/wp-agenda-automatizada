<?php
/**
 * AC MC1 — ReconcilePushActivationTaskUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-reconcile-push-activation-task-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        return 1;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-07-12 12:00:00' : '2026-07-12 12:00:00';
    }
}

if (!function_exists('aa_get_current_datetime')) {
    function aa_get_current_datetime() {
        return '2026-07-12 12:00:00';
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

$use_case_file = $plugin_root . '/includes/application/tasks/ReconcilePushActivationTaskUseCase.php';
$use_case_src = file_get_contents($use_case_file);

ac_assert('Use case file readable', $use_case_src !== false);
ac_assert('Use case defines ReconcilePushActivationTaskUseCase', strpos($use_case_src, 'class ReconcilePushActivationTaskUseCase') !== false);
ac_assert('Use case uses learning.recommendations list', strpos($use_case_src, 'learning.recommendations') !== false);
ac_assert('Use case uses ChangeTaskStatusUseCase for completion', strpos($use_case_src, 'ChangeTaskStatusUseCase') !== false);
ac_assert('Use case uses PushActivationTaskRepository lock', strpos($use_case_src, 'PushActivationTaskRepository') !== false);
ac_assert('Use case rejects invalid_device_key', strpos($use_case_src, 'invalid_device_key') !== false);
ac_assert('Use case rejects invalid_readiness', strpos($use_case_src, 'invalid_readiness') !== false);
ac_assert('Use case handles activation_list_not_ready', strpos($use_case_src, 'activation_list_not_ready') !== false);
ac_assert('Use case handles push_task_lock_unavailable', strpos($use_case_src, 'push_task_lock_unavailable') !== false);
ac_assert('Use case sets completion_type system', strpos($use_case_src, "'completion_type' => 'system'") !== false);
ac_assert('Use case sets completion_fact_key null', strpos($use_case_src, "'completion_fact_key' => null") !== false);
ac_assert('Use case sets importance 110', strpos($use_case_src, "'importance' => 110") !== false);
ac_assert('Use case sets default_bucket primary', strpos($use_case_src, "'default_bucket' => 'primary'") !== false);
ac_assert('Use case sets push.activate handler', strpos($use_case_src, "'push.activate'") !== false);
ac_assert('Use case approved title present', strpos($use_case_src, 'Activa las notificaciones en este dispositivo') !== false);
ac_assert('Use case approved description present', strpos($use_case_src, 'Permite que DEOIA te avise en este dispositivo') !== false);
ac_assert('Use case action label Activar notificaciones', strpos($use_case_src, 'Activar notificaciones') !== false);
ac_assert('Use case finally releases lock', strpos($use_case_src, 'finally') !== false && strpos($use_case_src, 'release_lock') !== false);

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/repositories/TaskRepository.php';
require_once $plugin_root . '/includes/repositories/TaskListRepository.php';
require_once $plugin_root . '/includes/repositories/PushActivationTaskRepository.php';
require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
require_once $plugin_root . '/includes/repositories/TaskActionRepository.php';
require_once $plugin_root . '/includes/application/tasks/ChangeTaskStatusUseCase.php';
require_once $use_case_file;

$device_key = 'a1b2c3d4e5f6789012345678abcdef01';
$active_list = [
    'id' => 50,
    'source_category' => 'agenda_app',
    'origin_key' => 'learning.recommendations',
    'status' => 'active',
    'title' => 'Activación de tu agenda',
];

$stored_tasks = [];
$stored_actions = [];
$completed_ids = [];
$lock_calls = 0;

$make_task = static function (
    int $id,
    string $origin_key,
    string $status,
    ?string $archived_at = null
) use ($active_list): array {
    return [
        'id' => $id,
        'list_id' => (int) $active_list['id'],
        'title' => 'Activa las notificaciones en este dispositivo',
        'notes' => 'Permite que DEOIA te avise en este dispositivo cuando una cita confirmada esté próxima, cuando una tarea llegue a su momento de realización y ante otros avisos importantes.',
        'status' => $status,
        'source' => 'system',
        'source_category' => 'agenda_app',
        'origin_key' => $origin_key,
        'managed_by' => 'developer',
        'importance' => 110,
        'default_bucket' => 'primary',
        'completion_type' => 'system',
        'completion_fact_key' => null,
        'position' => 0,
        'archived_at' => $archived_at,
        'completed_at' => $status === 'done' ? '2026-07-12 12:00:00' : null,
    ];
};

$occurrences_lister = static function (string $source_category, string $requested_device_key) use (&$stored_tasks, $device_key): array {
    $prefix = PushActivationTaskRepository::build_device_prefix($requested_device_key);

    return array_values(array_filter($stored_tasks, static function (array $task) use ($source_category, $prefix): bool {
        if (($task['source_category'] ?? '') !== $source_category) {
            return false;
        }

        $origin = (string) ($task['origin_key'] ?? '');

        return $prefix !== null && strpos($origin, $prefix) === 0
            && strtolower((string) ($task['status'] ?? '')) !== 'done';
    }));
};

$lock_runner = static function (string $lock_name, callable $callback) use (&$lock_calls): array {
    $lock_calls++;

    $result = $callback();

    return is_array($result) ? $result : [];
};

$status_changer = static function (int $task_id) use (&$stored_tasks, &$completed_ids): array {
    foreach ($stored_tasks as $index => $task) {
        if ((int) ($task['id'] ?? 0) !== $task_id) {
            continue;
        }

        $stored_tasks[$index]['status'] = 'done';
        $stored_tasks[$index]['completed_at'] = '2026-07-12 12:00:00';
        $completed_ids[] = $task_id;

        return TaskUseCaseSupport::ok(['task' => $stored_tasks[$index]]);
    }

    return TaskUseCaseSupport::fail('task_not_found', 'Tarea no encontrada.');
};

$task_creator = static function (int $list_id, string $origin_key) use (&$stored_tasks, $make_task): ?array {
    foreach ($stored_tasks as $task) {
        if (($task['origin_key'] ?? '') === $origin_key) {
            return $task;
        }
    }

    $task = $make_task(count($stored_tasks) + 1, $origin_key, 'pending');
    $task['list_id'] = $list_id;
    $stored_tasks[] = $task;

    return $task;
};

$action_ensurer = static function (int $task_id) use (&$stored_actions): ?array {
    $stored_actions[$task_id] = [
        'task_id' => $task_id,
        'action_key' => 'push.activate',
        'handler' => 'push.activate',
        'label' => 'Activar notificaciones',
    ];

    return $stored_actions[$task_id];
};

$base_use_case = static function () use (
    $active_list,
    $occurrences_lister,
    $lock_runner,
    $task_creator,
    $status_changer,
    $action_ensurer
): ReconcilePushActivationTaskUseCase {
    return new ReconcilePushActivationTaskUseCase(
        static function () use ($active_list): ?array {
            return $active_list;
        },
        $lock_runner,
        $occurrences_lister,
        $task_creator,
        $status_changer,
        $action_ensurer
    );
};

$invalid_key = (new ReconcilePushActivationTaskUseCase())->execute([
    'device_key' => 'NOT-HEX',
    'readiness' => 'unprepared',
]);
ac_assert('Rejects invalid device key', empty($invalid_key['success']) && ($invalid_key['error']['code'] ?? '') === 'invalid_device_key');

$invalid_readiness = (new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return ['id' => 50, 'status' => 'active'];
    }
))->execute([
    'device_key' => $device_key,
    'readiness' => 'maybe',
]);
ac_assert('Rejects invalid readiness', empty($invalid_readiness['success']) && ($invalid_readiness['error']['code'] ?? '') === 'invalid_readiness');

$list_missing = (new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return null;
    }
))->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);
ac_assert(
    'List missing is retryable',
    empty($list_missing['success'])
    && ($list_missing['error']['code'] ?? '') === 'activation_list_not_ready'
    && !empty($list_missing['error']['retryable'])
);

$list_archived = (new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return ['id' => 50, 'status' => 'archived'];
    }
))->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);
ac_assert('Archived list rejected', empty($list_archived['success']) && ($list_archived['error']['code'] ?? '') === 'activation_list_not_ready');

$lock_unavailable = (new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return ['id' => 50, 'status' => 'active'];
    },
    static function (): array {
        return ['lock_unavailable' => true];
    }
))->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);
ac_assert(
    'Lock unavailable is retryable',
    empty($lock_unavailable['success'])
    && ($lock_unavailable['error']['code'] ?? '') === 'push_task_lock_unavailable'
    && !empty($lock_unavailable['error']['retryable'])
);

// ─── unprepared creates ──────────────────────────────────────

$stored_tasks = [];
$stored_actions = [];
$completed_ids = [];
$lock_calls = 0;

$created = $base_use_case()->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);
ac_assert('Unprepared creates task', !empty($created['success']) && !empty($created['data']['created']));
ac_assert('Created task has approved title', ($created['data']['task']['title'] ?? '') === 'Activa las notificaciones en este dispositivo');
ac_assert('Created task importance 110', (int) ($created['data']['task']['importance'] ?? 0) === 110);
ac_assert('Created task default_bucket primary', ($created['data']['task']['default_bucket'] ?? '') === 'primary');
ac_assert('Created task completion_type system', ($created['data']['task']['completion_type'] ?? '') === 'system');
$created_task = $created['data']['task'] ?? [];
ac_assert(
    'Created task completion_fact_key null',
    array_key_exists('completion_fact_key', $created_task)
    && $created_task['completion_fact_key'] === null
);

$origin_created = (string) ($created['data']['task']['origin_key'] ?? '');
ac_assert(
    'Created origin key matches enable_push pattern',
    (bool) preg_match('/^enable_push:' . preg_quote($device_key, '/') . ':[a-f0-9]{16}$/', $origin_created)
);

$first_id = (int) ($created['data']['task']['id'] ?? 0);

// ─── unprepared idempotent ───────────────────────────────────

$again = $base_use_case()->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);
ac_assert('Second unprepared does not create', !empty($again['success']) && empty($again['data']['created']));
ac_assert('Second unprepared returns same task id', (int) ($again['data']['task']['id'] ?? 0) === $first_id);
ac_assert('Lock used on both unprepared calls', $lock_calls >= 2);

// ─── non-done statuses block new occurrence ──────────────────

foreach (['pending', 'missed'] as $blocking_status) {
    $stored_tasks = [];
    $completed_ids = [];
    $lock_calls = 0;

    $origin = PushActivationTaskRepository::build_origin_key($device_key, 'aaaaaaaaaaaaaaaa');
    $stored_tasks[] = $make_task(900, (string) $origin, $blocking_status);

    $blocked = $base_use_case()->execute([
        'device_key' => $device_key,
        'readiness' => 'unprepared',
    ]);

    ac_assert(
        'Status ' . $blocking_status . ' blocks new occurrence',
        !empty($blocked['success'])
        && empty($blocked['data']['created'])
        && (int) ($blocked['data']['task']['id'] ?? 0) === 900
    );
}

$stored_tasks = [];
$completed_ids = [];
$lock_calls = 0;
$origin = PushActivationTaskRepository::build_origin_key($device_key, 'bbbbbbbbbbbbbbbb');
$stored_tasks[] = $make_task(901, (string) $origin, 'pending', '2026-07-12 10:00:00');

$archived_pending = $base_use_case()->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);
ac_assert(
    'Archived pending blocks new occurrence',
    !empty($archived_pending['success']) && empty($archived_pending['data']['created'])
);

// ─── prepared completes ─────────────────────────────────────

$stored_tasks = [];
$completed_ids = [];
$lock_calls = 0;

$origin_pending = PushActivationTaskRepository::build_origin_key($device_key, 'cccccccccccccccc');
$origin_missed = PushActivationTaskRepository::build_origin_key($device_key, 'dddddddddddddddd');
$stored_tasks[] = $make_task(1001, (string) $origin_pending, 'pending');
$stored_tasks[] = $make_task(1002, (string) $origin_missed, 'missed');

$prepared = $base_use_case()->execute([
    'device_key' => $device_key,
    'readiness' => 'prepared',
]);
ac_assert('Prepared succeeds', !empty($prepared['success']));
ac_assert('Prepared completes both non-done tasks', count($prepared['data']['completed_task_ids'] ?? []) === 2);
ac_assert('Prepared uses lock', $lock_calls >= 1);

$stored_statuses = array_map(static function (array $task): string {
    return (string) ($task['status'] ?? '');
}, $stored_tasks);
ac_assert('All stored tasks marked done', $stored_statuses === ['done', 'done']);

$idempotent_prepared = $base_use_case()->execute([
    'device_key' => $device_key,
    'readiness' => 'prepared',
]);
ac_assert(
    'Prepared is idempotent when no pending occurrences remain',
    !empty($idempotent_prepared['success']) && ($idempotent_prepared['data']['completed_task_ids'] ?? []) === []
);

// ─── new occurrence after done ─────────────────────────────────

$new_after_done = $base_use_case()->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);
ac_assert('Creates new occurrence after all done', !empty($new_after_done['success']) && !empty($new_after_done['data']['created']));
ac_assert(
    'New occurrence has different id',
    (int) ($new_after_done['data']['task']['id'] ?? 0) !== 1001
    && (int) ($new_after_done['data']['task']['id'] ?? 0) !== 1002
);

$prepared_after_new = $base_use_case()->execute([
    'device_key' => $device_key,
    'readiness' => 'prepared',
]);
ac_assert(
    'Prepared completes newly created occurrence',
    !empty($prepared_after_new['success']) && count($prepared_after_new['data']['completed_task_ids'] ?? []) === 1
);

// ─── partial completion failure ──────────────────────────────

$stored_tasks = [];
$completed_ids = [];
$lock_calls = 0;
$stored_tasks[] = $make_task(2001, (string) PushActivationTaskRepository::build_origin_key($device_key, 'eeeeeeeeeeeeeeee'), 'pending');
$stored_tasks[] = $make_task(2002, (string) PushActivationTaskRepository::build_origin_key($device_key, 'ffffffffffffffff'), 'pending');

$fail_on_second = new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return ['id' => 50, 'status' => 'active'];
    },
    $lock_runner,
    $occurrences_lister,
    $task_creator,
    static function (int $task_id) use (&$completed_ids): array {
        if ($task_id === 2002) {
            return TaskUseCaseSupport::fail('task_completion_failed', 'Fallo simulado.');
        }

        $completed_ids[] = $task_id;

        return TaskUseCaseSupport::ok(['task' => ['id' => $task_id, 'status' => 'done']]);
    },
    $action_ensurer
);

$partial = $fail_on_second->execute([
    'device_key' => $device_key,
    'readiness' => 'prepared',
]);
ac_assert(
    'Partial completion returns task_completion_failed',
    empty($partial['success']) && ($partial['error']['code'] ?? '') === 'task_completion_failed'
);
ac_assert('Partial completion completed first task before failure', $completed_ids === [2001]);

// ─── recovered branch: action repair after create failure ─────

$stored_tasks = [];
$stored_actions = [];
$completed_ids = [];
$lock_calls = 0;
$action_attempts = 0;

$action_fail_then_ok = static function (int $task_id) use (&$stored_actions, &$action_attempts): ?array {
    $action_attempts++;

    if ($action_attempts === 1) {
        return null;
    }

    $stored_actions[$task_id] = [
        'task_id' => $task_id,
        'action_key' => 'push.activate',
        'handler' => 'push.activate',
        'label' => 'Activar notificaciones',
    ];

    return $stored_actions[$task_id];
};

$recovered_ok = (new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return ['id' => 50, 'status' => 'active'];
    },
    $lock_runner,
    $occurrences_lister,
    $task_creator,
    $status_changer,
    $action_fail_then_ok
))->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);

ac_assert(
    'Recovered branch repairs action within same reconciliation',
    !empty($recovered_ok['success'])
    && empty($recovered_ok['data']['created'])
    && (int) ($recovered_ok['data']['task']['id'] ?? 0) > 0
);
ac_assert('Recovered branch attempted action twice', $action_attempts === 2);
ac_assert(
    'Recovered branch leaves exactly one non-done occurrence',
    count($stored_tasks) === 1
    && strtolower((string) ($stored_tasks[0]['status'] ?? '')) === 'pending'
);
$recovered_task_id = (int) ($recovered_ok['data']['task']['id'] ?? 0);
ac_assert(
    'Recovered branch persists push.activate action',
    isset($stored_actions[$recovered_task_id])
    && ($stored_actions[$recovered_task_id]['handler'] ?? '') === 'push.activate'
);

$stored_tasks = [];
$stored_actions = [];
$lock_calls = 0;
$action_attempts = 0;

$action_always_fail = static function (int $task_id) use (&$action_attempts): ?array {
    $action_attempts++;

    return null;
};

$recovered_fail = (new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return ['id' => 50, 'status' => 'active'];
    },
    $lock_runner,
    $occurrences_lister,
    $task_creator,
    $status_changer,
    $action_always_fail
))->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);

ac_assert(
    'Recovered branch returns action_persistence_failed when action still missing',
    empty($recovered_fail['success'])
    && ($recovered_fail['error']['code'] ?? '') === 'action_persistence_failed'
);
ac_assert('Recovered failure never returns success data', empty($recovered_fail['data']));
ac_assert(
    'Recovered failure leaves exactly one orphan pending occurrence',
    count($stored_tasks) === 1
    && strtolower((string) ($stored_tasks[0]['status'] ?? '')) === 'pending'
);
ac_assert('Recovered failure attempted action at least twice', $action_attempts >= 2);

$orphan_task_id = (int) ($stored_tasks[0]['id'] ?? 0);
$stored_actions = [];
$action_attempts = 0;
$lock_calls = 0;

$later_repair = (new ReconcilePushActivationTaskUseCase(
    static function (): ?array {
        return ['id' => 50, 'status' => 'active'];
    },
    $lock_runner,
    $occurrences_lister,
    $task_creator,
    $status_changer,
    static function (int $task_id) use (&$stored_actions, &$action_attempts): ?array {
        $action_attempts++;
        $stored_actions[$task_id] = [
            'task_id' => $task_id,
            'action_key' => 'push.activate',
            'handler' => 'push.activate',
            'label' => 'Activar notificaciones',
        ];

        return $stored_actions[$task_id];
    }
))->execute([
    'device_key' => $device_key,
    'readiness' => 'unprepared',
]);

ac_assert(
    'Later reconciliation repairs same orphan occurrence',
    !empty($later_repair['success'])
    && empty($later_repair['data']['created'])
    && (int) ($later_repair['data']['task']['id'] ?? 0) === $orphan_task_id
);
ac_assert(
    'Later reconciliation does not create a second occurrence',
    count($stored_tasks) === 1
);
ac_assert(
    'Later reconciliation attaches push.activate to the same task',
    isset($stored_actions[$orphan_task_id])
    && ($stored_actions[$orphan_task_id]['handler'] ?? '') === 'push.activate'
);

// ─── AJAX wiring ─────────────────────────────────────────────

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/TasksAjax.php');
ac_assert('AJAX registers aa_reconcile_push_activation_task', strpos($ajax_src, 'aa_reconcile_push_activation_task') !== false);
ac_assert('AJAX handler uses ReconcilePushActivationTaskUseCase', strpos($ajax_src, 'ReconcilePushActivationTaskUseCase') !== false);
ac_assert('AJAX handler passes only device_key and readiness', strpos($ajax_src, "'device_key' => self::post_string('device_key')") !== false && strpos($ajax_src, "'readiness' => self::post_string('readiness')") !== false);
ac_assert('AJAX lock failure uses 409', strpos($ajax_src, 'push_task_lock_unavailable') !== false && strpos($ajax_src, '$status = 409') !== false);

echo "\n";
echo "Resultado: {$passed}/{$total} OK\n";

if ($failed !== []) {
    echo "Fallos:\n";
    foreach ($failed as $label) {
        echo " - {$label}\n";
    }
    exit(1);
}

exit(0);
