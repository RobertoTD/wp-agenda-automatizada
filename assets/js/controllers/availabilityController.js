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
// 🔹 DEBUG: Imprimir datos locales de disponibilidad
// ==============================
if (typeof window.aa_local_availability !== 'undefined') {
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('📦 DATOS COMPLETOS PARA IMPRESIÓN LOCAL');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('🔹 Configuración:', {
    slot_duration: window.aa_local_availability.slot_duration,
    timezone: window.aa_local_availability.timezone,
    total_confirmed: window.aa_local_availability.total_confirmed
  });
  console.log('🔹 Slots ocupados locales:', window.aa_local_availability.local_busy);
  console.log('🔹 Total de eventos locales:', window.aa_local_availability.local_busy.length);
  
  // Formatear para visualización detallada
  if (window.aa_local_availability.local_busy.length > 0) {
    console.log('🔹 Detalle de eventos:');
    window.aa_local_availability.local_busy.forEach((slot, index) => {
      console.log(`   ${index + 1}. ${slot.start} → ${slot.end} | ${slot.title} (${slot.attendee})`);
    });
  } else {
    console.log('ℹ️ No hay eventos confirmados en la base de datos local');
  }
  
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  
  // 🔹 Combinar con datos de Google Calendar si existen
  if (typeof window.aa_availability !== 'undefined' && window.aa_availability.busy) {
    console.log('🔹 Eventos de Google Calendar:', window.aa_availability.busy.length);
    console.log('🔹 Total combinado:', 
      window.aa_local_availability.local_busy.length + window.aa_availability.busy.length
    );
  }
  
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
}

// ==============================
// 🔹 CORREGIDO: Verificar si un slot tiene suficiente espacio libre
// ==============================
function hasEnoughFreeTime(slotStart, durationMinutes, busyRanges) {
  const slotEnd = new Date(slotStart.getTime() + durationMinutes * 60000);
  
  for (const busy of busyRanges) {
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
  const allSlots = generateSlots(day, intervals, busyRanges);
  
  console.log(`🕒 [${ymd(day)}] Slots generados antes de filtrar por duración: ${allSlots.length}`);
  
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
    
    if (!fechaInput) {
      console.warn(`⚠️ No se encontró el input ${fechaInputSelector}`);
      return;
    }
    
    if (typeof flatpickr === "undefined") {
      console.error('❌ Flatpickr no está disponible');
      return;
    }

    const aa_schedule = window.aa_schedule || {};
    const aa_future_window = window.aa_future_window || 14;
    
    const slotDuration = (typeof window.aa_slot_duration !== 'undefined' && window.aa_slot_duration > 0)
      ? parseInt(window.aa_slot_duration, 10)
      : 60;

    console.log(`📊 Configuración cargada:`);
    console.log(`   - Horario (aa_schedule):`, aa_schedule);
    console.log(`   - Duración de cita: ${slotDuration} minutos`);
    console.log(`   - Ventana futura: ${aa_future_window} días`);

    const busy = (window.aa_availability && Array.isArray(window.aa_availability.busy))
      ? window.aa_availability.busy
      : [];

    console.log(`   - Eventos ocupados (Google Calendar): ${busy.length}`);

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
      const intervals = getDayIntervals(aa_schedule, weekday);
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
    // 🔹 RECONSTRUIR Flatpickr con reglas de disponibilidad
    // ==============================
    if (isAdmin) {
      console.log('📅 Reconstruyendo calendario en panel del asistente con reglas de disponibilidad...');
      
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
      
      console.log('✅ Calendario reconstruido en admin con reglas de disponibilidad');
      
    } else {
      // ✅ Frontend: RECONSTRUIR calendario básico con reglas
      console.log('📅 Reconstruyendo calendario en frontend con reglas de disponibilidad...');
      
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
        
        console.log('✅ Calendario reconstruido en frontend con reglas de disponibilidad');
      } else {
        console.error('❌ CalendarUI no está disponible para reconstruir el calendario');
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