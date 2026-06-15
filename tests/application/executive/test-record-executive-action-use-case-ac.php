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
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-sprint-policy.php';
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-actions-policy.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-visible-actions-policy.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-list.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-active-view-projection-policy.php';
require_once __DIR__ . '/../../../includes/repositories/ExecutiveSprintStateRepository.php';
require_once __DIR__ . '/../../../includes/repositories/ExecutiveFocusStateRepository.php';
require_once __DIR__ . '/../../../includes/application/executive/ExecutiveProposalMapper.php';
require_once __DIR__ . '/../../../includes/application/executive/ExecutiveFocusTransitionService.php';
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

/**
 * @param array<int,array<string,mixed>> $storage
 * @return array{reader:callable,writer:callable,user_id_resolver:callable,now_ts_resolver:callable}
 */
function exec_action_sprint_deps(array &$storage, int $user_id = 7, ?int $now_ts = null): array {
    $resolved_now_ts = $now_ts ?? (int) strtotime('2026-06-04 12:00:00');

    return [
        'reader' => static function (int $uid) use (&$storage): array {
            return is_array($storage[$uid] ?? null) ? $storage[$uid] : [];
        },
        'writer' => static function (int $uid, array $state) use (&$storage): void {
            if ($state === []) {
                unset($storage[$uid]);

                return;
            }

            $storage[$uid] = $state;
        },
        'user_id_resolver' => static function () use ($user_id): int {
            return $user_id;
        },
        'now_ts_resolver' => static function () use ($resolved_now_ts): int {
            return $resolved_now_ts;
        },
    ];
}

/**
 * @param array<string,mixed> $board
 * @param array{reader:callable,writer:callable,user_id_resolver:callable,now_ts_resolver:callable} $deps
 */
function exec_action_sprint_uc(
    array &$board,
    array $deps,
    ?callable $change_status_executor = null,
    ?callable $dismiss_executor = null,
    ?array $focus_deps = null,
    ?callable $randomizer = null
): RecordExecutiveActionUseCase {
    $focus_reader = $focus_deps['reader'] ?? null;
    $focus_writer = $focus_deps['writer'] ?? null;

    return new RecordExecutiveActionUseCase(
        static function () use (&$board, $deps, $focus_reader, $focus_writer): array {
            return (new GetExecutiveProposalUseCase(
                static function () use (&$board): array {
                    return $board;
                },
                $deps['reader'],
                $deps['writer'],
                $deps['user_id_resolver'],
                $deps['now_ts_resolver'],
                $focus_reader,
                $focus_writer
            ))->execute();
        },
        $change_status_executor,
        $dismiss_executor,
        $deps['reader'],
        $deps['writer'],
        $deps['user_id_resolver'],
        $deps['now_ts_resolver'],
        $focus_reader,
        $focus_writer,
        static function () use (&$board): array {
            return $board;
        },
        $randomizer
    );
}

$action_now_ts = (int) strtotime('2026-06-04 12:00:00');
$action_user_id = 7;

// ─── MC4 sprint writes ───────────────────────────────────────

$start_storage = [];
$start_deps = exec_action_sprint_deps($start_storage, $action_user_id, $action_now_ts);
$start_board = exec_action_user_complete_board();
$start_uc = exec_action_sprint_uc(
    $start_board,
    $start_deps,
    static function (array $input) use (&$start_board): array {
        foreach ($start_board['tasks'] as $index => $task) {
            if ((int) ($task['id'] ?? 0) === (int) ($input['task_id'] ?? 0)) {
                $start_board['tasks'][$index]['status'] = 'done';
                break;
            }
        }

        return TaskUseCaseSupport::ok(['task' => ['id' => (int) ($input['task_id'] ?? 0), 'status' => 'done']]);
    }
);

$start_result = $start_uc->execute(['task_id' => 20, 'action_key' => 'complete']);
ac_assert('complete inicia sprint', isset($start_storage[$action_user_id]));
ac_assert(
    'complete inicia sprint con lista foco actual',
    (int) ($start_storage[$action_user_id]['active_focus_list_id'] ?? 0) === 1
);
ac_assert(
    'complete inicia sprint con expires_at',
    (int) ($start_storage[$action_user_id]['sprint_expires_at'] ?? 0) === $action_now_ts + 3600
);
ac_assert(
    'complete propuesta incluye meta sprint activo',
    is_array($start_result['data']['proposal']['meta']['sprint'] ?? null)
    && ($start_result['data']['proposal']['meta']['sprint']['sprint_active'] ?? false) === true
);

