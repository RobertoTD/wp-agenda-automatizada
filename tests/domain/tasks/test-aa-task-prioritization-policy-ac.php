<?php
/**
 * AC MC2 — AA_Task, AA_Task_List, AA_Task_Prioritization_Policy.
 *
 * Ejecutar: php tests/domain/tasks/test-aa-task-prioritization-policy-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-list.php';
require_once __DIR__ . '/../../../includes/domain/tasks/interface-aa-prioritization-provider.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-execution-timing-policy.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-prioritization-policy.php';
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
 * @return array<string,mixed>
 */
function tasks_prioritize(array $lists, array $tasks, string $now = '2026-06-04 12:00:00'): array {
    $execution_timing_policy = new AA_Task_Execution_Timing_Policy(new DateTimeZone('America/Mexico_City'));

    return (new AA_Task_Prioritization_Policy($execution_timing_policy))->prioritize([
        'lists' => $lists,
        'tasks' => $tasks,
        'now' => $now,
    ]);
}

/**
 * @param list<array<string,mixed>> $lists
 * @param list<array<string,mixed>> $tasks
 * @return array<int,array{primary:list<int>,secondary:list<int>}>
 */
function tasks_prioritize_buckets(array $lists, array $tasks, string $now = '2026-06-04 12:00:00'): array {
    $base = tasks_prioritize($lists, $tasks, $now);
    $signal_evaluations = (new AA_Task_Signal_Policy())->evaluate_all([
        'tasks' => $tasks,
        'task_state_by_id' => [],
        'now' => $now,
    ]);
    $projection = (new AA_Task_Active_View_Projection_Policy())->project([
        'lists' => $lists,
        'tasks' => $tasks,
        'list_order' => $base['list_order'],
        'task_order_by_list' => $base['task_order_by_list'],
        'task_evaluations_by_id' => $signal_evaluations,
        'now' => $now,
    ]);

    return $projection['task_bucket_order_by_list'];
}

$now = '2026-06-04 12:00:00';
$policy = new AA_Task_Prioritization_Policy(
    new AA_Task_Execution_Timing_Policy(new DateTimeZone('America/Mexico_City'))
);

ac_assert(
    'Policy implements prioritization port',
    $policy instanceof AA_Prioritization_Provider_Interface
);

// ─── Normalización Task ──────────────────────────────────────

$task_min = AA_Task::from_array(['id' => 7, 'list_id' => 2, 'title' => 'Hacer algo']);
$task_array = $task_min->to_array();
ac_assert('Task normalizes minimal fields', $task_array['id'] === 7 && $task_array['list_id'] === 2);
ac_assert('Task default status pending', $task_array['status'] === 'pending');
ac_assert('Task default importance 0', $task_array['importance'] === 0);
ac_assert('Task default position 0', $task_array['position'] === 0);
ac_assert('Task default source user', $task_array['source'] === 'user');
ac_assert('Task default due_at null', $task_array['due_at'] === null);

$task_defensive = AA_Task::from_array([
    'id' => '9',
    'status' => 'DONE',
    'importance' => '4',
    'due_at' => '',
]);
ac_assert('Task coerces id to int', $task_defensive->id() === 9);
ac_assert('Task normalizes done status', $task_defensive->status() === 'done');
ac_assert('Task invalid status falls back to pending', AA_Task::from_array(['status' => 'weird'])->status() === 'pending');
ac_assert('Task overdue detection', AA_Task::from_array([
    'status' => 'pending',
    'due_at' => '2026-06-01 09:00:00',
])->is_overdue($now));
ac_assert('Task upcoming detection', AA_Task::from_array([
    'status' => 'pending',
    'due_at' => '2026-06-10 09:00:00',
])->has_upcoming_due($now));

// ─── Normalización TaskList ───────────────────────────────────

