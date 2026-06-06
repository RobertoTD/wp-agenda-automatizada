<?php
/**
 * AC MC13G-A — Schema aa_task_state + TaskStateRepository.
 *
 * Ejecutar: php tests/repositories/test-task-state-repository-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$repo_file = $plugin_root . '/includes/repositories/TaskStateRepository.php';

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
ac_assert('DB_VERSION is 5', strpos($schema_src, "DB_VERSION = '5'") !== false);
ac_assert(
    'CREATE TABLE aa_task_state',
    strpos($schema_src, 'aa_task_state') !== false
        && strpos($schema_src, 'last_deferred_at datetime') !== false
        && strpos($schema_src, 'defer_until datetime') !== false
        && strpos($schema_src, 'defer_count int') !== false
        && strpos($schema_src, 'last_dismissed_at datetime') !== false
        && strpos($schema_src, 'dismiss_until datetime') !== false
        && strpos($schema_src, 'dismiss_count int') !== false
        && strpos($schema_src, 'PRIMARY KEY  (task_id)') !== false
);

$state_block_start = strpos($schema_src, 'aa_task_state');
$state_block = $state_block_start !== false
    ? substr($schema_src, $state_block_start, 900)
    : '';
ac_assert(
    'aa_task_state DDL has no FOREIGN KEY',
    $state_block !== ''
        && strpos($state_block, 'FOREIGN KEY') === false
);

// ─── Estáticos: Repository ───────────────────────────────────

$repo_src = file_get_contents($repo_file);
ac_assert('TaskStateRepository file readable', $repo_src !== false);
ac_assert('TaskStateRepository defines find_by_task_id', strpos($repo_src, 'function find_by_task_id') !== false);
ac_assert('TaskStateRepository defines upsert', strpos($repo_src, 'function upsert') !== false);
ac_assert('TaskStateRepository defines record_defer', strpos($repo_src, 'function record_defer') !== false);
ac_assert('TaskStateRepository defines record_dismiss', strpos($repo_src, 'function record_dismiss') !== false);
ac_assert('record_defer leaves defer_until null', strpos($repo_src, "'defer_until' => null") !== false);
ac_assert('record_dismiss leaves dismiss_until null', strpos($repo_src, "'dismiss_until' => null") !== false);

// ─── Integración WordPress ───────────────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $schema_file;
    require_once $plugin_root . '/includes/repositories/TaskListRepository.php';
    require_once $plugin_root . '/includes/repositories/TaskRepository.php';
    require_once $repo_file;

    AA_Schema::install();

    global $wpdb;
    $state_table = $wpdb->prefix . 'aa_task_state';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $lists_table = $wpdb->prefix . 'aa_task_lists';

    $table_exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $state_table)
    );
    ac_assert('aa_task_state table exists after install', $table_exists === $state_table);

    $missing = TaskStateRepository::find_by_task_id(99999999);
    ac_assert('find_by_task_id returns null when missing', $missing === null);

    $suffix = (string) time();
    $list_id = (int) $wpdb->insert(
        $lists_table,
        [
            'title' => 'State repo list ' . $suffix,
            'owner_type' => 'user',
            'importance' => 0,
            'status' => 'active',
            'position' => 0,
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%d', '%s', '%d', '%s']
    );
    $list_id = (int) $wpdb->insert_id;
    ac_assert('Seed list for task state tests', $list_id > 0);

    $task_id = (int) $wpdb->insert(
        $tasks_table,
        [
            'list_id' => $list_id,
            'title' => 'State repo task ' . $suffix,
            'source' => 'user',
            'importance' => 0,
            'status' => 'pending',
            'position' => 0,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%d', '%s', '%d', '%s']
    );
    $task_id = (int) $wpdb->insert_id;
    ac_assert('Seed task for task state tests', $task_id > 0);

    $task_before = TaskRepository::find_by_id($task_id);
    ac_assert('Task row exists before signals', is_array($task_before));

    $now = '2026-06-06 10:00:00';
    $first_defer = TaskStateRepository::record_defer($task_id, $now);
    ac_assert('record_defer creates row', is_array($first_defer));
    ac_assert('record_defer sets last_deferred_at', ($first_defer['last_deferred_at'] ?? '') === $now);
    ac_assert('record_defer sets defer_count=1', (int) ($first_defer['defer_count'] ?? 0) === 1);
    ac_assert('record_defer leaves defer_until null', ($first_defer['defer_until'] ?? 'x') === null);

    $second_defer = TaskStateRepository::record_defer($task_id, '2026-06-06 11:00:00');
    ac_assert('record_defer increments defer_count', (int) ($second_defer['defer_count'] ?? 0) === 2);
    ac_assert(
        'record_defer still leaves defer_until null',
        ($second_defer['defer_until'] ?? 'x') === null
    );

    $first_dismiss = TaskStateRepository::record_dismiss($task_id, '2026-06-06 12:00:00');
    ac_assert('record_dismiss updates row', is_array($first_dismiss));
    ac_assert(
        'record_dismiss sets last_dismissed_at',
        ($first_dismiss['last_dismissed_at'] ?? '') === '2026-06-06 12:00:00'
    );
    ac_assert('record_dismiss sets dismiss_count=1', (int) ($first_dismiss['dismiss_count'] ?? 0) === 1);
    ac_assert(
        'record_dismiss leaves dismiss_until null',
        ($first_dismiss['dismiss_until'] ?? 'x') === null
    );
    ac_assert(
        'record_dismiss preserves defer_count',
        (int) ($first_dismiss['defer_count'] ?? 0) === 2
    );

    $second_dismiss = TaskStateRepository::record_dismiss($task_id, '2026-06-06 13:00:00');
    ac_assert('record_dismiss increments dismiss_count', (int) ($second_dismiss['dismiss_count'] ?? 0) === 2);

    $found = TaskStateRepository::find_by_task_id($task_id);
    ac_assert('find_by_task_id returns persisted row', is_array($found) && (int) ($found['task_id'] ?? 0) === $task_id);

    $task_after = TaskRepository::find_by_id($task_id);
    ac_assert('aa_tasks status unchanged', ($task_after['status'] ?? '') === ($task_before['status'] ?? ''));
    ac_assert(
        'aa_tasks completed_at unchanged',
        ($task_after['completed_at'] ?? null) === ($task_before['completed_at'] ?? null)
    );

    $wpdb->delete($state_table, ['task_id' => $task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $task_id], ['%d']);
    $wpdb->delete($lists_table, ['id' => $list_id], ['%d']);
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
