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
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-sprint-policy.php';
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-actions-policy.php';
require_once __DIR__ . '/../../../includes/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-visible-actions-policy.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-list.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-active-view-projection-policy.php';
require_once __DIR__ . '/../../../includes/repositories/ExecutiveSprintStateRepository.php';
require_once __DIR__ . '/../../../includes/repositories/ExecutiveFocusStateRepository.php';
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
ac_assert('Meta includes sprint observability block', is_array($ready['meta']['sprint'] ?? null));
ac_assert('Meta includes focus_controls', is_array($ready['meta']['focus_controls'] ?? null));
ac_assert('Meta includes focus_state', is_array($ready['meta']['focus_state'] ?? null));
ac_assert('focus_controls can_change_focus when hay elegibles', ($ready['meta']['focus_controls']['can_change_focus'] ?? false) === true);
ac_assert('Sprint meta inactive without stored sprint', ($ready['meta']['sprint']['sprint_active'] ?? true) === false);
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
ac_assert('Current exposes origin_key for handlers', is_array($current) && array_key_exists('origin_key', $current));
ac_assert('Current exposes source for handlers', is_array($current) && ($current['source'] ?? '') === 'system');
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

/**
 * @param array<int,array<string,mixed>> $storage
 * @return array{reader:callable,writer:callable,user_id_resolver:callable,now_ts_resolver:callable}
 */
