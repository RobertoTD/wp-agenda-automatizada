document.addEventListener("DOMContentLoaded", () => {
  const fechaInput = document.getElementById("fecha") || document.getElementById("cita-fecha");
  
  console.log('aa_debug: fechaInput =>', !!fechaInput);
  console.log('aa_debug: fechaInput ID =>', fechaInput ? fechaInput.id : 'null');
  console.log('aa_debug: aa_backend =>', typeof aa_backend !== 'undefined' ? aa_backend : 'undefined');

  if (!fechaInput) {
    console.warn('⚠️ aa_debug: No se encontró input #fecha ni #cita-fecha');
    return;
  }

  // ✅ LEER slot_duration desde localización
  const slotDuration = (typeof window.aa_slot_duration !== 'undefined') 
    ? window.aa_slot_duration 
    : 60;
  
  console.log(`⚙️ Duración de slot configurada: ${slotDuration} minutos`);

  // ✅ Configurar e iniciar el proxy de disponibilidad
  if (typeof window.AvailabilityProxy !== 'undefined') {
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

    const availabilityProxy = new window.AvailabilityProxy(config);
    availabilityProxy.start();
  } else {
    console.error("❌ AvailabilityProxy no está disponible");
  }
});
