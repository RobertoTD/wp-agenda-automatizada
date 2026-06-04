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

    ac_assert(
        'install_pwa resolves to pwa.install handler action',
        is_array($install_action)
        && ($install_action['type'] ?? '') === 'handler'
        && ($install_action['handler'] ?? '') === 'pwa.install'
        && ($install_action['label'] ?? '') === 'Instalar'
        && !isset($install_action['url'])
    );

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

    run_policy_action_pipeline_assertions();
}

/**
 * Simula el enriquecimiento de action tal como enrich_item() del use case.
 *
 * @param array<string,mixed> $item
 * @return array<string,mixed>
 */
function enrich_action_fields_for_test(array $item): array {
    $navigation = is_array($item['navigation'] ?? null) ? $item['navigation'] : [];
    $raw_action = array_key_exists('action', $item) ? $item['action'] : null;
    $has_explicit_action = array_key_exists('action', $item);
    $action = GetLearningRecommendationsUseCase::resolve_action_payload(
        $has_explicit_action ? $raw_action : null,
        $navigation,
        $has_explicit_action
    );

    $item['action'] = $action;
    $item['action_url'] = is_array($action) && ($action['type'] ?? '') === 'navigate'
        ? (string) ($action['url'] ?? '')
        : null;
    $item['action_label'] = is_array($action)
        ? (string) ($action['label'] ?? '')
        : null;

    return $item;
}

function run_policy_action_pipeline_assertions(): void {
    $catalog = AA_Learning_Catalog::definitions();
    $policy = new AA_Learning_Visibility_Policy();
    $now = '2026-06-01 12:00:00';

    $install_def = $catalog['install_pwa'] ?? [];
    $evaluated_install = $policy->evaluate($install_def, null, [], $now);

    ac_assert(
        'policy evaluate preserves install_pwa action',
        array_key_exists('action', $evaluated_install)
        && is_array($evaluated_install['action'])
        && ($evaluated_install['action']['type'] ?? '') === 'handler'
        && ($evaluated_install['action']['handler'] ?? '') === 'pwa.install'
        && ($evaluated_install['action']['label'] ?? '') === 'Instalar'
    );

    $enriched_install = enrich_action_fields_for_test($evaluated_install);

    ac_assert(
        'install_pwa enriched payload exposes handler action',
        is_array($enriched_install['action'] ?? null)
        && ($enriched_install['action']['type'] ?? '') === 'handler'
        && ($enriched_install['action']['handler'] ?? '') === 'pwa.install'
        && ($enriched_install['action']['label'] ?? '') === 'Instalar'
    );
    ac_assert(
        'install_pwa enriched action_url stays null for handler',
        array_key_exists('action_url', $enriched_install) && $enriched_install['action_url'] === null
    );

    $legacy_def = $catalog['configure_services'] ?? [];
    $evaluated_legacy = $policy->evaluate($legacy_def, null, [], $now);

    ac_assert(
        'legacy recommendation policy evaluate has no explicit action key',
        !array_key_exists('action', $evaluated_legacy)
    );

    $enriched_legacy = enrich_action_fields_for_test($evaluated_legacy);

    ac_assert(
        'legacy recommendation enriched via navigation adapter',
        is_array($enriched_legacy['action'] ?? null)
        && ($enriched_legacy['action']['type'] ?? '') === 'navigate'
        && ($enriched_legacy['action']['label'] ?? '') === 'Ir'
        && !empty($enriched_legacy['action']['url'])
        && !empty($enriched_legacy['action_url'])
        && ($enriched_legacy['action_label'] ?? '') === 'Ir'
    );
}

// ─── Estáticos ───────────────────────────────────────────────

