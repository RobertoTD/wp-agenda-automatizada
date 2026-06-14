<?php
/**
 * AC MC1 — GetExecutiveProposalUseCase.
 *
 * Ejecutar: php tests/application/executive/test-get-executive-proposal-use-case-ac.php
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

if (!function_exists('current_time')) {
    function current_time($type) {
        return '2026-06-04 12:00:00';
    }
}

require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-contract.php';
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-proposal-policy.php';
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-actions-policy.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-visible-actions-policy.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-list.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-active-view-projection-policy.php';
require_once __DIR__ . '/../../../includes/application/executive/ExecutiveProposalMapper.php';
require_once __DIR__ . '/../../../includes/application/executive/GetExecutiveProposalUseCase.php';
require_once __DIR__ . '/../../../includes/application/executable/ExecutableNavigationUrlResolver.php';
require_once __DIR__ . '/../../../includes/application/tasks/TaskUseCaseSupport.php';

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
function exec_board_visible_eval(string $bucket = 'primary', bool $can_dismiss = true): array {
    return [
        'visible_in_active' => true,
        'projection' => [
            'visible_in_active' => true,
            'projected_bucket' => $bucket,
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
function exec_board_ready_fixture(): array {
    return [
        'lists' => [
            [
                'id' => 1,
                'title' => 'Clientes',
                'importance' => 5,
                'status' => 'active',
                'source_category' => 'user',
            ],
            [
                'id' => 2,
                'title' => 'Activación',
                'importance' => 20,
                'status' => 'active',
                'source_category' => 'agenda_app',
            ],
        ],
        'tasks' => [
            [
                'id' => 10,
                'list_id' => 2,
                'title' => 'Conecta calendario',
                'notes' => 'Sincroniza citas',
                'status' => 'pending',
                'importance' => 100,
                'due_at' => '2026-06-01 08:00:00',
                'completion_type' => 'system',
                'source_category' => 'agenda_app',
            ],
            [
                'id' => 11,
                'list_id' => 2,
                'title' => 'Completa negocio',
                'status' => 'pending',
                'importance' => 90,
                'source_category' => 'agenda_app',
            ],
            [
                'id' => 12,
                'list_id' => 2,
                'title' => 'Configura servicios',
                'status' => 'pending',
                'importance' => 80,
                'source_category' => 'agenda_app',
            ],
            [
                'id' => 13,
                'list_id' => 2,
                'title' => 'Extra cuarta',
                'status' => 'pending',
                'importance' => 70,
                'source_category' => 'agenda_app',
            ],
            [
                'id' => 20,
                'list_id' => 1,
                'title' => 'Llamar cliente',
                'status' => 'pending',
                'importance' => 50,
                'due_at' => null,
            ],
        ],
        'organization' => [
            'list_order' => [2, 1],
            'task_evaluations_by_id' => [
                10 => exec_board_visible_eval('primary', true),
                11 => exec_board_visible_eval('primary', true),
                12 => exec_board_visible_eval('primary', false),
                13 => exec_board_visible_eval('primary', false),
                20 => exec_board_visible_eval('primary', true),
            ],
            'task_actions_by_id' => [
                10 => [
                    [
                        'id' => 100,
                        'action_key' => 'navigate.settings',
                        'type' => 'navigate',
                        'label' => 'Ir',
                        'enabled' => 1,
                        'placement' => 'primary',
                        'category' => 'mechanical',
                        'target_module' => 'settings',
                        'target_setup_focus' => 'google_calendar',
                        'target_fragment' => null,
                    ],
                ],
            ],
        ],
    ];
}

$ready = (new GetExecutiveProposalUseCase(static function (): array {
    return exec_board_ready_fixture();
}))->execute();

ac_assert('Use case returns success', !empty($ready['success']));
ac_assert('Use case status ready', ($ready['status'] ?? '') === AA_Executive_Contract::STATUS_READY);
ac_assert('Focus list id matches highest-priority list with eligibles', (int) ($ready['focus_list']['id'] ?? 0) === 2);
ac_assert('Focus list preserves source_category', ($ready['focus_list']['source_category'] ?? '') === 'agenda_app');
ac_assert('Use case returns at most three tasks', count($ready['tasks'] ?? []) === 3);
ac_assert(
    'Top-3 task ids follow executive ordering in focus list',
    array_map(static function (array $task): int {
        return (int) ($task['task_id'] ?? 0);
    }, $ready['tasks'] ?? []) === [10, 11, 12]
);
ac_assert('Meta reports eligible count in focus list', (int) ($ready['meta']['eligible_count_in_focus_list'] ?? 0) === 4);

$current = $ready['tasks'][0] ?? null;
$next = $ready['tasks'][1] ?? null;
$third = $ready['tasks'][2] ?? null;

ac_assert('Current slot is current', is_array($current) && ($current['slot'] ?? '') === AA_Executive_Contract::SLOT_CURRENT);
ac_assert('Only current is actionable', is_array($current) && ($current['actionable'] ?? false) === true);
ac_assert('Next is not actionable', is_array($next) && ($next['actionable'] ?? true) === false);
ac_assert('Third is not actionable', is_array($third) && ($third['actionable'] ?? true) === false);
ac_assert('Next is continuation', is_array($next) && ($next['continuation'] ?? false) === true);
ac_assert('Third has empty executive_actions', is_array($third) && ($third['executive_actions'] ?? [1]) === []);
ac_assert('Next has empty executive_actions', is_array($next) && ($next['executive_actions'] ?? [1]) === []);
ac_assert(
    'Current has executive_actions',
    is_array($current)
    && is_array($current['executive_actions'] ?? null)
    && count($current['executive_actions']) >= 1
);
ac_assert(
    'Current executive_actions include navigate mechanical action',
    is_array($current)
    && in_array('navigate.settings', array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, $current['executive_actions'] ?? []), true)
);
ac_assert(
    'Current executive_actions include dismiss when allowed',
    is_array($current)
    && in_array('dismiss', array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, $current['executive_actions'] ?? []), true)
);
ac_assert(
    'Current executive_actions exclude complete for system completion type',
    is_array($current)
    && !in_array('complete', array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, $current['executive_actions'] ?? []), true)
);
ac_assert('Current is_overdue flag is informational', is_array($current) && ($current['is_overdue'] ?? false) === true);
ac_assert('Next is_overdue defaults false without due_at', is_array($next) && ($next['is_overdue'] ?? true) === false);

$due_order_board = [
    'lists' => [
        ['id' => 3, 'title' => 'Ops', 'importance' => 10, 'status' => 'active'],
    ],
    'tasks' => [
        [
            'id' => 30,
            'list_id' => 3,
            'title' => 'Overdue low importance',
            'status' => 'pending',
            'importance' => 5,
            'due_at' => '2026-06-01 08:00:00',
        ],
        [
            'id' => 31,
            'list_id' => 3,
            'title' => 'No due high importance',
            'status' => 'pending',
            'importance' => 50,
            'due_at' => null,
        ],
    ],
    'organization' => [
        'list_order' => [3],
        'task_evaluations_by_id' => [
            30 => exec_board_visible_eval(),
            31 => exec_board_visible_eval(),
        ],
        'task_actions_by_id' => [],
    ],
];

$due_order = (new GetExecutiveProposalUseCase(static function () use ($due_order_board): array {
    return $due_order_board;
}))->execute();

ac_assert(
    'Due_at does not decide executive order; importance DESC wins',
    (int) (($due_order['tasks'][0]['task_id'] ?? 0)) === 31
    && (int) (($due_order['tasks'][1]['task_id'] ?? 0)) === 30
);
ac_assert(
    'Overdue lower-importance task still exposes is_overdue flag',
    ($due_order['tasks'][1]['is_overdue'] ?? false) === true
);

$empty = (new GetExecutiveProposalUseCase(static function (): array {
    return [
        'lists' => [
            ['id' => 4, 'title' => 'Vacía', 'status' => 'active'],
        ],
        'tasks' => [
            ['id' => 40, 'list_id' => 4, 'title' => 'Dismissed', 'status' => 'pending'],
        ],
        'organization' => [
            'list_order' => [4],
            'task_evaluations_by_id' => [
                40 => [
                    'visible_in_active' => false,
                    'projection' => [
                        'visible_in_active' => false,
                        'projected_bucket' => null,
                    ],
                    'capabilities' => [
                        'can_dismiss' => false,
                    ],
                ],
            ],
            'task_actions_by_id' => [],
        ],
    ];
}))->execute();

ac_assert('Empty payload status', ($empty['status'] ?? '') === AA_Executive_Contract::STATUS_EMPTY);
ac_assert('Empty payload has no focus list', ($empty['focus_list'] ?? null) === null);
ac_assert('Empty payload has no tasks', ($empty['tasks'] ?? [1]) === []);
ac_assert('Empty payload reports empty_reason', ($empty['meta']['empty_reason'] ?? '') === AA_Executive_Contract::EMPTY_REASON_NO_ELIGIBLE_TASKS);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
