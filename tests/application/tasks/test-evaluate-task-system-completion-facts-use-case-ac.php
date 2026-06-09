<?php
/**
 * AC MC13O-E1 — EvaluateTaskSystemCompletionFactsUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-evaluate-task-system-completion-facts-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$use_case_file = $plugin_root . '/includes/application/tasks/EvaluateTaskSystemCompletionFactsUseCase.php';

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
require_once $plugin_root . '/includes/repositories/TaskStateRepository.php';
require_once $use_case_file;

$use_case_src = file_get_contents($use_case_file);
ac_assert('Use case file readable', $use_case_src !== false);
ac_assert('Use case defines EvaluateTaskSystemCompletionFactsUseCase', strpos($use_case_src, 'class EvaluateTaskSystemCompletionFactsUseCase') !== false);
ac_assert('Use case loads TaskSystemCompletionFactResolver', strpos($use_case_src, 'TaskSystemCompletionFactResolver') !== false);
ac_assert('Use case uses TaskRepository candidates', strpos($use_case_src, 'list_system_completion_candidates') !== false);
ac_assert('Use case records system completion', strpos($use_case_src, 'record_system_completion_evaluation') !== false);
ac_assert('Use case does not touch LearningRecommendationStateRepository', strpos($use_case_src, 'LearningRecommendationStateRepository') === false);
ac_assert('Use case does not touch task status', strpos($use_case_src, 'update_status') === false && strpos($use_case_src, 'mark_completed') === false);
ac_assert('Use case does not touch GetTaskBoardUseCase', strpos($use_case_src, 'GetTaskBoardUseCase') === false);

$recorded = [];
$state_by_task_id = [];

$result = (new EvaluateTaskSystemCompletionFactsUseCase(
    static function (): array {
        return [
            [
                'id' => 10,
                'list_id' => 50,
                'title' => 'Configura servicios',
                'status' => 'pending',
                'source_category' => 'agenda_app',
                'origin_key' => 'configure_services',
                'completion_type' => 'system',
                'completion_fact_key' => 'has_active_service',
            ],
            [
                'id' => 11,
                'list_id' => 50,
                'title' => 'Instala PWA',
                'status' => 'pending',
                'source_category' => 'agenda_app',
                'origin_key' => 'install_pwa',
                'completion_type' => 'manual',
                'completion_fact_key' => null,
            ],
            [
                'id' => 12,
                'list_id' => 50,
                'title' => 'Fact desconocido',
                'status' => 'pending',
                'source_category' => 'agenda_app',
                'origin_key' => 'unknown',
                'completion_type' => 'system',
                'completion_fact_key' => 'unknown_fact',
            ],
        ];
    },
    static function (): array {
        return [
            'has_active_service' => true,
            'google_connected' => false,
        ];
    },
    static function (int $task_id, bool $completed, string $now) use (&$recorded, &$state_by_task_id): ?array {
        $recorded[] = [
            'task_id' => $task_id,
            'completed' => $completed,
            'now' => $now,
        ];
        $system_completed_at = $state_by_task_id[$task_id]['system_completed_at'] ?? null;

        if ($completed && ($system_completed_at === null || $system_completed_at === '')) {
            $system_completed_at = $now;
        }

        $row = [
            'task_id' => $task_id,
            'completed_by_system' => $completed ? 1 : 0,
            'system_completed_at' => $system_completed_at,
            'last_system_evaluated_at' => $now,
            'defer_count' => 0,
            'dismiss_count' => 0,
        ];
        $state_by_task_id[$task_id] = $row;

        return $row;
    },
    static function (int $task_id) use (&$state_by_task_id): ?array {
        return $state_by_task_id[$task_id] ?? null;
    }
))->execute();

ac_assert('Use case success', !empty($result['success']));
ac_assert('Use case evaluates known system candidate', (int) ($result['data']['evaluated'] ?? 0) === 1);
ac_assert('Use case counts completed facts', (int) ($result['data']['completed'] ?? 0) === 1);
ac_assert('Use case counts newly completed', (int) ($result['data']['newly_completed'] ?? 0) === 1);
ac_assert('Use case counts unknown fact as error', (int) ($result['data']['errors'] ?? 0) === 1);
ac_assert(
    'Use case records completed_by_system for system task',
    ($recorded[0]['task_id'] ?? 0) === 10
    && ($recorded[0]['completed'] ?? false) === true
);

$reversible = (new EvaluateTaskSystemCompletionFactsUseCase(
    static function (): array {
        return [
            [
                'id' => 20,
                'completion_fact_key' => 'google_connected',
                'completion_type' => 'system',
                'status' => 'pending',
            ],
        ];
    },
    static function (): array {
        return ['google_connected' => false];
    },
    static function (int $task_id, bool $completed, string $now): ?array {
        return [
            'task_id' => $task_id,
            'completed_by_system' => $completed ? 1 : 0,
            'system_completed_at' => '2026-06-01 10:00:00',
            'last_system_evaluated_at' => $now,
        ];
    },
    static function (int $task_id): ?array {
        return [
            'task_id' => $task_id,
            'completed_by_system' => 1,
            'system_completed_at' => '2026-06-01 10:00:00',
        ];
    }
))->execute();
ac_assert(
    'False fact still evaluates without clearing sticky system_completed_at',
    !empty($reversible['success'])
    && (int) ($reversible['data']['evaluated'] ?? 0) === 1
    && (int) ($reversible['data']['completed'] ?? -1) === 0
);

$throws = (new EvaluateTaskSystemCompletionFactsUseCase(
    static function (): array {
        throw new RuntimeException('boom');
    }
))->execute();
ac_assert(
    'Throwable returns controlled error',
    empty($throws['success'])
    && ($throws['error']['code'] ?? '') === 'system_completion_evaluation_failed'
    && ($throws['error']['message'] ?? '') === 'boom'
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
