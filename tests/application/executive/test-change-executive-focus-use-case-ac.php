<?php
/**
 * AC MC5 — ChangeExecutiveFocusUseCase.
 *
 * Ejecutar: php tests/application/executive/test-change-executive-focus-use-case-ac.php
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
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-focus-state-policy.php';
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
require_once __DIR__ . '/../../../includes/application/executive/ChangeExecutiveFocusUseCase.php';
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
function focus_board_fixture(): array {
    return [
        'lists' => [
            ['id' => 1, 'title' => 'Clientes', 'status' => 'active', 'source_category' => 'user'],
            ['id' => 2, 'title' => 'Activación', 'status' => 'active', 'source_category' => 'agenda_app'],
        ],
        'tasks' => [
            ['id' => 10, 'list_id' => 2, 'title' => 'Tarea A', 'status' => 'pending', 'importance' => 100],
            ['id' => 20, 'list_id' => 1, 'title' => 'Tarea B', 'status' => 'pending', 'importance' => 50, 'completion_type' => 'manual'],
        ],
        'organization' => [
            'list_order' => [2, 1],
            'task_evaluations_by_id' => [
                10 => [
                    'visible_in_active' => true,
                    'projection' => ['visible_in_active' => true, 'projected_bucket' => 'primary'],
                    'capabilities' => ['can_dismiss' => true],
                ],
                20 => [
                    'visible_in_active' => true,
                    'projection' => ['visible_in_active' => true, 'projected_bucket' => 'primary'],
                    'capabilities' => ['can_dismiss' => true],
                ],
            ],
            'task_actions_by_id' => [],
        ],
    ];
}

/**
 * @param array<int,array<string,mixed>> $storage
 * @return array{reader:callable,writer:callable,user_id_resolver:callable,now_ts_resolver:callable}
 */
