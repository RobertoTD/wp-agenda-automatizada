<?php
/**
 * AC Ciclo C — GetLearningRecommendationsUseCase y wiring.
 *
 * Ejecutar: php tests/application/learning/test-get-learning-recommendations-use-case-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

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

function run_action_contract_assertions(): void {
    $legacy_action = GetLearningRecommendationsUseCase::resolve_action_payload(null, [
        'module' => 'assignments',
        'setup_focus' => 'services',
        'fragment' => 'aa-services-root',
    ], false);

    ac_assert(
        'legacy navigation resolves to navigate action',
        is_array($legacy_action)
        && ($legacy_action['type'] ?? '') === 'navigate'
        && ($legacy_action['label'] ?? '') === 'Ir'
        && !empty($legacy_action['url'])
        && strpos((string) $legacy_action['url'], 'module=assignments') !== false
    );

    $default_label_action = GetLearningRecommendationsUseCase::resolve_action_payload([
        'type' => 'navigate',
        'label' => '',
        'module' => 'calendar',
    ]);

    ac_assert(
        'navigate action empty label defaults to Ir',
        is_array($default_label_action)
        && ($default_label_action['type'] ?? '') === 'navigate'
        && ($default_label_action['label'] ?? '') === 'Ir'
        && !empty($default_label_action['url'])
    );

    $empty_navigation_action = GetLearningRecommendationsUseCase::resolve_action_payload(null, [
        'module' => null,
        'setup_focus' => null,
        'fragment' => null,
    ], false);

    ac_assert('legacy navigation without module resolves to null action', $empty_navigation_action === null);

    $catalog = AA_Learning_Catalog::definitions();
    $install_pwa = $catalog['install_pwa'] ?? [];
    $install_has_explicit_action = array_key_exists('action', $install_pwa);
    $install_action = GetLearningRecommendationsUseCase::resolve_action_payload(
        $install_has_explicit_action ? $install_pwa['action'] : null,
        is_array($install_pwa['navigation'] ?? null) ? $install_pwa['navigation'] : [],
        $install_has_explicit_action
    );

    ac_assert('install_pwa legacy navigation resolves to null action', $install_action === null);

    $invalid_handler_action = GetLearningRecommendationsUseCase::resolve_action_payload([
        'type' => 'handler',
        'label' => 'Instalar',
    ]);

    ac_assert('incomplete handler action resolves to null', $invalid_handler_action === null);

    $valid_handler_action = GetLearningRecommendationsUseCase::resolve_action_payload([
        'type' => 'handler',
        'label' => 'Instalar',
        'handler' => 'pwa.install',
    ]);

    ac_assert(
        'complete handler action resolves to handler payload',
        is_array($valid_handler_action)
        && ($valid_handler_action['type'] ?? '') === 'handler'
        && ($valid_handler_action['label'] ?? '') === 'Instalar'
        && ($valid_handler_action['handler'] ?? '') === 'pwa.install'
    );
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
ac_assert('Get use case uses is_dismiss_active flag', strpos($get_uc, 'is_dismiss_active') !== false);
ac_assert('Get use case exposes normalized action payload', strpos($get_uc, 'resolve_action_payload') !== false);

$index_php = file_get_contents($plugin_root . '/includes/admin/ui/modules/learning/index.php');
ac_assert('index.php exposes AA_LEARNING_DATA', strpos($index_php, 'AA_LEARNING_DATA') !== false);
ac_assert('index.php loads learningService.js', strpos($index_php, 'learningService.js') !== false);
ac_assert('index.php has primary/secondary containers', strpos($index_php, 'aa-learning-list-primary') !== false);

// ─── Contrato action sin WordPress (si no hay integración) ───

if ($wp_load === '' || !is_readable($wp_load)) {
    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_root . '/');
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path($file) {
            return rtrim(dirname($file), '/') . '/';
        }
    }

    if (!function_exists('sanitize_key')) {
        function sanitize_key($key) {
            $key = strtolower((string) $key);
            return preg_replace('/[^a-z0-9_\-]/', '', $key);
        }
    }

    if (!function_exists('admin_url')) {
        function admin_url($path = '') {
            return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
        }
    }

    if (!function_exists('add_query_arg')) {
        function add_query_arg($args, $url) {
            $separator = strpos($url, '?') === false ? '?' : '&';
            return $url . $separator . http_build_query($args);
        }
    }

    require_once $files['use_case'];
    run_action_contract_assertions();
}

// ─── Integración WordPress (opcional) ────────────────────────

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $files['use_case'];
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';

    AA_Schema::install();
    run_action_contract_assertions();

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
        ac_assert('list_1 item has action_label key', array_key_exists('action_label', $first));
        ac_assert(
            'list_1 item exposes navigate action payload',
            isset($first['action'])
            && is_array($first['action'])
            && ($first['action']['type'] ?? '') === 'navigate'
            && ($first['action']['label'] ?? '') === 'Ir'
            && !empty($first['action']['url'])
        );
        ac_assert('list_1 compatibility action_url non-empty', !empty($first['action_url']));
        ac_assert('list_1 compatibility action_label Ir', ($first['action_label'] ?? '') === 'Ir');
    }

    $install_items = array_values(array_filter(
        $result['all_visible'],
        static function (array $item): bool {
            return ($item['key'] ?? '') === 'install_pwa';
        }
    ));

    if (!empty($install_items)) {
        $install_pwa = $install_items[0];
        ac_assert('install_pwa action is null', array_key_exists('action', $install_pwa) && $install_pwa['action'] === null);
        ac_assert('install_pwa action_url is null', array_key_exists('action_url', $install_pwa) && $install_pwa['action_url'] === null);
        ac_assert('install_pwa action_label is null', array_key_exists('action_label', $install_pwa) && $install_pwa['action_label'] === null);
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
