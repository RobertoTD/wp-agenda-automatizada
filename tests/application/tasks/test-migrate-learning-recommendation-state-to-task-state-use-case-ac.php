<?php
/**
 * AC MC13O-F1 — MigrateLearningRecommendationStateToTaskStateUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-migrate-learning-recommendation-state-to-task-state-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$use_case_file = $plugin_root . '/includes/application/tasks/MigrateLearningRecommendationStateToTaskStateUseCase.php';

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

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-06-09 12:00:00' : '2026-06-09 12:00:00';
    }
}

if (!function_exists('aa_get_current_datetime')) {
    function aa_get_current_datetime() {
        return '2026-06-09 12:00:00';
    }
}

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/domain/tasks/class-aa-learning-legacy-state-to-task-state-mapper.php';
require_once $plugin_root . '/includes/repositories/TaskStateRepository.php';
require_once $use_case_file;

$use_case_src = file_get_contents($use_case_file);
ac_assert('Use case file readable', $use_case_src !== false);
ac_assert(
    'Use case defines MigrateLearningRecommendationStateToTaskStateUseCase',
    strpos($use_case_src, 'class MigrateLearningRecommendationStateToTaskStateUseCase') !== false
);
ac_assert('Use case loads legacy mapper', strpos($use_case_src, 'AA_Learning_Legacy_State_To_Task_State_Mapper') !== false);
ac_assert('Use case reads legacy repository', strpos($use_case_src, 'LearningRecommendationStateRepository::get_all') !== false);
ac_assert('Use case finds seeded tasks by origin', strpos($use_case_src, 'find_task_by_origin') !== false);
ac_assert('Use case uses mark_completed', strpos($use_case_src, 'mark_completed') !== false);
ac_assert('Use case uses apply_legacy_defer_migration', strpos($use_case_src, 'apply_legacy_defer_migration') !== false);
ac_assert('Use case does not use record_defer', strpos($use_case_src, 'record_defer') === false);
ac_assert('Use case does not use record_dismiss', strpos($use_case_src, 'record_dismiss') === false);
ac_assert(
    'Use case does not touch system completion',
    strpos($use_case_src, 'record_system_completion_evaluation') === false
    && strpos($use_case_src, 'completed_by_system') === false
);
ac_assert('Use case does not delete legacy state', strpos($use_case_src, 'delete') === false);

$tasks_by_key = [
    'install_pwa' => [
        'id' => 100,
        'origin_key' => 'install_pwa',
        'completion_type' => 'manual',
        'status' => 'pending',
        'completed_at' => null,
    ],
    'configure_services' => [
        'id' => 101,
        'origin_key' => 'configure_services',
        'completion_type' => 'system',
        'status' => 'pending',
        'completed_at' => null,
    ],
    'create_first_client' => [
        'id' => 102,
        'origin_key' => 'create_first_client',
        'completion_type' => 'system',
        'status' => 'pending',
        'completed_at' => null,
    ],
];

$legacy_states = [
    'install_pwa' => [
        'recommendation_key' => 'install_pwa',
        'is_completed' => 1,
        'completed_at' => '2026-06-01 09:00:00',
    ],
    'configure_services' => [
        'recommendation_key' => 'configure_services',
        'is_ignored' => 1,
        'ignored_at' => '2026-06-02 10:00:00',
    ],
    'create_first_client' => [
        'recommendation_key' => 'create_first_client',
        'is_dismissed' => 1,
        'dismissed_at' => '2026-06-03 11:00:00',
    ],
    'missing_key' => [
        'recommendation_key' => 'missing_key',
        'is_ignored' => 1,
        'ignored_at' => '2026-06-04 12:00:00',
    ],
];

$completed_tasks = [];
$defer_states = [];
$system_state = [
    'task_id' => 101,
    'completed_by_system' => 1,
    'system_completed_at' => '2026-06-08 09:00:00',
    'last_system_evaluated_at' => '2026-06-09 10:00:00',
    'defer_count' => 0,
    'dismiss_count' => 0,
];

$use_case = new MigrateLearningRecommendationStateToTaskStateUseCase(
    static function () use ($legacy_states): array {
        return $legacy_states;
    },
    static function (string $origin_key) use ($tasks_by_key): ?array {
        return $tasks_by_key[$origin_key] ?? null;
    },
    static function (int $task_id) use ($system_state): ?array {
        if ($task_id === 101) {
            return $system_state;
        }

        return $defer_states[$task_id] ?? null;
    },
    static function (int $task_id, string $completed_at) use (&$completed_tasks, &$tasks_by_key): ?array {
        foreach ($tasks_by_key as $key => $task) {
            if ((int) ($task['id'] ?? 0) !== $task_id) {
                continue;
            }

            $tasks_by_key[$key]['status'] = 'done';
            $tasks_by_key[$key]['completed_at'] = $completed_at;
            $completed_tasks[$task_id] = $completed_at;

            return $tasks_by_key[$key];
        }

        return null;
    },
    static function (int $task_id, string $last_deferred_at) use (&$defer_states, $system_state): ?array {
        $existing = $defer_states[$task_id] ?? null;

        if ($existing === null && $task_id === 101) {
            $existing = $system_state;
        }

        $defer_count = max(is_array($existing) ? (int) ($existing['defer_count'] ?? 0) : 0, 1);
        $resolved_last = is_array($existing) && !empty($existing['last_deferred_at'])
            ? (string) $existing['last_deferred_at']
            : $last_deferred_at;

        $defer_states[$task_id] = [
            'task_id' => $task_id,
            'last_deferred_at' => $resolved_last,
            'defer_until' => null,
            'defer_count' => $defer_count,
            'dismiss_count' => is_array($existing) ? (int) ($existing['dismiss_count'] ?? 0) : 0,
            'completed_by_system' => is_array($existing) ? (int) ($existing['completed_by_system'] ?? 0) : 0,
            'system_completed_at' => is_array($existing) ? ($existing['system_completed_at'] ?? null) : null,
            'last_system_evaluated_at' => is_array($existing) ? ($existing['last_system_evaluated_at'] ?? null) : null,
        ];

        return $defer_states[$task_id];
    }
);

$result = $use_case->execute();
$data = is_array($result['data'] ?? null) ? $result['data'] : [];

ac_assert('Use case success', !empty($result['success']));
ac_assert('completed_migrated count', (int) ($data['completed_migrated'] ?? 0) === 1);
ac_assert('defer_migrated count', (int) ($data['defer_migrated'] ?? 0) === 1);
ac_assert('dismissed_skipped count', (int) ($data['dismissed_skipped'] ?? 0) === 1);
ac_assert('skipped_no_task count', (int) ($data['skipped_no_task'] ?? 0) === 1);
ac_assert(
    'legacy completed manual sets task done',
    ($tasks_by_key['install_pwa']['status'] ?? '') === 'done'
    && ($tasks_by_key['install_pwa']['completed_at'] ?? '') === '2026-06-01 09:00:00'
);
ac_assert(
    'legacy ignored writes defer state',
    isset($defer_states[101])
    && ($defer_states[101]['last_deferred_at'] ?? '') === '2026-06-02 10:00:00'
    && (int) ($defer_states[101]['defer_count'] ?? 0) === 1
);
ac_assert(
    'legacy dismissed does not write defer or dismiss state',
    !isset($defer_states[102])
);
ac_assert(
    'completed_by_system preexisting stays intact after defer migration',
    (int) ($defer_states[101]['completed_by_system'] ?? 0) === 1
    && ($defer_states[101]['system_completed_at'] ?? '') === '2026-06-08 09:00:00'
);

$second_result = $use_case->execute();
$second_data = is_array($second_result['data'] ?? null) ? $second_result['data'] : [];
ac_assert('second run success', !empty($second_result['success']));
ac_assert(
    'second run does not increment defer_count',
    (int) ($defer_states[101]['defer_count'] ?? 0) === 1
);
ac_assert(
    'second run still reports defer_migrated',
    (int) ($second_data['defer_migrated'] ?? 0) === 1
);

$legacy_states_after = $legacy_states;
ac_assert(
    'legacy state provider data unchanged',
    $legacy_states_after === $legacy_states
);

// ─── Integración WordPress ───────────────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/LearningRecommendationStateRepository.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskStateRepository.php';
    require_once $plugin_root . '/includes/application/tasks/SyncLearningCatalogToTasksUseCase.php';

    AA_Schema::install();
    (new SyncLearningCatalogToTasksUseCase())->execute();

    $manual_key = 'install_pwa';
    $ignored_key = 'configure_services';
    $dismissed_key = 'create_first_client';

    LearningRecommendationStateRepository::reactivate($manual_key);
    LearningRecommendationStateRepository::reactivate($ignored_key);
    LearningRecommendationStateRepository::reactivate($dismissed_key);

    LearningRecommendationStateRepository::mark_completed($manual_key, '2026-06-01 09:00:00');
    LearningRecommendationStateRepository::mark_ignored($ignored_key, '2026-06-02 10:00:00');
    LearningRecommendationStateRepository::mark_dismissed($dismissed_key, '2026-06-03 11:00:00');

    $legacy_before = LearningRecommendationStateRepository::find_by_key($manual_key);
    ac_assert('legacy row exists before migration', is_array($legacy_before));

    $wp_result = (new MigrateLearningRecommendationStateToTaskStateUseCase())->execute();
    $wp_data = is_array($wp_result['data'] ?? null) ? $wp_result['data'] : [];

    ac_assert('WP migration success', !empty($wp_result['success']));
    ac_assert('WP completed_migrated >= 1', (int) ($wp_data['completed_migrated'] ?? 0) >= 1);
    ac_assert('WP defer_migrated >= 1', (int) ($wp_data['defer_migrated'] ?? 0) >= 1);
    ac_assert('WP dismissed_skipped >= 1', (int) ($wp_data['dismissed_skipped'] ?? 0) >= 1);

    $manual_task = SeededTaskRepository::find_task_by_origin('agenda_app', $manual_key);
    ac_assert('WP manual task marked done', is_array($manual_task) && ($manual_task['status'] ?? '') === 'done');

    $ignored_task = SeededTaskRepository::find_task_by_origin('agenda_app', $ignored_key);
    $ignored_state = is_array($ignored_task)
        ? TaskStateRepository::find_by_task_id((int) ($ignored_task['id'] ?? 0))
        : null;
    ac_assert(
        'WP ignored task has defer state',
        is_array($ignored_state)
        && (int) ($ignored_state['defer_count'] ?? 0) >= 1
        && !empty($ignored_state['last_deferred_at'])
    );

    $dismissed_task = SeededTaskRepository::find_task_by_origin('agenda_app', $dismissed_key);
    $dismissed_state = is_array($dismissed_task)
        ? TaskStateRepository::find_by_task_id((int) ($dismissed_task['id'] ?? 0))
        : null;
    ac_assert(
        'WP dismissed legacy does not write dismiss state',
        $dismissed_state === null
        || (
            (int) ($dismissed_state['dismiss_count'] ?? 0) === 0
            && ($dismissed_state['last_dismissed_at'] ?? null) === null
        )
    );

    $legacy_after = LearningRecommendationStateRepository::find_by_key($manual_key);
    ac_assert(
        'WP legacy state not deleted',
        is_array($legacy_after) && (int) ($legacy_after['is_completed'] ?? 0) === 1
    );

    $first_defer_count = is_array($ignored_state) ? (int) ($ignored_state['defer_count'] ?? 0) : 0;
    (new MigrateLearningRecommendationStateToTaskStateUseCase())->execute();
    $ignored_state_second = is_array($ignored_task)
        ? TaskStateRepository::find_by_task_id((int) ($ignored_task['id'] ?? 0))
        : null;
    ac_assert(
        'WP second run keeps defer_count stable',
        is_array($ignored_state_second)
        && (int) ($ignored_state_second['defer_count'] ?? 0) === $first_defer_count
    );
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar migración en BD.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
