<?php
/**
 * Learning Module - Guías / Aprendizaje UI
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$learning_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$learning_js  = plugin_dir_url(__FILE__) . 'learning-module.js';
$learning_handlers_js = plugin_dir_url(__FILE__) . 'learning-action-handlers.js';
$learning_service_js = AA_PLUGIN_URL . 'assets/js/services/learningService.js';
$learning_renderer_js = AA_PLUGIN_URL . 'assets/js/ui/learningRecommendationRenderer.js';
$tasks_service_js = AA_PLUGIN_URL . 'assets/js/services/tasksService.js';
$tasks_renderer_js = AA_PLUGIN_URL . 'assets/js/ui/taskBoardRenderer.js';
$tasks_board_js = plugin_dir_url(__FILE__) . 'tasks-board-module.js';
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

    <section id="aa-tasks-board" class="mt-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Mis listas</h3>
                <p class="text-sm text-gray-500 mt-0.5">Organiza tareas manuales con un objetivo común.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="aa-tasks-new-list"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Nueva lista
                </button>
                <button type="button" id="aa-tasks-new-task"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100">
                    Nueva tarea
                </button>
            </div>
        </div>

        <div id="aa-tasks-board-root" class="bg-white rounded-xl shadow border border-gray-200 p-4">
            <p id="aa-tasks-loading" class="text-sm text-gray-500">Cargando listas y tareas…</p>
            <p id="aa-tasks-error" class="hidden text-sm text-red-600 mb-3"></p>
            <p id="aa-tasks-empty" class="hidden text-sm text-gray-500">
                Crea tu primera lista para organizar tareas con un objetivo común.
            </p>
            <div id="aa-tasks-lists-root" class="hidden space-y-4"></div>
        </div>
    </section>

</div>

<!-- Modal: nueva lista -->
<div id="aa-task-list-modal" class="fixed inset-0 z-[300] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-aa-tasks-modal-close="aa-task-list-modal"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h4 class="text-lg font-semibold text-gray-900">Nueva lista</h4>
                <p class="text-sm text-gray-500 mt-1">Define un objetivo común para agrupar tareas.</p>
            </div>
            <form id="aa-task-list-form" class="px-5 py-4 space-y-4">
                <div>
                    <label for="aa-task-list-form-title" class="block text-sm font-medium text-gray-700 mb-1">Nombre de la lista</label>
                    <input type="text" id="aa-task-list-form-title" name="title" required maxlength="255"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ej. Pendientes de clientes">
                </div>
                <div>
                    <label for="aa-task-list-form-description" class="block text-sm font-medium text-gray-700 mb-1">Objetivo común de estas tareas</label>
                    <textarea id="aa-task-list-form-description" name="description" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ej. Resolver pendientes de clientes con servicios vigentes"></textarea>
                </div>
                <div>
                    <label for="aa-task-list-form-importance" class="block text-sm font-medium text-gray-700 mb-1">Importancia (opcional)</label>
                    <input type="number" id="aa-task-list-form-importance" name="importance" value="0"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Valores más bajos suelen aparecer primero.</p>
                </div>
                <p id="aa-task-list-form-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-task-list-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-blue-200 bg-blue-600 text-white hover:bg-blue-700">Crear lista</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: nueva tarea -->
<div id="aa-task-modal" class="fixed inset-0 z-[300] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-aa-tasks-modal-close="aa-task-modal"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h4 class="text-lg font-semibold text-gray-900">Nueva tarea</h4>
            </div>
            <form id="aa-task-form" class="px-5 py-4 space-y-4">
                <div>
                    <label for="aa-task-form-list-id" class="block text-sm font-medium text-gray-700 mb-1">Lista</label>
                    <select id="aa-task-form-list-id" name="list_id" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">No hay listas disponibles</option>
                    </select>
                </div>
                <div>
                    <label for="aa-task-form-title" class="block text-sm font-medium text-gray-700 mb-1">Tarea</label>
                    <input type="text" id="aa-task-form-title" name="title" required maxlength="255"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Qué necesitas hacer">
                </div>
                <div>
                    <label for="aa-task-form-notes" class="block text-sm font-medium text-gray-700 mb-1">Detalles o contexto</label>
                    <textarea id="aa-task-form-notes" name="notes" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Notas opcionales"></textarea>
                </div>
                <div>
                    <label for="aa-task-form-due-at" class="block text-sm font-medium text-gray-700 mb-1">Vencimiento (opcional)</label>
                    <input type="datetime-local" id="aa-task-form-due-at" name="due_at"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="aa-task-form-importance" class="block text-sm font-medium text-gray-700 mb-1">Importancia (opcional)</label>
                    <input type="number" id="aa-task-form-importance" name="importance" value="0"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <p id="aa-task-form-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-task-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-blue-200 bg-blue-600 text-white hover:bg-blue-700">Crear tarea</button>
                </div>
            </form>
        </div>
    </div>
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

    window.AA_TASKS_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo esc_js(wp_create_nonce('aa_tasks_nonce')); ?>'
    };
</script>

<script src="<?php echo esc_url($learning_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_handlers_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_renderer_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_renderer_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_board_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
