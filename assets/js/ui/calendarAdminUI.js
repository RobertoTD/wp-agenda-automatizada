// ==============================
// 🔹 UI de Calendario para Admin
// ==============================

console.log('🔄 Cargando calendarAdminUI.js...');

(function() {
  'use strict';

  /**
   * Renderiza el calendario admin con Flatpickr
   * @param {Object} config - Configuración del calendario admin
   */
  function render(config) {
    const {
      fechaInput,
      slotContainerSelector,
      slotsMap,
      minDate,
      maxDate,
      disableDateFn
    } = config;

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
        
        console.log(`📅 Admin: Fecha seleccionada: ${selectedDate.toLocaleDateString()}`);
        
        // Disparar evento personalizado para que AdminReservationAssignmentFlowController lo maneje
        // Mantener compatibilidad: solo selectedDate es necesario ahora
        document.dispatchEvent(new CustomEvent('aa:admin:date-selected', {
          detail: {
            containerId: slotContainerSelector,
            selectedDate,
            fechaInput
          }
        }));
      },
      onReady: function() {
      }
    });

    return picker;
  }

  /**
   * LEGACY: Mantener compatibilidad con código antiguo
   */
  function renderAdminCalendar(fechaInput, slotContainerSelector, proxy, config) {
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
  // 🔹 Exponer en window
  // ==============================
  window.CalendarAdminUI = {
    render,
    renderAdminCalendar
  };

  console.log('✅ CalendarAdminUI cargado y expuesto globalmente');
})();