$renew_storage = [];
$renew_storage[$action_user_id] = [
    'version' => 1,
    'active_focus_list_id' => 2,
    'sprint_started_at' => $action_now_ts - 2000,
    'last_executive_action_at' => $action_now_ts - 2000,
    'sprint_expires_at' => $action_now_ts + 1000,
];
$renew_deps = exec_action_sprint_deps($renew_storage, $action_user_id, $action_now_ts);
$renew_board = exec_action_ready_board();
$renew_uc = exec_action_sprint_uc($renew_board, $renew_deps);

$renew_result = $renew_uc->execute(['task_id' => 10, 'action_key' => 'navigate.settings']);
ac_assert('navigate renueva sprint aunque no mute tarea', !empty($renew_result['success']));
ac_assert(
    'navigate actualiza last_executive_action_at',
    (int) ($renew_storage[$action_user_id]['last_executive_action_at'] ?? 0) === $action_now_ts
);
ac_assert(
    'navigate mantiene sprint_started_at',
    (int) ($renew_storage[$action_user_id]['sprint_started_at'] ?? 0) === $action_now_ts - 2000
);
ac_assert(
    'navigate extiende sprint_expires_at',
    (int) ($renew_storage[$action_user_id]['sprint_expires_at'] ?? 0) === $action_now_ts + 3600
);

$expired_renew_storage = [];
$expired_renew_storage[$action_user_id] = [
    'version' => 1,
    'active_focus_list_id' => 1,
    'sprint_started_at' => $action_now_ts - 7200,
    'last_executive_action_at' => $action_now_ts - 7200,
    'sprint_expires_at' => $action_now_ts - 60,
];
$expired_renew_deps = exec_action_sprint_deps($expired_renew_storage, $action_user_id, $action_now_ts);
$expired_renew_board = exec_action_user_complete_board();
$expired_renew_uc = exec_action_sprint_uc(
    $expired_renew_board,
    $expired_renew_deps,
    static function (array $input) use (&$expired_renew_board): array {
        foreach ($expired_renew_board['tasks'] as $index => $task) {
            if ((int) ($task['id'] ?? 0) === (int) ($input['task_id'] ?? 0)) {
                $expired_renew_board['tasks'][$index]['status'] = 'done';
                break;
            }
        }

        return TaskUseCaseSupport::ok(['task' => ['id' => (int) ($input['task_id'] ?? 0), 'status' => 'done']]);
    }
);

$expired_renew_result = $expired_renew_uc->execute(['task_id' => 20, 'action_key' => 'complete']);
ac_assert('complete con sprint vencido renueva', !empty($expired_renew_result['success']));
ac_assert(
    'complete vencido reinicia expires_at',
    (int) ($expired_renew_storage[$action_user_id]['sprint_expires_at'] ?? 0) === $action_now_ts + 3600
);

$active_dismiss_storage = [];
$active_dismiss_storage[$action_user_id] = [
    'version' => 1,
    'active_focus_list_id' => 2,
    'sprint_started_at' => $action_now_ts - 300,
    'last_executive_action_at' => $action_now_ts - 300,
    'sprint_expires_at' => $action_now_ts + 3300,
];
$active_dismiss_before = $active_dismiss_storage[$action_user_id];
$active_dismiss_deps = exec_action_sprint_deps($active_dismiss_storage, $action_user_id, $action_now_ts);
$active_dismiss_board = exec_action_ready_board();
$active_dismiss_uc = exec_action_sprint_uc(
    $active_dismiss_board,
    $active_dismiss_deps,
    null,
    static function (array $input) use (&$active_dismiss_board): array {
        $task_id = (int) ($input['task_id'] ?? 0);
        $active_dismiss_board['organization']['task_evaluations_by_id'][$task_id] = [
            'visible_in_active' => false,
            'projection' => ['visible_in_active' => false, 'projected_bucket' => 'primary'],
            'capabilities' => ['can_dismiss' => false],
        ];

        return TaskUseCaseSupport::ok(['task_state' => ['task_id' => $task_id]]);
    }
);

$active_dismiss_result = $active_dismiss_uc->execute(['task_id' => 10, 'action_key' => 'dismiss']);
ac_assert('dismiss sprint activo no renueva', !empty($active_dismiss_result['success']));
ac_assert(
    'dismiss sprint activo mantiene sprint meta',
    $active_dismiss_storage[$action_user_id] === $active_dismiss_before
);

