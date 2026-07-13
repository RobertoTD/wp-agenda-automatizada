<?php
/**
 * AC MC7 — Contrato executable + mappers Learning/Tasks.
 *
 * Ejecutar: php tests/application/executable/test-executable-contract-mappers-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $key));
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $args, $url) {
        return rtrim((string) $url, '?') . '?' . http_build_query($args);
    }
}

if (!function_exists('aa_get_current_datetime')) {
    function aa_get_current_datetime() {
        return '2026-06-15 12:00:00';
    }
}

require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/application/executable/LearningRecommendationsToExecutableMapper.php';
require_once __DIR__ . '/../../../includes/application/executable/TaskBoardToExecutableMapper.php';
require_once __DIR__ . '/../../../includes/application/executable/ExecutableVisibleActionsEnricher.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-visible-actions-policy.php';
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

$contract_item = AA_Executable_Contract::normalize_item([
    'id' => '1',
    'source' => 'user',
    'source_category' => 'user',
    'title' => 'Contrato',
    'status' => 'pending',
    'capabilities' => [
        'can_delete' => 1,
    ],
]);
ac_assert(
    'Contract normalizes can_delete on item capabilities',
    ($contract_item['capabilities']['can_delete'] ?? false) === true
);
ac_assert(
    'Contract item defaults is_overdue to false',
    ($contract_item['is_overdue'] ?? true) === false
);

$contract_list = AA_Executable_Contract::normalize_list([
    'id' => '10',
    'source' => 'user',
    'source_category' => 'user',
    'title' => 'Lista contrato',
    'status' => 'active',
    'buckets' => [],
    'capabilities' => [
        'can_delete' => 1,
    ],
]);
ac_assert(
    'Contract normalizes can_delete on list capabilities',
    ($contract_list['capabilities']['can_delete'] ?? false) === true
);

/**
 * @param list<array<string,mixed>> $lists
 * @param list<array<string,mixed>> $tasks
 * @param array<int,array<string,mixed>> $task_state_by_id
 * @return array<string,mixed>
 */
function mapper_build_task_organization(
    array $lists,
    array $tasks,
    array $task_state_by_id = [],
    string $now = '2026-06-04 12:00:00'
): array {
    $list_order = array_values(array_map(static function (array $list): int {
        return (int) ($list['id'] ?? 0);
    }, $lists));
    $task_order_by_list = [];

    foreach ($lists as $list) {
        $list_id = (int) ($list['id'] ?? 0);

        if ($list_id < 1) {
            continue;
        }

        $task_order_by_list[$list_id] = [];

        foreach ($tasks as $task) {
            if ((int) ($task['list_id'] ?? 0) === $list_id) {
                $task_order_by_list[$list_id][] = (int) ($task['id'] ?? 0);
            }
        }
    }

    $signal_evaluations = (new AA_Task_Signal_Policy())->evaluate_all([
        'tasks' => $tasks,
        'task_state_by_id' => $task_state_by_id,
        'now' => $now,
    ]);
    $projection = (new AA_Task_Active_View_Projection_Policy())->project([
        'lists' => $lists,
        'tasks' => $tasks,
        'list_order' => $list_order,
        'task_order_by_list' => $task_order_by_list,
        'task_evaluations_by_id' => $signal_evaluations,
        'now' => $now,
    ]);

    return [
        'list_order' => $list_order,
        'task_order_by_list' => $task_order_by_list,
        'task_bucket_order_by_list' => $projection['task_bucket_order_by_list'],
        'executive_candidates' => [],
        'task_evaluations_by_id' => $projection['task_evaluations_by_id'],
    ];
}

// ─── Contrato: lista vacía ─────────────────────────────────────

$empty_list = AA_Executable_Contract::normalize_list([
    'id' => 'test:empty',
    'source' => AA_Executable_Contract::SOURCE_USER,
    'title' => 'Vacía',
    'buckets' => [],
]);

ac_assert(
    'Contract normalizes empty list',
    $empty_list['id'] === 'test:empty'
    && $empty_list['source'] === AA_Executable_Contract::SOURCE_USER
    && $empty_list['buckets'] === []
    && $empty_list['capabilities']['can_archive'] === false
    && ($empty_list['capabilities']['can_edit'] ?? true) === false
    && ($empty_list['capabilities']['can_restore_archived_tasks'] ?? true) === false
);

ac_assert(
    'Contract empty list has required keys',
    AA_Executable_Contract::missing_list_keys($empty_list) === []
);

// ─── Contrato: claves obligatorias ───────────────────────────

$sample_list = AA_Executable_Contract::normalize_list([
    'id' => '1',
    'source' => AA_Executable_Contract::SOURCE_USER,
    'origin_key' => null,
    'title' => 'Lista',
    'description' => 'Objetivo',
    'importance' => 2,
    'position' => 1,
    'status' => AA_Executable_Contract::LIST_STATUS_ACTIVE,
    'capabilities' => ['can_archive' => true],
    'buckets' => [
        [
            'key' => AA_Executable_Contract::BUCKET_DEFAULT,
            'label' => 'Tareas',
            'items' => [
                [
                    'id' => '10',
                    'source' => AA_Executable_Contract::SOURCE_USER,
                    'origin_key' => null,
                    'title' => 'Tarea',
                    'description' => 'Notas',
                    'importance' => 3,
                    'due_at' => '2026-06-10 09:00:00',
                    'status' => AA_Executable_Contract::ITEM_STATUS_PENDING,
                    'state' => ['completed' => false],
                    'capabilities' => ['can_complete' => true],
                    'primary_action' => [
                        'type' => AA_Executable_Contract::ACTION_STATUS,
                        'label' => 'Completar',
                        'to' => AA_Executable_Contract::ITEM_STATUS_DONE,
                    ],
                    'is_executive_candidate' => true,
                ],
            ],
        ],
    ],
]);

ac_assert(
    'Contract list has required keys',
    AA_Executable_Contract::missing_list_keys($sample_list) === []
);

$sample_bucket = $sample_list['buckets'][0];
ac_assert(
    'Contract bucket has required keys',
    AA_Executable_Contract::missing_bucket_keys($sample_bucket) === []
);

