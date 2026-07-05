<?php
/**
 * AC — ReconcileTutorialStateUseCase.
 *
 * Ejecutar: php tests/application/tutorials/test-reconcile-tutorial-state-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

require_once __DIR__ . '/../../../includes/domain/tutorials/class-aa-tutorial-state-policy.php';
require_once __DIR__ . '/../../../includes/repositories/TutorialStateRepository.php';
require_once __DIR__ . '/../../../includes/application/tutorials/ReconcileTutorialStateUseCase.php';

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

/** @var array<int,mixed> */
$storage = [];
/** @var list<string> */
$write_log = [];
/** @var list<string> */
$probe_log = [];

TutorialStateRepository::set_storage_override_for_tests(
    static function (string $operation, int $blog_id, $payload = null) use (&$storage, &$write_log) {
        if ($operation === 'read') {
            return $storage[$blog_id] ?? false;
        }

        if ($operation === 'write') {
            $write_log[] = 'write';
            $storage[$blog_id] = $payload;

            return true;
        }

        return false;
    }
);

$tutorial_id = AA_Tutorial_State_Policy::TUTORIAL_CREATE_TEST_APPOINTMENT;
$now = '2026-07-05 11:00:00';

$completed_state = [
    'version' => 1,
    'tutorials' => [
        $tutorial_id => [
            'status' => 'completed',
            'current_step_id' => null,
            'accepted_at' => '2026-07-01 10:00:00',
            'started_at' => '2026-07-01 10:00:00',
            'paused_at' => null,
            'completed_at' => '2026-07-03 09:00:00',
            'updated_at' => '2026-07-03 09:00:00',
        ],
    ],
];
$storage[1] = $completed_state;
$write_log = [];
$probe_log = [];

$completed_use_case = new ReconcileTutorialStateUseCase(
    static function () use ($now) {
        return $now;
    },
    static function () use (&$probe_log) {
        $probe_log[] = 'probe';
        return ['ok' => true, 'exists' => true];
    }
);

$completed_result = $completed_use_case->execute();
ac_assert('completed early return success', ($completed_result['success'] ?? false) === true);
ac_assert('completed reconciled false', ($completed_result['data']['reconciled'] ?? true) === false);
ac_assert('completed skips probe', $probe_log === []);
ac_assert('completed no write', $write_log === []);

$storage[1] = ['version' => 1, 'tutorials' => []];
$write_log = [];
$probe_log = [];

$probe_error_use_case = new ReconcileTutorialStateUseCase(
    static function () use ($now) {
        return $now;
    },
    static function () {
        return ['ok' => false, 'exists' => false];
    }
);

$probe_error_result = $probe_error_use_case->execute();
ac_assert('probe error fails', ($probe_error_result['success'] ?? true) === false);
ac_assert('probe error code', ($probe_error_result['error']['code'] ?? '') === 'reservation_existence_check_failed');
ac_assert('probe error no write', $write_log === []);

$storage[1] = ['version' => 1, 'tutorials' => []];
$write_log = [];

$no_reservations_use_case = new ReconcileTutorialStateUseCase(
    static function () use ($now) {
        return $now;
    },
    static function () {
        return ['ok' => true, 'exists' => false];
    }
);

$no_reservations_result = $no_reservations_use_case->execute();
ac_assert('no reservations success', ($no_reservations_result['success'] ?? false) === true);
ac_assert('no reservations reconciled false', ($no_reservations_result['data']['reconciled'] ?? true) === false);
ac_assert('no reservations no write', $write_log === []);

$storage[1] = ['version' => 1, 'tutorials' => []];
$write_log = [];

$reconcile_use_case = new ReconcileTutorialStateUseCase(
    static function () use ($now) {
        return $now;
    },
    static function () {
        return ['ok' => true, 'exists' => true];
    }
);

$first_reconcile = $reconcile_use_case->execute();
$first_tutorial = $first_reconcile['data']['tutorials'][$tutorial_id] ?? [];
ac_assert('first reconcile success', ($first_reconcile['success'] ?? false) === true);
ac_assert('first reconcile flag true', ($first_reconcile['data']['reconciled'] ?? false) === true);
ac_assert('first reconcile one write', count($write_log) === 1);
ac_assert('first reconcile status completed', ($first_tutorial['status'] ?? '') === 'completed');
ac_assert('first reconcile completed_at', ($first_tutorial['completed_at'] ?? '') === $now);
ac_assert('first reconcile updated_at', ($first_tutorial['updated_at'] ?? '') === $now);
ac_assert('first reconcile accepted_at null', array_key_exists('accepted_at', $first_tutorial) && $first_tutorial['accepted_at'] === null);

$write_log = [];
$second_reconcile = $reconcile_use_case->execute();
ac_assert('second reconcile success', ($second_reconcile['success'] ?? false) === true);
ac_assert('second reconcile flag false', ($second_reconcile['data']['reconciled'] ?? true) === false);
ac_assert('second reconcile no write', $write_log === []);

$storage[1] = ['version' => 1, 'tutorials' => []];
$write_log = [];

$persist_fail_use_case = new ReconcileTutorialStateUseCase(
    static function () use ($now) {
        return $now;
    },
    static function () {
        return ['ok' => true, 'exists' => true];
    }
);

TutorialStateRepository::set_storage_override_for_tests(
    static function (string $operation, int $blog_id, $payload = null) use (&$storage) {
        if ($operation === 'read') {
            return $storage[$blog_id] ?? false;
        }

        if ($operation === 'write') {
            return false;
        }

        return false;
    }
);

$persist_fail_result = $persist_fail_use_case->execute();
ac_assert('persist failure fails', ($persist_fail_result['success'] ?? true) === false);
ac_assert('persist failure code', ($persist_fail_result['error']['code'] ?? '') === 'persist_failed');

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
