<?php
/**
 * Learning Module - Guías / Aprendizaje UI
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$learning_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$learning_js  = plugin_dir_url(__FILE__) . 'learning-module.js';
$learning_handlers_js = plugin_dir_url(__FILE__) . 'learning-action-handlers.js';
$learning_service_js = AA_PLUGIN_URL . 'assets/js/services/learningService.js';
?>

<div class="max-w-5xl mx-auto py-2">

    <div class="mb-4">
        <h2 class="text-xl font-bold text-gray-900">Listas / Tareas</h2>
        <p class="text-sm text-gray-500 mt-0.5">Tareas organizadas inteligentemente para lograr tus ojetivos con eficacia.</p>
    </div>

    <details id="aa-learning-recommendations" class="bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden group" open>
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Recomendaciones</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Sugerencias para configurar y usar tu agenda.</p>
                    </div>
                </div>
                <svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </summary>

        <div id="aa-learning-recommendations-root" class="p-4 transition-all duration-200">
            <p id="aa-learning-loading" class="text-sm text-gray-500">Cargando recomendaciones…</p>
            <p id="aa-learning-error" class="hidden text-sm text-red-600"></p>
            <p id="aa-learning-empty" class="hidden text-sm text-gray-500">No hay recomendaciones activas en este momento.</p>

            <div id="aa-learning-list-primary-wrap" class="hidden space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Principales</p>
                <ul id="aa-learning-list-primary" class="space-y-3"></ul>
            </div>

            <div id="aa-learning-list-secondary-wrap" class="hidden mt-5 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Otras sugerencias</p>
                <ul id="aa-learning-list-secondary" class="space-y-3"></ul>
            </div>
        </div>
    </details>

</div>

<script>
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }

    window.AA_LEARNING_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
        action: 'aa_get_learning_recommendations',
        nonce: '<?php echo esc_js(wp_create_nonce('aa_get_learning_recommendations_nonce')); ?>'
    };
</script>

<script src="<?php echo esc_url($learning_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_handlers_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
