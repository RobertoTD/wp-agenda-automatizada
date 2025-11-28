import { AvailabilityService } from '../services/availabilityService.js';

// ==============================
// 🔹 Importar utilidades desde dateUtils.js
// ==============================
const { ymd } = window.DateUtils;

// ==============================
// 🔹 Variable global para almacenar el proxy
// ==============================
let availabilityProxyInstance = null;

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
        const firstAvailableDate = AvailabilityService.findFirstAvailable(
          minDate, 
          maxDate, 
          availableSlotsPerDay
        );
        
        if (firstAvailableDate) {
          const validSlots = availableSlotsPerDay[ymd(firstAvailableDate)] || [];
          
          console.log(`📅 Admin: Renderizando slots iniciales para ${ymd(firstAvailableDate)}`);
          console.log(`📅 Admin: ${validSlots.length} slots disponibles`);
          console.log(`📅 Admin: Slots:`, validSlots.map(s => s.toLocaleTimeString('es-MX')));
          
          // ✅ Esperar un tick para asegurar que el listener esté registrado
          setTimeout(() => {
            // Disparar evento para que SlotSelectorAdminUI renderice los slots
            document.dispatchEvent(new CustomEvent('aa:admin:date-selected', {
              detail: {
                containerId: slotContainerSelector,
                validSlots,
                selectedDate: firstAvailableDate,
                fechaInput
              }
            }));
          }, 0);
          
          // Establecer fecha en Flatpickr (sin disparar onChange)
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

// Validar si existe email configurado
  const email = (typeof aa_backend !== 'undefined' && aa_backend.email) 
    ? aa_backend.email 
    : '';

   const config = {
    ajaxUrl: (typeof aa_backend !== 'undefined' && aa_backend.ajax_url) 
      ? aa_backend.ajax_url 
      : '/wp-admin/admin-ajax.php',
    action: (typeof aa_backend !== 'undefined' && aa_backend.action) 
      ? aa_backend.action 
      : 'aa_get_availability',
    email: email,
    maxAttempts: 20,
    retryInterval: 15000
  };

  // ✅ Instanciar siempre para evitar errores en UI (AdminReservationController depende de esta instancia global)
  availabilityProxyInstance = new window.AvailabilityProxy(config);
  window.availabilityProxyInstance = availabilityProxyInstance;
  
// 🛑 CLÁUSULA DE GUARDA: Si no hay email, nos quedamos en MODO LOCAL
  if (!email) {
    console.warn('⚠️ aa_google_email no configurado. Operando en modo LOCAL SOLAMENTE.');
    console.log('✅ Inyectando datos locales en el proxy para mantener consistencia en UI.');
    
    // ✅ Asignar busyRanges locales al proxy
    const localBusyRanges = window.AvailabilityService.loadLocal();
    availabilityProxyInstance.busyRanges = localBusyRanges;
    
    // Inyectamos los slots locales calculados en el paso anterior al proxy
    // Esto permite que calendarAdminUI.js use window.availabilityProxyInstance.getSlotsForDate() sin errores
    availabilityProxyInstance.availableSlotsPerDay = initialData.availableSlotsPerDay || {};
    
    console.log(`📊 Proxy inicializado con ${Object.keys(availabilityProxyInstance.availableSlotsPerDay).length} días y ${availabilityProxyInstance.busyRanges.length} eventos ocupados`);
    
    return; // ⛔️ Terminamos aquí. No iniciamos el loop de fetch.
  }

    // --- Si llegamos aquí, hay email. Iniciamos sincronización ---
  console.log('✅ Email detectado. Iniciando sincronización con Google Calendar...');


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
    
    // ✅ NO remover listener para permitir actualizaciones futuras
    console.log('✅ UI refrescada, listener permanece activo para futuras actualizaciones');
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
        : AvailabilityService.findFirstAvailable(minDate, maxDate, availableSlotsPerDay);
      
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

  console.log('\n🚀 INICIANDO AVAILABILITY CONTROLLER');
  console.log(`🚀 Modo: ${isAdmin ? 'ADMIN' : 'FRONTEND'}\n`);

  // 1️⃣ Cargar disponibilidad LOCAL (delegado al servicio)
  const localBusyRanges = AvailabilityService.loadLocal();

  // 2️⃣ Calcular slots iniciales (delegado al servicio)
  const initialData = AvailabilityService.calculateInitial(localBusyRanges);

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