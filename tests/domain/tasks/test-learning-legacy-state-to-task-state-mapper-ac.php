<?php
/**
 * AC MC13O-F1 — AA_Learning_Legacy_State_To_Task_State_Mapper.
 *
 * Ejecutar: php tests/domain/tasks/test-learning-legacy-state-to-task-state-mapper-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-learning-legacy-state-to-task-state-mapper.php';

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
 * @param array<string,mixed> $legacy
 * @param array<string,mixed> $task
 * @return array<string,mixed>
 */
function map_legacy(array $legacy, array $task, string $now = '2026-06-09 12:00:00'): array {
    return (new AA_Learning_Legacy_State_To_Task_State_Mapper())->map($legacy, $task, $now);
}

$manual_task = [
    'id' => 10,
    'origin_key' => 'install_pwa',
    'completion_type' => 'manual',
    'status' => 'pending',
];

$system_task = [
    'id' => 11,
    'origin_key' => 'configure_services',
    'completion_type' => 'system',
    'status' => 'pending',
];

$complete_manual = map_legacy(
    [
        'recommendation_key' => 'install_pwa',
        'is_completed' => 1,
        'completed_at' => '2026-06-01 09:00:00',
    ],
    $manual_task
);
ac_assert(
    'is_completed manual task maps to complete_manual',
    ($complete_manual['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_COMPLETE_MANUAL
    && ($complete_manual['completed_at'] ?? '') === '2026-06-01 09:00:00'
);

$complete_system = map_legacy(
    [
        'recommendation_key' => 'configure_services',
        'is_completed' => 1,
        'completed_at' => '2026-06-01 09:00:00',
    ],
    $system_task
);
ac_assert(
    'is_completed system task maps to skipped_ambiguous',
    ($complete_system['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_AMBIGUOUS
);

$defer = map_legacy(
    [
        'recommendation_key' => 'configure_services',
        'is_ignored' => 1,
        'ignored_at' => '2026-06-02 10:00:00',
    ],
    $system_task
);
ac_assert(
    'is_ignored maps to defer',
    ($defer['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_DEFER
    && ($defer['last_deferred_at'] ?? '') === '2026-06-02 10:00:00'
    && (int) ($defer['defer_count_min'] ?? 0) === 1
);

$dismissed = map_legacy(
    [
        'recommendation_key' => 'install_pwa',
        'is_dismissed' => 1,
        'dismissed_at' => '2026-05-01 10:00:00',
    ],
    $manual_task
);
ac_assert(
    'is_dismissed maps to skipped_dismissed_deferred_for_policy',
    ($dismissed['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_DISMISSED
);

$dismissed_expired = map_legacy(
    [
        'recommendation_key' => 'install_pwa',
        'is_dismissed' => 1,
        'dismissed_at' => '2020-01-01 10:00:00',
    ],
    $manual_task
);
ac_assert(
    'expired dismissed does not map to defer',
    ($dismissed_expired['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_DISMISSED
);

$list_override_only = map_legacy(
    [
        'recommendation_key' => 'install_pwa',
        'list_override' => 2,
    ],
    $manual_task
);
ac_assert(
    'list_override only maps to skipped_ambiguous',
    ($list_override_only['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_AMBIGUOUS
);

$last_suggested_only = map_legacy(
    [
        'recommendation_key' => 'install_pwa',
        'last_suggested_at' => '2026-05-15 08:00:00',
    ],
    $manual_task
);
ac_assert(
    'last_suggested_at only maps to skipped_no_signal',
    ($last_suggested_only['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_NO_SIGNAL
);

$no_signal = map_legacy(
    ['recommendation_key' => 'install_pwa'],
    $manual_task
);
ac_assert(
    'empty legacy row maps to skipped_no_signal',
    ($no_signal['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_SKIPPED_NO_SIGNAL
);

$completed_beats_ignored = map_legacy(
    [
        'recommendation_key' => 'install_pwa',
        'is_completed' => 1,
        'completed_at' => '2026-06-03 11:00:00',
        'is_ignored' => 1,
        'ignored_at' => '2026-06-02 10:00:00',
    ],
    $manual_task
);
ac_assert(
    'completed manual precedence over ignored',
    ($completed_beats_ignored['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_COMPLETE_MANUAL
);

$ignored_beats_dismissed = map_legacy(
    [
        'recommendation_key' => 'install_pwa',
        'is_ignored' => 1,
        'ignored_at' => '2026-06-02 10:00:00',
        'is_dismissed' => 1,
        'dismissed_at' => '2026-06-01 09:00:00',
    ],
    $manual_task
);
ac_assert(
    'ignored precedence over dismissed',
    ($ignored_beats_dismissed['result'] ?? '') === AA_Learning_Legacy_State_To_Task_State_Mapper::RESULT_DEFER
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
