<?php
/**
 * Assignments Module - Assignments Management UI
 * 
 * This module handles:
 * - Display of assignments overview
 * - UI for managing staff, zones, services, and assignments
 * - No business logic (data operations handled via AJAX)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Resolver rutas de scripts
$base_dir = __DIR__;
$plugin_url = plugin_dir_url(__FILE__);

// Cache-busting: usar filemtime para versionar scripts
$module_js_url = $plugin_url . 'assignments-module.js?v=' . filemtime($base_dir . '/assignments-module.js');
?>

<div class="max-w-5xl mx-auto py-2">
    
    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Asignaciones
    ═══════════════════════════════════════════════════════════════ -->
    <details class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden group">
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Asignaciones</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Gestiona personal, zonas de atención, servicios y asignaciones.</p>
                    </div>
                </div>
                <svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </summary>
        
        <div class="p-6 transition-all duration-200">
            <!-- Botón para abrir modal de nueva asignación -->
            <div class="mb-4">
                <button type="button" 
                        id="aa-open-assignment-modal" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Abrir horario
                </button>
            </div>
            
            <!-- Root container for assignments section JS -->
            <div id="aa-assignments-root"></div>
        </div>
    </details>

    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Zonas de atención
    ═══════════════════════════════════════════════════════════════ -->
    <details class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden group">
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-green-100 text-green-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Zonas de atención</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Define áreas, consultorios o zonas donde se prestan servicios.</p>
                    </div>
                </div>
                <svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </summary>
        
        <div class="p-6 transition-all duration-200">
            <!-- Root container for areas section JS -->
            <div id="aa-areas-root"></div>
            
            <!-- Form to add new service area -->
            <div class="flex gap-2 mt-4">
                <input type="text" 
                       id="aa-area-name-input" 
                       placeholder="Ej: Consultorio 3" 
                       class="flex-1 px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow placeholder:text-gray-400">
                <button type="button" 
                        id="aa-add-area" 
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 transition-colors shadow-sm">
                    Agregar
                </button>
            </div>
        </div>
    </details>

    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Personal
    ═══════════════════════════════════════════════════════════════ -->
    <details class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden group">
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Personal</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Gestiona el personal que atiende clientes y sus capacidades.</p>
                    </div>
                </div>
                <svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </summary>
        
        <div class="p-6 transition-all duration-200">
            <!-- Root container for staff section JS -->
            <div id="aa-staff-root"></div>
            
            <!-- Form to add new staff -->
            <div class="flex gap-2 mt-4">
                <input type="text" 
                       id="aa-staff-name-input" 
                       placeholder="Ej: Juan Pérez" 
                       class="flex-1 px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow placeholder:text-gray-400">
                <button type="button" 
                        id="aa-add-staff" 
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 transition-colors shadow-sm">
                    Agregar
                </button>
            </div>
        </div>
    </details>

    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Servicios
    ═══════════════════════════════════════════════════════════════ -->
    <details class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden group">
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-100 text-purple-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Servicios</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Gestiona los servicios disponibles para asignar al personal.</p>
                    </div>
                </div>
                <svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </summary>
        
        <div class="p-6 transition-all duration-200">
            <!-- Root container for services section JS -->
            <div id="aa-services-root"></div>
            
            <!-- Form to add new service -->
            <div class="flex gap-2 mt-4">
                <input type="text" 
                       id="aa-service-name-input" 
                       placeholder="Ej: Consulta médica" 
                       class="flex-1 px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow placeholder:text-gray-400">
                <button type="button" 
                        id="aa-add-service" 
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 transition-colors shadow-sm">
                    Agregar
                </button>
            </div>
        </div>
    </details>

</div>

<!-- Datos iniciales del módulo -->
<script>
    // Garantizar ajaxurl global para el iframe
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    
    // Datos del módulo Assignments
    window.AA_ASSIGNMENTS_DATA = {
        ajaxurl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>'
    };
</script>

<!-- Módulo JS -->
<script src="<?php echo esc_url($module_js_url); ?>" defer></script>

<!-- Areas Section JS -->
<script src="<?php echo esc_url($plugin_url . 'areas-section/areas.js?v=' . filemtime($base_dir . '/areas-section/areas.js')); ?>" defer></script>

<!-- Staff Section JS -->
<script src="<?php echo esc_url($plugin_url . 'staff-section/staff.js?v=' . filemtime($base_dir . '/staff-section/staff.js')); ?>" defer></script>

<!-- Services Section JS -->
<script src="<?php echo esc_url($plugin_url . 'services-section/servicesSection.js?v=' . filemtime($base_dir . '/services-section/servicesSection.js')); ?>" defer></script>

<!-- Assignments Section JS -->
<script src="<?php echo esc_url($plugin_url . 'assignments-section/assignments-section.js?v=' . filemtime($base_dir . '/assignments-section/assignments-section.js')); ?>" defer></script>

<!-- Assignment Modal Template and JS are loaded in layout.php -->
<!-- No need to load them here as they are transversal modals -->

