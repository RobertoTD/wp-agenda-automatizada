<?php
/**
 * AC MC1 — ConfirmReservationUseCase.
 *
 * Ejecutar: php tests/application/appointments/test-confirm-reservation-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        $options = [
            'aa_timezone' => 'America/Mexico_City',
        ];
        if (array_key_exists($key, $options)) {
            return $options[$key];
        }
        return $default;
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

$use_case_file = $plugin_root . '/includes/application/appointments/ConfirmReservationUseCase.php';
$confirm_service_src = file_get_contents($plugin_root . '/includes/services/confirm-backend-service.php');
$confirm_controller_src = file_get_contents($plugin_root . '/includes/controllers/confirmController.php');

ac_assert('Use case file readable', is_readable($use_case_file));

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $use_case_file;
require_once $plugin_root . '/includes/application/appointments/SyncUpcomingConfirmedPushJobUseCase.php';

// ─── AC1: persiste estado confirmed ───────────────────────────────

$persist_calls = [];

$confirm_uc = new ConfirmReservationUseCase(
    static function (int $reservation_id, array $data, array $formats) use (&$persist_calls) {
        $persist_calls[] = [
            'reservation_id' => $reservation_id,
            'data' => $data,
            'formats' => $formats,
        ];

        return 1;
    },
    static function (int $reservation_id): void {
    }
);

$first = $confirm_uc->execute(['reservation_id' => 42]);

ac_assert('AC1: success on persist', !empty($first['success']));
ac_assert('AC1: estado confirmed in update data', ($persist_calls[0]['data']['estado'] ?? '') === 'confirmed');
ac_assert('AC1: reservation_id passed to persister', ($persist_calls[0]['reservation_id'] ?? 0) === 42);
ac_assert('AC1: rows_affected returned', ($first['data']['rows_affected'] ?? null) === 1);

// ─── AC2: ejecuta efecto común tras persistencia exitosa ───────────

$sync_calls = [];

$sync_uc = new ConfirmReservationUseCase(
    static function (int $reservation_id, array $data, array $formats): int {
        return 1;
    },
    static function (int $reservation_id) use (&$sync_calls): void {
        $sync_calls[] = $reservation_id;
    }
);

$sync_result = $sync_uc->execute(['reservation_id' => 77]);

ac_assert('AC2: success after sync', !empty($sync_result['success']));
ac_assert('AC2: post_confirmation_sync invoked once', count($sync_calls) === 1);
ac_assert('AC2: correct reservation_id synced', ($sync_calls[0] ?? 0) === 77);

// ─── AC3: update === false es fallo y no ejecuta efecto común ─────

$sync_calls = [];

$fail_uc = new ConfirmReservationUseCase(
    static function (int $reservation_id, array $data, array $formats): bool {
        return false;
    },
    static function (int $reservation_id) use (&$sync_calls): void {
        $sync_calls[] = $reservation_id;
    }
);

$fail_result = $fail_uc->execute(['reservation_id' => 88]);

ac_assert('AC3: failure on wpdb false', empty($fail_result['success']));
ac_assert('AC3: error code confirmation_persistence_failed', ($fail_result['error']['code'] ?? '') === 'confirmation_persistence_failed');
ac_assert('AC3: post_confirmation_sync not called', $sync_calls === []);

// ─── AC4: update === 0 es éxito y ejecuta efecto común ─────────────

$sync_calls = [];

$zero_uc = new ConfirmReservationUseCase(
    static function (int $reservation_id, array $data, array $formats): int {
        return 0;
    },
    static function (int $reservation_id) use (&$sync_calls): void {
        $sync_calls[] = $reservation_id;
    }
);

$zero_result = $zero_uc->execute(['reservation_id' => 99]);

ac_assert('AC4: success on zero rows affected', !empty($zero_result['success']));
ac_assert('AC4: rows_affected is zero', ($zero_result['data']['rows_affected'] ?? null) === 0);
ac_assert('AC4: post_confirmation_sync still called', count($sync_calls) === 1);

// ─── AC5: columnas opcionales REST en el mismo update ──────────────

$persist_calls = [];

$columns_uc = new ConfirmReservationUseCase(
    static function (int $reservation_id, array $data, array $formats) use (&$persist_calls): int {
        $persist_calls[] = ['data' => $data, 'formats' => $formats];

        return 1;
    },
    static function (int $reservation_id): void {
    }
);

$columns_result = $columns_uc->execute([
    'reservation_id' => 55,
    'columns' => [
        'calendar_uid' => 'uid-abc',
        'virtual_link' => 'https://meet.example/join',
    ],
]);

ac_assert('AC5: success with optional columns', !empty($columns_result['success']));
ac_assert('AC5: calendar_uid merged', ($persist_calls[0]['data']['calendar_uid'] ?? '') === 'uid-abc');
ac_assert('AC5: virtual_link merged', ($persist_calls[0]['data']['virtual_link'] ?? '') === 'https://meet.example/join');
ac_assert('AC5: single update payload', count($persist_calls) === 1);
ac_assert('AC5: formats match columns', count($persist_calls[0]['formats'] ?? []) === 3);

// ─── AC6: rechaza columnas fuera de whitelist ──────────────────────

$invalid_result = $columns_uc->execute([
    'reservation_id' => 55,
    'columns' => [
        'nombre' => 'hack',
    ],
]);

ac_assert('AC6: invalid column rejected', empty($invalid_result['success']));
ac_assert('AC6: error code invalid_column', ($invalid_result['error']['code'] ?? '') === 'invalid_column');

// ─── AC7: delegación de callers runtime ────────────────────────────

ac_assert(
    'AC7: confirm_backend_service delegates to ConfirmReservationUseCase',
    strpos($confirm_service_src, 'ConfirmReservationUseCase') !== false
    && strpos($confirm_service_src, "\$wpdb->update(\$table, ['estado' => 'confirmed']") === false
);
ac_assert(
    'AC7: aa_rest_confirmar_reserva delegates to ConfirmReservationUseCase',
    strpos($confirm_controller_src, 'ConfirmReservationUseCase') !== false
    && strpos($confirm_controller_src, "\$update_data = ['estado' => 'confirmed']") === false
);
ac_assert(
    'AC7: confirm_backend_service no longer calls CompleteAppointment directly',
    strpos($confirm_service_src, 'CompleteAppointmentConfirmationTaskUseCase::sync_after_local_confirmation_best_effort($reserva_id)') === false
);
ac_assert(
    'AC7: aa_rest_confirmar_reserva no longer calls CompleteAppointment directly',
    strpos($confirm_controller_src, 'CompleteAppointmentConfirmationTaskUseCase::sync_after_local_confirmation_best_effort($id)') === false
);

// ─── AC8: MC3 — ruta productiva incluye sync Push best-effort ───────

$confirm_uc_src = file_get_contents($use_case_file);

ac_assert(
    'AC8: ConfirmReservationUseCase invokes SyncUpcomingConfirmedPushJobUseCase',
    strpos($confirm_uc_src, 'SyncUpcomingConfirmedPushJobUseCase::sync_after_local_confirmation_best_effort') !== false
);
ac_assert(
    'AC8: SyncUpcomingConfirmed runs after CompleteAppointment',
    strpos($confirm_uc_src, 'CompleteAppointmentConfirmationTaskUseCase::sync_after_local_confirmation_best_effort($reservation_id);') !== false
    && strpos($confirm_uc_src, 'SyncUpcomingConfirmedPushJobUseCase::sync_after_local_confirmation_best_effort($reservation_id);') !== false
    && strpos($confirm_uc_src, 'CompleteAppointmentConfirmationTaskUseCase::sync_after_local_confirmation_best_effort($reservation_id);')
        < strpos($confirm_uc_src, 'SyncUpcomingConfirmedPushJobUseCase::sync_after_local_confirmation_best_effort($reservation_id);')
);

// ─── AC9: fallo Push no altera éxito de confirmación ───────────────

$push_fail_uc = new ConfirmReservationUseCase(
    static function (int $reservation_id, array $data, array $formats): int {
        return 1;
    },
    static function (int $reservation_id): void {
        $sync = new SyncUpcomingConfirmedPushJobUseCase(
            static function (array $payload): array {
                return [
                    'ok' => false,
                    'code' => 'push_backend_unavailable',
                    'error' => 'timeout',
                    'http_status' => 503,
                ];
            },
            static function (int $id): array {
                return ['id' => $id, 'fecha' => '2026-07-09 15:00:00'];
            },
            static function (): bool {
                return true;
            },
            static function (): int {
                return 15;
            }
        );

        $sync->execute(['reservation_id' => $reservation_id]);
    }
);

$push_fail_result = $push_fail_uc->execute(['reservation_id' => 501]);

ac_assert('AC9: confirmation success despite Push sync failure', !empty($push_fail_result['success']));
ac_assert('AC9: rows_affected still returned', ($push_fail_result['data']['rows_affected'] ?? null) === 1);

// ─── Summary ───────────────────────────────────────────────────────

echo "\n";
echo "Passed: {$passed}/{$total}\n";

if ($failed !== []) {
    echo "Failed:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

echo "All tests passed.\n";
exit(0);
