<?php
/**
 * AC MC1 — AA_Task_Execution_Timing_Policy.
 *
 * Ejecutar: php tests/domain/tasks/test-aa-task-execution-timing-policy-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';
require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task-execution-timing-policy.php';

$total = 0;
$passed = 0;
$failed = [];

const EXECUTION_TIMING_FLOAT_EPSILON = 0.0001;

function execution_timing_assert(string $label, bool $ok, string $detail = ''): void {
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

function execution_timing_assert_float(string $label, float $actual, float $expected): void {
    $delta = abs($actual - $expected);
    execution_timing_assert(
        $label,
        $delta < EXECUTION_TIMING_FLOAT_EPSILON,
        sprintf('actual=%.10f expected=%.10f delta=%.10f', $actual, $expected, $delta)
    );
}

/**
 * @param array<string,mixed> $task_data
 * @return array<string,mixed>
 */
function execution_timing_evaluate(
    AA_Task_Execution_Timing_Policy $policy,
    array $task_data,
    string $now = '2026-06-01 12:00:00'
): array {
    return $policy->evaluate(AA_Task::from_array($task_data), $now);
}

function execution_timing_expected_score(
    int $importance,
    int $elapsed_seconds,
    int $window_seconds
): float {
    $progress = max(0.0, min(1.0, $elapsed_seconds / $window_seconds));
    $multiplier = 1.0 + pow($progress, 1.5);

    return (float) ($importance + abs($importance) * ($multiplier - 1.0));
}

$original_timezone = date_default_timezone_get();
date_default_timezone_set('Asia/Tokyo');

$policy = new AA_Task_Execution_Timing_Policy(new DateTimeZone('America/Mexico_City'));

// ─── Capas y fronteras ───────────────────────────────────────

$layer_2 = execution_timing_evaluate($policy, ['importance' => 50]);
execution_timing_assert(
    'T01 no temporal dates is layer 2',
    $layer_2 === [
        'has_temporal_condition' => false,
        'is_temporal_condition_pending' => false,
        'is_temporal_condition_met' => false,
        'temporal_layer' => 2,
        'priority_score' => 50.0,
    ]
);

$layer_1 = execution_timing_evaluate($policy, [
    'importance' => 30,
    'execution_available_at' => '2026-06-10 10:00:00',
]);
execution_timing_assert(
    'T02 future execution condition is layer 1',
    $layer_1['temporal_layer'] === 1
    && $layer_1['has_temporal_condition'] === true
    && $layer_1['is_temporal_condition_pending'] === true
    && $layer_1['is_temporal_condition_met'] === false
    && $layer_1['priority_score'] === 30.0
);

$layer_3_without_due = execution_timing_evaluate($policy, [
    'importance' => 30,
    'execution_available_at' => '2026-06-01 08:00:00',
]);
execution_timing_assert(
    'T03 met execution condition without due is layer 3',
    $layer_3_without_due['temporal_layer'] === 3
    && $layer_3_without_due['is_temporal_condition_met'] === true
    && $layer_3_without_due['priority_score'] === 30.0
);

$execution_equals_now = execution_timing_evaluate($policy, [
    'importance' => 25,
    'execution_available_at' => '2026-06-01 12:00:00',
    'due_at' => '2026-06-01 16:00:00',
]);
execution_timing_assert(
    'T16 execution_available_at equal now is met in layer 3',
    $execution_equals_now['temporal_layer'] === 3
    && $execution_equals_now['is_temporal_condition_met'] === true
    && $execution_equals_now['priority_score'] === 25.0
);

$due_equals_now = execution_timing_evaluate($policy, [
    'importance' => 60,
    'execution_available_at' => '2026-06-01 08:00:00',
    'due_at' => '2026-06-01 12:00:00',
]);
execution_timing_assert(
    'T10 due_at equal now is absolute layer 4',
    $due_equals_now['temporal_layer'] === 4
    && $due_equals_now['priority_score'] === 60.0
);

