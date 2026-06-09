<?php
/**
 * AC MC13G-D — AA_Task_Active_View_Projection_Policy.
 *
 * Ejecutar: php tests/domain/tasks/test-aa-task-active-view-projection-policy-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-list.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-signal-policy.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-active-view-projection-policy.php';

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

/**
 * @param list<array<string,mixed>> $lists
 * @param list<array<string,mixed>> $tasks
 * @param array<int,array<string,mixed>> $task_state_by_id
 * @return array<string,mixed>
 */
function active_view_project(
    array $lists,
    array $tasks,
    array $task_state_by_id = [],
    string $now = '2026-06-04 12:00:00'
): array {
    $signal_evaluations = (new AA_Task_Signal_Policy())->evaluate_all([
        'tasks' => $tasks,
        'task_state_by_id' => $task_state_by_id,
        'now' => $now,
    ]);

    return (new AA_Task_Active_View_Projection_Policy())->project([
        'lists' => $lists,
        'tasks' => $tasks,
        'list_order' => array_map(static function (array $list): int {
            return (int) ($list['id'] ?? 0);
        }, $lists),
        'task_order_by_list' => [
            1 => array_values(array_map(static function (array $task): int {
                return (int) ($task['id'] ?? 0);
            }, array_filter($tasks, static function (array $task): bool {
                return (int) ($task['list_id'] ?? 0) === 1;
            }))),
        ],
        'task_evaluations_by_id' => $signal_evaluations,
        'now' => $now,
    ]);
}

$base_list = [[
    'id' => 1,
    'title' => 'Clientes',
    'status' => 'active',
]];

