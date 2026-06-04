<?php
/**
 * AC Ciclo A — Schema + LearningRecommendationStateRepository.
 *
 * Ejecutar: php tests/repositories/test-learning-recommendation-state-repository-ac.php
 *
 * Parte estática: no requiere WordPress.
 * Parte BD (opcional): define AA_WP_ROOT con ruta al wp-load.php y ejecuta migración + upsert.
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$repo_file = $plugin_root . '/includes/repositories/LearningRecommendationStateRepository.php';

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

$schema_src = file_get_contents($schema_file);
ac_assert('Schema file readable', $schema_src !== false);
ac_assert('DB_VERSION is 4', strpos($schema_src, "DB_VERSION = '4'") !== false);
ac_assert(
    'CREATE TABLE aa_learning_recommendation_state',
    strpos($schema_src, 'aa_learning_recommendation_state') !== false
        && strpos($schema_src, 'recommendation_key varchar(100)') !== false
        && strpos($schema_src, 'is_dismissed tinyint(1)') !== false
        && strpos($schema_src, 'dismissed_at datetime') !== false
        && strpos($schema_src, 'UNIQUE KEY recommendation_key') !== false
);

$repo_src = file_get_contents($repo_file);
ac_assert('Repository file readable', $repo_src !== false);
ac_assert('Repository defines get_all', strpos($repo_src, 'function get_all') !== false);
ac_assert('Repository defines find_by_key', strpos($repo_src, 'function find_by_key') !== false);
ac_assert('Repository defines upsert', strpos($repo_src, 'function upsert') !== false);
ac_assert('Repository defines mark_dismissed', strpos($repo_src, 'function mark_dismissed') !== false);
ac_assert('Repository maps dismissed_at', strpos($repo_src, "'dismissed_at' => \$row->dismissed_at") !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $repo_file;

ac_assert('Repository class exists', class_exists('LearningRecommendationStateRepository'));
ac_assert(
    'Repository methods are public static',
    method_exists('LearningRecommendationStateRepository', 'get_all')
    && method_exists('LearningRecommendationStateRepository', 'find_by_key')
    && method_exists('LearningRecommendationStateRepository', 'upsert')
);

// ─── Integración WP (opcional) ─────────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $schema_file;

    AA_Schema::install();

    global $wpdb;
    $table = $wpdb->prefix . 'aa_learning_recommendation_state';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    ac_assert('Table exists after AA_Schema::install', $exists === $table, $table);

    $test_key = 'ac_test_recommendation_' . time();
    $first = LearningRecommendationStateRepository::upsert($test_key, [
        'is_ignored' => 1,
        'ignored_at' => '2026-06-01 10:00:00',
    ]);
    ac_assert('upsert insert returns row', is_array($first) && ($first['recommendation_key'] ?? '') === $test_key);
    ac_assert('upsert insert is_ignored', ($first['is_ignored'] ?? 0) === 1);

    $second = LearningRecommendationStateRepository::upsert($test_key, [
        'is_completed' => 1,
        'completed_at' => '2026-06-01 11:00:00',
        'is_ignored' => 0,
        'is_dismissed' => 1,
        'dismissed_at' => '2026-06-01 12:00:00',
    ]);
    ac_assert('upsert update same key', is_array($second) && ($second['is_completed'] ?? 0) === 1);
    ac_assert('upsert update clears is_ignored when set', ($second['is_ignored'] ?? 1) === 0);
    ac_assert('upsert update maps dismissed_at', ($second['dismissed_at'] ?? '') === '2026-06-01 12:00:00');

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE recommendation_key = %s",
            $test_key
        )
    );
    ac_assert('single row per recommendation_key', $count === 1, 'count=' . $count);

    $all = LearningRecommendationStateRepository::get_all();
    ac_assert('get_all indexes by key', isset($all[$test_key]));

    $found = LearningRecommendationStateRepository::find_by_key($test_key);
    ac_assert('find_by_key matches upsert', is_array($found) && ($found['is_completed'] ?? 0) === 1);

    $wpdb->delete($table, ['recommendation_key' => $test_key], ['%s']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar migración y upsert.\n";
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
