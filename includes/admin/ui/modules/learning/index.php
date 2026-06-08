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
$executable_lists_service_js = AA_PLUGIN_URL . 'assets/js/services/executableListsService.js';
$executable_lists_renderer_js = AA_PLUGIN_URL . 'assets/js/ui/executableListRenderer.js';
$executable_lists_shadow_js = plugin_dir_url(__FILE__) . 'executable-lists-shadow-module.js';
$executable_lists_module_js = plugin_dir_url(__FILE__) . 'executable-lists-module.js';
$executable_actions_coordinator_js = plugin_dir_url(__FILE__) . 'executable-actions-coordinator.js';
$lists_area_tools_js = plugin_dir_url(__FILE__) . 'lists-area-tools.js';
?>

<div id="aa-tasks-module-root" class="max-w-5xl mx-auto py-2">

    <div class="mb-4">
        <h2 class="text-xl font-bold text-gray-900">Listas / Tareas</h2>
        <p class="text-sm text-gray-500 mt-0.5">Tareas organizadas inteligentemente para lograr tus ojetivos con eficacia.</p>
    </div>

    <section id="aa-executive-proposal" class="bg-white rounded-xl shadow border border-gray-200 mb-4 overflow-hidden">
        <div class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-white">
            <h3 class="text-lg font-semibold text-gray-900">Propuesta ejecutiva</h3>
            <p class="text-sm text-gray-500 mt-0.5">Acciones recomendadas ahora.</p>
        </div>
        <div class="p-4">
            <p id="aa-executive-empty" class="hidden text-sm text-gray-500">
                No hay acciones pendientes recomendadas. Crea tareas o revisa tus listas.
            </p>
            <ul id="aa-executive-list" class="space-y-3"></ul>
        </div>
    </section>

    <section id="aa-lists-section" class="pb-24">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-gray-900">Listas</h3>
                <p class="text-sm text-gray-500 mt-0.5">Todas las listas de tareas.</p>
            </div>
            <div id="aa-lists-area-tools" class="flex items-center gap-2 shrink-0">
                <button type="button"
                    data-lists-tool="restore-archived"
                    title="Restaurar listas archivadas"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-300/60 transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0014-7.5M19 5a9 9 0 00-14 7.5"/>
                    </svg>
                    <span>Restaurar</span>
                </button>
            </div>
        </div>

        <p id="aa-tasks-error" class="hidden text-sm text-red-600 mb-3"></p>

        <div id="aa-lists-feed" class="space-y-4">
            <section id="aa-executable-lists-active" class="hidden space-y-4">
                <p id="aa-executable-lists-active-error" class="hidden text-sm text-red-600"></p>
                <p id="aa-executable-lists-active-loading" class="hidden text-sm text-gray-500">Cargando listas…</p>
                <div id="aa-executable-lists-active-root" class="space-y-4"></div>
            </section>

            <article id="aa-learning-recommendations" class="aa-task-list-card aa-system-list-card bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div class="px-4 py-4 border-b border-gray-100 bg-gradient-to-r from-amber-50 to-white">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-600 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h4 class="text-base font-semibold text-gray-900">Recomendaciones</h4>
                            <p class="text-sm text-gray-500 mt-0.5">Sugerencias para configurar y usar tu agenda.</p>
                        </div>
                    </div>
                </div>

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
            </article>

            <section id="aa-executable-user-lists-visible" class="hidden mb-4 border border-dashed border-violet-300 rounded-xl overflow-hidden bg-white">
                <div id="aa-executable-user-lists-visible-header" class="px-4 py-3 border-b border-violet-200 bg-violet-50">
                    <h4 id="aa-executable-user-lists-visible-title" class="text-sm font-semibold text-violet-900">Listas de usuario (executable visible)</h4>
                    <p id="aa-executable-user-lists-visible-subtitle" class="text-xs text-violet-800 mt-0.5">Comparación MC13A: feed user filtrado; legacy sigue visible abajo.</p>
                </div>
                <p id="aa-executable-user-lists-error" class="hidden text-sm text-red-600 px-4 pt-3"></p>
                <p id="aa-executable-user-lists-loading" class="hidden text-sm text-gray-500 px-4 pt-3">Cargando listas…</p>
                <div id="aa-executable-user-lists-root" class="p-4 space-y-4"></div>
            </section>

            <div id="aa-tasks-board-root">
                <p id="aa-tasks-loading" class="text-sm text-gray-500">Cargando listas y tareas…</p>
                <p id="aa-tasks-empty" class="hidden text-sm text-gray-500 bg-white rounded-xl border border-dashed border-gray-200 px-4 py-3">
                    Aún no tienes listas propias. Usa el botón flotante para crear una.
                </p>
                <div id="aa-tasks-lists-root" class="hidden space-y-4"></div>
            </div>
        </div>

        <section id="aa-executable-lists-experimental" class="hidden mt-6 border border-dashed border-amber-300 rounded-xl overflow-hidden bg-white">
            <div class="px-4 py-3 border-b border-amber-200 bg-amber-50">
                <h4 class="text-sm font-semibold text-amber-900">Feed executable experimental</h4>
                <p id="aa-executable-lists-mode" class="text-xs text-amber-800 mt-0.5">Modo preview: acciones desactivadas.</p>
            </div>
            <p id="aa-executable-lists-error" class="hidden text-sm text-red-600 px-4 pt-3"></p>
            <div id="aa-executable-lists-root" inert class="pointer-events-none p-4 space-y-4"></div>
        </section>
    </section>

