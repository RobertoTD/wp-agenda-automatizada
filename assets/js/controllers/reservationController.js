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

    // 🔹 Normalizar teléfono a formato canónico (sin selector de país)
    const telefonoRaw = (form.telefono.value || '').trim();
    const digits = telefonoRaw.replace(/\D/g, '');
    let telefonoCanon;
    if (digits.length === 10) {
      telefonoCanon = '52' + digits;
    } else if (digits.length === 11 && digits.startsWith('1')) {
      telefonoCanon = digits;
    } else {
      respuestaDiv.innerText = '❌ Teléfono inválido. Usa 10 dígitos (México) o 11 dígitos iniciando con 1 (USA).';
      return;
    }

    // 🔹 Construir objeto de datos
    const datos = {
      servicio: form.servicio.value,
      fecha: selectedSlotISO,
      nombre: form.nombre.value,
      telefono: telefonoCanon,
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
      // Solo enviar si el cliente tiene correo
      if (datos.correo) {
        window.ReservationService.sendConfirmation(datos).catch(emailError => {
          console.warn('⚠️ Error al enviar correo (no crítico):', emailError);
        });
      } else {
        console.log('ℹ️ Correo vacío → confirmación por email omitida');
      }

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

      // 🔹 PASO 5: Nombre del servicio para el mensaje (fixed:: → solo nombre; asignación → text del option)
      const servicioDisplayName = getServiceDisplayName(form, datos.servicio);

      // 🔹 PASO 5b: Construir URL de WhatsApp
      const whatsappNumber = (typeof wpaa_vars !== 'undefined' && wpaa_vars.whatsapp_number)
        ? wpaa_vars.whatsapp_number
        : '5212214365851';
      const whatsappMsg = `Hola, soy ${datos.nombre}. Me gustaría agendar una cita para: ${servicioDisplayName} el día ${fechaLegible}.`;
      const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(whatsappMsg)}`;

      // 🔹 PASO 5c: Payload de evento compartido (incluye whatsappUrl)
      const eventPayload = {
        nombre: datos.nombre,
        telefono: datos.telefono,
        correo: datos.correo,
        servicio: servicioDisplayName,
        fechaISO: selectedSlotISO,
        fechaLegible,
        id_reserva: datos.id_reserva || null,
        whatsappUrl
      };

      // 🔹 PASO 5d: Mensaje de éxito con info de correo
      const correoLine = datos.correo
        ? `\nTe enviaremos un correo a ${datos.correo} con los detalles.\nDesde ese correo podrás confirmar tu asistencia con un clic.\nSi no llega en 2–3 minutos, revisa Spam/Promociones.`
        : '';
      respuestaDiv.innerText = `✅ Tu solicitud de reserva fue registrada.${correoLine}\nAbriendo WhatsApp…`;

      // 🔹 PASO 5e: Emitir evento cancelable aa:reservation:processed
      // Si un listener llama e.preventDefault(), notCanceled será false → no auto-redirect.
      const evt = new CustomEvent('aa:reservation:processed', { detail: eventPayload, cancelable: true });
      const notCanceled = window.dispatchEvent(evt);

      // 🔹 PASO 6: Redirigir a WhatsApp solo si ningún listener canceló el evento (flujo free)
      if (notCanceled) {
        setTimeout(() => {
          window.dispatchEvent(new CustomEvent('aa:whatsapp:redirecting', { detail: eventPayload }));
          redirectToWhatsApp(datos.nombre, servicioDisplayName, fechaLegible);
        }, 3000);
      }

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
    let optionText = servicioSelect.options[servicioSelect.selectedIndex].text;
    if (optionText) {
      optionText = optionText.trim();
      // Quitar prefijo informativo "• Videollamada " para WhatsApp y otros usos (solo es label en el select)
      optionText = optionText.replace(/^\s*•\s*Videollamada\s+/i, '').trim();
      return optionText || String(servicioValue);
    }
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