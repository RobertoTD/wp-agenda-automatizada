<?php
/**
 * AC MC13O-C1 — TaskActionRepository.
 *
 * Ejecutar: php tests/repositories/test-task-actions-repository-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$repo_file = $plugin_root . '/includes/repositories/TaskActionRepository.php';

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

// ─── Estáticos ───────────────────────────────────────────────

$repo_src = file_get_contents($repo_file);
ac_assert('TaskActionRepository file readable', $repo_src !== false);
ac_assert('TaskActionRepository defines find_by_task_and_key', strpos($repo_src, 'function find_by_task_and_key') !== false);
ac_assert('TaskActionRepository defines upsert', strpos($repo_src, 'function upsert') !== false);
ac_assert('TaskActionRepository defines list_by_task_id', strpos($repo_src, 'function list_by_task_id') !== false);
ac_assert('TaskActionRepository defines list_by_task_ids', strpos($repo_src, 'function list_by_task_ids') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $repo_file;

ac_assert('TaskActionRepository class exists', class_exists('TaskActionRepository'));
ac_assert('upsert method exists', method_exists('TaskActionRepository', 'upsert'));

// ─── Integración WP (opcional) ───────────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $schema_file;
    require_once $repo_file;

    AA_Schema::install();

    global $wpdb;
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $actions_table = $wpdb->prefix . 'aa_task_actions';
    $suffix = (string) time();

    $wpdb->insert(
        $lists_table,
        [
            'title' => 'AC actions list ' . $suffix,
            'owner_type' => 'developer',
            'source_category' => 'ac_test',
            'origin_key' => 'actions.list.' . $suffix,
            'managed_by' => 'developer',
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );
    $list_id = (int) $wpdb->insert_id;
    ac_assert('Seed list for action repository', $list_id > 0);

    $wpdb->insert(
        $tasks_table,
        [
            'list_id' => $list_id,
            'title' => 'AC actions task ' . $suffix,
            'status' => 'pending',
            'source' => 'system',
            'source_category' => 'ac_test',
            'origin_key' => 'actions.task.' . $suffix,
            'managed_by' => 'developer',
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );
    $task_id = (int) $wpdb->insert_id;
    ac_assert('Seed task for action repository', $task_id > 0);

    $first = TaskActionRepository::upsert($task_id, [
        'action_key' => 'navigate.settings',
        'type' => 'navigate',
        'label' => 'Ir',
        'target_module' => 'settings',
        'placement' => 'primary',
        'category' => 'mechanical',
    ]);
    ac_assert('upsert action inserts row', is_array($first) && ($first['action_key'] ?? '') === 'navigate.settings');
    $first_id = (int) ($first['id'] ?? 0);
    ac_assert('inserted action has id', $first_id > 0);

    $second = TaskActionRepository::upsert($task_id, [
        'action_key' => 'navigate.settings',
        'type' => 'navigate',
        'label' => 'Abrir',
        'target_module' => 'settings',
        'placement' => 'primary',
        'category' => 'mechanical',
    ]);
    ac_assert('second upsert preserves id', (int) ($second['id'] ?? 0) === $first_id);
    ac_assert('second upsert updates metadata', ($second['label'] ?? '') === 'Abrir');

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$actions_table} WHERE task_id = %d AND action_key = %s",
            $task_id,
            'navigate.settings'
        )
    );
    ac_assert('upsert twice does not duplicate', $count === 1, 'count=' . $count);

    $listed = TaskActionRepository::list_by_task_id($task_id);
    ac_assert('list_by_task_id returns action', count($listed) === 1 && ($listed[0]['action_key'] ?? '') === 'navigate.settings');

    $grouped = TaskActionRepository::list_by_task_ids([$task_id]);
    ac_assert('list_by_task_ids groups action', isset($grouped[$task_id]) && count($grouped[$task_id]) === 1);

    $wpdb->delete($actions_table, ['task_id' => $task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $task_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar repository.\n";
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
