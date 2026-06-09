<?php
/**
 * AC MC1 — Schema + TaskListRepository + TaskRepository.
 *
 * Ejecutar: php tests/repositories/test-task-repositories-ac.php
 *
 * Parte estática: no requiere WordPress.
 * Parte BD (opcional): define AA_WP_ROOT con ruta al wp-load.php y ejecuta migración + CRUD.
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$list_repo_file = $plugin_root . '/includes/repositories/TaskListRepository.php';
$task_repo_file = $plugin_root . '/includes/repositories/TaskRepository.php';

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

// ─── Estáticos: Schema ───────────────────────────────────────

$schema_src = file_get_contents($schema_file);
ac_assert('Schema file readable', $schema_src !== false);
ac_assert('DB_VERSION is 6', strpos($schema_src, "DB_VERSION = '6'") !== false);
ac_assert(
    'CREATE TABLE aa_task_lists',
    strpos($schema_src, 'aa_task_lists') !== false
        && strpos($schema_src, 'owner_type varchar(20)') !== false
        && strpos($schema_src, 'source_category varchar(20)') !== false
        && strpos($schema_src, 'origin_key varchar(100)') !== false
        && strpos($schema_src, 'managed_by varchar(20)') !== false
        && strpos($schema_src, 'importance int') !== false
        && strpos($schema_src, 'status varchar(20)') !== false
        && strpos($schema_src, 'position int') !== false
        && strpos($schema_src, 'KEY source_category (source_category)') !== false
        && strpos($schema_src, 'uniq_list_origin') !== false
);
ac_assert(
    'CREATE TABLE aa_tasks',
    strpos($schema_src, 'aa_tasks') !== false
        && strpos($schema_src, 'list_id bigint(20) unsigned') !== false
        && strpos($schema_src, 'source varchar(20)') !== false
        && strpos($schema_src, 'source_category varchar(20)') !== false
        && strpos($schema_src, 'origin_key varchar(100)') !== false
        && strpos($schema_src, 'managed_by varchar(20)') !== false
        && strpos($schema_src, 'default_bucket varchar(20)') !== false
        && strpos($schema_src, 'completion_type varchar(20)') !== false
        && strpos($schema_src, 'completion_fact_key varchar(100)') !== false
        && strpos($schema_src, 'due_at datetime') !== false
        && strpos($schema_src, 'completed_at datetime') !== false
        && strpos($schema_src, 'KEY source_category (source_category)') !== false
        && strpos($schema_src, 'uniq_task_origin') !== false
);
$tasks_block_start = strpos($schema_src, 'aa_tasks');
$tasks_block = $tasks_block_start !== false
    ? substr($schema_src, $tasks_block_start, 800)
    : '';
ac_assert(
    'aa_tasks DDL has no FOREIGN KEY',
    $tasks_block !== ''
        && strpos($tasks_block, 'FOREIGN KEY') === false
);

// ─── Estáticos: Repositories ─────────────────────────────────

$list_repo_src = file_get_contents($list_repo_file);
ac_assert('TaskListRepository file readable', $list_repo_src !== false);
ac_assert('TaskListRepository defines create', strpos($list_repo_src, 'function create') !== false);
ac_assert('TaskListRepository defines find_by_id', strpos($list_repo_src, 'function find_by_id') !== false);
ac_assert('TaskListRepository defines list_all', strpos($list_repo_src, 'function list_all') !== false);
ac_assert('TaskListRepository defines update', strpos($list_repo_src, 'function update') !== false);
ac_assert('TaskListRepository defines archive', strpos($list_repo_src, 'function archive') !== false);

$task_repo_src = file_get_contents($task_repo_file);
ac_assert('TaskRepository file readable', $task_repo_src !== false);
ac_assert('TaskRepository defines create', strpos($task_repo_src, 'function create') !== false);
ac_assert('TaskRepository defines find_by_id', strpos($task_repo_src, 'function find_by_id') !== false);
ac_assert('TaskRepository defines list_by_list_id', strpos($task_repo_src, 'function list_by_list_id') !== false);
ac_assert('TaskRepository defines update_status', strpos($task_repo_src, 'function update_status') !== false);
ac_assert('TaskRepository defines mark_completed', strpos($task_repo_src, 'function mark_completed') !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
$wp_integration = $wp_load !== '' && is_readable($wp_load);

if ($wp_integration) {
    require_once $wp_load;
} elseif (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $list_repo_file;
require_once $task_repo_file;

ac_assert('TaskListRepository class exists', class_exists('TaskListRepository'));
ac_assert('TaskRepository class exists', class_exists('TaskRepository'));
ac_assert(
    'Repository methods are public static',
    method_exists('TaskListRepository', 'create')
    && method_exists('TaskRepository', 'list_by_list_id')
);

// ─── Integración WP (opcional) ─────────────────────────────

if ($wp_integration) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $schema_file;

    AA_Schema::install();
    AA_Schema::install();

    global $wpdb;
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $tasks_table = $wpdb->prefix . 'aa_tasks';

    $lists_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lists_table));
    ac_assert('aa_task_lists exists after install', $lists_exists === $lists_table, $lists_table);

    $tasks_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tasks_table));
    ac_assert('aa_tasks exists after install', $tasks_exists === $tasks_table, $tasks_table);

    foreach (['source_category', 'origin_key', 'managed_by'] as $column) {
        $exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$lists_table} LIKE %s", $column));
        ac_assert("aa_task_lists has {$column}", !empty($exists));
    }

    foreach (['source_category', 'origin_key', 'managed_by', 'default_bucket', 'completion_type', 'completion_fact_key'] as $column) {
        $exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$tasks_table} LIKE %s", $column));
        ac_assert("aa_tasks has {$column}", !empty($exists));
    }

    $list_origin_index = $wpdb->get_results("SHOW INDEX FROM {$lists_table} WHERE Key_name = 'uniq_list_origin'");
    ac_assert('aa_task_lists has uniq_list_origin', !empty($list_origin_index));
    $task_origin_index = $wpdb->get_results("SHOW INDEX FROM {$tasks_table} WHERE Key_name = 'uniq_task_origin'");
    ac_assert('aa_tasks has uniq_task_origin', !empty($task_origin_index));

    $suffix = (string) time();
    $list = TaskListRepository::create([
        'title' => 'Lista AC ' . $suffix,
        'description' => 'Contexto de prueba',
        'owner_type' => 'user',
        'importance' => -5,
        'position' => 10,
    ]);
    ac_assert('create list returns row', is_array($list) && ($list['title'] ?? '') === 'Lista AC ' . $suffix);
    ac_assert('create list maps importance', ($list['importance'] ?? null) === -5);
    ac_assert('create list default status active', ($list['status'] ?? '') === 'active');

    $list_id = (int) ($list['id'] ?? 0);
    ac_assert('create list has id', $list_id > 0);

    $found_list = TaskListRepository::find_by_id($list_id);
    ac_assert('find_by_id list matches create', is_array($found_list) && ($found_list['id'] ?? 0) === $list_id);

    $all_active = TaskListRepository::list_all('active');
    ac_assert('list_all active includes new list', count(array_filter(
        $all_active,
        static function ($row) use ($list_id) {
            return is_array($row) && (int) ($row['id'] ?? 0) === $list_id;
        }
    )) === 1);

    $updated_list = TaskListRepository::update($list_id, [
        'title' => 'Lista AC actualizada ' . $suffix,
        'importance' => 3,
    ]);
    ac_assert('update list title', ($updated_list['title'] ?? '') === 'Lista AC actualizada ' . $suffix);
    ac_assert('update list importance', ($updated_list['importance'] ?? null) === 3);

    $archived_list = TaskListRepository::archive($list_id);
    ac_assert('archive list status', ($archived_list['status'] ?? '') === 'archived');

    $active_after_archive = TaskListRepository::list_all('active');
    ac_assert(
        'archived list excluded from list_all(active)',
        count(array_filter(
            $active_after_archive,
            static function ($row) use ($list_id) {
                return is_array($row) && (int) ($row['id'] ?? 0) === $list_id;
            }
        )) === 0
    );

    $archived_recent = TaskListRepository::list_archived_recent_first();
    ac_assert('list_archived_recent_first returns array', is_array($archived_recent));
    ac_assert(
        'list_archived_recent_first includes archived list',
        count(array_filter(
            $archived_recent,
            static function ($row) use ($list_id) {
                return is_array($row)
                    && (int) ($row['id'] ?? 0) === $list_id
                    && ($row['status'] ?? '') === 'archived';
            }
        )) === 1
    );
    ac_assert(
        'list_archived_recent_first excludes active lists',
        count(array_filter(
            $archived_recent,
            static function ($row) {
                return is_array($row) && ($row['status'] ?? '') === 'active';
            }
        )) === 0
    );

    $older_archived = TaskListRepository::create([
        'title' => 'Lista archivada antigua ' . $suffix,
        'owner_type' => 'user',
        'status' => 'archived',
    ]);
    $older_archived_id = (int) ($older_archived['id'] ?? 0);
    ac_assert('older archived list has id', $older_archived_id > 0);

    if ($older_archived_id > 0) {
        $wpdb->update(
            $lists_table,
            ['updated_at' => '2020-01-01 00:00:00'],
            ['id' => $older_archived_id],
            ['%s'],
            ['%d']
        );
    }

    $archived_ordered = TaskListRepository::list_archived_recent_first();
    $ordered_ids = array_map(
        static function ($row) {
            return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
        },
        $archived_ordered
    );
    $list_id_pos = array_search($list_id, $ordered_ids, true);
    $older_pos = array_search($older_archived_id, $ordered_ids, true);
    ac_assert(
        'list_archived_recent_first orders updated_at DESC',
        $list_id_pos !== false
        && $older_pos !== false
        && $list_id_pos < $older_pos
    );

    $restored_list = TaskListRepository::restore($list_id);
    ac_assert('restore list status active', ($restored_list['status'] ?? '') === 'active');

    $empty_list = TaskListRepository::create([
        'title' => 'Lista vacía AC ' . $suffix,
    ]);
    $empty_list_id = (int) ($empty_list['id'] ?? 0);
    ac_assert('create empty list has id', $empty_list_id > 0);

    $empty_tasks = TaskRepository::list_by_list_id($empty_list_id);
    ac_assert('list_by_list_id empty list returns array', is_array($empty_tasks));
    ac_assert('list_by_list_id empty list has zero tasks', count($empty_tasks) === 0);

    $task = TaskRepository::create([
        'list_id' => $empty_list_id,
        'title' => 'Tarea AC ' . $suffix,
        'notes' => 'Notas de prueba',
        'source' => 'user',
        'importance' => 7,
        'due_at' => '2026-06-15 09:00:00',
        'position' => 1,
    ]);
    ac_assert('create task returns row', is_array($task) && ($task['title'] ?? '') === 'Tarea AC ' . $suffix);
    ac_assert('create task maps list_id', (int) ($task['list_id'] ?? 0) === $empty_list_id);
    ac_assert('create task default status pending', ($task['status'] ?? '') === 'pending');

    $task_id = (int) ($task['id'] ?? 0);
    ac_assert('create task has id', $task_id > 0);

    $found_task = TaskRepository::find_by_id($task_id);
    ac_assert('find_by_id task matches create', is_array($found_task) && ($found_task['id'] ?? 0) === $task_id);

    $tasks_in_list = TaskRepository::list_by_list_id($empty_list_id);
    ac_assert('list_by_list_id returns one task', count($tasks_in_list) === 1);
    ac_assert('list_by_list_id task id matches', (int) ($tasks_in_list[0]['id'] ?? 0) === $task_id);

    $status_updated = TaskRepository::update_status($task_id, 'pending');
    ac_assert('update_status keeps pending', ($status_updated['status'] ?? '') === 'pending');

    $completed = TaskRepository::mark_completed($task_id, '2026-06-04 18:30:00');
    ac_assert('mark_completed status done', ($completed['status'] ?? '') === 'done');
    ac_assert('mark_completed sets completed_at', ($completed['completed_at'] ?? '') === '2026-06-04 18:30:00');

    $invalid_list_tasks = TaskRepository::list_by_list_id(0);
    ac_assert('list_by_list_id invalid id returns empty array', $invalid_list_tasks === []);

    $wpdb->delete($tasks_table, ['list_id' => $empty_list_id], ['%d']);
    $wpdb->delete($tasks_table, ['list_id' => $list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $empty_list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $older_archived_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar migración y CRUD.\n";
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
