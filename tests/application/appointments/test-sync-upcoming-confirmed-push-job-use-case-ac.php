<?php
/**
 * AC MC3 — SyncUpcomingConfirmedPushJobUseCase.
 *
 * Ejecutar: php tests/application/appointments/test-sync-upcoming-confirmed-push-job-use-case-ac.php
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

$GLOBALS['aa_test_options'] = [
    'aa_push_upcoming_confirmed_enabled' => 1,
    'aa_push_upcoming_confirmed_minutes' => 15,
    'aa_timezone' => 'America/Mexico_City',
];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }
        return $default;
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        $GLOBALS['aa_test_error_logs'][] = (string) $message;
    }
}

$GLOBALS['aa_test_error_logs'] = [];

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/infrastructure/backend/class-aa-push-backend-client.php';
require_once $plugin_root . '/includes/application/appointments/SyncUpcomingConfirmedPushJobUseCase.php';

// ─── enabled=true: lee cita y envía ISO + minutes ─────────────────

$sync_payloads = [];

$enabled_uc = new SyncUpcomingConfirmedPushJobUseCase(
    static function (array $payload) use (&$sync_payloads): array {
        $sync_payloads[] = $payload;
        return ['ok' => true, 'sync' => 'scheduled'];
    },
    static function (int $reservation_id): array {
        return [
            'id' => $reservation_id,
            'fecha' => '2026-07-09 15:00:00',
        ];
    },
    static function (): bool {
        return true;
    },
    static function (): int {
        return 15;
    }
);

$enabled_result = $enabled_uc->execute(['reservation_id' => 123]);

ac_assert('enabled=true success', !empty($enabled_result['success']));
ac_assert('enabled=true sends appointment_id', ($sync_payloads[0]['appointment_id'] ?? 0) === 123);
ac_assert('enabled=true sends enabled true', ($sync_payloads[0]['enabled'] ?? null) === true);
ac_assert('enabled=true sends minutes', ($sync_payloads[0]['minutes'] ?? null) === 15);
ac_assert(
    'enabled=true sends ISO appointment_start with offset',
    isset($sync_payloads[0]['appointment_start'])
    && strpos((string) $sync_payloads[0]['appointment_start'], 'T') !== false
    && preg_match('/[+-]\d{2}:\d{2}$/', (string) $sync_payloads[0]['appointment_start']) === 1
);

// ─── defaults enabled=true / minutes=15 ───────────────────────────

$GLOBALS['aa_test_options'] = [
    'aa_timezone' => 'America/Mexico_City',
];

$default_payloads = [];

$default_uc = new SyncUpcomingConfirmedPushJobUseCase(
    static function (array $payload) use (&$default_payloads): array {
        $default_payloads[] = $payload;
        return ['ok' => true, 'sync' => 'scheduled'];
    },
    static function (int $reservation_id): array {
        return ['id' => $reservation_id, 'fecha' => '2026-07-09 15:00:00'];
    }
);

$default_result = $default_uc->execute(['reservation_id' => 200]);

ac_assert('defaults: success with absent options', !empty($default_result['success']));
ac_assert('defaults: minutes=15', ($default_payloads[0]['minutes'] ?? null) === 15);
ac_assert('defaults: enabled=true path', ($default_payloads[0]['enabled'] ?? null) === true);

// ─── enabled=false: contrato reducido ─────────────────────────────

$disabled_payloads = [];

$disabled_uc = new SyncUpcomingConfirmedPushJobUseCase(
    static function (array $payload) use (&$disabled_payloads): array {
        $disabled_payloads[] = $payload;
        return ['ok' => true, 'sync' => 'disabled'];
    },
    static function (int $reservation_id): array {
        throw new RuntimeException('reservation reader must not run when disabled');
    },
    static function (): bool {
        return false;
    },
    static function (): int {
        throw new RuntimeException('minutes reader must not run when disabled');
    }
);

$disabled_result = $disabled_uc->execute(['reservation_id' => 456]);

ac_assert('enabled=false success', !empty($disabled_result['success']));
ac_assert('enabled=false reduced payload keys', array_keys($disabled_payloads[0]) === ['appointment_id', 'enabled']);
ac_assert('enabled=false appointment_id', ($disabled_payloads[0]['appointment_id'] ?? 0) === 456);
ac_assert('enabled=false enabled flag', ($disabled_payloads[0]['enabled'] ?? null) === false);
ac_assert('enabled=false no appointment_start', !array_key_exists('appointment_start', $disabled_payloads[0]));
ac_assert('enabled=false no minutes', !array_key_exists('minutes', $disabled_payloads[0]));

// ─── fallo backend no propaga excepción en best-effort ─────────────

$GLOBALS['aa_test_options'] = [
    'aa_push_upcoming_confirmed_enabled' => 0,
];

if (!defined('AA_API_BASE_URL')) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
}

$best_effort_threw = false;

try {
    SyncUpcomingConfirmedPushJobUseCase::sync_after_local_confirmation_best_effort(999);
} catch (Throwable $e) {
    $best_effort_threw = true;
}

ac_assert('best-effort static does not throw on backend failure', $best_effort_threw === false);

$sync_src = file_get_contents($plugin_root . '/includes/application/appointments/SyncUpcomingConfirmedPushJobUseCase.php');
ac_assert('best-effort static logs failures', strpos($sync_src, 'error_log(') !== false);
ac_assert(
    'best-effort static log mentions reservation id',
    strpos($sync_src, '$reservation_id') !== false
);

// ─── fallo backend en execute retorna error estructurado ──────────

$fail_uc = new SyncUpcomingConfirmedPushJobUseCase(
    static function (array $payload): array {
        return [
            'ok' => false,
            'code' => 'push_backend_unavailable',
            'error' => 'timeout',
            'http_status' => 503,
        ];
    },
    static function (int $reservation_id): array {
        return ['id' => $reservation_id, 'fecha' => '2026-07-09 15:00:00'];
    },
    static function (): bool {
        return true;
    },
    static function (): int {
        return 15;
    }
);

$fail_result = $fail_uc->execute(['reservation_id' => 321]);

ac_assert('backend failure returns structured error', empty($fail_result['success']));
ac_assert('backend failure preserves code', ($fail_result['error']['code'] ?? '') === 'push_backend_unavailable');

// ─── ConfirmReservationUseCase dispara sync en ruta productiva ──────

$confirm_src = file_get_contents($plugin_root . '/includes/application/appointments/ConfirmReservationUseCase.php');

ac_assert(
    'ConfirmReservationUseCase calls CompleteAppointment best-effort',
    strpos($confirm_src, 'CompleteAppointmentConfirmationTaskUseCase::sync_after_local_confirmation_best_effort') !== false
);
ac_assert(
    'ConfirmReservationUseCase calls SyncUpcomingConfirmed best-effort',
    strpos($confirm_src, 'SyncUpcomingConfirmedPushJobUseCase::sync_after_local_confirmation_best_effort') !== false
);

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
