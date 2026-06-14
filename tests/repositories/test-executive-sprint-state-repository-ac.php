<?php
/**
 * AC MC4 — ExecutiveSprintStateRepository.
 *
 * Ejecutar: php tests/repositories/test-executive-sprint-state-repository-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

require_once __DIR__ . '/../../includes/domain/executive/class-aa-executive-sprint-policy.php';
require_once __DIR__ . '/../../includes/repositories/ExecutiveSprintStateRepository.php';

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

ExecutiveSprintStateRepository::set_storage_override_for_tests(
    static function (string $operation, int $user_id, $payload = null) use (&$storage) {
        if ($operation === 'read') {
            return $storage[$user_id] ?? null;
        }

        if ($operation === 'write') {
            $storage[$user_id] = $payload;

            return true;
        }

        if ($operation === 'delete') {
            unset($storage[$user_id]);

            return true;
        }

        return false;
    }
);

$valid_state = [
    'version' => 1,
    'active_focus_list_id' => 7,
    'sprint_started_at' => 1000,
    'last_executive_action_at' => 1200,
    'sprint_expires_at' => 4600,
];

ac_assert('save/find roundtrip', ExecutiveSprintStateRepository::save_for_user(42, $valid_state));
$loaded = ExecutiveSprintStateRepository::find_for_user(42);
ac_assert('roundtrip preserves active_focus_list_id', (int) ($loaded['active_focus_list_id'] ?? 0) === 7);
ac_assert('roundtrip preserves sprint_expires_at', (int) ($loaded['sprint_expires_at'] ?? 0) === 4600);

ac_assert('clear removes state', ExecutiveSprintStateRepository::clear_for_user(42));
ac_assert('clear leaves empty state', ExecutiveSprintStateRepository::find_for_user(42) === []);

ExecutiveSprintStateRepository::save_for_user(99, $valid_state);
$storage[99] = '{not-json';
ac_assert('corrupt JSON yields empty', ExecutiveSprintStateRepository::find_for_user(99) === []);

ExecutiveSprintStateRepository::save_for_user(100, $valid_state);
$storage[100] = ['version' => 2, 'active_focus_list_id' => 5];
ac_assert('unsupported version yields empty', ExecutiveSprintStateRepository::find_for_user(100) === []);

ExecutiveSprintStateRepository::save_for_user(101, [
    'version' => 1,
    'active_focus_list_id' => 0,
    'sprint_started_at' => 1,
    'last_executive_action_at' => 1,
    'sprint_expires_at' => 2,
]);
ac_assert('invalid active_focus_list_id yields empty', ExecutiveSprintStateRepository::find_for_user(101) === []);

ExecutiveSprintStateRepository::save_for_user(102, [
    'version' => 1,
    'active_focus_list_id' => 3,
    'sprint_started_at' => 500,
    'last_executive_action_at' => 500,
    'sprint_expires_at' => 400,
]);
ac_assert('expires before started yields empty', ExecutiveSprintStateRepository::find_for_user(102) === []);

ac_assert('user_id < 1 returns empty', ExecutiveSprintStateRepository::find_for_user(0) === []);

ExecutiveSprintStateRepository::set_storage_override_for_tests(null);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
