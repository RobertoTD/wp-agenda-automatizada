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
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-prioritization-policy.php';

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
    return (new AA_Task_Prioritization_Policy())->prioritize([
        'lists' => $lists,
        'tasks' => $tasks,
        'now' => $now,
    ]);
}

$now = '2026-06-04 12:00:00';
$policy = new AA_Task_Prioritization_Policy();

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
ac_assert('Empty snapshot task_bucket_order_by_list', $empty['task_bucket_order_by_list'] === []);
ac_assert('Empty snapshot executive_candidates', $empty['executive_candidates'] === []);

$defensive = $policy->prioritize([
    'lists' => 'invalid',
    'tasks' => null,
]);
ac_assert('Invalid lists/tasks normalize to empty output', $defensive === [
    'list_order' => [],
    'task_order_by_list' => [],
    'task_bucket_order_by_list' => [],
    'executive_candidates' => [],
]);

// ─── Orden de listas ─────────────────────────────────────────

$list_result = tasks_prioritize(
    [
        ['id' => 2, 'title' => 'B', 'importance' => 5, 'position' => 0, 'status' => 'active'],
        ['id' => 1, 'title' => 'A', 'importance' => -3, 'position' => 0, 'status' => 'active'],
        ['id' => 9, 'title' => 'Archivada', 'importance' => -99, 'status' => 'archived'],
    ],
    []
);
ac_assert('Archived list excluded from list_order', $list_result['list_order'] === [1, 2]);
ac_assert('Higher list importance first (lower int)', $list_result['list_order'][0] === 1);

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

$upcoming_result = tasks_prioritize($base_list, [
    ['id' => 201, 'list_id' => 1, 'title' => 'Lejana', 'due_at' => '2026-06-30 10:00:00', 'status' => 'pending'],
    ['id' => 202, 'list_id' => 1, 'title' => 'Próxima', 'due_at' => '2026-06-05 10:00:00', 'status' => 'pending'],
]);
ac_assert('Sooner due_at before later due_at', ($upcoming_result['task_order_by_list'][1] ?? []) === [202, 201]);

$importance_result = tasks_prioritize($base_list, [
    ['id' => 301, 'list_id' => 1, 'title' => 'Baja', 'importance' => 10, 'status' => 'pending'],
    ['id' => 302, 'list_id' => 1, 'title' => 'Alta', 'importance' => -5, 'status' => 'pending'],
]);
ac_assert('Higher importance before lower (int ASC)', ($importance_result['task_order_by_list'][1] ?? []) === [302, 301]);

$done_result = tasks_prioritize($base_list, [
    ['id' => 401, 'list_id' => 1, 'title' => 'Hecha', 'status' => 'done'],
    ['id' => 402, 'list_id' => 1, 'title' => 'Pendiente', 'status' => 'pending'],
]);
ac_assert('Pending before done in task order', ($done_result['task_order_by_list'][1] ?? []) === [402, 401]);
ac_assert('Done excluded from executive_candidates', $done_result['executive_candidates'] === [402]);
ac_assert(
    'Done excluded from active task buckets',
    ($done_result['task_bucket_order_by_list'][1]['primary'] ?? []) === []
    && ($done_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === [402]
);

$bucket_result = tasks_prioritize($base_list, [
    ['id' => 701, 'list_id' => 1, 'title' => 'Normal', 'importance' => 0, 'status' => 'pending'],
    ['id' => 702, 'list_id' => 1, 'title' => 'Vencida', 'due_at' => '2026-06-01 10:00:00', 'importance' => 5, 'status' => 'pending'],
    ['id' => 703, 'list_id' => 1, 'title' => 'Alta importancia', 'importance' => -1, 'status' => 'pending'],
    ['id' => 704, 'list_id' => 1, 'title' => 'Hecha alta', 'due_at' => '2026-06-01 09:00:00', 'importance' => -10, 'status' => 'done'],
]);
ac_assert(
    'Active task buckets project overdue and high importance to primary',
    ($bucket_result['task_bucket_order_by_list'][1]['primary'] ?? []) === [702, 703]
);
ac_assert(
    'Active task buckets project normal pending to secondary',
    ($bucket_result['task_bucket_order_by_list'][1]['secondary'] ?? []) === [701]
);
ac_assert(
    'Task order remains available independently from task buckets',
    ($bucket_result['task_order_by_list'][1] ?? []) === [702, 703, 701, 704]
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
        ['id' => 1, 'title' => 'Alta lista', 'importance' => -10, 'status' => 'active'],
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
ac_assert('Empty task list per list returns empty task buckets', ($empty_list_tasks['task_bucket_order_by_list'][1] ?? null) === [
    'primary' => [],
    'secondary' => [],
]);
ac_assert('Empty tasks yield empty executive candidates', $empty_list_tasks['executive_candidates'] === []);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
