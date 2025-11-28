// ==============================
// 🔹 UI de Calendario para Admin
// ==============================

console.log('🔄 Cargando calendarAdminUI.js...');

/**
 * Renderiza el calendario admin con Flatpickr
 * @param {Object} config - Configuración del calendario admin
 */
export function render(config) {
  const {
    fechaInput,
    slotContainerSelector,
    slotsMap,
    minDate,
    maxDate,
    disableDateFn
  } = config;

  console.log('🔄 CalendarAdminUI.render() llamado con config:', config);
  
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
    disable: [disableDateFn],
    onChange: function(selectedDates) {
      if (!selectedDates.length) return;
      
      const selectedDate = selectedDates[0];
      
      console.log('🔍 DEBUG onChange:', {
        proxyExists: !!window.availabilityProxyInstance,
        proxyHasSlots: window.availabilityProxyInstance ? Object.keys(window.availabilityProxyInstance.availableSlotsPerDay || {}).length : 0,
        selectedDateKey: window.DateUtils.ymd(selectedDate)
      });
      
      const validSlots = window.AvailabilityService.slotsForDate(
        window.availabilityProxyInstance || {}, 
        selectedDate
      );
      
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

/**
 * LEGACY: Mantener compatibilidad con código antiguo
 */
export function renderAdminCalendar(fechaInput, slotContainerSelector, proxy, config) {
  console.warn('⚠️ renderAdminCalendar() es legacy, usa render() en su lugar');
  
  return render({
    fechaInput,
    slotContainerSelector,
    slotsMap: proxy.availableSlotsPerDay,
    minDate: config.minDate,
    maxDate: config.maxDate,
    disableDateFn: (date) => window.AvailabilityService.disable(proxy, date)
  });
}

// ==============================
// 🔹 Exponer en window para compatibilidad
// ==============================
window.CalendarAdminUI = {
  render,
  renderAdminCalendar
};

console.log('✅ CalendarAdminUI cargado y expuesto globalmente');