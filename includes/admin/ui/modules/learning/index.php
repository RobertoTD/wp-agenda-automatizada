<?php
/**
 * Learning Module - Guías / Aprendizaje UI
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$learning_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$learning_handlers_js = plugin_dir_url(__FILE__) . 'learning-action-handlers.js';
$learning_service_js = AA_PLUGIN_URL . 'assets/js/services/learningService.js';
$tasks_service_js = AA_PLUGIN_URL . 'assets/js/services/tasksService.js';
$tasks_renderer_js = AA_PLUGIN_URL . 'assets/js/ui/taskBoardRenderer.js';
$task_completed_toast_js = AA_PLUGIN_URL . 'assets/js/ui/taskCompletedToast.js';
$tasks_board_js = plugin_dir_url(__FILE__) . 'tasks-board-module.js';
$executable_lists_service_js = AA_PLUGIN_URL . 'assets/js/services/executableListsService.js';
$executable_lists_renderer_js = AA_PLUGIN_URL . 'assets/js/ui/executableListRenderer.js';
$executable_lists_module_js = plugin_dir_url(__FILE__) . 'executable-lists-module.js';
$executable_actions_coordinator_js = plugin_dir_url(__FILE__) . 'executable-actions-coordinator.js';
$lists_area_tools_js = plugin_dir_url(__FILE__) . 'lists-area-tools.js';
$task_edit_js = plugin_dir_url(__FILE__) . 'task-edit-module.js';
$task_options_js = plugin_dir_url(__FILE__) . 'task-options-module.js';
$list_options_js = plugin_dir_url(__FILE__) . 'list-options-module.js';
$list_card_longpress_js = plugin_dir_url(__FILE__) . 'list-card-longpress-module.js';
$executable_options_menu_placement_js = plugin_dir_url(__FILE__) . 'executable-options-menu-placement.js';
$list_edit_js = plugin_dir_url(__FILE__) . 'list-edit-module.js';
$restore_archived_tasks_js = plugin_dir_url(__FILE__) . 'restore-archived-tasks-module.js';
$tasks_modal_ui_js = plugin_dir_url(__FILE__) . 'tasks-modal-ui.js';
$section_toggles_js = plugin_dir_url(__FILE__) . 'section-toggles-module.js';
$executive_proposal_service_js = AA_PLUGIN_URL . 'assets/js/services/executiveProposalService.js';
$executive_client_action_runner_js = AA_PLUGIN_URL . 'assets/js/services/executiveClientActionRunner.js';
$executive_proposal_renderer_js = AA_PLUGIN_URL . 'assets/js/ui/executiveProposalRenderer.js';
$executive_proposal_module_js = plugin_dir_url(__FILE__) . 'executive-proposal-module.js';
$pwa_push_activation_service_js = AA_PLUGIN_URL . 'assets/js/services/pwaPushActivationService.js';
$push_activation_reconcile_service_js = AA_PLUGIN_URL . 'assets/js/services/pushActivationReconcileService.js';
?>

<div id="aa-tasks-module-root" class="max-w-5xl mx-auto py-2 pb-24">

    <section id="aa-lists-section">
        <header id="aa-lists-header" class="aa-lists-header mb-3 flex items-start justify-between gap-3">
            <button type="button"
                id="aa-lists-header-toggle"
                class="aa-lists-header-toggle min-w-0 flex flex-1 items-center gap-2 text-left rounded-lg -ml-1 px-1 py-0.5 focus:outline-none"
                aria-expanded="true"
                aria-controls="aa-lists-body">
                <span class="aa-lists-header-label text-lg font-semibold text-gray-600">Listas de tareas</span>
                <svg class="aa-lists-header-chevron w-3.5 h-3.5 shrink-0 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
            <div id="aa-lists-area-tools" class="relative flex items-center gap-2 shrink-0">
                <button type="button"
                    id="aa-lists-options-trigger"
                    data-lists-tool="options-menu"
                    title="Opciones de listas"
                    aria-haspopup="true"
                    aria-expanded="false"
                    class="aa-options-trigger-flat">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="5" cy="12" r="1.75"/>
                        <circle cx="12" cy="12" r="1.75"/>
                        <circle cx="19" cy="12" r="1.75"/>
                    </svg>
                </button>
                <div id="aa-lists-options-menu"
                    class="hidden absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                    role="menu">
                    <button type="button" role="menuitem"
                        data-lists-tool="create-list"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Lista</span>
                    </button>
                    <button type="button" role="menuitem"
                        data-lists-tool="restore-archived"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0014-7.5M19 5a9 9 0 00-14 7.5"/>
                        </svg>
                        <span>Desarchivar listas</span>
                    </button>
                    <button type="button" role="menuitem"
                        data-lists-tool="return-ignored-tasks"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14L4 9m0 0l5-5M4 9h12a4 4 0 014 4v1"/>
                        </svg>
                        <span>Regresar tareas ignoradas</span>
                    </button>
                </div>
            </div>
        </header>

        <div id="aa-lists-body" class="aa-lists-body" aria-hidden="false">
        <p id="aa-lists-area-tools-error" class="hidden text-sm text-red-600 mb-3"></p>
        <p id="aa-tasks-error" class="hidden text-sm text-red-600 mb-3"></p>

        <div id="aa-lists-feed" class="space-y-4">
            <section id="aa-executable-lists-active" class="space-y-4">
                <p id="aa-executable-lists-active-error" class="hidden text-sm text-red-600"></p>
                <p id="aa-executable-lists-active-loading" class="text-sm text-gray-500">Cargando listas…</p>
                <div id="aa-executable-lists-active-root" class="space-y-2 pb-1"></div>
            </section>

            <div id="aa-tasks-board-root" class="hidden">
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
        </div>
    </section>

    <section id="aa-executive-proposal" class="mt-6">
        <header id="aa-executive-section-header" class="aa-executive-section-header mb-3 flex items-start justify-between gap-3">
            <button type="button"
                id="aa-executive-header-toggle"
                class="aa-executive-header-toggle ml-auto inline-flex items-center justify-center w-8 h-8 shrink-0 rounded-lg text-gray-600 hover:bg-gray-100 focus:outline-none"
                aria-expanded="false"
                aria-controls="aa-executive-body"
                aria-label="Ejecutar">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </button>
        </header>

        <div id="aa-executive-body" class="aa-executive-body is-collapsed" aria-hidden="true" inert>
            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div class="aa-executive-header px-4 py-4 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-white">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p id="aa-executive-status" class="text-sm" aria-live="polite"></p>
                        </div>
                        <div id="aa-executive-header-actions" class="aa-executive-header-actions flex flex-wrap items-center justify-end gap-2 shrink-0"></div>
                    </div>
                </div>
                <div class="p-4">
                    <p id="aa-executive-proposal-loading" class="hidden text-sm text-gray-500">Cargando propuesta ejecutiva…</p>
                    <p id="aa-executive-proposal-error" class="hidden text-sm text-red-600"></p>
                    <p id="aa-executive-empty" class="hidden text-sm text-gray-500">
                        No hay acciones pendientes recomendadas. Crea tareas o revisa tus listas.
                    </p>
                    <ul id="aa-executive-list" class="space-y-3"></ul>
                </div>
            </div>
        </div>
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
    <div class="aa-tasks-modal-frame">
        <div class="aa-tasks-modal-panel">
            <div class="px-5 py-4 border-b border-gray-100 shrink-0">
                <h4 class="text-lg font-semibold text-gray-600">Desarchivar listas</h4>
                <p class="text-sm text-gray-500 mt-1">Elige una lista archivada para volver a mostrarla en tus listas activas.</p>
            </div>
            <div class="aa-tasks-modal-scroll space-y-4">
                <p id="aa-restore-archived-lists-loading" class="hidden text-sm text-gray-500">Cargando listas archivadas…</p>
                <p id="aa-restore-archived-lists-empty" class="hidden text-sm text-gray-500">No hay listas archivadas para restaurar.</p>
                <div id="aa-restore-archived-lists-select-wrap">
                    <label for="aa-restore-archived-lists-select" class="block text-sm font-medium text-gray-700 mb-1">Lista archivada</label>
                    <select id="aa-restore-archived-lists-select"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        disabled>
                        <option value="">Selecciona una lista</option>
                    </select>
                </div>
                <p id="aa-restore-archived-lists-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-restore-archived-lists-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="button" id="aa-restore-archived-lists-submit" disabled
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-indigo-200 bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">Desarchivar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: desarchivar tareas archivadas de una lista -->
<div id="aa-restore-archived-tasks-modal" class="fixed inset-0 z-[300] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-aa-tasks-modal-close="aa-restore-archived-tasks-modal"></div>
    <div class="aa-tasks-modal-frame">
        <div class="aa-tasks-modal-panel">
            <div class="px-5 py-4 border-b border-gray-100 shrink-0">
                <h4 class="text-lg font-semibold text-gray-600">Desarchivar tareas</h4>
                <p class="text-sm text-gray-500 mt-1">Elige una tarea archivada de esta lista para volver a mostrarla.</p>
            </div>
            <div class="aa-tasks-modal-scroll space-y-4">
                <input type="hidden" id="aa-restore-archived-tasks-form-list-id" value="">
                <p id="aa-restore-archived-tasks-loading" class="hidden text-sm text-gray-500">Cargando tareas archivadas…</p>
                <p id="aa-restore-archived-tasks-empty" class="hidden text-sm text-gray-500">No hay tareas archivadas en esta lista.</p>
                <div id="aa-restore-archived-tasks-select-wrap">
                    <label for="aa-restore-archived-tasks-select" class="block text-sm font-medium text-gray-700 mb-1">Tarea archivada</label>
                    <select id="aa-restore-archived-tasks-select"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        disabled>
                        <option value="">Selecciona una tarea</option>
                    </select>
                </div>
                <p id="aa-restore-archived-tasks-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-restore-archived-tasks-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="button" id="aa-restore-archived-tasks-submit" disabled
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-indigo-200 bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">Desarchivar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: editar lista -->
<div id="aa-task-list-edit-modal" class="fixed inset-0 z-[300] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-aa-tasks-modal-close="aa-task-list-edit-modal"></div>
    <div class="aa-tasks-modal-frame">
        <div class="aa-tasks-modal-panel">
            <div class="px-5 py-4 border-b border-gray-100 shrink-0">
                <h4 class="text-lg font-semibold text-gray-600">Editar lista</h4>
                <p class="text-sm text-gray-500 mt-1">Actualiza el nombre, objetivo o importancia de tu lista.</p>
            </div>
            <form id="aa-task-list-edit-form" class="aa-tasks-modal-scroll space-y-4">
                <input type="hidden" id="aa-task-list-edit-form-list-id" name="list_id" value="">
                <div>
                    <label for="aa-task-list-edit-form-title" class="block text-sm font-medium text-gray-700 mb-1">Nombre de la lista</label>
                    <input type="text" id="aa-task-list-edit-form-title" name="title" required maxlength="255"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ej. Pendientes de clientes">
                </div>
                <div>
                    <label for="aa-task-list-edit-form-description" class="block text-sm font-medium text-gray-700 mb-1">Objetivo común de estas tareas</label>
                    <textarea id="aa-task-list-edit-form-description" name="description" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ej. Resolver pendientes de clientes con servicios vigentes"></textarea>
                </div>
                <div>
                    <label for="aa-task-list-edit-form-importance" class="block text-sm font-medium text-gray-700 mb-1">Importancia (opcional)</label>
                    <input type="number" id="aa-task-list-edit-form-importance" name="importance" value="0"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Valores más altos aparecen primero.</p>
                </div>
                <p id="aa-task-list-edit-form-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-task-list-edit-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-indigo-200 bg-indigo-600 text-white hover:bg-indigo-700">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: nueva lista -->
<div id="aa-task-list-modal" class="fixed inset-0 z-[300] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-aa-tasks-modal-close="aa-task-list-modal"></div>
    <div class="aa-tasks-modal-frame">
        <div class="aa-tasks-modal-panel">
            <div class="px-5 py-4 border-b border-gray-100 shrink-0">
                <h4 class="text-lg font-semibold text-gray-600">Nueva lista</h4>
                <p class="text-sm text-gray-500 mt-1">Define un objetivo común para agrupar tareas.</p>
            </div>
            <form id="aa-task-list-form" class="aa-tasks-modal-scroll space-y-4">
                <div>
                    <label for="aa-task-list-form-title" class="block text-sm font-medium text-gray-700 mb-1">Nombre de la lista</label>
                    <input type="text" id="aa-task-list-form-title" name="title" required maxlength="255"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ej. Pendientes de clientes">
                </div>
                <div>
                    <label for="aa-task-list-form-description" class="block text-sm font-medium text-gray-700 mb-1">Objetivo común de estas tareas</label>
                    <textarea id="aa-task-list-form-description" name="description" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ej. Resolver pendientes de clientes con servicios vigentes"></textarea>
                </div>
                <div>
                    <label for="aa-task-list-form-importance" class="block text-sm font-medium text-gray-700 mb-1">Importancia (opcional)</label>
                    <input type="number" id="aa-task-list-form-importance" name="importance" value="0"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Valores más altos aparecen primero.</p>
                </div>
                <p id="aa-task-list-form-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-task-list-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-indigo-200 bg-indigo-600 text-white hover:bg-indigo-700">Crear lista</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: nueva tarea -->
<div id="aa-task-modal" class="fixed inset-0 z-[300] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-aa-tasks-modal-close="aa-task-modal"></div>
    <div class="aa-tasks-modal-frame">
        <div class="aa-tasks-modal-panel">
            <div class="px-5 py-4 border-b border-gray-100 shrink-0">
                <h4 class="text-lg font-semibold text-gray-600">Nueva tarea</h4>
            </div>
            <form id="aa-task-form" class="aa-tasks-modal-scroll space-y-4" novalidate>
                <div>
                    <label for="aa-task-form-list-id" class="block text-sm font-medium text-gray-700 mb-1">Lista</label>
                    <select id="aa-task-form-list-id" name="list_id" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">No hay listas disponibles</option>
                    </select>
                </div>
                <div>
                    <label for="aa-task-form-title" class="block text-sm font-medium text-gray-700 mb-1">Tarea</label>
                    <input type="text" id="aa-task-form-title" name="title" required maxlength="255"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Qué necesitas hacer">
                </div>
                <div>
                    <label for="aa-task-form-notes" class="block text-sm font-medium text-gray-700 mb-1">Detalles o contexto</label>
                    <textarea id="aa-task-form-notes" name="notes" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Notas opcionales"></textarea>
                </div>
                <details id="aa-task-form-options" class="rounded-lg border border-gray-200 bg-gray-50/50">
                    <summary class="cursor-pointer select-none px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg">
                        Opciones
                    </summary>
                    <div class="space-y-4 border-t border-gray-200 px-3 py-3">
                        <div>
                            <label for="aa-task-form-execution-available-at" class="block text-sm font-medium text-gray-700 mb-1">Realizar a partir de (opcional)</label>
                            <input type="datetime-local" id="aa-task-form-execution-available-at" name="execution_available_at"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <p class="text-xs text-gray-500 mt-1">La tarea se volverá pertinente para realizarse desde este momento.</p>
                        </div>
                        <div>
                            <label for="aa-task-form-due-at" class="block text-sm font-medium text-gray-700 mb-1">Vencimiento (opcional)</label>
                            <input type="datetime-local" id="aa-task-form-due-at" name="due_at"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        </div>
                        <div>
                            <label for="aa-task-form-importance" class="block text-sm font-medium text-gray-700 mb-1">Importancia (opcional)</label>
                            <input type="number" id="aa-task-form-importance" name="importance" value="0"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <p class="text-xs text-gray-500 mt-1">Valores más altos aparecen primero.</p>
                        </div>
                        <div>
                            <label for="aa-task-form-default-bucket" class="block text-sm font-medium text-gray-700 mb-1">Clasificación</label>
                            <select id="aa-task-form-default-bucket" name="default_bucket"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                                <option value="primary" selected>Principal</option>
                                <option value="secondary">Secundaria</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Elige si esta tarea es indispensable para cumplir el objetivo de la lista, o si es una tarea sugerida, conveniente pero prescindible.</p>
                        </div>
                    </div>
                </details>
                <p id="aa-task-form-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-task-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-indigo-200 bg-indigo-600 text-white hover:bg-indigo-700">Crear tarea</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: editar tarea -->
<div id="aa-task-edit-modal" class="fixed inset-0 z-[300] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-aa-tasks-modal-close="aa-task-edit-modal"></div>
    <div class="aa-tasks-modal-frame">
        <div class="aa-tasks-modal-panel">
            <div class="px-5 py-4 border-b border-gray-100 shrink-0">
                <h4 class="text-lg font-semibold text-gray-600">Editar tarea</h4>
            </div>
            <form id="aa-task-edit-form" class="aa-tasks-modal-scroll space-y-4" novalidate>
                <input type="hidden" id="aa-task-edit-form-task-id" name="task_id" value="">
                <div>
                    <label for="aa-task-edit-form-title" class="block text-sm font-medium text-gray-700 mb-1">Tarea</label>
                    <input type="text" id="aa-task-edit-form-title" name="title" required maxlength="255"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Qué necesitas hacer">
                </div>
                <div>
                    <label for="aa-task-edit-form-notes" class="block text-sm font-medium text-gray-700 mb-1">Detalles o contexto</label>
                    <textarea id="aa-task-edit-form-notes" name="notes" rows="3" maxlength="800"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Notas opcionales"></textarea>
                </div>
                <details id="aa-task-edit-form-options" class="rounded-lg border border-gray-200 bg-gray-50/50">
                    <summary class="cursor-pointer select-none px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg">
                        Opciones
                    </summary>
                    <div class="space-y-4 border-t border-gray-200 px-3 py-3">
                        <div>
                            <label for="aa-task-edit-form-execution-available-at" class="block text-sm font-medium text-gray-700 mb-1">Realizar a partir de (opcional)</label>
                            <input type="datetime-local" id="aa-task-edit-form-execution-available-at" name="execution_available_at"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <p class="text-xs text-gray-500 mt-1">La tarea se volverá pertinente para realizarse desde este momento.</p>
                        </div>
                        <div>
                            <label for="aa-task-edit-form-due-at" class="block text-sm font-medium text-gray-700 mb-1">Vencimiento (opcional)</label>
                            <input type="datetime-local" id="aa-task-edit-form-due-at" name="due_at"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        </div>
                        <div>
                            <label for="aa-task-edit-form-importance" class="block text-sm font-medium text-gray-700 mb-1">Importancia (opcional)</label>
                            <input type="number" id="aa-task-edit-form-importance" name="importance" value="0"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <p class="text-xs text-gray-500 mt-1">Valores más altos aparecen primero.</p>
                        </div>
                        <div>
                            <label for="aa-task-edit-form-default-bucket" class="block text-sm font-medium text-gray-700 mb-1">Clasificación</label>
                            <select id="aa-task-edit-form-default-bucket" name="default_bucket"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                                <option value="primary" selected>Principal</option>
                                <option value="secondary">Secundaria</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Elige si esta tarea es indispensable para cumplir el objetivo de la lista, o si es una tarea sugerida, conveniente pero prescindible.</p>
                        </div>
                    </div>
                </details>
                <p id="aa-task-edit-form-error" class="hidden text-sm text-red-600"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-aa-tasks-modal-close="aa-task-edit-modal"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="px-3 py-2 text-sm font-medium rounded-lg border border-indigo-200 bg-indigo-600 text-white hover:bg-indigo-700">Guardar cambios</button>
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

    window.AA_EXECUTIVE_PROPOSAL_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
        action: 'aa_get_executive_proposal',
        actionPost: 'aa_executive_action',
        focusActionPost: 'aa_executive_focus_action',
        nonce: '<?php echo esc_js(wp_create_nonce('aa_executive_proposal_nonce')); ?>'
    };

    window.AA_PUSH_CONFIG = {
        ajaxUrl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
        registerAction: '<?php echo esc_js(PushSubscriptionAjax::ACTION_REGISTER); ?>',
        configAction: '<?php echo esc_js(PushSubscriptionAjax::ACTION_CONFIG); ?>',
        nonce: '<?php echo esc_js(wp_create_nonce(PushSubscriptionAjax::NONCE_ACTION)); ?>'
    };
</script>

<script src="<?php echo esc_url($learning_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($pwa_push_activation_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($push_activation_reconcile_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_handlers_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_renderer_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($task_completed_toast_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executive_proposal_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executive_proposal_renderer_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executive_client_action_runner_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executive_proposal_module_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_modal_ui_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($tasks_board_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_lists_service_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_lists_renderer_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_options_menu_placement_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($task_options_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($task_edit_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_actions_coordinator_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($executable_lists_module_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($lists_area_tools_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($list_options_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($list_card_longpress_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($restore_archived_tasks_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($list_edit_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
<script src="<?php echo esc_url($section_toggles_js . '?ver=' . rawurlencode($learning_ver)); ?>" defer></script>
