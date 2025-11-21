// ==============================
// 🔹 Importar utilidades desde dateUtils.js
// ==============================
import { 
  ymd, 
  getWeekdayName, 
  getDayIntervals, 
  generateSlotsForDay as generateSlots,
  timeStrToMinutes 
} from '../utils/dateUtils.js';

// ==============================
// 🔹 CORREGIDO: Verificar si un slot tiene suficiente espacio libre
// ==============================
function hasEnoughFreeTime(slotStart, durationMinutes, busyRanges) {
  const slotEnd = new Date(slotStart.getTime() + durationMinutes * 60000);
  
  for (const busy of busyRanges) {
    // ✅ CORRECCIÓN: Superposición real
    // Un slot se superpone con un evento si:
    // - El slot empieza ANTES de que termine el evento
    // - Y el slot termina DESPUÉS de que empiece el evento
    
    // PERO: Si el slot termina EXACTAMENTE donde empieza el evento, NO hay superposición
    // Y si el evento termina EXACTAMENTE donde empieza el slot, NO hay superposición
    
    const overlaps = slotStart < busy.end && slotEnd > busy.start;
    
    if (overlaps) {
      console.log(`❌ Slot ${slotStart.toLocaleTimeString()}-${slotEnd.toLocaleTimeString()} rechazado: intersecta con evento ${busy.start.toLocaleTimeString()}-${busy.end.toLocaleTimeString()}`);
      return false;
    }
  }
  
  return true;
}

// ==============================
// 🔹 Wrapper: Filtrar slots por duración mínima
// ==============================
function generateSlotsForDay(day, intervals, busyRanges, slotDuration) {
  // ✅ Usar la función de dateUtils.js (que ya maneja minutos correctamente)
  const allSlots = generateSlots(day, intervals, busyRanges);
  
  console.log(`🕒 [${ymd(day)}] Slots generados antes de filtrar por duración: ${allSlots.length}`);
  
  // ✅ Filtrar slots que NO tienen suficiente espacio
  const validSlots = allSlots.filter(slot => {
    const hasSpace = hasEnoughFreeTime(slot, slotDuration, busyRanges);
    if (hasSpace) {
      console.log(`✅ Slot ${slot.toLocaleTimeString()} VÁLIDO (requiere ${slotDuration} min)`);
    }
    return hasSpace;
  });
  
  console.log(`✅ [${ymd(day)}] Slots válidos después de filtrar (${slotDuration} min): ${validSlots.length}`);
  
  return validSlots;
}

