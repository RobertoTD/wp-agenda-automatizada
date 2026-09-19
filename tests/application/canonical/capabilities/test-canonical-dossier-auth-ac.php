<?php
/**
 * AC — dossier: autorización / concurrencia / destino inválido / origen bajo lock.
 *
 * Ejecutar:
 *   php tests/application/canonical/capabilities/test-canonical-dossier-auth-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

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

echo "=== 1. Estático auth/navegación ===\n";
$ajax = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalOpenContactDossierAjax.php');
ac_assert('ACTION POST aa_open_canonical_contact_dossier', strpos($ajax, "ACTION = 'aa_open_canonical_contact_dossier'") !== false);
ac_assert('Solo family contact', strpos($ajax, 'CONTACT_FAMILY') !== false);
ac_assert('authorize contact + archive', substr_count($ajax, 'authorize_identity') >= 2);
ac_assert('archive_disabled tipado', strpos($ajax, 'archive_disabled') !== false);
ac_assert('origin_retiring tipado', strpos($ajax, 'origin_retiring') !== false);
ac_assert('Sin confiar archive_container_id del POST', strpos($ajax, 'archive_container_id') === false || preg_match('/\$_POST\[[\'"]archive_container_id/', $ajax) !== 1);
ac_assert(
    'Archivo desde authorize_identity conservado',
    preg_match(
        '/\$authorized_archive\s*=\s*CanonicalShellWriteAjaxSupport::authorize_identity\(\s*OpenOrCreateCanonicalContactDossierUseCase::ARCHIVE_FAMILY/',
        $ajax
    ) === 1
    && strpos($ajax, "\$archive_family = \$authorized_archive['family']") !== false
);
ac_assert(
    'Use Case recibe \$archive_family autorizado',
    preg_match(
        '/new OpenOrCreateCanonicalContactDossierUseCase\([\s\S]*?\$archive_family\s*\)/',
        $ajax
    ) === 1
);
ac_assert(
    'Sin segunda resolución vía Core_Bootstrap::instance',
    strpos($ajax, 'AA_Canonical_Core_Bootstrap::instance()') === false
);
ac_assert(
    'Sin registry->get para Archivo',
    !preg_match('/\$registry\s*->\s*get\s*\(/', $ajax)
);
ac_assert(
    'Sin mensaje No se pudo preparar Archivo',
    strpos($ajax, 'No se pudo preparar Archivo.') === false
);

$uc = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierUseCase.php'
);
ac_assert('Lock SCOPE_CANONICAL_CONTAINER', strpos($uc, 'SCOPE_CANONICAL_CONTAINER') !== false);
ac_assert('Revalida find_container + find_record bajo lock', strpos($uc, 'find_container(') !== false && strpos($uc, 'find_record(') !== false);
ac_assert('Revalida dossier is_active', strpos($uc, "find_container_capability") !== false);
ac_assert('build_records_url con lists_scope', strpos($uc, 'build_records_url') !== false);
ac_assert('recover_after_create_conflict', strpos($uc, 'recover_after_create_conflict') !== false);

$cmd = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierCommand.php'
);
ac_assert('lists_scope en comando', strpos($cmd, 'lists_scope') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierUseCase.php';

$title = OpenOrCreateCanonicalContactDossierUseCase::build_dossier_title(
    str_repeat('N', 250)
);
ac_assert(
    'Título respeta MAX_TITLE_LENGTH',
    function_exists('mb_strlen')
        ? mb_strlen($title, 'UTF-8') <= 200
        : strlen($title) <= 200
);
ac_assert('Prefijo Exp — ', strpos($title, 'Exp — ') === 0);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
