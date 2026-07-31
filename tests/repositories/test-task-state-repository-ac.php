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
ac_assert('DB_VERSION is 13', strpos($schema_src, "DB_VERSION = '13'") !== false);
ac_assert(
    'CREATE TABLE aa_task_state',
    strpos($schema_src, 'aa_task_state') !== false
        && strpos($schema_src, 'last_deferred_at datetime') !== false
        && strpos($schema_src, 'defer_until datetime') !== false
        && strpos($schema_src, 'defer_count int') !== false
        && strpos($schema_src, 'last_dismissed_at datetime') !== false
        && strpos($schema_src, 'dismiss_until datetime') !== false
        && strpos($schema_src, 'dismiss_count int') !== false
        && strpos($schema_src, 'completed_by_system tinyint(1)') !== false
        && strpos($schema_src, 'system_completed_at datetime') !== false
        && strpos($schema_src, 'last_system_evaluated_at datetime') !== false
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
ac_assert('TaskStateRepository defines find_by_task_ids', strpos($repo_src, 'function find_by_task_ids') !== false);
ac_assert('record_defer leaves defer_until null', strpos($repo_src, "'defer_until' => null") !== false);
ac_assert('record_dismiss accepts dismiss_until parameter', strpos($repo_src, '$dismiss_until = null') !== false);
ac_assert('TaskStateRepository defines clear_dismiss_hiding_effect', strpos($repo_src, 'function clear_dismiss_hiding_effect') !== false);
ac_assert(
    'TaskStateRepository defines clear_dismiss_hiding_effect_for_task_ids',
    strpos($repo_src, 'function clear_dismiss_hiding_effect_for_task_ids') !== false
);
ac_assert('TaskStateRepository maps completed_by_system', strpos($repo_src, "'completed_by_system' =>") !== false);
ac_assert('TaskStateRepository maps system_completed_at', strpos($repo_src, "'system_completed_at' =>") !== false);
ac_assert('TaskStateRepository maps last_system_evaluated_at', strpos($repo_src, "'last_system_evaluated_at' =>") !== false);
ac_assert(
    'TaskStateRepository defines record_system_completion_evaluation',
    strpos($repo_src, 'function record_system_completion_evaluation') !== false
);
ac_assert(
    'TaskStateRepository defines apply_legacy_defer_migration',
    strpos($repo_src, 'function apply_legacy_defer_migration') !== false
);
ac_assert(
    'apply_legacy_defer_migration uses max defer_count',
    strpos($repo_src, 'max($existing === null ? 0 : (int) ($existing[\'defer_count\'] ?? 0), 1)') !== false
);

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

    foreach (['completed_by_system', 'system_completed_at', 'last_system_evaluated_at'] as $column) {
        $exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$state_table} LIKE %s", $column));
        ac_assert("aa_task_state has {$column}", !empty($exists));
    }

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
        'record_dismiss without dismiss_until leaves dismiss_until null (legacy permanent)',
        ($first_dismiss['dismiss_until'] ?? 'x') === null
    );
    ac_assert(
        'record_dismiss preserves defer_count',
        (int) ($first_dismiss['defer_count'] ?? 0) === 2
    );

    $second_dismiss = TaskStateRepository::record_dismiss($task_id, '2026-06-06 13:00:00');
    ac_assert('record_dismiss increments dismiss_count', (int) ($second_dismiss['dismiss_count'] ?? 0) === 2);

    $cleared = TaskStateRepository::clear_dismiss_hiding_effect($task_id, '2026-06-06 14:00:00');
    ac_assert('clear_dismiss_hiding_effect updates row', is_array($cleared));
    ac_assert(
        'clear_dismiss_hiding_effect sets dismiss_until',
        ($cleared['dismiss_until'] ?? '') === '2026-06-06 14:00:00'
    );
    ac_assert(
        'clear_dismiss_hiding_effect preserves last_dismissed_at',
        ($cleared['last_dismissed_at'] ?? '') === '2026-06-06 13:00:00'
    );
    ac_assert(
        'clear_dismiss_hiding_effect preserves dismiss_count',
        (int) ($cleared['dismiss_count'] ?? 0) === 2
    );
    ac_assert(
        'clear_dismiss_hiding_effect preserves defer_count',
        (int) ($cleared['defer_count'] ?? 0) === 2
    );

    $second_task_insert = $wpdb->insert(
        $tasks_table,
        [
            'list_id' => $list_id,
            'title' => 'Second dismiss task ' . $suffix,
            'notes' => '',
            'importance' => 0,
            'status' => 'pending',
            'position' => 1,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%d', '%s', '%d', '%s']
    );
    $second_task_id = (int) $wpdb->insert_id;
    ac_assert('Seed second task for bulk clear', $second_task_id > 0);
    TaskStateRepository::record_dismiss($second_task_id, '2026-06-06 15:00:00');

    $third_task_insert = $wpdb->insert(
        $tasks_table,
        [
            'list_id' => $list_id,
            'title' => 'Temp dismiss task ' . $suffix,
            'notes' => '',
            'importance' => 0,
            'status' => 'pending',
            'position' => 2,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%d', '%s', '%d', '%s']
    );
    $third_task_id = (int) $wpdb->insert_id;
    ac_assert('Seed third task for temp dismiss_until', $third_task_id > 0);

    $temp_dismiss = TaskStateRepository::record_dismiss(
        $third_task_id,
        '2026-06-06 15:00:00',
        '2026-06-07 12:00:00'
    );
    ac_assert('record_dismiss persists explicit dismiss_until', is_array($temp_dismiss));
    ac_assert(
        'record_dismiss writes future dismiss_until',
        ($temp_dismiss['dismiss_until'] ?? '') === '2026-06-07 12:00:00'
    );
    ac_assert(
        'record_dismiss with dismiss_until preserves last_dismissed_at',
        ($temp_dismiss['last_dismissed_at'] ?? '') === '2026-06-06 15:00:00'
    );

    $bulk = TaskStateRepository::clear_dismiss_hiding_effect_for_task_ids(
        [$task_id, $second_task_id, 99999999],
        '2026-06-06 16:00:00'
    );
    ac_assert('bulk clear returns map keyed by task_id', isset($bulk[$task_id]) && isset($bulk[$second_task_id]));
    ac_assert('bulk clear omits missing ids', !isset($bulk[99999999]));
    ac_assert(
        'bulk clear writes dismiss_until on second task',
        ($bulk[$second_task_id]['dismiss_until'] ?? '') === '2026-06-06 16:00:00'
    );

    $found = TaskStateRepository::find_by_task_id($task_id);
    ac_assert('find_by_task_id returns persisted row', is_array($found) && (int) ($found['task_id'] ?? 0) === $task_id);

    ac_assert('find_by_task_ids empty input returns []', TaskStateRepository::find_by_task_ids([]) === []);
    ac_assert('find_by_task_ids ignores invalid ids', TaskStateRepository::find_by_task_ids([0, -1, 'abc']) === []);

    $batch = TaskStateRepository::find_by_task_ids([$task_id, 99999999]);
    ac_assert('find_by_task_ids returns map keyed by task_id', isset($batch[$task_id]) && is_array($batch[$task_id]));
    ac_assert('find_by_task_ids omits missing ids', !isset($batch[99999999]));

    $task_after = TaskRepository::find_by_id($task_id);
    ac_assert('aa_tasks status unchanged', ($task_after['status'] ?? '') === ($task_before['status'] ?? ''));
    ac_assert(
        'aa_tasks completed_at unchanged',
        ($task_after['completed_at'] ?? null) === ($task_before['completed_at'] ?? null)
    );

    $system_task_id = (int) $wpdb->insert(
        $tasks_table,
        [
            'list_id' => $list_id,
            'title' => 'System completion task ' . $suffix,
            'source' => 'system',
            'source_category' => 'agenda_app',
            'origin_key' => 'configure_services',
            'managed_by' => 'developer',
            'completion_type' => 'system',
            'completion_fact_key' => 'has_active_service',
            'importance' => 0,
            'status' => 'pending',
            'position' => 2,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
    );
    $system_task_id = (int) $wpdb->insert_id;
    ac_assert('Seed system completion task', $system_task_id > 0);

    $first_system = TaskStateRepository::record_system_completion_evaluation($system_task_id, true, '2026-06-09 10:00:00');
    ac_assert('record_system_completion_evaluation creates row', is_array($first_system));
    ac_assert('first true sets completed_by_system=1', (int) ($first_system['completed_by_system'] ?? 0) === 1);
    ac_assert(
        'first true sets system_completed_at',
        ($first_system['system_completed_at'] ?? '') === '2026-06-09 10:00:00'
    );
    ac_assert(
        'first true sets last_system_evaluated_at',
        ($first_system['last_system_evaluated_at'] ?? '') === '2026-06-09 10:00:00'
    );

    $second_system = TaskStateRepository::record_system_completion_evaluation($system_task_id, false, '2026-06-09 11:00:00');
    ac_assert('false evaluation sets completed_by_system=0', (int) ($second_system['completed_by_system'] ?? 1) === 0);
    ac_assert(
        'false evaluation preserves sticky system_completed_at',
        ($second_system['system_completed_at'] ?? '') === '2026-06-09 10:00:00'
    );
    ac_assert(
        'false evaluation updates last_system_evaluated_at',
        ($second_system['last_system_evaluated_at'] ?? '') === '2026-06-09 11:00:00'
    );
    ac_assert(
        'system evaluation preserves defer_count',
        (int) ($second_system['defer_count'] ?? -1) === 0
    );
    ac_assert(
        'system evaluation preserves dismiss_count',
        (int) ($second_system['dismiss_count'] ?? -1) === 0
    );

    $system_task_after = TaskRepository::find_by_id($system_task_id);
    ac_assert('system evaluation leaves aa_tasks status pending', ($system_task_after['status'] ?? '') === 'pending');
    ac_assert('system evaluation leaves aa_tasks completed_at null', ($system_task_after['completed_at'] ?? null) === null);

    $legacy_defer = TaskStateRepository::apply_legacy_defer_migration($system_task_id, '2026-06-07 08:00:00');
    ac_assert('apply_legacy_defer_migration updates system task row', is_array($legacy_defer));
    ac_assert(
        'apply_legacy_defer_migration sets defer_count at least 1',
        (int) ($legacy_defer['defer_count'] ?? 0) >= 1
    );
    ac_assert(
        'apply_legacy_defer_migration leaves defer_until null',
        ($legacy_defer['defer_until'] ?? 'x') === null
    );
    ac_assert(
        'apply_legacy_defer_migration preserves completed_by_system',
        (int) ($legacy_defer['completed_by_system'] ?? -1) === 0
    );

    $legacy_defer_second = TaskStateRepository::apply_legacy_defer_migration($system_task_id, '2026-06-07 09:00:00');
    ac_assert(
        'apply_legacy_defer_migration idempotent defer_count',
        (int) ($legacy_defer_second['defer_count'] ?? 0) === (int) ($legacy_defer['defer_count'] ?? 0)
    );
    ac_assert(
        'apply_legacy_defer_migration preserves first last_deferred_at',
        ($legacy_defer_second['last_deferred_at'] ?? '') === '2026-06-07 08:00:00'
    );

    $wpdb->delete($state_table, ['task_id' => $task_id], ['%d']);
    $wpdb->delete($state_table, ['task_id' => $system_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $system_task_id], ['%d']);
    if (isset($second_task_id) && $second_task_id > 0) {
        $wpdb->delete($state_table, ['task_id' => $second_task_id], ['%d']);
        $wpdb->delete($tasks_table, ['id' => $second_task_id], ['%d']);
    }
    if (isset($third_task_id) && $third_task_id > 0) {
        $wpdb->delete($state_table, ['task_id' => $third_task_id], ['%d']);
        $wpdb->delete($tasks_table, ['id' => $third_task_id], ['%d']);
    }
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
