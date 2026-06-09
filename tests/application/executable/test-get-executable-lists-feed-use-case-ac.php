<?php
/**
 * AC MC9A — GetExecutableListsFeedUseCase + ExecutableListsAjax wiring.
 *
 * Ejecutar: php tests/application/executable/test-get-executable-lists-feed-use-case-ac.php
 *
 * No requiere WordPress ni BD para la parte de ensamblado.
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

$plugin_root = dirname(__DIR__, 3);

require_once $plugin_root . '/includes/application/executable/GetExecutableListsFeedUseCase.php';
require_once $plugin_root . '/includes/application/executable/LearningRecommendationsToExecutableMapper.php';
require_once $plugin_root . '/includes/domain/executable/class-aa-executable-contract.php';
require_once $plugin_root . '/includes/domain/tasks/class-aa-task-prioritization-policy.php';
require_once $plugin_root . '/includes/domain/tasks/class-aa-task-signal-policy.php';
require_once $plugin_root . '/includes/domain/tasks/class-aa-task-active-view-projection-policy.php';

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
 * @return array{list_1:list<array<string,mixed>>,list_2:list<array<string,mixed>>,all_visible:list<array<string,mixed>>}
 */
function feed_fixture_learning_payload(): array {
    return [
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
        'list_2' => [],
        'all_visible' => [],
    ];
}

/**
 * @param list<array<string,mixed>> $lists
 * @param list<array<string,mixed>> $tasks
 * @param array<int,array<string,mixed>> $task_state_by_id
 * @return array<string,mixed>
 */
