<?php
/**
 * AC MC13O consolidation — runtime enqueue Listas/Tareas (index.php).
 *
 * Ejecutar: php tests/admin/ui/test-learning-index-runtime-ac.php
 *
 * Verifica que la pantalla Listas/Tareas usa feed unified oficial y no encola
 * renderer legacy de Learning en paralelo.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$index_php = file_get_contents($plugin_root . '/includes/admin/ui/modules/learning/index.php');

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

ac_assert('index.php Listas readable', $index_php !== false);

ac_assert(
    'index.php configures visibleFeed unified',
    is_string($index_php)
    && strpos($index_php, 'visibleFeed: \'unified\'') !== false
);
ac_assert(
    'index.php configures aa_get_executable_lists_feed action',
    is_string($index_php)
    && strpos($index_php, 'aa_get_executable_lists_feed') !== false
    && strpos($index_php, 'AA_EXECUTABLE_LISTS_DATA') !== false
);

ac_assert(
    'index.php does not enqueue learning-module.js',
    is_string($index_php) && strpos($index_php, 'learning-module.js') === false
);
ac_assert(
    'index.php does not enqueue learningRecommendationRenderer.js',
    is_string($index_php) && strpos($index_php, 'learningRecommendationRenderer.js') === false
);
ac_assert(
    'index.php has no legacy learning recommendations DOM',
    is_string($index_php)
    && strpos($index_php, 'aa-learning-recommendations') === false
);

ac_assert(
    'index.php enqueues executableListsService.js',
    is_string($index_php) && strpos($index_php, 'executableListsService.js') !== false
);
ac_assert(
    'index.php enqueues executableListRenderer.js',
    is_string($index_php) && strpos($index_php, 'executableListRenderer.js') !== false
);
ac_assert(
    'index.php enqueues executable-lists-module.js',
    is_string($index_php) && strpos($index_php, 'executable-lists-module.js') !== false
);
ac_assert(
    'index.php does not enqueue executable-lists-shadow-module.js (MC13J-2E)',
    is_string($index_php) && strpos($index_php, 'executable-lists-shadow-module.js') === false
);
ac_assert(
    'index.php shows unified feed section without hidden',
    is_string($index_php)
    && preg_match('/id="aa-executable-lists-active"\s+class="space-y-4"/', $index_php) === 1
);
ac_assert(
    'index.php shows unified loading without hidden',
    is_string($index_php)
    && preg_match('/id="aa-executable-lists-active-loading"\s+class="text-sm text-gray-500"/', $index_php) === 1
);
ac_assert(
    'index.php hides legacy board root by default',
    is_string($index_php)
    && preg_match('/id="aa-tasks-board-root"\s+class="hidden"/', $index_php) === 1
);
ac_assert(
    'index.php enqueues executable-actions-coordinator.js',
    is_string($index_php) && strpos($index_php, 'executable-actions-coordinator.js') !== false
);

ac_assert(
    'index.php keeps learningService.js for coordinator mutations',
    is_string($index_php) && strpos($index_php, 'learningService.js') !== false
);

ac_assert(
    'index.php has no module page header Listas / Tareas',
    is_string($index_php)
    && strpos($index_php, 'Listas / Tareas') === false
    && strpos($index_php, 'Tareas organizadas inteligentemente') === false
);

ac_assert(
    'index.php lists section header is Organizador · Listas de tareas',
    is_string($index_php) && strpos($index_php, 'Organizador · Listas de tareas') !== false
);
ac_assert(
    'index.php does not render lists section subtitle',
    is_string($index_php) && strpos($index_php, 'Todas las listas de tareas.') === false
);
ac_assert(
    'index.php no longer renders executive/lists divider (Cycle B)',
    is_string($index_php) && strpos($index_php, 'aa-executive-lists-divider') === false
);
ac_assert(
    'index.php keeps aa-executive-proposal and aa-lists-section ids',
    is_string($index_php)
    && strpos($index_php, 'id="aa-executive-proposal"') !== false
    && strpos($index_php, 'id="aa-lists-section"') !== false
);
ac_assert(
    'index.php exposes persistent lists header and collapsible body',
    is_string($index_php)
    && strpos($index_php, 'id="aa-lists-header"') !== false
    && strpos($index_php, 'id="aa-lists-body"') !== false
    && strpos($index_php, 'Organizador · Listas de tareas') !== false
);
ac_assert(
    'index.php lists section no longer starts fully muted',
    is_string($index_php)
    && strpos($index_php, 'id="aa-lists-section"') !== false
    && strpos($index_php, 'id="aa-lists-section" class="pb-24 is-muted"') === false
);
ac_assert(
    'index.php enqueues section-toggles-module.js',
    is_string($index_php) && strpos($index_php, 'section-toggles-module.js') !== false
);
ac_assert(
    'index.php no longer references executive-lists-focus-module.js',
    is_string($index_php) && strpos($index_php, 'executive-lists-focus-module.js') === false
);

ac_assert(
    'index.php configures AA_EXECUTIVE_PROPOSAL_DATA',
    is_string($index_php)
    && strpos($index_php, 'AA_EXECUTIVE_PROPOSAL_DATA') !== false
    && strpos($index_php, 'aa_get_executive_proposal') !== false
    && strpos($index_php, 'aa_executive_proposal_nonce') !== false
);
ac_assert(
    'index.php enqueues executiveProposalService.js',
    is_string($index_php) && strpos($index_php, 'executiveProposalService.js') !== false
);
ac_assert(
    'index.php enqueues executiveProposalRenderer.js',
    is_string($index_php) && strpos($index_php, 'executiveProposalRenderer.js') !== false
);
ac_assert(
    'index.php enqueues executive-proposal-module.js',
    is_string($index_php) && strpos($index_php, 'executive-proposal-module.js') !== false
);
ac_assert(
    'index.php keeps executive proposal containers',
    is_string($index_php)
    && strpos($index_php, 'id="aa-executive-proposal"') !== false
    && strpos($index_php, 'id="aa-executive-list"') !== false
    && strpos($index_php, 'id="aa-executive-empty"') !== false
);
ac_assert(
    'index.php exposes AA_PUSH_CONFIG for Agenda App only',
    is_string($index_php)
    && strpos($index_php, 'window.AA_PUSH_CONFIG') !== false
    && strpos($index_php, 'PushSubscriptionAjax::ACTION_REGISTER') !== false
    && strpos($index_php, 'PushSubscriptionAjax::ACTION_CONFIG') !== false
    && strpos($index_php, 'PushSubscriptionAjax::NONCE_ACTION') !== false
);
ac_assert(
    'index.php enqueues push activation reconcile service',
    is_string($index_php) && strpos($index_php, 'pushActivationReconcileService.js') !== false
);
ac_assert(
    'index.php does not enqueue push device key service',
    is_string($index_php) && strpos($index_php, 'pushDeviceKeyService.js') === false
);
ac_assert(
    'index.php enqueues pwaPushActivationService after AA_PUSH_CONFIG',
    is_string($index_php)
    && strpos($index_php, 'pwaPushActivationService.js') !== false
);
$handlers_script_enqueue = is_string($index_php)
    ? strpos($index_php, 'esc_url($learning_handlers_js')
    : false;
$reconcile_script_enqueue = is_string($index_php)
    ? strpos($index_php, 'esc_url($push_activation_reconcile_service_js')
    : false;
$pwa_script_enqueue = is_string($index_php)
    ? strpos($index_php, 'esc_url($pwa_push_activation_service_js')
    : false;
$config_pos = is_string($index_php) ? strpos($index_php, 'window.AA_PUSH_CONFIG') : false;
ac_assert(
    'index.php loads AA_PUSH_CONFIG before pwaPushActivationService script',
    $config_pos !== false
    && $pwa_script_enqueue !== false
    && $config_pos < $pwa_script_enqueue
);
ac_assert(
    'index.php loads push services before learning-action-handlers scripts',
    $reconcile_script_enqueue !== false
    && $handlers_script_enqueue !== false
    && $reconcile_script_enqueue < $handlers_script_enqueue
);
ac_assert(
    'index.php MC6 executive status header ids',
    is_string($index_php)
    && strpos($index_php, 'id="aa-executive-status"') !== false
    && strpos($index_php, 'id="aa-executive-header-actions"') !== false
);
ac_assert(
    'index.php MC6 removes static executive subtitle',
    is_string($index_php) && strpos($index_php, 'Acciones recomendadas ahora') === false
);
ac_assert(
    'index.php MC6 removes legacy executive focus container',
    is_string($index_php) && strpos($index_php, 'id="aa-executive-focus"') === false
);

// --- Cycle B assertions ---
$lists_pos = is_string($index_php) ? strpos($index_php, 'id="aa-lists-section"') : false;
$exec_pos = is_string($index_php) ? strpos($index_php, 'id="aa-executive-proposal"') : false;
ac_assert(
    'Cycle B: aa-lists-section appears before aa-executive-proposal',
    $lists_pos !== false && $exec_pos !== false && $lists_pos < $exec_pos
);
ac_assert(
    'Cycle B: aa-lists-header-toggle starts with aria-expanded=true',
    is_string($index_php) && preg_match('/id="aa-lists-header-toggle"[^>]*aria-expanded="true"/', $index_php) === 1
);
ac_assert(
    'Cycle B: aa-lists-body starts without is-collapsed',
    is_string($index_php)
    && preg_match('/id="aa-lists-body"\s+class="aa-lists-body"/', $index_php) === 1
);
ac_assert(
    'Cycle B: aa-lists-body starts with aria-hidden=false',
    is_string($index_php) && preg_match('/id="aa-lists-body"[^>]*aria-hidden="false"/', $index_php) === 1
);
ac_assert(
    'Cycle B: aa-lists-body starts without inert',
    is_string($index_php)
    && preg_match('/id="aa-lists-body"[^>]*\binert\b/', $index_php) === 0
);
ac_assert(
    'Cycle B: divider removed from markup',
    is_string($index_php) && strpos($index_php, 'aa-executive-lists-divider') === false
);
ac_assert(
    'Cycle B: aa-lists-section no longer has pb-24',
    is_string($index_php) && strpos($index_php, 'id="aa-lists-section" class="pb-24"') === false
);
ac_assert(
    'Cycle B: aa-tasks-module-root has pb-24',
    is_string($index_php) && preg_match('/id="aa-tasks-module-root"[^>]*pb-24/', $index_php) === 1
);
ac_assert(
    'Cycle B: executive proposal preserves key nodes',
    is_string($index_php)
    && strpos($index_php, 'id="aa-executive-status"') !== false
    && strpos($index_php, 'id="aa-executive-header-actions"') !== false
    && strpos($index_php, 'id="aa-executive-list"') !== false
);

// --- Cycle C assertions ---
ac_assert(
    'Cycle C: aa-executive-section-header exists',
    is_string($index_php) && strpos($index_php, 'id="aa-executive-section-header"') !== false
);
ac_assert(
    'Cycle C: aa-executive-header-toggle exists',
    is_string($index_php) && strpos($index_php, 'id="aa-executive-header-toggle"') !== false
);
ac_assert(
    'Cycle C: toggle text is Propuesta de ejecución',
    is_string($index_php) && strpos($index_php, 'Propuesta de ejecución') !== false
);
ac_assert(
    'Cycle C: executive toggle has aria-expanded=false',
    is_string($index_php) && preg_match('/id="aa-executive-header-toggle"[^>]*aria-expanded="false"/', $index_php) === 1
);
ac_assert(
    'Cycle C: executive toggle controls aa-executive-body',
    is_string($index_php) && preg_match('/id="aa-executive-header-toggle"[^>]*aria-controls="aa-executive-body"/', $index_php) === 1
);
ac_assert(
    'Cycle C: aa-executive-body exists',
    is_string($index_php) && strpos($index_php, 'id="aa-executive-body"') !== false
);
ac_assert(
    'Cycle C: aa-executive-body starts with is-collapsed',
    is_string($index_php) && preg_match('/id="aa-executive-body"[^>]*is-collapsed/', $index_php) === 1
);
ac_assert(
    'Cycle C: aa-executive-body starts with aria-hidden=true',
    is_string($index_php) && preg_match('/id="aa-executive-body"[^>]*aria-hidden="true"/', $index_php) === 1
);
ac_assert(
    'Cycle C: aa-executive-body starts with inert',
    is_string($index_php) && preg_match('/id="aa-executive-body"[^>]*\binert\b/', $index_php) === 1
);
ac_assert(
    'Cycle C: no duplicate visible executive title h3',
    is_string($index_php) && preg_match('/<h3[^>]*>Propuesta ejecutiva<\/h3>/', $index_php) === 0
);
ac_assert(
    'Cycle C: organizer still expanded initially',
    is_string($index_php) && preg_match('/id="aa-lists-header-toggle"[^>]*aria-expanded="true"/', $index_php) === 1
);
ac_assert(
    'Cycle C: no divider exists',
    is_string($index_php) && strpos($index_php, 'aa-executive-lists-divider') === false
);
ac_assert(
    'Cycle C: pb-24 still on root',
    is_string($index_php) && preg_match('/id="aa-tasks-module-root"[^>]*pb-24/', $index_php) === 1
);

// --- Last-card shadow room under #aa-lists-body overflow:hidden ---
ac_assert(
    'active-root keeps id and space-y-2 pb-1 for last-card shadow room',
    is_string($index_php)
    && preg_match(
        '/id="aa-executable-lists-active-root"\s+class="space-y-2 pb-1"/',
        $index_php
    ) === 1
);

// --- Cycle D assertions ---
$admin_css_src = file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.source.css');
$section_toggles_src = file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/learning/section-toggles-module.js'
);
ac_assert(
    'lists-body expanded keeps overflow:hidden for collapse animation',
    is_string($admin_css_src)
    && preg_match(
        '/#aa-lists-body\s*,\s*#aa-executive-body\s*\{[^}]*overflow:\s*hidden/',
        $admin_css_src
    ) === 1
);
ac_assert(
    'lists-body.is-collapsed keeps overflow:hidden',
    is_string($admin_css_src)
    && preg_match(
        '/#aa-lists-body\.is-collapsed\s*,\s*#aa-executive-body\.is-collapsed\s*\{[^}]*overflow:\s*hidden/',
        $admin_css_src
    ) === 1
);
ac_assert(
    'lists-body source does not use overflow:visible to fix shadow clip',
    is_string($admin_css_src)
    && preg_match('/#aa-lists-body[^{]*\{[^}]*overflow:\s*visible/', $admin_css_src) === 0
);
ac_assert(
    'section-toggles-module has no overflow:visible or transitionend for shadow clip',
    is_string($section_toggles_src)
    && strpos($section_toggles_src, 'overflow') === false
    && strpos($section_toggles_src, 'transitionend') === false
);
$admin_css_built = file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.css');
ac_assert(
    'admin.css build includes .pb-1 utility',
    is_string($admin_css_built)
    && preg_match('/\.pb-1\{[^}]*padding-bottom:\s*\.25rem/', $admin_css_built) === 1
);
ac_assert(
    'Cycle D: chevron del organizador tiene transition-transform en markup',
    is_string($index_php) && preg_match('/aa-lists-header-chevron[^"]*transition-transform/', $index_php) === 1
);
ac_assert(
    'Cycle D: chevron del ejecutor tiene transition-transform en markup',
    is_string($index_php) && preg_match('/aa-executive-header-chevron[^"]*transition-transform/', $index_php) === 1
);
ac_assert(
    'Cycle D: CSS tiene regla de rotación para organizador',
    is_string($admin_css_src) && strpos($admin_css_src, '#aa-lists-header-toggle[aria-expanded="true"] .aa-lists-header-chevron') !== false
);
ac_assert(
    'Cycle D: CSS tiene regla de rotación para ejecutor',
    is_string($admin_css_src) && strpos($admin_css_src, '#aa-executive-header-toggle[aria-expanded="true"] .aa-executive-header-chevron') !== false
);
ac_assert(
    'Cycle 2A: regla de rotación Learning usa rotate(90deg)',
    is_string($admin_css_src) && preg_match('/aa-lists-header-chevron[^}]*rotate\(90deg\)/', $admin_css_src) === 1
);
ac_assert(
    'Cycle 2A: headers Learning usan chevron derecho base',
    is_string($index_php)
    && substr_count($index_php, 'M9 5l7 7-7 7') >= 2
    && strpos($index_php, 'aa-lists-header-chevron') !== false
);
ac_assert(
    'Cycle D: script enqueued es section-toggles-module.js',
    is_string($index_php) && strpos($index_php, 'section-toggles-module.js') !== false
);
ac_assert(
    'Cycle D: no queda referencia a executive-lists-focus-module.js en index.php',
    is_string($index_php) && strpos($index_php, 'executive-lists-focus-module.js') === false
);

// --- Cycle E assertions ---
ac_assert(
    'Cycle E: aa-executive-header-summary exists once',
    is_string($index_php)
    && substr_count($index_php, 'id="aa-executive-header-summary"') === 1
);
ac_assert(
    'Cycle E: summary is inside aa-executive-header-toggle',
    is_string($index_php)
    && ($summary_pos = strpos($index_php, 'id="aa-executive-header-summary"')) !== false
    && ($toggle_start = strpos($index_php, 'id="aa-executive-header-toggle"')) !== false
    && ($toggle_end = strpos($index_php, '</button>', $toggle_start)) !== false
    && $summary_pos > $toggle_start
    && $summary_pos < $toggle_end
);
ac_assert(
    'Cycle E: summary is between label and chevron',
    is_string($index_php)
    && ($label_pos = strpos($index_php, 'aa-executive-header-label')) !== false
    && ($summ_pos = strpos($index_php, 'aa-executive-header-summary')) !== false
    && ($chev_pos = strpos($index_php, 'aa-executive-header-chevron')) !== false
    && $label_pos < $summ_pos
    && $summ_pos < $chev_pos
);
ac_assert(
    'Cycle E: summary has min-w-0 and truncate',
    is_string($index_php)
    && preg_match('/id="aa-executive-header-summary"[^>]*min-w-0/', $index_php) === 1
    && preg_match('/id="aa-executive-header-summary"[^>]*truncate/', $index_php) === 1
);
ac_assert(
    'Cycle E: summary does not have aria-hidden',
    is_string($index_php)
    && preg_match('/id="aa-executive-header-summary"[^>]*aria-hidden/', $index_php) === 0
);
ac_assert(
    'Cycle E: summary does not have aria-live',
    is_string($index_php)
    && preg_match('/id="aa-executive-header-summary"[^>]*aria-live/', $index_php) === 0
);
ac_assert(
    'Cycle E: summary starts empty',
    is_string($index_php)
    && preg_match('/id="aa-executive-header-summary"[^>]*><\/span>/', $index_php) === 1
);
ac_assert(
    'Cycle E: no second button inside toggle',
    is_string($index_php)
    && ($toggle_s = strpos($index_php, 'id="aa-executive-header-toggle"')) !== false
    && ($toggle_e = strpos($index_php, '</button>', $toggle_s)) !== false
    && substr_count(substr($index_php, $toggle_s, $toggle_e - $toggle_s), '<button') === 0
);
ac_assert(
    'Cycle E: CSS hides summary when expanded',
    is_string($admin_css_src)
    && strpos($admin_css_src, '#aa-executive-header-toggle[aria-expanded="true"] .aa-executive-header-summary') !== false
);
ac_assert(
    'Cycle E: renderer IDs still present',
    is_string($index_php)
    && strpos($index_php, 'id="aa-executive-status"') !== false
    && strpos($index_php, 'id="aa-executive-list"') !== false
    && strpos($index_php, 'id="aa-executive-empty"') !== false
);
ac_assert(
    'Cycle E: label has shrink-0',
    is_string($index_php)
    && preg_match('/aa-executive-header-label[^"]*shrink-0/', $index_php) === 1
);

ac_assert(
    'index.php enqueues learning-action-handlers.js',
    is_string($index_php) && strpos($index_php, 'learning-action-handlers.js') !== false
);

$board_module_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/learning/tasks-board-module.js');
ac_assert('tasks-board-module readable', $board_module_src !== false);
ac_assert(
    'tasks-board-module no longer renders legacy executive proposal in renderBoardPayload',
    is_string($board_module_src)
    && strpos($board_module_src, 'renderExecutiveProposal(data)') === false
);
ac_assert(
    'tasks-board-module refreshes executive proposal after board mutation',
    is_string($board_module_src) && strpos($board_module_src, 'reloadExecutiveProposalBestEffort') !== false
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
