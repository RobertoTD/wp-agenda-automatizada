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
ac_assert('Sidebar entrada Listas', strpos($sidebar, '>Listas</span>') !== false
    && strpos($sidebar, 'AA_Canonical_Shell_Base_Url_Policy::build_module_url') !== false);
ac_assert('Sidebar sin grupo Tipos de registros ni nav id', strpos($sidebar, 'Tipos de registros') === false
    && strpos($sidebar, 'aa-canonical-record-types-nav') === false);
ac_assert('Finance legacy nav ausente', strpos($sidebar, 'data-aa-nav-module="canonical"') === false
    && strpos($sidebar, '>Finanzas</span>') === false);

ac_assert('Gate family_disabled en router', strpos($router, "family_disabled") !== false);
ac_assert('Gate antes de compose', strpos($router, 'aa_enablement_gate_state') !== false
    && strpos($router, 'compose_family') !== false);
ac_assert('Copy family_disabled en shell', strpos($shell, 'Este tipo de registro está desactivado.') !== false
    && strpos($shell, 'Puedes activarlo en Ajustes, en la sección “Tipos de registros”.') !== false);

ac_assert('Toggles sin regeneración live de nav', strpos($js, 'renderNav') === false
    && strpos($js, 'aa-canonical-family-enabled-changed') === false
    && strpos($js, 'postMessage') === false);
ac_assert('Parent sin handler de nav obsoleto', strpos($parent, 'aa-canonical-family-enabled-changed') === false
    && strpos($parent, 'aa-canonical-record-types-nav') === false);

ac_assert('Binding Finance legacy ausente del bootstrap productivo', strpos($binding, 'AA_Finance_Canonical_Read_Adapter') === false);
ac_assert('Bootstrap read usa Relational', strpos($binding, 'AA_Canonical_Relational_Read_Adapter') !== false
    || strpos($binding, 'Relational_Read_Adapter') !== false);
ac_assert('DB_VERSION=28', strpos($schema, "DB_VERSION = '28'") !== false);
ac_assert('CATALOG_VERSION=2', strpos($catalog, 'CATALOG_VERSION = 2') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
