<?php
/**
 * AC MC13O-F2 — AA_Learning_State_Migration_Lifecycle.
 *
 * Ejecutar: php tests/infrastructure/wp/test-learning-state-migration-lifecycle-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$root = dirname(__DIR__, 3);
$lifecycle_file = $root . '/includes/infrastructure/wp/LearningStateMigrationLifecycle.php';
$bootstrap_file = $root . '/wp-agenda-automatizada.php';

$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_test_transients'] = [];
$GLOBALS['aa_test_doing_ajax'] = false;
$GLOBALS['aa_test_doing_cron'] = false;
$GLOBALS['aa_test_migration_calls'] = 0;

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }

        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        $GLOBALS['aa_test_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($key) {
        unset($GLOBALS['aa_test_options'][$key]);

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        if (!array_key_exists($key, $GLOBALS['aa_test_transients'])) {
            return false;
        }

        $entry = $GLOBALS['aa_test_transients'][$key];

        if (($entry['expires_at'] ?? 0) < time()) {
            unset($GLOBALS['aa_test_transients'][$key]);

            return false;
        }

        return $entry['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration) {
        $GLOBALS['aa_test_transients'][$key] = [
            'value' => $value,
            'expires_at' => time() + (int) $expiration,
        ];

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['aa_test_transients'][$key]);

        return true;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool {
        return (bool) $GLOBALS['aa_test_doing_ajax'];
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-06-09 12:00:00' : '2026-06-09 12:00:00';
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10) {
        $GLOBALS['aa_test_registered_actions'][$hook][] = [
            'callback' => $callback,
            'priority' => (int) $priority,
        ];
    }
}

require_once $root . '/includes/domain/learning/class-aa-learning-catalog.php';
require_once $root . '/includes/repositories/SeededTaskRepository.php';
require_once $root . '/includes/application/tasks/SyncLearningCatalogToTasksUseCase.php';
require_once $root . '/includes/infrastructure/wp/LearningCatalogSeedLifecycle.php';
require_once $lifecycle_file;

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

function migration_reset_test_state(): void {
    $GLOBALS['aa_test_options'] = [
        'aa_db_version' => '7',
        AA_Learning_Catalog_Seed_Lifecycle::OPTION_SEED_VERSION => AA_Learning_Catalog::SEED_VERSION,
    ];
    $GLOBALS['aa_test_transients'] = [];
    $GLOBALS['aa_test_doing_ajax'] = false;
    $GLOBALS['aa_test_doing_cron'] = false;
    $GLOBALS['aa_test_migration_calls'] = 0;
    AA_Learning_State_Migration_Lifecycle::set_migration_executor_for_tests(null);
}

function migration_set_success_override(): void {
    AA_Learning_State_Migration_Lifecycle::set_migration_executor_for_tests(static function (): array {
        $GLOBALS['aa_test_migration_calls']++;

        return [
            'success' => true,
            'data' => [
                'completed_migrated' => 1,
                'defer_migrated' => 0,
                'dismissed_skipped' => 0,
                'skipped_no_task' => 0,
                'skipped_ambiguous' => 0,
                'skipped_no_signal' => 2,
                'errors' => 0,
            ],
        ];
    });
}

function migration_success_result(): array {
    return [
        'success' => true,
        'data' => [
            'completed_migrated' => 0,
            'defer_migrated' => 0,
            'dismissed_skipped' => 0,
            'skipped_no_task' => 0,
            'skipped_ambiguous' => 0,
            'skipped_no_signal' => 0,
            'errors' => 0,
        ],
    ];
}

// ─── Estáticos ───────────────────────────────────────────────

$lifecycle_src = file_get_contents($lifecycle_file);
ac_assert('Lifecycle file readable', $lifecycle_src !== false);
ac_assert(
    'Lifecycle defines AA_Learning_State_Migration_Lifecycle',
    strpos($lifecycle_src, 'class AA_Learning_State_Migration_Lifecycle') !== false
);
ac_assert('Lifecycle registers admin_init', strpos($lifecycle_src, "add_action('admin_init'") !== false);
ac_assert('Lifecycle uses admin_init priority 21', strpos($lifecycle_src, "maybe_migrate'], 21") !== false);
ac_assert('Lifecycle guards DOING_AJAX', strpos($lifecycle_src, 'wp_doing_ajax') !== false);
ac_assert('Lifecycle guards DOING_CRON', strpos($lifecycle_src, 'DOING_CRON') !== false);
ac_assert('Lifecycle checks aa_db_version', strpos($lifecycle_src, 'aa_db_version') !== false);
ac_assert(
    'Lifecycle checks catalog seed version',
    strpos($lifecycle_src, 'OPTION_SEED_VERSION') !== false
);
ac_assert(
    'Lifecycle checks migration version option',
    strpos($lifecycle_src, 'OPTION_MIGRATION_VERSION') !== false
);
ac_assert('Lifecycle defines MIGRATION_VERSION', strpos($lifecycle_src, "MIGRATION_VERSION = '1'") !== false);
ac_assert('Lifecycle uses transient lock', strpos($lifecycle_src, 'LOCK_KEY') !== false);
ac_assert('Lifecycle stores last_error option', strpos($lifecycle_src, 'OPTION_LAST_ERROR') !== false);
ac_assert('Lifecycle stores last_run_at option', strpos($lifecycle_src, 'OPTION_LAST_RUN_AT') !== false);
ac_assert(
    'Lifecycle invokes MigrateLearningRecommendationStateToTaskStateUseCase',
    strpos($lifecycle_src, 'MigrateLearningRecommendationStateToTaskStateUseCase') !== false
);
ac_assert('Lifecycle does not use wp_die', strpos($lifecycle_src, 'wp_die') === false);
ac_assert('Lifecycle does not use admin notices', strpos($lifecycle_src, 'add_action(\'admin_notices\'') === false);

$bootstrap_src = file_get_contents($bootstrap_file);
ac_assert('Plugin bootstrap readable', $bootstrap_src !== false);
ac_assert(
    'Plugin bootstrap requires LearningStateMigrationLifecycle',
    strpos($bootstrap_src, 'LearningStateMigrationLifecycle.php') !== false
);
ac_assert(
    'Plugin bootstrap registers state migration lifecycle',
    strpos($bootstrap_src, 'AA_Learning_State_Migration_Lifecycle::register') !== false
);

// ─── Comportamiento con mocks ──────────────────────────────────

migration_reset_test_state();
migration_set_success_override();
AA_Learning_State_Migration_Lifecycle::maybe_migrate();
ac_assert(
    'Migration runs when migration version option is missing',
    (int) $GLOBALS['aa_test_migration_calls'] === 1
    && ($GLOBALS['aa_test_options'][AA_Learning_State_Migration_Lifecycle::OPTION_MIGRATION_VERSION] ?? '') === AA_Learning_State_Migration_Lifecycle::MIGRATION_VERSION
    && ($GLOBALS['aa_test_options'][AA_Learning_State_Migration_Lifecycle::OPTION_LAST_RUN_AT] ?? '') === '2026-06-09 12:00:00'
    && !array_key_exists(AA_Learning_State_Migration_Lifecycle::OPTION_LAST_ERROR, $GLOBALS['aa_test_options'])
);

migration_reset_test_state();
$GLOBALS['aa_test_options'][AA_Learning_State_Migration_Lifecycle::OPTION_MIGRATION_VERSION] = AA_Learning_State_Migration_Lifecycle::MIGRATION_VERSION;
migration_set_success_override();
AA_Learning_State_Migration_Lifecycle::maybe_migrate();
ac_assert('Migration skips when migration version is current', (int) $GLOBALS['aa_test_migration_calls'] === 0);

migration_reset_test_state();
$GLOBALS['aa_test_options'][AA_Learning_Catalog_Seed_Lifecycle::OPTION_SEED_VERSION] = '0';
migration_set_success_override();
AA_Learning_State_Migration_Lifecycle::maybe_migrate();
ac_assert('Migration skips when catalog seed version is behind', (int) $GLOBALS['aa_test_migration_calls'] === 0);

migration_reset_test_state();
$GLOBALS['aa_test_doing_ajax'] = true;
migration_set_success_override();
AA_Learning_State_Migration_Lifecycle::maybe_migrate();
ac_assert('Migration skips during DOING_AJAX', (int) $GLOBALS['aa_test_migration_calls'] === 0);

migration_reset_test_state();
$GLOBALS['aa_test_options']['aa_db_version'] = '6';
migration_set_success_override();
AA_Learning_State_Migration_Lifecycle::maybe_migrate();
ac_assert('Migration skips when aa_db_version is below 7', (int) $GLOBALS['aa_test_migration_calls'] === 0);

migration_reset_test_state();
AA_Learning_State_Migration_Lifecycle::set_migration_executor_for_tests(static function (): array {
    $GLOBALS['aa_test_migration_calls']++;

    return [
        'success' => false,
        'error' => [
            'code' => 'learning_state_migration_failed',
            'message' => 'migration failed controlled',
        ],
    ];
});
AA_Learning_State_Migration_Lifecycle::maybe_migrate();
ac_assert(
    'Controlled failure stores last_error and does not bump version',
    ($GLOBALS['aa_test_options'][AA_Learning_State_Migration_Lifecycle::OPTION_LAST_ERROR] ?? '') === 'migration failed controlled'
    && !array_key_exists(AA_Learning_State_Migration_Lifecycle::OPTION_MIGRATION_VERSION, $GLOBALS['aa_test_options'])
);

migration_reset_test_state();
AA_Learning_State_Migration_Lifecycle::set_migration_executor_for_tests(static function (): array {
    $GLOBALS['aa_test_migration_calls']++;

    throw new RuntimeException('migration exploded');
});
AA_Learning_State_Migration_Lifecycle::maybe_migrate();
ac_assert(
    'Throwable stores last_error and does not bump version',
    ($GLOBALS['aa_test_options'][AA_Learning_State_Migration_Lifecycle::OPTION_LAST_ERROR] ?? '') === 'migration exploded'
    && !array_key_exists(AA_Learning_State_Migration_Lifecycle::OPTION_MIGRATION_VERSION, $GLOBALS['aa_test_options'])
);

migration_reset_test_state();
set_transient(AA_Learning_State_Migration_Lifecycle::LOCK_KEY, '1', 60);
AA_Learning_State_Migration_Lifecycle::set_migration_executor_for_tests(static function (): array {
    $GLOBALS['aa_test_migration_calls']++;

    return migration_success_result();
});
AA_Learning_State_Migration_Lifecycle::maybe_migrate();
ac_assert('Transient lock prevents second migration execution', (int) $GLOBALS['aa_test_migration_calls'] === 0);

migration_reset_test_state();
$GLOBALS['aa_test_registered_actions'] = [];
AA_Learning_State_Migration_Lifecycle::register('/tmp/wp-agenda-automatizada.php');
$admin_init = $GLOBALS['aa_test_registered_actions']['admin_init'][0] ?? null;
ac_assert(
    'register wires maybe_migrate on admin_init priority 21',
    is_array($admin_init)
    && ($admin_init['priority'] ?? 0) === 21
    && is_array($admin_init['callback'] ?? null)
    && ($admin_init['callback'][1] ?? '') === 'maybe_migrate'
);

if (!defined('DOING_CRON')) {
    migration_reset_test_state();
    define('DOING_CRON', true);
    migration_set_success_override();
    AA_Learning_State_Migration_Lifecycle::maybe_migrate();
    ac_assert('Migration skips during DOING_CRON', (int) $GLOBALS['aa_test_migration_calls'] === 0);
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
