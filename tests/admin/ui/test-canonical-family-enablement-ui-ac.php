<?php
/**
 * AC Test — Settings / sidebar / gate UI containment (PCU-5A).
 *
 * Ejecutar: php tests/admin/ui/test-canonical-family-enablement-ui-ac.php
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

$settings = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/settings/index.php');
$sidebar = (string) file_get_contents($plugin_root . '/includes/admin/ui/shared/sidebar.php');
$router = (string) file_get_contents($plugin_root . '/includes/admin/ui/index.php');
$shell = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
$js = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/settings/canonical-family-toggles.js');
$binding = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php');
$parent = (string) file_get_contents($plugin_root . '/views/admin-controls.php');
$schema = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/Schema.php');
$catalog = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-catalog-lifecycle.php');

$form_close_pos = strpos($settings, '</form>');
$section_pos = strpos($settings, 'id="aa-canonical-record-types-root"');
$script_pos = strpos($settings, 'canonical-family-toggles.js');
ac_assert('Sección después de </form>', $form_close_pos !== false && $section_pos !== false && $section_pos > $form_close_pos);
ac_assert('Script después de sección', $script_pos !== false && $script_pos > $section_pos);
ac_assert('Sin form propio de familias', preg_match('/aa-canonical-record-types-root[\s\S]*?<form/i', $settings) !== 1);
ac_assert('Sin submit en sección', !preg_match('/aa-canonical-record-types-root[\s\S]*?type=["\']submit["\']/i', $settings));
ac_assert('Sin name= en toggles', !preg_match('/data-aa-canonical-family-toggle[^>]*name=/', $settings));
ac_assert('Título exacto', strpos($settings, '>Tipos de registros</h3>') !== false);
ac_assert('Copy exacto', strpos($settings, 'Elige los tipos de información que quieres organizar en DEOIA.') !== false);
ac_assert('data-aa-canonical-family-toggle', strpos($settings, 'data-aa-canonical-family-toggle') !== false);
ac_assert('aria-live status', strpos($settings, 'data-aa-family-status') !== false && strpos($settings, 'aria-live="polite"') !== false);
ac_assert('AA_CANONICAL_FAMILY_ENABLED config', strpos($settings, 'AA_CANONICAL_FAMILY_ENABLED') !== false);
ac_assert('JS dedicado cargado', strpos($settings, 'canonical-family-toggles.js') !== false);

ac_assert('Sidebar sin Shell canónico provisional', strpos($sidebar, 'Shell canónico') === false);
ac_assert('Sidebar Tipos de registros', strpos($sidebar, 'Tipos de registros') !== false);
ac_assert('Contenedor nav id', strpos($sidebar, 'id="aa-canonical-record-types-nav"') !== false);
ac_assert('Finance legacy intacto', strpos($sidebar, 'data-aa-nav-module="canonical"') !== false
    && strpos($sidebar, '>Finanzas</span>') !== false);

ac_assert('Gate family_disabled en router', strpos($router, "family_disabled") !== false);
ac_assert('Gate antes de compose', strpos($router, 'aa_enablement_gate_state') !== false
    && strpos($router, 'compose_family') !== false);
ac_assert('Copy family_disabled en shell', strpos($shell, 'Este tipo de registro está desactivado.') !== false
    && strpos($shell, 'Puedes activarlo en Ajustes, en la sección “Tipos de registros”.') !== false);

ac_assert('postMessage type en JS', strpos($js, 'aa-canonical-family-enabled-changed') !== false);
ac_assert('targetOrigin exacto en JS', strpos($js, "postMessage({") !== false && strpos($js, ", '*')") === false);
ac_assert('Parent handler', strpos($parent, 'aa-canonical-family-enabled-changed') !== false
    && strpos($parent, 'aa-canonical-record-types-nav') !== false);
ac_assert('Parent usa textContent', strpos($parent, 'textContent = label') !== false);

ac_assert('Binding Finance legacy intacto', strpos($binding, 'AA_Finance_Canonical_Read_Adapter') !== false
    && strpos($binding, "CanonicalReadIdentity('finance', 'general')") !== false);
ac_assert('DB_VERSION=21', strpos($schema, "DB_VERSION = '21'") !== false);
ac_assert('CATALOG_VERSION=1', strpos($catalog, 'CATALOG_VERSION = 1') !== false);
ac_assert('Sin PCU-3 productivo en bootstrap read', strpos($binding, 'CanonicalRelational') === false
    && strpos($binding, 'CanonicalReadRelational') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