$expired_dismiss_storage = [];
$expired_dismiss_storage[$action_user_id] = [
    'version' => 1,
    'active_focus_list_id' => 2,
    'sprint_started_at' => $action_now_ts - 5000,
    'last_executive_action_at' => $action_now_ts - 5000,
    'sprint_expires_at' => $action_now_ts - 100,
];
$expired_dismiss_deps = exec_action_sprint_deps($expired_dismiss_storage, $action_user_id, $action_now_ts);
$expired_dismiss_board = exec_action_ready_board();
$expired_dismiss_uc = exec_action_sprint_uc(
    $expired_dismiss_board,
    $expired_dismiss_deps,
    null,
    static function (array $input) use (&$expired_dismiss_board): array {
        $task_id = (int) ($input['task_id'] ?? 0);
        $expired_dismiss_board['organization']['task_evaluations_by_id'][$task_id] = [
            'visible_in_active' => false,
            'projection' => ['visible_in_active' => false, 'projected_bucket' => 'primary'],
            'capabilities' => ['can_dismiss' => false],
        ];

        return TaskUseCaseSupport::ok(['task_state' => ['task_id' => $task_id]]);
    }
);

$expired_dismiss_result = $expired_dismiss_uc->execute(['task_id' => 10, 'action_key' => 'dismiss']);
ac_assert('dismiss sprint vencido limpia estado', !empty($expired_dismiss_result['success']));
ac_assert('dismiss sprint vencido no deja storage', !isset($expired_dismiss_storage[$action_user_id]));

$shift_storage = [];
$shift_storage[$action_user_id] = [
    'version' => 1,
    'active_focus_list_id' => 2,
    'sprint_started_at' => $action_now_ts - 400,
    'last_executive_action_at' => $action_now_ts - 400,
    'sprint_expires_at' => $action_now_ts + 3200,
];
$shift_expires = $shift_storage[$action_user_id]['sprint_expires_at'];
$shift_deps = exec_action_sprint_deps($shift_storage, $action_user_id, $action_now_ts);
$shift_board = [
    'lists' => [
        ['id' => 2, 'title' => 'Agotandose', 'status' => 'active', 'source_category' => 'agenda_app'],
        ['id' => 1, 'title' => 'Siguiente', 'status' => 'active', 'source_category' => 'user'],
    ],
    'tasks' => [
        ['id' => 10, 'list_id' => 2, 'title' => 'Ultima en 2', 'status' => 'pending', 'importance' => 100],
        ['id' => 20, 'list_id' => 1, 'title' => 'En lista 1', 'status' => 'pending', 'importance' => 50, 'completion_type' => 'manual'],
    ],
    'organization' => [
        'list_order' => [2, 1],
        'task_evaluations_by_id' => [
            10 => exec_action_visible_eval('primary', true),
            20 => exec_action_visible_eval('primary', true),
        ],
        'task_actions_by_id' => [],
    ],
];
$shift_uc = exec_action_sprint_uc(
    $shift_board,
    $shift_deps,
    null,
    static function (array $input) use (&$shift_board): array {
        $task_id = (int) ($input['task_id'] ?? 0);
        $shift_board['organization']['task_evaluations_by_id'][$task_id] = [
            'visible_in_active' => false,
            'projection' => ['visible_in_active' => false, 'projected_bucket' => 'primary'],
            'capabilities' => ['can_dismiss' => false],
        ];

        return TaskUseCaseSupport::ok(['task_state' => ['task_id' => $task_id]]);
    }
);

$shift_result = $shift_uc->execute(['task_id' => 10, 'action_key' => 'dismiss']);
ac_assert('dismiss agota lista y avanza sin extender TTL', !empty($shift_result['success']));
ac_assert(
    'dismiss agotado actualiza active_focus_list_id',
    (int) ($shift_storage[$action_user_id]['active_focus_list_id'] ?? 0) === 1
);
ac_assert(
    'dismiss agotado mantiene sprint_expires_at',
    (int) ($shift_storage[$action_user_id]['sprint_expires_at'] ?? 0) === $shift_expires
);
ac_assert(
    'dismiss agotado propone tarea de nueva lista',
    (int) ($shift_result['data']['proposal']['focus_list']['id'] ?? 0) === 1
    && (int) ($shift_result['data']['proposal']['tasks'][0]['task_id'] ?? 0) === 20
);

// ─── MC5 dismiss streak fuera de sprint ─────────────────────

