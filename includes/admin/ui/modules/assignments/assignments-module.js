/**
 * Assignments Module - JavaScript
 * 
 * Renders the assignments management UI.
 * No business logic - UI scaffold only.
 */

(function() {
    'use strict';

    /**
     * Initialize the assignments module
     */
    function initAssignmentsModule() {
        const root = document.getElementById('aa-assignments-root');
        
        // Fail safely if root doesn't exist
        if (!root) {
            console.warn('[Assignments Module] Root element #aa-assignments-root not found');
            return;
        }

        // Render the coming soon UI
        renderComingSoonUI(root);
    }

    /**
     * Render the "Coming Soon" placeholder UI with feature cards
     * @param {HTMLElement} root - The root container element
     */
    function renderComingSoonUI(root) {
        const html = `
            <div class="aa-assignments-coming-soon">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-900 mb-1">Próximamente</h4>
                    <p class="text-sm text-gray-500">Este módulo te permitirá gestionar las siguientes áreas:</p>
                </div>

                <!-- Feature Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    
                    <!-- Card: Personal -->
                    <div class="aa-feature-card bg-gray-50 border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:bg-indigo-50 transition-colors">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </span>
                            <h5 class="font-medium text-gray-900">Personal</h5>
                        </div>
                        <p class="text-xs text-gray-500">Gestiona empleados, horarios y disponibilidad del equipo.</p>
                    </div>

                    <!-- Card: Zonas de atención -->
                    <div class="aa-feature-card bg-gray-50 border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:bg-indigo-50 transition-colors">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 text-green-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </span>
                            <h5 class="font-medium text-gray-900">Zonas de atención</h5>
                        </div>
                        <p class="text-xs text-gray-500">Define áreas, sucursales o puntos de servicio disponibles.</p>
                    </div>

                    <!-- Card: Asignaciones -->
                    <div class="aa-feature-card bg-gray-50 border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:bg-indigo-50 transition-colors">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 text-purple-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </span>
                            <h5 class="font-medium text-gray-900">Asignaciones</h5>
                        </div>
                        <p class="text-xs text-gray-500">Vincula personal con zonas y servicios específicos.</p>
                    </div>

                </div>
            </div>
        `;

        root.innerHTML = html;
    }

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAssignmentsModule);
    } else {
        // DOM already ready
        initAssignmentsModule();
    }

})();

