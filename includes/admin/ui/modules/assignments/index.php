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
$plugin_url = plugin_dir_url(__FILE__);
$module_js_url = $plugin_url . 'assignments-module.js';
?>

<div class="max-w-5xl mx-auto py-2">
    
    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Asignaciones
    ═══════════════════════════════════════════════════════════════ -->
    <details class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden group" open>
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
            <!-- Root container for JS module -->
            <div id="aa-assignments-root"></div>
            <p class="text-sm text-gray-500">Aquí se configurarán las asignaciones de personal, zonas y servicios.</p>
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
            <p class="text-sm text-gray-500">Aquí se configurarán las zonas de atención.</p>
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
            <p class="text-sm text-gray-500">Aquí se configurará el personal.</p>
        </div>
    </details>

</div>

<!-- Datos iniciales del módulo -->
<script>
    // Garantizar ajaxurl global para el iframe
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    
    // Datos placeholder del módulo Assignments
    window.AA_ASSIGNMENTS_DATA = {
        // Placeholder - se llenará cuando se implementen los endpoints
    };
</script>

<!-- Módulo JS -->
<script src="<?php echo esc_url($module_js_url); ?>" defer></script>

