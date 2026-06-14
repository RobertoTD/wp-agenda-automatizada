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
    'index.php lists section header is Listas de tareas',
    is_string($index_php) && strpos($index_php, '>Listas de tareas<') !== false
);
ac_assert(
    'index.php does not render lists section subtitle',
    is_string($index_php) && strpos($index_php, 'Todas las listas de tareas.') === false
);
ac_assert(
    'index.php renders executive/lists divider',
    is_string($index_php) && strpos($index_php, 'aa-executive-lists-divider') !== false
);
ac_assert(
    'index.php keeps aa-executive-proposal and aa-lists-section ids',
    is_string($index_php)
    && strpos($index_php, 'id="aa-executive-proposal"') !== false
    && strpos($index_php, 'id="aa-lists-section"') !== false
);
ac_assert(
    'index.php lists section starts muted',
    is_string($index_php) && strpos($index_php, 'id="aa-lists-section" class="pb-24 is-muted"') !== false
);
ac_assert(
    'index.php enqueues executive-lists-focus-module.js',
    is_string($index_php) && strpos($index_php, 'executive-lists-focus-module.js') !== false
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
