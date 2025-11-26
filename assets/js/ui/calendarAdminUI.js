// ==============================
// 🔹 UI de Calendario para Admin
// ==============================

console.log('🔄 Cargando calendarAdminUI.js...');

/**
 * Renderiza el calendario admin con Flatpickr
 * @param {HTMLElement} fechaInput - Input de fecha
 * @param {string} slotContainerSelector - ID del contenedor de slots
 * @param {Object} proxy - Instancia de AvailabilityProxy
 * @param {Object} config - Configuración (minDate, maxDate)
 */
export function renderAdminCalendar(fechaInput, slotContainerSelector, proxy, config) {
  const { minDate, maxDate } = config;
  
  if (!fechaInput) {
    console.error('❌ calendarAdminUI: fechaInput no proporcionado');
    return null;
  }

  if (typeof flatpickr === "undefined") {
    console.error('❌ calendarAdminUI: Flatpickr no está disponible');
    return null;
  }
  
  // Destruir instancia previa si existe
  if (fechaInput._flatpickr) {
    console.log('🗑️ Destruyendo instancia anterior de Flatpickr (admin)');
    fechaInput._flatpickr.destroy();
  }
  
  // Crear nueva instancia de Flatpickr
  const picker = flatpickr(fechaInput, {
    disableMobile: true,
    dateFormat: "d-m-Y",
    minDate,
    maxDate,
    locale: "es",
    disable: [(date) => proxy.disableDate(date)],
    onChange: function(selectedDates) {
      if (!selectedDates.length) return;
      
      const selectedDate = selectedDates[0];
      const validSlots = proxy.getSlotsForDate(selectedDate);
      
      console.log(`📅 Admin: Fecha seleccionada: ${selectedDate.toLocaleDateString()}`);
      console.log(`📅 Admin: Slots disponibles: ${validSlots.length}`);
      
      // Disparar evento personalizado para que SlotSelectorAdminUI lo maneje
      document.dispatchEvent(new CustomEvent('aa:admin:date-selected', {
        detail: {
          containerId: slotContainerSelector,
          validSlots,
          selectedDate,
          fechaInput
        }
      }));
    },
    onReady: function() {
      console.log('✅ Flatpickr admin inicializado correctamente');
    }
  });
  
  console.log('✅ Calendario admin renderizado');
  
  return picker;
}

// ==============================
// 🔹 Exponer en window para compatibilidad
// ==============================
window.CalendarAdminUI = {
  renderAdminCalendar
};

console.log('✅ CalendarAdminUI cargado y expuesto globalmente');