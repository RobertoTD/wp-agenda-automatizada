// ==============================
// 🔹 Funciones UI de Flatpickr
// ==============================

console.log('🔄 Cargando calendarUI.js...');

/**
 * Buscar y validar input de fecha
 * @returns {HTMLElement|null}
 */
export function findDateInput() {
  const fechaInput = document.getElementById("fecha") || document.getElementById("cita-fecha");
  
  console.log('aa_debug: fechaInput =>', !!fechaInput);
  console.log('aa_debug: fechaInput ID =>', fechaInput ? fechaInput.id : 'null');

  if (!fechaInput) {
    console.warn('⚠️ aa_debug: No se encontró input #fecha ni #cita-fecha');
    return null;
  }

  return fechaInput;
}

/**
 * Leer duración de slot desde configuración global
 * @returns {number}
 */
export function getSlotDuration() {
  const slotDuration = (typeof window.aa_slot_duration !== 'undefined') 
    ? window.aa_slot_duration 
    : 60;
  
  console.log(`⚙️ Duración de slot configurada: ${slotDuration} minutos`);
  
  return slotDuration;
}

/**
 * Inicializa Flatpickr en modo básico (sin reglas de disponibilidad)
 * @param {string} fechaSelector - Selector CSS del input de fecha
 */
export function initBasicCalendar(fechaSelector) {
  console.log('📅 initBasicCalendar llamado con selector:', fechaSelector);
  
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
 */
export function rebuildCalendar(options) {
  console.log('🔄 rebuildCalendar llamado con opciones:', options);
  
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

  if (fechaInput._flatpickr) {
    console.log('🗑️ Destruyendo instancia anterior de Flatpickr');
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
      
      if (onDateSelected && typeof onDateSelected === 'function') {
        const result = onDateSelected(sel, this);
        
        if (result && result.selectedSlotISO) {
          selectedSlotISO = result.selectedSlotISO;
        }
      }
    }
  });

  console.log('✅ Flatpickr reconstruido con reglas de disponibilidad');

  return picker;
}

// ==============================
// 🔹 Exponer en window para compatibilidad con código no-modular
// ==============================
window.CalendarUI = {
  findDateInput,
  getSlotDuration,
  initBasicCalendar,
  rebuildCalendar
};

console.log('✅ CalendarUI cargado y expuesto globalmente');
console.log('   - window.CalendarUI:', window.CalendarUI);
console.log('   - Funciones disponibles:', Object.keys(window.CalendarUI));