function feed_build_task_organization(
    array $lists,
    array $tasks,
    array $task_state_by_id = [],
    string $now = '2026-06-04 12:00:00'
): array {
    $base = (new AA_Task_Prioritization_Policy())->prioritize([
        'lists' => $lists,
        'tasks' => $tasks,
        'now' => $now,
    ]);
    $signal_evaluations = (new AA_Task_Signal_Policy())->evaluate_all([
        'tasks' => $tasks,
        'task_state_by_id' => $task_state_by_id,
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

    return [
        'list_order' => $base['list_order'],
        'task_order_by_list' => $base['task_order_by_list'],
        'task_bucket_order_by_list' => $projection['task_bucket_order_by_list'],
        'executive_candidates' => $base['executive_candidates'],
        'task_evaluations_by_id' => $projection['task_evaluations_by_id'],
    ];
}

/**
 * @return array{lists:list<array<string,mixed>>,tasks:list<array<string,mixed>>,organization:array<string,mixed>}
 */
function feed_fixture_tasks_payload(): array {
    $lists = [
        [
            'id' => 1,
            'title' => 'Clientes',
            'description' => 'Pendientes',
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
    ];
    $tasks = [
        [
            'id' => 10,
            'list_id' => 1,
            'title' => 'Llamar cliente',
            'notes' => 'Seguimiento',
            'status' => 'pending',
            'importance' => -1,
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
            'id' => 12,
            'list_id' => 1,
            'title' => 'Enviar propuesta',
            'notes' => 'Correo',
            'status' => 'pending',
            'importance' => 2,
            'due_at' => null,
        ],
        [
            'id' => 20,
            'list_id' => 2,
            'title' => 'Revisar agenda',
            'notes' => 'Mañana',
            'status' => 'pending',
            'importance' => 1,
            'due_at' => null,
        ],
    ];

    return [
        'lists' => $lists,
        'tasks' => $tasks,
        'organization' => feed_build_task_organization($lists, $tasks),
    ];
}

// ─── Estáticos: wiring AJAX ──────────────────────────────────

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ExecutableListsAjax.php');
ac_assert('ExecutableListsAjax file readable', $ajax_src !== false);
ac_assert('AJAX registers aa_get_executable_lists_feed', strpos($ajax_src, 'aa_get_executable_lists_feed') !== false);
ac_assert('AJAX uses manage_options', strpos($ajax_src, 'manage_options') !== false);
ac_assert('AJAX uses aa_executable_lists_nonce', strpos($ajax_src, 'aa_executable_lists_nonce') !== false);

$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert('Plugin bootstrap registers ExecutableListsAjax', strpos($bootstrap_src, 'ExecutableListsAjax::register()') !== false);

$use_case_src = file_get_contents($plugin_root . '/includes/application/executable/GetExecutableListsFeedUseCase.php');
ac_assert('Use case requires LearningRecommendationsToExecutableMapper', strpos($use_case_src, 'LearningRecommendationsToExecutableMapper.php') !== false);
ac_assert('Use case requires TaskBoardToExecutableMapper', strpos($use_case_src, 'TaskBoardToExecutableMapper.php') !== false);
ac_assert('Use case requires ExecutableVisibleActionsEnricher', strpos($use_case_src, 'ExecutableVisibleActionsEnricher.php') !== false);
ac_assert('Use case enriches lists with visible actions', strpos($use_case_src, 'ExecutableVisibleActionsEnricher::enrich_lists') !== false);
ac_assert('Use case lazy-loads GetLearningRecommendationsUseCase', strpos($use_case_src, 'GetLearningRecommendationsUseCase.php') !== false);
ac_assert('Use case lazy-loads GetTaskBoardUseCase', strpos($use_case_src, 'GetTaskBoardUseCase.php') !== false);
ac_assert(
    'Use case detects ready seeded Agenda app in task payload',
    strpos($use_case_src, 'payload_has_ready_seeded_agenda_app_list') !== false
    && strpos($use_case_src, 'payload_list_has_tasks') !== false
);

// ─── Ensamblado feliz ────────────────────────────────────────

$happy = (new GetExecutableListsFeedUseCase(
    static function (): array {
        return feed_fixture_learning_payload();
    },
    static function (): array {
        return feed_fixture_tasks_payload();
    }
))->execute();

ac_assert('Happy path success', !empty($happy['success']));
ac_assert('Happy path meta version 1', (int) ($happy['meta']['version'] ?? 0) === 1);
ac_assert(
    'Happy path learning source ok',
    ($happy['meta']['sources']['learning']['status'] ?? '') === 'ok'
    && (int) ($happy['meta']['sources']['learning']['list_count'] ?? 0) === 1
);
ac_assert(
    'Happy path tasks source ok',
    ($happy['meta']['sources']['tasks']['status'] ?? '') === 'ok'
    && (int) ($happy['meta']['sources']['tasks']['list_count'] ?? 0) === 2
);

$happy_lists = is_array($happy['lists'] ?? null) ? $happy['lists'] : [];
ac_assert('Happy path assembles system list first', count($happy_lists) >= 1);
ac_assert(
    'Happy path system list id',
    ($happy_lists[0]['id'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ID
    && ($happy_lists[0]['source'] ?? '') === AA_Executable_Contract::SOURCE_SYSTEM
);

ac_assert(
    'Happy path preserves source metadata after enricher',
    ($happy_lists[0]['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP
    && ($happy_lists[0]['source_label'] ?? '') === 'Agenda app'
    && ($happy_lists[1]['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_USER
    && ($happy_lists[1]['source_label'] ?? '') === 'Mis listas'
);
ac_assert(
    'Happy path user lists follow system list',
    count($happy_lists) === 3
    && ($happy_lists[1]['source'] ?? '') === AA_Executable_Contract::SOURCE_USER
    && ($happy_lists[2]['source'] ?? '') === AA_Executable_Contract::SOURCE_USER
);

$happy_order = is_array($happy['meta']['order'] ?? null) ? $happy['meta']['order'] : [];
$happy_ids = array_map(static function (array $list): string {
    return (string) ($list['id'] ?? '');
}, $happy_lists);
ac_assert('Happy path meta order matches list ids', $happy_order === $happy_ids);
ac_assert(
    'Happy path order starts with system list',
    ($happy_order[0] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ID
);

$first_user_list = is_array($happy_lists[1] ?? null) ? $happy_lists[1] : [];
$first_user_items = is_array($first_user_list['buckets'][0]['items'] ?? null)
    ? $first_user_list['buckets'][0]['items']
    : [];
$first_user_item_ids = array_map(static function (array $item): string {
    return (string) ($item['id'] ?? '');
}, $first_user_items);
ac_assert(
    'Happy path excludes done user tasks from active buckets',
    $first_user_item_ids === ['10', '12']
    && (int) ($happy['meta']['sources']['tasks']['item_count'] ?? -1) === 3
);

$first_user_secondary_items = is_array($first_user_list['buckets'][1]['items'] ?? null)
    ? $first_user_list['buckets'][1]['items']
    : [];
ac_assert(
    'Happy path pending user tasks without signals stay in primary bucket',
    ($first_user_list['buckets'][0]['key'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY
    && count($first_user_items) === 2
    && count($first_user_secondary_items) === 0
);

$system_list = is_array($happy_lists[0] ?? null) ? $happy_lists[0] : [];
$system_primary_item = null;

foreach ($system_list['buckets'] ?? [] as $bucket) {
    if (!is_array($bucket)) {
        continue;
    }

    if (($bucket['key'] ?? '') === AA_Executable_Contract::BUCKET_PRIMARY) {
        $system_primary_item = is_array($bucket['items'][0] ?? null) ? $bucket['items'][0] : null;
        break;
    }
}

$system_visible_keys = is_array($system_primary_item)
    ? array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, is_array($system_primary_item['visible_actions'] ?? null) ? $system_primary_item['visible_actions'] : [])
    : [];
$first_user_item = is_array($first_user_items[0] ?? null) ? $first_user_items[0] : null;
$user_visible_keys = is_array($first_user_item)
    ? array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, is_array($first_user_item['visible_actions'] ?? null) ? $first_user_item['visible_actions'] : [])
    : [];

ac_assert(
    'Happy path learning item includes visible_actions',
    $system_visible_keys === ['navigate', 'defer']
    && is_array($system_primary_item)
    && array_key_exists('visible_actions', $system_primary_item)
);
ac_assert(
    'Happy path user pending item includes visible_actions complete defer only',
    $user_visible_keys === ['complete', 'defer']
    && is_array($first_user_item)
    && ($first_user_item['primary_action']['type'] ?? '') === AA_Executable_Contract::ACTION_STATUS
    && ($first_user_item['capabilities']['can_defer'] ?? false) === true
    && ($first_user_item['capabilities']['can_dismiss'] ?? true) === false
);

$second_user_list = is_array($happy_lists[2] ?? null) ? $happy_lists[2] : [];
$second_user_items = is_array($second_user_list['buckets'][0]['items'] ?? null)
    ? $second_user_list['buckets'][0]['items']
    : [];
$second_user_item = is_array($second_user_items[0] ?? null) ? $second_user_items[0] : null;
$second_user_visible_keys = is_array($second_user_item)
    ? array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, is_array($second_user_item['visible_actions'] ?? null) ? $second_user_item['visible_actions'] : [])
    : [];
ac_assert(
    'Happy path second list pending user also exposes defer only in primary',
    $second_user_visible_keys === ['complete', 'defer']
);

$seeded_payload = feed_fixture_tasks_payload();
$seeded_payload['lists'][] = [
    'id' => 50,
    'title' => 'Recomendaciones',
    'description' => 'Sugerencias para configurar y usar tu agenda.',
    'owner_type' => 'developer',
    'source_category' => 'agenda_app',
    'origin_key' => 'learning.recommendations',
    'managed_by' => 'developer',
    'importance' => 0,
    'position' => 0,
    'status' => 'active',
];
$seeded_payload['tasks'][] = [
    'id' => 500,
    'list_id' => 50,
    'title' => 'Revisa tu agenda',
    'notes' => 'Consulta las citas de hoy.',
    'status' => 'pending',
    'source' => 'system',
    'source_category' => 'agenda_app',
    'origin_key' => 'review_agenda',
    'managed_by' => 'developer',
    'default_bucket' => 'secondary',
    'completion_type' => 'manual',
    'completion_fact_key' => null,
    'importance' => 120,
    'due_at' => null,
];
$seeded_payload['organization']['list_order'] = [1, 2, 50];
$seeded_payload['organization']['task_order_by_list'][50] = [500];
$seeded_payload['organization']['task_bucket_order_by_list'][50] = [
    'primary' => [500],
    'secondary' => [],
];
$seeded_payload['organization']['task_actions_by_id'][500] = [
    [
        'id' => 1,
        'task_id' => 500,
        'action_key' => 'navigate.calendar',
        'type' => 'navigate',
        'label' => 'Ir',
        'placement' => 'primary',
        'category' => 'mechanical',
        'target_module' => 'calendar',
        'target_setup_focus' => null,
        'target_fragment' => null,
        'handler' => null,
        'enabled' => 1,
        'position' => 0,
    ],
];

$learning_reader_called = false;
$seeded_feed = (new GetExecutableListsFeedUseCase(
    static function () use (&$learning_reader_called): array {
        $learning_reader_called = true;

        return feed_fixture_learning_payload();
    },
    static function () use ($seeded_payload): array {
        return $seeded_payload;
    }
))->execute();
$seeded_lists = is_array($seeded_feed['lists'] ?? null) ? $seeded_feed['lists'] : [];
$seeded_list_ids = array_map(static function (array $list): string {
    return (string) ($list['id'] ?? '');
}, $seeded_lists);
$seeded_recommendations_lists = array_values(array_filter(
    $seeded_lists,
    static function (array $list): bool {
        return ($list['origin_key'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ORIGIN_KEY;
    }
));
$seeded_db_list = is_array($seeded_recommendations_lists[0] ?? null)
    ? $seeded_recommendations_lists[0]
    : [];
$seeded_db_item = $seeded_db_list['buckets'][0]['items'][0] ?? null;
ac_assert(
    'Seeded Agenda app omits Learning legacy from feed',
    !$learning_reader_called
    && ($seeded_feed['meta']['sources']['learning']['status'] ?? '') === 'skipped'
    && ($seeded_feed['meta']['sources']['learning']['reason'] ?? '') === 'seeded_agenda_app_available'
    && count($seeded_lists) === 3
    && !in_array(LearningRecommendationsToExecutableMapper::LIST_ID, $seeded_list_ids, true)
);
ac_assert(
    'Seeded feed exposes single learning.recommendations list from DB',
    count($seeded_recommendations_lists) === 1
    && ($seeded_db_list['id'] ?? '') === '50'
    && ($seeded_db_list['source'] ?? '') === AA_Executable_Contract::SOURCE_SYSTEM
    && ($seeded_db_list['source_category'] ?? '') === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP
    && ($seeded_db_list['source_label'] ?? '') === 'Agenda app'
    && ($seeded_db_list['origin_key'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ORIGIN_KEY
);
ac_assert(
    'Feed seeded Agenda app carries persisted navigate action',
    is_array($seeded_db_item)
    && ($seeded_db_item['origin_key'] ?? '') === 'review_agenda'
    && ($seeded_db_item['visible_actions'][0]['key'] ?? '') === 'navigate.calendar'
);

$incomplete_seeded_payload = feed_fixture_tasks_payload();
$incomplete_seeded_payload['lists'][] = [
    'id' => 55,
    'title' => 'Recomendaciones',
    'description' => 'Sugerencias para configurar y usar tu agenda.',
    'owner_type' => 'developer',
    'source_category' => 'agenda_app',
    'origin_key' => 'learning.recommendations',
    'managed_by' => 'developer',
    'importance' => 0,
    'position' => 0,
    'status' => 'active',
];
$incomplete_seeded_payload['organization']['list_order'] = [1, 2, 55];
$incomplete_seeded_reader_called = false;
$incomplete_seeded_feed = (new GetExecutableListsFeedUseCase(
    static function () use (&$incomplete_seeded_reader_called): array {
        $incomplete_seeded_reader_called = true;

        return feed_fixture_learning_payload();
    },
    static function () use ($incomplete_seeded_payload): array {
        return $incomplete_seeded_payload;
    }
))->execute();
ac_assert(
    'Active seeded list without tasks keeps Learning legacy fallback',
    $incomplete_seeded_reader_called
    && ($incomplete_seeded_feed['meta']['sources']['learning']['status'] ?? '') === 'ok'
    && ($incomplete_seeded_feed['lists'][0]['id'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ID
);

$other_agenda_payload = feed_fixture_tasks_payload();
$other_agenda_payload['lists'][] = [
    'id' => 60,
    'title' => 'Otra lista sistema',
    'description' => 'No es learning.recommendations.',
    'owner_type' => 'developer',
    'source_category' => 'agenda_app',
    'origin_key' => 'other.system.list',
    'managed_by' => 'developer',
    'importance' => 0,
    'position' => 0,
    'status' => 'active',
];
$other_agenda_payload['organization']['list_order'] = [1, 2, 60];
$other_agenda_feed = (new GetExecutableListsFeedUseCase(
    static function (): array {
        return feed_fixture_learning_payload();
    },
    static function () use ($other_agenda_payload): array {
        return $other_agenda_payload;
    }
))->execute();
$other_agenda_lists = is_array($other_agenda_feed['lists'] ?? null) ? $other_agenda_feed['lists'] : [];
ac_assert(
    'Other agenda_app origin_key does not skip Learning legacy',
    ($other_agenda_feed['meta']['sources']['learning']['status'] ?? '') === 'ok'
    && ($other_agenda_lists[0]['id'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ID
    && count($other_agenda_lists) === 4
);

$archived_seeded_payload = $seeded_payload;
$archived_seeded_payload['lists'] = array_map(static function (array $list): array {
    if ((int) ($list['id'] ?? 0) === 50) {
        $list['status'] = 'archived';
    }

    return $list;
}, $archived_seeded_payload['lists']);
$archived_seeded_feed = (new GetExecutableListsFeedUseCase(
    static function (): array {
        return feed_fixture_learning_payload();
    },
    static function () use ($archived_seeded_payload): array {
        return $archived_seeded_payload;
    }
))->execute();
$archived_seeded_lists = is_array($archived_seeded_feed['lists'] ?? null) ? $archived_seeded_feed['lists'] : [];
ac_assert(
    'Archived seeded list keeps Learning legacy fallback',
    ($archived_seeded_feed['meta']['sources']['learning']['status'] ?? '') === 'ok'
    && ($archived_seeded_lists[0]['id'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ID
    && count($archived_seeded_lists) === 4
);

$defer_payload = feed_fixture_tasks_payload();
$defer_payload['tasks'] = array_values(array_filter(
    $defer_payload['tasks'],
    static function (array $task): bool {
        return (int) ($task['id'] ?? 0) !== 12;
    }
));
$defer_payload['tasks'][] = [
    'id' => 12,
    'list_id' => 1,
    'title' => 'Enviar propuesta',
    'notes' => 'Correo',
    'status' => 'pending',
    'importance' => 2,
    'due_at' => null,
];
$defer_payload['organization'] = feed_build_task_organization(
    $defer_payload['lists'],
    $defer_payload['tasks'],
    [
        12 => [
            'task_id' => 12,
            'last_deferred_at' => '2026-06-04 10:00:00',
            'defer_count' => 1,
            'last_dismissed_at' => null,
            'dismiss_count' => 0,
            'defer_until' => null,
            'dismiss_until' => null,
        ],
    ]
);
$defer_feed = (new GetExecutableListsFeedUseCase(
    static function (): array {
        return feed_fixture_learning_payload();
    },
    static function () use ($defer_payload): array {
        return $defer_payload;
    }
))->execute();
$defer_user_list = is_array($defer_feed['lists'][1] ?? null) ? $defer_feed['lists'][1] : [];
$defer_secondary_items = is_array($defer_user_list['buckets'][1]['items'] ?? null)
    ? $defer_user_list['buckets'][1]['items']
    : [];
$defer_secondary_item = is_array($defer_secondary_items[0] ?? null) ? $defer_secondary_items[0] : null;
$defer_secondary_keys = is_array($defer_secondary_item)
    ? array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, is_array($defer_secondary_item['visible_actions'] ?? null) ? $defer_secondary_item['visible_actions'] : [])
    : [];
ac_assert(
    'Deferred user task moves to secondary and exposes dismiss only',
    array_map(static function (array $item): string {
        return (string) ($item['id'] ?? '');
    }, $defer_secondary_items) === ['12']
    && $defer_secondary_keys === ['complete', 'dismiss']
);

$dismiss_payload = feed_fixture_tasks_payload();
$dismiss_payload['organization'] = feed_build_task_organization(
    $dismiss_payload['lists'],
    $dismiss_payload['tasks'],
    [
        10 => [
            'task_id' => 10,
            'last_deferred_at' => null,
            'defer_count' => 0,
            'last_dismissed_at' => '2026-06-04 11:00:00',
            'dismiss_count' => 1,
            'defer_until' => null,
            'dismiss_until' => null,
        ],
    ]
);
$dismiss_feed = (new GetExecutableListsFeedUseCase(
    static function (): array {
        return feed_fixture_learning_payload();
    },
    static function () use ($dismiss_payload): array {
        return $dismiss_payload;
    }
))->execute();
$dismiss_user_list = is_array($dismiss_feed['lists'][1] ?? null) ? $dismiss_feed['lists'][1] : [];
$dismiss_user_item_ids = [];
foreach ($dismiss_user_list['buckets'] ?? [] as $bucket) {
    if (!is_array($bucket)) {
        continue;
    }

    foreach ($bucket['items'] ?? [] as $item) {
        if (is_array($item)) {
            $dismiss_user_item_ids[] = (string) ($item['id'] ?? '');
        }
    }
}
ac_assert(
    'Dismissed user task disappears from active feed buckets',
    !in_array('10', $dismiss_user_item_ids, true)
    && in_array('12', $dismiss_user_item_ids, true)
);

// ─── Vacíos ──────────────────────────────────────────────────

$learning_empty = (new GetExecutableListsFeedUseCase(
    static function (): array {
        return ['list_1' => [], 'list_2' => [], 'all_visible' => []];
    },
    static function (): array {
        return feed_fixture_tasks_payload();
    }
))->execute();

ac_assert('Learning empty still success', !empty($learning_empty['success']));
ac_assert(
    'Learning empty keeps system list',
    ($learning_empty['lists'][0]['id'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ID
);
ac_assert(
    'Learning empty source ok with zero items',
    ($learning_empty['meta']['sources']['learning']['status'] ?? '') === 'ok'
    && (int) ($learning_empty['meta']['sources']['learning']['item_count'] ?? -1) === 0
);

$tasks_empty = (new GetExecutableListsFeedUseCase(
    static function (): array {
        return feed_fixture_learning_payload();
    },
    static function (): array {
        return ['lists' => [], 'tasks' => [], 'organization' => []];
    }
))->execute();

ac_assert('Tasks empty still success', !empty($tasks_empty['success']));
ac_assert(
    'Tasks empty yields zero user lists',
    count($tasks_empty['lists'] ?? []) === 1
    && ($tasks_empty['meta']['sources']['tasks']['list_count'] ?? -1) === 0
);

// ─── Errores parciales ───────────────────────────────────────

$learning_error = (new GetExecutableListsFeedUseCase(
    static function (): array {
        throw new RuntimeException('learning failed');
    },
    static function (): array {
        return feed_fixture_tasks_payload();
    }
))->execute();

ac_assert('Partial learning error still success', !empty($learning_error['success']));
ac_assert(
    'Partial learning error marks learning source error',
    ($learning_error['meta']['sources']['learning']['status'] ?? '') === 'error'
);
ac_assert(
    'Partial learning error keeps user lists',
    count($learning_error['lists'] ?? []) === 2
    && ($learning_error['lists'][0]['source'] ?? '') === AA_Executable_Contract::SOURCE_USER
);

$tasks_error = (new GetExecutableListsFeedUseCase(
    static function (): array {
        return feed_fixture_learning_payload();
    },
    static function (): array {
        throw new RuntimeException('tasks failed');
    }
))->execute();

ac_assert('Partial tasks error still success', !empty($tasks_error['success']));
ac_assert(
    'Partial tasks error marks tasks source error',
    ($tasks_error['meta']['sources']['tasks']['status'] ?? '') === 'error'
);
ac_assert(
    'Partial tasks error keeps system list',
    count($tasks_error['lists'] ?? []) === 1
    && ($tasks_error['lists'][0]['id'] ?? '') === LearningRecommendationsToExecutableMapper::LIST_ID
);

$both_error = (new GetExecutableListsFeedUseCase(
    static function (): array {
        throw new RuntimeException('learning failed');
    },
    static function (): array {
        throw new RuntimeException('tasks failed');
    }
))->execute();

ac_assert('Both sources fail returns controlled error', empty($both_error['success']));
ac_assert(
    'Both sources fail error code',
    ($both_error['error']['code'] ?? '') === 'feed_sources_unavailable'
);
ac_assert(
    'Both sources fail meta marks both errors',
    ($both_error['meta']['sources']['learning']['status'] ?? '') === 'error'
    && ($both_error['meta']['sources']['tasks']['status'] ?? '') === 'error'
);
ac_assert('Both sources fail returns empty lists', ($both_error['lists'] ?? null) === []);

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
