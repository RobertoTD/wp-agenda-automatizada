<?php
/**
 * AC — UpdateTaskUseCase governance + notes limit + default_bucket write path.
 *
 * Ejecutar: php tests/application/tasks/test-update-task-governance-use-case-ac.php
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

require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/domain/tasks/class-aa-task-governance-policy.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
require_once $plugin_root . '/includes/application/tasks/UpdateTaskUseCase.php';

$update_src = file_get_contents($plugin_root . '/includes/application/tasks/UpdateTaskUseCase.php');
$create_src = file_get_contents($plugin_root . '/includes/application/tasks/CreateTaskUseCase.php');
$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/TasksAjax.php');
$support_src = file_get_contents($plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php');

ac_assert('UpdateTaskUseCase file readable', $update_src !== false);
ac_assert('UpdateTaskUseCase uses governance policy', strpos($update_src, 'AA_Task_Governance_Policy') !== false);
ac_assert('UpdateTaskUseCase rejects task_not_editable', strpos($update_src, 'task_not_editable') !== false);
ac_assert('UpdateTaskUseCase supports default_bucket', strpos($update_src, 'default_bucket') !== false);
ac_assert('UpdateTaskUseCase validates notes_too_long', strpos($update_src, 'notes_too_long') !== false);
ac_assert('CreateTaskUseCase validates notes_too_long', strpos($create_src, 'notes_too_long') !== false);
ac_assert('TaskUseCaseSupport defines TASK_NOTES_MAX_LENGTH 800', strpos($support_src, 'TASK_NOTES_MAX_LENGTH = 800') !== false);
ac_assert(
    'AJAX update task passes default_bucket',
    strpos($ajax_src, "array_key_exists('default_bucket', \$_POST)") !== false
);

$service_src = file_get_contents($plugin_root . '/assets/js/services/tasksService.js');
ac_assert('TasksService exposes updateTask', strpos($service_src, 'updateTask: updateTask') !== false);
ac_assert('TasksService updateTask posts aa_update_task', strpos($service_src, "postAction('aa_update_task'") !== false);

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';

    AA_Schema::install();

    global $wpdb;
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $suffix = (string) time();

    $created_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Governance UC list ' . $suffix,
    ]);
    ac_assert('Seed list success', !empty($created_list['success']));
    $list_id = (int) ($created_list['data']['list']['id'] ?? 0);

    $created_task = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Governance UC task ' . $suffix,
        'notes' => 'Notas iniciales',
    ]);
    ac_assert('Seed user task success', !empty($created_task['success']));
    $task_id = (int) ($created_task['data']['task']['id'] ?? 0);

    $updated = (new UpdateTaskUseCase())->execute([
        'task_id' => $task_id,
        'title' => 'Governance UC task actualizada ' . $suffix,
        'notes' => 'Notas actualizadas',
        'importance' => 3,
        'default_bucket' => 'secondary',
    ]);
    ac_assert('Update user task success', !empty($updated['success']));
    ac_assert(
        'Update user task persists fields',
        ($updated['data']['task']['title'] ?? '') === 'Governance UC task actualizada ' . $suffix
        && ($updated['data']['task']['notes'] ?? '') === 'Notas actualizadas'
        && (int) ($updated['data']['task']['importance'] ?? -1) === 3
        && ($updated['data']['task']['default_bucket'] ?? '') === 'secondary'
    );

    $long_notes = str_repeat('x', TaskUseCaseSupport::TASK_NOTES_MAX_LENGTH + 1);
    $create_long = (new CreateTaskUseCase())->execute([
        'list_id' => $list_id,
        'title' => 'Notas largas create ' . $suffix,
        'notes' => $long_notes,
    ]);
    ac_assert(
        'CreateTaskUseCase rejects notes_too_long',
        empty($create_long['success']) && ($create_long['error']['code'] ?? '') === 'notes_too_long'
    );

    $update_long = (new UpdateTaskUseCase())->execute([
        'task_id' => $task_id,
        'notes' => $long_notes,
    ]);
    ac_assert(
        'UpdateTaskUseCase rejects notes_too_long',
        empty($update_long['success']) && ($update_long['error']['code'] ?? '') === 'notes_too_long'
    );

    $seeded_list = SeededTaskRepository::upsert_seeded_list([
        'title' => 'Agenda governance ' . $suffix,
        'description' => 'Lista developer',
        'owner_type' => 'developer',
        'source_category' => 'agenda_app',
        'origin_key' => 'governance.test.' . $suffix,
        'managed_by' => 'developer',
        'status' => 'active',
        'importance' => 0,
        'position' => 0,
    ]);
    ac_assert('Seed developer list success', is_array($seeded_list));
    $seeded_list_id = (int) ($seeded_list['id'] ?? 0);

    $seeded_task = SeededTaskRepository::upsert_seeded_task([
        'list_id' => $seeded_list_id,
        'title' => 'Tarea developer ' . $suffix,
        'notes' => 'No editable',
        'source_category' => 'agenda_app',
        'origin_key' => 'governance.task.' . $suffix,
        'managed_by' => 'developer',
        'default_bucket' => 'primary',
        'completion_type' => 'manual',
    ]);
    ac_assert('Seed developer task success', is_array($seeded_task));
    $developer_task_id = (int) ($seeded_task['id'] ?? 0);

    $blocked_update = (new UpdateTaskUseCase())->execute([
        'task_id' => $developer_task_id,
        'title' => 'Intento editar developer',
    ]);
    ac_assert(
        'UpdateTaskUseCase rejects developer task',
        empty($blocked_update['success']) && ($blocked_update['error']['code'] ?? '') === 'task_not_editable'
    );

    $wpdb->delete($tasks_table, ['list_id' => $list_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $developer_task_id], ['%d']);
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
