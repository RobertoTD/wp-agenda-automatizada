// ==============================
// 🔹 Importar utilidades desde dateUtils.js
// ==============================
import { 
  ymd, 
  getWeekdayName, 
  getDayIntervals, 
  generateSlotsForDay,
  hasEnoughFreeTime
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
  
  if (window.aa_local_availability.local_busy.length > 0) {
    console.log('🔹 Detalle de eventos:');
    window.aa_local_availability.local_busy.forEach((slot, index) => {
      console.log(`   ${index + 1}. ${slot.start} → ${slot.end} | ${slot.title} (${slot.attendee})`);
    });
  } else {
    console.log('ℹ️ No hay eventos confirmados en la base de datos local');
  }
  
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  
  if (typeof window.aa_availability !== 'undefined' && window.aa_availability.busy) {
    console.log('🔹 Eventos de Google Calendar:', window.aa_availability.busy.length);
    console.log('🔹 Total combinado:', 
      window.aa_local_availability.local_busy.length + window.aa_availability.busy.length
    );
  }
  
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
}

// ==============================
// 🔹 Combinar disponibilidad local y externa
// ==============================
function combineAvailabilityData() {
  if (typeof aa_local_availability !== 'undefined' && aa_local_availability.local_busy) {
    console.log("📊 Combinando disponibilidad local con datos externos");
    
    if (window.aa_availability) {
      const externalBusy = window.aa_availability.busy || [];
      const localBusy = aa_local_availability.local_busy.map(slot => ({
        start: new Date(slot.start),
        end: new Date(slot.end)
      }));
      
      window.aa_availability.busy = [...externalBusy, ...localBusy];
      
      console.log(`✅ Total combinado: ${window.aa_availability.busy.length}`);
      console.log(`   - Google Calendar: ${externalBusy.length}`);
      console.log(`   - Local: ${localBusy.length}`);
      
      document.dispatchEvent(new CustomEvent('aa:availability:updated', {
        detail: window.aa_availability
      }));
    }
  }
}

// ==============================
// 🔹 Iniciar AvailabilityProxy
// ==============================
function startAvailabilityProxy() {
  console.log('aa_debug: aa_backend =>', typeof aa_backend !== 'undefined' ? aa_backend : 'undefined');

  if (typeof window.AvailabilityProxy === 'undefined') {
    console.error("❌ AvailabilityProxy no está disponible");
    return;
  }

  const config = {
    ajaxUrl: (typeof aa_backend !== 'undefined' && aa_backend.ajax_url) 
      ? aa_backend.ajax_url 
      : '/wp-admin/admin-ajax.php',
    action: (typeof aa_backend !== 'undefined' && aa_backend.action) 
      ? aa_backend.action 
      : 'aa_get_availability',
    email: (typeof aa_backend !== 'undefined' && aa_backend.email) 
      ? aa_backend.email 
      : '',
    maxAttempts: 20,
    retryInterval: 15000
  };

  console.log('🚀 Iniciando AvailabilityProxy con configuración:', config);

  const availabilityProxy = new window.AvailabilityProxy(config);
  availabilityProxy.start();
}

// ==============================
// 🔹 Procesar calendario con disponibilidad
// ==============================
function processCalendar(fechaInputSelector, slotContainerSelector, isAdmin) {
  const fechaInput = document.querySelector(fechaInputSelector);
  
  if (!fechaInput) {
    console.warn(`⚠️ No se encontró ${fechaInputSelector}`);
    return;
  }
  
  if (typeof flatpickr === "undefined") {
    console.error('❌ Flatpickr no disponible');
    return;
  }

  const aa_schedule = window.aa_schedule || {};
  const aa_future_window = window.aa_future_window || 14;
  const slotDuration = parseInt(window.aa_slot_duration, 10) || 60;

  console.log(`📊 Configuración:`);
  console.log(`   - Horario:`, aa_schedule);
  console.log(`   - Duración: ${slotDuration} min`);
  console.log(`   - Ventana: ${aa_future_window} días`);

  const busy = (window.aa_availability?.busy) || [];
  console.log(`   - Eventos ocupados: ${busy.length}`);

  const busyRanges = busy.map(ev => ({
    start: new Date(ev.start),
    end: new Date(ev.end)
  }));

  const minDate = new Date();
  const maxDate = new Date();
  maxDate.setDate(minDate.getDate() + aa_future_window);

  const availableSlotsPerDay = {};
  
  for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
    const day = new Date(d);
    const weekday = getWeekdayName(day);
    const intervals = getDayIntervals(aa_schedule, weekday);
    const slots = generateSlotsForDay(day, intervals, busyRanges, slotDuration);
    
    availableSlotsPerDay[ymd(day)] = slots;
    
    if (slots.length > 0) {
      console.log(`📅 ${ymd(day)}: ${slots.length} slots`);
    }
  }

  const isDateAvailable = (date) => (availableSlotsPerDay[ymd(date)]?.length || 0) > 0;
  const disableDate = (date) => !isDateAvailable(date);

  if (isAdmin) {
    renderAdminCalendar(fechaInput, slotContainerSelector, availableSlotsPerDay, {
      minDate,
      maxDate,
      aa_schedule,
      busyRanges,
      slotDuration
    });
  } else {
    if (typeof window.CalendarUI !== 'undefined') {
      window.CalendarUI.rebuildCalendar({
        fechaInput,
        minDate,
        maxDate,
        disableDateCallback: disableDate,
        onDateSelected: (selectedDate) => {
          const slots = availableSlotsPerDay[ymd(selectedDate)] || [];
          renderSlots(slotContainerSelector, slots, selectedDate, fechaInput, false);
          return { selectedSlotISO: slots[0]?.toISOString() || null };
        }
      });
    }
  }
}

