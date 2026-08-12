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

// Obtener schedule (legacy: en instalaciones viejas puede ser "" en vez de array)
$schedule = get_option('aa_schedule', []);
if (!is_array($schedule)) {
    $schedule = [];
}

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
    <div class="px-4 py-5 border-b border-gray-100 bg-white">
        <div class="flex items-center justify-center w-full">
            <div id="aa-date-selector" class="flex items-center justify-center">
                <div class="aa-date-navigator">
                    <!-- Botón anterior -->
                    <button id="aa-date-prev" type="button" class="aa-date-nav-btn aa-date-nav-prev" aria-label="Día anterior">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    <!-- Input Flatpickr (centro del control compuesto) -->
                    <input type="text" id="aa-date-picker" class="aa-date-input" placeholder="Seleccionar fecha" readonly>

                    <!-- Botón siguiente -->
                    <button id="aa-date-next" type="button" class="aa-date-nav-btn aa-date-nav-next" aria-label="Día siguiente">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- =========================
         🔹 CONTENIDO / BODY
         ========================= -->
    <div class="p-0 transition-all duration-200">

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

  window.AA_PUSH_CONFIG = {
    ajaxUrl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
    registerAction: '<?php echo esc_js(PushSubscriptionAjax::ACTION_REGISTER); ?>',
    configAction: '<?php echo esc_js(PushSubscriptionAjax::ACTION_CONFIG); ?>',
    nonce: '<?php echo esc_js(wp_create_nonce(PushSubscriptionAjax::NONCE_ACTION)); ?>'
  };
</script>

<!-- 
    Scripts compartidos (Flatpickr, dateUtils, services, controllers) 
    están cargados globalmente desde layout.php 
-->

<!-- PWA install + notifications first opportunity (orden crítico) -->
<?php
$learning_handlers_js = plugin_dir_url(__DIR__ . '/../learning/index.php') . 'learning-action-handlers.js';
$pwa_install_first_opportunity_js = plugin_dir_url(__DIR__ . '/../dashboard/index.php') . 'pwa-install-first-opportunity.js';
$pwa_push_activation_service_js = AA_PLUGIN_URL . 'assets/js/services/pwaPushActivationService.js';
$pwa_notifications_first_opportunity_js = plugin_dir_url(__DIR__ . '/../dashboard/index.php') . 'pwa-notifications-first-opportunity.js';
?>
<script src="<?php echo esc_url($learning_handlers_js . '?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>
<script src="<?php echo esc_url($pwa_install_first_opportunity_js . '?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>
<script src="<?php echo esc_url($pwa_push_activation_service_js . '?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>
<script src="<?php echo esc_url($pwa_notifications_first_opportunity_js . '?ver=' . rawurlencode($calendar_ver)); ?>" defer></script>

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
        class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 active:bg-violet-800 rounded-full shadow-lg shadow-violet-600/30 hover:shadow-xl hover:shadow-violet-600/35 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-violet-500/40"
        aria-label="Crear cita">
        <span>+ Crear cita</span>
    </button>
</div>