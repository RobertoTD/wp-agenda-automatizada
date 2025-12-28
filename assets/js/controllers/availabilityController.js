/**
 * Controlador de Disponibilidad
 */
(function() {
  'use strict';

  // ==============================
  // 🔹 Referencias locales (dentro del IIFE)
  // ==============================
  const AvailabilityService = window.AvailabilityService;
  const ymd = window.DateUtils.ymd;
  const hm = window.DateUtils.hm;

  // ==============================
  // 🔹 Variables del módulo
  // ==============================
  let availabilityProxyInstance = null;
  let calendarInstance = null;
  let slotsInstance = null;

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
      // ========== FRONTEND: Usar WPAgenda adaptadores ==========
      const calendarAdapterInstance = WPAgenda?.ui?.calendar || window.calendarDefaultAdapter.create();
      const slotsAdapterInstance = WPAgenda?.ui?.slots || window.slotsDefaultAdapter.create();

      calendarInstance = calendarAdapterInstance;
      slotsInstance = slotsAdapterInstance;

      calendarInstance.render({
        container: '#wpagenda-calendar',
        minDate,
        maxDate,
        disableDateFn,
        onDateSelected: (selectedDate) => {
          const slots = availableSlotsPerDay[ymd(selectedDate)] || [];

          // Mostrar título solo si hay slots
          const titleEl = document.getElementById('aa-slot-title');
          if (slots.length > 0) {
            titleEl.innerText = 'Horarios disponibles';
            titleEl.style.display = 'block';
          } else {
            titleEl.style.display = 'none';
          }

          slotsInstance.render('#slot-container', slots, (chosenSlot) => {
            const fechaStr = ymd(selectedDate);
            const horaStr = hm(chosenSlot);
            fechaInput.value = `${fechaStr} ${horaStr}`;
            
            const slotInput = document.getElementById('slot-selector');
            if (slotInput) {
              slotInput.value = chosenSlot.toISOString();
            }
          });

          if (slots[0]) {
            const fechaStr = ymd(selectedDate);
            const horaStr = hm(slots[0]);
            fechaInput.value = `${fechaStr} ${horaStr}`;
            
            const slotInput = document.getElementById('slot-selector');
            if (slotInput) {
              slotInput.value = slots[0].toISOString();
            }
          }
        }
      });
      console.log('✅ Calendario FRONTEND renderizado con WPAgenda adaptadores');
      return calendarInstance;
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

    // 🆕 Obtener estado de sincronización con Google Calendar
    const gsyncStatus = (typeof aa_backend !== 'undefined' && aa_backend.gsync_status)
      ? aa_backend.gsync_status
      : 'active';

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

    // ✅ Instanciar siempre para evitar errores en UI
    availabilityProxyInstance = new window.AvailabilityProxy(config);
    window.availabilityProxyInstance = availabilityProxyInstance;
    
    // ✅ IMPORTANTE: Siempre inyectar datos locales al proxy para que onChange funcione
    const localBusyRanges = window.AvailabilityService.loadLocal();
    availabilityProxyInstance.busyRanges = localBusyRanges;
    availabilityProxyInstance.availableSlotsPerDay = initialData.availableSlotsPerDay || {};
    
    console.log(`📊 Proxy inicializado con ${Object.keys(availabilityProxyInstance.availableSlotsPerDay).length} días locales`);
    
    // 🛑 CLÁUSULA DE GUARDA EXTENDIDA: Sin email O gsync desconectado = MODO LOCAL
    if (!email || gsyncStatus === 'disconnected') {
      const reason = !email 
        ? 'aa_google_email no configurado'
        : 'Google Calendar desconectado (gsync_status = disconnected)';
      console.warn(`⚠️ ${reason}. Operando en modo LOCAL SOLAMENTE.`);
      return;
    }

    console.log('✅ Email detectado y gsync activo. Iniciando sincronización con Google Calendar...');

    // ✅ IMPORTANTE: Registrar listener ANTES de iniciar
    const handleAvailabilityLoaded = (event) => {
      if (!event.detail || !event.detail.proxy) {
        console.warn('⚠️ Evento recibido sin proxy, ignorando...');
        return;
      }
      
      const proxy = event.detail.proxy;
      const { schedule, futureWindow, slotDuration } = initialData;
      const updatedSlotsMap = proxy.calculateAvailableSlots(schedule, futureWindow, slotDuration);
      
      refreshUI(fechaInputSelector, slotContainerSelector, isAdmin, {
        ...initialData,
        availableSlotsPerDay: updatedSlotsMap,
        proxy
      });
      
      console.log('✅ UI refrescada, listener permanece activo para futuras actualizaciones');
    };

    document.addEventListener('aa:availability:loaded', handleAvailabilityLoaded);
    availabilityProxyInstance.start();
  }

  // ==============================
  // 🔹 PASO 5: Refrescar UI con datos externos
  // ==============================
  function refreshUI(fechaInputSelector, slotContainerSelector, isAdmin, updatedData) {
    console.log('🔄 Refrescando UI con datos actualizados...');
    
    const fechaInput = document.querySelector(fechaInputSelector);
    
    const hasCalendar = isAdmin ? (fechaInput && fechaInput._flatpickr) : (calendarInstance !== null);
    if (!hasCalendar) {
      console.warn('⚠️ No se puede refrescar: calendario no encontrado');
      return;
    }

    const { availableSlotsPerDay, minDate, maxDate, proxy } = updatedData;
    const disableDateFn = (date) => !proxy.isDateAvailable(date);

    const currentSelectedDate = isAdmin 
      ? fechaInput._flatpickr.selectedDates[0]
      : calendarInstance.getSelectedDate();

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
        
        const dateToSelect = currentSelectedDate && proxy.isDateAvailable(currentSelectedDate)
          ? currentSelectedDate
          : AvailabilityService.findFirstAvailable(minDate, maxDate, availableSlotsPerDay);
        
        if (dateToSelect && picker) {
          const validSlots = window.AvailabilityService.slotsForDate(proxy, dateToSelect);
          
          console.log(`📅 Admin: Actualizando slots para ${ymd(dateToSelect)}`);
          console.log(`📅 Admin: ${validSlots.length} slots disponibles (con Google)`);
          
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
      if (calendarInstance) {
        calendarInstance.destroy();
      }
      
      const calendarAdapterInstance = WPAgenda?.ui?.calendar || window.calendarDefaultAdapter.create();
      calendarInstance = calendarAdapterInstance;
      
      calendarInstance.render({
        container: '#wpagenda-calendar',
        minDate,
        maxDate,
        disableDateFn: (date) => window.AvailabilityService.disable(proxy, date),
        onDateSelected: (selectedDate) => {
          const slots = window.AvailabilityService.slotsForDate(proxy, selectedDate);

          // Mostrar título solo si hay slots
          const titleEl = document.getElementById('aa-slot-title');
          if (slots.length > 0) {
            titleEl.innerText = 'Horarios disponibles';
            titleEl.style.display = 'block';
          } else {
            titleEl.style.display = 'none';
          }

          slotsInstance.render('#slot-container', slots, (chosenSlot) => {
            const input = document.querySelector(fechaInputSelector);
            if (input) {
              const fechaStr = ymd(selectedDate);
              const horaStr = hm(chosenSlot);
              input.value = `${fechaStr} ${horaStr}`;
            }
            
            const slotInput = document.getElementById('slot-selector');
            if (slotInput) {
              slotInput.value = chosenSlot.toISOString();
            }
          });

          if (slots[0]) {
            const input = document.querySelector(fechaInputSelector);
            if (input) {
              const fechaStr = ymd(selectedDate);
              const horaStr = hm(slots[0]);
              input.value = `${fechaStr} ${horaStr}`;
            }
            
            const slotInput = document.getElementById('slot-selector');
            if (slotInput) {
              slotInput.value = slots[0].toISOString();
            }
          }
        }
      });
      
      if (currentSelectedDate && proxy.isDateAvailable(currentSelectedDate)) {
        calendarInstance.setDate(currentSelectedDate, true);
      }
      
      console.log('✅ Calendario FRONTEND actualizado con WPAgenda adaptadores');
    }
  }

  // ==============================
  // 🔹 FUNCIÓN PRINCIPAL
  // ==============================
  function initAvailabilityController(config) {
    const {
      fechaInputSelector,
      slotContainerSelector = '#slot-container',
      isAdmin = false
    } = config;

    console.log('\n🚀 INICIANDO AVAILABILITY CONTROLLER');
    console.log(`🚀 Modo: ${isAdmin ? 'ADMIN' : 'FRONTEND'}\n`);

    const localBusyRanges = AvailabilityService.loadLocal();
    const initialData = AvailabilityService.calculateInitial(localBusyRanges);

    renderInitialUI(fechaInputSelector, slotContainerSelector, isAdmin, initialData);
    startGoogleCalendarSync(fechaInputSelector, slotContainerSelector, isAdmin, initialData);
  }

  // ==============================
  // 🔹 Exponer en window
  // ==============================
  window.AvailabilityController = {
    init: initAvailabilityController
  };

  console.log('✅ AvailabilityController cargado (flujo corregido)');
})();