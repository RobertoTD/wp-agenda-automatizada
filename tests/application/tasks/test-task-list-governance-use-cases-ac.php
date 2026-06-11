<?php
/**
 * AC — Task list governance guards (UpdateTaskListUseCase + ArchiveTaskListUseCase).
 *
 * Ejecutar: php tests/application/tasks/test-task-list-governance-use-cases-ac.php
 */

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

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    require_once $wp_load;
} elseif (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return is_string($str) ? trim($str) : '';
    }
}

require_once $plugin_root . '/includes/domain/tasks/class-aa-task-list-governance-policy.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/UpdateTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/ArchiveTaskListUseCase.php';

$update_src = file_get_contents($plugin_root . '/includes/application/tasks/UpdateTaskListUseCase.php');
$archive_src = file_get_contents($plugin_root . '/includes/application/tasks/ArchiveTaskListUseCase.php');
$policy_src = file_get_contents($plugin_root . '/includes/domain/tasks/class-aa-task-list-governance-policy.php');

ac_assert('Task list governance policy file readable', $policy_src !== false);
ac_assert('Policy defines can_edit_list', strpos($policy_src, 'can_edit_list') !== false);
ac_assert('Policy defines can_archive_list', strpos($policy_src, 'can_archive_list') !== false);
ac_assert('UpdateTaskListUseCase uses list governance policy', strpos($update_src, 'AA_Task_List_Governance_Policy') !== false);
ac_assert('UpdateTaskListUseCase rejects list_not_editable', strpos($update_src, 'list_not_editable') !== false);
ac_assert('ArchiveTaskListUseCase uses list governance policy', strpos($archive_src, 'AA_Task_List_Governance_Policy') !== false);
ac_assert('ArchiveTaskListUseCase rejects list_not_archivable', strpos($archive_src, 'list_not_archivable') !== false);

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';

    AA_Schema::install();

    global $wpdb;
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $suffix = (string) time();

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'List governance UC ' . $suffix,
        'description' => 'Editable user list',
    ]);
    ac_assert('Create user list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);

    $updated_list = (new UpdateTaskListUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'List governance UC updated ' . $suffix,
        'description' => 'Updated description',
    ]);
    ac_assert('Update user list success', !empty($updated_list['success']));
    ac_assert(
        'Update user list persists title',
        ($updated_list['data']['list']['title'] ?? '') === 'List governance UC updated ' . $suffix
    );

    $archived_list = (new ArchiveTaskListUseCase())->execute(['list_id' => $list_id]);
    ac_assert('Archive user list success', !empty($archived_list['success']));
    ac_assert(
        'Archive user list status archived',
        ($archived_list['data']['list']['status'] ?? '') === 'archived'
    );

    $seeded_list = SeededTaskRepository::upsert_seeded_list([
        'title' => 'Agenda list governance ' . $suffix,
        'description' => 'Developer list',
        'owner_type' => 'developer',
        'source_category' => 'agenda_app',
        'origin_key' => 'list.governance.' . $suffix,
        'managed_by' => 'developer',
        'status' => 'active',
        'importance' => 0,
        'position' => 0,
    ]);
    ac_assert('Seed developer list success', is_array($seeded_list));
    $seeded_list_id = (int) ($seeded_list['id'] ?? 0);

    $blocked_update = (new UpdateTaskListUseCase())->execute([
        'list_id' => $seeded_list_id,
        'title' => 'Intento editar developer list',
    ]);
    ac_assert(
        'UpdateTaskListUseCase rejects developer agenda list',
        empty($blocked_update['success']) && ($blocked_update['error']['code'] ?? '') === 'list_not_editable'
    );

    $blocked_archive = (new ArchiveTaskListUseCase())->execute([
        'list_id' => $seeded_list_id,
    ]);
    ac_assert(
        'ArchiveTaskListUseCase rejects developer agenda list',
        empty($blocked_archive['success']) && ($blocked_archive['error']['code'] ?? '') === 'list_not_archivable'
    );

    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $seeded_list_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar Use Cases.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
