// ==============================
// 🔹 Punto de entrada principal del admin
// ==============================

document.addEventListener('DOMContentLoaded', function () {
  console.log('🚀 Inicializando aplicación admin...');

  // ✅ Inicializar controlador de disponibilidad PRIMERO
  if (typeof window.AvailabilityController !== 'undefined') {
    window.AvailabilityController.init({
      fechaInputSelector: '#cita-fecha',
      slotContainerSelector: 'slot-container-admin',
      isAdmin: true
    });
  } else {
    console.error('❌ AvailabilityController no está cargado');
  }

  // ✅ Inicializar controlador de reservas
  const btnToggle = document.getElementById('btn-toggle-form-nueva-cita');
  const formNuevaCita = document.getElementById('form-nueva-cita');
  const btnCancelar = document.getElementById('btn-cancelar-cita-form');
  const form = document.getElementById('form-crear-cita-admin');

  if (typeof window.AdminReservationController !== 'undefined') {
    window.AdminReservationController.init({
      btnToggle,
      formNuevaCita,
      btnCancelar,
      form
    });
  } else {
    console.error('❌ AdminReservationController no está cargado');
  }

  console.log('✅ Aplicación admin inicializada correctamente');
});