// ==============================
// 🔹 Inicialización
// ==============================
export function initAvailabilityController(config) {
  const {
    fechaInputSelector,
    slotContainerSelector,
    isAdmin = false
  } = config;

  document.addEventListener("aa:availability:loaded", () => {
    const fechaInput = document.querySelector(fechaInputSelector);
    if (!fechaInput || typeof flatpickr === "undefined") {
      console.warn(`⚠️ No se encontró el input ${fechaInputSelector} o flatpickr no está disponible`);
      return;
    }

    // ✅ CORRECCIÓN: Leer aa_slot_duration correctamente
    const aa_schedule = window.aa_schedule || {};
    const aa_future_window = window.aa_future_window || 14;
    
    // ✅ LEER desde window.aa_slot_duration (localizado por PHP)
    const slotDuration = (typeof window.aa_slot_duration !== 'undefined' && window.aa_slot_duration > 0)
      ? parseInt(window.aa_slot_duration, 10)
      : 60; // fallback a 60 solo si no existe

    console.log(`📊 Configuración cargada:`);
    console.log(`   - Horario (aa_schedule):`, aa_schedule);
    console.log(`   - Duración de cita: ${slotDuration} minutos ← DEBE SER 30`);
    console.log(`   - Ventana futura: ${aa_future_window} días`);

    const busy = (window.aa_availability && Array.isArray(window.aa_availability.busy))
      ? window.aa_availability.busy
      : [];

    console.log(`   - Eventos ocupados: ${busy.length}`);

    const busyRanges = busy.map(ev => ({
      start: new Date(ev.start),
      end: new Date(ev.end)
    }));

    const minDate = new Date();
    const maxDate = new Date();
    maxDate.setDate(minDate.getDate() + Number(aa_future_window));

    const availableSlotsPerDay = {};
    
    for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
      const day = new Date(d);
      const weekday = getWeekdayName(day);
      
      // ✅ getDayIntervals ya convierte a minutos internamente
      const intervals = getDayIntervals(aa_schedule, weekday);
      
      // ✅ generateSlotsForDay filtra por duración
      const slots = generateSlotsForDay(day, intervals, busyRanges, slotDuration);
      
      availableSlotsPerDay[ymd(day)] = slots.length;
      
      if (slots.length > 0) {
        console.log(`📅 ${ymd(day)} (${weekday}): ${slots.length} slots disponibles`);
      }
    }

    function isDateAvailable(date) {
      return (availableSlotsPerDay[ymd(date)] || 0) > 0;
    }

    function disableDate(date) {
      return !isDateAvailable(date);
    }

    // ==============================
    // 🔹 Inicializar Flatpickr
    // ==============================
    if (isAdmin) {
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
          const validSlots = generateSlotsForDay(sel, intervals, busyRanges, slotDuration);
          
          renderSlots(slotContainerSelector, validSlots, sel, fechaInput, true);
        }
      });
      
      console.log('📅 Flatpickr inicializado en panel del asistente');
      
    } else {
      if (typeof window.CalendarUI !== 'undefined') {
        window.CalendarUI.rebuildCalendar({
          fechaInput: fechaInput,
          minDate: minDate,
          maxDate: maxDate,
          disableDateCallback: disableDate,
          onDateSelected: (selectedDate, pickerInstance) => {
            const weekday = getWeekdayName(selectedDate);
            const intervals = getDayIntervals(aa_schedule, weekday);
            const validSlots = generateSlotsForDay(selectedDate, intervals, busyRanges, slotDuration);
            pickerInstance.validSlots = validSlots;
            
            renderSlots(slotContainerSelector, validSlots, selectedDate, fechaInput, false);
            
            return { selectedSlotISO: validSlots.length > 0 ? validSlots[0].toISOString() : null };
          }
        });
      } else {
        console.error('❌ CalendarUI no está disponible en el frontend');
      }
    }
  });
}

// ==============================
// 🔹 Renderizado de slots
// ==============================
function renderSlots(containerId, validSlots, selectedDate, fechaInput, isAdmin) {
  const slotSelectorId = isAdmin ? 'slot-selector-admin' : 'slot-selector';
  
  if (isAdmin) {
    const container = document.getElementById(containerId);
    if (!container) {
      console.error(`❌ No se encontró contenedor: ${containerId}`);
      return;
    }
    
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
    
    if (validSlots.length > 0) {
      const firstSlot = validSlots[0];
      fechaInput.value = `${selectedDate.toLocaleDateString()} ${firstSlot.getHours().toString().padStart(2,'0')}:${firstSlot.getMinutes().toString().padStart(2,'0')}`;
    }
    
  } else {
    if (typeof window.SlotSelectorUI !== 'undefined') {
      window.SlotSelectorUI.renderAvailableSlots(containerId, validSlots, chosen => {
        fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosen.getHours().toString().padStart(2,'0')}:${chosen.getMinutes().toString().padStart(2,'0')}`;
      });
    }
    
    if (validSlots.length > 0) {
      const firstSlot = validSlots[0];
      fechaInput.value = `${selectedDate.toLocaleDateString()} ${firstSlot.getHours().toString().padStart(2,'0')}:${firstSlot.getMinutes().toString().padStart(2,'0')}`;
    }
  }
}

// ==============================
// 🔹 Exponer en window
// ==============================
window.AvailabilityController = {
  init: initAvailabilityController
};

console.log('✅ AvailabilityController cargado');