$list_min = AA_Task_List::from_array(['id' => 3, 'title' => 'Mi lista']);
$list_array = $list_min->to_array();
ac_assert('TaskList normalizes minimal fields', $list_array['id'] === 3 && $list_array['title'] === 'Mi lista');
ac_assert('TaskList default status active', $list_array['status'] === 'active');
ac_assert('TaskList default owner_type user', $list_array['owner_type'] === 'user');
ac_assert('TaskList default importance 0', $list_array['importance'] === 0);
ac_assert('TaskList archived status', AA_Task_List::from_array(['status' => 'archived'])->is_archived());
ac_assert('TaskList invalid status falls back to active', AA_Task_List::from_array(['status' => 'gone'])->is_active());

// ─── Snapshot vacío / incompleto ─────────────────────────────

$empty = $policy->prioritize([]);
ac_assert('Empty snapshot list_order', $empty['list_order'] === []);
ac_assert('Empty snapshot task_order_by_list', $empty['task_order_by_list'] === []);
ac_assert('Empty snapshot executive_candidates', $empty['executive_candidates'] === []);

$defensive = $policy->prioritize([
    'lists' => 'invalid',
    'tasks' => null,
]);
ac_assert('Invalid lists/tasks normalize to empty output', $defensive === [
    'list_order' => [],
    'task_order_by_list' => [],
    'executive_candidates' => [],
]);

// ─── Orden de listas ─────────────────────────────────────────

$list_result = tasks_prioritize(
    [
        ['id' => 2, 'title' => 'B', 'importance' => 5, 'position' => 0, 'status' => 'active'],
        ['id' => 1, 'title' => 'A', 'importance' => 10, 'position' => 0, 'status' => 'active'],
        ['id' => 9, 'title' => 'Archivada', 'importance' => -99, 'status' => 'archived'],
    ],
    []
);
ac_assert('Archived list excluded from list_order', $list_result['list_order'] === [1, 2]);
ac_assert('Higher list importance first (int DESC)', $list_result['list_order'][0] === 1);

$list_position = tasks_prioritize(
    [
        ['id' => 10, 'title' => 'L2', 'importance' => 0, 'position' => 20, 'status' => 'active'],
        ['id' => 11, 'title' => 'L1', 'importance' => 0, 'position' => 5, 'status' => 'active'],
    ],
    []
);
ac_assert('List tie-break by position ASC', $list_position['list_order'] === [11, 10]);

// ─── Orden de tareas ─────────────────────────────────────────

$base_list = [['id' => 1, 'title' => 'Lista', 'status' => 'active']];

$overdue_result = tasks_prioritize($base_list, [
    ['id' => 101, 'list_id' => 1, 'title' => 'Futura', 'due_at' => '2026-06-20 10:00:00', 'status' => 'pending'],
    ['id' => 102, 'list_id' => 1, 'title' => 'Vencida', 'due_at' => '2026-06-01 10:00:00', 'status' => 'pending'],
]);
ac_assert('Overdue task before non-overdue', ($overdue_result['task_order_by_list'][1] ?? []) === [102, 101]);

$overdue_due_order = tasks_prioritize($base_list, [
    ['id' => 201, 'list_id' => 1, 'title' => 'Vencida tarde', 'due_at' => '2026-06-03 10:00:00', 'importance' => 0, 'status' => 'pending'],
    ['id' => 202, 'list_id' => 1, 'title' => 'Vencida temprano', 'due_at' => '2026-06-01 10:00:00', 'importance' => 0, 'status' => 'pending'],
]);
ac_assert('Layer 4 tie-breaks by due_at ASC', ($overdue_due_order['task_order_by_list'][1] ?? []) === [202, 201]);

$importance_result = tasks_prioritize($base_list, [
    ['id' => 301, 'list_id' => 1, 'title' => 'Baja', 'importance' => 10, 'status' => 'pending'],
    ['id' => 302, 'list_id' => 1, 'title' => 'Alta', 'importance' => 90, 'status' => 'pending'],
]);
ac_assert('Higher importance before lower (int DESC)', ($importance_result['task_order_by_list'][1] ?? []) === [302, 301]);

