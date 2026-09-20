<?php
/**
 * AC — C3: Expediente es solution, no capability.
 *
 * Ejecutar: php tests/application/canonical/solutions/test-contact-dossier-c3-ac.php
 */

$plugin_root = dirname(__DIR__, 4);
$failed = [];
function c3_assert(string $label, bool $condition): void {
    global $failed;
    if ($condition) {
        echo "[PASS] {$label}\n";
        return;
    }
    $failed[] = $label;
    echo "[FAIL] {$label}\n";
}

$capabilities = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php');
$defaults = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php');
$schema = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php');
$support = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalShellWriteAjaxSupport.php');
$contributor = (string) file_get_contents($plugin_root . '/includes/application/canonical/capabilities/dossier/CanonicalDossierRecordsPageContributor.php');
$open = (string) file_get_contents($plugin_root . '/includes/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierUseCase.php');
$card = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php');
$form = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/canonical-shell-container-form.js');

c3_assert('Registry de capabilities no registra dossier', strpos($capabilities, "'dossier'") === false);
c3_assert('Defaults no siembra dossier', strpos($defaults, "'dossier'") === false);
c3_assert('DB 37 retira solo configuración legacy', strpos($schema, 'retire_legacy_dossier_capability_configuration_v37') !== false
    && strpos($schema, "capability_key = %s") !== false);
c3_assert('Wire de solution separado', strpos($support, 'parse_solution_selection_from_source') !== false
    && strpos($form, 'solution_selection_scope') !== false
    && strpos($form, 'solution_selection') !== false);
c3_assert('Contributor consulta application de solution', strpos($contributor, 'ReadContactDossierApplicationUseCase') !== false
    && strpos($contributor, "public const KEY = 'contact_dossier'") !== false);
c3_assert('Apertura no consulta capability config', strpos($open, 'find_container_capability') === false
    && strpos($open, 'application_reader->execute') !== false);
c3_assert('Card consume mapa solutions', strpos($card, '$card_solutions') !== false);

if ($failed !== []) {
    fwrite(STDERR, "C3 AC failed: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo "C3 AC passed.\n";