$sample_item = $sample_bucket['items'][0];
ac_assert(
    'Contract item has required keys',
    AA_Executable_Contract::missing_item_keys($sample_item) === []
);
ac_assert(
    'Contract item carries source_category metadata',
    ($sample_item['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_USER
);

// ─── Contrato: labels canónicos de bucket (MC13O-0) ───────────

ac_assert(
    'Contract bucket_label primary is Principales',
    AA_Executable_Contract::bucket_label(AA_Executable_Contract::BUCKET_PRIMARY) === 'Principales'
);
ac_assert(
    'Contract bucket_label secondary is Secundarias',
    AA_Executable_Contract::bucket_label(AA_Executable_Contract::BUCKET_SECONDARY) === 'Secundarias'
);
ac_assert(
    'Contract bucket_label default is empty',
    AA_Executable_Contract::bucket_label(AA_Executable_Contract::BUCKET_DEFAULT) === ''
);

$canonical_primary_bucket = AA_Executable_Contract::normalize_bucket([
    'key' => AA_Executable_Contract::BUCKET_PRIMARY,
    'label' => '',
    'items' => [],
]);
ac_assert(
    'Contract normalize_bucket applies canonical primary label when empty',
    ($canonical_primary_bucket['key'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY
    && ($canonical_primary_bucket['label'] ?? '') === 'Principales'
);

// ─── Learning mapper ─────────────────────────────────────────

$learning_payload = [
    'list_1' => [
        [
            'key' => 'configure_services',
            'title' => 'Configura tus servicios',
            'description' => 'Define servicios.',
            'importance' => 0,
            'can_complete_manually' => false,
            'can_defer' => true,
            'can_dismiss' => false,
            'can_reactivate' => false,
            'is_completed' => false,
            'is_ignored' => false,
            'is_dismissed' => false,
            'is_dismiss_active' => false,
            'is_auto_completed' => false,
            'action' => [
                'type' => 'navigate',
                'label' => 'Ir',
                'url' => 'https://example.test/admin-post.php?module=assignments',
            ],
        ],
    ],
    'list_2' => [
        [
            'key' => 'install_pwa',
            'title' => 'Instala la app',
            'description' => 'PWA.',
            'importance' => 100,
            'can_complete_manually' => true,
            'can_defer' => false,
            'can_dismiss' => true,
            'can_reactivate' => true,
            'is_completed' => false,
            'is_ignored' => true,
            'is_dismissed' => true,
            'is_dismiss_active' => false,
            'is_auto_completed' => false,
            'action' => [
                'type' => 'handler',
                'label' => 'Instalar',
                'handler' => 'pwa.install',
            ],
        ],
    ],
];

$learning_list = LearningRecommendationsToExecutableMapper::map($learning_payload);

ac_assert(
    'Learning maps to system source list',
    ($learning_list['source'] ?? '') === AA_Executable_Contract::SOURCE_SYSTEM
    && ($learning_list['title'] ?? '') === 'Activación de tu agenda'
    && ($learning_list['origin_key'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ORIGIN_KEY
);

ac_assert(
    'Learning list carries agenda_app source metadata',
    ($learning_list['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP
    && ($learning_list['source_label'] ?? '') === 'Agenda app'
);
ac_assert(
    'Learning fallback list exposes can_edit and can_archive false',
    ($learning_list['capabilities']['can_edit'] ?? true) === false
    && ($learning_list['capabilities']['can_archive'] ?? true) === false
    && ($learning_list['capabilities']['can_restore_archived_tasks'] ?? true) === false
    && ($learning_list['capabilities']['can_delete'] ?? true) === false
);

$learning_bucket_keys = array_map(static function (array $bucket): string {
    return (string) ($bucket['key'] ?? '');
}, $learning_list['buckets'] ?? []);

ac_assert(
    'Learning fixture produces primary and secondary buckets',
    in_array(AA_Executable_Contract::BUCKET_PRIMARY, $learning_bucket_keys, true)
    && in_array(AA_Executable_Contract::BUCKET_SECONDARY, $learning_bucket_keys, true)
);

$learning_primary_bucket = null;
$learning_secondary_bucket = null;
$primary_item = null;
$secondary_item = null;

foreach ($learning_list['buckets'] as $bucket) {
    if (($bucket['key'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY) {
        $learning_primary_bucket = $bucket;
        $primary_item = $bucket['items'][0] ?? null;
    }

    if (($bucket['key'] ?? '') === AA_Executable_Contract::BUCKET_SECONDARY) {
        $learning_secondary_bucket = $bucket;
        $secondary_item = $bucket['items'][0] ?? null;
    }
}

ac_assert(
    'Learning primary bucket uses canonical label Principales',
    is_array($learning_primary_bucket)
    && ($learning_primary_bucket['label'] ?? '') === 'Principales'
);
ac_assert(
    'Learning secondary bucket uses canonical label Secundarias',
    is_array($learning_secondary_bucket)
    && ($learning_secondary_bucket['label'] ?? '') === 'Secundarias'
);

ac_assert(
    'Learning navigate item preserves primary_action navigate',
    is_array($primary_item)
    && ($primary_item['primary_action']['type'] ?? '') === AA_Executable_Contract::ACTION_NAVIGATE
    && ($primary_item['primary_action']['url'] ?? '') !== ''
    && ($primary_item['origin_key'] ?? '') === 'configure_services'
);

ac_assert(
    'Learning handler item preserves primary_action handler',
    is_array($secondary_item)
    && ($secondary_item['primary_action']['type'] ?? '') === AA_Executable_Contract::ACTION_HANDLER
    && ($secondary_item['primary_action']['handler'] ?? '') === 'pwa.install'
    && ($secondary_item['origin_key'] ?? '') === 'install_pwa'
);

ac_assert(
    'Learning fallback items expose can_edit false',
    is_array($primary_item)
    && ($primary_item['capabilities']['can_edit'] ?? true) === false
    && is_array($secondary_item)
    && ($secondary_item['capabilities']['can_edit'] ?? true) === false
);
ac_assert(
    'Learning fallback items expose can_archive and can_restore false',
    is_array($primary_item)
    && ($primary_item['capabilities']['can_archive'] ?? true) === false
    && ($primary_item['capabilities']['can_restore'] ?? true) === false
    && ($primary_item['capabilities']['can_delete'] ?? true) === false
    && is_array($secondary_item)
    && ($secondary_item['capabilities']['can_archive'] ?? true) === false
    && ($secondary_item['capabilities']['can_restore'] ?? true) === false
    && ($secondary_item['capabilities']['can_delete'] ?? true) === false
);
ac_assert(
    'Learning fallback items expose default_bucket from default_list',
    is_array($primary_item)
    && ($primary_item['default_bucket'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY
    && is_array($secondary_item)
    && ($secondary_item['default_bucket'] ?? '') === AA_Executable_Contract::BUCKET_SECONDARY
);
ac_assert(
    'Learning item preserves defer/dismiss/reactivate/complete capabilities',
    is_array($primary_item)
    && ($primary_item['capabilities']['can_defer'] ?? false) === true
    && ($primary_item['capabilities']['can_complete'] ?? false) === false
    && is_array($secondary_item)
    && ($secondary_item['capabilities']['can_dismiss'] ?? false) === true
    && ($secondary_item['capabilities']['can_reactivate'] ?? false) === true
    && ($secondary_item['capabilities']['can_complete'] ?? false) === true
);

ac_assert(
    'Learning items are not executive candidates in MC7',
    is_array($primary_item)
    && ($primary_item['is_executive_candidate'] ?? true) === false
);

// ─── Task board mapper ───────────────────────────────────────

$task_payload = [
    'lists' => [
        [
            'id' => 1,
            'title' => 'Clientes',
            'description' => 'Pendientes de clientes',
            'importance' => 0,
            'position' => 0,
            'status' => 'active',
        ],
        [
            'id' => 2,
            'title' => 'Operación',
            'description' => 'Día a día',
            'importance' => 5,
            'position' => 1,
            'status' => 'active',
        ],
    ],
    'tasks' => [
        [
            'id' => 10,
            'list_id' => 1,
            'title' => 'Llamar cliente',
            'notes' => 'Seguimiento de cotización',
            'status' => 'pending',
            'importance' => 2,
            'due_at' => '2026-06-08 10:00:00',
        ],
        [
            'id' => 11,
            'list_id' => 1,
            'title' => 'Enviar recordatorio',
            'notes' => 'WhatsApp',
            'status' => 'done',
            'importance' => 4,
            'due_at' => null,
        ],
        [
            'id' => 20,
            'list_id' => 2,
            'title' => 'Revisar agenda',
            'notes' => 'Mañana',
            'status' => 'pending',
            'importance' => 1,
            'due_at' => '2026-06-09 08:00:00',
            'default_bucket' => 'secondary',
        ],
    ],
    'organization' => [
        'list_order' => [1, 2],
        'task_order_by_list' => [
            1 => [10, 11],
            2 => [20],
        ],
        'executive_candidates' => [10, 20],
    ],
];

$task_lists = TaskBoardToExecutableMapper::map($task_payload);

ac_assert(
    'Task board produces one ExecutableList per user list',
    count($task_lists) === 2
    && ($task_lists[0]['source'] ?? '') === AA_Executable_Contract::SOURCE_USER
    && ($task_lists[0]['id'] ?? '') === '1'
    && ($task_lists[1]['id'] ?? '') === '2'
);

ac_assert(
    'User list carries user source metadata',
    ($task_lists[0]['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_USER
    && ($task_lists[0]['source_label'] ?? '') === 'Mis listas'
    && ($task_lists[1]['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_USER
    && ($task_lists[1]['source_label'] ?? '') === 'Mis listas'
);

$fallback_system_list = AA_Executable_Contract::normalize_list([
    'id' => 'system:test',
    'source' => AA_Executable_Contract::SOURCE_SYSTEM,
    'title' => 'Sin metadata explícita',
    'buckets' => [],
]);

ac_assert(
    'Contract falls back source_category and source_label from source',
    ($fallback_system_list['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP
    && ($fallback_system_list['source_label'] ?? '') === 'Agenda app'
);

ac_assert(
    'Contract normalized list includes source metadata keys',
    AA_Executable_Contract::missing_list_keys($fallback_system_list) === []
);

$first_list_bucket = $task_lists[0]['buckets'][0] ?? null;
$first_list_items = is_array($first_list_bucket) && is_array($first_list_bucket['items'] ?? null)
    ? $first_list_bucket['items']
    : [];
$pending_task = $first_list_items[0] ?? null;

ac_assert(
    'Task item preserves due_at notes as description and importance',
    is_array($pending_task)
    && ($pending_task['due_at'] ?? '') === '2026-06-08 10:00:00'
    && ($pending_task['description'] ?? '') === 'Seguimiento de cotización'
    && ($pending_task['importance'] ?? -1) === 2
);

ac_assert(
    'Task pending item has complete capability and status primary_action',
    is_array($pending_task)
    && ($pending_task['capabilities']['can_complete'] ?? false) === true
    && ($pending_task['primary_action']['type'] ?? '') === AA_Executable_Contract::ACTION_STATUS
    && ($pending_task['primary_action']['to'] ?? '') === AA_Executable_Contract::ITEM_STATUS_DONE
);

ac_assert(
    'Task pending without evaluation keeps can_defer/can_dismiss false',
    is_array($pending_task)
    && ($pending_task['capabilities']['can_defer'] ?? true) === false
    && ($pending_task['capabilities']['can_dismiss'] ?? true) === false
);
ac_assert(
    'User pending task exposes can_edit true',
    is_array($pending_task)
    && ($pending_task['capabilities']['can_edit'] ?? false) === true
);
ac_assert(
    'User pending task exposes can_archive true',
    is_array($pending_task)
    && ($pending_task['capabilities']['can_archive'] ?? false) === true
    && ($pending_task['capabilities']['can_restore'] ?? true) === false
);
ac_assert(
    'User pending task exposes can_delete true',
    is_array($pending_task)
    && ($pending_task['capabilities']['can_delete'] ?? false) === true
);
ac_assert(
    'User pending task exposes default_bucket primary when unset',
    is_array($pending_task)
    && ($pending_task['default_bucket'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY
);

ac_assert(
    'Task done item excluded from active default bucket',
    count($first_list_items) === 1
    && ($first_list_items[0]['id'] ?? '') === '10'
);

ac_assert(
    'Task order preserved for pending items only',
    is_array($pending_task)
    && ($pending_task['id'] ?? '') === '10'
);

$second_list_item = $task_lists[1]['buckets'][0]['items'][0] ?? null;

ac_assert(
    'Task executive_candidates mark is_executive_candidate',
    is_array($pending_task)
    && ($pending_task['is_executive_candidate'] ?? false) === true
    && is_array($second_list_item)
    && ($second_list_item['is_executive_candidate'] ?? false) === true
);
ac_assert(
    'User secondary task exposes default_bucket secondary',
    is_array($second_list_item)
    && ($second_list_item['default_bucket'] ?? '') === AA_Executable_Contract::BUCKET_SECONDARY
);

ac_assert(
    'Task user list exposes can_archive at list level',
    ($task_lists[0]['capabilities']['can_archive'] ?? false) === true
);
ac_assert(
    'Task user list exposes can_edit at list level',
    ($task_lists[0]['capabilities']['can_edit'] ?? false) === true
);
ac_assert(
    'Task user list exposes can_restore_archived_tasks at list level',
    ($task_lists[0]['capabilities']['can_restore_archived_tasks'] ?? false) === true
);
ac_assert(
    'Task user list exposes can_delete at list level',
    ($task_lists[0]['capabilities']['can_delete'] ?? false) === true
);

ac_assert(
    'Task list uses default bucket',
    is_array($first_list_bucket)
    && ($first_list_bucket['key'] ?? '') === AA_Executable_Contract::BUCKET_DEFAULT
);

$task_bucket_payload = [
    'lists' => [
        [
            'id' => 3,
            'title' => 'Ventas',
            'description' => 'Seguimientos',
            'importance' => 0,
            'position' => 0,
            'status' => 'active',
        ],
    ],
    'tasks' => [
        [
            'id' => 30,
            'list_id' => 3,
            'title' => 'Vencida',
            'notes' => 'Prioritaria por policy',
            'status' => 'pending',
            'importance' => 5,
            'due_at' => '2026-06-01 10:00:00',
        ],
        [
            'id' => 31,
            'list_id' => 3,
            'title' => 'Normal',
            'notes' => 'Pendiente normal',
            'status' => 'pending',
            'importance' => 2,
            'due_at' => null,
        ],
        [
            'id' => 32,
            'list_id' => 3,
            'title' => 'Done no active',
            'notes' => 'No debe aparecer',
            'status' => 'done',
            'importance' => -10,
            'due_at' => '2026-06-01 09:00:00',
        ],
    ],
    'organization' => [
        'list_order' => [3],
        'task_order_by_list' => [
            3 => [30, 31, 32],
        ],
        'task_bucket_order_by_list' => [
            3 => [
                'primary' => [30, 31],
                'secondary' => [],
            ],
        ],
        'executive_candidates' => [31],
    ],
];

$task_bucket_lists = TaskBoardToExecutableMapper::map($task_bucket_payload);
$task_bucket_list = $task_bucket_lists[0] ?? [];
$task_buckets = is_array($task_bucket_list) && is_array($task_bucket_list['buckets'] ?? null)
    ? $task_bucket_list['buckets']
    : [];
$task_bucket_keys = array_map(static function (array $bucket): string {
    return (string) ($bucket['key'] ?? '');
}, $task_buckets);
$primary_bucket = $task_buckets[0] ?? null;
$secondary_bucket = $task_buckets[1] ?? null;
$primary_bucket_items = is_array($primary_bucket) && is_array($primary_bucket['items'] ?? null)
    ? $primary_bucket['items']
    : [];
$secondary_bucket_items = is_array($secondary_bucket) && is_array($secondary_bucket['items'] ?? null)
    ? $secondary_bucket['items']
    : [];

ac_assert(
    'Task projected buckets map to primary label when secondary empty',
    $task_bucket_keys === [AA_Executable_Contract::BUCKET_PRIMARY]
    && ($primary_bucket['label'] ?? '') === 'Principales'
);
ac_assert(
    'Task secondary bucket uses canonical label Secundarias when present',
    ($secondary_bucket === null || ($secondary_bucket['label'] ?? '') === 'Secundarias')
);
ac_assert(
    'Task projected buckets preserve order and exclude done',
    array_map(static function (array $item): string {
        return (string) ($item['id'] ?? '');
    }, $primary_bucket_items) === ['30', '31']
    && $secondary_bucket_items === []
);
ac_assert(
    'Task projected buckets keep executive_candidates independent',
    ($primary_bucket_items[0]['is_executive_candidate'] ?? true) === false
    && ($primary_bucket_items[1]['is_executive_candidate'] ?? false) === true
);
ac_assert(
    'Task mapper marks past due pending as is_overdue',
    ($primary_bucket_items[0]['is_overdue'] ?? false) === true
);
ac_assert(
    'Task mapper marks item without due_at as not overdue',
    ($primary_bucket_items[1]['is_overdue'] ?? false) === false
);

$future_due_task_lists = TaskBoardToExecutableMapper::map([
    'lists' => [
        [
            'id' => 4,
            'title' => 'Futuras',
            'status' => 'active',
        ],
    ],
    'tasks' => [
        [
            'id' => 40,
            'list_id' => 4,
            'title' => 'Futura',
            'status' => 'pending',
            'due_at' => '2026-06-20 08:00:00',
        ],
    ],
    'organization' => [
        'list_order' => [4],
        'task_order_by_list' => [
            4 => [40],
        ],
        'task_bucket_order_by_list' => [
            4 => [
                'primary' => [40],
                'secondary' => [],
            ],
        ],
        'executive_candidates' => [],
    ],
]);
$future_due_item = $future_due_task_lists[0]['buckets'][0]['items'][0] ?? null;
ac_assert(
    'Task mapper marks future due pending as not overdue',
    is_array($future_due_item) && ($future_due_item['is_overdue'] ?? true) === false
);

$overdue_mapper_now = aa_get_current_datetime();
ac_assert(
    'Done task with past due_at is not overdue in domain',
    AA_Task::from_array([
        'id' => 32,
        'list_id' => 3,
        'title' => 'Done no active',
        'status' => 'done',
        'due_at' => '2026-06-01 10:00:00',
    ])->is_overdue($overdue_mapper_now) === false
);

$archived_feed_payload = [
    'lists' => [
        [
            'id' => 8,
            'title' => 'Archivadas feed',
            'status' => 'active',
        ],
    ],
    'tasks' => [
        [
            'id' => 80,
            'list_id' => 8,
            'title' => 'Activa',
            'status' => 'pending',
        ],
        [
            'id' => 81,
            'list_id' => 8,
            'title' => 'Archivada',
            'status' => 'pending',
            'archived_at' => '2026-06-10 10:00:00',
        ],
    ],
    'organization' => [
        'list_order' => [8],
        'task_order_by_list' => [
            8 => [80, 81],
        ],
        'task_bucket_order_by_list' => [
            8 => [
                'primary' => [80],
                'secondary' => [],
            ],
        ],
        'executive_candidates' => [80],
    ],
];
$archived_feed_lists = TaskBoardToExecutableMapper::map($archived_feed_payload);
$archived_feed_items = $archived_feed_lists[0]['buckets'][0]['items'] ?? [];
$archived_feed_ids = array_map(static function ($item) {
    return is_array($item) ? (string) ($item['id'] ?? '') : '';
}, is_array($archived_feed_items) ? $archived_feed_items : []);
ac_assert(
    'Projected feed excludes archived task from buckets',
    $archived_feed_ids === ['80']
);
ac_assert(
    'Projected feed excludes archived task from executive candidates',
    is_array($archived_feed_items[0] ?? null)
    && ($archived_feed_items[0]['is_executive_candidate'] ?? false) === true
);

$task_signal_payload = [
    'lists' => [
        [
            'id' => 4,
            'title' => 'Señales',
            'description' => null,
            'importance' => 0,
            'position' => 0,
            'status' => 'active',
        ],
    ],
    'tasks' => [
        [
            'id' => 40,
            'list_id' => 4,
            'title' => 'Secundaria con defer histórico',
            'notes' => 'Señal legacy registrada',
            'status' => 'pending',
            'default_bucket' => 'secondary',
            'importance' => 0,
            'due_at' => null,
        ],
    ],
    'organization' => [
        'list_order' => [4],
        'task_order_by_list' => [
            4 => [40],
        ],
        'task_bucket_order_by_list' => [
            4 => [
                'primary' => [],
                'secondary' => [40],
            ],
        ],
        'executive_candidates' => [40],
        'task_evaluations_by_id' => [
            40 => [
                'signals' => [
                    'has_defer' => true,
                    'has_dismiss' => false,
                    'defer_count' => 1,
                    'dismiss_count' => 0,
                ],
                'state' => [
                    'is_defer_active' => false,
                    'is_dismiss_active' => false,
                ],
                'capabilities' => [
                    'can_defer' => false,
                    'can_dismiss' => true,
                    'can_reactivate' => false,
                ],
                'visible_in_active' => true,
                'projection' => [
                    'view' => 'active',
                    'visible_in_active' => true,
                    'projected_bucket' => 'secondary',
                    'projection_reason' => 'default_secondary',
                    'suggested_active_bucket' => 'secondary',
                ],
            ],
        ],
    ],
];

$task_signal_lists = TaskBoardToExecutableMapper::map($task_signal_payload);
$task_signal_secondary_bucket = $task_signal_lists[0]['buckets'][0] ?? null;
ac_assert(
    'Task user secondary bucket uses canonical label Secundarias',
    is_array($task_signal_secondary_bucket)
    && ($task_signal_secondary_bucket['key'] ?? '') === AA_Executable_Contract::BUCKET_SECONDARY
    && ($task_signal_secondary_bucket['label'] ?? '') === 'Secundarias'
);
$task_signal_item = $task_signal_secondary_bucket['items'][0] ?? null;
ac_assert(
    'Task mapper does not map historical defer to state.ignored',
    is_array($task_signal_item)
    && ($task_signal_item['state']['ignored'] ?? true) === false
    && ($task_signal_item['state']['dismissed'] ?? true) === false
);
ac_assert(
    'Task mapper publishes filtered capabilities from default_bucket projection',
    is_array($task_signal_item)
    && ($task_signal_item['capabilities']['can_defer'] ?? true) === false
    && ($task_signal_item['capabilities']['can_dismiss'] ?? false) === true
);

$enriched_signal_lists = ExecutableVisibleActionsEnricher::enrich_lists($task_signal_lists, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
]);
$enriched_signal_item = $enriched_signal_lists[0]['buckets'][0]['items'][0] ?? null;
$enriched_action_keys = array_map(static function (array $action): string {
    return (string) ($action['key'] ?? '');
}, is_array($enriched_signal_item['visible_actions'] ?? null) ? $enriched_signal_item['visible_actions'] : []);
ac_assert(
    'Secondary task exposes complete and dismiss visible_actions without defer',
    $enriched_action_keys === ['complete', 'dismiss']
);
ac_assert(
    'Secondary task does not expose defer visible_action',
    !in_array('defer', $enriched_action_keys, true)
);

$legacy_defer_lists_data = [[
    'id' => 41,
    'title' => 'Legacy defer',
    'status' => 'active',
]];
$legacy_defer_tasks_data = [[
    'id' => 410,
    'list_id' => 41,
    'title' => 'Deferred audit only',
    'status' => 'pending',
    'default_bucket' => 'primary',
]];
$legacy_defer_organization = mapper_build_task_organization($legacy_defer_lists_data, $legacy_defer_tasks_data, [
    410 => [
        'task_id' => 410,
        'last_deferred_at' => '2026-06-04 10:00:00',
        'defer_count' => 1,
        'last_dismissed_at' => null,
        'dismiss_count' => 0,
        'defer_until' => null,
        'dismiss_until' => null,
    ],
]);
$legacy_defer_lists = TaskBoardToExecutableMapper::map([
    'lists' => $legacy_defer_lists_data,
    'tasks' => $legacy_defer_tasks_data,
    'organization' => $legacy_defer_organization,
]);
$legacy_defer_primary_item = $legacy_defer_lists[0]['buckets'][0]['items'][0] ?? null;
ac_assert(
    'Historical defer alone does not move task to secondary',
    ($legacy_defer_lists[0]['buckets'][0]['key'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY
    && is_array($legacy_defer_primary_item)
    && ($legacy_defer_primary_item['id'] ?? '') === '410'
    && ($legacy_defer_primary_item['state']['ignored'] ?? true) === false
);

// ─── Agenda app seeded desde DB común ─────────────────────────

$agenda_lists_data = [
    [
        'id' => 50,
        'title' => 'Activación de tu agenda',
        'description' => 'Sugerencias para configurar y usar tu agenda.',
        'owner_type' => 'developer',
        'source_category' => 'agenda_app',
        'origin_key' => 'learning.recommendations',
        'managed_by' => 'developer',
        'importance' => 0,
        'position' => 0,
        'status' => 'active',
    ],
];
$agenda_tasks_data = [
    [
        'id' => 500,
        'list_id' => 50,
        'title' => 'Completa los datos de tu negocio',
        'notes' => 'Añade el nombre y la dirección de tu negocio.',
        'status' => 'pending',
        'source' => 'system',
        'source_category' => 'agenda_app',
        'origin_key' => 'complete_business_data',
        'managed_by' => 'developer',
        'default_bucket' => 'primary',
        'completion_type' => 'system',
        'completion_fact_key' => 'business_data_complete',
        'importance' => -5,
        'due_at' => null,
    ],
    [
        'id' => 501,
        'list_id' => 50,
        'title' => 'Instala la app',
        'notes' => 'Añade DEOIA Citas a la pantalla de inicio.',
        'status' => 'pending',
        'source' => 'system',
        'source_category' => 'agenda_app',
        'origin_key' => 'install_pwa',
        'managed_by' => 'developer',
        'default_bucket' => 'secondary',
        'completion_type' => 'manual',
        'completion_fact_key' => null,
        'importance' => 100,
        'due_at' => null,
    ],
];
$agenda_organization = mapper_build_task_organization($agenda_lists_data, $agenda_tasks_data);
$agenda_organization['task_actions_by_id'] = [
    500 => [
        [
            'id' => 1,
            'task_id' => 500,
            'action_key' => 'navigate.settings.business_data',
            'type' => 'navigate',
            'label' => 'Ir',
            'placement' => 'primary',
            'category' => 'mechanical',
            'target_module' => 'settings',
            'target_setup_focus' => 'business_data',
            'target_fragment' => 'aa-business-data-root',
            'handler' => null,
            'enabled' => 1,
            'position' => 0,
        ],
    ],
    501 => [
        [
            'id' => 2,
            'task_id' => 501,
            'action_key' => 'pwa.install',
            'type' => 'handler',
            'label' => 'Instalar',
            'placement' => 'primary',
            'category' => 'mechanical',
            'target_module' => null,
            'target_setup_focus' => null,
            'target_fragment' => null,
            'handler' => 'pwa.install',
            'enabled' => 1,
            'position' => 0,
        ],
    ],
];
$agenda_seeded_payload = [
    'lists' => $agenda_lists_data,
    'tasks' => $agenda_tasks_data,
    'organization' => $agenda_organization,
];

$agenda_lists = TaskBoardToExecutableMapper::map($agenda_seeded_payload);
$agenda_list = $agenda_lists[0] ?? null;
$agenda_primary_item = $agenda_list['buckets'][0]['items'][0] ?? null;
$agenda_secondary_item = $agenda_list['buckets'][1]['items'][0] ?? null;

ac_assert(
    'Agenda app seeded list maps as system Agenda app',
    is_array($agenda_list)
    && ($agenda_list['source'] ?? '') === AA_Executable_Contract::SOURCE_SYSTEM
    && ($agenda_list['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP
    && ($agenda_list['source_label'] ?? '') === 'Agenda app'
    && ($agenda_list['origin_key'] ?? '') === 'learning.recommendations'
);
ac_assert(
    'Agenda app seeded list cannot archive',
    is_array($agenda_list)
    && ($agenda_list['capabilities']['can_archive'] ?? true) === false
);
ac_assert(
    'Agenda app seeded list cannot edit',
    is_array($agenda_list)
    && ($agenda_list['capabilities']['can_edit'] ?? true) === false
);
ac_assert(
    'Agenda app seeded list cannot restore archived tasks',
    is_array($agenda_list)
    && ($agenda_list['capabilities']['can_restore_archived_tasks'] ?? true) === false
);
ac_assert(
    'Agenda app seeded list cannot delete',
    is_array($agenda_list)
    && ($agenda_list['capabilities']['can_delete'] ?? true) === false
);
ac_assert(
    'Agenda app seeded tasks map source metadata',
    is_array($agenda_primary_item)
    && ($agenda_primary_item['source'] ?? '') === AA_Executable_Contract::SOURCE_SYSTEM
    && ($agenda_primary_item['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP
    && ($agenda_primary_item['origin_key'] ?? '') === 'complete_business_data'
);
ac_assert(
    'Agenda app seeded tasks cannot archive or restore',
    is_array($agenda_primary_item)
    && ($agenda_primary_item['capabilities']['can_archive'] ?? true) === false
    && ($agenda_primary_item['capabilities']['can_restore'] ?? true) === false
    && ($agenda_primary_item['capabilities']['can_delete'] ?? true) === false
    && is_array($agenda_secondary_item)
    && ($agenda_secondary_item['capabilities']['can_archive'] ?? true) === false
    && ($agenda_secondary_item['capabilities']['can_restore'] ?? true) === false
    && ($agenda_secondary_item['capabilities']['can_delete'] ?? true) === false
);
ac_assert(
    'Agenda app default_bucket controls projected bucket',
    ($agenda_list['buckets'][0]['key'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY
    && ($agenda_primary_item['id'] ?? '') === '500'
    && ($agenda_list['buckets'][1]['key'] ?? '') === AA_Executable_Contract::BUCKET_SECONDARY
    && is_array($agenda_secondary_item)
    && ($agenda_secondary_item['id'] ?? '') === '501'
);
ac_assert(
    'Agenda app navigate action maps to primary_action URL',
    is_array($agenda_primary_item)
    && ($agenda_primary_item['primary_action']['key'] ?? '') === 'navigate.settings.business_data'
    && ($agenda_primary_item['primary_action']['type'] ?? '') === AA_Executable_Contract::ACTION_NAVIGATE
    && strpos((string) ($agenda_primary_item['primary_action']['url'] ?? ''), 'module=settings') !== false
    && strpos((string) ($agenda_primary_item['primary_action']['url'] ?? ''), 'setup_focus=business_data') !== false
    && strpos((string) ($agenda_primary_item['primary_action']['url'] ?? ''), '#aa-business-data-root') !== false
);
ac_assert(
    'Agenda app handler action maps to primary_action handler',
    is_array($agenda_secondary_item)
    && ($agenda_secondary_item['primary_action']['key'] ?? '') === 'pwa.install'
    && ($agenda_secondary_item['primary_action']['type'] ?? '') === AA_Executable_Contract::ACTION_HANDLER
    && ($agenda_secondary_item['primary_action']['handler'] ?? '') === 'pwa.install'
);

$enriched_agenda_lists = ExecutableVisibleActionsEnricher::enrich_lists($agenda_lists, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
]);
$enriched_agenda_primary = $enriched_agenda_lists[0]['buckets'][0]['items'][0] ?? null;
$enriched_agenda_secondary = $enriched_agenda_lists[0]['buckets'][1]['items'][0] ?? null;
ac_assert(
    'Agenda app visible_actions preserve persisted action keys',
    ($enriched_agenda_primary['visible_actions'][0]['key'] ?? '') === 'navigate.settings.business_data'
    && ($enriched_agenda_secondary['visible_actions'][0]['key'] ?? '') === 'pwa.install'
);
ac_assert(
    'Agenda app completion_type=system disables manual complete',
    is_array($agenda_primary_item)
    && ($agenda_primary_item['capabilities']['can_complete'] ?? true) === false
    && ($agenda_primary_item['capabilities']['can_reopen'] ?? true) === false
);
ac_assert(
    'Agenda app completion_type=manual keeps manual complete when pending',
    is_array($agenda_secondary_item)
    && ($agenda_secondary_item['capabilities']['can_complete'] ?? false) === true
);

$push_origin_key = 'enable_push';
$push_tasks_data = [
    [
        'id' => 777,
        'list_id' => 50,
        'title' => 'Activa las notificaciones en este dispositivo',
        'notes' => 'Permite que DEOIA te avise en este dispositivo cuando una cita confirmada esté próxima, cuando una tarea llegue a su momento de realización y ante otros avisos importantes.',
        'status' => 'pending',
        'source' => 'system',
        'source_category' => 'agenda_app',
        'origin_key' => $push_origin_key,
        'managed_by' => 'developer',
        'default_bucket' => 'primary',
        'completion_type' => 'system',
        'completion_fact_key' => null,
        'importance' => 110,
        'due_at' => null,
    ],
];
$push_organization = mapper_build_task_organization($agenda_lists_data, $push_tasks_data);
$push_organization['task_actions_by_id'] = [
    777 => [
        [
            'id' => 9,
            'task_id' => 777,
            'action_key' => 'push.activate',
            'type' => 'handler',
            'label' => 'Activar notificaciones',
            'placement' => 'primary',
            'category' => 'mechanical',
            'handler' => 'push.activate',
            'enabled' => 1,
            'position' => 0,
        ],
    ],
];
$push_lists = TaskBoardToExecutableMapper::map([
    'lists' => $agenda_lists_data,
    'tasks' => $push_tasks_data,
    'organization' => $push_organization,
]);
$push_item = null;
foreach (($push_lists[0]['buckets'] ?? []) as $bucket) {
    foreach (($bucket['items'] ?? []) as $item) {
        if (($item['origin_key'] ?? '') === $push_origin_key) {
            $push_item = $item;
            break 2;
        }
    }
}
$enriched_push_lists = ExecutableVisibleActionsEnricher::enrich_lists($push_lists, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
]);
$enriched_push_item = null;
foreach (($enriched_push_lists[0]['buckets'] ?? []) as $bucket) {
    foreach (($bucket['items'] ?? []) as $item) {
        if (($item['origin_key'] ?? '') === $push_origin_key) {
            $enriched_push_item = $item;
            break 2;
        }
    }
}
ac_assert('Push activation task maps with enable_push origin', is_array($push_item));
ac_assert(
    'Push activation system/null disables manual complete and reopen',
    is_array($push_item)
    && ($push_item['capabilities']['can_complete'] ?? true) === false
    && ($push_item['capabilities']['can_reopen'] ?? true) === false
);
ac_assert(
    'Push activation visible_actions expose handler and hide generic complete',
    is_array($enriched_push_item)
    && in_array('push.activate', array_map(static function (array $action): string {
        return (string) ($action['handler'] ?? '');
    }, $enriched_push_item['visible_actions'] ?? []), true)
    && !in_array('complete', array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, $enriched_push_item['visible_actions'] ?? []), true)
    && !in_array('reopen', array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, $enriched_push_item['visible_actions'] ?? []), true)
);
ac_assert(
    'Agenda app developer tasks expose can_edit false',
    is_array($agenda_primary_item)
    && ($agenda_primary_item['capabilities']['can_edit'] ?? true) === false
    && is_array($agenda_secondary_item)
    && ($agenda_secondary_item['capabilities']['can_edit'] ?? true) === false
);
ac_assert(
    'Agenda app items may expose default_bucket without can_edit',
    is_array($agenda_primary_item)
    && ($agenda_primary_item['default_bucket'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY
    && is_array($agenda_secondary_item)
    && ($agenda_secondary_item['default_bucket'] ?? '') === AA_Executable_Contract::BUCKET_SECONDARY
    && ($agenda_primary_item['capabilities']['can_edit'] ?? true) === false
);
ac_assert(
    'Agenda app pending primary exposes can_dismiss capability',
    is_array($agenda_primary_item)
    && ($agenda_primary_item['capabilities']['can_dismiss'] ?? false) === true
);
ac_assert(
    'Agenda app default_bucket secondary without defer exposes can_dismiss',
    is_array($agenda_secondary_item)
    && ($agenda_secondary_item['capabilities']['can_dismiss'] ?? false) === true
);

$enriched_agenda_primary_keys = is_array($enriched_agenda_primary)
    ? array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, is_array($enriched_agenda_primary['visible_actions'] ?? null) ? $enriched_agenda_primary['visible_actions'] : [])
    : [];
$enriched_agenda_secondary_keys = is_array($enriched_agenda_secondary)
    ? array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, is_array($enriched_agenda_secondary['visible_actions'] ?? null) ? $enriched_agenda_secondary['visible_actions'] : [])
    : [];
ac_assert(
    'Agenda app primary navigate item includes dismiss visible_action',
    $enriched_agenda_primary_keys === ['navigate.settings.business_data', 'dismiss']
);
ac_assert(
    'Agenda app secondary default_bucket item includes install complete dismiss',
    $enriched_agenda_secondary_keys === ['pwa.install', 'complete', 'dismiss']
);

$system_completed_payload = $agenda_seeded_payload;
$system_completed_payload['organization']['task_evaluations_by_id'][500] = [
    'state' => ['is_system_completed' => true],
    'capabilities' => ['can_defer' => false, 'can_dismiss' => false],
];
$system_completed_lists = TaskBoardToExecutableMapper::map($system_completed_payload);
$system_completed_item = $system_completed_lists[0]['buckets'][0]['items'][0] ?? null;
ac_assert(
    'Mapper projects auto_completed when evaluation is_system_completed',
    is_array($system_completed_item)
    && ($system_completed_item['state']['auto_completed'] ?? false) === true
);

// ─── MC13O consolidation: can_archive oficial ────────────────

$archive_rules_lists_data = [
    [
        'id' => 70,
        'title' => 'User archivable',
        'status' => 'active',
        'source_category' => 'user',
        'managed_by' => 'user',
    ],
    [
        'id' => 71,
        'title' => 'Developer agenda',
        'status' => 'active',
        'owner_type' => 'developer',
        'source_category' => 'agenda_app',
        'origin_key' => 'learning.recommendations',
        'managed_by' => 'developer',
    ],
    [
        'id' => 72,
        'title' => 'Developer system',
        'status' => 'active',
        'owner_type' => 'developer',
        'source_category' => 'system',
        'origin_key' => 'other.system.list',
        'managed_by' => 'developer',
    ],
    [
        'id' => 73,
        'title' => 'User archived',
        'status' => 'archived',
        'source_category' => 'user',
        'managed_by' => 'user',
    ],
];
$archive_rules_tasks_data = [
    ['id' => 700, 'list_id' => 70, 'title' => 'Tarea user', 'status' => 'pending'],
    ['id' => 701, 'list_id' => 71, 'title' => 'Tarea agenda', 'status' => 'pending'],
    ['id' => 702, 'list_id' => 72, 'title' => 'Tarea system', 'status' => 'pending'],
];
$archive_rules_organization = mapper_build_task_organization(
    $archive_rules_lists_data,
    $archive_rules_tasks_data
);
$archive_rules_lists = TaskBoardToExecutableMapper::map([
    'lists' => $archive_rules_lists_data,
    'tasks' => $archive_rules_tasks_data,
    'organization' => $archive_rules_organization,
]);
$archive_rules_by_id = [];

foreach ($archive_rules_lists as $list) {
    if (!is_array($list)) {
        continue;
    }

    $archive_rules_by_id[(string) ($list['id'] ?? '')] = $list;
}

ac_assert(
    'can_archive true only for active user managed_by=user',
    ($archive_rules_by_id['70']['capabilities']['can_archive'] ?? false) === true
);
ac_assert(
    'can_edit true only for active user managed_by=user',
    ($archive_rules_by_id['70']['capabilities']['can_edit'] ?? false) === true
);
ac_assert(
    'can_archive false for agenda_app developer list',
    ($archive_rules_by_id['71']['capabilities']['can_archive'] ?? true) === false
);
ac_assert(
    'can_edit false for agenda_app developer list',
    ($archive_rules_by_id['71']['capabilities']['can_edit'] ?? true) === false
);
ac_assert(
    'can_archive false for generic developer system list',
    ($archive_rules_by_id['72']['capabilities']['can_archive'] ?? true) === false
);
ac_assert(
    'can_edit false for generic developer system list',
    ($archive_rules_by_id['72']['capabilities']['can_edit'] ?? true) === false
);
ac_assert(
    'can_archive false for archived user list',
    ($archive_rules_by_id['73']['capabilities']['can_archive'] ?? true) === false
);
ac_assert(
    'can_edit false for archived user list',
    ($archive_rules_by_id['73']['capabilities']['can_edit'] ?? true) === false
);
ac_assert(
    'can_restore_archived_tasks true only for active user managed_by=user',
    ($archive_rules_by_id['70']['capabilities']['can_restore_archived_tasks'] ?? false) === true
);
ac_assert(
    'can_restore_archived_tasks false for agenda_app developer list',
    ($archive_rules_by_id['71']['capabilities']['can_restore_archived_tasks'] ?? true) === false
);
ac_assert(
    'can_restore_archived_tasks false for generic developer system list',
    ($archive_rules_by_id['72']['capabilities']['can_restore_archived_tasks'] ?? true) === false
);
ac_assert(
    'can_restore_archived_tasks false for archived user list',
    ($archive_rules_by_id['73']['capabilities']['can_restore_archived_tasks'] ?? true) === false
);

// ─── Resumen ─────────────────────────────────────────────────

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