$done_result = tasks_prioritize($base_list, [
    ['id' => 401, 'list_id' => 1, 'title' => 'Hecha', 'status' => 'done'],
    ['id' => 402, 'list_id' => 1, 'title' => 'Pendiente', 'status' => 'pending'],
]);
ac_assert('Pending before done in task order', ($done_result['task_order_by_list'][1] ?? []) === [402, 401]);
ac_assert('Done excluded from executive_candidates', $done_result['executive_candidates'] === [402]);

$archived_pending_result = tasks_prioritize($base_list, [
    ['id' => 410, 'list_id' => 1, 'title' => 'Archivada pending', 'status' => 'pending', 'archived_at' => '2026-06-10 10:00:00'],
    ['id' => 411, 'list_id' => 1, 'title' => 'Activa pending', 'status' => 'pending'],
]);
ac_assert(
    'Archived pending excluded from executive_candidates',
    $archived_pending_result['executive_candidates'] === [411]
);

$position_result = tasks_prioritize($base_list, [
    ['id' => 501, 'list_id' => 1, 'title' => 'T2', 'position' => 20, 'status' => 'pending'],
    ['id' => 502, 'list_id' => 1, 'title' => 'T1', 'position' => 5, 'status' => 'pending'],
]);
ac_assert('Task tie-break by position ASC', ($position_result['task_order_by_list'][1] ?? []) === [502, 501]);

$id_tiebreak = tasks_prioritize($base_list, [
    ['id' => 601, 'list_id' => 1, 'title' => 'Same', 'position' => 0, 'importance' => 0, 'status' => 'pending'],
    ['id' => 602, 'list_id' => 1, 'title' => 'Same', 'position' => 0, 'importance' => 0, 'status' => 'pending'],
]);
ac_assert('Task tie-break by id ASC', ($id_tiebreak['task_order_by_list'][1] ?? []) === [601, 602]);

$archived_tasks = tasks_prioritize(
    [['id' => 99, 'title' => 'Archivada', 'status' => 'archived']],
    [['id' => 900, 'list_id' => 99, 'title' => 'En archivada', 'status' => 'pending']]
);
ac_assert('Tasks in archived list excluded from executive_candidates', $archived_tasks['executive_candidates'] === []);
ac_assert('Archived list excluded from task_order_by_list keys', !isset($archived_tasks['task_order_by_list'][99]));

$executive_result = tasks_prioritize(
    [
        ['id' => 1, 'title' => 'Alta lista', 'importance' => 90, 'status' => 'active'],
        ['id' => 2, 'title' => 'Baja lista', 'importance' => 10, 'status' => 'active'],
    ],
    [
        ['id' => 11, 'list_id' => 2, 'title' => 'Urgente lista 2', 'due_at' => '2026-06-01 08:00:00', 'status' => 'pending'],
        ['id' => 12, 'list_id' => 1, 'title' => 'Sin due lista 1', 'status' => 'pending'],
    ]
);
ac_assert(
    'Executive candidates prioritize task urgency over list order',
    ($executive_result['executive_candidates'][0] ?? null) === 11
);
ac_assert(
    'Executive candidates include multiple pending tasks',
    count($executive_result['executive_candidates']) === 2
);

$empty_list_tasks = tasks_prioritize($base_list, []);
ac_assert('Empty task list per list returns empty order', ($empty_list_tasks['task_order_by_list'][1] ?? []) === []);
ac_assert('Empty tasks yield empty executive candidates', $empty_list_tasks['executive_candidates'] === []);

// ─── Capas temporales (MC timing integration) ────────────────

