// ==============================
// 🔹 Controlador de reservas
// ==============================

/**
 * Inicializa el controlador de reservas
 */
function initReservationController(formSelector) {
  const form = document.querySelector(formSelector);
  
  if (!form) {
    console.error(`❌ No se encontró el formulario: ${formSelector}`);
    return;
  }

  // ✅ Crear campo honeypot invisible anti-bot
  const honeypot = document.createElement('input');
  honeypot.type = 'text';
  honeypot.name = 'extra_field';
  honeypot.style.display = 'none';
  form.appendChild(honeypot);

  // ==============================
  // 🔹 Manejar envío del formulario
  // ==============================
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const respuestaDiv = document.getElementById('respuesta-agenda');
    
    if (!respuestaDiv) {
      console.error('❌ No se encontró el div de respuesta');
      return;
    }

    respuestaDiv.innerText = 'Procesando solicitud...';

    // 🔹 Obtener el slot seleccionado del selector
    const slotSelector = document.getElementById('slot-selector');
    const selectedSlotISO = slotSelector ? slotSelector.value : null;
    
    // 🔹 Validar que se haya seleccionado un horario
    if (!selectedSlotISO) {
      respuestaDiv.innerText = '❌ Por favor, selecciona una fecha y hora válidas.';
      console.warn('⚠️ No se ha seleccionado ningún horario');
      return;
    }

    // 🔹 Construir objeto de datos
    const datos = {
      servicio: form.servicio.value,
      fecha: selectedSlotISO,
      nombre: form.nombre.value,
      telefono: form.telefono.value,
      correo: form.correo.value || '',
      nonce: wpaa_vars.nonce,
      extra_field: honeypot.value || ''
    };

    // 🔹 Añadir assignment_id si existe (flujo basado en asignaciones)
    const assignmentIdInput = document.getElementById('assignment-id');
    if (assignmentIdInput && assignmentIdInput.value) {
      datos.assignment_id = parseInt(assignmentIdInput.value, 10);
      console.log('📋 [ReservationController] assignment_id incluido:', datos.assignment_id);
    }

    try {
      // 🔹 PASO 1: Guardar la reserva
      const data = await window.ReservationService.saveReservation(datos);

      // 🔹 PASO 2: Añadir ID de la reserva
      if (data.data && data.data.id) {
        datos.id_reserva = data.data.id;
        console.log('🆔 ID de reserva asignado:', datos.id_reserva);
      } else if (data.id) {
        datos.id_reserva = data.id;
        console.warn('⚠️ ID de reserva recibido en formato alternativo:', datos.id_reserva);
      } else {
        console.warn('⚠️ No se recibió ID de reserva en la respuesta del backend.');
      }

      // 🔹 PASO 3: Enviar confirmación por correo (sin bloquear el flujo)
      window.ReservationService.sendConfirmation(datos).catch(emailError => {
        console.warn('⚠️ Error al enviar correo (no crítico):', emailError);
      });

      // 🔹 PASO 4: Formatear la fecha para WhatsApp
      const fechaObj = new Date(selectedSlotISO);
      const userLocale = (typeof wpaa_vars !== 'undefined' && wpaa_vars.locale) 
        ? wpaa_vars.locale 
        : 'es-MX';
      
      const fechaLegible = fechaObj.toLocaleString(userLocale, {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: wpaa_vars.timezone || 'America/Mexico_City'
      });

      respuestaDiv.innerText = '✅ Cita agendada correctamente. Redirigiendo a WhatsApp...';

      // 🔹 PASO 5: Nombre del servicio para el mensaje (fixed:: → solo nombre; asignación → text del option)
      const servicioDisplayName = getServiceDisplayName(form, datos.servicio);

      // 🔹 PASO 6: Redirigir a WhatsApp después de 2 segundos
      setTimeout(() => {
        redirectToWhatsApp(datos.nombre, servicioDisplayName, fechaLegible);
      }, 2000);

    } catch (err) {
      console.error('❌ Error al agendar:', err);
      respuestaDiv.innerText = `❌ Error al agendar: ${err.message}`;
    }
  });

  console.log('✅ ReservationController inicializado');
}

/**
 * Obtiene el nombre a mostrar del servicio para el mensaje de WhatsApp.
 * - Si es fixed:: (ej. "fixed::Informes") devuelve solo el nombre (ej. "Informes").
 * - Si es asignación (id numérico), devuelve el text del option seleccionado en #servicio.
 */
function getServiceDisplayName(form, servicioValue) {
  if (!servicioValue) return '';
  if (String(servicioValue).startsWith('fixed::')) {
    return String(servicioValue).replace(/^fixed::/, '').trim();
  }
  const servicioSelect = form && form.servicio;
  if (servicioSelect && servicioSelect.options && servicioSelect.selectedIndex >= 0) {
    const optionText = servicioSelect.options[servicioSelect.selectedIndex].text;
    if (optionText) return optionText.trim();
  }
  return String(servicioValue);
}

/**
 * Redirige a WhatsApp con mensaje prellenado
 */
function redirectToWhatsApp(nombre, servicioDisplayName, fechaLegible) {
  const whatsappNumber = (typeof wpaa_vars !== 'undefined' && wpaa_vars.whatsapp_number) 
    ? wpaa_vars.whatsapp_number 
    : '5212214365851';

  const mensaje = `Hola, soy ${nombre}. Me gustaría agendar una cita para: ${servicioDisplayName} el día ${fechaLegible}.`;
  
  window.location.href = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(mensaje)}`;
}

// ==============================
// 🔹 Exponer en window
// ==============================
window.ReservationController = {
  init: initReservationController
};

console.log('✅ ReservationController cargado y expuesto globalmente');