<?php
/**
 * AC MC4 — CompleteAppointmentConfirmationTaskUseCase.
 *
 * Ejecutar: php tests/application/appointments/test-complete-appointment-confirmation-task-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-06-17 14:30:00' : time();
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

$use_case_file = $plugin_root . '/includes/application/appointments/CompleteAppointmentConfirmationTaskUseCase.php';
$confirm_service_src = file_get_contents($plugin_root . '/includes/services/confirm-backend-service.php');
$confirm_controller_src = file_get_contents($plugin_root . '/includes/controllers/confirmController.php');

ac_assert('Use case file readable', is_readable($use_case_file));
ac_assert(
    'confirm_backend_service delegates confirmation to ConfirmReservationUseCase',
    strpos($confirm_service_src, 'ConfirmReservationUseCase') !== false
    && strpos($confirm_service_src, "\$wpdb->update(\$table, ['estado' => 'confirmed']") === false
    && strpos($confirm_service_src, 'CompleteAppointmentConfirmationTaskUseCase::sync_after_local_confirmation_best_effort($reserva_id)') === false
);
ac_assert(
    'aa_rest_confirmar_reserva delegates confirmation to ConfirmReservationUseCase',
    strpos($confirm_controller_src, 'ConfirmReservationUseCase') !== false
    && strpos($confirm_controller_src, "\$update_data = ['estado' => 'confirmed']") === false
    && strpos($confirm_controller_src, 'CompleteAppointmentConfirmationTaskUseCase::sync_after_local_confirmation_best_effort($id)') === false
);

require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-confirmation-task-projector.php';
require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $use_case_file;

$confirmed_reservation = [
    'id' => 501,
    'estado' => 'confirmed',
];

$pending_task = [
    'id' => 9001,
    'status' => 'pending',
    'source_category' => 'agenda_app',
    'origin_key' => 'appointment_confirmation:501',
    'completed_at' => null,
];

$done_task = [
    'id' => 9002,
    'status' => 'done',
    'source_category' => 'agenda_app',
    'origin_key' => 'appointment_confirmation:501',
    'completed_at' => '2026-06-10 08:00:00',
];

$other_task = [
    'id' => 9003,
    'status' => 'archived',
    'source_category' => 'agenda_app',
    'origin_key' => 'appointment_confirmation:999',
];

// ─── AC1: confirmed + pending → done ───────────────────────────────

$completed_at = '2026-06-17 14:30:00';
$complete_calls = [];

$complete_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($confirmed_reservation): ?array {
        return array_merge($confirmed_reservation, ['id' => $reservation_id]);
    },
    static function (int $reservation_id) use ($pending_task): ?array {
        return $reservation_id === 501 ? $pending_task : null;
    },
    static function (int $task_id, string $at) use (&$complete_calls, $completed_at): ?array {
        $complete_calls[] = ['task_id' => $task_id, 'completed_at' => $at];

        return [
            'id' => $task_id,
            'status' => 'done',
            'completed_at' => $completed_at,
        ];
    }
);

$first = $complete_uc->execute(['reservation_id' => 501]);

ac_assert('AC1: success on pending task', !empty($first['success']));
ac_assert('AC1: task_completed true', ($first['data']['task_completed'] ?? false) === true);
ac_assert('AC1: not skipped', ($first['data']['skipped'] ?? true) === false);
ac_assert('AC1: mark_completed invoked once', count($complete_calls) === 1);
ac_assert('AC1: correct task_id', ($complete_calls[0]['task_id'] ?? 0) === 9001);
ac_assert('AC1: result status done', ($first['data']['task']['status'] ?? '') === 'done');
ac_assert('AC1: completed_at set', ($first['data']['task']['completed_at'] ?? '') === $completed_at);

// ─── AC2: already done → skip, preserve completed_at ───────────────

$complete_calls = [];

$done_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($confirmed_reservation): ?array {
        return array_merge($confirmed_reservation, ['id' => $reservation_id]);
    },
    static function (int $reservation_id) use ($done_task): ?array {
        return $reservation_id === 501 ? $done_task : null;
    },
    static function (int $task_id, string $at) use (&$complete_calls): ?array {
        $complete_calls[] = $task_id;

        return null;
    }
);

$already_done = $done_uc->execute(['reservation_id' => 501]);

ac_assert('AC2: skip success', !empty($already_done['success']));
ac_assert('AC2: skip_reason task_already_completed', ($already_done['data']['skip_reason'] ?? '') === 'task_already_completed');
ac_assert('AC2: mark_completed not called', $complete_calls === []);
ac_assert('AC2: completed_at preserved', ($already_done['data']['task']['completed_at'] ?? '') === '2026-06-10 08:00:00');

// ─── AC3: task not found → skip ────────────────────────────────────

$missing_task_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($confirmed_reservation): ?array {
        return array_merge($confirmed_reservation, ['id' => $reservation_id]);
    },
    static function (int $reservation_id): ?array {
        return null;
    }
);

$no_task = $missing_task_uc->execute(['reservation_id' => 501]);

ac_assert('AC3: skip when task missing', !empty($no_task['success']) && ($no_task['data']['skip_reason'] ?? '') === 'task_not_found');

// ─── AC4: reservation not confirmed → skip ─────────────────────────

$not_confirmed_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id): ?array {
        return ['id' => $reservation_id, 'estado' => 'pending'];
    },
    static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    static function (int $task_id, string $at): ?array {
        return null;
    }
);

$not_confirmed = $not_confirmed_uc->execute(['reservation_id' => 501]);

ac_assert('AC4: skip reservation_not_confirmed', !empty($not_confirmed['success']));
ac_assert('AC4: not an error', !isset($not_confirmed['error']));
ac_assert('AC4: skip_reason', ($not_confirmed['data']['skip_reason'] ?? '') === 'reservation_not_confirmed');

// ─── AC5: unexpected task status → skip ──────────────────────────

$unexpected_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($confirmed_reservation): ?array {
        return array_merge($confirmed_reservation, ['id' => $reservation_id]);
    },
    static function (int $reservation_id): ?array {
        return [
            'id' => 9010,
            'status' => 'archived',
            'origin_key' => 'appointment_confirmation:501',
        ];
    }
);

$unexpected = $unexpected_uc->execute(['reservation_id' => 501]);

ac_assert('AC5: skip task_not_pending', !empty($unexpected['success']));
ac_assert('AC5: skip_reason', ($unexpected['data']['skip_reason'] ?? '') === 'task_not_pending');

// ─── AC6: mark_completed failure → operational error ───────────────

$fail_complete_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($confirmed_reservation): ?array {
        return array_merge($confirmed_reservation, ['id' => $reservation_id]);
    },
    static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    static function (int $task_id, string $at): ?array {
        return null;
    }
);

$fail_complete = $fail_complete_uc->execute(['reservation_id' => 501]);

ac_assert('AC6: task_completion_failed', empty($fail_complete['success']));
ac_assert('AC6: error code', ($fail_complete['error']['code'] ?? '') === 'task_completion_failed');

// ─── AC7: only matching origin_key modified ──────────────────────

$tasks_store = [
    501 => $pending_task,
    999 => $other_task,
];

$isolated_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($confirmed_reservation): ?array {
        return array_merge($confirmed_reservation, ['id' => $reservation_id]);
    },
    static function (int $reservation_id) use (&$tasks_store): ?array {
        return $tasks_store[$reservation_id] ?? null;
    },
    static function (int $task_id, string $at) use (&$tasks_store): ?array {
        foreach ($tasks_store as $reservation_id => $task) {
            if ((int) ($task['id'] ?? 0) !== $task_id) {
                continue;
            }

            $tasks_store[$reservation_id] = array_merge($task, [
                'status' => 'done',
                'completed_at' => $at,
            ]);

            return $tasks_store[$reservation_id];
        }

        return null;
    }
);

$isolated = $isolated_uc->execute(['reservation_id' => 501]);

ac_assert('AC7: target task completed', ($tasks_store[501]['status'] ?? '') === 'done');
ac_assert('AC7: other task untouched', ($tasks_store[999]['status'] ?? '') === 'archived');
ac_assert('AC7: isolated success', !empty($isolated['success']));

// ─── AC8: second execution idempotent ────────────────────────────

$idempotent_task = $pending_task;
$idempotent_calls = 0;

$idempotent_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($confirmed_reservation): ?array {
        return array_merge($confirmed_reservation, ['id' => $reservation_id]);
    },
    static function (int $reservation_id) use (&$idempotent_task): ?array {
        return $reservation_id === 501 ? $idempotent_task : null;
    },
    static function (int $task_id, string $at) use (&$idempotent_task, &$idempotent_calls, $completed_at): ?array {
        $idempotent_calls++;
        $idempotent_task = array_merge($idempotent_task, [
            'status' => 'done',
            'completed_at' => $completed_at,
        ]);

        return $idempotent_task;
    }
);

$idempotent_first = $idempotent_uc->execute(['reservation_id' => 501]);
$idempotent_second = $idempotent_uc->execute(['reservation_id' => 501]);

ac_assert('AC8: first run completes', ($idempotent_first['data']['task_completed'] ?? false) === true);
ac_assert('AC8: second run still success', !empty($idempotent_second['success']));
ac_assert('AC8: second run skips', ($idempotent_second['data']['skip_reason'] ?? '') === 'task_already_completed');
ac_assert('AC8: mark_completed called once', $idempotent_calls === 1);

/**
 * @param array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}} $result
 */
