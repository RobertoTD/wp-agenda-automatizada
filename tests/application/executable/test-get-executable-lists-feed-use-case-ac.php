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

$plugin_root = dirname(__DIR__, 3);

require_once $plugin_root . '/includes/application/executable/GetExecutableListsFeedUseCase.php';
require_once $plugin_root . '/includes/application/executable/LearningRecommendationsToExecutableMapper.php';
require_once $plugin_root . '/includes/domain/executable/class-aa-executable-contract.php';

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
 * @return array{lists:list<array<string,mixed>>,tasks:list<array<string,mixed>>,organization:array<string,mixed>}
 */
function feed_fixture_tasks_payload(): array {
    return [
        'lists' => [
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
        ],
        'tasks' => [
            [
                'id' => 10,
                'list_id' => 1,
                'title' => 'Llamar cliente',
                'notes' => 'Seguimiento',
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
                'due_at' => null,
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
    'Happy path excludes done user tasks from active bucket',
    $first_user_item_ids === ['10']
    && (int) ($happy['meta']['sources']['tasks']['item_count'] ?? -1) === 2
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
    'Happy path user pending item includes visible_actions complete',
    $user_visible_keys === ['complete']
    && is_array($first_user_item)
    && ($first_user_item['primary_action']['type'] ?? '') === AA_Executable_Contract::ACTION_STATUS
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
