<?php
/**
 * AC MC5 — ExecutiveFocusStateRepository.
 *
 * Ejecutar: php tests/repositories/test-executive-focus-state-repository-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

require_once __DIR__ . '/../../includes/domain/executive/class-aa-executive-focus-state-policy.php';
require_once __DIR__ . '/../../includes/repositories/ExecutiveFocusStateRepository.php';

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

ExecutiveFocusStateRepository::set_storage_override_for_tests(
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
    'manual_focus_list_id' => 12,
    'previous_focus_list_id' => 8,
    'dismiss_streak_without_sprint' => 2,
    'manual_focus_expires_at' => 2000,
];

ac_assert('save/find roundtrip', ExecutiveFocusStateRepository::save_for_user(42, $valid_state));
$loaded = ExecutiveFocusStateRepository::find_for_user(42);
ac_assert('roundtrip manual_focus_list_id', (int) ($loaded['manual_focus_list_id'] ?? 0) === 12);
ac_assert('roundtrip previous_focus_list_id', (int) ($loaded['previous_focus_list_id'] ?? 0) === 8);
ac_assert('roundtrip dismiss streak', (int) ($loaded['dismiss_streak_without_sprint'] ?? 0) === 2);

ac_assert('estado vacío devuelve defaults', ExecutiveFocusStateRepository::find_for_user(99)['version'] === 1);
ac_assert('manual focus null por defecto', ExecutiveFocusStateRepository::find_for_user(99)['manual_focus_list_id'] === null);

ExecutiveFocusStateRepository::save_for_user(100, $valid_state);
$storage[100] = '{not-json';
ac_assert('corrupto devuelve defaults', ExecutiveFocusStateRepository::find_for_user(100)['manual_focus_list_id'] === null);

ExecutiveFocusStateRepository::save_for_user(101, [
    'version' => 2,
    'manual_focus_list_id' => 5,
]);
ac_assert('versión inválida devuelve defaults', ExecutiveFocusStateRepository::find_for_user(101)['manual_focus_list_id'] === null);

ExecutiveFocusStateRepository::save_for_user(102, [
    'version' => 1,
    'manual_focus_list_id' => 0,
    'previous_focus_list_id' => -1,
    'dismiss_streak_without_sprint' => 99,
    'manual_focus_expires_at' => -5,
]);
$sanitized = ExecutiveFocusStateRepository::find_for_user(102);
ac_assert('manual_focus_list_id < 1 → null', $sanitized['manual_focus_list_id'] === null);
ac_assert('previous_focus_list_id < 1 → null', $sanitized['previous_focus_list_id'] === null);
ac_assert('dismiss streak limitado a 2', (int) $sanitized['dismiss_streak_without_sprint'] === 2);
ac_assert('manual_focus_expires_at inválido → null', $sanitized['manual_focus_expires_at'] === null);

$expired_state = [
    'version' => 1,
    'manual_focus_list_id' => 3,
    'previous_focus_list_id' => null,
    'dismiss_streak_without_sprint' => 0,
    'manual_focus_expires_at' => 1000,
];
ExecutiveFocusStateRepository::save_for_user(103, $expired_state);
ac_assert(
    'manual focus vencido inactivo',
    AA_Executive_Focus_State_Policy::is_manual_focus_active(
        ExecutiveFocusStateRepository::find_for_user(103),
        1500
    ) === false
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
