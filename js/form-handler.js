// form-handler.js
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('agenda-form');

  // ✅ Añadir campo honeypot invisible anti-bot
  const honeypot = document.createElement('input');
  honeypot.type = 'text';
  honeypot.name = 'extra_field';
  honeypot.style.display = 'none';
  form.appendChild(honeypot);

  // ==============================
  // 🔹 Flatpickr inicial básico
  // ==============================
  // Usar la función modular del UI
  if (typeof window.CalendarUI !== 'undefined') {
    window.CalendarUI.initBasicCalendar("#fecha");
  } else {
    console.error('❌ CalendarUI no está cargado');
  }

  // ==============================
  // 🔹 Cuando llega la disponibilidad
  // ==============================
  document.addEventListener("aa:availability:loaded", () => {
    const fechaInput = document.getElementById('fecha');
    if (!fechaInput || typeof flatpickr === "undefined") return;

    const aa_schedule = window.aa_schedule || {};
    const aa_future_window = window.aa_future_window || 14;

    const busy = (window.aa_availability && Array.isArray(window.aa_availability.busy))
      ? window.aa_availability.busy
      : [];

    // 🔹 Convertir todas las fechas ocupadas a objetos Date locales
    const busyRanges = busy.map(ev => {
      return {
        start: new Date(ev.start),
        end: new Date(ev.end)
      };
    });

    const minDate = new Date();
    const maxDate = new Date();
    maxDate.setDate(minDate.getDate() + Number(aa_future_window));

    // Precalcular número de slots por día para deshabilitar días "vacíos"
    const availableSlotsPerDay = {};
    let totalIntervals = 0;
    let totalBusy = busyRanges.length;
    
    // recorrer cada día en el rango
    for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
      const day = new Date(d);
      const weekday = getWeekdayName(day);
      const intervals = getDayIntervals(aa_schedule, weekday);
      
      totalIntervals += intervals.length;
      const slots = generateSlotsForDay(day, intervals, busyRanges);
      
      availableSlotsPerDay[ymd(day)] = slots.length;
      
      // 🔹 Debug: mostrar slots calculados para cada día
      if (slots.length > 0) {
        console.log(`📅 ${ymd(day)} (${weekday}): ${slots.length} slots disponibles`, 
          slots.map(s => s.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })));
      }
    }

    function isDateAvailable(date) {
      return (availableSlotsPerDay[ymd(date)] || 0) > 0;
    }

    function disableDate(date) {
      return !isDateAvailable(date);
    }

    // ==============================
    // 🔹 Flatpickr con reglas reales usando UI modular
    // ==============================
    let selectedSlotISO = null;

    if (typeof window.CalendarUI !== 'undefined') {
      window.CalendarUI.rebuildCalendar({
        fechaInput: fechaInput,
        minDate: minDate,
        maxDate: maxDate,
        disableDateCallback: disableDate,
        onDateSelected: (selectedDate, pickerInstance) => {
          const weekday = getWeekdayName(selectedDate);
          const intervals = getDayIntervals(aa_schedule, weekday);
          const validSlots = generateSlotsForDay(selectedDate, intervals, busyRanges);
          pickerInstance.validSlots = validSlots;
          
          // 🔹 Renderiza la lista debajo del calendario usando SlotSelectorUI
          if (typeof window.SlotSelectorUI !== 'undefined') {
            window.SlotSelectorUI.renderAvailableSlots('slot-container', validSlots, chosen => {
              // cuando el usuario elige un horario de la lista
              selectedSlotISO = chosen.toISOString();
              fechaInput.value = `${selectedDate.toLocaleDateString()} ${chosen.getHours().toString().padStart(2,'0')}:${chosen.getMinutes().toString().padStart(2,'0')}`;
            });
          }
          
          // 🔹 Establecer el primer slot como predeterminado
          if (validSlots.length > 0) {
            const firstSlot = validSlots[0];
            selectedSlotISO = firstSlot.toISOString();
            fechaInput.value = `${selectedDate.toLocaleDateString()} ${firstSlot.getHours().toString().padStart(2,'0')}:${firstSlot.getMinutes().toString().padStart(2,'0')}`;
          }

          return { selectedSlotISO };
        }
      });
    }

    console.log(`📅 Flatpickr reinicializado con reglas reales. Intervalos: ${totalIntervals}, Ocupados: ${totalBusy}`);

  // ==============================
  // 🔹 Envío del formulario
  // ==============================
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const respuestaDiv = document.getElementById('respuesta-agenda');
    respuestaDiv.innerText = 'Procesando solicitud...';

    // 🔹 Obtener el slot seleccionado directamente del <select>
    const slotSelector = document.getElementById('slot-selector');
    const selectedSlotISO = slotSelector ? slotSelector.value : null;
    
    // 🔹 Validar que se haya seleccionado un horario
    if (!selectedSlotISO) {
      respuestaDiv.innerText = '❌ Por favor, selecciona una fecha y hora válidas.';
      console.warn('⚠️ aa_debug: No se ha seleccionado ningún horario');
      return;
    }

    const datos = {
      servicio: form.servicio.value,
      fecha: selectedSlotISO, // 🔹 Usar el valor del <select> que ya está en ISO
      nombre: form.nombre.value,
      telefono: form.telefono.value,
      correo: form.correo.value || '',
      nonce: wpaa_vars.nonce,           // ✅ añadir nonce
      extra_field: honeypot.value || '' // ✅ añadir honeypot
    };

    console.group('🧩 aa_debug: datos que se enviarán al backend');
    console.log('Tipo de datos:', typeof datos);
    console.log('Contenido del objeto:', datos);
    console.log('Fecha ISO final enviada:', datos.fecha);
    console.groupEnd();

    // Opcional: validar estructura antes del fetch
    ['servicio', 'fecha', 'nombre', 'telefono'].forEach(campo => {
      if (!datos[campo]) {
        console.warn(`⚠️ aa_debug: el campo "${campo}" está vacío o indefinido`);
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

      if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);

      const data = await response.json();
      if (!data.success) {
        throw new Error(data.data?.message || 'Error desconocido al guardar.');
      }

      console.log('✅ Reserva guardada correctamente:', data);

      // 🔹 Añadir ID de la reserva al objeto que se enviará al backend
      if (data.data && data.data.id) {
        datos.id_reserva = data.data.id;
        console.log('🆔 ID de reserva asignado al objeto datos:', datos.id_reserva);
      } else if (data.id) {
        datos.id_reserva = data.id;
        console.warn('⚠️ ID de reserva recibido en formato alternativo:', datos.id_reserva);
      } else {
        console.warn('⚠️ No se recibió ID de reserva en la respuesta del backend.');
      }

      // 🔹 Enviar confirmación por correo (sin bloquear el flujo)
      console.log("📦 Datos que se envían al backend:", datos);

      fetch(wpaa_vars.ajax_url + '?action=aa_enviar_confirmacion', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
      }).then(emailResponse => {
        return emailResponse.json();
      }).then(emailData => {
        console.log('📧 Resultado del envío de correo:', emailData);
      }).catch(emailError => {
        console.warn('⚠️ Error al enviar correo (no crítico):', emailError);
      });

      // 🔹 Formatear la fecha para el mensaje de WhatsApp usando zona horaria y locale del admin
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
        timeZone: wpaa_vars.timezone || 'America/Mexico_City' // 🔹 Forzar zona horaria del negocio
      });

      respuestaDiv.innerText = '✅ Cita agendada correctamente. Redirigiendo a WhatsApp...';

      // 🔹 Redirigir a WhatsApp después de 2 segundos
      setTimeout(() => {
        const whatsappNumber = (typeof wpaa_vars !== 'undefined' && wpaa_vars.whatsapp_number) 
          ? wpaa_vars.whatsapp_number 
          : '5215522992290';

        const mensaje = `Hola, soy ${datos.nombre}. Me gustaría agendar una cita para: ${datos.servicio} el día ${fechaLegible}. Mi teléfono es ${datos.telefono}.`;
        window.location.href = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(mensaje)}`;
      }, 2000);
    } catch (err) {
      console.error('Error:', err);
      respuestaDiv.innerText = `❌ Error al agendar: ${err.message}`;
    }

  });
  
  }); // Closing brace for the "aa:availability:loaded" event listener
}); // Closing brace for DOMContentLoaded