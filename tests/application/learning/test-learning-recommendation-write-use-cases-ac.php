<?php
/**
 * AC Ciclo D — Use Cases de escritura y ensure_suggested_at.
 *
 * Ejecutar: php tests/application/learning/test-learning-recommendation-write-use-cases-ac.php
 *
 * Parte estática: sin WordPress.
 * Integración: AA_WP_ROOT=/ruta/wordpress
 */

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

// ─── Estáticos (validador + complete manual guard sin BD) ───

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

require_once $plugin_root . '/includes/domain/learning/class-aa-learning-catalog.php';
require_once $plugin_root . '/includes/application/learning/LearningRecommendationCatalogValidator.php';
require_once $plugin_root . '/includes/application/learning/CompleteLearningRecommendationUseCase.php';

$unknown = LearningRecommendationCatalogValidator::resolve('no_such_key_xyz');
ac_assert('Unknown key rejected', $unknown['ok'] === false && $unknown['code'] === 'unknown_recommendation');

$manual_def = LearningRecommendationCatalogValidator::resolve('install_pwa');
ac_assert('Manual key resolves', $manual_def['ok'] === true);

$auto_def = LearningRecommendationCatalogValidator::resolve('configure_services');
ac_assert('Auto key resolves', $auto_def['ok'] === true);

$ajax_file = file_get_contents($plugin_root . '/includes/http/ajax/LearningRecommendationsAjax.php');
ac_assert('AJAX registers ignore endpoint', strpos($ajax_file, 'aa_ignore_learning_recommendation') !== false);
ac_assert('AJAX registers dismiss endpoint', strpos($ajax_file, 'aa_dismiss_learning_recommendation') !== false);
ac_assert('AJAX registers complete endpoint', strpos($ajax_file, 'aa_complete_learning_recommendation') !== false);
ac_assert('AJAX registers reactivate endpoint', strpos($ajax_file, 'aa_reactivate_learning_recommendation') !== false);

$service_js = file_get_contents($plugin_root . '/assets/js/services/learningService.js');
ac_assert('learningService ignoreRecommendation', strpos($service_js, 'ignoreRecommendation') !== false);
ac_assert('learningService dismissRecommendation', strpos($service_js, 'dismissRecommendation') !== false);
ac_assert('learningService completeRecommendation', strpos($service_js, 'completeRecommendation') !== false);

$renderer_js = file_get_contents($plugin_root . '/assets/js/ui/learningRecommendationRenderer.js');
$module_js = file_get_contents($plugin_root . '/includes/admin/ui/modules/learning/learning-module.js');
ac_assert('learning renderer defer action', strpos($renderer_js, 'data-learning-action="defer"') !== false);
ac_assert('learning renderer dismiss action', strpos($renderer_js, 'data-learning-action="dismiss"') !== false);
ac_assert('learning renderer shows Ignorar label', strpos($renderer_js, 'Ignorar') !== false);
ac_assert('learning renderer no reactivate button', strpos($renderer_js, 'Reactivar') === false);
ac_assert('learning-module delegates render to shared renderer', strpos($module_js, 'AALearningRecommendationRenderer') !== false);

$get_uc = file_get_contents($plugin_root . '/includes/application/learning/GetLearningRecommendationsUseCase.php');
ac_assert('Get use case exposes can_defer', strpos($get_uc, 'can_defer') !== false);
ac_assert('Get use case exposes can_dismiss', strpos($get_uc, 'can_dismiss') !== false);
ac_assert('Get use case checks is_dismiss_active', strpos($get_uc, 'is_dismiss_active') !== false);

$dismiss_uc = file_get_contents($plugin_root . '/includes/application/learning/DismissLearningRecommendationUseCase.php');
ac_assert('Dismiss use case file exists', is_readable($plugin_root . '/includes/application/learning/DismissLearningRecommendationUseCase.php'));
ac_assert('Dismiss use case uses mark_dismissed', strpos($dismiss_uc, 'mark_dismissed') !== false);

$ignore_uc = file_get_contents($plugin_root . '/includes/application/learning/IgnoreLearningRecommendationUseCase.php');
ac_assert('Ignore use case rejects not_in_primary_list', strpos($ignore_uc, 'not_in_primary_list') !== false);

