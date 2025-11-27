// ==============================
// 🔹 Importar utilidades desde dateUtils.js
// ==============================
const { ymd, computeLimits } = window.DateUtils;

// ==============================
// 🔹 Variable global para almacenar el proxy
// ==============================
let availabilityProxyInstance = null;

// ==============================
// 🔹 PASO 1: Cargar disponibilidad LOCAL
// ==============================
function loadLocalAvailability() {
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('📦 CARGANDO DISPONIBILIDAD LOCAL');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

  const localBusyRanges = [];

  if (typeof window.aa_local_availability !== 'undefined' && window.aa_local_availability.local_busy) {
    console.log('✅ Datos locales encontrados:', window.aa_local_availability);
    
    window.aa_local_availability.local_busy.forEach((slot, index) => {
      const start = new Date(slot.start);
      const end = new Date(slot.end);
      
      if (!isNaN(start.getTime()) && !isNaN(end.getTime())) {
        localBusyRanges.push({ start, end });
        console.log(`   ${index + 1}. ${slot.start} → ${slot.end} | ${slot.title}`);
      }
    });
    
    console.log(`📊 Total eventos locales: ${localBusyRanges.length}`);
  } else {
    console.log('ℹ️ No hay datos locales de disponibilidad');
  }

  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
  
  return localBusyRanges;
}

// ==============================
// 🔹 PASO 2: Calcular slots SOLO con datos locales
// ==============================
function calculateInitialSlots(localBusyRanges) {
  const aa_schedule = window.aa_schedule || {};
  const futureWindow = window.aa_future_window || 14;
  const slotDuration = parseInt(window.aa_slot_duration, 10) || 60;

  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('🧮 CALCULANDO SLOTS INICIALES (SOLO LOCAL)');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log(`📊 Configuración:`);
  console.log(`   - Duración de slot: ${slotDuration} min`);
  console.log(`   - Ventana futura: ${futureWindow} días`);
  console.log(`   - Eventos ocupados locales: ${localBusyRanges.length}`);

  const minDate = new Date();
  const maxDate = new Date();
  maxDate.setDate(minDate.getDate() + futureWindow);

  const availableSlotsPerDay = {};

  for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
    const day = new Date(d);
    const weekday = window.DateUtils.getWeekdayName(day);
    const intervals = window.DateUtils.getDayIntervals(aa_schedule, weekday);
    const slots = window.DateUtils.generateSlotsForDay(day, intervals, localBusyRanges, slotDuration);
    
    availableSlotsPerDay[ymd(day)] = slots;
    
    if (slots.length > 0) {
      console.log(`📅 ${ymd(day)}: ${slots.length} slots disponibles`);
    }
  }

  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

  return {
    availableSlotsPerDay,
    schedule: aa_schedule,
    futureWindow,
    slotDuration,
    minDate,
    maxDate
  };
}

