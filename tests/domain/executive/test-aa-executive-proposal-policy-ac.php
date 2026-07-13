<?php
/**
 * AC MC1 — AA_Executive_Proposal_Policy.
 *
 * Ejecutar: php tests/domain/executive/test-aa-executive-proposal-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-contract.php';
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-proposal-policy.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-list.php';
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
 * @return array<string,mixed>
 */
function exec_visible_eval(string $bucket = 'primary', bool $can_dismiss = true): array {
    return [
        'visible_in_active' => true,
        'projection' => [
            'visible_in_active' => true,
            'projected_bucket' => $bucket,
            'projection_reason' => $bucket === 'secondary' ? 'default_secondary' : 'default_primary',
        ],
        'capabilities' => [
            'can_defer' => false,
            'can_dismiss' => $can_dismiss,
            'can_reactivate' => false,
        ],
    ];
}

/**
 * @return array<string,mixed>
 */
function exec_hidden_eval(string $reason = 'dismissed'): array {
    return [
        'visible_in_active' => false,
        'projection' => [
            'visible_in_active' => false,
            'projected_bucket' => null,
            'projection_reason' => $reason,
        ],
        'capabilities' => [
            'can_defer' => false,
            'can_dismiss' => false,
            'can_reactivate' => false,
        ],
    ];
}

/**
 * @param list<array<string,mixed>> $lists
 * @param list<array<string,mixed>> $tasks
 * @param array<string,mixed>       $organization
 * @param array<string,mixed>       $context
 * @return array<string,mixed>
 */
function exec_propose(array $lists, array $tasks, array $organization, array $context = []): array {
    return (new AA_Executive_Proposal_Policy())->propose([
        'lists' => $lists,
        'tasks' => $tasks,
        'organization' => $organization,
    ], $context);
}

$policy = new AA_Executive_Proposal_Policy();

// ─── Lista foco por list_order ─────────────────────────────────

$focus_result = exec_propose(
    [
        ['id' => 1, 'title' => 'Baja', 'importance' => 5, 'status' => 'active'],
        ['id' => 2, 'title' => 'Alta', 'importance' => 20, 'status' => 'active'],
    ],
    [
        ['id' => 101, 'list_id' => 1, 'title' => 'T1', 'status' => 'pending', 'importance' => 1],
        ['id' => 201, 'list_id' => 2, 'title' => 'T2', 'status' => 'pending', 'importance' => 1],
    ],
    [
        'list_order' => [2, 1],
        'task_evaluations_by_id' => [
            101 => exec_visible_eval(),
            201 => exec_visible_eval(),
        ],
    ]
);
ac_assert('Focus list follows list_order importance DESC', ($focus_result['focus_list_id'] ?? null) === 2);
ac_assert('Focus status ready when eligible tasks exist', ($focus_result['status'] ?? '') === AA_Executive_Contract::STATUS_READY);

$skip_empty_list = exec_propose(
    [
        ['id' => 10, 'title' => 'Sin elegibles', 'importance' => 50, 'status' => 'active'],
        ['id' => 11, 'title' => 'Con elegibles', 'importance' => 10, 'status' => 'active'],
    ],
    [
        ['id' => 110, 'list_id' => 10, 'title' => 'Dismissed', 'status' => 'pending'],
        ['id' => 111, 'list_id' => 11, 'title' => 'Visible', 'status' => 'pending'],
    ],
    [
        'list_order' => [10, 11],
        'task_evaluations_by_id' => [
            110 => exec_hidden_eval(),
            111 => exec_visible_eval(),
        ],
    ]
);
ac_assert('Skips list without eligible tasks', ($skip_empty_list['focus_list_id'] ?? null) === 11);

