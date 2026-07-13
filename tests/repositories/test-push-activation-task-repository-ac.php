<?php
/**
 * AC MC1 — PushActivationTaskRepository.
 *
 * Ejecutar: php tests/repositories/test-push-activation-task-repository-ac.php
 */

$plugin_root = dirname(__DIR__, 2);

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

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$repo_file = $plugin_root . '/includes/repositories/PushActivationTaskRepository.php';
$repo_src = file_get_contents($repo_file);

ac_assert('Repository file readable', $repo_src !== false);
ac_assert('Repository defines build_lock_name', strpos($repo_src, 'function build_lock_name') !== false);
ac_assert('Repository defines try_acquire_lock', strpos($repo_src, 'function try_acquire_lock') !== false);
ac_assert('Repository defines release_lock', strpos($repo_src, 'function release_lock') !== false);
ac_assert('Repository uses GET_LOCK', strpos($repo_src, 'GET_LOCK') !== false);
ac_assert('Repository uses RELEASE_LOCK', strpos($repo_src, 'RELEASE_LOCK') !== false);
ac_assert('Repository defines list_non_done_occurrences', strpos($repo_src, 'function list_non_done_occurrences') !== false);
ac_assert('Repository defines build_origin_key', strpos($repo_src, 'function build_origin_key') !== false);
ac_assert('Lock timeout defaults to 2 seconds', strpos($repo_src, 'LOCK_TIMEOUT_SECONDS = 2') !== false);

require_once $repo_file;

$device_key = 'a1b2c3d4e5f6789012345678abcdef01';
$occurrence_id = 'fedcba9876543210';

ac_assert('Valid device key accepted', PushActivationTaskRepository::is_valid_device_key($device_key));
ac_assert('Uppercase device key rejected', !PushActivationTaskRepository::is_valid_device_key(strtoupper($device_key)));
ac_assert('Short device key rejected', !PushActivationTaskRepository::is_valid_device_key('abc123'));
ac_assert('Non-hex device key rejected', !PushActivationTaskRepository::is_valid_device_key(str_repeat('g', 32)));

ac_assert('Valid occurrence id accepted', PushActivationTaskRepository::is_valid_occurrence_id($occurrence_id));
ac_assert('Short occurrence id rejected', !PushActivationTaskRepository::is_valid_occurrence_id('abc'));

$prefix = PushActivationTaskRepository::build_device_prefix($device_key);
ac_assert('Device prefix format', $prefix === 'enable_push:' . $device_key . ':');

$origin_key = PushActivationTaskRepository::build_origin_key($device_key, $occurrence_id);
ac_assert(
    'Origin key format',
    $origin_key === 'enable_push:' . $device_key . ':' . $occurrence_id
);
ac_assert('Origin key length within 100 chars', strlen((string) $origin_key) <= 100);

$lock_name = PushActivationTaskRepository::build_lock_name('3', $device_key);
ac_assert('Lock name prefix', strpos($lock_name, 'aa_ep_') === 0);
ac_assert('Lock name length <= 64', strlen($lock_name) <= 64);

$generated = PushActivationTaskRepository::generate_occurrence_id();
ac_assert('Generated occurrence id is 16 hex', PushActivationTaskRepository::is_valid_occurrence_id($generated));

// ─── Integración WordPress (opcional) ────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
    require_once $repo_file;

    AA_Schema::install();

    global $wpdb;

    $suffix = (string) time();
    $source = 'agenda_app';
    $list_origin = 'learning.recommendations';
    $test_device = 'deadbeef' . str_repeat('0', 24);
    $other_device = 'cafebabe' . str_repeat('0', 24);

    $list = SeededTaskRepository::upsert_seeded_list([
        'title' => 'Activación de tu agenda',
        'description' => 'Sugerencias para configurar y usar tu agenda.',
        'owner_type' => 'developer',
        'source_category' => $source,
        'origin_key' => $list_origin,
        'managed_by' => 'developer',
        'status' => 'active',
        'importance' => 0,
        'position' => 0,
    ]);

    ac_assert('Integration list seeded', is_array($list) && (int) ($list['id'] ?? 0) > 0);

    $list_id = is_array($list) ? (int) ($list['id'] ?? 0) : 0;

    $done_origin = PushActivationTaskRepository::build_origin_key($test_device, '1111111111111111');
    $pending_origin = PushActivationTaskRepository::build_origin_key($test_device, '2222222222222222');
    $missed_origin = PushActivationTaskRepository::build_origin_key($test_device, '3333333333333333');
    $other_origin = PushActivationTaskRepository::build_origin_key($other_device, '4444444444444444');

    foreach ([
        [$done_origin, 'done'],
        [$pending_origin, 'pending'],
        [$missed_origin, 'missed'],
        [$other_origin, 'pending'],
    ] as $pair) {
        SeededTaskRepository::upsert_seeded_task([
            'list_id' => $list_id,
            'title' => 'Push test',
            'notes' => 'Test',
            'status' => $pair[1],
            'source' => 'system',
            'source_category' => $source,
            'origin_key' => $pair[0],
            'managed_by' => 'developer',
            'importance' => 110,
            'default_bucket' => 'primary',
            'completion_type' => 'system',
            'completion_fact_key' => null,
            'due_at' => null,
            'completed_at' => $pair[1] === 'done' ? current_time('mysql') : null,
        ]);
    }

    $non_done = PushActivationTaskRepository::list_non_done_occurrences($source, $test_device);
    ac_assert('Integration non-done excludes done', count($non_done) === 2);
    ac_assert(
        'Integration non-done includes pending and missed',
        array_reduce($non_done, static function (bool $carry, array $task): bool {
            $status = strtolower((string) ($task['status'] ?? ''));

            return $carry || $status === 'pending' || $status === 'missed';
        }, false)
    );

    $lock_name = PushActivationTaskRepository::build_lock_name((string) get_current_blog_id(), $test_device);
    $acquired = PushActivationTaskRepository::try_acquire_lock($lock_name);
    ac_assert('Integration acquires lock', $acquired === true);

    if ($acquired) {
        PushActivationTaskRepository::release_lock($lock_name);
    }

    $reacquired = PushActivationTaskRepository::try_acquire_lock($lock_name);
    ac_assert('Integration reacquires lock after release', $reacquired === true);

    if ($reacquired) {
        PushActivationTaskRepository::release_lock($lock_name);
    }

    // Cleanup
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$tasks_table} WHERE source_category = %s AND origin_key LIKE %s",
            $source,
            $wpdb->esc_like('enable_push:') . '%'
        )
    );
} else {
    echo "\n--- Integración WordPress omitida (AA_WP_ROOT no configurado) ---\n";
}

echo "\n";
echo "Resultado: {$passed}/{$total} OK\n";

if ($failed !== []) {
    echo "Fallos:\n";
    foreach ($failed as $label) {
        echo " - {$label}\n";
    }
    exit(1);
}

exit(0);
