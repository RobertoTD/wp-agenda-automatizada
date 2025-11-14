// ==============================
// 🔹 Punto de entrada principal del frontend
// ==============================

document.addEventListener('DOMContentLoaded', function () {
  console.log('🚀 Inicializando aplicación frontend...');

  // ==============================
  // 🔹 1. Inicializar calendario básico
  // ==============================
  if (typeof window.CalendarUI !== 'undefined') {
    window.CalendarUI.initBasicCalendar("#fecha");
  } else {
    console.error('❌ CalendarUI no está cargado');
  }

  // ==============================
  // 🔹 2. Inicializar controlador de disponibilidad
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
  // 🔹 3. Inicializar controlador de reservas
  // ==============================
  if (typeof window.ReservationController !== 'undefined') {
    window.ReservationController.init('#agenda-form');
  } else {
    console.error('❌ ReservationController no está cargado');
  }

  console.log('✅ Aplicación frontend inicializada correctamente');
});