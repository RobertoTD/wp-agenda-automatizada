<?php
/**
 * AC MC13O-C1 — SeededTaskRepository.
 *
 * Ejecutar: php tests/repositories/test-seeded-task-repository-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$repo_file = $plugin_root . '/includes/repositories/SeededTaskRepository.php';

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
ac_assert('SeededTaskRepository file readable', $repo_src !== false);
ac_assert('SeededTaskRepository defines find_list_by_origin', strpos($repo_src, 'function find_list_by_origin') !== false);
ac_assert('SeededTaskRepository defines upsert_seeded_list', strpos($repo_src, 'function upsert_seeded_list') !== false);
ac_assert('SeededTaskRepository defines find_task_by_origin', strpos($repo_src, 'function find_task_by_origin') !== false);
ac_assert('SeededTaskRepository defines upsert_seeded_task', strpos($repo_src, 'function upsert_seeded_task') !== false);
ac_assert('Task update does not reset status', strpos($repo_src, "unset(\$payload['source_category'], \$payload['origin_key'], \$payload['status'], \$payload['completed_at'])") !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $repo_file;

ac_assert('SeededTaskRepository class exists', class_exists('SeededTaskRepository'));
ac_assert('upsert_seeded_task method exists', method_exists('SeededTaskRepository', 'upsert_seeded_task'));

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
    $suffix = (string) time();
    $source = 'ac_test';
    $list_origin = 'seeded.list.' . $suffix;
    $task_origin = 'seeded.task.' . $suffix;

    $first_list = SeededTaskRepository::upsert_seeded_list([
        'title' => 'Seeded AC list',
        'description' => 'Primera descripción',
        'owner_type' => 'developer',
        'source_category' => $source,
        'origin_key' => $list_origin,
        'managed_by' => 'developer',
        'status' => 'active',
        'importance' => 0,
        'position' => 0,
    ]);
    ac_assert('upsert_seeded_list inserts row', is_array($first_list) && ($first_list['origin_key'] ?? '') === $list_origin);
    $list_id = (int) ($first_list['id'] ?? 0);
    ac_assert('inserted seeded list has id', $list_id > 0);

    $second_list = SeededTaskRepository::upsert_seeded_list([
        'title' => 'Seeded AC list updated',
        'description' => 'Segunda descripción',
        'owner_type' => 'developer',
        'source_category' => $source,
        'origin_key' => $list_origin,
        'managed_by' => 'developer',
        'status' => 'active',
        'importance' => 1,
        'position' => 2,
    ]);
    ac_assert('second list upsert preserves id', (int) ($second_list['id'] ?? 0) === $list_id);
    ac_assert('second list upsert updates definition', ($second_list['title'] ?? '') === 'Seeded AC list updated');

    $first_task = SeededTaskRepository::upsert_seeded_task([
        'list_id' => $list_id,
        'title' => 'Seeded AC task',
        'notes' => 'Primera nota',
        'status' => 'pending',
        'source' => 'system',
        'source_category' => $source,
        'origin_key' => $task_origin,
        'managed_by' => 'developer',
        'importance' => 10,
        'position' => 0,
        'default_bucket' => 'primary',
        'completion_type' => 'system',
        'completion_fact_key' => 'ac_fact',
        'due_at' => null,
        'completed_at' => null,
    ]);
    ac_assert('upsert_seeded_task inserts row', is_array($first_task) && ($first_task['origin_key'] ?? '') === $task_origin);
    $task_id = (int) ($first_task['id'] ?? 0);
    ac_assert('inserted seeded task has id', $task_id > 0);

    $wpdb->update(
        $tasks_table,
        ['status' => 'completed', 'completed_at' => '2026-06-09 10:00:00'],
        ['id' => $task_id],
        ['%s', '%s'],
        ['%d']
    );

    $second_task = SeededTaskRepository::upsert_seeded_task([
        'list_id' => $list_id,
        'title' => 'Seeded AC task updated',
        'notes' => 'Segunda nota',
        'status' => 'pending',
        'source' => 'system',
        'source_category' => $source,
        'origin_key' => $task_origin,
        'managed_by' => 'developer',
        'importance' => 20,
        'position' => 1,
        'default_bucket' => 'secondary',
        'completion_type' => 'manual',
        'completion_fact_key' => null,
        'due_at' => null,
        'completed_at' => null,
    ]);
    ac_assert('second task upsert preserves id', (int) ($second_task['id'] ?? 0) === $task_id);
    ac_assert('second task upsert updates definition', ($second_task['title'] ?? '') === 'Seeded AC task updated');
    ac_assert('second task upsert preserves status', ($second_task['status'] ?? '') === 'completed');
    ac_assert('second task upsert preserves completed_at', ($second_task['completed_at'] ?? '') === '2026-06-09 10:00:00');

    $task_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tasks_table} WHERE source_category = %s AND origin_key = %s",
            $source,
            $task_origin
        )
    );
    ac_assert('task upsert twice does not duplicate', $task_count === 1, 'count=' . $task_count);

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