$layer_precedence = tasks_prioritize($base_list, [
    ['id' => 1, 'list_id' => 1, 'title' => 'Capa 1', 'importance' => 100, 'execution_available_at' => '2026-06-10 10:00:00', 'status' => 'pending'],
    ['id' => 2, 'list_id' => 1, 'title' => 'Capa 2', 'importance' => 1, 'status' => 'pending'],
    ['id' => 3, 'list_id' => 1, 'title' => 'Capa 3', 'importance' => 1, 'execution_available_at' => '2026-06-01 08:00:00', 'due_at' => '2026-06-10 10:00:00', 'status' => 'pending'],
    ['id' => 4, 'list_id' => 1, 'title' => 'Capa 4', 'importance' => 1, 'due_at' => '2026-06-01 10:00:00', 'status' => 'pending'],
]);
ac_assert(
    'Temporal layer precedence is 4 > 3 > 2 > 1',
    ($layer_precedence['task_order_by_list'][1] ?? []) === [4, 3, 2, 1]
);

$layer4_over_layer3_score = tasks_prioritize($base_list, [
    ['id' => 301, 'list_id' => 1, 'title' => 'Capa 3 score alto', 'importance' => 100, 'execution_available_at' => '2026-06-01 08:00:00', 'due_at' => '2026-06-05 12:00:00', 'status' => 'pending'],
    ['id' => 401, 'list_id' => 1, 'title' => 'Capa 4 baja', 'importance' => 5, 'due_at' => '2026-06-01 10:00:00', 'status' => 'pending'],
]);
ac_assert(
    'Low importance layer 4 beats high score layer 3',
    ($layer4_over_layer3_score['task_order_by_list'][1] ?? []) === [401, 301]
);

$layer3_over_layer2 = tasks_prioritize($base_list, [
    ['id' => 501, 'list_id' => 1, 'title' => 'Capa 2 alta', 'importance' => 100, 'status' => 'pending'],
    ['id' => 502, 'list_id' => 1, 'title' => 'Capa 3 baja', 'importance' => 1, 'execution_available_at' => '2026-06-01 08:00:00', 'status' => 'pending'],
]);
ac_assert(
    'Layer 3 beats layer 2 despite lower importance',
    ($layer3_over_layer2['task_order_by_list'][1] ?? []) === [502, 501]
);

$layer2_over_layer1 = tasks_prioritize($base_list, [
    ['id' => 601, 'list_id' => 1, 'title' => 'Capa 1 alta', 'importance' => 100, 'execution_available_at' => '2026-06-10 10:00:00', 'status' => 'pending'],
    ['id' => 602, 'list_id' => 1, 'title' => 'Capa 2 baja', 'importance' => 1, 'status' => 'pending'],
]);
ac_assert(
    'Layer 2 beats layer 1 despite lower importance',
    ($layer2_over_layer1['task_order_by_list'][1] ?? []) === [602, 601]
);

$layer3_score_order = tasks_prioritize($base_list, [
    [
        'id' => 701,
        'list_id' => 1,
        'title' => 'Capa 3 score bajo',
        'importance' => 50,
        'execution_available_at' => '2026-06-01 08:00:00',
        'due_at' => '2026-06-10 10:00:00',
        'status' => 'pending',
    ],
    [
        'id' => 702,
        'list_id' => 1,
        'title' => 'Capa 3 score alto',
        'importance' => 50,
        'execution_available_at' => '2026-06-01 08:00:00',
        'due_at' => '2026-06-04 20:00:00',
        'status' => 'pending',
    ],
]);
ac_assert(
    'Layer 3 orders by priority_score DESC',
    ($layer3_score_order['task_order_by_list'][1] ?? []) === [702, 701]
);

$layer3_negative_importance = tasks_prioritize($base_list, [
    [
        'id' => 801,
        'list_id' => 1,
        'title' => 'Negativa sin urgencia',
        'importance' => -90,
        'execution_available_at' => '2026-06-04 12:00:00',
        'due_at' => '2026-06-05 12:00:00',
        'status' => 'pending',
    ],
    [
        'id' => 802,
        'list_id' => 1,
        'title' => 'Negativa con urgencia',
        'importance' => -90,
        'execution_available_at' => '2026-06-01 08:00:00',
        'due_at' => '2026-06-05 12:00:00',
        'status' => 'pending',
    ],
]);
ac_assert(
    'Layer 3 negative importance orders by higher priority_score',
    ($layer3_negative_importance['task_order_by_list'][1] ?? []) === [802, 801]
);

