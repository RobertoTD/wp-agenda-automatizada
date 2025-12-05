// ==============================
// 🔹 Funciones UI de Flatpickr
// ==============================

console.log('🔄 Cargando calendarUI.js...');

/**
 * Buscar y validar input de fecha
 */
function findDateInput() {
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
 */
function getSlotDuration() {
  const slotDuration = (typeof window.aa_slot_duration !== 'undefined') 
    ? window.aa_slot_duration 
    : 60;
  
  console.log(`⚙️ Duración de slot configurada: ${slotDuration} minutos`);
  
  return slotDuration;
}

/**
 * Inicializa Flatpickr en modo básico (sin reglas de disponibilidad)
 */
function initBasicCalendar(fechaSelector) {
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
 * Renderiza el calendario frontend con reglas de disponibilidad
 */
function render(config) {
  const {
    fechaInput,
    minDate,
    maxDate,
    disableDateFn,
    onDateSelected
  } = config;

  console.log('🔄 CalendarUI.render() llamado con config:', config);

  if (!fechaInput || typeof flatpickr === "undefined") {
    console.error('❌ No se puede renderizar el calendario: input o Flatpickr no disponibles');
    return null;
  }

  if (fechaInput._flatpickr) {
    console.log('🗑️ Destruyendo instancia anterior de Flatpickr');
    fechaInput._flatpickr.destroy();
  }

  const picker = flatpickr(fechaInput, {
    disableMobile: true,
    dateFormat: "d-m-Y",
    minDate: minDate,
    maxDate: maxDate,
    locale: "es",
    disable: [disableDateFn],
    onChange: function(selectedDates) {
      if (!selectedDates.length) return;
      
      const selectedDate = selectedDates[0];
      
      if (onDateSelected && typeof onDateSelected === 'function') {
        onDateSelected(selectedDate, this);
      }
    }
  });

  console.log('✅ Flatpickr frontend renderizado con reglas de disponibilidad');

  return picker;
}

/**
 * Reconstruye el calendario con reglas de disponibilidad (LEGACY)
 */
function rebuildCalendar(options) {
  console.warn('⚠️ rebuildCalendar() es legacy, usa render() en su lugar');
  return render(options);
}

// ==============================
// 🔹 Exponer en window
// ==============================
window.CalendarUI = {
  findDateInput,
  getSlotDuration,
  initBasicCalendar,
  rebuildCalendar,
  render
};

console.log('✅ CalendarUI cargado y expuesto globalmente');
console.log('   - window.CalendarUI:', window.CalendarUI);
console.log('   - Funciones disponibles:', Object.keys(window.CalendarUI));