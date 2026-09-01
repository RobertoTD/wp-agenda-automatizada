<?php
/**
 * AC Test — Finance UI Module (Ciclos 3D1, 3D2 y 3D3A).
 *
 * Ejecutar:
 *   php tests/admin/ui/test-finance-ui-module-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: $url;
    }
}
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string {
        return 'nonce_' . $action;
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512): string {
        return json_encode($data, $options, $depth);
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string {
        return 'https://example.com/wp-content/plugins/wp-agenda-automatizada/includes/admin/ui/modules/canonical/finance/';
    }
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';
require_once $plugin_root . '/includes/http/ajax/FinanceAjaxSupport.php';
require_once $plugin_root . '/includes/http/ajax/FinanceContainersAjax.php';
require_once $plugin_root . '/includes/http/ajax/FinanceRecordsAjax.php';

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

echo "=== 1. Análisis estático de templates y scripts ===\n";

$finance_tpl_file = $plugin_root . '/includes/admin/ui/modules/canonical/finance/index.php';
$finance_js_file  = $plugin_root . '/includes/admin/ui/modules/canonical/finance/finance-module.js';
$finance_records_js_file = $plugin_root . '/includes/admin/ui/modules/canonical/finance/finance-records-module.js';
$canonical_dispatcher = $plugin_root . '/includes/admin/ui/modules/canonical/index.php';
$fallback_tpl_file    = $plugin_root . '/includes/admin/ui/modules/canonical/_fallback.php';

ac_assert('Template finance/index.php existe y es legible', is_readable($finance_tpl_file));
ac_assert('Script finance-module.js existe y es legible', is_readable($finance_js_file));
ac_assert('Script finance-records-module.js existe y es legible', is_readable($finance_records_js_file));
ac_assert('Dispatcher canonical/index.php existe', is_readable($canonical_dispatcher));
ac_assert('Fallback canonical/_fallback.php existe', is_readable($fallback_tpl_file));

$finance_tpl_src = file_get_contents($finance_tpl_file);
$finance_js_src  = file_get_contents($finance_js_file);
$finance_records_js_src = file_get_contents($finance_records_js_file);

ac_assert('Template no lee $_GET', strpos($finance_tpl_src, '$_GET') === false);
ac_assert('Template no usa window.ajaxurl', strpos($finance_tpl_src, 'window.ajaxurl') === false);
ac_assert('Script JS no usa window.ajaxurl', strpos($finance_js_src, 'window.ajaxurl') === false);
ac_assert('Script JS no usa wpaa_vars', strpos($finance_js_src, 'wpaa_vars') === false);
ac_assert('Script JS no usa innerHTML con datos', strpos($finance_js_src, '.innerHTML =') === false);
ac_assert('Template deriva nonce de FinanceAjaxSupport::NONCE_ACTION', strpos($finance_tpl_src, 'FinanceAjaxSupport::NONCE_ACTION') !== false);
ac_assert('Template deriva acción List de FinanceContainersAjax::ACTION_LIST', strpos($finance_tpl_src, 'FinanceContainersAjax::ACTION_LIST') !== false);
ac_assert('Template deriva acción Create de FinanceContainersAjax::ACTION_CREATE', strpos($finance_tpl_src, 'FinanceContainersAjax::ACTION_CREATE') !== false);
ac_assert('Script records no usa innerHTML con datos', strpos($finance_records_js_src, '.innerHTML =') === false);
ac_assert('Template deriva acción listRecords de FinanceRecordsAjax::ACTION_LIST', strpos($finance_tpl_src, 'FinanceRecordsAjax::ACTION_LIST') !== false);
ac_assert('Template no publica acciones create/get/delete de registros', strpos($finance_tpl_src, 'FinanceRecordsAjax::ACTION_CREATE') === false && strpos($finance_tpl_src, 'FinanceRecordsAjax::ACTION_GET') === false && strpos($finance_tpl_src, 'FinanceRecordsAjax::ACTION_DELETE') === false);

echo "\n=== 2. Renderizado de Vista Finance con Contexto Resuelto ===\n";

$registry = AA_Canonical_Core_Bootstrap::build_registry();
$aa_canonical_family = $registry->family('finance');
$aa_canonical_variant = $registry->variant('finance', 'general');

ob_start();
require $canonical_dispatcher;
$html = ob_get_clean();

ac_assert('Dispatcher renderiza aa-finance-root para family=finance', strpos($html, 'id="aa-finance-root"') !== false);
ac_assert('Renderiza label Finanzas obtenido del objeto', strpos($html, 'Finanzas') !== false);
ac_assert('Renderiza label General obtenido del objeto', strpos($html, 'General') !== false);
ac_assert('Contiene data-aa-canonical-family="finance"', strpos($html, 'data-aa-canonical-family="finance"') !== false);
ac_assert('Contiene data-aa-canonical-variant="general"', strpos($html, 'data-aa-canonical-variant="general"') !== false);
ac_assert('Contiene data-aa-canonical-qualified="finance.general"', strpos($html, 'data-aa-canonical-qualified="finance.general"') !== false);
ac_assert('Contiene botón trigger Nueva lista', strpos($html, 'id="aa-finance-open-create-btn"') !== false && strpos($html, 'Nueva lista') !== false);
ac_assert('Contiene modal de creación accesible', strpos($html, 'id="aa-finance-create-modal"') !== false && strpos($html, 'role="dialog"') !== false && strpos($html, 'aria-modal="true"') !== false);
ac_assert('Contiene input de título y textarea de detalles', strpos($html, 'id="aa-finance-create-title"') !== false && strpos($html, 'id="aa-finance-create-details"') !== false);
ac_assert('Contiene botón de salida para estado incierto', strpos($html, 'id="aa-finance-modal-uncertain-close-btn"') !== false && strpos($html, 'Cerrar y revisar') !== false);
ac_assert('Contiene región aria-live en status', strpos($html, 'id="aa-finance-status" class="text-sm text-gray-500" aria-live="polite" tabindex="-1"') !== false);
ac_assert('Status es destino programático de foco tras Cerrar y revisar', strpos($html, 'id="aa-finance-status"') !== false && strpos($html, 'tabindex="-1"') !== false);
ac_assert('Contiene grid de contenedores', strpos($html, 'id="aa-finance-grid"') !== false);
ac_assert('Carga script finance-records-module.js', strpos($html, 'finance-records-module.js') !== false);
ac_assert('Carga script finance-module.js', strpos($html, 'finance-module.js') !== false);
ac_assert('Records script precede al orquestador en el markup', strpos($html, 'finance-records-module.js') !== false && strpos($html, 'finance-module.js') !== false && strpos($html, 'finance-records-module.js') < strpos($html, 'finance-module.js'));

ac_assert('Contiene región de detalle aa-finance-records-container oculta', strpos($html, 'id="aa-finance-records-container"') !== false && preg_match('/id="aa-finance-records-container"[^>]*class="[^"]*hidden[^"]*"/', $html) === 1);
ac_assert('Contiene botón Volver a listas', strpos($html, 'id="aa-finance-records-back"') !== false && strpos($html, 'Volver a listas') !== false);
ac_assert('Contiene heading enfocable de registros', strpos($html, 'id="aa-finance-records-heading"') !== false && strpos($html, 'tabindex="-1"') !== false);
ac_assert('Contiene summary, status, grid y paginación de registros', strpos($html, 'id="aa-finance-records-summary"') !== false && strpos($html, 'id="aa-finance-records-status"') !== false && strpos($html, 'id="aa-finance-records-grid"') !== false && strpos($html, 'id="aa-finance-records-pagination"') !== false);
ac_assert('Status de registros tiene aria-live polite', strpos($html, 'id="aa-finance-records-status" class="text-sm text-gray-500" aria-live="polite" tabindex="-1"') !== false);
ac_assert('Grid de registros declara aria-busy', strpos($html, 'id="aa-finance-records-grid"') !== false && strpos($html, 'aria-busy="false"') !== false);
ac_assert('Paginación de registros tiene controles accesibles', strpos($html, 'id="aa-finance-records-prev"') !== false && strpos($html, 'aria-label="Página anterior de registros"') !== false && strpos($html, 'id="aa-finance-records-next"') !== false && strpos($html, 'aria-label="Página siguiente de registros"') !== false);
ac_assert('No contiene formularios ni botones de mutación de registros', strpos($html, 'aa-finance-create-record') === false && strpos($html, 'aa-finance-edit-record') === false && strpos($html, 'aa-finance-delete-record') === false);

// Extraer JSON emitido en AA_FINANCE_DATA
preg_match('/window\.AA_FINANCE_DATA\s*=\s*(\{.*?\});/s', $html, $matches);
ac_assert('Emite bloque window.AA_FINANCE_DATA', !empty($matches[1]));

$config_json = json_decode($matches[1] ?? '{}', true);
ac_assert('Configuración tiene ajaxUrl válido', isset($config_json['ajaxUrl']) && strpos($config_json['ajaxUrl'], 'admin-ajax.php') !== false);
ac_assert('Configuración tiene nonce válido', isset($config_json['nonce']) && $config_json['nonce'] === 'nonce_aa_finance_nonce');
ac_assert('Configuración tiene familyKey = finance', isset($config_json['familyKey']) && $config_json['familyKey'] === 'finance');
ac_assert('Configuración tiene variantKey = general', isset($config_json['variantKey']) && $config_json['variantKey'] === 'general');
ac_assert('Configuración tiene actions.listContainers = aa_list_finance_containers', isset($config_json['actions']['listContainers']) && $config_json['actions']['listContainers'] === 'aa_list_finance_containers');
ac_assert('Configuración tiene actions.createContainer = aa_create_finance_container', isset($config_json['actions']['createContainer']) && $config_json['actions']['createContainer'] === 'aa_create_finance_container');
ac_assert('Configuración tiene actions.listRecords = aa_list_finance_records', isset($config_json['actions']['listRecords']) && $config_json['actions']['listRecords'] === 'aa_list_finance_records');
ac_assert('Configuración no publica create/get/delete record actions', !isset($config_json['actions']['createRecord']) && !isset($config_json['actions']['getRecord']) && !isset($config_json['actions']['deleteRecord']));

echo "\n=== 3. Comprobación del Dispatcher Fail-Closed y Fallback ===\n";

// Sin familia (contexto ausente)
unset($aa_canonical_family);
unset($aa_canonical_variant);
ob_start();
require $canonical_dispatcher;
$fallback_html = ob_get_clean();

ac_assert('Contexto ausente renderiza aa-canonical-root (_fallback.php)', strpos($fallback_html, 'id="aa-canonical-root"') !== false);
ac_assert('Contexto ausente no renderiza aa-finance-root', strpos($fallback_html, 'id="aa-finance-root"') === false);

// Familia no registrada en el mapa de templates
$custom_family = new AA_Canonical_Family_Definition('custom_family', 'Familia Custom', 'custom_var');
$aa_canonical_family = $custom_family;
$aa_canonical_variant = new AA_Canonical_Variant_Definition('custom_family', 'custom_var', 'Variante Custom');
ob_start();
require $canonical_dispatcher;
$custom_html = ob_get_clean();

ac_assert('Familia sin template en mapa renderiza _fallback.php', strpos($custom_html, 'id="aa-canonical-root"') !== false);
ac_assert('Familia sin template no cae en finance', strpos($custom_html, 'id="aa-finance-root"') === false);

echo "\n=========================================\n";
echo "Total assertions: $total\n";
echo "Passed: $passed\n";
echo "Failed: " . count($failed) . "\n";
echo "=========================================\n";

if (count($failed) > 0) {
    echo "Fallas detectadas:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}

exit(0);
