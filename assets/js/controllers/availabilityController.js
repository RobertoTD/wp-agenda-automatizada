// ==============================
// 🔹 Importar utilidades desde dateUtils.js
// ==============================
const { ymd, computeLimits } = window.DateUtils;

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
  
  // Exponer globalmente para que CalendarAdminUI pueda accederlo
  window.availabilityProxyInstance = availabilityProxyInstance;
  
  return availabilityProxyInstance;
}

// ==============================
// 🔹 Procesar calendario con disponibilidad (REFACTORIZADO)
// ==============================
function processCalendar(fechaInputSelector, slotContainerSelector, isAdmin, proxy) {
  
  // 1️⃣ UI mínima: encontrar input
  const fechaInput = document.querySelector(fechaInputSelector);
  if (!fechaInput) {
    console.warn(`⚠️ No se encontró ${fechaInputSelector}`);
    return;
  }

  // 2️⃣ Cargar config del dominio (solo lectura, permitido)
  const aa_schedule = window.aa_schedule || {};
  const futureWindow = window.aa_future_window || 14;
  const slotDuration = parseInt(window.aa_slot_duration, 10) || 60;

  console.log(`📊 Configuración del calendario:`);
  console.log(`   - Horario:`, aa_schedule);
  console.log(`   - Duración de slot: ${slotDuration} min`);
  console.log(`   - Ventana futura: ${futureWindow} días`);
  console.log(`   - Eventos ocupados: ${proxy.busyRanges.length}`);

  // 3️⃣ Pedir al SERVICE que calcule slots
  const slotsMap = window.AvailabilityService.calculate(proxy, {
    schedule: aa_schedule,
    futureWindow,
    slotDuration
  });

  // 4️⃣ Pedir a UTILS los límites de fecha
  const { minDate, maxDate } = window.DateUtils.computeLimits(futureWindow);

  // 5️⃣ ORQUESTACIÓN: decidir qué UI usar
  if (isAdmin) {
    // Modo Admin
    if (typeof window.CalendarAdminUI !== 'undefined') {
      window.CalendarAdminUI.render({
        fechaInput,
        slotContainerSelector,
        slotsMap,
        minDate,
        maxDate,
        disableDateFn: (date) => window.AvailabilityService.disable(proxy, date)
      });
    } else {
      console.error('❌ CalendarAdminUI no está disponible');
    }
    return;
  }

  // Modo Frontend
  if (typeof window.CalendarUI !== 'undefined') {
    window.CalendarUI.render({
      fechaInput,
      minDate,
      maxDate,
      disableDateFn: (date) => window.AvailabilityService.disable(proxy, date),

      // Slot selection
      onDateSelected: (selectedDate) => {
        const slots = window.AvailabilityService.slotsForDate(proxy, selectedDate);

        if (typeof window.SlotSelectorUI !== 'undefined') {
          window.SlotSelectorUI.render(slotContainerSelector, slots, (chosenSlot) => {
            fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosenSlot.toLocaleTimeString('es-MX', {
              hour: '2-digit',
              minute: '2-digit'
            })}`;
          });
        }

        // Setear valor inicial
        if (slots[0]) {
          fechaInput.value = `${selectedDate.toLocaleDateString()} ${slots[0].toLocaleTimeString('es-MX', {
            hour: '2-digit',
            minute: '2-digit'
          })}`;
        }

        return { selectedSlotISO: slots[0]?.toISOString() || null };
      }
    });
  } else {
    console.error('❌ CalendarUI no está disponible');
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