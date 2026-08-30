<?php
/**
 * Finance Canonical Module — Vista de lectura de Contenedores Financieros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical\Finance
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

if (!class_exists('FinanceAjaxSupport')) {
    require_once dirname(__DIR__, 4) . '/http/ajax/FinanceAjaxSupport.php';
}
if (!class_exists('FinanceContainersAjax')) {
    require_once dirname(__DIR__, 4) . '/http/ajax/FinanceContainersAjax.php';
}

/** @var AA_Canonical_Family_Definition $aa_canonical_family */
/** @var AA_Canonical_Variant_Definition $aa_canonical_variant */

$family_key     = $aa_canonical_family->key();
$family_label   = $aa_canonical_family->label();
$variant_key    = $aa_canonical_variant->key();
$variant_label  = $aa_canonical_variant->label();
$qualified_key  = $aa_canonical_variant->qualified_key();

$finance_config = [
    'ajaxUrl'    => admin_url('admin-ajax.php'),
    'nonce'      => wp_create_nonce(FinanceAjaxSupport::NONCE_ACTION),
    'familyKey'  => $family_key,
    'variantKey' => $variant_key,
    'actions'    => [
        'listContainers' => FinanceContainersAjax::ACTION_LIST,
    ],
];

$finance_module_js  = plugin_dir_url(__FILE__) . 'finance-module.js';
$finance_module_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
?>

<div
    id="aa-finance-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="<?php echo esc_attr($family_label); ?>"
    data-aa-canonical-family="<?php echo esc_attr($family_key); ?>"
    data-aa-canonical-variant="<?php echo esc_attr($variant_key); ?>"
    data-aa-canonical-qualified="<?php echo esc_attr($qualified_key); ?>"
>
    <!-- Encabezado de contexto canónico -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 font-bold text-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 leading-tight">
                        <?php echo esc_html($family_label); ?>
                    </h2>
                    <p class="text-sm text-gray-500">
                        Variante: <span class="font-medium text-gray-700"><?php echo esc_html($variant_label); ?></span>
                        <span class="text-gray-300 mx-1.5">•</span>
                        Clave: <code class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded font-mono"><?php echo esc_html($qualified_key); ?></code>
                    </p>
                </div>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    <?php echo esc_html($variant_label); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Región interactiva del listado de contenedores -->
    <div id="aa-finance-list-container" class="flex flex-col gap-4">
        <!-- Barra de estado y paginación -->
        <div id="aa-finance-action-bar" class="flex items-center justify-between min-h-9 flex-wrap gap-2">
            <div id="aa-finance-status" class="text-sm text-gray-500" aria-live="polite"></div>
            <div id="aa-finance-pagination" class="flex items-center gap-2 hidden" hidden>
                <button
                    type="button"
                    id="aa-finance-prev"
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                    disabled
                    aria-label="Página anterior"
                >← Anterior</button>
                <span id="aa-finance-page-indicator" class="text-xs text-gray-500">Página 1</span>
                <button
                    type="button"
                    id="aa-finance-next"
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                    disabled
                    aria-label="Página siguiente"
                >Siguiente →</button>
            </div>
        </div>

        <!-- Grid de Cards de Contenedores -->
        <div
            id="aa-finance-grid"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
        ></div>
    </div>
</div>

<script>
window.AA_FINANCE_DATA = <?php echo wp_json_encode($finance_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo esc_url($finance_module_js . '?ver=' . rawurlencode($finance_module_ver)); ?>" defer></script>