$future_execution_overdue = execution_timing_evaluate($policy, [
    'importance' => 40,
    'execution_available_at' => '2026-06-10 10:00:00',
    'due_at' => '2026-06-01 09:00:00',
]);
execution_timing_assert(
    'T12 overdue dominates future execution condition',
    $future_execution_overdue['temporal_layer'] === 4
    && $future_execution_overdue['is_temporal_condition_pending'] === true
);

$absent_execution_overdue = execution_timing_evaluate($policy, [
    'importance' => 35,
    'due_at' => '2026-06-01 09:00:00',
]);
execution_timing_assert(
    'Absolute layer 4 also dominates absent execution condition',
    $absent_execution_overdue['temporal_layer'] === 4
    && $absent_execution_overdue['has_temporal_condition'] === false
    && $absent_execution_overdue['priority_score'] === 35.0
);

// ─── Score con importance positiva, negativa y cero ─────────

$positive_midpoint = execution_timing_evaluate($policy, [
    'importance' => 80,
    'execution_available_at' => '2026-06-01 08:00:00',
    'due_at' => '2026-06-01 16:00:00',
]);
execution_timing_assert('T04 positive midpoint remains layer 3', $positive_midpoint['temporal_layer'] === 3);
execution_timing_assert_float(
    'T04 positive midpoint uses progress 0.5 curve',
    $positive_midpoint['priority_score'],
    execution_timing_expected_score(80, 4 * 3600, 8 * 3600)
);

$negative_near_multiplier_1_5 = execution_timing_evaluate(
    $policy,
    [
        'importance' => -90,
        'execution_available_at' => '2026-06-01 08:00:00',
        'due_at' => '2026-06-01 16:00:00',
    ],
    '2026-06-01 13:02:23'
);
execution_timing_assert_float(
    'T05 negative importance moves toward zero at progress near 0.62996',
    $negative_near_multiplier_1_5['priority_score'],
    execution_timing_expected_score(-90, 18143, 28800)
);
execution_timing_assert(
    'T05 negative score remains below zero in layer 3',
    $negative_near_multiplier_1_5['temporal_layer'] === 3
    && $negative_near_multiplier_1_5['priority_score'] < 0.0
    && $negative_near_multiplier_1_5['priority_score'] > -90.0
);

$zero_importance = execution_timing_evaluate($policy, [
    'importance' => 0,
    'execution_available_at' => '2026-06-01 08:00:00',
    'due_at' => '2026-06-01 16:00:00',
]);
execution_timing_assert_float('T07 zero importance stays zero', $zero_importance['priority_score'], 0.0);

// ─── Parser estricto y fallbacks legacy ─────────────────────

$impossible_execution = execution_timing_evaluate($policy, [
    'importance' => 10,
    'execution_available_at' => '2026-19-45 67:80:90',
]);
execution_timing_assert(
    'Impossible execution datetime falls back to absent condition',
    $impossible_execution['temporal_layer'] === 2
    && $impossible_execution['has_temporal_condition'] === false
);

$impossible_due = execution_timing_evaluate($policy, [
    'importance' => 10,
    'execution_available_at' => '2026-06-01 08:00:00',
    'due_at' => '2026-02-30 12:00:00',
]);
execution_timing_assert(
    'T14 impossible due datetime falls back to no due date',
    $impossible_due['temporal_layer'] === 3
    && $impossible_due['priority_score'] === 10.0
);

$malformed_both = execution_timing_evaluate($policy, [
    'importance' => 10,
    'execution_available_at' => 'foo',
    'due_at' => 'bar',
]);
execution_timing_assert(
    'T15 malformed datetimes fall back deterministically',
    $malformed_both['temporal_layer'] === 2
    && $malformed_both['priority_score'] === 10.0
);

$trimmed_dates = execution_timing_evaluate($policy, [
    'importance' => 15,
    'execution_available_at' => ' 2026-06-01 08:00:00 ',
    'due_at' => ' 2026-06-01 16:00:00 ',
]);
execution_timing_assert(
    'Strict parser accepts exact datetime after trimming',
    $trimmed_dates['temporal_layer'] === 3
    && $trimmed_dates['has_temporal_condition'] === true
);

