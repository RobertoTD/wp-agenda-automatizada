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
<!--
 * Admin UI – Agenda Timeline (Iframe Module)
 *
 * Estructura HTML mínima para el timeline por slots.
 * NO contiene lógica, NO contiene otras secciones.
 * El JS se encarga de inyectar filas, cards y estados.
 *-->


<section class="aa-day-timeline relative bg-white rounded-xl border border-gray-200 overflow-hidden">

  <!-- =========================
       🔹 INDICADOR DE HORA ACTUAL
       ========================= -->
  <div
    id="aa-time-now-indicator"
    class="aa-time-now-indicator pointer-events-none absolute left-0 right-0 z-10">
    <!-- línea / punto se insertan por JS -->
  </div>

  <!-- =========================
       🔹 GRID DE HORARIOS (SLOTS)
       ========================= -->
  <div id="aa-time-grid" class="aa-time-grid">

    
   <!--   JS renderiza aquí filas como esta: -->

      <div class="aa-time-row aa-past">
        <div class="aa-time-label">16:00</div>
        <div class="aa-time-content">
          <!-- vacío o card -->
        </div>
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

<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'module.js'); ?>" defer></script>

