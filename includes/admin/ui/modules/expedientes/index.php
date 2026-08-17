<?php
/**
 * Expedientes Module — parent-entity list (Cycle E).
 *
 * Search / pagination / cards via ExpedientesAjax. Create via FAB + modal.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$aa_expedientes_module_url = admin_url('admin-post.php?action=aa_iframe_content&module=expedientes');
$aa_expedientes_ajax_url = admin_url('admin-ajax.php');
$aa_expedientes_nonce = wp_create_nonce(
    class_exists('ExpedientesAjax') ? ExpedientesAjax::NONCE_ACTION : 'aa_expedientes_nonce'
);
$aa_expedientes_list_action = class_exists('ExpedientesAjax')
    ? ExpedientesAjax::ACTION_LIST
    : 'aa_list_expedientes';
$aa_expedientes_create_action = class_exists('ExpedientesAjax')
    ? ExpedientesAjax::ACTION_CREATE
    : 'aa_create_expediente';
?>

<div
    id="aa-expedientes-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="Expedientes"
>
    <div id="aa-expedientes-list-root">
        <div id="aa-expedientes-action-bar" class="aa-expedientes-action-bar">
            <input
                type="search"
                id="aa-expedientes-search"
                class="aa-expedientes-search-input"
                placeholder="Buscar expediente por nombre"
                autocomplete="off"
                enterkeyhint="search"
            >
            <div
                id="aa-expedientes-pagination"
                class="aa-expedientes-pagination hidden"
                hidden
            >
                <button
                    type="button"
                    id="aa-expedientes-prev"
                    class="aa-expedientes-pagination-button"
                    disabled
                    aria-label="Página anterior"
                >←</button>
                <button
                    type="button"
                    id="aa-expedientes-next"
                    class="aa-expedientes-pagination-button"
                    disabled
                    aria-label="Página siguiente"
                >→</button>
            </div>
        </div>
        <div
            id="aa-expedientes-status"
            class="aa-expedientes-status"
            aria-live="polite"
        ></div>
        <div
            id="aa-expedientes-grid"
            class="aa-expedientes-grid"
            aria-live="polite"
        ></div>
    </div>
</div>

<div id="aa-expedientes-fab-stack" class="aa-expedientes-fab-stack fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <button
        type="button"
        id="aa-expedientes-new-expediente"
        class="aa-expedientes-fab inline-flex items-center gap-2 px-4 py-3 text-base font-bold text-white bg-violet-600 hover:bg-violet-700 active:bg-violet-800 rounded-full shadow-lg shadow-violet-600/30 hover:shadow-xl hover:shadow-violet-600/35 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-violet-500/40"
        aria-label="Nuevo expediente"
        data-expedientes-tool="create-expediente"
    >
        <span>Nuevo expediente</span>
    </button>
</div>

<script>
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = <?php echo wp_json_encode($aa_expedientes_ajax_url); ?>;
    }

    window.AA_EXPEDIENTES_DATA = {
        ajaxUrl: window.ajaxurl || <?php echo wp_json_encode($aa_expedientes_ajax_url); ?>,
        nonce: <?php echo wp_json_encode($aa_expedientes_nonce); ?>,
        moduleBaseUrl: <?php echo wp_json_encode($aa_expedientes_module_url); ?>,
        actions: {
            list: <?php echo wp_json_encode($aa_expedientes_list_action); ?>,
            create: <?php echo wp_json_encode($aa_expedientes_create_action); ?>
        }
    };
</script>
<?php
$expedientes_create_modal_js = plugin_dir_url(__FILE__) . 'expediente-create-modal.js';
$expedientes_module_js = plugin_dir_url(__FILE__) . 'expedientes-module.js';
$expedientes_module_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
?>
<script src="<?php echo esc_url($expedientes_create_modal_js . '?ver=' . rawurlencode($expedientes_module_ver)); ?>" defer></script>
<script src="<?php echo esc_url($expedientes_module_js . '?ver=' . rawurlencode($expedientes_module_ver)); ?>" defer></script>