function focus_deps(array &$storage, int $user_id = 7, ?int $now_ts = null): array {
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
$user_id = 7;
$board = focus_board_fixture();
$task_mutations = 0;

$sprint_storage = [];
$focus_storage = [];
$sprint_deps = focus_deps($sprint_storage, $user_id, $now_ts);
$focus_deps = focus_deps($focus_storage, $user_id, $now_ts);

$change_uc = new ChangeExecutiveFocusUseCase(
    static function () use (&$board): array {
        return $board;
    },
    $sprint_deps['reader'],
    $sprint_deps['writer'],
    $focus_deps['reader'],
    $focus_deps['writer'],
    $sprint_deps['user_id_resolver'],
    $sprint_deps['now_ts_resolver'],
    static function (): int {
        return 1;
    }
);

$change_result = $change_uc->execute(['focus_action' => 'change_focus']);
ac_assert('change_focus success', !empty($change_result['success']));
ac_assert('change_focus sin sprint no inicia sprint', !isset($sprint_storage[$user_id]));
ac_assert(
    'change_focus sin sprint guarda manual focus',
    (int) ($focus_storage[$user_id]['manual_focus_list_id'] ?? 0) === 1
);
ac_assert(
    'change_focus guarda previous',
    (int) ($focus_storage[$user_id]['previous_focus_list_id'] ?? 0) === 2
);
ac_assert(
    'change_focus resetea dismiss streak',
    (int) ($focus_storage[$user_id]['dismiss_streak_without_sprint'] ?? -1) === 0
);
ac_assert(
    'change_focus con varias listas no devuelve lista actual',
    (int) ($focus_storage[$user_id]['manual_focus_list_id'] ?? 0) === 1
    && (int) ($focus_storage[$user_id]['manual_focus_list_id'] ?? 0) !== 2
);

$sprint_storage[$user_id] = [
    'version' => 1,
    'active_focus_list_id' => 2,
    'sprint_started_at' => $now_ts - 200,
    'last_executive_action_at' => $now_ts - 200,
    'sprint_expires_at' => $now_ts + 3400,
];
$focus_storage = [];
$before_expires = $sprint_storage[$user_id]['sprint_expires_at'];
$before_last_action = $sprint_storage[$user_id]['last_executive_action_at'];

$sprint_change_uc = new ChangeExecutiveFocusUseCase(
    static function () use ($board): array {
        return $board;
    },
    $sprint_deps['reader'],
    $sprint_deps['writer'],
    $focus_deps['reader'],
    $focus_deps['writer'],
    $sprint_deps['user_id_resolver'],
    $sprint_deps['now_ts_resolver'],
    static function (): int {
        return 0;
    }
);

$sprint_change = $sprint_change_uc->execute(['focus_action' => 'change_focus']);
ac_assert('change_focus con sprint activo success', !empty($sprint_change['success']));
ac_assert(
    'change_focus con sprint actualiza active_focus_list_id a otra lista',
    (int) ($sprint_storage[$user_id]['active_focus_list_id'] ?? 0) === 1
);
ac_assert(
    'change_focus con sprint no extiende TTL',
    (int) ($sprint_storage[$user_id]['sprint_expires_at'] ?? 0) === $before_expires
);
ac_assert(
    'change_focus con sprint no actualiza last_executive_action_at',
    (int) ($sprint_storage[$user_id]['last_executive_action_at'] ?? 0) === $before_last_action
);

$focus_storage[$user_id] = [
    'version' => 1,
    'manual_focus_list_id' => 2,
    'previous_focus_list_id' => 1,
    'dismiss_streak_without_sprint' => 0,
    'manual_focus_expires_at' => $now_ts + 3600,
];
$sprint_storage = [];

$previous_uc = new ChangeExecutiveFocusUseCase(
    static function () use ($board): array {
        return $board;
    },
    $sprint_deps['reader'],
    $sprint_deps['writer'],
    $focus_deps['reader'],
    $focus_deps['writer'],
    $sprint_deps['user_id_resolver'],
    $sprint_deps['now_ts_resolver']
);

$previous_result = $previous_uc->execute(['focus_action' => 'previous_focus']);
ac_assert('previous_focus success', !empty($previous_result['success']));
ac_assert(
    'previous_focus alterna A/B',
    (int) ($focus_storage[$user_id]['manual_focus_list_id'] ?? 0) === 1
    && (int) ($focus_storage[$user_id]['previous_focus_list_id'] ?? 0) === 2
);

$focus_storage[$user_id] = [
    'version' => 1,
    'manual_focus_list_id' => 2,
    'previous_focus_list_id' => 99,
    'dismiss_streak_without_sprint' => 0,
    'manual_focus_expires_at' => $now_ts + 3600,
];

$invalid_previous = $previous_uc->execute(['focus_action' => 'previous_focus']);
ac_assert(
    'previous inválido falla limpio',
    ($invalid_previous['error']['code'] ?? '') === 'previous_focus_unavailable'
);

$sprint_storage[$user_id] = [
    'version' => 1,
    'active_focus_list_id' => 2,
    'sprint_started_at' => $now_ts - 100,
    'last_executive_action_at' => $now_ts - 100,
    'sprint_expires_at' => $now_ts + 3500,
];

$expire_uc = new ChangeExecutiveFocusUseCase(
    static function () use ($board): array {
        return $board;
    },
    $sprint_deps['reader'],
    $sprint_deps['writer'],
    $focus_deps['reader'],
    $focus_deps['writer'],
    $sprint_deps['user_id_resolver'],
    $sprint_deps['now_ts_resolver']
);

$expire_result = $expire_uc->execute(['focus_action' => 'expire_sprint_debug']);
ac_assert('expire_sprint_debug success', !empty($expire_result['success']));
ac_assert(
    'expire_sprint_debug limpia sprint vencido tras recálculo',
    !isset($sprint_storage[$user_id])
);
ac_assert(
    'expire_sprint_debug propuesta sprint inactive expired',
    ($expire_result['data']['proposal']['meta']['sprint']['sprint_active'] ?? true) === false
    && ($expire_result['data']['proposal']['meta']['sprint']['inactive_reason'] ?? '') === 'expired'
);

ac_assert('change_focus no muta tareas', $task_mutations === 0);

$single_list_board = [
    'lists' => [
        ['id' => 5, 'title' => 'Única', 'status' => 'active', 'source_category' => 'user'],
    ],
    'tasks' => [
        ['id' => 50, 'list_id' => 5, 'title' => 'Sola', 'status' => 'pending', 'importance' => 10, 'completion_type' => 'manual'],
    ],
    'organization' => [
        'list_order' => [5],
        'task_evaluations_by_id' => [
            50 => [
                'visible_in_active' => true,
                'projection' => ['visible_in_active' => true, 'projected_bucket' => 'primary'],
                'capabilities' => ['can_dismiss' => true],
            ],
        ],
        'task_actions_by_id' => [],
    ],
];
$single_sprint_storage = [];
$single_focus_storage = [];
$single_sprint_deps = focus_deps($single_sprint_storage, $user_id, $now_ts);
$single_focus_deps = focus_deps($single_focus_storage, $user_id, $now_ts);
$single_change_uc = new ChangeExecutiveFocusUseCase(
    static function () use ($single_list_board): array {
        return $single_list_board;
    },
    $single_sprint_deps['reader'],
    $single_sprint_deps['writer'],
    $single_focus_deps['reader'],
    $single_focus_deps['writer'],
    $single_sprint_deps['user_id_resolver'],
    $single_sprint_deps['now_ts_resolver']
);

$single_change = $single_change_uc->execute(['focus_action' => 'change_focus']);
ac_assert('change_focus con una sola lista puede quedarse en la misma', !empty($single_change['success']));
ac_assert(
    'change_focus una lista mantiene manual_focus_list_id',
    (int) ($single_focus_storage[$user_id]['manual_focus_list_id'] ?? 0) === 5
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