function mc4_log_sync_failure(int $reservation_id, array $result): void {
    if (!empty($result['success'])) {
        return;
    }

    $code = (string) ($result['error']['code'] ?? 'unknown');

    if (in_array($code, [
        'missing_reservation_id',
        'reservation_not_found',
        'task_completion_failed',
    ], true)) {
        error_log(
            '⚠️ [CompleteAppointmentConfirmation] Tarea no completada para reserva '
            . $reservation_id
            . ': '
            . $code
        );
    }
}

// ─── AC9: best-effort sync does not throw on failure ─────────────

$log_file_skip = tempnam(sys_get_temp_dir(), 'mc4skip');
$previous_log = ini_get('error_log');
ini_set('error_log', $log_file_skip);

try {
    $skip_sync_uc = new CompleteAppointmentConfirmationTaskUseCase(
        static function (int $reservation_id): ?array {
            return ['id' => $reservation_id, 'estado' => 'pending'];
        }
    );
    mc4_log_sync_failure(777, $skip_sync_uc->execute(['reservation_id' => 777]));
} catch (Throwable $e) {
    ac_assert('AC9: sync does not throw on skip', false, $e->getMessage());
}

ini_set('error_log', $previous_log !== false ? $previous_log : '');
$skip_log = is_readable($log_file_skip) ? (string) file_get_contents($log_file_skip) : '';
@unlink($log_file_skip);

