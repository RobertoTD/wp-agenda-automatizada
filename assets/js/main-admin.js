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

  // ✅ Inicializar controlador de reservas (solo para modal)
  // NOTA: El formulario inline legacy ya no existe, solo se usa el modal
  const btnCancelar = document.getElementById('btn-cancelar-cita-form');
  const form = document.getElementById('form-crear-cita-admin');

  if (form && typeof window.AdminReservationController !== 'undefined') {
    window.AdminReservationController.init({
      btnCancelar,
      form
    });
  } else if (!form) {
    // Form solo existe cuando el modal está abierto, esto es normal
    console.log('ℹ️ Formulario de reservas no encontrado (modal no abierto aún)');
  } else {
    console.error('❌ AdminReservationController no está cargado');
  }

  console.log('✅ Aplicación admin inicializada correctamente');
});