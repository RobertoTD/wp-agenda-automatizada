// ==============================
// 🔹 Controlador de disponibilidad
// ==============================

/**
 * Inicializa el controlador de disponibilidad
 * Procesa busyRanges, genera slots, conecta UI con servicios
 */
export function initAvailabilityController(config) {
  const {
    fechaInputSelector,
    slotContainerSelector,
    isAdmin = false
  } = config;

  // ==============================
  // 🔹 Escuchar evento de disponibilidad cargada
  // ==============================
  document.addEventListener("aa:availability:loaded", () => {
    const fechaInput = document.querySelector(fechaInputSelector);
    if (!fechaInput || typeof flatpickr === "undefined") {
      console.warn(`⚠️ No se encontró el input ${fechaInputSelector} o flatpickr no está disponible`);
      return;
    }

    const aa_schedule = window.aa_schedule || {};
    const aa_future_window = window.aa_future_window || 14;

    const busy = (window.aa_availability && Array.isArray(window.aa_availability.busy))
      ? window.aa_availability.busy
      : [];

    // 🔹 Convertir todas las fechas ocupadas a objetos Date locales
    const busyRanges = busy.map(ev => ({
      start: new Date(ev.start),
      end: new Date(ev.end)
    }));

    const minDate = new Date();
    const maxDate = new Date();
    maxDate.setDate(minDate.getDate() + Number(aa_future_window));

    // 🔹 Precalcular slots disponibles por día
    const availableSlotsPerDay = {};
    let totalIntervals = 0;
    let totalBusy = busyRanges.length;
    
    for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
      const day = new Date(d);
      const weekday = getWeekdayName(day);
      const intervals = getDayIntervals(aa_schedule, weekday);
      
      totalIntervals += intervals.length;
      const slots = generateSlotsForDay(day, intervals, busyRanges);
      
      availableSlotsPerDay[ymd(day)] = slots.length;
      
      // 🔹 Debug: mostrar slots calculados
      if (slots.length > 0) {
        console.log(`📅 ${ymd(day)} (${weekday}): ${slots.length} slots disponibles`, 
          slots.map(s => s.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })));
      }
    }

    // 🔹 Funciones helper
    function isDateAvailable(date) {
      return (availableSlotsPerDay[ymd(date)] || 0) > 0;
    }

    function disableDate(date) {
      return !isDateAvailable(date);
    }

    // ==============================
    // 🔹 Inicializar Flatpickr según contexto
    // ==============================
    if (isAdmin) {
      // 🔹 ADMIN: usar flatpickr directamente
      if (fechaInput._flatpickr) fechaInput._flatpickr.destroy();
      
      flatpickr(fechaInput, {
        disableMobile: true,
        dateFormat: "d-m-Y",
        minDate: minDate,
        maxDate: maxDate,
        locale: "es",
        disable: [disableDate],
        onChange: function(selectedDates) {
          if (!selectedDates.length) return;
          const sel = selectedDates[0];
          const weekday = getWeekdayName(sel);
          const intervals = getDayIntervals(aa_schedule, weekday);
          const validSlots = generateSlotsForDay(sel, intervals, busyRanges);
          
          renderSlots(slotContainerSelector, validSlots, sel, fechaInput, true);
        }
      });
      
      console.log('📅 Flatpickr inicializado en panel del asistente');
      
    } else {
      // 🔹 FRONTEND: usar CalendarUI modular
      if (typeof window.CalendarUI !== 'undefined') {
        window.CalendarUI.rebuildCalendar({
          fechaInput: fechaInput,
          minDate: minDate,
          maxDate: maxDate,
          disableDateCallback: disableDate,
          onDateSelected: (selectedDate, pickerInstance) => {
            const weekday = getWeekdayName(selectedDate);
            const intervals = getDayIntervals(aa_schedule, weekday);
            const validSlots = generateSlotsForDay(selectedDate, intervals, busyRanges);
            pickerInstance.validSlots = validSlots;
            
            renderSlots(slotContainerSelector, validSlots, selectedDate, fechaInput, false);
            
            return { selectedSlotISO: validSlots.length > 0 ? validSlots[0].toISOString() : null };
          }
        });
      } else {
        console.error('❌ CalendarUI no está disponible en el frontend');
      }
      
      console.log(`📅 Flatpickr reinicializado. Intervalos: ${totalIntervals}, Ocupados: ${totalBusy}`);
    }
  });
}

/**
 * Renderiza los slots disponibles usando SlotSelectorUI o lógica admin
 * @param {string} containerId - ID del contenedor
 * @param {Array<Date>} validSlots - Slots disponibles
 * @param {Date} selectedDate - Fecha seleccionada
 * @param {HTMLElement} fechaInput - Input de fecha
 * @param {boolean} isAdmin - Si es contexto admin
 */
function renderSlots(containerId, validSlots, selectedDate, fechaInput, isAdmin) {
  const slotSelectorId = isAdmin ? 'slot-selector-admin' : 'slot-selector';
  
  if (isAdmin) {
    // 🔹 ADMIN: renderizado local
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    if (!validSlots.length) {
      container.textContent = 'No hay horarios disponibles para este día.';
      return;
    }
    
    const label = document.createElement('label');
    label.textContent = 'Horarios disponibles:';
    label.style.display = 'block';
    label.style.marginTop = '8px';
    
    const select = document.createElement('select');
    select.id = slotSelectorId;
    select.style.marginTop = '4px';
    select.style.width = '100%';
    select.style.padding = '8px';
    
    validSlots.forEach(date => {
      const option = document.createElement('option');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      option.value = date.toISOString();
      option.textContent = `${hours}:${minutes}`;
      select.appendChild(option);
    });
    
    select.addEventListener('change', () => {
      const chosen = new Date(select.value);
      fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosen.getHours().toString().padStart(2,'0')}:${chosen.getMinutes().toString().padStart(2,'0')}`;
    });
    
    container.appendChild(label);
    container.appendChild(select);
    
    // Auto-seleccionar primer slot
    if (validSlots.length > 0) {
      const firstSlot = validSlots[0];
      fechaInput.value = `${selectedDate.toLocaleDateString()} ${firstSlot.getHours().toString().padStart(2,'0')}:${firstSlot.getMinutes().toString().padStart(2,'0')}`;
    }
    
  } else {
    // 🔹 FRONTEND: usar SlotSelectorUI
    if (typeof window.SlotSelectorUI !== 'undefined') {
      window.SlotSelectorUI.renderAvailableSlots(containerId, validSlots, chosen => {
        fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosen.getHours().toString().padStart(2,'0')}:${chosen.getMinutes().toString().padStart(2,'0')}`;
      });
    } else {
      console.error('❌ SlotSelectorUI no está disponible');
    }
    
    // Auto-seleccionar primer slot
    if (validSlots.length > 0) {
      const firstSlot = validSlots[0];
      fechaInput.value = `${selectedDate.toLocaleDateString()} ${firstSlot.getHours().toString().padStart(2,'0')}:${firstSlot.getMinutes().toString().padStart(2,'0')}`;
    }
  }
}

// ==============================
// 🔹 Exponer en window para compatibilidad
// ==============================
window.AvailabilityController = {
  init: initAvailabilityController
};

console.log('✅ AvailabilityController cargado y expuesto globalmente');