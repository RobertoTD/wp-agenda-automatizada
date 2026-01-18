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

// Resolver rutas de scripts
$plugin_url = plugin_dir_url(__FILE__);
$module_js_url = $plugin_url . 'calendar-module.js';
?>

<div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">

    <!-- =========================
         🔹 HEADER
         ========================= -->
    <div class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
        <div class="flex items-center justify-between gap-3">

            <!-- Izquierda: icono + texto -->
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                    <!-- Icono calendario -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
             🔹 SELECTOR DE FECHA
             ========================= -->
        <div id="aa-date-selector" class="mt-2 mx-2 mb-4 flex items-center gap-2">
            <!-- Botón anterior -->
            <button id="aa-date-prev" type="button" class="px-2 py-[0.3rem] bg-gray-100 hover:bg-gray-200 rounded-lg border border-gray-300 transition-colors">
                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            
            <!-- Input Flatpickr -->
            <input type="text" id="aa-date-picker" class="w-32 px-2 py-[0.3rem] border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Seleccionar fecha" readonly>
            
            <!-- Botón siguiente -->
            <button id="aa-date-next" type="button" class="px-2 py-[0.3rem] bg-gray-100 hover:bg-gray-200 rounded-lg border border-gray-300 transition-colors">
                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <!-- =========================
             🔹 AGENDA TIMELINE
             ========================= -->
        <section class="aa-day-timeline relative bg-white border border-gray-200 overflow-hidden">

            <!-- Indicador de hora actual -->
            <div
                id="aa-time-now-indicator"
                class="aa-time-now-indicator pointer-events-none absolute left-0 right-0 z-10">
                <!-- línea / punto se insertan por JS -->
            </div>

            <!-- Grid de horarios -->
            <div id="aa-time-grid" class="aa-time-grid">
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
    nonce: '<?php echo wp_create_nonce('aa_proximas_citas'); ?>',
    historialNonce: '<?php echo wp_create_nonce('aa_historial_citas'); ?>',
    ajaxurl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>'
  };
</script>

<!-- 
    Scripts compartidos (Flatpickr, dateUtils, services, controllers) 
    están cargados globalmente desde layout.php 
-->

<!-- Archivos de sección del calendario (específicos del módulo) -->
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'calendar-section/calendar-appointment-card.js'); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'calendar-section/calendar-overlap.js'); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'calendar-section/calendar-appointments.js'); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'calendar-section/calendar-timeline.js'); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'calendar-section/calendar-assignments.js'); ?>" defer></script>

<!-- Módulo del calendario (SIEMPRE AL FINAL) -->
<script src="<?php echo esc_url($module_js_url); ?>" defer></script>