ac_assert('AC9: sync does not throw on skip', true);
ac_assert('AC9: skip paths do not log operational warning', $skip_log === '');

$fail_sync_uc = new CompleteAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($confirmed_reservation): ?array {
        return array_merge($confirmed_reservation, ['id' => $reservation_id]);
    },
    static function (int $reservation_id) use ($pending_task): ?array {
        return $pending_task;
    },
    static function (int $task_id, string $at): ?array {
        return null;
    }
);

$log_file_ops = tempnam(sys_get_temp_dir(), 'mc4ops');
ini_set('error_log', $log_file_ops);

mc4_log_sync_failure(501, $fail_sync_uc->execute(['reservation_id' => 501]));

ini_set('error_log', $previous_log !== false ? $previous_log : '');
$ops_log = is_readable($log_file_ops) ? (string) file_get_contents($log_file_ops) : '';
@unlink($log_file_ops);

ac_assert('AC9: operational failure logged with reservation_id', strpos($ops_log, '501') !== false);
ac_assert('AC9: operational failure logged with code', strpos($ops_log, 'task_completion_failed') !== false);

// ─── Resumen ───────────────────────────────────────────────────────

echo "\n=== MC4 complete task: {$passed}/{$total} passed ===\n";

if ($failed !== []) {
    echo "Failed:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