$default_result = active_view_project($base_list, [
    ['id' => 10, 'list_id' => 1, 'title' => 'Sin señales', 'status' => 'pending', 'importance' => 0],
]);
$default_eval = $default_result['task_evaluations_by_id'][10] ?? null;
ac_assert(
    'Pending user without signals projects to primary',
    ($default_result['task_bucket_order_by_list'][1]['primary'] ?? []) === [10]
    && ($default_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === []
);
ac_assert(
    'Pending user without signals can_defer only',
    is_array($default_eval)
    && ($default_eval['capabilities']['can_defer'] ?? false) === true
    && ($default_eval['capabilities']['can_dismiss'] ?? true) === false
    && ($default_eval['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_DEFAULT_PRIMARY
);

$defer_result = active_view_project($base_list, [
    ['id' => 11, 'list_id' => 1, 'title' => 'Deferred', 'status' => 'pending'],
], [
    11 => [
        'task_id' => 11,
        'last_deferred_at' => '2026-06-04 10:00:00',
        'defer_count' => 1,
        'last_dismissed_at' => null,
        'dismiss_count' => 0,
        'defer_until' => null,
        'dismiss_until' => null,
    ],
]);
$defer_eval = $defer_result['task_evaluations_by_id'][11] ?? null;
ac_assert(
    'Deferred task projects to secondary',
    ($defer_result['task_bucket_order_by_list'][1]['primary'] ?? []) === []
    && ($defer_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === [11]
);
ac_assert(
    'Deferred task can_dismiss only',
    is_array($defer_eval)
    && ($defer_eval['capabilities']['can_defer'] ?? true) === false
    && ($defer_eval['capabilities']['can_dismiss'] ?? false) === true
    && ($defer_eval['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_DEFERRED
);

$dismiss_result = active_view_project($base_list, [
    ['id' => 12, 'list_id' => 1, 'title' => 'Dismissed', 'status' => 'pending'],
], [
    12 => [
        'task_id' => 12,
        'last_deferred_at' => null,
        'defer_count' => 0,
        'last_dismissed_at' => '2026-06-04 11:00:00',
        'dismiss_count' => 1,
        'defer_until' => null,
        'dismiss_until' => null,
    ],
]);
$dismiss_eval = $dismiss_result['task_evaluations_by_id'][12] ?? null;
ac_assert(
    'Dismissed task stays outside active buckets',
    ($dismiss_result['task_bucket_order_by_list'][1]['primary'] ?? []) === []
    && ($dismiss_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === []
);
ac_assert(
    'Dismissed task hides from active view',
    is_array($dismiss_eval)
    && ($dismiss_eval['visible_in_active'] ?? true) === false
    && ($dismiss_eval['capabilities']['can_defer'] ?? true) === false
    && ($dismiss_eval['capabilities']['can_dismiss'] ?? true) === false
    && ($dismiss_eval['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_DISMISSED
);

$dismiss_over_defer = active_view_project($base_list, [
    ['id' => 13, 'list_id' => 1, 'title' => 'Both signals', 'status' => 'pending'],
], [
    13 => [
        'task_id' => 13,
        'last_deferred_at' => '2026-06-04 09:00:00',
        'defer_count' => 1,
        'last_dismissed_at' => '2026-06-04 12:00:00',
        'dismiss_count' => 1,
        'defer_until' => null,
        'dismiss_until' => null,
    ],
]);
ac_assert(
    'Dismiss hiding dominates defer for active projection',
    ($dismiss_over_defer['task_bucket_order_by_list'][1]['primary'] ?? []) === []
    && ($dismiss_over_defer['task_bucket_order_by_list'][1]['secondary'] ?? []) === []
    && (($dismiss_over_defer['task_evaluations_by_id'][13]['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_DISMISSED)
);

$returned_dismiss_state = [
    'task_id' => 20,
    'last_deferred_at' => null,
    'defer_count' => 0,
    'last_dismissed_at' => '2026-06-04 09:00:00',
    'dismiss_count' => 1,
    'defer_until' => null,
    'dismiss_until' => '2026-06-04 12:00:00',
];
$returned_result = active_view_project($base_list, [
    ['id' => 20, 'list_id' => 1, 'title' => 'Returned dismissed', 'status' => 'pending'],
], [
    20 => $returned_dismiss_state,
]);
$returned_eval = $returned_result['task_evaluations_by_id'][20] ?? null;
ac_assert(
    'Returned dismissed task projects back to primary',
    ($returned_result['task_bucket_order_by_list'][1]['primary'] ?? []) === [20]
    && ($returned_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === []
);
ac_assert(
    'Returned dismissed task is visible in active view',
    is_array($returned_eval)
    && ($returned_eval['visible_in_active'] ?? false) === true
    && ($returned_eval['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_DEFAULT_PRIMARY
);

$returned_defer_state = [
    'task_id' => 21,
    'last_deferred_at' => '2026-06-04 08:00:00',
    'defer_count' => 1,
    'last_dismissed_at' => '2026-06-04 10:00:00',
    'dismiss_count' => 1,
    'defer_until' => null,
    'dismiss_until' => '2026-06-04 12:00:00',
];
$returned_defer_result = active_view_project($base_list, [
    ['id' => 21, 'list_id' => 1, 'title' => 'Returned dismissed deferred', 'status' => 'pending'],
], [
    21 => $returned_defer_state,
]);
ac_assert(
    'Returned dismissed task with defer projects to secondary',
    ($returned_defer_result['task_bucket_order_by_list'][1]['primary'] ?? []) === []
    && ($returned_defer_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === [21]
    && (($returned_defer_result['task_evaluations_by_id'][21]['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_DEFERRED)
);

$done_result = active_view_project($base_list, [
    ['id' => 14, 'list_id' => 1, 'title' => 'Done', 'status' => 'done'],
]);
ac_assert(
    'Done task stays outside active buckets',
    ($done_result['task_bucket_order_by_list'][1]['primary'] ?? []) === []
    && ($done_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === []
    && (($done_result['task_evaluations_by_id'][14]['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_NOT_PENDING)
);

$system_completed_result = active_view_project($base_list, [
    ['id' => 22, 'list_id' => 1, 'title' => 'System completed', 'status' => 'pending'],
], [
    22 => [
        'task_id' => 22,
        'completed_by_system' => 1,
        'system_completed_at' => '2026-06-04 09:00:00',
        'last_system_evaluated_at' => '2026-06-04 12:00:00',
        'defer_count' => 0,
        'last_deferred_at' => null,
        'defer_until' => null,
        'dismiss_count' => 0,
        'last_dismissed_at' => null,
        'dismiss_until' => null,
    ],
]);
$system_completed_eval = $system_completed_result['task_evaluations_by_id'][22] ?? null;
ac_assert(
    'Pending system-completed task stays outside active buckets',
    ($system_completed_result['task_bucket_order_by_list'][1]['primary'] ?? []) === []
    && ($system_completed_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === []
);
ac_assert(
    'Pending system-completed task uses REASON_SYSTEM_COMPLETED',
    is_array($system_completed_eval)
    && ($system_completed_eval['visible_in_active'] ?? true) === false
    && ($system_completed_eval['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_SYSTEM_COMPLETED
    && ($system_completed_eval['capabilities']['can_defer'] ?? true) === false
    && ($system_completed_eval['capabilities']['can_dismiss'] ?? true) === false
);

$archived_result = active_view_project(
    [[
        'id' => 2,
        'title' => 'Archivada',
        'status' => 'archived',
    ]],
    [
        ['id' => 15, 'list_id' => 2, 'title' => 'En archivada', 'status' => 'pending'],
    ]
);
$archived_result = (new AA_Task_Active_View_Projection_Policy())->project([
    'lists' => [[
        'id' => 2,
        'title' => 'Archivada',
        'status' => 'archived',
    ]],
    'tasks' => [
        ['id' => 15, 'list_id' => 2, 'title' => 'En archivada', 'status' => 'pending'],
    ],
    'list_order' => [2],
    'task_order_by_list' => [2 => [15]],
    'task_evaluations_by_id' => (new AA_Task_Signal_Policy())->evaluate_all([
        'tasks' => [
            ['id' => 15, 'list_id' => 2, 'title' => 'En archivada', 'status' => 'pending'],
        ],
        'task_state_by_id' => [],
        'now' => '2026-06-04 12:00:00',
    ]),
    'now' => '2026-06-04 12:00:00',
]);
ac_assert(
    'Archived list keeps tasks outside active buckets',
    ($archived_result['task_bucket_order_by_list'][2]['primary'] ?? []) === []
    && ($archived_result['task_bucket_order_by_list'][2]['secondary'] ?? []) === []
    && (($archived_result['task_evaluations_by_id'][15]['projection']['projection_reason'] ?? '') === AA_Task_Active_View_Projection_Policy::REASON_LIST_NOT_ACTIVE)
);

$importance_result = active_view_project($base_list, [
    ['id' => 16, 'list_id' => 1, 'title' => 'Alta importancia', 'status' => 'pending', 'importance' => -10],
    ['id' => 17, 'list_id' => 1, 'title' => 'Baja importancia', 'status' => 'pending', 'importance' => 10],
]);
ac_assert(
    'Importance does not decide primary/secondary without signals',
    ($importance_result['task_bucket_order_by_list'][1]['primary'] ?? []) === [16, 17]
    && ($importance_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === []
);

$order_result = active_view_project($base_list, [
    ['id' => 18, 'list_id' => 1, 'title' => 'Lejana', 'status' => 'pending', 'due_at' => '2026-06-30 10:00:00', 'position' => 20],
    ['id' => 19, 'list_id' => 1, 'title' => 'Vencida', 'status' => 'pending', 'due_at' => '2026-06-01 10:00:00', 'position' => 5],
]);
$order_result = (new AA_Task_Active_View_Projection_Policy())->project([
    'lists' => $base_list,
    'tasks' => [
        ['id' => 18, 'list_id' => 1, 'title' => 'Lejana', 'status' => 'pending', 'due_at' => '2026-06-30 10:00:00', 'position' => 20],
        ['id' => 19, 'list_id' => 1, 'title' => 'Vencida', 'status' => 'pending', 'due_at' => '2026-06-01 10:00:00', 'position' => 5],
    ],
    'list_order' => [1],
    'task_order_by_list' => [1 => [19, 18]],
    'task_evaluations_by_id' => (new AA_Task_Signal_Policy())->evaluate_all([
        'tasks' => [
            ['id' => 18, 'list_id' => 1, 'title' => 'Lejana', 'status' => 'pending', 'due_at' => '2026-06-30 10:00:00', 'position' => 20],
            ['id' => 19, 'list_id' => 1, 'title' => 'Vencida', 'status' => 'pending', 'due_at' => '2026-06-01 10:00:00', 'position' => 5],
        ],
        'task_state_by_id' => [],
        'now' => '2026-06-04 12:00:00',
    ]),
    'now' => '2026-06-04 12:00:00',
]);
ac_assert(
    'Primary bucket preserves task_order_by_list order',
    ($order_result['task_bucket_order_by_list'][1]['primary'] ?? []) === [19, 18]
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
