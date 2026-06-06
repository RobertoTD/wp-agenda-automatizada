<?php
/**
 * AC MC11B — ExecutableVisibleActionsEnricher.
 *
 * Ejecutar: php tests/application/executable/test-executable-visible-actions-enricher-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/../../../includes/domain/executable/class-aa-executable-visible-actions-policy.php';
require_once __DIR__ . '/../../../includes/application/executable/ExecutableVisibleActionsEnricher.php';

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
function enricher_item(array $overrides = []): array {
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
 * @param array<string,mixed> $item
 * @param string              $bucket_key
 * @param string              $source
 * @return list<array<string,mixed>>
 */
function enricher_single_list(array $item, string $bucket_key, string $source = AA_Executable_Contract::SOURCE_SYSTEM): array {
    return ExecutableVisibleActionsEnricher::enrich_lists([
        [
            'id' => 'system:learning.recommendations',
            'source' => $source,
            'origin_key' => 'learning.recommendations',
            'title' => 'Recomendaciones',
            'description' => null,
            'importance' => 0,
            'position' => 0,
            'status' => AA_Executable_Contract::LIST_STATUS_ACTIVE,
            'capabilities' => ['can_archive' => false],
            'buckets' => [
                [
                    'key' => $bucket_key,
                    'label' => '',
                    'items' => [$item],
                ],
            ],
        ],
    ]);
}

/**
 * @param list<array<string,mixed>> $actions
 * @return list<string>
 */
function enricher_action_keys(array $actions): array {
    return array_map(static function (array $action): string {
        return (string) ($action['key'] ?? '');
    }, $actions);
}

/**
 * @param list<array<string,mixed>> $actions
 */
function enricher_find_action(array $actions, string $key): ?array {
    foreach ($actions as $action) {
        if (($action['key'] ?? '') === $key) {
            return $action;
        }
    }

    return null;
}

$learning_primary_lists = enricher_single_list(
    enricher_item([
        'primary_action' => [
            'type' => AA_Executable_Contract::ACTION_NAVIGATE,
            'label' => 'Ir',
            'url' => 'https://example.test/admin-post.php?module=assignments',
        ],
        'capabilities' => [
            'can_defer' => true,
        ],
    ]),
    AA_Executable_Contract::BUCKET_PRIMARY
);
$learning_primary_item = $learning_primary_lists[0]['buckets'][0]['items'][0] ?? null;
$learning_primary_actions = is_array($learning_primary_item)
    ? ($learning_primary_item['visible_actions'] ?? [])
    : [];

ac_assert(
    'Learning primary enricher includes navigate and defer',
    is_array($learning_primary_item)
    && enricher_action_keys($learning_primary_actions) === ['navigate', 'defer']
    && ($learning_primary_item['primary_action']['type'] ?? '') === AA_Executable_Contract::ACTION_NAVIGATE
);

$learning_secondary_lists = enricher_single_list(
    enricher_item([
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
    ]),
    AA_Executable_Contract::BUCKET_SECONDARY
);
$learning_secondary_item = $learning_secondary_lists[0]['buckets'][0]['items'][0] ?? null;
$learning_secondary_actions = is_array($learning_secondary_item)
    ? ($learning_secondary_item['visible_actions'] ?? [])
    : [];

ac_assert(
    'Learning secondary enricher includes handler complete dismiss',
    is_array($learning_secondary_item)
    && enricher_action_keys($learning_secondary_actions) === ['pwa.install', 'complete', 'dismiss']
);

$user_pending_lists = enricher_single_list(
    enricher_item([
        'id' => '42',
        'source' => AA_Executable_Contract::SOURCE_USER,
        'origin_key' => null,
        'primary_action' => [
            'type' => AA_Executable_Contract::ACTION_STATUS,
            'label' => 'Completar',
            'to' => AA_Executable_Contract::ITEM_STATUS_DONE,
        ],
        'capabilities' => [
            'can_complete' => true,
        ],
    ]),
    AA_Executable_Contract::BUCKET_DEFAULT,
    AA_Executable_Contract::SOURCE_USER
);
$user_pending_item = $user_pending_lists[0]['buckets'][0]['items'][0] ?? null;
$user_pending_actions = is_array($user_pending_item)
    ? ($user_pending_item['visible_actions'] ?? [])
    : [];
$user_pending_complete = enricher_find_action($user_pending_actions, 'complete');

ac_assert(
    'User pending default enricher includes complete',
    is_array($user_pending_complete)
    && ($user_pending_complete['type'] ?? '') === AA_Executable_Contract::ACTION_STATUS
    && ($user_pending_complete['target_status'] ?? '') === AA_Executable_Contract::ITEM_STATUS_DONE
);

$reactivable_lists = enricher_single_list(
    enricher_item([
        'capabilities' => [
            'can_reactivate' => true,
        ],
    ]),
    AA_Executable_Contract::BUCKET_SECONDARY
);
$reactivable_actions = $reactivable_lists[0]['buckets'][0]['items'][0]['visible_actions'] ?? [];
ac_assert(
    'Active view enricher excludes reactivate',
    enricher_find_action(is_array($reactivable_actions) ? $reactivable_actions : [], 'reactivate') === null
);

$reopen_lists = enricher_single_list(
    enricher_item([
        'status' => AA_Executable_Contract::ITEM_STATUS_DONE,
        'capabilities' => [
            'can_reopen' => true,
        ],
    ]),
    AA_Executable_Contract::BUCKET_DEFAULT,
    AA_Executable_Contract::SOURCE_USER
);
$reopen_actions = $reopen_lists[0]['buckets'][0]['items'][0]['visible_actions'] ?? [];
ac_assert(
    'Active view enricher excludes reopen',
    enricher_find_action(is_array($reopen_actions) ? $reopen_actions : [], 'reopen') === null
);

$immutable_input = [
    [
        'id' => 'system:learning.recommendations',
        'source' => AA_Executable_Contract::SOURCE_SYSTEM,
        'origin_key' => 'learning.recommendations',
        'title' => 'Recomendaciones',
        'description' => null,
        'importance' => 0,
        'position' => 0,
        'status' => AA_Executable_Contract::LIST_STATUS_ACTIVE,
        'capabilities' => ['can_archive' => false],
        'buckets' => [
            [
                'key' => AA_Executable_Contract::BUCKET_PRIMARY,
                'label' => '',
                'items' => [
                    enricher_item([
                        'primary_action' => [
                            'type' => AA_Executable_Contract::ACTION_NAVIGATE,
                            'label' => 'Ir',
                            'url' => 'https://example.test',
                        ],
                        'capabilities' => [
                            'can_defer' => true,
                        ],
                    ]),
                ],
            ],
        ],
    ],
];
$immutable_before = serialize($immutable_input);
ExecutableVisibleActionsEnricher::enrich_lists($immutable_input);
ac_assert('Enricher does not mutate input lists', serialize($immutable_input) === $immutable_before);

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
