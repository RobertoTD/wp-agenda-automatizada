// ==============================
// 🔹 Importar utilidades desde dateUtils.js
// ==============================
const { ymd } = window.DateUtils;

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
// 🔹 Variable global para almacenar el proxy
// ==============================
let availabilityProxyInstance = null;

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

  availabilityProxyInstance = new window.AvailabilityProxy(config);
  availabilityProxyInstance.start();
  
  return availabilityProxyInstance;
}

// ==============================
// 🔹 Procesar calendario con disponibilidad
// ==============================
function processCalendar(fechaInputSelector, slotContainerSelector, isAdmin, proxy) {
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

  console.log(`📊 Configuración del calendario:`);
  console.log(`   - Horario:`, aa_schedule);
  console.log(`   - Duración de slot: ${slotDuration} min`);
  console.log(`   - Ventana futura: ${aa_future_window} días`);
  console.log(`   - Eventos ocupados: ${proxy.busyRanges.length}`);

  // ✅ Calcular slots disponibles usando el servicio
  const availableSlotsPerDay = proxy.calculateAvailableSlots(aa_schedule, aa_future_window, slotDuration);

  const minDate = new Date();
  const maxDate = new Date();
  maxDate.setDate(minDate.getDate() + aa_future_window);

  if (isAdmin) {
    renderAdminCalendar(fechaInput, slotContainerSelector, proxy, {
      minDate,
      maxDate,
      availableSlotsPerDay
    });
  } else {
    if (typeof window.CalendarUI !== 'undefined') {
      window.CalendarUI.rebuildCalendar({
        fechaInput,
        minDate,
        maxDate,
        disableDateCallback: (date) => proxy.disableDate(date),
        onDateSelected: (selectedDate) => {
          const slots = proxy.getSlotsForDate(selectedDate);
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
function renderAdminCalendar(fechaInput, slotContainerSelector, proxy, config) {
  const { minDate, maxDate } = config;
  
  if (fechaInput._flatpickr) fechaInput._flatpickr.destroy();
  
  flatpickr(fechaInput, {
    disableMobile: true,
    dateFormat: "d-m-Y",
    minDate,
    maxDate,
    locale: "es",
    disable: [(date) => proxy.disableDate(date)],
    onChange: function(selectedDates) {
      if (!selectedDates.length) return;
      const sel = selectedDates[0];
      const validSlots = proxy.getSlotsForDate(sel);
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
  const proxy = startAvailabilityProxy();

  // ✅ Escuchar datos de disponibilidad
  document.addEventListener("aa:availability:loaded", (event) => {
    console.log("✅ Datos de disponibilidad recibidos en el controlador");
    
    // Usar el proxy del evento (contiene busyRanges ya calculados)
    const proxyFromEvent = event.detail.proxy;
    
    processCalendar(fechaInputSelector, slotContainerSelector, isAdmin, proxyFromEvent);
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