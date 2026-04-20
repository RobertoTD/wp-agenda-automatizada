<?php
/**
 * Calendar Module - Calendar Management UI
 * 
 * This module handles:
 * - Display of calendar view
 * - UI for managing appointments
 * - No business logic (data operations handled outside)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Obtener schedule
$schedule = get_option('aa_schedule', []);

// Obtener datos del horario fijo (staff y servicio)
$fixed_staff_name = get_option('aa_staff_schedule', '');
$fixed_service_name = get_option('aa_service_schedule', '');

// Resolver rutas de scripts y versión (cache-busting unificado)
$plugin_url = plugin_dir_url(__FILE__);
$calendar_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$module_js_url = $plugin_url . 'calendar-module.js?ver=' . rawurlencode($calendar_ver);
?>

<div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden mt-2">

    <!-- =========================
         🔹 HEADER
         ========================= -->
    <div class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
        <div class="flex items-center justify-between gap-3">

            <!-- Izquierda: icono + texto -->
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-600">
                    <!-- Icono calendario -->
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>

                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Calendario
                    </h3>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Agenda del día y próximas citas
                    </p>
                </div>
            </div>

        </div>
    </div>

    <!-- =========================
         🔹 CONTENIDO / BODY
         ========================= -->
    <div class="p-0 transition-all duration-200">

        <!-- =========================
             🔹 TOOLBAR DE CALENDARIO
             ========================= -->
        <div id="aa-date-selector" class="aa-toolbar">
            
            <!-- ═══ ZONA IZQUIERDA: Navegación de fecha ═══ -->
            <div class="aa-date-navigator">
                <!-- Botón anterior -->
                <button id="aa-date-prev" type="button" class="aa-date-nav-btn aa-date-nav-prev" aria-label="Día anterior">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                
                <!-- Input Flatpickr (centro del control compuesto) -->
                <input type="text" id="aa-date-picker" class="aa-date-input" placeholder="Seleccionar fecha" readonly>
                
                <!-- Botón siguiente -->
                <button id="aa-date-next" type="button" class="aa-date-nav-btn aa-date-nav-next" aria-label="Día siguiente">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
            
            <!-- ═══ ZONA DERECHA: Acciones ═══ -->
            <div class="aa-toolbar-actions">
                <!-- Botón de búsqueda (ghost/terciario) -->
                <button id="aa-btn-search" type="button" class="aa-btn-ghost" aria-label="Buscar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>
                
                <!-- Botón + Horario (acción de crear, presencia moderada) -->
                <button id="aa-btn-add-schedule" type="button" class="aa-btn-create">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Horario</span>
                </button>
            </div>
        </div>

        <!-- =========================
             🔹 AGENDA TIMELINE
             ========================= -->
        <section class="aa-day-timeline relative bg-white border border-gray-200 overflow-visible">

            <!-- Indicador de hora actual -->
            <div
                id="aa-time-now-indicator"
                class="aa-time-now-indicator pointer-events-none absolute left-0 right-0 z-10">
                <!-- línea / punto se insertan por JS -->
            </div>

            <!-- Grid de horarios -->
            <div id="aa-time-grid" class="aa-time-grid min-h-[100px]">
              <!-- 🔥 JS renderiza aquí dinámicamente las filas de horarios -->
            </div>

        </section>

    </div>

</div>

<!-- Scripts: Orden crítico - datos primero, luego dependencias, luego módulo -->
<!-- Datos base del calendario -->
<script>
  // Datos específicos del módulo Calendar (complementa variables globales de layout.php)
  window.AA_CALENDAR_DATA = {
    schedule: <?php echo wp_json_encode($schedule); ?>,
    fixedStaffName: <?php echo wp_json_encode($fixed_staff_name); ?>,
    fixedServiceName: <?php echo wp_json_encode($fixed_service_name); ?>,
    nonce: '<?php echo wp_create_nonce('aa_proximas_citas'); ?>',
    historialNonce: '<?php echo wp_create_nonce('aa_historial_citas'); ?>',
    ajaxurl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>'
  };

  // Nonce real del endpoint AI para pruebas manuales y futura UI del chat.
  window.AA_AI_CHAT = {
    nonce: '<?php echo esc_js(wp_create_nonce('aa_admin_ai_chat_nonce')); ?>',
    confirm_nonce: '<?php echo esc_js(wp_create_nonce('aa_ai_confirm_booking_nonce')); ?>'
  };
</script>

<!-- 
    Scripts compartidos (Flatpickr, dateUtils, services, controllers) 
    están cargados globalmente desde layout.php 
-->

<!-- Archivos de sección del calendario (específicos del módulo) -->
<script src="<?php echo esc_url($plugin_url . 'calendar-section/calendar-appointment-card.js?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>
<script src="<?php echo esc_url($plugin_url . 'calendar-section/calendar-overlap.js?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>
<script src="<?php echo esc_url($plugin_url . 'calendar-section/calendar-appointments.js?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>
<script src="<?php echo esc_url($plugin_url . 'calendar-section/calendar-timeline.js?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>
<script src="<?php echo esc_url($plugin_url . 'calendar-section/calendar-assignments.js?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>

<!-- Módulo del calendario (SIEMPRE AL FINAL) -->
<script src="<?php echo esc_url($module_js_url); ?>" defer></script>

<!-- =========================
     🔹 FLOATING ACTIONS: AGENDAR
     ========================= -->
<div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <button
        id="aa-btn-open-fastappointment-modal"
        type="button"
        class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-full shadow-lg hover:bg-slate-50 hover:border-slate-300 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-slate-300/40"
        aria-label="Abrir cita rapida">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l-7 7 7 7M5 12h14"/>
        </svg>
        <span>Cita rapida</span>
    </button>

    <button 
        id="aa-btn-open-reservation-modal" 
        type="button"
        class="flex items-center justify-center w-14 h-14 text-white bg-blue-500 hover:bg-blue-600 active:bg-blue-700 rounded-full shadow-xl shadow-blue-500/25 hover:shadow-2xl hover:shadow-blue-500/30 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-blue-500/30"
        aria-label="Agendar nueva cita"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
    </button>
</div>