$agenda_app_focus = exec_propose(
    [
        ['id' => 55, 'title' => 'Activación', 'importance' => 100, 'status' => 'active', 'source_category' => 'agenda_app'],
        ['id' => 56, 'title' => 'User', 'importance' => 5, 'status' => 'active', 'source_category' => 'user'],
    ],
    [
        ['id' => 551, 'list_id' => 55, 'title' => 'Seed', 'status' => 'pending', 'importance' => 90],
        ['id' => 561, 'list_id' => 56, 'title' => 'User task', 'status' => 'pending'],
    ],
    [
        'list_order' => [55, 56],
        'task_evaluations_by_id' => [
            551 => exec_visible_eval(),
            561 => exec_visible_eval(),
        ],
    ]
);
ac_assert('agenda_app can be focus list', ($agenda_app_focus['focus_list_id'] ?? null) === 55);

// ─── Top-3 misma lista ───────────────────────────────────────

$top3_same_list = exec_propose(
    [['id' => 3, 'title' => 'Ventas', 'status' => 'active']],
    [
        ['id' => 301, 'list_id' => 3, 'title' => 'A', 'status' => 'pending', 'importance' => 10, 'position' => 0],
        ['id' => 302, 'list_id' => 3, 'title' => 'B', 'status' => 'pending', 'importance' => 20, 'position' => 0],
        ['id' => 303, 'list_id' => 3, 'title' => 'C', 'status' => 'pending', 'importance' => 30, 'position' => 0],
        ['id' => 304, 'list_id' => 3, 'title' => 'D', 'status' => 'pending', 'importance' => 40, 'position' => 0],
        ['id' => 399, 'list_id' => 99, 'title' => 'Otra lista', 'status' => 'pending', 'importance' => 100],
    ],
    [
        'list_order' => [3, 99],
        'task_evaluations_by_id' => [
            301 => exec_visible_eval(),
            302 => exec_visible_eval(),
            303 => exec_visible_eval(),
            304 => exec_visible_eval(),
            399 => exec_visible_eval(),
        ],
    ]
);
ac_assert('Top-3 returns at most three tasks', count($top3_same_list['task_ids'] ?? []) === 3);
ac_assert(
    'Top-3 only takes tasks from focus list',
    ($top3_same_list['task_ids'] ?? []) === [304, 303, 302]
);
ac_assert('Eligible count reflects all candidates in focus list', ($top3_same_list['eligible_count_in_focus_list'] ?? 0) === 4);

// ─── Orden primary / importance / position / id ──────────────

$bucket_order = exec_propose(
    [['id' => 4, 'title' => 'Ops', 'status' => 'active']],
    [
        ['id' => 401, 'list_id' => 4, 'title' => 'Secondary high', 'status' => 'pending', 'importance' => 90, 'default_bucket' => 'secondary'],
        ['id' => 402, 'list_id' => 4, 'title' => 'Primary low', 'status' => 'pending', 'importance' => 1, 'default_bucket' => 'primary'],
    ],
    [
        'list_order' => [4],
        'task_evaluations_by_id' => [
            401 => exec_visible_eval('secondary'),
            402 => exec_visible_eval('primary'),
        ],
    ]
);
ac_assert('Primary bucket before secondary', ($bucket_order['task_ids'] ?? []) === [402, 401]);

$importance_order = exec_propose(
    [['id' => 5, 'title' => 'Ops', 'status' => 'active']],
    [
        ['id' => 501, 'list_id' => 5, 'title' => 'Low', 'status' => 'pending', 'importance' => 5, 'position' => 0],
        ['id' => 502, 'list_id' => 5, 'title' => 'High', 'status' => 'pending', 'importance' => 50, 'position' => 0],
    ],
    [
        'list_order' => [5],
        'task_evaluations_by_id' => [
            501 => exec_visible_eval(),
            502 => exec_visible_eval(),
        ],
    ]
);
ac_assert('Importance DESC within same bucket', ($importance_order['task_ids'] ?? []) === [502, 501]);

$position_tiebreak = exec_propose(
    [['id' => 6, 'title' => 'Ops', 'status' => 'active']],
    [
        ['id' => 601, 'list_id' => 6, 'title' => 'Late position', 'status' => 'pending', 'importance' => 10, 'position' => 20],
        ['id' => 602, 'list_id' => 6, 'title' => 'Early position', 'status' => 'pending', 'importance' => 10, 'position' => 5],
    ],
    [
        'list_order' => [6],
        'task_evaluations_by_id' => [
            601 => exec_visible_eval(),
            602 => exec_visible_eval(),
        ],
    ]
);
ac_assert('Position ASC tie-break', ($position_tiebreak['task_ids'] ?? []) === [602, 601]);

