<?php
/**
 * AC MC11A — AA_Executable_Visible_Actions_Policy.
 *
 * Ejecutar: php tests/domain/executable/test-aa-executable-visible-actions-policy-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

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
 * @param array<string,mixed> $overrides
 * @return array<string,mixed>
 */
function executable_visible_action_item(array $overrides = []): array {
    return array_merge([
        'id' => 'configure_services',
        'source' => AA_Executable_Contract::SOURCE_SYSTEM,
        'origin_key' => 'configure_services',
        'title' => 'Configura tus servicios',
        'description' => 'Define servicios.',
        'importance' => 0,
        'due_at' => null,
        'status' => AA_Executable_Contract::ITEM_STATUS_PENDING,
        'state' => [
            'completed' => false,
            'ignored' => false,
            'dismissed' => false,
            'dismiss_active' => false,
            'auto_completed' => false,
        ],
        'capabilities' => [
            'can_complete' => false,
            'can_reopen' => false,
            'can_defer' => false,
            'can_dismiss' => false,
            'can_reactivate' => false,
        ],
        'primary_action' => null,
        'is_executive_candidate' => false,
    ], $overrides);
}

/**
 * @param list<array<string,mixed>> $actions
 * @return list<string>
 */
function executable_visible_action_keys(array $actions): array {
    return array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, $actions);
}

/**
 * @param list<array<string,mixed>> $actions
 */
function executable_visible_action_find(array $actions, string $key): ?array {
    foreach ($actions as $action) {
        if (($action['key'] ?? '') === $key) {
            return $action;
        }
    }

    return null;
}

$learning_primary = executable_visible_action_item([
    'primary_action' => [
        'type' => AA_Executable_Contract::ACTION_NAVIGATE,
        'label' => 'Ir',
        'url' => 'https://example.test/admin-post.php?module=assignments',
    ],
    'capabilities' => [
        'can_defer' => true,
    ],
]);
$learning_primary_actions = AA_Executable_Visible_Actions_Policy::resolve($learning_primary, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_PRIMARY,
    'source' => AA_Executable_Contract::SOURCE_SYSTEM,
]);
$learning_primary_navigate = executable_visible_action_find($learning_primary_actions, 'navigate');
$learning_primary_defer = executable_visible_action_find($learning_primary_actions, 'defer');

ac_assert(
    'Learning primary produces navigate mechanical action',
    is_array($learning_primary_navigate)
    && ($learning_primary_navigate['type'] ?? '') === AA_Executable_Contract::ACTION_NAVIGATE
    && ($learning_primary_navigate['category'] ?? '') === AA_Executable_Visible_Actions_Policy::CATEGORY_MECHANICAL
    && ($learning_primary_navigate['placement'] ?? '') === AA_Executable_Visible_Actions_Policy::PLACEMENT_PRIMARY
    && ($learning_primary_navigate['label'] ?? '') === 'Ir'
    && ($learning_primary_navigate['url'] ?? '') !== ''
);

ac_assert(
    'Learning primary produces defer intent action',
    is_array($learning_primary_defer)
    && ($learning_primary_defer['type'] ?? '') === AA_Executable_Visible_Actions_Policy::ACTION_TYPE_INTENT
    && ($learning_primary_defer['category'] ?? '') === AA_Executable_Visible_Actions_Policy::CATEGORY_INTENT
    && ($learning_primary_defer['placement'] ?? '') === AA_Executable_Visible_Actions_Policy::PLACEMENT_SECONDARY
    && ($learning_primary_defer['label'] ?? '') === 'Ahora no'
);

ac_assert(
    'Learning primary action order is mechanical then intent',
    executable_visible_action_keys($learning_primary_actions) === ['navigate', 'defer']
);

$learning_primary_dismiss = executable_visible_action_item([
    'primary_action' => [
        'type' => AA_Executable_Contract::ACTION_NAVIGATE,
        'label' => 'Ir',
        'url' => 'https://example.test/admin-post.php?module=settings',
    ],
    'capabilities' => [
        'can_defer' => true,
        'can_dismiss' => true,
    ],
]);
$learning_primary_dismiss_actions = AA_Executable_Visible_Actions_Policy::resolve($learning_primary_dismiss, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_PRIMARY,
    'source' => AA_Executable_Contract::SOURCE_SYSTEM,
]);
ac_assert(
    'System primary with can_dismiss emits dismiss without secondary bucket',
    executable_visible_action_find($learning_primary_dismiss_actions, 'dismiss') !== null
    && executable_visible_action_keys($learning_primary_dismiss_actions) === ['navigate', 'defer', 'dismiss']
);

