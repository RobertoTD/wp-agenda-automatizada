// ==============================
// 🔹 Servicio de reservas
// ==============================

/**
 * Guarda una reserva en el backend
 */
async function saveReservation(datos) {
  if (typeof wpaa_vars === 'undefined' || !wpaa_vars.ajax_url) {
    throw new Error('Variables de configuración no disponibles (wpaa_vars)');
  }

  console.group('🧩 saveReservation: datos que se enviarán al backend');
  console.log('Tipo de datos:', typeof datos);
  console.log('Contenido del objeto:', datos);
  console.log('Fecha ISO final enviada:', datos.fecha);
  console.groupEnd();

  // Validar estructura antes del fetch
  ['servicio', 'fecha', 'nombre', 'telefono'].forEach(campo => {
    if (!datos[campo]) {
      console.warn(`⚠️ El campo "${campo}" está vacío o indefinido`);
    }
  });

  try {
    const response = await fetch(wpaa_vars.ajax_url + '?action=aa_save_reservation', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(datos)
    });

    if (!response.ok) {
      throw new Error(`Error HTTP: ${response.status}`);
    }

    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.data?.message || 'Error desconocido al guardar.');
    }

    console.log('✅ Reserva guardada correctamente:', data);

    return data;
  } catch (error) {
    console.error('❌ Error al guardar reserva:', error);
    throw error;
  }
}

/**
 * Envía correo de confirmación de reserva
 */
async function sendConfirmation(datos) {
  if (typeof wpaa_vars === 'undefined' || !wpaa_vars.ajax_url) {
    throw new Error('Variables de configuración no disponibles (wpaa_vars)');
  }

  console.log("📧 sendConfirmation: Enviando correo de confirmación...");
  console.log("📦 Datos:", datos);

  try {
    const response = await fetch(wpaa_vars.ajax_url + '?action=aa_enviar_confirmacion', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(datos)
    });

    const emailData = await response.json();
    
    console.log('📧 Resultado del envío de correo:', emailData);
    
    return emailData;
  } catch (error) {
    console.warn('⚠️ Error al enviar correo (no crítico):', error);
    throw error;
  }
}

// ==============================
// 🔹 Exponer en window
// ==============================
window.ReservationService = {
  saveReservation,
  sendConfirmation
};

console.log('✅ ReservationService cargado y expuesto globalmente');