$id_tiebreak = exec_propose(
    [['id' => 7, 'title' => 'Ops', 'status' => 'active']],
    [
        ['id' => 702, 'list_id' => 7, 'title' => 'B', 'status' => 'pending', 'importance' => 0, 'position' => 0],
        ['id' => 701, 'list_id' => 7, 'title' => 'A', 'status' => 'pending', 'importance' => 0, 'position' => 0],
    ],
    [
        'list_order' => [7],
        'task_evaluations_by_id' => [
            701 => exec_visible_eval(),
            702 => exec_visible_eval(),
        ],
    ]
);
ac_assert('Id ASC tie-break', ($id_tiebreak['task_ids'] ?? []) === [701, 702]);

// ─── Exclusiones ─────────────────────────────────────────────

$exclusions = exec_propose(
    [['id' => 8, 'title' => 'Ops', 'status' => 'active']],
    [
        ['id' => 801, 'list_id' => 8, 'title' => 'Done', 'status' => 'done'],
        ['id' => 802, 'list_id' => 8, 'title' => 'Archived', 'status' => 'pending', 'archived_at' => '2026-06-01 10:00:00'],
        ['id' => 803, 'list_id' => 8, 'title' => 'Dismissed', 'status' => 'pending'],
        ['id' => 804, 'list_id' => 8, 'title' => 'Visible', 'status' => 'pending', 'importance' => 5],
    ],
    [
        'list_order' => [8],
        'task_evaluations_by_id' => [
            801 => exec_visible_eval(),
            802 => exec_hidden_eval('archived'),
            803 => exec_hidden_eval('dismissed'),
            804 => exec_visible_eval(),
        ],
    ]
);
ac_assert('Done archived dismissed excluded from top-3', ($exclusions['task_ids'] ?? []) === [804]);
ac_assert('is_eligible rejects done task', !$policy->is_eligible_task(AA_Task::from_array(['id' => 9, 'list_id' => 1, 'status' => 'done']), [9 => exec_visible_eval()]));
ac_assert('is_eligible rejects dismissed task', !$policy->is_eligible_task(AA_Task::from_array(['id' => 10, 'list_id' => 1, 'status' => 'pending']), [10 => exec_hidden_eval()]));

$archived_list = exec_propose(
    [
        ['id' => 90, 'title' => 'Archivada', 'status' => 'archived', 'importance' => 100],
        ['id' => 91, 'title' => 'Activa', 'status' => 'active', 'importance' => 1],
    ],
    [
        ['id' => 900, 'list_id' => 90, 'title' => 'En archivada', 'status' => 'pending'],
        ['id' => 910, 'list_id' => 91, 'title' => 'En activa', 'status' => 'pending'],
    ],
    [
        'list_order' => [90, 91],
        'task_evaluations_by_id' => [
            900 => exec_visible_eval(),
            910 => exec_visible_eval(),
        ],
    ]
);
ac_assert('Archived list cannot become focus', ($archived_list['focus_list_id'] ?? null) === 91);

// ─── Empty ───────────────────────────────────────────────────

$empty = exec_propose(
    [['id' => 12, 'title' => 'Vacía', 'status' => 'active']],
    [
        ['id' => 1201, 'list_id' => 12, 'title' => 'Dismissed only', 'status' => 'pending'],
    ],
    [
        'list_order' => [12],
        'task_evaluations_by_id' => [
            1201 => exec_hidden_eval(),
        ],
    ]
);
ac_assert('Empty when no eligible tasks', ($empty['status'] ?? '') === AA_Executive_Contract::STATUS_EMPTY);
ac_assert('Empty clears focus list id', ($empty['focus_list_id'] ?? null) === null);
ac_assert('Empty returns no task ids', ($empty['task_ids'] ?? [1]) === []);

