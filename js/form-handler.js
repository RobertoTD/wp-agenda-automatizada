// form-handler.js
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('agenda-form');

  // ==============================
  // 🔹 Flatpickr inicial básico
  // ==============================
  if (typeof flatpickr !== "undefined" && typeof flatpickr.l10ns !== "undefined") {
    flatpickr.localize(flatpickr.l10ns.es);
    flatpickr("#fecha", {
      disableMobile: true,
      dateFormat: "d-m-Y",
      minDate: "today",
      locale: "es",
      maxDate: new Date().fp_incr(14),
      onReady: function () {
        console.log("📅 Flatpickr inicializado correctamente (modo básico).");
      }
    });
  } else {
    console.error('❌ Flatpickr no está cargado correctamente.');
  }

  // ==============================
  // 🔹 Utilidades
  // ==============================

  // convertir fecha con formato YYYY-MM-DDTHH:mm:ss.sssZ de Date a YYYY-MM-DD string
  const ymd = d => d.toISOString().slice(0, 10);
  
  // devuelve el nombre del día en inglés en minúsculas
  function getWeekdayName(date) {
    const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
    return days[date.getDay()];
  }
  // convierte el formato "HH:MM" a minutos desde medianoche ejemplo: "14:30" => 870
  function timeStrToMinutes(str) {
    const [h, m] = str.split(':').map(Number);
    return h * 60 + m;
  }
  // convierte un objeto Date a minutos desde medianoche  Date(14:30) => 870
  function minutesFromDate(d) {
    return d.getHours() * 60 + d.getMinutes();
  }
  // obtiene los intervalos de un día específico del horario admin 
  function getDayIntervals(aa_schedule, weekday) {
    if (!aa_schedule || !aa_schedule[weekday] || !aa_schedule[weekday].enabled) return [];
    const intervals = aa_schedule[weekday].intervals || [];
    return intervals.map(iv => ({
      start: timeStrToMinutes(iv.start),
      end: timeStrToMinutes(iv.end)
    }));
  }
  // verifica si una fecha dada cae dentro de algún rango ocupado
  function isSlotBusy(slotDate, busyRanges) {
    return busyRanges.some(range => slotDate >= range.start && slotDate < range.end);
  }

  // genera todos los slots disponibles para un día dado, excluyendo los ocupados
  function generateSlotsForDay(date, intervals, busyRanges) {
    const slots = [];
    intervals.forEach(iv => {
      for (let min = iv.start; min < iv.end; min += 30) {
        const slot = new Date(date);
        // 🔹 Obtener la zona horaria local en milisegundos
        const offsetMs = slot.getTimezoneOffset() * 60000;
        // 🔹 Crear fecha en hora local sin conversión UTC
        slot.setHours(Math.floor(min / 60), min % 60, 0, 0);
        
        if (!isSlotBusy(slot, busyRanges)) slots.push(slot);
      }
    });
    return slots;
  }

  // Crea un <select> con los horarios disponibles del día seleccionado
  function renderAvailableSlots(containerId, validSlots, onSelectSlot) {
    const container = document.getElementById(containerId);
    container.innerHTML = ''; // limpiar

    if (!validSlots.length) {
      container.textContent = 'No hay horarios disponibles para este día.';
      return;
    }

    const label = document.createElement('label');
    label.textContent = 'Horarios disponibles:';
    label.style.display = 'block';
    label.style.marginTop = '8px';

    const select = document.createElement('select');
    select.id = 'slot-selector';
    select.style.marginTop = '4px';
    select.style.width = '100%';
    select.style.padding = '4px';

    validSlots.forEach(date => {
      const option = document.createElement('option');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      
      // 🔹 Usar toISOString() que siempre genera UTC
      // El backend lo convertirá a la zona horaria correcta
      option.value = date.toISOString();
      option.textContent = `${hours}:${minutes}`;
      select.appendChild(option);
    });

    select.addEventListener('change', () => {
      const chosen = new Date(select.value);
      onSelectSlot(chosen);
    });

    container.appendChild(label);
    container.appendChild(select);
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

    const busyRanges = busy.map(ev => ({
      start: new Date(ev.start),
      end: new Date(ev.end)
    }));

    const minDate = new Date();
    const maxDate = new Date();
    maxDate.setDate(minDate.getDate() + Number(aa_future_window));

    // Precalcular número de slots por día para deshabilitar días “vacíos”
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
    }

    function isDateAvailable(date) {
      return (availableSlotsPerDay[ymd(date)] || 0) > 0;
    }

    function disableDate(date) {
      return !isDateAvailable(date);
    }

    // ==============================
    // 🔹 Flatpickr con reglas reales
    // ==============================
    if (fechaInput._flatpickr) fechaInput._flatpickr.destroy();

    let lastYMD = null;
    let selectedSlotISO = null; // 🔹 Nueva variable para guardar el slot elegido

    const picker = flatpickr(fechaInput, {
      disableMobile: true,
      dateFormat: "d-m-Y",
      minDate: minDate,
      maxDate: maxDate,
      locale: "es",
      disable: [disableDate],
       onChange: function(selectedDates) {
      if (!selectedDates.length) return;
      const sel = selectedDates[0];
      const weekday = getWeekdayName(sel);
      const intervals = getDayIntervals(aa_schedule, weekday);
      const validSlots = generateSlotsForDay(sel, intervals, busyRanges);
      this.validSlots = validSlots;
     
       // 🔹 Renderiza la lista debajo del calendario
    renderAvailableSlots('slot-container', validSlots, chosen => {
      // cuando el usuario elige un horario de la lista
      selectedSlotISO = chosen.toISOString(); // 🔹 Guardar hora completa elegida
      fechaInput.value = `${sel.toLocaleDateString()} ${chosen.getHours().toString().padStart(2,'0')}:${chosen.getMinutes().toString().padStart(2,'0')}`;
       });
       
       // 🔹 Establecer el primer slot como predeterminado
       if (validSlots.length > 0) {
         const firstSlot = validSlots[0];
         selectedSlotISO = firstSlot.toISOString();
         fechaInput.value = `${sel.toLocaleDateString()} ${firstSlot.getHours().toString().padStart(2,'0')}:${firstSlot.getMinutes().toString().padStart(2,'0')}`;
       }
      }

    });
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
      correo: form.correo.value || ''
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

      const mensaje = `Hola, soy ${datos.nombre}. Me gustaría agendar una cita para: ${datos.servicio} el día ${fechaLegible}. Mi teléfono es ${datos.telefono}.`;
      window.location.href = `https://wa.me/5215522992290?text=${encodeURIComponent(mensaje)}`;
    } catch (err) {
      console.error('Error:', err);
      respuestaDiv.innerText = `❌ Error al agendar: ${err.message}`;
    }
  });
}); 
});