$streak_board = [
    'lists' => [
        ['id' => 2, 'title' => 'Agotandose', 'status' => 'active', 'source_category' => 'agenda_app'],
        ['id' => 1, 'title' => 'Siguiente', 'status' => 'active', 'source_category' => 'user'],
    ],
    'tasks' => [
        ['id' => 10, 'list_id' => 2, 'title' => 'A', 'status' => 'pending', 'importance' => 100],
        ['id' => 11, 'list_id' => 2, 'title' => 'B', 'status' => 'pending', 'importance' => 90],
        ['id' => 20, 'list_id' => 1, 'title' => 'C', 'status' => 'pending', 'importance' => 50, 'completion_type' => 'manual'],
    ],
    'organization' => [
        'list_order' => [2, 1],
        'task_evaluations_by_id' => [
            10 => exec_action_visible_eval('primary', true),
            11 => exec_action_visible_eval('primary', true),
            20 => exec_action_visible_eval('primary', true),
        ],
        'task_actions_by_id' => [],
    ],
];

$dismiss_executor = static function (array $input) use (&$streak_board): array {
    $task_id = (int) ($input['task_id'] ?? 0);
    $streak_board['organization']['task_evaluations_by_id'][$task_id] = [
        'visible_in_active' => false,
        'projection' => ['visible_in_active' => false, 'projected_bucket' => 'primary'],
        'capabilities' => ['can_dismiss' => false],
    ];

    return TaskUseCaseSupport::ok(['task_state' => ['task_id' => $task_id]]);
};

$streak_sprint_storage = [];
$streak_focus_storage = [];
$streak_deps = exec_action_sprint_deps($streak_sprint_storage, $action_user_id, $action_now_ts);
$streak_focus_deps = exec_action_sprint_deps($streak_focus_storage, $action_user_id, $action_now_ts);
$streak_uc = exec_action_sprint_uc(
    $streak_board,
    $streak_deps,
    null,
    $dismiss_executor,
    $streak_focus_deps,
    static function (): int {
        return 1;
    }
);

$streak_uc->execute(['task_id' => 10, 'action_key' => 'dismiss']);
ac_assert(
    'dismiss fuera de sprint incrementa streak a 1',
    (int) ($streak_focus_storage[$action_user_id]['dismiss_streak_without_sprint'] ?? 0) === 1
);

$streak_uc->execute(['task_id' => 11, 'action_key' => 'dismiss']);
ac_assert(
    'segundo dismiss incrementa streak a 2',
    (int) ($streak_focus_storage[$action_user_id]['dismiss_streak_without_sprint'] ?? 0) === 2
);

$third_dismiss = $streak_uc->execute(['task_id' => 20, 'action_key' => 'dismiss']);
ac_assert('tercer dismiss success', !empty($third_dismiss['success']));
ac_assert(
    'tercer dismiss resetea streak',
    (int) ($streak_focus_storage[$action_user_id]['dismiss_streak_without_sprint'] ?? -1) === 0
);
ac_assert(
    'tercer dismiss con una sola lista elegible puede quedarse en la misma',
    (int) ($streak_focus_storage[$action_user_id]['manual_focus_list_id'] ?? 0) === 1
);
ac_assert('tercer dismiss no inicia sprint', !isset($streak_sprint_storage[$action_user_id]));

$alt_streak_board = [
    'lists' => [
        ['id' => 2, 'title' => 'Lista A', 'status' => 'active', 'source_category' => 'agenda_app'],
        ['id' => 1, 'title' => 'Lista B', 'status' => 'active', 'source_category' => 'user'],
    ],
    'tasks' => [
        ['id' => 10, 'list_id' => 2, 'title' => 'A1', 'status' => 'pending', 'importance' => 100],
        ['id' => 11, 'list_id' => 2, 'title' => 'A2', 'status' => 'pending', 'importance' => 90],
        ['id' => 12, 'list_id' => 2, 'title' => 'A3', 'status' => 'pending', 'importance' => 80],
        ['id' => 20, 'list_id' => 1, 'title' => 'B1', 'status' => 'pending', 'importance' => 50, 'completion_type' => 'manual'],
    ],
    'organization' => [
        'list_order' => [2, 1],
        'task_evaluations_by_id' => [
            10 => exec_action_visible_eval('primary', true),
            11 => exec_action_visible_eval('primary', true),
            12 => exec_action_visible_eval('primary', true),
            20 => exec_action_visible_eval('primary', true),
        ],
        'task_actions_by_id' => [],
    ],
];
$alt_dismiss_executor = static function (array $input) use (&$alt_streak_board): array {
    $task_id = (int) ($input['task_id'] ?? 0);
    $alt_streak_board['organization']['task_evaluations_by_id'][$task_id] = [
        'visible_in_active' => false,
        'projection' => ['visible_in_active' => false, 'projected_bucket' => 'primary'],
        'capabilities' => ['can_dismiss' => false],
    ];

    return TaskUseCaseSupport::ok(['task_state' => ['task_id' => $task_id]]);
};
$alt_streak_sprint_storage = [];
$alt_streak_focus_storage = [];
$alt_streak_deps = exec_action_sprint_deps($alt_streak_sprint_storage, $action_user_id, $action_now_ts);
$alt_streak_focus_deps = exec_action_sprint_deps($alt_streak_focus_storage, $action_user_id, $action_now_ts);
$alt_streak_uc = exec_action_sprint_uc(
    $alt_streak_board,
    $alt_streak_deps,
    null,
    $alt_dismiss_executor,
    $alt_streak_focus_deps,
    static function (): int {
        return 0;
    }
);