// ─── Sprint preferred focus (MC4) ─────────────────────────────

$preferred_forces = exec_propose(
    [
        ['id' => 1, 'title' => 'Primera en orden', 'importance' => 50, 'status' => 'active'],
        ['id' => 2, 'title' => 'Preferred', 'importance' => 5, 'status' => 'active'],
    ],
    [
        ['id' => 101, 'list_id' => 1, 'title' => 'T1', 'status' => 'pending'],
        ['id' => 201, 'list_id' => 2, 'title' => 'T2', 'status' => 'pending'],
    ],
    [
        'list_order' => [1, 2],
        'task_evaluations_by_id' => [
            101 => exec_visible_eval(),
            201 => exec_visible_eval(),
        ],
    ],
    [
        'preferred_focus_list_id' => 2,
        'sprint_active' => true,
    ]
);
ac_assert('Preferred valid list forces focus', ($preferred_forces['focus_list_id'] ?? null) === 2);
ac_assert('Preferred used flag true', ($preferred_forces['preferred_focus_used'] ?? false) === true);
ac_assert('Preferred sets sprint focus reason', ($preferred_forces['focus_reason'] ?? '') === AA_Executive_Contract::FOCUS_REASON_SPRINT_ACTIVE);

$preferred_exhausted = exec_propose(
    [
        ['id' => 10, 'title' => 'Agotada', 'importance' => 100, 'status' => 'active'],
        ['id' => 11, 'title' => 'Siguiente', 'importance' => 1, 'status' => 'active'],
    ],
    [
        ['id' => 1001, 'list_id' => 10, 'title' => 'Hidden', 'status' => 'pending'],
        ['id' => 1101, 'list_id' => 11, 'title' => 'Visible', 'status' => 'pending'],
    ],
    [
        'list_order' => [10, 11],
        'task_evaluations_by_id' => [
            1001 => exec_hidden_eval(),
            1101 => exec_visible_eval(),
        ],
    ],
    [
        'preferred_focus_list_id' => 10,
        'sprint_active' => true,
    ]
);
ac_assert('Preferred without eligibles falls back', ($preferred_exhausted['focus_list_id'] ?? null) === 11);
ac_assert('Preferred exhausted not used', ($preferred_exhausted['preferred_focus_used'] ?? true) === false);
ac_assert('Sprint active keeps sprint focus reason on fallback', ($preferred_exhausted['focus_reason'] ?? '') === AA_Executive_Contract::FOCUS_REASON_SPRINT_ACTIVE);

$preferred_archived = exec_propose(
    [
        ['id' => 20, 'title' => 'Archivada', 'status' => 'archived', 'importance' => 100],
        ['id' => 21, 'title' => 'Activa', 'status' => 'active', 'importance' => 1],
    ],
    [
        ['id' => 2001, 'list_id' => 20, 'title' => 'En archivada', 'status' => 'pending'],
        ['id' => 2101, 'list_id' => 21, 'title' => 'En activa', 'status' => 'pending'],
    ],
    [
        'list_order' => [20, 21],
        'task_evaluations_by_id' => [
            2001 => exec_visible_eval(),
            2101 => exec_visible_eval(),
        ],
    ],
    [
        'preferred_focus_list_id' => 20,
        'sprint_active' => true,
    ]
);
ac_assert('Preferred archived list falls back', ($preferred_archived['focus_list_id'] ?? null) === 21);

$no_false_empty = exec_propose(
    [
        ['id' => 30, 'title' => 'Sin elegibles', 'status' => 'active'],
        ['id' => 31, 'title' => 'Con elegibles', 'status' => 'active'],
    ],
    [
        ['id' => 3001, 'list_id' => 30, 'title' => 'Dismissed', 'status' => 'pending'],
        ['id' => 3101, 'list_id' => 31, 'title' => 'Visible', 'status' => 'pending'],
    ],
    [
        'list_order' => [30, 31],
        'task_evaluations_by_id' => [
            3001 => exec_hidden_eval(),
            3101 => exec_visible_eval(),
        ],
    ],
    [
        'preferred_focus_list_id' => 30,
        'sprint_active' => true,
    ]
);
ac_assert('No false empty when other lists have eligibles', ($no_false_empty['status'] ?? '') === AA_Executive_Contract::STATUS_READY);

