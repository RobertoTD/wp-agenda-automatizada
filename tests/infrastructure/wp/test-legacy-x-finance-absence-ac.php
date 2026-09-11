<?php
/**
 * AC LEGACY-X bloque 2 — referencias y carga sin dependencias huérfanas.
 *
 * Ejecutar:
 *   php tests/infrastructure/wp/test-legacy-x-finance-absence-ac.php
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

echo "=== Archivos retirados ===\n";
$gone = [
    'includes/infrastructure/wp/FinanceSchema.php',
    'includes/infrastructure/wp/class-aa-canonical-shell-url-policy.php',
    'includes/application/finance/FinanceUseCaseSupport.php',
    'includes/http/ajax/FinanceContainersAjax.php',
    'includes/http/ajax/FinanceRecordsAjax.php',
    'includes/http/ajax/FinanceAjaxSupport.php',
    'includes/repositories/FinanceContainerRepository.php',
    'includes/repositories/FinanceRecordRepository.php',
    'includes/infrastructure/canonical/finance/class-aa-finance-canonical-read-adapter.php',
    'includes/admin/ui/modules/canonical/index.php',
    'includes/admin/ui/modules/canonical/finance/index.php',
];
foreach ($gone as $rel) {
    ac_assert('Ausente ' . $rel, !is_readable($plugin_root . '/' . $rel));
}

echo "\n=== Bootstrap / router / sidebar ===\n";
$main = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert('Main sin FinanceSchema', strpos($main, 'FinanceSchema') === false);
ac_assert('Main sin FinanceAjax', strpos($main, 'FinanceContainersAjax') === false
    && strpos($main, 'FinanceRecordsAjax') === false);
ac_assert('Main sin Shell_Url_Policy', strpos($main, 'class-aa-canonical-shell-url-policy') === false);
ac_assert('Main conserva Amount Normalizer path', strpos($main, 'AA_Canonical_Amount_Normalizer') !== false
    || is_readable($plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_Normalizer.php'));

$router = (string) file_get_contents($plugin_root . '/includes/admin/ui/index.php');
ac_assert('Router 404 para module=canonical', strpos($router, "requested_module === 'canonical'") !== false
    && strpos($router, 'UI module not found') !== false);
ac_assert('Router sin allowed canonical legacy', !preg_match("/allowed_modules\s*=\s*\[[^\]]*'canonical'\s*,/", $router));
ac_assert('Router sin FinanceUseCaseSupport', strpos($router, 'FinanceUseCaseSupport') === false);

$sidebar = (string) file_get_contents($plugin_root . '/includes/admin/ui/shared/sidebar.php');
ac_assert('Sidebar sin data-aa-nav-module=canonical', strpos($sidebar, 'data-aa-nav-module="canonical"') === false);
ac_assert('Sidebar sin Shell_Url_Policy', strpos($sidebar, 'AA_Canonical_Shell_Url_Policy') === false);

$iframe = (string) file_get_contents($plugin_root . '/includes/admin/iframe-test.php');
ac_assert('iframe-test sin Shell_Url_Policy', strpos($iframe, 'AA_Canonical_Shell_Url_Policy') === false);

echo "\n=== Docs operativas coherentes ===\n";
$doc05 = (string) file_get_contents($plugin_root . '/docs/05-canonical-capabilities.md');
$cheat = (string) file_get_contents($plugin_root . '/docs/00-paradigm-cheatsheet.md');
ac_assert('docs/05 no presenta Finance legacy como operativo', stripos($doc05, 'Finance legacy operativo') === false);
ac_assert('docs/05 registra LEGACY-X o retirada', stripos($doc05, 'LEGACY-X') !== false || stripos($doc05, 'retirado') !== false);
ac_assert('Cheatsheet registra LEGACY-X Finance', stripos($cheat, 'LEGACY-X') !== false);

echo "\n=== Suites legacy eliminadas ===\n";
ac_assert('Sin tests/application/finance', !is_dir($plugin_root . '/tests/application/finance'));
ac_assert('Sin test-finance-schema-ac', !is_readable($plugin_root . '/tests/infrastructure/wp/test-finance-schema-ac.php'));
ac_assert('Sin financeContainersModule.test.js', !is_readable($plugin_root . '/tests/js/financeContainersModule.test.js'));

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
