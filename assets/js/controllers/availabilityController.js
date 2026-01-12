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

    const { availableDays, minDate, maxDate } = initialData;

    // Función helper para determinar si una fecha está disponible
    const isDateAvailable = (date) => {
      return !!availableDays[ymd(date)];
    };

    const disableDateFn = (date) => !isDateAvailable(date);

    if (isAdmin) {
      if (typeof window.CalendarAdminUI !== 'undefined') {
        // Para ADMIN, crear un slotsMap vacío ya que no calculamos slots iniciales
        const emptySlotsMap = {};
        Object.keys(availableDays).forEach(day => {
          emptySlotsMap[day] = [];
        });
        
        const picker = window.CalendarAdminUI.render({
          fechaInput,
          slotContainerSelector,
          slotsMap: emptySlotsMap,
          minDate,
          maxDate,
          disableDateFn
        });
        
        console.log('✅ Calendario ADMIN renderizado con datos locales');
        console.log('ℹ️ Admin: Slots se calcularán on-demand cuando se seleccione una fecha');
        
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
          // NO hacer nada aquí.
          // El cálculo de slots se maneja por FrontendAssignmentsController
          // o por el flujo fixed on-demand vía eventos slotsCalculated.
          console.log('📅 [AvailabilityController] Fecha seleccionada:', ymd(selectedDate));
          console.log('ℹ️ [AvailabilityController] Esperando cálculo de slots vía eventos...');
        }
      });
      console.log('✅ Calendario FRONTEND renderizado con WPAgenda adaptadores');
      return calendarInstance;
    }

    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
  }


  // ==============================
  // 🔹 Helper: Crear callback de selección de slot
  // ==============================
  function createSlotSelectionCallback(fechaInputSelector, selectedDate) {
    return function(chosenSlot) {
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
    };
  }

  // ==============================
  // 🔹 Registrar listener para slots calculados por assignments (FRONTEND)
  // ==============================
  function setupAssignmentsSlotsListener(fechaInputSelector, slotContainerSelector) {
    if (typeof window.WPAgenda === 'undefined' || typeof window.WPAgenda.on !== 'function') {
      console.warn('⚠️ [AvailabilityController] WPAgenda.on no disponible, no se puede escuchar slotsCalculated');
      return;
    }

    window.WPAgenda.on('slotsCalculated', function(data) {
      console.log('📥 [AvailabilityController] Evento slotsCalculated recibido:', data);

      if (!data || !data.slots || !Array.isArray(data.slots)) {
        console.warn('⚠️ [AvailabilityController] Evento slotsCalculated sin slots válidos');
        return;
      }

      // Asegurar que slotsInstance esté disponible
      if (!slotsInstance) {
        slotsInstance = WPAgenda?.ui?.slots || window.slotsDefaultAdapter.create();
      }

      const selectedDate = data.selectedDate ? new Date(data.selectedDate + 'T00:00:00') : null;
      
      // Mostrar título solo si hay slots
      const titleEl = document.getElementById('aa-slot-title');
      if (data.slots.length > 0) {
        if (titleEl) {
          titleEl.innerText = 'Horarios disponibles';
          titleEl.style.display = 'block';
        }
      } else {
        if (titleEl) {
          titleEl.style.display = 'none';
        }
      }

      // Renderizar slots usando el adaptador
      // Asegurar que el selector tenga el prefijo '#' (requerido por slotsDefaultAdapter)
      const normalizedSelector = slotContainerSelector.startsWith('#') 
        ? slotContainerSelector 
        : '#' + slotContainerSelector;
      
      const onSelectCallback = createSlotSelectionCallback(fechaInputSelector, selectedDate);
      slotsInstance.render(normalizedSelector, data.slots, onSelectCallback);

      console.log('✅ [AvailabilityController] Slots renderizados desde assignments:', data.slots.length);
    });

    console.log('✅ [AvailabilityController] Listener slotsCalculated registrado para FRONTEND');
  }

  // ==============================
  // 🔹 FUNCIÓN PRINCIPAL
  // ==============================
  async function initAvailabilityController(config) {
    const {
      fechaInputSelector,
      slotContainerSelector = '#slot-container',
      isAdmin = false
    } = config;

    console.log('\n🚀 INICIANDO AVAILABILITY CONTROLLER');
    console.log(`🚀 Modo: ${isAdmin ? 'ADMIN' : 'FRONTEND'}\n`);

    const localBusyRanges = AvailabilityService.loadLocal();
    const initialData = await AvailabilityService.calculateInitial(localBusyRanges);

    renderInitialUI(fechaInputSelector, slotContainerSelector, isAdmin, initialData);
    
    // Registrar listener para slots calculados por assignments (solo FRONTEND)
    if (!isAdmin) {
      setupAssignmentsSlotsListener(fechaInputSelector, slotContainerSelector);
    }
  }

  // ==============================
  // 🔹 Exponer en window
  // ==============================
  window.AvailabilityController = {
    init: initAvailabilityController
  };

  console.log('✅ AvailabilityController cargado (flujo corregido)');
})();