$invalid_now = execution_timing_evaluate(
    $policy,
    [
        'importance' => 20,
        'execution_available_at' => '2026-06-01 08:00:00',
        'due_at' => '2026-06-01 09:00:00',
    ],
    'invalid-now'
);
execution_timing_assert(
    'Invalid now uses deterministic layer 2 fallback',
    $invalid_now === [
        'has_temporal_condition' => false,
        'is_temporal_condition_pending' => false,
        'is_temporal_condition_met' => false,
        'temporal_layer' => 2,
        'priority_score' => 20.0,
    ]
);

// ─── Precedencia absoluta y ventana inválida ────────────────

$t08 = execution_timing_evaluate(
    $policy,
    [
        'importance' => 50,
        'execution_available_at' => '2026-06-01 10:00:00',
        'due_at' => '2026-06-01 10:00:00',
    ],
    '2026-06-01 12:00:00'
);
execution_timing_assert(
    'T08 invalid window remains absolute layer 4 when due is past',
    $t08['temporal_layer'] === 4 && $t08['priority_score'] === 50.0
);

$t13 = execution_timing_evaluate(
    $policy,
    [
        'importance' => 10,
        'execution_available_at' => '2026-19-45 67:80:90',
        'due_at' => '2026-06-01 09:00:00',
    ],
    '2026-06-01 12:00:00'
);
execution_timing_assert(
    'T13 invalid execution date does not neutralize valid overdue date',
    $t13['temporal_layer'] === 4
    && $t13['has_temporal_condition'] === false
    && $t13['priority_score'] === 10.0
);

$invalid_future_window = execution_timing_evaluate(
    $policy,
    [
        'importance' => -20,
        'execution_available_at' => '2026-06-03 12:00:00',
        'due_at' => '2026-06-02 12:00:00',
    ],
    '2026-06-01 12:00:00'
);
execution_timing_assert(
    'Future invalid window remains layer 1 without urgency score',
    $invalid_future_window['temporal_layer'] === 1
    && $invalid_future_window['priority_score'] === -20.0
);

// ─── Transición un segundo antes del vencimiento ────────────

$one_second_before_due = execution_timing_evaluate(
    $policy,
    [
        'importance' => -90,
        'execution_available_at' => '2026-06-01 08:00:00',
        'due_at' => '2026-06-01 12:00:00',
    ],
    '2026-06-01 11:59:59'
);
$expected_before_due = execution_timing_expected_score(-90, 14399, 14400);
execution_timing_assert(
    'X01 one second before due remains layer 3 with negative score',
    $one_second_before_due['temporal_layer'] === 3
    && $one_second_before_due['priority_score'] < 0.0
);
execution_timing_assert_float(
    'X01 one second before due follows the limiting curve',
    $one_second_before_due['priority_score'],
    $expected_before_due
);

$at_due = execution_timing_evaluate(
    $policy,
    [
        'importance' => -90,
        'execution_available_at' => '2026-06-01 08:00:00',
        'due_at' => '2026-06-01 12:00:00',
    ],
    '2026-06-01 12:00:00'
);
execution_timing_assert(
    'X02 at due instant transitions to layer 4 and stops using urgency score',
    $at_due['temporal_layer'] === 4
    && $at_due['priority_score'] === -90.0
);

// La TZ global es deliberadamente distinta: todos los resultados anteriores
// deben depender exclusivamente de la zona inyectada en la policy.
execution_timing_assert(
    'Policy behavior is independent from PHP global timezone',
    date_default_timezone_get() === 'Asia/Tokyo'
);

$public_methods = array_map(
    static function (ReflectionMethod $method): string {
        return $method->getName();
    },
    (new ReflectionClass(AA_Task_Execution_Timing_Policy::class))->getMethods(ReflectionMethod::IS_PUBLIC)
);
sort($public_methods);
execution_timing_assert(
    'Production policy exposes no test-only public helpers',
    $public_methods === ['__construct', 'evaluate']
);

date_default_timezone_set($original_timezone);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
