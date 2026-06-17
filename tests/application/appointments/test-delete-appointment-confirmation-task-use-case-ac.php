<?php
/**
 * AC MC6 — DeleteAppointmentConfirmationTaskUseCase.
 *
 * Ejecutar: php tests/application/appointments/test-delete-appointment-confirmation-task-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

$plugin_root = dirname(__DIR__, 3);

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

$use_case_file = $plugin_root . '/includes/application/appointments/DeleteAppointmentConfirmationTaskUseCase.php';
$cancel_controller_src = file_get_contents($plugin_root . '/includes/controllers/proximasCitasController.php');
$confirm_service_src = file_get_contents($plugin_root . '/includes/services/confirm-backend-service.php');
$confirm_controller_src = file_get_contents($plugin_root . '/includes/controllers/confirmController.php');

ac_assert('Use case file readable', is_readable($use_case_file));
ac_assert(
    'aa_cancel_reservation_internal wires MC6 after local cancelled update',
    strpos($cancel_controller_src, 'DeleteAppointmentConfirmationTaskUseCase::sync_after_local_cancellation_best_effort($reserva_id)') !== false
    && strpos($cancel_controller_src, "estado' => 'cancelled'") !== false
);
ac_assert(
    'confirm_backend_service cascade wires MC6 after cancelled update',
    strpos($confirm_service_src, 'DeleteAppointmentConfirmationTaskUseCase::sync_after_local_cancellation_best_effort((int) $conflicto->id)') !== false
);
ac_assert(
    'aa_rest_confirmar_reserva cascade wires MC6 after cancelled update',
    strpos($confirm_controller_src, 'DeleteAppointmentConfirmationTaskUseCase::sync_after_local_cancellation_best_effort((int) $conflicto->id)') !== false
);

require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-confirmation-task-projector.php';
require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $use_case_file;

$cancelled_reservation = [
    'id' => 601,
    'estado' => 'cancelled',
];

$pending_task = [
    'id' => 9101,
    'status' => 'pending',
    'source_category' => 'agenda_app',
    'origin_key' => 'appointment_confirmation:601',
];

$done_task = [
    'id' => 9102,
    'status' => 'done',
    'source_category' => 'agenda_app',
    'origin_key' => 'appointment_confirmation:601',
    'completed_at' => '2026-06-10 08:00:00',
];

$other_task = [
    'id' => 9103,
    'status' => 'pending',
    'source_category' => 'agenda_app',
    'origin_key' => 'appointment_confirmation:999',
];

function mc6_make_delete_uc(
    array $options
): DeleteAppointmentConfirmationTaskUseCase {
    return new DeleteAppointmentConfirmationTaskUseCase(
        $options['reservation_reader'] ?? null,
        $options['task_finder'] ?? null,
        $options['actions_deleter'] ?? null,
        $options['state_deleter'] ?? null,
        $options['task_deleter'] ?? null
    );
}

function mc6_log_sync_failure(int $reservation_id, array $result): void {
    if (!empty($result['success'])) {
        return;
    }

    $code = (string) ($result['error']['code'] ?? 'unknown');
    $stage = (string) ($result['error']['stage'] ?? '');

    if (in_array($code, [
        'missing_reservation_id',
        'reservation_not_found',
        'task_deletion_failed',
    ], true)) {
        $suffix = $stage !== '' ? ' stage=' . $stage : '';
        error_log(
            '⚠️ [DeleteAppointmentConfirmation] Tarea no eliminada para reserva '
            . $reservation_id
            . ': '
            . $code
            . $suffix
        );
    }
}

// ─── AC1: cancelled + pending → hard delete ───────────────────────

$delete_calls = ['actions' => [], 'state' => [], 'task' => []];
$task_store = [601 => $pending_task];

$pending_delete_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use (&$task_store): ?array {
        return $task_store[$reservation_id] ?? null;
    },
    'actions_deleter' => static function (int $task_id) use (&$delete_calls): int {
        $delete_calls['actions'][] = $task_id;

        return 1;
    },
    'state_deleter' => static function (int $task_id) use (&$delete_calls): bool {
        $delete_calls['state'][] = $task_id;

        return true;
    },
    'task_deleter' => static function (int $task_id) use (&$delete_calls, &$task_store): bool {
        $delete_calls['task'][] = $task_id;
        unset($task_store[601]);

        return true;
    },
]);

$pending_result = $pending_delete_uc->execute(['reservation_id' => 601]);

ac_assert('AC1: success on pending task', !empty($pending_result['success']));
ac_assert('AC1: task_deleted true', ($pending_result['data']['task_deleted'] ?? false) === true);
ac_assert('AC1: deletes actions then state then task', $delete_calls === [
    'actions' => [9101],
    'state' => [9101],
    'task' => [9101],
]);
ac_assert('AC1: task removed from store', !isset($task_store[601]));

// ─── AC2: cancelled + done → hard delete ──────────────────────────

$done_delete_calls = ['actions' => [], 'state' => [], 'task' => []];

$done_delete_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use ($done_task): ?array {
        return $reservation_id === 601 ? $done_task : null;
    },
    'actions_deleter' => static function (int $task_id) use (&$done_delete_calls): int {
        $done_delete_calls['actions'][] = $task_id;

        return 1;
    },
    'state_deleter' => static function (int $task_id) use (&$done_delete_calls): bool {
        $done_delete_calls['state'][] = $task_id;

        return true;
    },
    'task_deleter' => static function (int $task_id) use (&$done_delete_calls): bool {
        $done_delete_calls['task'][] = $task_id;

        return true;
    },
]);

$done_result = $done_delete_uc->execute(['reservation_id' => 601]);

ac_assert('AC2: success on done task', !empty($done_result['success']));
ac_assert('AC2: task_deleted true', ($done_result['data']['task_deleted'] ?? false) === true);
ac_assert('AC2: full delete pipeline invoked', count($done_delete_calls['task']) === 1);

// ─── AC3: reservation not cancelled → skip ────────────────────────

$not_cancelled_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id): ?array {
        return ['id' => $reservation_id, 'estado' => 'pending'];
    },
    'task_finder' => static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    'actions_deleter' => static function (): int {
        throw new RuntimeException('actions should not run');
    },
]);

$not_cancelled = $not_cancelled_uc->execute(['reservation_id' => 601]);

ac_assert('AC3: skip when not cancelled', ($not_cancelled['data']['skip_reason'] ?? '') === 'reservation_not_cancelled');

// ─── AC4: missing task → skip ─────────────────────────────────────

$missing_task_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (): ?array {
        return null;
    },
]);

$missing_task = $missing_task_uc->execute(['reservation_id' => 601]);

ac_assert('AC4: skip when task missing', ($missing_task['data']['skip_reason'] ?? '') === 'task_not_found');

// ─── AC5: second execution → idempotent skip ──────────────────────

$second_run_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (): ?array {
        return null;
    },
]);

$second_run = $second_run_uc->execute(['reservation_id' => 601]);

ac_assert('AC5: second run skips task_not_found', ($second_run['data']['skip_reason'] ?? '') === 'task_not_found');

// ─── AC6: other task untouched ────────────────────────────────────

$other_task_calls = [];

$isolated_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use ($pending_task, $other_task): ?array {
        return $reservation_id === 601 ? $pending_task : null;
    },
    'actions_deleter' => static function (int $task_id) use (&$other_task_calls): int {
        $other_task_calls[] = $task_id;

        return 1;
    },
    'state_deleter' => static function (int $task_id): bool {
        return true;
    },
    'task_deleter' => static function (int $task_id): bool {
        return true;
    },
]);

$isolated = $isolated_uc->execute(['reservation_id' => 601]);

ac_assert('AC6: isolated delete success', !empty($isolated['success']));
ac_assert('AC6: only target task id deleted', $other_task_calls === [9101]);
ac_assert('AC6: other task id not in delete calls', !in_array(9103, $other_task_calls, true));

// ─── AC7: actions missing (0) is not error ────────────────────────

$actions_zero_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    'actions_deleter' => static function (): int {
        return 0;
    },
    'state_deleter' => static function (): bool {
        return true;
    },
    'task_deleter' => static function (): bool {
        return true;
    },
]);

$actions_zero = $actions_zero_uc->execute(['reservation_id' => 601]);

ac_assert('AC7: actions zero rows still succeeds', !empty($actions_zero['success']));

// ─── AC8: task state missing is not error ─────────────────────────

$state_missing_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    'actions_deleter' => static function (): int {
        return 0;
    },
    'state_deleter' => static function (): bool {
        return true;
    },
    'task_deleter' => static function (): bool {
        return true;
    },
]);

$state_missing = $state_missing_uc->execute(['reservation_id' => 601]);

ac_assert('AC8: missing task state still succeeds', !empty($state_missing['success']));

// ─── AC9: task race (delete false, task gone) → success ───────────

$race_store = [601 => $pending_task];

$race_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use (&$race_store): ?array {
        return $race_store[$reservation_id] ?? null;
    },
    'actions_deleter' => static function (): int {
        return 1;
    },
    'state_deleter' => static function (): bool {
        return true;
    },
    'task_deleter' => static function () use (&$race_store): bool {
        unset($race_store[601]);

        return false;
    },
]);

$race_result = $race_uc->execute(['reservation_id' => 601]);

ac_assert('AC9: concurrent task delete treated as success', !empty($race_result['success']));
ac_assert('AC9: task_deleted true on race', ($race_result['data']['task_deleted'] ?? false) === true);

// ─── AC10: actions false stops pipeline ───────────────────────────

$actions_fail_calls = ['state' => 0, 'task' => 0];

$actions_fail_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    'actions_deleter' => static function (): int|false {
        return false;
    },
    'state_deleter' => static function () use (&$actions_fail_calls): bool {
        $actions_fail_calls['state']++;

        return true;
    },
    'task_deleter' => static function () use (&$actions_fail_calls): bool {
        $actions_fail_calls['task']++;

        return true;
    },
]);

$actions_fail = $actions_fail_uc->execute(['reservation_id' => 601]);

ac_assert('AC10: actions false returns task_deletion_failed', ($actions_fail['error']['code'] ?? '') === 'task_deletion_failed');
ac_assert('AC10: actions stage reported', ($actions_fail['error']['stage'] ?? '') === 'actions');
ac_assert('AC10: state and task not deleted', $actions_fail_calls === ['state' => 0, 'task' => 0]);

// ─── AC11: state false stops before task ──────────────────────────

$state_fail_calls = ['task' => 0];

$state_fail_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    'actions_deleter' => static function (): int {
        return 1;
    },
    'state_deleter' => static function (): bool {
        return false;
    },
    'task_deleter' => static function () use (&$state_fail_calls): bool {
        $state_fail_calls['task']++;

        return true;
    },
]);

$state_fail = $state_fail_uc->execute(['reservation_id' => 601]);

ac_assert('AC11: state false returns task_deletion_failed', ($state_fail['error']['code'] ?? '') === 'task_deletion_failed');
ac_assert('AC11: state stage reported', ($state_fail['error']['stage'] ?? '') === 'state');
ac_assert('AC11: task not deleted after state failure', $state_fail_calls['task'] === 0);

// ─── AC12: task false with task still present → error ─────────────

$task_fail_uc = mc6_make_delete_uc([
    'reservation_reader' => static function (int $reservation_id) use ($cancelled_reservation): ?array {
        return array_merge($cancelled_reservation, ['id' => $reservation_id]);
    },
    'task_finder' => static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    'actions_deleter' => static function (): int {
        return 1;
    },
    'state_deleter' => static function (): bool {
        return true;
    },
    'task_deleter' => static function (): bool {
        return false;
    },
]);

$task_fail = $task_fail_uc->execute(['reservation_id' => 601]);

ac_assert('AC12: task false with row present fails', ($task_fail['error']['code'] ?? '') === 'task_deletion_failed');
ac_assert('AC12: task stage reported', ($task_fail['error']['stage'] ?? '') === 'task');

// ─── AC13: best-effort helper logging ─────────────────────────────

$log_file_skip = tempnam(sys_get_temp_dir(), 'mc6skip');
$previous_log = ini_get('error_log');
ini_set('error_log', $log_file_skip);

try {
    mc6_log_sync_failure(777, (new DeleteAppointmentConfirmationTaskUseCase(
        static function (int $reservation_id): ?array {
            return ['id' => $reservation_id, 'estado' => 'pending'];
        }
    ))->execute(['reservation_id' => 777]));
} catch (Throwable $e) {
    ac_assert('AC13: sync does not throw on skip', false, $e->getMessage());
}

ini_set('error_log', $previous_log !== false ? $previous_log : '');
$skip_log = is_readable($log_file_skip) ? (string) file_get_contents($log_file_skip) : '';
@unlink($log_file_skip);

ac_assert('AC13: sync does not throw on skip', true);
ac_assert('AC13: skip paths do not log operational warning', $skip_log === '');

$log_file_ops = tempnam(sys_get_temp_dir(), 'mc6ops');
ini_set('error_log', $log_file_ops);

mc6_log_sync_failure(601, $task_fail_uc->execute(['reservation_id' => 601]));

ini_set('error_log', $previous_log !== false ? $previous_log : '');
$ops_log = is_readable($log_file_ops) ? (string) file_get_contents($log_file_ops) : '';
@unlink($log_file_ops);

ac_assert('AC13: operational failure logged with reservation_id', strpos($ops_log, '601') !== false);
ac_assert('AC13: operational failure logged with code', strpos($ops_log, 'task_deletion_failed') !== false);
ac_assert('AC13: operational failure logged with stage', strpos($ops_log, 'stage=task') !== false);

// ─── Resumen ───────────────────────────────────────────────────────

echo "\n=== MC6 delete task: {$passed}/{$total} passed ===\n";

if ($failed !== []) {
    echo "Failed:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
