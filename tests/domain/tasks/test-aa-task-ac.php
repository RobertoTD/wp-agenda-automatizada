<?php
/**
 * AC — AA_Task (archived_at eje de visibilidad independiente de status).
 *
 * Ejecutar: php tests/domain/tasks/test-aa-task-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/tasks/class-aa-task.php';

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

$archived_at = '2026-06-10 14:30:00';
$execution_available_at = '2026-06-20 08:30:00';

ac_assert(
    'from_array preserves archived_at',
    AA_Task::from_array(['archived_at' => $archived_at])->archived_at() === $archived_at
);
ac_assert(
    'to_array includes archived_at',
    (AA_Task::from_array(['archived_at' => $archived_at])->to_array()['archived_at'] ?? null) === $archived_at
);
ac_assert(
    'default archived_at is null',
    AA_Task::from_array([])->archived_at() === null
);
ac_assert(
    'is_archived false when archived_at null',
    AA_Task::from_array(['archived_at' => null])->is_archived() === false
);
ac_assert(
    'is_archived false when archived_at empty string',
    AA_Task::from_array(['archived_at' => ''])->is_archived() === false
);
ac_assert(
    'is_archived true with valid datetime',
    AA_Task::from_array(['archived_at' => $archived_at])->is_archived() === true
);

$pending_archived = AA_Task::from_array([
    'status' => 'pending',
    'archived_at' => $archived_at,
]);
ac_assert(
    'pending + archived_at keeps is_pending true',
    $pending_archived->is_pending() === true && $pending_archived->is_archived() === true
);

$done_archived = AA_Task::from_array([
    'status' => 'done',
    'archived_at' => $archived_at,
]);
ac_assert(
    'done + archived_at keeps is_done true',
    $done_archived->is_done() === true && $done_archived->is_archived() === true
);

ac_assert(
    'normalize_status maps unknown to pending and keeps done',
    AA_Task::from_array(['status' => 'archived'])->status() === 'pending'
    && AA_Task::from_array(['status' => 'done'])->status() === 'done'
);

// ─── MC4: status missed ──────────────────────────────────────

ac_assert(
    'normalize_status accepts missed as valid status',
    AA_Task::from_array(['status' => 'missed'])->status() === AA_Task::STATUS_MISSED
);
ac_assert(
    'normalize_status accepts MISSED case-insensitive',
    AA_Task::from_array(['status' => 'MISSED'])->status() === AA_Task::STATUS_MISSED
);

$missed_task = AA_Task::from_array(['status' => 'missed', 'due_at' => '2026-06-01 08:00:00']);
ac_assert(
    'is_missed true and is_pending false for missed',
    $missed_task->is_missed() === true
    && $missed_task->is_pending() === false
    && $missed_task->is_done() === false
);
ac_assert(
    'missed task is not overdue (only pending tasks can be overdue)',
    $missed_task->is_overdue('2026-06-10 12:00:00') === false
);
ac_assert(
    'missed task does not carry completed_at',
    AA_Task::from_array(['status' => 'missed'])->to_array()['completed_at'] === null
);

ac_assert(
    'from_array preserves execution_available_at',
    AA_Task::from_array(['execution_available_at' => $execution_available_at])->execution_available_at() === $execution_available_at
);
ac_assert(
    'to_array includes execution_available_at',
    (AA_Task::from_array(['execution_available_at' => $execution_available_at])->to_array()['execution_available_at'] ?? null) === $execution_available_at
);
ac_assert(
    'default execution_available_at is null',
    AA_Task::from_array([])->execution_available_at() === null
);
ac_assert(
    'execution_available_at empty string normalizes to null',
    AA_Task::from_array(['execution_available_at' => ''])->execution_available_at() === null
);
ac_assert(
    'due_at semantics unchanged when execution_available_at present',
    AA_Task::from_array([
        'status' => 'pending',
        'due_at' => '2026-06-01 08:00:00',
        'execution_available_at' => $execution_available_at,
    ])->is_overdue('2026-06-10 12:00:00') === true
    && AA_Task::from_array([
        'status' => 'pending',
        'due_at' => '2026-06-01 08:00:00',
        'execution_available_at' => $execution_available_at,
    ])->execution_available_at() === $execution_available_at
);

ac_assert(
    'from_array preserves optional origin_key',
    AA_Task::from_array(['origin_key' => 'enable_push'])->origin_key() === 'enable_push'
);
ac_assert(
    'origin_key defaults to null when omitted',
    AA_Task::from_array([])->origin_key() === null
);
ac_assert(
    'blank origin_key normalizes to null',
    AA_Task::from_array(['origin_key' => '   '])->origin_key() === null
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