// ─── Integración WordPress ───────────────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/LearningRecommendationStateRepository.php';
    require_once $plugin_root . '/includes/application/learning/IgnoreLearningRecommendationUseCase.php';
    require_once $plugin_root . '/includes/application/learning/DismissLearningRecommendationUseCase.php';
    require_once $plugin_root . '/includes/application/learning/ReactivateLearningRecommendationUseCase.php';
    require_once $plugin_root . '/includes/application/learning/GetLearningRecommendationsUseCase.php';

    AA_Schema::install();

    $list2_key = 'install_pwa';
    $list1_key = 'configure_services';
    $auto_key = 'configure_services';

    LearningRecommendationStateRepository::reactivate($list2_key);
    LearningRecommendationStateRepository::reactivate($list1_key);

    $defer_result = (new IgnoreLearningRecommendationUseCase())->execute($list1_key);
    ac_assert('Defer list 1 success', !empty($defer_result['success']));

    $deferred_row = LearningRecommendationStateRepository::find_by_key($list1_key);
    ac_assert('Defer sets is_ignored', ($deferred_row['is_ignored'] ?? 0) === 1);
    ac_assert('Defer sets ignored_at', !empty($deferred_row['ignored_at']));
    ac_assert('Defer does not set is_dismissed', ($deferred_row['is_dismissed'] ?? 0) === 0);

    $defer_list2 = (new IgnoreLearningRecommendationUseCase())->execute($list2_key);
    ac_assert(
        'Defer list 2 fails controlled',
        empty($defer_list2['success'])
        && ($defer_list2['error']['code'] ?? '') === 'not_in_primary_list'
    );

    LearningRecommendationStateRepository::reactivate($list2_key);

    $dismiss_result = (new DismissLearningRecommendationUseCase())->execute($list2_key);
    ac_assert('Dismiss list 2 success', !empty($dismiss_result['success']));

    $dismissed_row = LearningRecommendationStateRepository::find_by_key($list2_key);
    ac_assert('Dismiss sets is_dismissed', ($dismissed_row['is_dismissed'] ?? 0) === 1);
    ac_assert('Dismiss sets dismissed_at', !empty($dismissed_row['dismissed_at']));

    $dismiss_list1 = (new DismissLearningRecommendationUseCase())->execute($list1_key);
    ac_assert(
        'Dismiss list 1 fails controlled',
        empty($dismiss_list1['success'])
        && ($dismiss_list1['error']['code'] ?? '') === 'not_in_secondary_list'
    );

    $recommendations = (new GetLearningRecommendationsUseCase())->execute();
    $dismissed_visible = array_filter(
        $recommendations['all_visible'],
        static function (array $item) use ($list2_key): bool {
            return ($item['key'] ?? '') === $list2_key;
        }
    );
    ac_assert('Dismissed item not in all_visible', count($dismissed_visible) === 0);

    $list1_items = array_filter(
        $recommendations['list_1'],
        static function (array $item): bool {
            return !empty($item['can_defer']);
        }
    );
    ac_assert('Some list_1 items expose can_defer', count($list1_items) > 0);

    LearningRecommendationStateRepository::reactivate($list2_key);
    $recommendations_secondary = (new GetLearningRecommendationsUseCase())->execute();
    $list2_items = array_filter(
        $recommendations_secondary['list_2'],
        static function (array $item): bool {
            return !empty($item['can_dismiss']);
        }
    );
    ac_assert('Some list_2 items expose can_dismiss', count($list2_items) > 0);

    $old_dismissed_at = '2026-05-01 10:00:00';
    LearningRecommendationStateRepository::upsert($list2_key, [
        'is_dismissed' => 1,
        'dismissed_at' => $old_dismissed_at,
    ]);

    $expired_recommendations = (new GetLearningRecommendationsUseCase())->execute();
    $expired_visible_items = array_filter(
        $expired_recommendations['list_2'],
        static function (array $item) use ($list2_key): bool {
            return ($item['key'] ?? '') === $list2_key
                && !empty($item['can_dismiss'])
                && !empty($item['is_dismissed'])
                && empty($item['is_dismiss_active']);
        }
    );
    ac_assert('Expired dismissed item returns to list 2 with can_dismiss', count($expired_visible_items) === 1);

    $redismiss_result = (new DismissLearningRecommendationUseCase())->execute($list2_key);
    ac_assert('Expired dismissed item can be dismissed again', !empty($redismiss_result['success']));

    $redismissed_row = LearningRecommendationStateRepository::find_by_key($list2_key);
    ac_assert(
        'Dismiss again updates dismissed_at',
        ($redismissed_row['is_dismissed'] ?? 0) === 1
        && !empty($redismissed_row['dismissed_at'])
        && ($redismissed_row['dismissed_at'] ?? '') !== $old_dismissed_at
    );

    $complete_auto = (new CompleteLearningRecommendationUseCase())->execute($auto_key);
    ac_assert(
        'Complete auto fails controlled',
        empty($complete_auto['success'])
        && ($complete_auto['error']['code'] ?? '') === 'not_manual_recommendation'
    );

    LearningRecommendationStateRepository::reactivate($list2_key);
    (new DismissLearningRecommendationUseCase())->execute($list2_key);

    $complete_manual = (new CompleteLearningRecommendationUseCase())->execute($list2_key);
    ac_assert('Complete manual success', !empty($complete_manual['success']));

    $completed_row = LearningRecommendationStateRepository::find_by_key($list2_key);
    ac_assert('Complete manual sets is_completed', ($completed_row['is_completed'] ?? 0) === 1);

    $reactivate_result = (new ReactivateLearningRecommendationUseCase())->execute($list2_key);
    ac_assert('Reactivate success', !empty($reactivate_result['success']));

    $reactivated_row = LearningRecommendationStateRepository::find_by_key($list2_key);
    ac_assert(
        'Reactivate clears ignored/completed/dismissed',
        ($reactivated_row['is_ignored'] ?? 1) === 0
        && ($reactivated_row['is_completed'] ?? 1) === 0
        && ($reactivated_row['is_dismissed'] ?? 1) === 0
        && empty($reactivated_row['ignored_at'])
        && empty($reactivated_row['completed_at'])
        && empty($reactivated_row['dismissed_at'])
    );

    $seal_key = 'learn_basic_flow';
    LearningRecommendationStateRepository::reactivate($seal_key);

    $now = '2026-06-01 12:00:00';
    $first = LearningRecommendationStateRepository::ensure_suggested_at($seal_key, $now);
    ac_assert('ensure_suggested_at first write', ($first['last_suggested_at'] ?? '') === $now);

    $second = LearningRecommendationStateRepository::ensure_suggested_at($seal_key, '2026-07-01 12:00:00');
    ac_assert(
        'ensure_suggested_at does not overwrite',
        ($second['last_suggested_at'] ?? '') === $now
    );

    LearningRecommendationStateRepository::reactivate($list1_key);
    LearningRecommendationStateRepository::reactivate($list2_key);
    LearningRecommendationStateRepository::reactivate($seal_key);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT para probar escritura y ensure_suggested_at.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