function exec_sprint_test_deps(array &$storage, int $user_id = 7, ?int $now_ts = null): array {
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

$now_ts = (int) strtotime('2026-06-04 12:00:00');
$sprint_storage = [];
$sprint_storage[7] = [
    'version' => 1,
    'active_focus_list_id' => 1,
    'sprint_started_at' => $now_ts - 500,
    'last_executive_action_at' => $now_ts - 500,
    'sprint_expires_at' => $now_ts + 3600,
];
$sprint_deps = exec_sprint_test_deps($sprint_storage, 7, $now_ts);

$sprint_active = (new GetExecutiveProposalUseCase(
    static function (): array {
        return exec_board_ready_fixture();
    },
    $sprint_deps['reader'],
    $sprint_deps['writer'],
    $sprint_deps['user_id_resolver'],
    $sprint_deps['now_ts_resolver']
))->execute();

ac_assert(
    'Sprint activo fuerza lista foco aunque otra tenga mayor prioridad',
    (int) ($sprint_active['focus_list']['id'] ?? 0) === 1
);
ac_assert(
    'Sprint activo expone focus_reason sprint_active',
    ($sprint_active['meta']['focus_reason'] ?? '') === AA_Executive_Contract::FOCUS_REASON_SPRINT_ACTIVE
);
ac_assert('Sprint activo meta sprint_active true', ($sprint_active['meta']['sprint']['sprint_active'] ?? false) === true);
ac_assert(
    'Sprint activo meta active_focus_list_id',
    (int) ($sprint_active['meta']['sprint']['active_focus_list_id'] ?? 0) === 1
);
ac_assert(
    'Sprint activo meta seconds_remaining positive',
    (int) ($sprint_active['meta']['sprint']['seconds_remaining'] ?? 0) > 0
);
ac_assert(
    'Sprint activo meta current_focus_list_id',
    (int) ($sprint_active['meta']['sprint']['current_focus_list_id'] ?? 0) === 1
);

$expired_storage = [];
$expired_storage[7] = [
    'version' => 1,
    'active_focus_list_id' => 1,
    'sprint_started_at' => $now_ts - 5000,
    'last_executive_action_at' => $now_ts - 5000,
    'sprint_expires_at' => $now_ts - 10,
];
$expired_deps = exec_sprint_test_deps($expired_storage, 7, $now_ts);

$sprint_expired = (new GetExecutiveProposalUseCase(
    static function (): array {
        return exec_board_ready_fixture();
    },
    $expired_deps['reader'],
    $expired_deps['writer'],
    $expired_deps['user_id_resolver'],
    $expired_deps['now_ts_resolver']
))->execute();

ac_assert(
    'Sprint vencido no fuerza lista foco',
    (int) ($sprint_expired['focus_list']['id'] ?? 0) === 2
);
ac_assert('Sprint vencido meta inactive_reason expired', ($sprint_expired['meta']['sprint']['inactive_reason'] ?? '') === 'expired');
ac_assert('Sprint vencido limpia storage', !isset($expired_storage[7]));

$exhausted_storage = [];
$exhausted_storage[7] = [
    'version' => 1,
    'active_focus_list_id' => 2,
    'sprint_started_at' => $now_ts - 800,
    'last_executive_action_at' => $now_ts - 800,
    'sprint_expires_at' => $now_ts + 2800,
];
$exhausted_deps = exec_sprint_test_deps($exhausted_storage, 7, $now_ts);
$exhausted_expires = $exhausted_storage[7]['sprint_expires_at'];

$exhausted_board = exec_board_ready_fixture();
foreach ($exhausted_board['organization']['task_evaluations_by_id'] as $task_id => $evaluation) {
    if (in_array((int) $task_id, [10, 11, 12, 13], true)) {
        $exhausted_board['organization']['task_evaluations_by_id'][$task_id] = [
            'visible_in_active' => false,
            'projection' => [
                'visible_in_active' => false,
                'projected_bucket' => null,
            ],
            'capabilities' => [
                'can_dismiss' => false,
            ],
        ];
    }
}

$exhausted_proposal = (new GetExecutiveProposalUseCase(
    static function () use ($exhausted_board): array {
        return $exhausted_board;
    },
    $exhausted_deps['reader'],
    $exhausted_deps['writer'],
    $exhausted_deps['user_id_resolver'],
    $exhausted_deps['now_ts_resolver']
))->execute();

ac_assert(
    'Sprint activo con lista agotada avanza a otra lista elegible',
    (int) ($exhausted_proposal['focus_list']['id'] ?? 0) === 1
);
ac_assert(
    'Fallback actualiza active_focus_list_id sin renovar TTL',
    (int) ($exhausted_storage[7]['active_focus_list_id'] ?? 0) === 1
    && (int) ($exhausted_storage[7]['sprint_expires_at'] ?? 0) === $exhausted_expires
    && (int) ($exhausted_storage[7]['last_executive_action_at'] ?? 0) === $now_ts - 800
);
ac_assert(
    'Propuesta no queda empty si hay otra lista elegible',
    ($exhausted_proposal['status'] ?? '') === AA_Executive_Contract::STATUS_READY
);

// ─── MC5 manual focus ───────────────────────────────────────

$focus_now_ts = (int) strtotime('2026-06-04 12:00:00');
$sprint_empty_storage = [];
$manual_only_focus_storage = [];
$manual_only_focus_storage[7] = [
    'version' => 1,
    'manual_focus_list_id' => 1,
    'previous_focus_list_id' => 2,
    'dismiss_streak_without_sprint' => 0,
    'manual_focus_expires_at' => $focus_now_ts + 3600,
];
$sprint_empty_deps = exec_sprint_test_deps($sprint_empty_storage, 7, $focus_now_ts);
$manual_only_focus_deps = exec_sprint_test_deps($manual_only_focus_storage, 7, $focus_now_ts);

$manual_focus_proposal = (new GetExecutiveProposalUseCase(
    static function (): array {
        return exec_board_ready_fixture();
    },
    $sprint_empty_deps['reader'],
    $sprint_empty_deps['writer'],
    $sprint_empty_deps['user_id_resolver'],
    $sprint_empty_deps['now_ts_resolver'],
    $manual_only_focus_deps['reader'],
    $manual_only_focus_deps['writer']
))->execute();

ac_assert(
    'manual_focus_list_id vigente fuerza foco sin sprint',
    (int) ($manual_focus_proposal['focus_list']['id'] ?? 0) === 1
);
ac_assert(
    'manual focus expone focus_reason manual_focus_active',
    ($manual_focus_proposal['meta']['focus_reason'] ?? '') === AA_Executive_Contract::FOCUS_REASON_MANUAL_FOCUS
);

$sprint_and_manual_storage = [];
$sprint_and_manual_storage[7] = [
    'version' => 1,
    'active_focus_list_id' => 1,
    'sprint_started_at' => $focus_now_ts - 100,
    'last_executive_action_at' => $focus_now_ts - 100,
    'sprint_expires_at' => $focus_now_ts + 3500,
];
$focus_manual_storage = [];
$focus_manual_storage[7] = [
    'version' => 1,
    'manual_focus_list_id' => 2,
    'previous_focus_list_id' => null,
    'dismiss_streak_without_sprint' => 0,
    'manual_focus_expires_at' => $focus_now_ts + 3600,
];
$sprint_priority_deps = exec_sprint_test_deps($sprint_and_manual_storage, 7, $focus_now_ts);
$focus_priority_deps = exec_sprint_test_deps($focus_manual_storage, 7, $focus_now_ts);

$sprint_priority = (new GetExecutiveProposalUseCase(
    static function (): array {
        return exec_board_ready_fixture();
    },
    $sprint_priority_deps['reader'],
    $sprint_priority_deps['writer'],
    $sprint_priority_deps['user_id_resolver'],
    $sprint_priority_deps['now_ts_resolver'],
    $focus_priority_deps['reader'],
    $focus_priority_deps['writer']
))->execute();

ac_assert(
    'sprint activo tiene prioridad sobre manual_focus',
    (int) ($sprint_priority['focus_list']['id'] ?? 0) === 1
);

$expired_manual_storage = [];
$expired_manual_storage[7] = [
    'version' => 1,
    'manual_focus_list_id' => 1,
    'previous_focus_list_id' => null,
    'dismiss_streak_without_sprint' => 0,
    'manual_focus_expires_at' => $focus_now_ts - 10,
];
$expired_manual_deps = exec_sprint_test_deps($expired_manual_storage, 7, $focus_now_ts);

$expired_manual = (new GetExecutiveProposalUseCase(
    static function (): array {
        return exec_board_ready_fixture();
    },
    $expired_manual_deps['reader'],
    $expired_manual_deps['writer'],
    $expired_manual_deps['user_id_resolver'],
    $expired_manual_deps['now_ts_resolver'],
    $expired_manual_deps['reader'],
    $expired_manual_deps['writer']
))->execute();

ac_assert(
    'manual focus vencido no fuerza foco',
    (int) ($expired_manual['focus_list']['id'] ?? 0) === 2
);

$invalid_manual_storage = [];
$invalid_manual_storage[7] = [
    'version' => 1,
    'manual_focus_list_id' => 99,
    'previous_focus_list_id' => null,
    'dismiss_streak_without_sprint' => 0,
    'manual_focus_expires_at' => $focus_now_ts + 3600,
];
$invalid_manual_deps = exec_sprint_test_deps($invalid_manual_storage, 7, $focus_now_ts);

$invalid_manual = (new GetExecutiveProposalUseCase(
    static function (): array {
        return exec_board_ready_fixture();
    },
    $invalid_manual_deps['reader'],
    $invalid_manual_deps['writer'],
    $invalid_manual_deps['user_id_resolver'],
    $invalid_manual_deps['now_ts_resolver'],
    $invalid_manual_deps['reader'],
    $invalid_manual_deps['writer']
))->execute();

ac_assert(
    'manual focus inválido no bloquea propuesta',
    ($invalid_manual['status'] ?? '') === AA_Executive_Contract::STATUS_READY
);

$mapper_src = file_get_contents(__DIR__ . '/../../../includes/application/executive/ExecutiveProposalMapper.php');
ac_assert(
    'ExecutiveProposalMapper passes origin_key to policy item',
    strpos($mapper_src, "'origin_key' => self::nullable_origin_key(\$task)") !== false
);
ac_assert(
    'ExecutiveProposalMapper passes is_overdue to policy item',
    strpos($mapper_src, "'is_overdue' => \$task_vo->is_overdue(\$now)") !== false
);

/**
 * @return array<string,mixed>
 */
function exec_appointment_confirmation_board(bool $is_overdue): array {
    $due_at = $is_overdue ? '2026-06-01 08:00:00' : '2026-06-10 12:00:00';

    return [
        'lists' => [
            [
                'id' => 5,
                'title' => 'Acciones de citas',
                'importance' => 10,
                'status' => 'active',
                'source_category' => 'agenda_app',
                'origin_key' => 'appointment_actions',
            ],
        ],
        'tasks' => [
            [
                'id' => 501,
                'list_id' => 5,
                'title' => 'Confirmar cita con Ana',
                'status' => 'pending',
                'importance' => 0,
                'due_at' => $due_at,
                'completion_type' => 'system',
                'source_category' => 'agenda_app',
                'origin_key' => AA_Appointment_Actions_Catalog::task_origin_key(123),
                'source' => 'system',
            ],
        ],
        'organization' => [
            'task_evaluations_by_id' => [
                501 => exec_board_visible_eval('primary', true),
            ],
            'task_actions_by_id' => [
                501 => [
                    [
                        'id' => 9001,
                        'action_key' => AA_Appointment_Actions_Catalog::TASK_ACTION_KEY,
                        'type' => 'handler',
                        'label' => AA_Appointment_Actions_Catalog::TASK_ACTION_LABEL,
                        'enabled' => 1,
                        'placement' => 'primary',
                        'category' => 'mechanical',
                        'handler' => AA_Appointment_Actions_Catalog::TASK_ACTION_HANDLER,
                        'position' => 0,
                    ],
                ],
            ],
        ],
    ];
}

/**
 * @param list<int> $task_ids
 * @return array<string,mixed>
 */
function exec_appointment_confirmation_selection(array $task_ids): array {
    return [
        'status' => AA_Executive_Contract::STATUS_READY,
        'focus_list_id' => 5,
        'task_ids' => $task_ids,
        'eligible_count_in_focus_list' => count($task_ids),
    ];
}

$overdue_confirmation = ExecutiveProposalMapper::map(
    exec_appointment_confirmation_board(true),
    exec_appointment_confirmation_selection([501]),
    '2026-06-04 12:00:00'
);
$overdue_current = $overdue_confirmation['tasks'][0] ?? null;
$overdue_action_keys = array_map(static function (array $action): string {
    return (string) ($action['key'] ?? '');
}, is_array($overdue_current['executive_actions'] ?? null) ? $overdue_current['executive_actions'] : []);
ac_assert(
    'Executive overdue appointment confirmation hides Confirmar',
    is_array($overdue_current)
    && ($overdue_current['is_overdue'] ?? false) === true
    && !in_array(AA_Appointment_Actions_Catalog::TASK_ACTION_KEY, $overdue_action_keys, true)
);
ac_assert(
    'Executive overdue appointment confirmation keeps dismiss',
    in_array('dismiss', $overdue_action_keys, true)
);
ac_assert(
    'Executive overdue appointment confirmation exposes missed action (MC4)',
    in_array('missed', $overdue_action_keys, true)
);

$future_confirmation = ExecutiveProposalMapper::map(
    exec_appointment_confirmation_board(false),
    exec_appointment_confirmation_selection([501]),
    '2026-06-04 12:00:00'
);
$future_current = $future_confirmation['tasks'][0] ?? null;
$future_action_keys = array_map(static function (array $action): string {
    return (string) ($action['key'] ?? '');
}, is_array($future_current['executive_actions'] ?? null) ? $future_current['executive_actions'] : []);
ac_assert(
    'Executive future appointment confirmation keeps Confirmar',
    is_array($future_current)
    && ($future_current['is_overdue'] ?? true) === false
    && in_array(AA_Appointment_Actions_Catalog::TASK_ACTION_KEY, $future_action_keys, true)
);
ac_assert(
    'Executive future appointment confirmation hides missed action (MC4)',
    !in_array('missed', $future_action_keys, true)
);

$missed_board = exec_appointment_confirmation_board(true);
foreach ($missed_board['tasks'] as $index => $task) {
    if ((int) ($task['id'] ?? 0) === 501) {
        $missed_board['tasks'][$index]['status'] = 'missed';
    }
}
$missed_board['organization']['task_evaluations_by_id'][501] = [
    'visible_in_active' => false,
    'projection' => ['visible_in_active' => false, 'projected_bucket' => 'primary'],
    'capabilities' => ['can_dismiss' => false],
];
$missed_proposal = (new GetExecutiveProposalUseCase(static function () use ($missed_board): array {
    return $missed_board;
}))->execute();
ac_assert(
    'Missed task does not enter executive proposal (MC4)',
    is_array($missed_proposal['tasks'] ?? null) && ($missed_proposal['tasks'] === [])
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
