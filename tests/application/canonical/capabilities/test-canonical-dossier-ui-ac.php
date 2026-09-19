<?php
/**
 * AC — dossier: UI/SSR (contributor, presenter, superficie de acciones, label).
 *
 * Ejecutar:
 *   php tests/application/canonical/capabilities/test-canonical-dossier-ui-ac.php
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

echo "=== UI/SSR dossier ===\n";

$card = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php'
);
ac_assert('Presenter dossier en card', strpos($card, 'AA_Canonical_Dossier_Shell_Presenter::card_action') !== false);
ac_assert('Superficie capability-actions', strpos($card, 'aa-shell-record-capability-actions') !== false);
ac_assert('data-aa-dossier-open', strpos($card, 'data-aa-dossier-open') !== false);
ac_assert('Botón disabled Archivo', strpos($card, 'archive_disabled') !== false);
$compact_pos = strpos($card, '$is_compact');
$actions_count = substr_count($card, 'aa-shell-record-capability-actions');
ac_assert('Acciones en compacta y normal', $actions_count >= 2);

$index = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);
ac_assert('Label Expediente', strpos($index, "'Expediente'") !== false || strpos($index, 'Expediente') !== false);
ac_assert('dossier → Expediente', strpos($index, "cap_key === 'dossier'") !== false);
ac_assert('Boot AA_CANONICAL_SHELL_DOSSIER', strpos($index, 'AA_CANONICAL_SHELL_DOSSIER') !== false);
ac_assert('Script dossier-action.js', strpos($index, 'canonical-shell-dossier-action.js') !== false);

$js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-dossier-action.js'
);
ac_assert('JS POST method', strpos($js, "method: 'POST'") !== false);
ac_assert('JS usa redirect_url', strpos($js, 'redirect_url') !== false);
ac_assert('JS no muta por GET', strpos($js, "method: 'GET'") === false);

$contrib = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/capabilities/dossier/CanonicalDossierRecordsPageContributor.php'
);
ac_assert('Contributor solo contact', strpos($contrib, "family_key !== 'contact'") !== false);
ac_assert('VALUE_ARCHIVE_DISABLED', strpos($contrib, 'VALUE_ARCHIVE_DISABLED') !== false);
ac_assert('Sin create en contributor', strpos($contrib, 'create_container') === false && strpos($contrib, 'insert_association') === false);

$page_boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-page-contributor-bootstrap.php'
);
ac_assert('Page bootstrap registra dossier', strpos($page_boot, 'CanonicalDossierRecordsPageContributor') !== false);

$presenter = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-dossier-shell-presenter.php'
);
ac_assert('Presenter sin edit_payload', strpos($presenter, 'edit_payload') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
