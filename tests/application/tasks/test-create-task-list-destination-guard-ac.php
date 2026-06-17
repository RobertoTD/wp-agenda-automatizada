<?php
/**
 * AC MC1 — CreateTaskUseCase guard + list governance for appointment_actions.
 *
 * Ejecutar: php tests/application/tasks/test-create-task-list-destination-guard-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
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

$policy_file = $plugin_root . '/includes/domain/tasks/class-aa-task-list-governance-policy.php';
$create_file = $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';

$policy_src = file_get_contents($policy_file);
$create_src = file_get_contents($create_file);

ac_assert('Policy file readable', $policy_src !== false);
ac_assert('Policy defines can_accept_user_created_task', strpos($policy_src, 'can_accept_user_created_task') !== false);
ac_assert('CreateTaskUseCase uses list governance policy', strpos($create_src, 'AA_Task_List_Governance_Policy') !== false);
ac_assert('CreateTaskUseCase rejects list_not_manual_destination', strpos($create_src, 'list_not_manual_destination') !== false);
ac_assert('CreateTaskUseCase has no bypass flag', strpos($create_src, 'allow_system') === false && strpos($create_src, 'bypass') === false);

require_once $policy_file;
$policy = new AA_Task_List_Governance_Policy();

ac_assert(
    'User list accepts manual tasks',
    $policy->can_accept_user_created_task([
        'source_category' => 'user',
        'managed_by' => 'user',
        'status' => 'active',
    ]) === true
);
ac_assert(
    'Developer agenda_app list rejects manual tasks',
    $policy->can_accept_user_created_task([
        'source_category' => 'agenda_app',
        'managed_by' => 'developer',
        'status' => 'active',
    ]) === false
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-actions-catalog.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskListRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskRepository.php';
    require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/SyncAppointmentActionsListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/UpdateTaskListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/ArchiveTaskListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/DeleteTaskListUseCase.php';

    AA_Schema::install();

    $suffix = (string) time();
    (new SyncAppointmentActionsListUseCase())->execute();
    $seeded_list = SeededTaskRepository::find_list_by_origin('agenda_app', 'appointment_actions');
    $seeded_list_id = (int) ($seeded_list['id'] ?? 0);
    ac_assert('appointment_actions list seeded', $seeded_list_id > 0);

    $blocked_create = (new CreateTaskUseCase())->execute([
        'list_id' => $seeded_list_id,
        'title' => 'Tarea manual bloqueada ' . $suffix,
    ]);
    ac_assert(
        'CreateTaskUseCase rejects manual task in appointment_actions',
        empty($blocked_create['success'])
        && ($blocked_create['error']['code'] ?? '') === 'list_not_manual_destination'
    );

    $blocked_update = (new UpdateTaskListUseCase())->execute([
        'list_id' => $seeded_list_id,
        'title' => 'Renombrar bloqueado',
    ]);
    ac_assert(
        'UpdateTaskListUseCase rejects appointment_actions',
        empty($blocked_update['success']) && ($blocked_update['error']['code'] ?? '') === 'list_not_editable'
    );

    $blocked_archive = (new ArchiveTaskListUseCase())->execute(['list_id' => $seeded_list_id]);
    ac_assert(
        'ArchiveTaskListUseCase rejects appointment_actions',
        empty($blocked_archive['success']) && ($blocked_archive['error']['code'] ?? '') === 'list_not_archivable'
    );

    $blocked_delete = (new DeleteTaskListUseCase())->execute(['list_id' => $seeded_list_id]);
    ac_assert(
        'DeleteTaskListUseCase rejects appointment_actions',
        empty($blocked_delete['success']) && ($blocked_delete['error']['code'] ?? '') === 'list_not_deletable'
    );

    $user_list = (new CreateTaskListUseCase())->execute([
        'title' => 'Lista user MC1 ' . $suffix,
    ]);
    $user_list_id = (int) ($user_list['data']['list']['id'] ?? 0);
    ac_assert('User list created', $user_list_id > 0);

    $user_create = (new CreateTaskUseCase())->execute([
        'list_id' => $user_list_id,
        'title' => 'Tarea user MC1 ' . $suffix,
    ]);
    ac_assert('CreateTaskUseCase allows manual task in user list', !empty($user_create['success']));

    global $wpdb;
    $task_id = (int) ($user_create['data']['task']['id'] ?? 0);
    if ($task_id > 0) {
        $wpdb->delete($wpdb->prefix . 'aa_tasks', ['id' => $task_id], ['%d']);
    }
    $wpdb->delete($wpdb->prefix . 'aa_task_lists', ['id' => $user_list_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar guards reales.\n";
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