$layer1_importance_order = tasks_prioritize($base_list, [
    ['id' => 901, 'list_id' => 1, 'title' => 'Capa 1 baja', 'importance' => 10, 'execution_available_at' => '2026-06-10 10:00:00', 'status' => 'pending'],
    ['id' => 902, 'list_id' => 1, 'title' => 'Capa 1 alta', 'importance' => 90, 'execution_available_at' => '2026-06-10 12:00:00', 'status' => 'pending'],
]);
ac_assert(
    'Layer 1 orders by importance DESC',
    ($layer1_importance_order['task_order_by_list'][1] ?? []) === [902, 901]
);

$layer2_importance_order = tasks_prioritize($base_list, [
    ['id' => 911, 'list_id' => 1, 'title' => 'Capa 2 baja', 'importance' => 10, 'status' => 'pending'],
    ['id' => 912, 'list_id' => 1, 'title' => 'Capa 2 alta', 'importance' => 90, 'status' => 'pending'],
]);
ac_assert(
    'Layer 2 orders by importance DESC',
    ($layer2_importance_order['task_order_by_list'][1] ?? []) === [912, 911]
);

$layer4_importance_order = tasks_prioritize($base_list, [
    ['id' => 921, 'list_id' => 1, 'title' => 'Capa 4 baja', 'importance' => 10, 'due_at' => '2026-06-01 10:00:00', 'status' => 'pending'],
    ['id' => 922, 'list_id' => 1, 'title' => 'Capa 4 alta', 'importance' => 90, 'due_at' => '2026-06-02 10:00:00', 'status' => 'pending'],
]);
ac_assert(
    'Layer 4 orders by importance DESC before due_at',
    ($layer4_importance_order['task_order_by_list'][1] ?? []) === [922, 921]
);

$future_due_regression = tasks_prioritize($base_list, [
    ['id' => 1001, 'list_id' => 1, 'title' => 'Solo due futuro', 'importance' => 10, 'due_at' => '2026-06-10 10:00:00', 'status' => 'pending'],
    ['id' => 1002, 'list_id' => 1, 'title' => 'Sin due alta', 'importance' => 90, 'status' => 'pending'],
]);
ac_assert(
    'Future due_at alone no longer dominates higher importance task',
    ($future_due_regression['task_order_by_list'][1] ?? []) === [1002, 1001]
);

$bucket_order = tasks_prioritize_buckets($base_list, [
    ['id' => 1201, 'list_id' => 1, 'title' => 'Primary capa 4', 'importance' => 1, 'due_at' => '2026-06-01 10:00:00', 'default_bucket' => 'primary', 'status' => 'pending'],
    ['id' => 1202, 'list_id' => 1, 'title' => 'Primary capa 2', 'importance' => 90, 'default_bucket' => 'primary', 'status' => 'pending'],
    ['id' => 1203, 'list_id' => 1, 'title' => 'Secondary capa 3', 'importance' => 1, 'execution_available_at' => '2026-06-01 08:00:00', 'default_bucket' => 'secondary', 'status' => 'pending'],
    ['id' => 1204, 'list_id' => 1, 'title' => 'Secondary capa 1', 'importance' => 90, 'execution_available_at' => '2026-06-10 10:00:00', 'default_bucket' => 'secondary', 'status' => 'pending'],
]);
ac_assert(
    'Primary bucket preserves temporal order independently',
    ($bucket_order[1]['primary'] ?? []) === [1201, 1202]
);
ac_assert(
    'Secondary bucket preserves temporal order independently',
    ($bucket_order[1]['secondary'] ?? []) === [1203, 1204]
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
