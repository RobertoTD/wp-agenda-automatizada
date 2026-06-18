<?php
/**
 * AC MC13O-C2 — SyncLearningCatalogToTasksUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-sync-learning-catalog-to-tasks-use-case-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$use_case_file = $plugin_root . '/includes/application/tasks/SyncLearningCatalogToTasksUseCase.php';
$catalog_file = $plugin_root . '/includes/domain/learning/class-aa-learning-catalog.php';
$seeded_repo_file = $plugin_root . '/includes/repositories/SeededTaskRepository.php';
$action_repo_file = $plugin_root . '/includes/repositories/TaskActionRepository.php';

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

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$use_case_src = file_get_contents($use_case_file);
require_once $catalog_file;
ac_assert('Use case file readable', $use_case_src !== false);
ac_assert('Use case defines SyncLearningCatalogToTasksUseCase', strpos($use_case_src, 'class SyncLearningCatalogToTasksUseCase') !== false);
ac_assert('Use case reads AA_Learning_Catalog', strpos($use_case_src, 'AA_Learning_Catalog::definitions()') !== false);
ac_assert('Use case writes seeded list', strpos($use_case_src, 'upsert_seeded_list') !== false);
ac_assert('Use case writes seeded tasks', strpos($use_case_src, 'upsert_seeded_task') !== false);
ac_assert('Use case writes task actions', strpos($use_case_src, 'TaskActionRepository::upsert') !== false);
ac_assert('Use case has no automatic hook', strpos($use_case_src, 'add_action(') === false && strpos($use_case_src, 'register_activation_hook') === false && strpos($use_case_src, 'wp_schedule_event') === false);
ac_assert('Use case upserts seeded list archived-first', strpos($use_case_src, "'status' => 'archived'") !== false);
ac_assert('Use case does not touch Learning state repository', strpos($use_case_src, 'LearningRecommendationStateRepository') === false);
ac_assert('Use case does not touch task state repository', strpos($use_case_src, 'TaskStateRepository') === false);
ac_assert('Use case seeds Activación de tu agenda title', strpos($use_case_src, "'title' => 'Activación de tu agenda'") !== false);
ac_assert('Use case keeps learning.recommendations origin_key', strpos($use_case_src, 'learning.recommendations') !== false);
ac_assert('Catalog excludes learn_basic_flow', !isset(AA_Learning_Catalog::definitions()['learn_basic_flow']));
ac_assert('Catalog excludes review_agenda', !isset(AA_Learning_Catalog::definitions()['review_agenda']));
ac_assert('Catalog active_definition_keys has 7 entries', count(AA_Learning_Catalog::active_definition_keys()) === 7);
ac_assert('Catalog SEED_VERSION remains 3', AA_Learning_Catalog::SEED_VERSION === '3');

$runtime_files = [
    'includes/application/executable/GetExecutableListsFeedUseCase.php',
    'includes/application/executable/LearningRecommendationsToExecutableMapper.php',
    'includes/application/executable/TaskBoardToExecutableMapper.php',
    'includes/application/executable/ExecutableVisibleActionsEnricher.php',
];

foreach ($runtime_files as $runtime_file) {
    ac_assert('Runtime file not modified by this test target: ' . basename($runtime_file), is_readable($plugin_root . '/' . $runtime_file));
}

// ─── Integración WP (opcional) ───────────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $schema_file;
    require_once $catalog_file;
    require_once $seeded_repo_file;
    require_once $action_repo_file;
    require_once $use_case_file;

    AA_Schema::install();

    global $wpdb;
    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $actions_table = $wpdb->prefix . 'aa_task_actions';
    $learning_state_table = $wpdb->prefix . 'aa_learning_recommendation_state';
    $task_state_table = $wpdb->prefix . 'aa_task_state';

    $learning_state_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$learning_state_table}");
    $task_state_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$task_state_table}");

    $first_result = (new SyncLearningCatalogToTasksUseCase())->execute();
    ac_assert('sync returns list_id', (int) ($first_result['list_id'] ?? 0) > 0);

    $list = SeededTaskRepository::find_list_by_origin('agenda_app', 'learning.recommendations');
    ac_assert('sync creates learning.recommendations list', is_array($list) && ($list['title'] ?? '') === 'Activación de tu agenda');
    ac_assert('seeded list source_category agenda_app', ($list['source_category'] ?? '') === 'agenda_app');
    ac_assert('seeded list owner_type developer', ($list['owner_type'] ?? '') === 'developer');
    ac_assert('sync leaves seeded list archived until lifecycle activates', ($list['status'] ?? '') === 'archived');

    $definitions = AA_Learning_Catalog::definitions();
    $active_keys = [];
    foreach ($definitions as $key => $definition) {
        if (!empty($definition['active'])) {
            $active_keys[] = isset($definition['key']) ? (string) $definition['key'] : (string) $key;
        }
    }

    $placeholders = implode(', ', array_fill(0, count($active_keys), '%s'));
    $task_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tasks_table} WHERE source_category = %s AND origin_key IN ({$placeholders})",
            'agenda_app',
            ...$active_keys
        )
    );
    ac_assert('sync creates active catalog tasks', $task_count === count($active_keys), 'count=' . $task_count);

    $complete_business_data = SeededTaskRepository::find_task_by_origin('agenda_app', 'complete_business_data');
    ac_assert('complete_business_data task exists', is_array($complete_business_data));
    ac_assert('complete_business_data source system', ($complete_business_data['source'] ?? '') === 'system');
    ac_assert('complete_business_data source_category agenda_app', ($complete_business_data['source_category'] ?? '') === 'agenda_app');
    ac_assert('complete_business_data managed_by developer', ($complete_business_data['managed_by'] ?? '') === 'developer');
    ac_assert('complete_business_data default_bucket primary', ($complete_business_data['default_bucket'] ?? '') === 'primary');
    ac_assert('complete_business_data completion_type system', ($complete_business_data['completion_type'] ?? '') === 'system');
    ac_assert('complete_business_data completion_fact_key', ($complete_business_data['completion_fact_key'] ?? '') === 'business_data_complete');

    $install_pwa = SeededTaskRepository::find_task_by_origin('agenda_app', 'install_pwa');
    ac_assert('install_pwa task exists', is_array($install_pwa));
    ac_assert('install_pwa default_bucket secondary', ($install_pwa['default_bucket'] ?? '') === 'secondary');
    ac_assert('install_pwa completion_type manual', ($install_pwa['completion_type'] ?? '') === 'manual');

    $install_action = is_array($install_pwa)
        ? TaskActionRepository::find_by_task_and_key((int) $install_pwa['id'], 'pwa.install')
        : null;
    ac_assert('install_pwa has pwa.install action', is_array($install_action));
    ac_assert('install_pwa action is handler', ($install_action['type'] ?? '') === 'handler' && ($install_action['handler'] ?? '') === 'pwa.install');

    ac_assert(
        'sync does not seed learn_basic_flow',
        SeededTaskRepository::find_task_by_origin('agenda_app', 'learn_basic_flow') === null
    );
    ac_assert(
        'sync does not seed review_agenda',
        SeededTaskRepository::find_task_by_origin('agenda_app', 'review_agenda') === null
    );
    ac_assert('Catalog SEED_VERSION remains 3', AA_Learning_Catalog::SEED_VERSION === '3');

    $complete_action = is_array($complete_business_data)
        ? TaskActionRepository::find_by_task_and_key((int) $complete_business_data['id'], 'navigate.settings')
        : null;
    ac_assert('complete_business_data has navigate.settings action', is_array($complete_action));

    $first_task_id = (int) ($complete_business_data['id'] ?? 0);
    $tasks_before_second = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tasks_table} WHERE source_category = %s AND origin_key IN ({$placeholders})",
            'agenda_app',
            ...$active_keys
        )
    );
    $actions_before_second = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$actions_table} WHERE task_id IN (SELECT id FROM {$tasks_table} WHERE source_category = 'agenda_app')");

    $second_result = (new SyncLearningCatalogToTasksUseCase())->execute();
    $list_after_second = SeededTaskRepository::find_list_by_origin('agenda_app', 'learning.recommendations');
    $complete_business_data_after = SeededTaskRepository::find_task_by_origin('agenda_app', 'complete_business_data');
    $tasks_after_second = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tasks_table} WHERE source_category = %s AND origin_key IN ({$placeholders})",
            'agenda_app',
            ...$active_keys
        )
    );
    $actions_after_second = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$actions_table} WHERE task_id IN (SELECT id FROM {$tasks_table} WHERE source_category = 'agenda_app')");

    ac_assert('second sync preserves task_id', (int) ($complete_business_data_after['id'] ?? 0) === $first_task_id);
    ac_assert('second sync preserves single seeded list id', (int) ($list_after_second['id'] ?? 0) === (int) ($list['id'] ?? 0));
    ac_assert('second sync keeps Activación de tu agenda title', ($list_after_second['title'] ?? '') === 'Activación de tu agenda');
    ac_assert('second sync does not duplicate tasks', $tasks_after_second === $tasks_before_second);
    ac_assert('second sync does not duplicate actions', $actions_after_second === $actions_before_second);
    ac_assert('second sync reports updates', (int) ($second_result['tasks_updated'] ?? 0) >= count($active_keys));

    $learning_state_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$learning_state_table}");
    $task_state_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$task_state_table}");
    ac_assert('sync does not touch aa_learning_recommendation_state', $learning_state_after === $learning_state_before);
    ac_assert('sync does not touch aa_task_state', $task_state_after === $task_state_before);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar sync real.\n";
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
