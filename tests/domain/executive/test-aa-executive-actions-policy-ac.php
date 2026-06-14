<?php
/**
 * AC MC1 — AA_Executive_Actions_Policy.
 *
 * Ejecutar: php tests/domain/executive/test-aa-executive-actions-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-actions-policy.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-visible-actions-policy.php';

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

/**
 * @param list<array<string,mixed>> $actions
 * @return list<string>
 */
function action_keys(array $actions): array {
    return array_values(array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, $actions));
}

$user_item = [
    'id' => '10',
    'source' => AA_Executable_Contract::SOURCE_USER,
    'status' => AA_Executable_Contract::ITEM_STATUS_PENDING,
    'primary_action' => [
        'key' => 'navigate.settings',
        'type' => AA_Executable_Contract::ACTION_NAVIGATE,
        'label' => 'Ir a ajustes',
        'url' => 'https://example.test/admin-post.php?module=settings',
    ],
    'capabilities' => [
        'can_complete' => true,
        'can_dismiss' => true,
        'can_edit' => true,
        'can_archive' => true,
        'can_delete' => true,
        'can_defer' => false,
    ],
];

$current_actions = AA_Executive_Actions_Policy::resolve($user_item, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_PRIMARY,
    'source' => AA_Executable_Contract::SOURCE_USER,
]);
$current_keys = action_keys($current_actions);

ac_assert('Current can include mechanical navigate action', in_array('navigate.settings', $current_keys, true));
ac_assert('Current can include complete action', in_array('complete', $current_keys, true));
ac_assert('Current can include dismiss action', in_array('dismiss', $current_keys, true));
ac_assert('Current excludes edit action', !in_array('edit', $current_keys, true));
ac_assert('Current excludes archive action', !in_array('archive', $current_keys, true));
ac_assert('Current excludes delete action', !in_array('delete', $current_keys, true));
ac_assert('Current excludes defer action', !in_array('defer', $current_keys, true));

$handler_item = [
    'id' => '11',
    'source' => AA_Executable_Contract::SOURCE_SYSTEM,
    'status' => AA_Executable_Contract::ITEM_STATUS_PENDING,
    'primary_action' => [
        'key' => 'pwa.install',
        'type' => AA_Executable_Contract::ACTION_HANDLER,
        'label' => 'Instalar',
        'handler' => 'pwa.install',
    ],
    'capabilities' => [
        'can_complete' => false,
        'can_dismiss' => true,
    ],
];

$handler_actions = AA_Executive_Actions_Policy::resolve($handler_item, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_SECONDARY,
    'source' => AA_Executable_Contract::SOURCE_SYSTEM,
]);
$handler_keys = action_keys($handler_actions);

ac_assert('Current can include handler install action', in_array('pwa.install', $handler_keys, true));
ac_assert('Handler item keeps dismiss when allowed', in_array('dismiss', $handler_keys, true));
ac_assert('System completion item without can_complete omits complete', !in_array('complete', $handler_keys, true));

$continuation_item = [
    'id' => '12',
    'source' => AA_Executable_Contract::SOURCE_USER,
    'status' => AA_Executable_Contract::ITEM_STATUS_PENDING,
    'capabilities' => [
        'can_complete' => true,
        'can_dismiss' => true,
        'can_edit' => true,
    ],
];

// Continuity slots should not call resolve in mapper; policy still filters if invoked.
$continuation_actions = AA_Executive_Actions_Policy::resolve($continuation_item, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_PRIMARY,
    'source' => AA_Executable_Contract::SOURCE_USER,
]);

ac_assert(
    'Policy itself can still resolve actions when invoked',
    count($continuation_actions) >= 1
);

$empty_capabilities = AA_Executive_Actions_Policy::resolve([
    'id' => '13',
    'source' => AA_Executable_Contract::SOURCE_USER,
    'status' => AA_Executable_Contract::ITEM_STATUS_PENDING,
    'capabilities' => [
        'can_complete' => false,
        'can_dismiss' => false,
        'can_edit' => true,
        'can_archive' => true,
    ],
], [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_PRIMARY,
    'source' => AA_Executable_Contract::SOURCE_USER,
]);

ac_assert('No executive actions when capabilities disallow them', $empty_capabilities === []);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
