<?php
/**
 * AC MC3 — RecordExecutiveActionUseCase.
 *
 * Ejecutar: php tests/application/executive/test-record-executive-action-use-case-ac.php
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
require_once __DIR__ . '/../../../includes/application/executive/RecordExecutiveActionUseCase.php';
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
function exec_action_visible_eval(string $bucket = 'primary', bool $can_dismiss = true): array {
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
function exec_action_ready_board(): array {
    return [
        'lists' => [
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
                'origin_key' => 'connect_calendar',
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
        ],
        'organization' => [
            'list_order' => [2],
            'task_evaluations_by_id' => [
                10 => exec_action_visible_eval('primary', true),
                11 => exec_action_visible_eval('primary', true),
                12 => exec_action_visible_eval('primary', false),
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

/**
 * @return array<string,mixed>
 */
function exec_action_user_complete_board(): array {
    return [
        'lists' => [
            [
                'id' => 1,
                'title' => 'Clientes',
                'importance' => 5,
                'status' => 'active',
                'source_category' => 'user',
            ],
        ],
        'tasks' => [
            [
                'id' => 20,
                'list_id' => 1,
                'title' => 'Llamar cliente',
                'status' => 'pending',
                'importance' => 50,
                'completion_type' => 'manual',
                'source_category' => 'user',
            ],
            [
                'id' => 21,
                'list_id' => 1,
                'title' => 'Seguimiento',
                'status' => 'pending',
                'importance' => 40,
                'completion_type' => 'manual',
                'source_category' => 'user',
            ],
        ],
        'organization' => [
            'list_order' => [1],
            'task_evaluations_by_id' => [
                20 => exec_action_visible_eval(),
                21 => exec_action_visible_eval(),
            ],
            'task_actions_by_id' => [],
        ],
    ];
}

/**
 * @param array<string,mixed> $board
 * @return callable():array<string,mixed>
 */
function exec_action_proposal_reader(array &$board): callable {
    return static function () use (&$board): array {
        return (new GetExecutiveProposalUseCase(static function () use (&$board): array {
            return $board;
        }))->execute();
    };
}

$board_complete = exec_action_user_complete_board();
$complete_uc = new RecordExecutiveActionUseCase(
    exec_action_proposal_reader($board_complete),
    static function (array $input) use (&$board_complete): array {
        foreach ($board_complete['tasks'] as $index => $task) {
            if ((int) ($task['id'] ?? 0) === (int) ($input['task_id'] ?? 0)) {
                $board_complete['tasks'][$index]['status'] = 'done';
                break;
            }
        }

        return TaskUseCaseSupport::ok(['task' => ['id' => (int) ($input['task_id'] ?? 0), 'status' => 'done']]);
    }
);

$complete_result = $complete_uc->execute([
    'task_id' => 20,
    'action_key' => 'complete',
]);

ac_assert('complete success', !empty($complete_result['success']));
ac_assert('complete marks mutated', ($complete_result['data']['action']['mutated'] ?? false) === true);
ac_assert(
    'complete advances current task id',
    is_array($complete_result['data']['proposal']['tasks'][0] ?? null)
    && (int) ($complete_result['data']['proposal']['tasks'][0]['task_id'] ?? 0) === 21
);

$board_dismiss = exec_action_ready_board();
$dismiss_uc = new RecordExecutiveActionUseCase(
    exec_action_proposal_reader($board_dismiss),
    null,
    static function (array $input) use (&$board_dismiss): array {
        $task_id = (int) ($input['task_id'] ?? 0);
        $board_dismiss['organization']['task_evaluations_by_id'][$task_id] = [
            'visible_in_active' => false,
            'projection' => [
                'visible_in_active' => false,
                'projected_bucket' => 'primary',
            ],
            'capabilities' => [
                'can_defer' => false,
                'can_dismiss' => false,
                'can_reactivate' => false,
            ],
        ];

        return TaskUseCaseSupport::ok(['task_state' => ['task_id' => $task_id]]);
    }
);

$dismiss_result = $dismiss_uc->execute([
    'task_id' => 10,
    'action_key' => 'dismiss',
]);

ac_assert('dismiss success', !empty($dismiss_result['success']));
ac_assert('dismiss marks mutated', ($dismiss_result['data']['action']['mutated'] ?? false) === true);
ac_assert(
    'dismiss advances current away from dismissed task',
    is_array($dismiss_result['data']['proposal']['tasks'][0] ?? null)
    && (int) ($dismiss_result['data']['proposal']['tasks'][0]['task_id'] ?? 0) === 11
);

$board_not_current = exec_action_ready_board();
$not_current = (new RecordExecutiveActionUseCase(
    exec_action_proposal_reader($board_not_current)
))->execute([
    'task_id' => 11,
    'action_key' => 'dismiss',
]);

ac_assert('next task rejected', ($not_current['error']['code'] ?? '') === 'task_not_current');

$board_invalid = exec_action_ready_board();
$invalid_action = (new RecordExecutiveActionUseCase(
    exec_action_proposal_reader($board_invalid)
))->execute([
    'task_id' => 10,
    'action_key' => 'complete',
]);

ac_assert('invented complete on system task rejected', ($invalid_action['error']['code'] ?? '') === 'action_not_allowed');

$empty_board = [
    'lists' => [],
    'tasks' => [],
    'organization' => ['list_order' => [], 'task_evaluations_by_id' => []],
];
$empty_result = (new RecordExecutiveActionUseCase(
    exec_action_proposal_reader($empty_board)
))->execute([
    'task_id' => 10,
    'action_key' => 'dismiss',
]);

ac_assert('empty proposal rejected', ($empty_result['error']['code'] ?? '') === 'proposal_empty');

$board_navigate = exec_action_ready_board();
$navigate_result = (new RecordExecutiveActionUseCase(
    exec_action_proposal_reader($board_navigate)
))->execute([
    'task_id' => 10,
    'action_key' => 'navigate.settings',
]);

ac_assert('navigate success', !empty($navigate_result['success']));
ac_assert(
    'navigate returns client_action',
    is_array($navigate_result['data']['client_action'] ?? null)
    && ($navigate_result['data']['client_action']['type'] ?? '') === 'navigate'
    && ($navigate_result['data']['client_action']['url'] ?? '') !== ''
);

$handler_board = exec_action_ready_board();
$handler_board['tasks'][0]['origin_key'] = 'install_pwa';
$handler_board['organization']['task_actions_by_id'][10] = [
    [
        'id' => 200,
        'action_key' => 'pwa.install',
        'type' => 'handler',
        'label' => 'Instalar',
        'enabled' => 1,
        'placement' => 'primary',
        'category' => 'mechanical',
        'handler' => 'pwa.install',
    ],
];

$handler_result = (new RecordExecutiveActionUseCase(
    exec_action_proposal_reader($handler_board)
))->execute([
    'task_id' => 10,
    'action_key' => 'pwa.install',
]);

ac_assert('handler success', !empty($handler_result['success']));
ac_assert(
    'handler returns client_action',
    is_array($handler_result['data']['client_action'] ?? null)
    && ($handler_result['data']['client_action']['type'] ?? '') === 'handler'
    && ($handler_result['data']['client_action']['handler'] ?? '') === 'pwa.install'
    && ($handler_result['data']['client_action']['origin_key'] ?? '') === 'install_pwa'
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