$learning_secondary_no_dismiss = executable_visible_action_item([
    'capabilities' => [
        'can_complete' => true,
        'can_dismiss' => false,
    ],
]);
$learning_secondary_no_dismiss_actions = AA_Executable_Visible_Actions_Policy::resolve($learning_secondary_no_dismiss, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_SECONDARY,
    'source' => AA_Executable_Contract::SOURCE_SYSTEM,
]);
ac_assert(
    'System without can_dismiss emits no dismiss action',
    executable_visible_action_find($learning_secondary_no_dismiss_actions, 'dismiss') === null
);

$learning_secondary_defer_gate = executable_visible_action_item([
    'capabilities' => [
        'can_defer' => true,
    ],
]);
$learning_secondary_defer_actions = AA_Executable_Visible_Actions_Policy::resolve($learning_secondary_defer_gate, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_SECONDARY,
    'source' => AA_Executable_Contract::SOURCE_SYSTEM,
]);
ac_assert(
    'System defer remains gated to primary bucket',
    executable_visible_action_find($learning_secondary_defer_actions, 'defer') === null
);

$learning_secondary = executable_visible_action_item([
    'id' => 'install_pwa',
    'origin_key' => 'install_pwa',
    'primary_action' => [
        'type' => AA_Executable_Contract::ACTION_HANDLER,
        'label' => 'Instalar',
        'handler' => 'pwa.install',
    ],
    'capabilities' => [
        'can_complete' => true,
        'can_dismiss' => true,
    ],
]);
$learning_secondary_actions = AA_Executable_Visible_Actions_Policy::resolve($learning_secondary, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_SECONDARY,
    'source' => AA_Executable_Contract::SOURCE_SYSTEM,
]);
$learning_secondary_handler = executable_visible_action_find($learning_secondary_actions, 'pwa.install');
$learning_secondary_complete = executable_visible_action_find($learning_secondary_actions, 'complete');
$learning_secondary_dismiss = executable_visible_action_find($learning_secondary_actions, 'dismiss');

ac_assert(
    'Learning secondary produces handler mechanical action',
    is_array($learning_secondary_handler)
    && ($learning_secondary_handler['type'] ?? '') === AA_Executable_Contract::ACTION_HANDLER
    && ($learning_secondary_handler['category'] ?? '') === AA_Executable_Visible_Actions_Policy::CATEGORY_MECHANICAL
    && ($learning_secondary_handler['label'] ?? '') === 'Instalar'
    && ($learning_secondary_handler['handler'] ?? '') === 'pwa.install'
);

ac_assert(
    'Learning secondary produces complete declarative action',
    is_array($learning_secondary_complete)
    && ($learning_secondary_complete['type'] ?? '') === AA_Executable_Contract::ACTION_STATUS
    && ($learning_secondary_complete['category'] ?? '') === AA_Executable_Visible_Actions_Policy::CATEGORY_DECLARATIVE
    && ($learning_secondary_complete['label'] ?? '') === 'Completar'
    && ($learning_secondary_complete['target_status'] ?? '') === AA_Executable_Contract::ITEM_STATUS_DONE
);

ac_assert(
    'Learning secondary produces dismiss intent action',
    is_array($learning_secondary_dismiss)
    && ($learning_secondary_dismiss['type'] ?? '') === AA_Executable_Visible_Actions_Policy::ACTION_TYPE_INTENT
    && ($learning_secondary_dismiss['category'] ?? '') === AA_Executable_Visible_Actions_Policy::CATEGORY_INTENT
    && ($learning_secondary_dismiss['label'] ?? '') === 'Ignorar'
);

ac_assert(
    'Learning secondary action order is mechanical then declarative then intent',
    executable_visible_action_keys($learning_secondary_actions) === ['pwa.install', 'complete', 'dismiss']
);

$reactivable_active = executable_visible_action_item([
    'capabilities' => [
        'can_reactivate' => true,
    ],
]);
$reactivable_active_actions = AA_Executable_Visible_Actions_Policy::resolve($reactivable_active, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_SECONDARY,
]);
ac_assert(
    'Active view does not produce reactivate',
    executable_visible_action_find($reactivable_active_actions, 'reactivate') === null
);

