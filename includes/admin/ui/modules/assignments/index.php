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

// Resolver rutas de scripts y versión (cache-busting unificado)
$base_dir = __DIR__;
$plugin_url = plugin_dir_url(__FILE__);
$ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$module_js_url = $plugin_url . 'assignments-module.js?ver=' . rawurlencode($ver);
?>

<div class="max-w-5xl mx-auto py-2">
    
    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Asignaciones
    ═══════════════════════════════════════════════════════════════ -->
    <details id="aa-assignments-section" class="hidden bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden group">
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center">
                    <span class="flex items-center justify-center w-8 h-8 text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-600">Asignaciones</h3>
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
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg border border-gray-300 transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    <details class="aa-module-section-card bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden group" data-aa-section="areas">
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none" title="Áreas donde se realizan las citas.">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center min-w-0">
                    <span class="flex items-center justify-center w-8 h-8 text-gray-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-600">Zonas de atención</h3>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <div class="relative aa-module-section-options shrink-0">
                        <button type="button"
                            data-aa-section-options-trigger="1"
                            data-section-id="areas"
                            onclick="event.stopPropagation()"
                            title="Opciones de sección"
                            aria-label="Opciones de sección"
                            aria-haspopup="menu"
                            aria-expanded="false"
                            class="aa-module-section-options-trigger aa-options-trigger-flat">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.75"/>
                                <circle cx="12" cy="12" r="1.75"/>
                                <circle cx="19" cy="12" r="1.75"/>
                            </svg>
                        </button>
                        <div class="hidden aa-module-section-options-menu absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                            role="menu"
                            data-section-id="areas">
                            <button type="button" role="menuitem"
                                data-aa-section-action="new"
                                data-section-id="areas"
                                onclick="event.stopPropagation()"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                                + Nuevo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </summary>
        
        <div class="p-6 transition-all duration-200">
            <!-- Root container for areas section JS -->
            <div id="aa-areas-root"></div>
        </div>
    </details>

    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Personal
    ═══════════════════════════════════════════════════════════════ -->
    <details class="aa-module-section-card bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden group" data-aa-section="staff">
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none" title="Personal que atiende las citas.">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center min-w-0">
                    <span class="flex items-center justify-center w-8 h-8 text-gray-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-600">Personal</h3>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <div class="relative aa-module-section-options shrink-0">
                        <button type="button"
                            data-aa-section-options-trigger="1"
                            data-section-id="staff"
                            onclick="event.stopPropagation()"
                            title="Opciones de sección"
                            aria-label="Opciones de sección"
                            aria-haspopup="menu"
                            aria-expanded="false"
                            class="aa-module-section-options-trigger aa-options-trigger-flat">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.75"/>
                                <circle cx="12" cy="12" r="1.75"/>
                                <circle cx="19" cy="12" r="1.75"/>
                            </svg>
                        </button>
                        <div class="hidden aa-module-section-options-menu absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                            role="menu"
                            data-section-id="staff">
                            <button type="button" role="menuitem"
                                data-aa-section-action="new"
                                data-section-id="staff"
                                onclick="event.stopPropagation()"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                                + Nuevo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </summary>
        
        <div class="p-6 transition-all duration-200">
            <!-- Root container for staff section JS -->
            <div id="aa-staff-root"></div>
        </div>
    </details>

    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Servicios
    ═══════════════════════════════════════════════════════════════ -->
    <details class="aa-module-section-card bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden group" data-aa-section="services">
        <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none" title="Servicios que los clientes pueden reservar.">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center min-w-0">
                    <span class="flex items-center justify-center w-8 h-8 text-gray-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-600">Servicios</h3>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <div class="relative aa-module-section-options shrink-0">
                        <button type="button"
                            data-aa-section-options-trigger="1"
                            data-section-id="services"
                            onclick="event.stopPropagation()"
                            title="Opciones de sección"
                            aria-label="Opciones de sección"
                            aria-haspopup="menu"
                            aria-expanded="false"
                            class="aa-module-section-options-trigger aa-options-trigger-flat">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.75"/>
                                <circle cx="12" cy="12" r="1.75"/>
                                <circle cx="19" cy="12" r="1.75"/>
                            </svg>
                        </button>
                        <div class="hidden aa-module-section-options-menu absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                            role="menu"
                            data-section-id="services">
                            <button type="button" role="menuitem"
                                data-aa-section-action="new"
                                data-section-id="services"
                                onclick="event.stopPropagation()"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                                + Nuevo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </summary>
        
        <div class="p-6 transition-all duration-200">
            <!-- Root container for services section JS -->
            <div id="aa-services-root"></div>
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
        ajaxurl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
        defaultAttendanceType: <?php echo (int) get_option('aa_is_virtual', 0) === 1 ? "'virtual'" : "'physical'"; ?>
    };
</script>

<!-- Options menu placement (shared) + section options -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/modules/learning/executable-options-menu-placement.js?ver=' . rawurlencode($ver)); ?>" defer></script>
<script src="<?php echo esc_url($plugin_url . 'section-options-module.js?ver=' . rawurlencode($ver)); ?>" defer></script>

<!-- Módulo JS -->
<script src="<?php echo esc_url($module_js_url); ?>" defer></script>

<!-- Areas Section JS -->
<script src="<?php echo esc_url($plugin_url . 'areas-section/areas.js?ver=' . rawurlencode($ver)); ?>" defer></script>

<!-- Staff Section JS -->
<script src="<?php echo esc_url($plugin_url . 'staff-section/staff.js?ver=' . rawurlencode($ver)); ?>" defer></script>

<!-- Services Section JS -->
<script src="<?php echo esc_url($plugin_url . 'services-section/servicesSection.js?ver=' . rawurlencode($ver)); ?>" defer></script>

<!-- Assignments Section JS -->
<script src="<?php echo esc_url($plugin_url . 'assignments-section/assignments-section.js?ver=' . rawurlencode($ver)); ?>" defer></script>

<!-- Assignment Modal Template and JS are loaded in layout.php -->
<!-- No need to load them here as they are transversal modals -->

