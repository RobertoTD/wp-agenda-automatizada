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

require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/application/executable/LearningRecommendationsToExecutableMapper.php';
require_once __DIR__ . '/../../../includes/application/executable/TaskBoardToExecutableMapper.php';
require_once __DIR__ . '/../../../includes/application/executable/ExecutableVisibleActionsEnricher.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-visible-actions-policy.php';

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
    && ($learning_list['title'] ?? '') === 'Recomendaciones'
    && ($learning_list['origin_key'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ORIGIN_KEY
);

$learning_bucket_keys = array_map(static function (array $bucket): string {
    return (string) ($bucket['key'] ?? '');
}, $learning_list['buckets'] ?? []);

ac_assert(
    'Learning fixture produces primary and secondary buckets',
    in_array(AA_Executable_Contract::BUCKET_PRIMARY, $learning_bucket_keys, true)
    && in_array(AA_Executable_Contract::BUCKET_SECONDARY, $learning_bucket_keys, true)
);

$primary_item = null;
$secondary_item = null;

foreach ($learning_list['buckets'] as $bucket) {
    if (($bucket['key'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY) {
        $primary_item = $bucket['items'][0] ?? null;
    }

    if (($bucket['key'] ?? '') === AA_Executable_Contract::BUCKET_SECONDARY) {
        $secondary_item = $bucket['items'][0] ?? null;
    }
}

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
    'Task user list exposes can_archive at list level',
    ($task_lists[0]['capabilities']['can_archive'] ?? false) === true
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
                'primary' => [30, 32],
                'secondary' => [31],
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
    'Task projected buckets map to primary and secondary labels',
    $task_bucket_keys === [AA_Executable_Contract::BUCKET_PRIMARY, AA_Executable_Contract::BUCKET_SECONDARY]
    && ($primary_bucket['label'] ?? '') === 'Prioritarias'
    && ($secondary_bucket['label'] ?? '') === 'Otras tareas'
);
ac_assert(
    'Task projected buckets preserve order and exclude done',
    array_map(static function (array $item): string {
        return (string) ($item['id'] ?? '');
    }, $primary_bucket_items) === ['30']
    && array_map(static function (array $item): string {
        return (string) ($item['id'] ?? '');
    }, $secondary_bucket_items) === ['31']
);
ac_assert(
    'Task projected buckets keep executive_candidates independent',
    ($primary_bucket_items[0]['is_executive_candidate'] ?? true) === false
    && ($secondary_bucket_items[0]['is_executive_candidate'] ?? false) === true
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
            'title' => 'Con defer',
            'notes' => 'Señal registrada',
            'status' => 'pending',
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
                    'can_defer' => true,
                    'can_dismiss' => true,
                    'can_reactivate' => false,
                ],
                'visible_in_active' => true,
            ],
        ],
    ],
];

$task_signal_lists = TaskBoardToExecutableMapper::map($task_signal_payload);
$task_signal_item = $task_signal_lists[0]['buckets'][0]['items'][0] ?? null;
ac_assert(
    'Task mapper reflects deferred signal in state.ignored',
    is_array($task_signal_item)
    && ($task_signal_item['state']['ignored'] ?? false) === true
    && ($task_signal_item['state']['dismissed'] ?? true) === false
);
ac_assert(
    'Task mapper keeps can_defer/can_dismiss false in feed',
    is_array($task_signal_item)
    && ($task_signal_item['capabilities']['can_defer'] ?? true) === false
    && ($task_signal_item['capabilities']['can_dismiss'] ?? true) === false
);

$enriched_signal_lists = ExecutableVisibleActionsEnricher::enrich_lists($task_signal_lists, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
]);
$enriched_signal_item = $enriched_signal_lists[0]['buckets'][0]['items'][0] ?? null;
$enriched_action_keys = array_map(static function (array $action): string {
    return (string) ($action['key'] ?? '');
}, is_array($enriched_signal_item['visible_actions'] ?? null) ? $enriched_signal_item['visible_actions'] : []);
ac_assert(
    'Task feed does not expose defer/dismiss visible_actions in MC13G-B',
    !in_array('defer', $enriched_action_keys, true)
    && !in_array('dismiss', $enriched_action_keys, true)
);
ac_assert(
    'Task feed still exposes complete visible_action',
    in_array('complete', $enriched_action_keys, true)
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
