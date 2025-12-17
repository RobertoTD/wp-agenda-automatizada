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

// Resolver rutas de scripts (wpaa_url puede no estar disponible en este contexto)
$plugin_url = plugin_dir_url(__FILE__);
$date_utils_url = $plugin_url . '../../../../../assets/js/utils/dateUtils.js';
$module_js_url = $plugin_url . 'calendar-module.js';
?>

<details open class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden group">

    <!-- =========================
         🔹 HEADER / SUMMARY
         ========================= -->
    <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
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

            <!-- Derecha: flecha (aunque esté abierto) -->
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200 group-open:rotate-180"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 9l-7 7-7-7"/>
            </svg>

        </div>
    </summary>

    <!-- =========================
         🔹 CONTENIDO / BODY
         ========================= -->
    <div class="p-6 transition-all duration-200">

        <!-- =========================
             🔹 SELECTOR DE FECHA
             ========================= -->
        <div id="aa-date-selector" class="mb-4 flex items-center gap-2">
            <!-- Botón anterior -->
            <button id="aa-date-prev" type="button" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg border border-gray-300 transition-colors">
                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            
            <!-- Input Flatpickr -->
            <input type="text" id="aa-date-picker" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Seleccionar fecha" readonly>
            
            <!-- Botón siguiente -->
            <button id="aa-date-next" type="button" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg border border-gray-300 transition-colors">
                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <!-- =========================
             🔹 AGENDA TIMELINE
             ========================= -->
        <section class="aa-day-timeline relative bg-white rounded-lg border border-gray-200 overflow-hidden">

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

</details>

<!-- Scripts: Orden crítico - datos primero, luego dependencias, luego módulo -->
<!-- Datos base del calendario -->
<script>
  window.AA_CALENDAR_DATA = {
    schedule: <?php echo wp_json_encode($schedule); ?>,
    nonce: '<?php echo wp_create_nonce('aa_proximas_citas'); ?>',
    historialNonce: '<?php echo wp_create_nonce('aa_historial_citas'); ?>',
    ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>'
  };

  // Variables requeridas por ConfirmService
  window.aa_asistant_vars = {
    nonce_confirmar: '<?php echo wp_create_nonce('aa_confirmar_cita'); ?>',
    nonce_cancelar: '<?php echo wp_create_nonce('aa_cancelar_cita'); ?>'
  };

  // Garantizar ajaxurl global
  if (typeof window.ajaxurl === 'undefined') {
    window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
  }
</script>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Utils -->
<script src="<?php echo esc_url($date_utils_url); ?>" defer></script>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js" defer></script>

<!-- Servicios del calendario -->
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../../../../../assets/js/services/adminCalendarService.js'); ?>" defer></script>

<!-- Servicios y controladores requeridos por los botones -->
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../../../../../assets/js/services/confirmService.js'); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../../../../../assets/js/controllers/adminConfirmController.js'); ?>" defer></script>

<!-- Controlador del calendario -->
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../../../../../assets/js/controllers/adminCalendarController.js'); ?>" defer></script>

<!-- DatePicker Adapter -->
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../../../../../assets/js/ui-adapters/datePickerAdapter.js'); ?>" defer></script>

<!-- Archivos de sección del calendario -->
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../../../../../assets/js/calendar/calendar-section/calendar-appointment-card.js'); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../../../../../assets/js/calendar/calendar-section/calendar-appointments.js'); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../../../../../assets/js/calendar/calendar-section/calendar-timeline.js'); ?>" defer></script>

<!-- Módulo del calendario (SIEMPRE AL FINAL) -->
<script src="<?php echo esc_url($module_js_url); ?>" defer></script>