// ─── Unlinked agenda: hide valid enable_push from executive eligibility ─

$push_device_key = 'a1b2c3d4e5f6789012345678abcdef01';
$push_origin_key = 'enable_push:' . $push_device_key . ':fedcba9876543210';

$unlinked_push_exec = exec_propose(
    [
        ['id' => 50, 'title' => 'Activación de tu agenda', 'status' => 'active', 'importance' => 100],
    ],
    [
        [
            'id' => 777,
            'list_id' => 50,
            'title' => 'Activa notificaciones',
            'status' => 'pending',
            'importance' => 110,
            'origin_key' => $push_origin_key,
        ],
        [
            'id' => 500,
            'list_id' => 50,
            'title' => 'Instala la app',
            'status' => 'pending',
            'importance' => 90,
            'origin_key' => 'pwa.install',
        ],
        [
            'id' => 778,
            'list_id' => 50,
            'title' => 'Push malformada',
            'status' => 'pending',
            'importance' => 80,
            'origin_key' => 'enable_push:bad:bad',
        ],
    ],
    [
        'list_order' => [50],
        'task_evaluations_by_id' => [
            777 => exec_visible_eval(),
            500 => exec_visible_eval(),
            778 => exec_visible_eval(),
        ],
    ],
    [
        'agenda_linked' => false,
    ]
);

ac_assert(
    'Unlinked agenda excludes valid enable_push from top-3',
    ($unlinked_push_exec['task_ids'] ?? []) === [500, 778]
);
ac_assert(
    'Unlinked agenda eligible_count excludes hidden push',
    (int) ($unlinked_push_exec['eligible_count_in_focus_list'] ?? 0) === 2
);
ac_assert(
    'Unlinked agenda keeps Activación list as focus',
    (int) ($unlinked_push_exec['focus_list_id'] ?? 0) === 50
);
ac_assert(
    'Unlinked agenda is_eligible rejects valid enable_push',
    !$policy->is_eligible_task(
        AA_Task::from_array([
            'id' => 777,
            'list_id' => 50,
            'status' => 'pending',
            'origin_key' => $push_origin_key,
        ]),
        [777 => exec_visible_eval()],
        ['agenda_linked' => false]
    )
);
ac_assert(
    'Unlinked agenda keeps malformed enable_push eligible',
    $policy->is_eligible_task(
        AA_Task::from_array([
            'id' => 778,
            'list_id' => 50,
            'status' => 'pending',
            'origin_key' => 'enable_push:bad:bad',
        ]),
        [778 => exec_visible_eval()],
        ['agenda_linked' => false]
    )
);

$linked_push_exec = exec_propose(
    [
        ['id' => 50, 'title' => 'Activación de tu agenda', 'status' => 'active', 'importance' => 100],
    ],
    [
        [
            'id' => 777,
            'list_id' => 50,
            'title' => 'Activa notificaciones',
            'status' => 'pending',
            'importance' => 110,
            'origin_key' => $push_origin_key,
        ],
        [
            'id' => 500,
            'list_id' => 50,
            'title' => 'Instala la app',
            'status' => 'pending',
            'importance' => 90,
            'origin_key' => 'pwa.install',
        ],
    ],
    [
        'list_order' => [50],
        'task_evaluations_by_id' => [
            777 => exec_visible_eval(),
            500 => exec_visible_eval(),
        ],
    ],
    [
        'agenda_linked' => true,
    ]
);

ac_assert(
    'Linked agenda keeps valid enable_push in top-3 by importance',
    ($linked_push_exec['task_ids'] ?? []) === [777, 500]
);
ac_assert(
    'Linked agenda eligible_count includes push task',
    (int) ($linked_push_exec['eligible_count_in_focus_list'] ?? 0) === 2
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
