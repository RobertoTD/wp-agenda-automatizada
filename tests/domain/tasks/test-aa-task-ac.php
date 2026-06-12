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
    'normalize_status still only pending or done',
    AA_Task::from_array(['status' => 'archived'])->status() === 'pending'
    && AA_Task::from_array(['status' => 'done'])->status() === 'done'
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