$files = [
    'use_case' => $plugin_root . '/includes/application/learning/GetLearningRecommendationsUseCase.php',
    'ajax' => $plugin_root . '/includes/http/ajax/LearningRecommendationsAjax.php',
    'service_js' => $plugin_root . '/assets/js/services/learningService.js',
    'handlers_js' => $plugin_root . '/includes/admin/ui/modules/learning/learning-action-handlers.js',
    'renderer_js' => $plugin_root . '/assets/js/ui/learningRecommendationRenderer.js',
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
$renderer_js = file_get_contents($files['renderer_js']);
$handlers_js = file_get_contents($files['handlers_js']);
ac_assert('learning renderer uses can_defer', strpos($renderer_js, 'can_defer') !== false);
ac_assert('learning renderer uses can_dismiss', strpos($renderer_js, 'can_dismiss') !== false);
ac_assert('learning renderer resolves primary action from item.action', strpos($renderer_js, 'resolvePrimaryAction') !== false);
ac_assert('learning renderer reads item.action type navigate', strpos($renderer_js, "action.type === 'navigate'") !== false);
ac_assert('learning renderer keeps legacy action_url fallback', strpos($renderer_js, 'item.action_url') !== false);
ac_assert('learning renderer checks handler action availability', strpos($renderer_js, 'registry.isAvailable(action, item)') !== false);
ac_assert('learning renderer renders primary handler button', strpos($renderer_js, 'data-learning-action="primary-handler"') !== false);
ac_assert('learning renderer exposes AALearningRecommendationRenderer', strpos($renderer_js, 'AALearningRecommendationRenderer') !== false);
ac_assert('learning renderer exposes pickFirstVisibleRecommendation', strpos($renderer_js, 'pickFirstVisibleRecommendation') !== false);
ac_assert('learning renderer supports conservative render options', strpos($renderer_js, 'showHandlerPrimary') !== false && strpos($renderer_js, 'normalizeRenderOptions') !== false);
ac_assert('learning-module uses shared renderer', strpos($module_js, 'AALearningRecommendationRenderer') !== false);
ac_assert('learning-module runs primary handler via registry', strpos($module_js, 'registry.run(action, item') !== false);
ac_assert('learning-module rerenders on handler availability changes', strpos($module_js, 'onAvailabilityChange') !== false);

ac_assert('learning-action-handlers exposes register', strpos($handlers_js, 'register: register') !== false);
ac_assert('learning-action-handlers exposes get', strpos($handlers_js, 'get: get') !== false);
ac_assert('learning-action-handlers exposes isAvailable', strpos($handlers_js, 'isAvailable: isAvailable') !== false);
ac_assert('learning-action-handlers exposes run', strpos($handlers_js, 'run: run') !== false);
ac_assert('learning-action-handlers exposes onAvailabilityChange', strpos($handlers_js, 'onAvailabilityChange: onAvailabilityChange') !== false);
ac_assert('learning-action-handlers exposes shouldShowRecommendation', strpos($handlers_js, 'shouldShowRecommendation: shouldShowRecommendation') !== false);
ac_assert('shouldShowRecommendation is conservative for non-handler actions', strpos($handlers_js, "action.type !== 'handler'") !== false);
ac_assert('shouldShowRecommendation delegates hide only via shouldHideRecommendation', strpos($handlers_js, 'shouldHideRecommendation') !== false);
ac_assert('isAvailable and shouldShowRecommendation are separate registry methods', strpos($handlers_js, 'isAvailable: isAvailable') !== false && strpos($handlers_js, 'shouldShowRecommendation: shouldShowRecommendation') !== false);
ac_assert('learning-action-handlers normalizes run to Promise', strpos($handlers_js, 'Promise.resolve(handler.run') !== false);
ac_assert('learning-action-handlers registers pwa.install handler', strpos($handlers_js, "register('pwa.install'") !== false);
ac_assert('pwa.install captures beforeinstallprompt', strpos($handlers_js, 'beforeinstallprompt') !== false);
ac_assert('pwa.install listens appinstalled', strpos($handlers_js, 'appinstalled') !== false);
ac_assert('pwa.install detects standalone display-mode', strpos($handlers_js, '(display-mode: standalone)') !== false);
ac_assert('pwa.install checks navigator.standalone for iOS', strpos($handlers_js, 'navigator.standalone') !== false);
ac_assert('pwa.install keeps deferredPrompt out of window', strpos($handlers_js, 'window.deferredPrompt') === false);
ac_assert('pwa.install does not complete recommendation yet', strpos($handlers_js, 'completeRecommendation') === false && strpos($handlers_js, 'ctx.complete') === false);
ac_assert('pwa.install defines shouldHideRecommendation for card visibility', strpos($handlers_js, 'shouldHideRecommendation: function') !== false);
ac_assert('pwa.install hides card in standalone via shouldHideRecommendation', strpos($handlers_js, 'shouldHideRecommendation: function') !== false && strpos($handlers_js, 'isStandalone()') !== false);
ac_assert('pwa.install hides card when installed via shouldHideRecommendation', strpos($handlers_js, 'shouldHideRecommendation: function') !== false && strpos($handlers_js, 'installed') !== false);
ac_assert('pwa.install isAvailable only gates install button', strpos($handlers_js, 'canInstallNow()') !== false && strpos($handlers_js, 'shouldHideRecommendation: function') !== false);
ac_assert('learning-module filters recommendations before renderList', strpos($module_js, 'filterRecommendationsForRender') !== false && strpos($module_js, 'renderList(primaryList, list1)') !== false);
ac_assert(
    'learning renderer uses shouldShowRecommendation for card filter not isAvailable',
    strpos($renderer_js, 'shouldShowRecommendation') !== false
    && strpos($renderer_js, 'filterRecommendationsForRender') !== false
);

$get_uc = file_get_contents($files['use_case']);
ac_assert('Get use case exposes can_defer flag', strpos($get_uc, 'can_defer') !== false);
ac_assert('Get use case exposes can_dismiss flag', strpos($get_uc, 'can_dismiss') !== false);
ac_assert('Get use case exposes is_dismissed flag', strpos($get_uc, 'is_dismissed') !== false);
ac_assert('Get use case uses is_dismiss_active flag', strpos($get_uc, 'is_dismiss_active') !== false);
ac_assert('Get use case exposes normalized action payload', strpos($get_uc, 'resolve_action_payload') !== false);

$index_php = file_get_contents($plugin_root . '/includes/admin/ui/modules/learning/index.php');
ac_assert('index.php exposes AA_LEARNING_DATA', strpos($index_php, 'AA_LEARNING_DATA') !== false);
ac_assert('index.php loads learningService.js', strpos($index_php, 'learningService.js') !== false);
ac_assert('index.php loads learning-action-handlers.js', strpos($index_php, 'learning-action-handlers.js') !== false);
ac_assert('index.php loads learningRecommendationRenderer.js', strpos($index_php, 'learningRecommendationRenderer.js') !== false);
ac_assert(
    'index.php loads renderer before learning module',
    strpos($index_php, 'learningRecommendationRenderer.js') !== false
    && strpos($index_php, 'learning-module.js') !== false
    && strpos($index_php, '$learning_renderer_js .') !== false
    && strpos($index_php, '$learning_js .') !== false
    && strpos($index_php, '$learning_renderer_js .') < strpos($index_php, '$learning_js .')
);
ac_assert(
    'index.php loads handlers before learning module',
    strpos($index_php, 'learning-action-handlers.js') !== false
    && strpos($index_php, 'learning-module.js') !== false
    && strpos($index_php, '$learning_handlers_js .') !== false
    && strpos($index_php, '$learning_js .') !== false
    && strpos($index_php, '$learning_handlers_js .') < strpos($index_php, '$learning_js .')
);
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
        ac_assert(
            'install_pwa exposes pwa.install handler action',
            is_array($install_pwa['action'] ?? null)
            && ($install_pwa['action']['type'] ?? '') === 'handler'
            && ($install_pwa['action']['handler'] ?? '') === 'pwa.install'
            && ($install_pwa['action']['label'] ?? '') === 'Instalar'
        );
        ac_assert('install_pwa action_url stays null for handler', array_key_exists('action_url', $install_pwa) && $install_pwa['action_url'] === null);
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
