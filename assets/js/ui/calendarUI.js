// ==============================
// 🔹 Funciones UI de Flatpickr
// ==============================

/**
 * Inicializa Flatpickr en modo básico (sin reglas de disponibilidad)
 * @param {string} fechaSelector - Selector CSS del input de fecha
 */
export function initBasicCalendar(fechaSelector) {
  if (typeof flatpickr === "undefined" || typeof flatpickr.l10ns === "undefined") {
    console.error('❌ Flatpickr no está cargado correctamente.');
    return null;
  }

  flatpickr.localize(flatpickr.l10ns.es);
  
  const instance = flatpickr(fechaSelector, {
    disableMobile: true,
    dateFormat: "d-m-Y",
    minDate: "today",
    locale: "es",
    maxDate: new Date().fp_incr(14),
    onReady: function () {
      console.log("📅 Flatpickr inicializado correctamente (modo básico).");
    }
  });

  return instance;
}

/**
 * Reconstruye el calendario con reglas de disponibilidad
 * @param {Object} options - Configuración del calendario
 * @param {HTMLElement} options.fechaInput - Input de fecha
 * @param {Date} options.minDate - Fecha mínima
 * @param {Date} options.maxDate - Fecha máxima
 * @param {Function} options.disableDateCallback - Función para deshabilitar fechas
 * @param {Function} options.onDateSelected - Callback cuando se selecciona una fecha
 * @returns {Object} Instancia de Flatpickr
 */
export function rebuildCalendar(options) {
  const {
    fechaInput,
    minDate,
    maxDate,
    disableDateCallback,
    onDateSelected
  } = options;

  if (!fechaInput || typeof flatpickr === "undefined") {
    console.error('❌ No se puede reconstruir el calendario: input o Flatpickr no disponibles');
    return null;
  }

  // Destruir instancia anterior si existe
  if (fechaInput._flatpickr) {
    fechaInput._flatpickr.destroy();
  }

  let selectedSlotISO = null;

  const picker = flatpickr(fechaInput, {
    disableMobile: true,
    dateFormat: "d-m-Y",
    minDate: minDate,
    maxDate: maxDate,
    locale: "es",
    disable: [disableDateCallback],
    onChange: function(selectedDates) {
      if (!selectedDates.length) return;
      
      const sel = selectedDates[0];
      
      // Llamar al callback externo con la fecha seleccionada
      if (onDateSelected && typeof onDateSelected === 'function') {
        const result = onDateSelected(sel, this);
        
        // Si el callback retorna un valor ISO, guardarlo
        if (result && result.selectedSlotISO) {
          selectedSlotISO = result.selectedSlotISO;
        }
      }
    }
  });

  console.log('📅 Flatpickr reconstruido con reglas de disponibilidad');

  return picker;
}

// ==============================
// 🔹 Exponer en window para compatibilidad con código no-modular
// ==============================
window.CalendarUI = {
  initBasicCalendar,
  rebuildCalendar
};

console.log('✅ CalendarUI cargado y expuesto globalmente');