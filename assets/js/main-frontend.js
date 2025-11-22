// ==============================
// 🔹 Punto de entrada principal del frontend
// ==============================

document.addEventListener('DOMContentLoaded', function () {
  console.log('🚀 Inicializando aplicación frontend...');

  // ==============================
  // 🔹 FASE 1: Inicializar calendario básico INMEDIATAMENTE
  // ==============================
  const fechaInput = document.querySelector('#fecha');
  
  if (fechaInput && typeof window.CalendarUI !== 'undefined') {
    console.log('📅 Inicializando calendario básico (sin reglas de disponibilidad)...');
    window.CalendarUI.initBasicCalendar('#fecha');
  } else {
    if (!fechaInput) {
      console.warn('⚠️ Input #fecha no encontrado');
    }
    if (typeof window.CalendarUI === 'undefined') {
      console.error('❌ CalendarUI no está disponible');
    }
  }

  // ==============================
  // 🔹 FASE 2: Inicializar controlador de disponibilidad
  // (Se activará cuando lleguen los datos de Google Calendar)
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
  // 🔹 Inicializar controlador de reservas
  // ==============================
  if (typeof window.ReservationController !== 'undefined') {
    window.ReservationController.init('#agenda-form');
  } else {
    console.error('❌ ReservationController no está cargado');
  }

  console.log('✅ Aplicación frontend inicializada correctamente');
});