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

                
              <!-- 🔥 JS renderiza aquí filas como: -->

                  <div class="aa-time-row aa-past">
                    <div class="aa-time-label">16:00</div>
                    <div class="aa-time-content"></div>
                  </div>

                  <div class="aa-time-row aa-future">
                    <div class="aa-time-label">17:00</div>
                    <div class="aa-time-content">
                      <article class="aa-appointment-card aa-span-2 aa-status-confirmed">
                        <!-- cita -->
                      </article>
                    </div>
                  </div>
                

            </div>

        </section>

    </div>

</details>

<?php
// Pasar variables JavaScript necesarias para el módulo
$schedule = get_option('aa_schedule', []);
$slot_duration = intval(get_option('aa_slot_duration', 60));
$ajax_url = admin_url('admin-ajax.php');
?>
<script>
    // Variables globales para el módulo de calendario
    window.aaCalendarVars = {
        ajaxurl: <?php echo json_encode($ajax_url); ?>,
        schedule: <?php echo json_encode($schedule); ?>,
        slot_duration: <?php echo $slot_duration; ?>,
        nonce: <?php echo json_encode(wp_create_nonce('aa_calendar_citas')); ?>,
        nonce_confirmar: <?php echo json_encode(wp_create_nonce('aa_confirmar_cita')); ?>,
        nonce_cancelar: <?php echo json_encode(wp_create_nonce('aa_cancelar_cita')); ?>,
        nonce_crear_cliente_desde_cita: <?php echo json_encode(wp_create_nonce('aa_crear_cliente_desde_cita')); ?>
    };
</script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'module.js'); ?>" defer></script>

