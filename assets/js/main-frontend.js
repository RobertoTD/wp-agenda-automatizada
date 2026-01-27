// ==============================
// 🔹 Punto de entrada principal del frontend
// ==============================

document.addEventListener('DOMContentLoaded', function () {
  console.log('🚀 Inicializando aplicación frontend...');

  // ==============================
  // 🔹 FASE 1: Validar input de fecha y configuración
  // ==============================
  if (typeof window.CalendarUI !== 'undefined') {
    const fechaInput = window.CalendarUI.findDateInput();
    
    if (!fechaInput) {
      console.error('❌ No se encontró input de fecha, abortando inicialización');
      return;
    }

    // Leer duración de slot
    const slotDuration = window.CalendarUI.getSlotDuration();
    console.log(`✅ Input encontrado: #${fechaInput.id}`);
    console.log(`✅ Slot duration: ${slotDuration} min`);

    // ==============================
    // 🔹 FASE 2: Inicializar calendario básico INMEDIATAMENTE
    // ==============================
    console.log('📅 Inicializando calendario básico...');
    

  } else {
    console.error('❌ CalendarUI no está disponible');
  }

  // ==============================
  // 🔹 FASE 3: Inicializar controlador de disponibilidad
  // (Iniciará el proxy y se activará cuando lleguen los datos)
  // ==============================
  if (typeof window.AvailabilityController !== 'undefined') {
    window.AvailabilityController.init({
      fechaInputSelector: '#fecha',
      slotContainerSelector: 'slot-container',
      isAdmin: false
    });
  } else {
    console.error('❌ AvailabilityController no está cargado');
  }

  // ==============================
  // 🔹 FASE 4: Inicializar controlador de reservas
  // ==============================
  if (typeof window.ReservationController !== 'undefined') {
    window.ReservationController.init('#agenda-form');
  } else {
    console.error('❌ ReservationController no está cargado');
  }

  // ==============================
  // 🔹 FASE 5: Inicializar controlador de asignaciones (NUEVO - Fase 2)
  // ==============================
  if (typeof window.FrontendAssignmentsController !== 'undefined') {
    console.log('🔄 Inicializando FrontendAssignmentsController...');
    window.FrontendAssignmentsController.init({
      serviceSelect: '#servicio',
      dateInput: '#fecha',
      staffSelect: '#staff-selector'
    });
  } else {
    console.warn('⚠️ FrontendAssignmentsController no está cargado (opcional en Fase 2)');
  }

  // ==============================
  // 🔹 FASE 6: Inicializar botón flotante de WhatsApp
  // ==============================
  if (typeof window.WhatsAppController?.initFrontend === 'function') {
    window.WhatsAppController.initFrontend();
  }

  console.log('✅ Aplicación frontend inicializada correctamente');
});