</div>

<div id="aa-tasks-fab-stack" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <button type="button" id="aa-tasks-new-list"
        class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 active:bg-gray-100 rounded-full shadow-lg shadow-gray-900/10 hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-gray-300/40"
        aria-label="Nueva lista">
        <span>+ Nueva lista</span>
    </button>
    <button type="button" id="aa-tasks-new-task"
        class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 active:bg-violet-800 rounded-full shadow-lg shadow-violet-600/30 hover:shadow-xl hover:shadow-violet-600/35 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-violet-500/40"
        aria-label="Nueva tarea">
        <span>+ Nueva tarea</span>
    </button>
</div>

<!-- Modal: restaurar listas archivadas -->
<div id="aa-restore-archived-lists-modal" class="fixed inset-0 z-[300] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-aa-tasks-modal-close="aa-restore-archived-lists-modal"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h4 class="text-lg font-semibold text-gray-900">Restaurar listas archivadas</h4>
                <p class="text-sm text-gray-500 mt-1">Elige una lista archivada para volver a mostrarla en tus listas activas.</p>
            </div>
            <div class="px-5 py-4 space-y-4">
                <p id="aa-restore-archived-lists-loading" class="hidden text-sm text-gray-500">Cargando listas archivadas…</p>
                <p id="aa-restore-archived-lists-empty" class="hidden text-sm text-gray-500">No hay listas archivadas para restaurar.</p>
                <div id="aa-restore-archived-lists-select-wrap">
                    <label for="aa-restore-archived-lists-select" class="block text-sm font-medium text-gray-700 mb-1">Lista archivada</label>
                    <select id="aa-restore-archived-lists-select"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        disabled>
                        <option value="">Selecciona una lista</option>
                    </select>
                </div>
                <p id="aa-restore-archived-lists-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-restore-archived-lists-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="button" id="aa-restore-archived-lists-submit" disabled
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Restaurar</button>
                </div>
            </div>
        </div>
    </div>
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

    window.AA_EXECUTABLE_LISTS_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
        action: 'aa_get_executable_lists_feed',
        nonce: '<?php echo esc_js(wp_create_nonce('aa_executable_lists_nonce')); ?>',
        visibleFeed: 'unified'
    };
</script>

<script src="<?php echo esc_url($learning_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_handlers_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_renderer_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_renderer_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_board_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_lists_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_lists_renderer_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_lists_shadow_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_actions_coordinator_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_lists_module_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($lists_area_tools_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
