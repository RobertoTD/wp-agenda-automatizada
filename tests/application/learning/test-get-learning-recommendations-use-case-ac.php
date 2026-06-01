<?php
/**
 * AC Ciclo C — GetLearningRecommendationsUseCase y wiring.
 *
 * Ejecutar: php tests/application/learning/test-get-learning-recommendations-use-case-ac.php
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

// ─── Estáticos ───────────────────────────────────────────────

$files = [
    'use_case' => $plugin_root . '/includes/application/learning/GetLearningRecommendationsUseCase.php',
    'ajax' => $plugin_root . '/includes/http/ajax/LearningRecommendationsAjax.php',
    'service_js' => $plugin_root . '/assets/js/services/learningService.js',
    'module_js' => $plugin_root . '/includes/admin/ui/modules/learning/learning-module.js',
];

foreach ($files as $label => $path) {
    ac_assert('File exists: ' . $label, is_readable($path), $path);
}

$bootstrap = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert(
    'LearningRecommendationsAjax registered in bootstrap',
    strpos($bootstrap, 'LearningRecommendationsAjax::register') !== false
);

$module_js = file_get_contents($files['module_js']);
ac_assert('learning-module uses can_defer', strpos($module_js, 'can_defer') !== false);
ac_assert('learning-module uses can_dismiss', strpos($module_js, 'can_dismiss') !== false);

$get_uc = file_get_contents($files['use_case']);
ac_assert('Get use case exposes can_defer flag', strpos($get_uc, 'can_defer') !== false);
ac_assert('Get use case exposes can_dismiss flag', strpos($get_uc, 'can_dismiss') !== false);
ac_assert('Get use case exposes is_dismissed flag', strpos($get_uc, 'is_dismissed') !== false);

$index_php = file_get_contents($plugin_root . '/includes/admin/ui/modules/learning/index.php');
ac_assert('index.php exposes AA_LEARNING_DATA', strpos($index_php, 'AA_LEARNING_DATA') !== false);
ac_assert('index.php loads learningService.js', strpos($index_php, 'learningService.js') !== false);
ac_assert('index.php has primary/secondary containers', strpos($index_php, 'aa-learning-list-primary') !== false);

// ─── Integración WordPress (opcional) ────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $files['use_case'];
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';

    AA_Schema::install();

    $result = (new GetLearningRecommendationsUseCase())->execute();

    ac_assert('execute returns list_1', isset($result['list_1']) && is_array($result['list_1']));
    ac_assert('execute returns list_2', isset($result['list_2']) && is_array($result['list_2']));
    ac_assert('execute returns all_visible', isset($result['all_visible']) && is_array($result['all_visible']));

    $visible_count = count($result['all_visible']);
    ac_assert('has visible recommendations on empty state', $visible_count > 0, 'count=' . $visible_count);

    $list_sum = count($result['list_1']) + count($result['list_2']);
    ac_assert('list_1 + list_2 equals all_visible count', $list_sum === $visible_count, "sum={$list_sum}");

    $google_url = GetLearningRecommendationsUseCase::resolve_navigation_url([
        'module' => 'settings',
        'setup_focus' => 'google_calendar',
        'fragment' => 'aa-google-calendar-root',
    ]);
    ac_assert(
        'Google Calendar URL shape',
        is_string($google_url)
        && strpos($google_url, 'module=settings') !== false
        && strpos($google_url, 'setup_focus=google_calendar') !== false
        && strpos($google_url, '#aa-google-calendar-root') !== false
    );

    if (!empty($result['list_1'])) {
        $first = $result['list_1'][0];
        ac_assert('list_1 item has title', isset($first['title']) && $first['title'] !== '');
        ac_assert('list_1 item has effective_list', (int) ($first['effective_list'] ?? 0) === 1);
        ac_assert('list_1 item has action_url key', array_key_exists('action_url', $first));
    }

    // Simular handler AJAX (sin HTTP).
    require_once $files['ajax'];
    ac_assert('AJAX class register method exists', method_exists('LearningRecommendationsAjax', 'register'));
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT para probar Use Case y URLs.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