// ==============================
// 🔹 PASO 3: Renderizar UI con datos iniciales
// ==============================
function renderInitialUI(fechaInputSelector, slotContainerSelector, isAdmin, initialData) {
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('🎨 RENDERIZANDO UI INICIAL');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

  const fechaInput = document.querySelector(fechaInputSelector);
  if (!fechaInput) {
    console.warn(`⚠️ No se encontró ${fechaInputSelector}`);
    return null;
  }

  const { availableSlotsPerDay, minDate, maxDate } = initialData;

  // Función helper para determinar si una fecha tiene slots
  const isDateAvailable = (date) => {
    return (availableSlotsPerDay[ymd(date)]?.length || 0) > 0;
  };

  const disableDateFn = (date) => !isDateAvailable(date);

  if (isAdmin) {
    if (typeof window.CalendarAdminUI !== 'undefined') {
      const picker = window.CalendarAdminUI.render({
        fechaInput,
        slotContainerSelector,
        slotsMap: availableSlotsPerDay,
        minDate,
        maxDate,
        disableDateFn
      });
      
      console.log('✅ Calendario ADMIN renderizado con datos locales');
      
      // ✅ RENDERIZAR SLOTS INICIALES para la primera fecha disponible
      if (picker) {
        const firstAvailableDate = findFirstAvailableDate(minDate, maxDate, availableSlotsPerDay);
        
        if (firstAvailableDate) {
          const validSlots = availableSlotsPerDay[ymd(firstAvailableDate)] || [];
          
          console.log(`📅 Admin: Renderizando slots iniciales para ${ymd(firstAvailableDate)}`);
          console.log(`📅 Admin: ${validSlots.length} slots disponibles`);
          
          // Disparar evento para que SlotSelectorAdminUI renderice los slots
          document.dispatchEvent(new CustomEvent('aa:admin:date-selected', {
            detail: {
              containerId: slotContainerSelector,
              validSlots,
              selectedDate: firstAvailableDate,
              fechaInput
            }
          }));
          
          // Establecer fecha en Flatpickr
          picker.setDate(firstAvailableDate, false);
        }
      }
      
      return picker;
    } else {
      console.error('❌ CalendarAdminUI no disponible');
    }
  } else {
    if (typeof window.CalendarUI !== 'undefined') {
      const picker = window.CalendarUI.render({
        fechaInput,
        minDate,
        maxDate,
        disableDateFn,
        onDateSelected: (selectedDate) => {
          const slots = availableSlotsPerDay[ymd(selectedDate)] || [];

          if (typeof window.SlotSelectorUI !== 'undefined') {
            window.SlotSelectorUI.render(slotContainerSelector, slots, (chosenSlot) => {
              fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosenSlot.toLocaleTimeString('es-MX', {
                hour: '2-digit',
                minute: '2-digit'
              })}`;
            });
          }

          if (slots[0]) {
            fechaInput.value = `${selectedDate.toLocaleDateString()} ${slots[0].toLocaleTimeString('es-MX', {
              hour: '2-digit',
              minute: '2-digit'
            })}`;
          }
        }
      });
      console.log('✅ Calendario FRONTEND renderizado con datos locales');
      return picker;
    } else {
      console.error('❌ CalendarUI no disponible');
    }
  }

  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
}

// ==============================
// 🔹 Helper: Encontrar primera fecha disponible
// ==============================
function findFirstAvailableDate(minDate, maxDate, availableSlotsPerDay) {
  for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
    const day = new Date(d);
    const slots = availableSlotsPerDay[ymd(day)] || [];
    
    if (slots.length > 0) {
      console.log(`✅ Primera fecha disponible encontrada: ${ymd(day)}`);
      return day;
    }
  }
  
  console.warn('⚠️ No se encontró ninguna fecha disponible');
  return null;
}

// ==============================
// 🔹 PASO 4: Iniciar consulta a Google Calendar (async)
// ==============================
function startGoogleCalendarSync(fechaInputSelector, slotContainerSelector, isAdmin, initialData) {
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('🔄 INICIANDO SINCRONIZACIÓN CON GOOGLE CALENDAR');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

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

  availabilityProxyInstance = new window.AvailabilityProxy(config);
  window.availabilityProxyInstance = availabilityProxyInstance;
  
  // ✅ IMPORTANTE: Registrar listener ANTES de iniciar
  const handleAvailabilityLoaded = (event) => {
    // ✅ Validar que el evento incluye proxy
    if (!event.detail || !event.detail.proxy) {
      console.warn('⚠️ Evento recibido sin proxy, ignorando...');
      return;
    }
    
    const proxy = event.detail.proxy;
    const { schedule, futureWindow, slotDuration } = initialData;
    const updatedSlotsMap = proxy.calculateAvailableSlots(schedule, futureWindow, slotDuration);
    
    // Refrescar UI
    refreshUI(fechaInputSelector, slotContainerSelector, isAdmin, {
      ...initialData,
      availableSlotsPerDay: updatedSlotsMap,
      proxy
    });
    
    // ✅ Remover listener después de procesar
    document.removeEventListener('aa:availability:loaded', handleAvailabilityLoaded);
  };

  // Registrar listener
  document.addEventListener('aa:availability:loaded', handleAvailabilityLoaded);

  // Iniciar consulta
  availabilityProxyInstance.start();
}

// ==============================
// 🔹 PASO 5: Refrescar UI con datos externos
// ==============================
function refreshUI(fechaInputSelector, slotContainerSelector, isAdmin, updatedData) {
  console.log('🔄 Refrescando UI con datos actualizados...');
  
  const fechaInput = document.querySelector(fechaInputSelector);
  if (!fechaInput || !fechaInput._flatpickr) {
    console.warn('⚠️ No se puede refrescar: calendario no encontrado');
    return;
  }

  const { availableSlotsPerDay, minDate, maxDate, proxy } = updatedData;

  // Actualizar función de disable
  const disableDateFn = (date) => !proxy.isDateAvailable(date);

  // Obtener fecha actualmente seleccionada
  const currentSelectedDate = fechaInput._flatpickr.selectedDates[0];

  // Destruir y recrear Flatpickr con nuevos datos
  if (isAdmin) {
    if (typeof window.CalendarAdminUI !== 'undefined') {
      const picker = window.CalendarAdminUI.render({
        fechaInput,
        slotContainerSelector,
        slotsMap: availableSlotsPerDay,
        minDate,
        maxDate,
        disableDateFn: (date) => window.AvailabilityService.disable(proxy, date)
      });
      
      console.log('✅ Calendario ADMIN actualizado con datos de Google');
      
      // ✅ MANTENER fecha seleccionada o usar primera disponible
      const dateToSelect = currentSelectedDate && proxy.isDateAvailable(currentSelectedDate)
        ? currentSelectedDate
        : findFirstAvailableDate(minDate, maxDate, availableSlotsPerDay);
      
      if (dateToSelect && picker) {
        const validSlots = window.AvailabilityService.slotsForDate(proxy, dateToSelect);
        
        console.log(`📅 Admin: Actualizando slots para ${ymd(dateToSelect)}`);
        console.log(`📅 Admin: ${validSlots.length} slots disponibles (con Google)`);
        
        // Disparar evento para actualizar slots
        document.dispatchEvent(new CustomEvent('aa:admin:date-selected', {
          detail: {
            containerId: slotContainerSelector,
            validSlots,
            selectedDate: dateToSelect,
            fechaInput
          }
        }));
        
        picker.setDate(dateToSelect, false);
      }
    }
  } else {
    if (typeof window.CalendarUI !== 'undefined') {
      window.CalendarUI.render({
        fechaInput,
        minDate,
        maxDate,
        disableDateFn: (date) => window.AvailabilityService.disable(proxy, date),
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

          if (slots[0]) {
            fechaInput.value = `${selectedDate.toLocaleDateString()} ${slots[0].toLocaleTimeString('es-MX', {
              hour: '2-digit',
              minute: '2-digit'
            })}`;
          }
        }
      });
      console.log('✅ Calendario FRONTEND actualizado con datos de Google');
    }
  }
}

// ==============================
// 🔹 FUNCIÓN PRINCIPAL: Inicialización con flujo correcto
// ==============================
export function initAvailabilityController(config) {
  const {
    fechaInputSelector,
    slotContainerSelector,
    isAdmin = false
  } = config;

  console.log('\n🚀 ============================================');
  console.log('🚀 INICIANDO AVAILABILITY CONTROLLER');
  console.log(`🚀 Modo: ${isAdmin ? 'ADMIN' : 'FRONTEND'}`);
  console.log('🚀 ============================================\n');

  // 1️⃣ Cargar disponibilidad LOCAL
  const localBusyRanges = loadLocalAvailability();

  // 2️⃣ Calcular slots iniciales SOLO con local
  const initialData = calculateInitialSlots(localBusyRanges);

  // 3️⃣ Renderizar UI inmediatamente con datos locales
  renderInitialUI(fechaInputSelector, slotContainerSelector, isAdmin, initialData);

  // 4️⃣ Iniciar sincronización con Google Calendar (async)
  startGoogleCalendarSync(fechaInputSelector, slotContainerSelector, isAdmin, initialData);
}

// ==============================
// 🔹 Exponer en window
// ==============================
window.AvailabilityController = {
  init: initAvailabilityController
};

console.log('✅ AvailabilityController cargado (flujo corregido)');