$task_pending = executable_visible_action_item([
    'id' => '42',
    'source' => AA_Executable_Contract::SOURCE_USER,
    'origin_key' => null,
    'capabilities' => [
        'can_complete' => true,
    ],
]);
$task_pending_actions = AA_Executable_Visible_Actions_Policy::resolve($task_pending, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_DEFAULT,
    'source' => AA_Executable_Contract::SOURCE_USER,
]);
$task_pending_complete = executable_visible_action_find($task_pending_actions, 'complete');
ac_assert(
    'User pending task produces complete action',
    is_array($task_pending_complete)
    && ($task_pending_complete['target_status'] ?? '') === AA_Executable_Contract::ITEM_STATUS_DONE
);

$reopen_active = executable_visible_action_item([
    'status' => AA_Executable_Contract::ITEM_STATUS_DONE,
    'capabilities' => [
        'can_reopen' => true,
    ],
]);
$reopen_active_actions = AA_Executable_Visible_Actions_Policy::resolve($reopen_active, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_DEFAULT,
]);
ac_assert(
    'Active view does not produce reopen',
    executable_visible_action_find($reopen_active_actions, 'reopen') === null
);

$completed_view_actions = AA_Executable_Visible_Actions_Policy::resolve($reopen_active, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_COMPLETED,
    'bucket_key' => AA_Executable_Visible_Actions_Policy::VIEW_COMPLETED,
]);
$completed_view_reopen = executable_visible_action_find($completed_view_actions, 'reopen');
ac_assert(
    'Completed view can produce reopen recovery action',
    is_array($completed_view_reopen)
    && ($completed_view_reopen['category'] ?? '') === AA_Executable_Visible_Actions_Policy::CATEGORY_RECOVERY
    && ($completed_view_reopen['target_status'] ?? '') === AA_Executable_Contract::ITEM_STATUS_PENDING
);

$ignored_view_actions = AA_Executable_Visible_Actions_Policy::resolve($reactivable_active, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_IGNORED,
    'bucket_key' => AA_Executable_Visible_Actions_Policy::VIEW_IGNORED,
]);
$ignored_view_reactivate = executable_visible_action_find($ignored_view_actions, 'reactivate');
ac_assert(
    'Ignored view can produce reactivate recovery action',
    is_array($ignored_view_reactivate)
    && ($ignored_view_reactivate['category'] ?? '') === AA_Executable_Visible_Actions_Policy::CATEGORY_RECOVERY
    && ($ignored_view_reactivate['label'] ?? '') === 'Reactivar'
);

$immutable_item = executable_visible_action_item([
    'primary_action' => [
        'type' => AA_Executable_Contract::ACTION_NAVIGATE,
        'label' => 'Ir',
        'url' => 'https://example.test',
    ],
    'capabilities' => [
        'can_complete' => true,
        'can_defer' => true,
    ],
]);
$immutable_before = serialize($immutable_item);
AA_Executable_Visible_Actions_Policy::resolve($immutable_item, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_PRIMARY,
]);
ac_assert('Policy does not mutate item', serialize($immutable_item) === $immutable_before);

$user_defer_primary = executable_visible_action_item([
    'id' => '99',
    'source' => AA_Executable_Contract::SOURCE_USER,
    'origin_key' => null,
    'capabilities' => [
        'can_defer' => true,
        'can_dismiss' => true,
    ],
]);
$user_defer_primary_actions = AA_Executable_Visible_Actions_Policy::resolve($user_defer_primary, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_SECONDARY,
    'source' => AA_Executable_Contract::SOURCE_USER,
]);
$user_defer_action = executable_visible_action_find($user_defer_primary_actions, 'defer');
$user_dismiss_action = executable_visible_action_find($user_defer_primary_actions, 'dismiss');
ac_assert(
    'User source defer does not depend on primary bucket',
    is_array($user_defer_action)
    && ($user_defer_action['key'] ?? '') === 'defer'
);
ac_assert(
    'User source dismiss does not depend on secondary bucket',
    is_array($user_dismiss_action)
    && ($user_dismiss_action['key'] ?? '') === 'dismiss'
);

$user_no_capabilities = executable_visible_action_item([
    'id' => '100',
    'source' => AA_Executable_Contract::SOURCE_USER,
    'capabilities' => [
        'can_complete' => true,
        'can_defer' => false,
        'can_dismiss' => false,
    ],
]);
$user_no_capability_actions = AA_Executable_Visible_Actions_Policy::resolve($user_no_capabilities, [
    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
    'bucket_key' => AA_Executable_Contract::BUCKET_PRIMARY,
    'source' => AA_Executable_Contract::SOURCE_USER,
]);
ac_assert(
    'User source without defer capability emits no defer action',
    executable_visible_action_find($user_no_capability_actions, 'defer') === null
);

// ─── Resumen ─────────────────────────────────────────────────

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
