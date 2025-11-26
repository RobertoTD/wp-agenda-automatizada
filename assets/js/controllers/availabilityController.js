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
  proxy.calculateAvailableSlots(aa_schedule, aa_future_window, slotDuration);

  const minDate = new Date();
  const maxDate = new Date();
  maxDate.setDate(minDate.getDate() + aa_future_window);

  // ✅ ORQUESTACIÓN: Decidir qué UI usar según el contexto
  if (isAdmin) {
    // Modo Admin: Usar CalendarAdminUI
    if (typeof window.CalendarAdminUI !== 'undefined') {
      window.CalendarAdminUI.renderAdminCalendar(fechaInput, slotContainerSelector, proxy, {
        minDate,
        maxDate
      });
    } else {
      console.error('❌ CalendarAdminUI no está disponible');
    }
  } else {
    // Modo Frontend: Usar CalendarUI
    if (typeof window.CalendarUI !== 'undefined') {
      window.CalendarUI.rebuildCalendar({
        fechaInput,
        minDate,
        maxDate,
        disableDateCallback: (date) => proxy.disableDate(date),
        onDateSelected: (selectedDate) => {
          const slots = proxy.getSlotsForDate(selectedDate);
          
          // Delegar renderizado de slots a SlotSelectorUI (frontend)
          if (typeof window.SlotSelectorUI !== 'undefined') {
            window.SlotSelectorUI.renderAvailableSlots(slotContainerSelector, slots, chosen => {
              fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosen.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`;
            });
          }
          
          // Setear valor inicial
          if (slots[0]) {
            fechaInput.value = `${selectedDate.toLocaleDateString()} ${slots[0].toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`;
          }
          
          return { selectedSlotISO: slots[0]?.toISOString() || null };
        }
      });
    } else {
      console.error('❌ CalendarUI no está disponible');
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

  console.log("📋 AvailabilityController inicializado", { isAdmin });

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