$alt_streak_uc->execute(['task_id' => 10, 'action_key' => 'dismiss']);
$alt_streak_uc->execute(['task_id' => 11, 'action_key' => 'dismiss']);
$alt_third = $alt_streak_uc->execute(['task_id' => 12, 'action_key' => 'dismiss']);
ac_assert('tercer dismiss con alternativa success', !empty($alt_third['success']));
ac_assert(
    'tercer dismiss cambia a lista distinta si hay alternativa',
    (int) ($alt_streak_focus_storage[$action_user_id]['manual_focus_list_id'] ?? 0) === 1
);
ac_assert(
    'tercer dismiss alternativa no inicia sprint',
    !isset($alt_streak_sprint_storage[$action_user_id])
);

$active_streak_storage = [];
$active_streak_focus = [];
$active_streak_storage[$action_user_id] = [
    'version' => 1,
    'active_focus_list_id' => 2,
    'sprint_started_at' => $action_now_ts - 200,
    'last_executive_action_at' => $action_now_ts - 200,
    'sprint_expires_at' => $action_now_ts + 3400,
];
$active_streak_board = exec_action_ready_board();
$active_streak_deps = exec_action_sprint_deps($active_streak_storage, $action_user_id, $action_now_ts);
$active_streak_focus_deps = exec_action_sprint_deps($active_streak_focus, $action_user_id, $action_now_ts);
$active_streak_uc = exec_action_sprint_uc(
    $active_streak_board,
    $active_streak_deps,
    null,
    static function (array $input) use (&$active_streak_board): array {
        $task_id = (int) ($input['task_id'] ?? 0);
        $active_streak_board['organization']['task_evaluations_by_id'][$task_id] = [
            'visible_in_active' => false,
            'projection' => ['visible_in_active' => false, 'projected_bucket' => 'primary'],
            'capabilities' => ['can_dismiss' => false],
        ];

        return TaskUseCaseSupport::ok(['task_state' => ['task_id' => $task_id]]);
    },
    $active_streak_focus_deps
);

$active_streak_uc->execute(['task_id' => 10, 'action_key' => 'dismiss']);
ac_assert(
    'dismiss dentro de sprint no incrementa streak',
    !isset($active_streak_focus[$action_user_id])
    || (int) ($active_streak_focus[$action_user_id]['dismiss_streak_without_sprint'] ?? 0) === 0
);

$reset_streak_storage = [];
$reset_streak_focus = [];
$reset_streak_focus[$action_user_id] = [
    'version' => 1,
    'manual_focus_list_id' => null,
    'previous_focus_list_id' => null,
    'dismiss_streak_without_sprint' => 2,
    'manual_focus_expires_at' => null,
];
$reset_board = exec_action_user_complete_board();
$reset_deps = exec_action_sprint_deps($reset_streak_storage, $action_user_id, $action_now_ts);
$reset_focus_deps = exec_action_sprint_deps($reset_streak_focus, $action_user_id, $action_now_ts);
$reset_uc = exec_action_sprint_uc(
    $reset_board,
    $reset_deps,
    static function (array $input) use (&$reset_board): array {
        foreach ($reset_board['tasks'] as $index => $task) {
            if ((int) ($task['id'] ?? 0) === (int) ($input['task_id'] ?? 0)) {
                $reset_board['tasks'][$index]['status'] = 'done';
                break;
            }
        }

        return TaskUseCaseSupport::ok(['task' => ['id' => (int) ($input['task_id'] ?? 0), 'status' => 'done']]);
    },
    null,
    $reset_focus_deps
);

$reset_uc->execute(['task_id' => 20, 'action_key' => 'complete']);
ac_assert(
    'complete resetea dismiss streak',
    (int) ($reset_streak_focus[$action_user_id]['dismiss_streak_without_sprint'] ?? -1) === 0
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