// ==============================
// 🔹 Renderizado admin
// ==============================
function renderAdminCalendar(fechaInput, slotContainerSelector, availableSlotsPerDay, config) {
  const { minDate, maxDate, aa_schedule, busyRanges, slotDuration } = config;
  
  if (fechaInput._flatpickr) fechaInput._flatpickr.destroy();
  
  flatpickr(fechaInput, {
    disableMobile: true,
    dateFormat: "d-m-Y",
    minDate,
    maxDate,
    locale: "es",
    disable: [(date) => (availableSlotsPerDay[ymd(date)]?.length || 0) === 0],
    onChange: function(selectedDates) {
      if (!selectedDates.length) return;
      const sel = selectedDates[0];
      const validSlots = availableSlotsPerDay[ymd(sel)] || [];
      renderSlots(slotContainerSelector, validSlots, sel, fechaInput, true);
    }
  });
  
  console.log('✅ Calendario admin renderizado');
}

// ==============================
// 🔹 Renderizado de slots
// ==============================
function renderSlots(containerId, validSlots, selectedDate, fechaInput, isAdmin) {
  if (isAdmin) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (!validSlots.length) {
      container.textContent = 'No hay horarios disponibles.';
      return;
    }
    
    const select = document.createElement('select');
    select.id = 'slot-selector-admin';
    select.style.width = '100%';
    select.style.padding = '8px';
    
    validSlots.forEach(date => {
      const option = document.createElement('option');
      option.value = date.toISOString();
      option.textContent = date.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
      select.appendChild(option);
    });
    
    select.addEventListener('change', () => {
      const chosen = new Date(select.value);
      fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosen.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`;
    });
    
    container.appendChild(select);
    
    if (validSlots[0]) {
      fechaInput.value = `${selectedDate.toLocaleDateString()} ${validSlots[0].toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`;
    }
  } else {
    if (typeof window.SlotSelectorUI !== 'undefined') {
      window.SlotSelectorUI.renderAvailableSlots(containerId, validSlots, chosen => {
        fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosen.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`;
      });
    }
    
    if (validSlots[0]) {
      fechaInput.value = `${selectedDate.toLocaleDateString()} ${validSlots[0].toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`;
    }
  }
}

// ==============================
// 🔹 Inicialización del controlador
// ==============================
export function initAvailabilityController(config) {
  const {
    fechaInputSelector,
    slotContainerSelector,
    isAdmin = false
  } = config;

  console.log("📋 AvailabilityController inicializado");

  // ✅ Iniciar proxy de disponibilidad
  startAvailabilityProxy();

  // ✅ Escuchar datos de disponibilidad
  document.addEventListener("aa:availability:loaded", () => {
    console.log("✅ Datos de disponibilidad recibidos");
    
    combineAvailabilityData();
    processCalendar(fechaInputSelector, slotContainerSelector, isAdmin);
  });

  // ✅ Escuchar errores
  document.addEventListener('aa:availability:error', (event) => {
    console.error("❌ Error al cargar disponibilidad:", event.detail);
  });
}

// ==============================
// 🔹 Exponer en window
// ==============================
window.AvailabilityController = {
  init: initAvailabilityController
};

console.log('✅ AvailabilityController cargado');