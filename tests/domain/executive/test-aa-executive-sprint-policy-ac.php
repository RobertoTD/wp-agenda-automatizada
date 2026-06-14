<?php
/**
 * AC MC4 — AA_Executive_Sprint_Policy.
 *
 * Ejecutar: php tests/domain/executive/test-aa-executive-sprint-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-sprint-policy.php';

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

$now = 2000;
$state = [
    'version' => 1,
    'active_focus_list_id' => 5,
    'sprint_started_at' => 1000,
    'last_executive_action_at' => 1500,
    'sprint_expires_at' => 4600,
];

ac_assert('is_active true before expiry', AA_Executive_Sprint_Policy::is_active($state, $now) === true);
ac_assert('is_expired false before expiry', AA_Executive_Sprint_Policy::is_expired($state, $now) === false);
ac_assert('get_active_focus_list_id returns id', AA_Executive_Sprint_Policy::get_active_focus_list_id($state, $now) === 5);
ac_assert('is_active false after expiry', AA_Executive_Sprint_Policy::is_active($state, 5000) === false);
ac_assert('is_expired true after expiry', AA_Executive_Sprint_Policy::is_expired($state, 5000) === true);

$first_renew = AA_Executive_Sprint_Policy::renew([], 9, $now);
ac_assert('renew first time sets sprint_started_at', (int) ($first_renew['sprint_started_at'] ?? 0) === $now);
ac_assert('renew first time sets expires_at', (int) ($first_renew['sprint_expires_at'] ?? 0) === $now + 3600);
ac_assert('renew first time sets active list', (int) ($first_renew['active_focus_list_id'] ?? 0) === 9);

$second_renew = AA_Executive_Sprint_Policy::renew($first_renew, 11, $now + 600);
ac_assert('renew keeps sprint_started_at', (int) ($second_renew['sprint_started_at'] ?? 0) === $now);
ac_assert('renew updates last_executive_action_at', (int) ($second_renew['last_executive_action_at'] ?? 0) === $now + 600);
ac_assert('renew extends expires_at from action time', (int) ($second_renew['sprint_expires_at'] ?? 0) === $now + 600 + 3600);

ac_assert(
    'complete renews',
    AA_Executive_Sprint_Policy::should_renew_for_executive_action([
        'type' => AA_Executable_Contract::ACTION_STATUS,
        'key' => 'complete',
    ]) === true
);
ac_assert(
    'navigate renews',
    AA_Executive_Sprint_Policy::should_renew_for_executive_action([
        'type' => AA_Executable_Contract::ACTION_NAVIGATE,
        'key' => 'navigate.settings',
    ]) === true
);
ac_assert(
    'handler renews',
    AA_Executive_Sprint_Policy::should_renew_for_executive_action([
        'type' => AA_Executable_Contract::ACTION_HANDLER,
        'key' => 'pwa.install',
    ]) === true
);
ac_assert(
    'dismiss does not renew',
    AA_Executive_Sprint_Policy::should_renew_for_executive_action([
        'type' => AA_Executable_Contract::ACTION_INTENT,
        'key' => 'dismiss',
    ]) === false
);

$shifted = AA_Executive_Sprint_Policy::update_active_focus_without_renew($second_renew, 15);
ac_assert('update focus changes active list', (int) ($shifted['active_focus_list_id'] ?? 0) === 15);
ac_assert('update focus keeps sprint_started_at', (int) ($shifted['sprint_started_at'] ?? 0) === $now);
ac_assert('update focus keeps last_executive_action_at', (int) ($shifted['last_executive_action_at'] ?? 0) === $now + 600);
ac_assert('update focus keeps sprint_expires_at', (int) ($shifted['sprint_expires_at'] ?? 0) === $now + 600 + 3600);

ac_assert('release returns empty', AA_Executive_Sprint_Policy::release($state) === []);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
