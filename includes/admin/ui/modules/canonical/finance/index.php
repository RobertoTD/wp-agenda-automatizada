<?php
/**
 * Finance Canonical Module — Vista de lectura y creación de Contenedores Financieros.
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
if (!class_exists('FinanceRecordsAjax')) {
    require_once dirname(__DIR__, 4) . '/http/ajax/FinanceRecordsAjax.php';
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
        'listContainers'   => FinanceContainersAjax::ACTION_LIST,
        'createContainer'  => FinanceContainersAjax::ACTION_CREATE,
        'listRecords'      => FinanceRecordsAjax::ACTION_LIST,
        'createRecord'     => FinanceRecordsAjax::ACTION_CREATE,
    ],
];

$finance_records_js = plugin_dir_url(__FILE__) . 'finance-records-module.js';
$finance_record_create_js = plugin_dir_url(__FILE__) . 'finance-record-create-module.js';
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
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    <?php echo esc_html($variant_label); ?>
                </span>
                <button
                    type="button"
                    id="aa-finance-open-create-btn"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nueva lista</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Región interactiva del listado de contenedores -->
    <div id="aa-finance-list-container" class="flex flex-col gap-4">
        <!-- Barra de estado y paginación -->
        <div id="aa-finance-action-bar" class="flex items-center justify-between min-h-9 flex-wrap gap-2">
            <div id="aa-finance-status" class="text-sm text-gray-500" aria-live="polite" tabindex="-1"></div>
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

    <!-- Región de detalle y registros (solo lectura) -->
    <div
        id="aa-finance-records-container"
        class="flex flex-col gap-4 hidden"
        hidden
        aria-hidden="true"
    >
        <div class="flex items-center gap-3">
            <button
                type="button"
                id="aa-finance-records-back"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition"
            >
                ← Volver a listas
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3
                id="aa-finance-records-heading"
                class="text-xl font-bold text-gray-900 leading-tight"
                tabindex="-1"
            ></h3>
            <div id="aa-finance-records-summary" class="mt-2"></div>
        </div>

        <div id="aa-finance-records-action-bar" class="flex items-center justify-between min-h-9 flex-wrap gap-2">
            <div id="aa-finance-records-status" class="text-sm text-gray-500" aria-live="polite" tabindex="-1"></div>
            <div class="flex items-center gap-2 flex-wrap">
                <button
                    type="button"
                    id="aa-finance-open-record-btn"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition hidden"
                    hidden
                    aria-disabled="true"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nueva entrada</span>
                </button>
                <div id="aa-finance-records-pagination" class="flex items-center gap-2 hidden" hidden>
                <button
                    type="button"
                    id="aa-finance-records-prev"
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                    disabled
                    aria-label="Página anterior de registros"
                >← Anterior</button>
                <span id="aa-finance-records-page-indicator" class="text-xs text-gray-500">Página 1</span>
                <button
                    type="button"
                    id="aa-finance-records-next"
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                    disabled
                    aria-label="Página siguiente de registros"
                >Siguiente →</button>
                </div>
            </div>
        </div>

        <div
            id="aa-finance-records-grid"
            class="grid grid-cols-1 gap-4"
            aria-busy="false"
        ></div>
    </div>

    <!-- Modal Accesible de Creación de Entrada Financiera -->
    <div
        id="aa-finance-record-create-modal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-finance-record-create-modal-title"
        aria-hidden="true"
    >
        <div id="aa-finance-record-create-modal-backdrop" class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true"></div>

        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-4">
                <h3 id="aa-finance-record-create-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                    Nueva entrada
                </h3>
                <button
                    type="button"
                    id="aa-finance-record-create-close"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Cerrar modal"
                >
                    ✕
                </button>
            </div>

            <form id="aa-finance-record-create-form" novalidate>
                <div id="aa-finance-record-create-error" class="hidden mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium" aria-live="polite"></div>

                <div class="space-y-4">
                    <div>
                        <label for="aa-finance-record-create-title" class="block text-xs font-semibold text-gray-700 mb-1">
                            Título <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="aa-finance-record-create-title"
                            name="title"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Ej. Compra de material, Pago de servicio…"
                            autocomplete="off"
                            required
                        />
                        <p id="aa-finance-record-title-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                    </div>

                    <div>
                        <label for="aa-finance-record-create-details" class="block text-xs font-semibold text-gray-700 mb-1">
                            Detalles (opcional)
                        </label>
                        <textarea
                            id="aa-finance-record-create-details"
                            name="details"
                            rows="3"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Descripción o notas adicionales…"
                        ></textarea>
                        <p id="aa-finance-record-details-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                    </div>

                    <div>
                        <label for="aa-finance-record-create-amount" class="block text-xs font-semibold text-gray-700 mb-1">
                            Importe (opcional)
                        </label>
                        <input
                            type="text"
                            id="aa-finance-record-create-amount"
                            name="amount"
                            inputmode="decimal"
                            autocomplete="off"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Ej. 150.00, -25.50, 0.00"
                        />
                        <p class="mt-1 text-xs text-gray-500">Opcional. Usa punto decimal. Sin símbolo de moneda.</p>
                        <p id="aa-finance-record-amount-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                    </div>
                </div>

                <div id="aa-finance-record-create-actions-standard" class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-finance-record-create-cancel"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        id="aa-finance-record-create-submit"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Crear entrada
                    </button>
                </div>

                <div id="aa-finance-record-create-actions-uncertain" class="hidden mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-finance-record-create-uncertain-close"
                        class="px-4 py-2 text-xs font-semibold text-white bg-gray-800 rounded-lg hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-600"
                    >
                        Cerrar y revisar
                    </button>
                </div>

                <div id="aa-finance-record-create-actions-blocked" class="hidden mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-finance-record-create-blocked-close"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        Cerrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Accesible de Creación de Lista Financiera -->
    <div
        id="aa-finance-create-modal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-finance-modal-title"
        aria-hidden="true"
    >
        <!-- Backdrop -->
        <div id="aa-finance-modal-backdrop" class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true"></div>

        <!-- Panel Modal -->
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-4">
                <h3 id="aa-finance-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                    Nueva lista de Finanzas
                </h3>
                <button
                    type="button"
                    id="aa-finance-modal-close-btn"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Cerrar modal"
                >
                    ✕
                </button>
            </div>

            <form id="aa-finance-create-form" novalidate>
                <!-- Errores generales / Advertencias -->
                <div id="aa-finance-modal-error" class="hidden mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium" aria-live="polite"></div>

                <div class="space-y-4">
                    <div>
                        <label for="aa-finance-create-title" class="block text-xs font-semibold text-gray-700 mb-1">
                            Título <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="aa-finance-create-title"
                            name="title"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Ej. Gastos de oficina, Caja chica…"
                            autocomplete="off"
                            required
                        />
                        <p id="aa-finance-title-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                    </div>

                    <div>
                        <label for="aa-finance-create-details" class="block text-xs font-semibold text-gray-700 mb-1">
                            Detalles (opcional)
                        </label>
                        <textarea
                            id="aa-finance-create-details"
                            name="details"
                            rows="3"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Descripción o notas adicionales sobre esta lista…"
                        ></textarea>
                        <p id="aa-finance-details-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                    </div>
                </div>

                <!-- Botonera estándar (Normal / Rechazo corregible) -->
                <div id="aa-finance-modal-actions-standard" class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-finance-modal-cancel-btn"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        id="aa-finance-modal-submit-btn"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Crear lista
                    </button>
                </div>

                <!-- Botonera de Estado Incierto ("Cerrar y revisar") -->
                <div id="aa-finance-modal-actions-uncertain" class="hidden mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-finance-modal-uncertain-close-btn"
                        class="px-4 py-2 text-xs font-semibold text-white bg-gray-800 rounded-lg hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-600"
                    >
                        Cerrar y revisar
                    </button>
                </div>

                <!-- Botonera de Rechazo Bloqueante ("Cerrar") -->
                <div id="aa-finance-modal-actions-blocked" class="hidden mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-finance-modal-blocked-close-btn"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        Cerrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.AA_FINANCE_DATA = <?php echo wp_json_encode($finance_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo esc_url($finance_records_js . '?ver=' . rawurlencode($finance_module_ver)); ?>" defer></script>
<script src="<?php echo esc_url($finance_record_create_js . '?ver=' . rawurlencode($finance_module_ver)); ?>" defer></script>
<script src="<?php echo esc_url($finance_module_js . '?ver=' . rawurlencode($finance_module_ver)); ?>" defer></script>
