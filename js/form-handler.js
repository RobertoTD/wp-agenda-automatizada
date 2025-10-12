// form-handler.js
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('agenda-form');

  // ==============================
  // 🔹 Flatpickr inicial básico
  // ==============================
  if (typeof flatpickr !== "undefined" && typeof flatpickr.l10ns !== "undefined") {
    flatpickr.localize(flatpickr.l10ns.es);
    flatpickr("#fecha", {
      enableTime: true,
      disableMobile: true,
      dateFormat: "d-m-Y H:i",
      minDate: "today",
      locale: "es",
      maxDate: new Date().fp_incr(14),
      time_24hr: true,
      minuteIncrement: 30,
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
  const ymd = d => d.toISOString().slice(0, 10);

  function getWeekdayName(date) {
    const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
    return days[date.getDay()];
  }

  function timeStrToMinutes(str) {
    const [h, m] = str.split(':').map(Number);
    return h * 60 + m;
  }

  function minutesFromDate(d) {
    return d.getHours() * 60 + d.getMinutes();
  }

  function getDayIntervals(aa_schedule, weekday) {
    if (!aa_schedule || !aa_schedule[weekday] || !aa_schedule[weekday].enabled) return [];
    const intervals = aa_schedule[weekday].intervals || [];
    return intervals.map(iv => ({
      start: timeStrToMinutes(iv.start),
      end: timeStrToMinutes(iv.end)
    }));
  }

  function isSlotBusy(slotDate, busyRanges) {
    return busyRanges.some(range => slotDate >= range.start && slotDate < range.end);
  }

  function generateSlotsForDay(date, intervals, busyRanges) {
    const slots = [];
    intervals.forEach(iv => {
      for (let min = iv.start; min < iv.end; min += 30) {
        const slot = new Date(date);
        slot.setHours(Math.floor(min / 60), min % 60, 0, 0);
        if (!isSlotBusy(slot, busyRanges)) slots.push(slot);
      }
    });
    return slots;
  }

  function isTimeIn(validSlots, date) {
    const mm = minutesFromDate(date);
    return validSlots.some(s => minutesFromDate(s) === mm);
  }

  function nextValidSlot(validSlots, afterDate) {
    const cur = minutesFromDate(afterDate);
    const byMin = validSlots
      .map(d => ({ d, m: minutesFromDate(d) }))
      .sort((a,b) => a.m - b.m);

    for (const item of byMin) {
      if (item.m >= cur) return item.d;
    }
    // si no hay posterior, devolvemos el primero del día (o null)
    return byMin.length ? byMin[0].d : null;
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

    const picker = flatpickr(fechaInput, {
      enableTime: true,
      disableMobile: true,
      dateFormat: "d-m-Y H:i",
      minDate: minDate,
      maxDate: maxDate,
      time_24hr: true,
      minuteIncrement: 30,
      locale: "es",
      disable: [disableDate],

      // Se llama tanto al cambiar día como hora/min: aquí diferenciamos
      onChange: function(selectedDates) {
        if (!selectedDates.length) return;

        const sel = selectedDates[0];
        const selYMD = ymd(sel);

        // ¿Cambió el día?
        const dayChanged = (selYMD !== lastYMD);

        // Recalcular slots válidos del día **sólo si cambió el día**
        if (dayChanged) {
          const weekday = getWeekdayName(sel);
          const intervals = getDayIntervals(aa_schedule, weekday);
          const valid = generateSlotsForDay(sel, intervals, busyRanges);
          this.validSlots = valid;
          lastYMD = selYMD;

          // Si no hay tiempo seleccionado aún (o venimos del mes/día), fijar el primero
          if (valid.length > 0 && !this._timeManuallyEditing) {
            this.setDate(valid[0], true); // true = no dispara onChange en bucle
          }
        }
      },

      onReady: function(selectedDates, dateStr, instance) {
        // Marcar cuando el usuario toca los controles de hora/minuto
        const hourInput = instance.timeContainer && instance.timeContainer.querySelector(".flatpickr-hour");
        const minInput  = instance.timeContainer && instance.timeContainer.querySelector(".flatpickr-minute");

        const markEditing = () => { instance._timeManuallyEditing = true; };
        const unmarkEditing = () => { instance._timeManuallyEditing = false; };

        if (hourInput) {
          hourInput.addEventListener('input', markEditing);
          hourInput.addEventListener('focus', markEditing);
          hourInput.addEventListener('blur', unmarkEditing);
        }
        if (minInput) {
          minInput.addEventListener('input', markEditing);
          minInput.addEventListener('focus', markEditing);
          minInput.addEventListener('blur', unmarkEditing);
        }

        console.debug("[AA] Intervals from admin schedule:", totalIntervals);
        console.debug("[AA] Busy events disabled:", totalBusy);
        console.debug("[AA] Effective available slots per date:", availableSlotsPerDay);
      },

      // Al cerrar el picker, si la hora elegida no es válida, la ajustamos
      onClose: function(selectedDates, dateStr, instance) {
        if (!selectedDates.length || !instance.validSlots || !instance.validSlots.length) return;
        const sel = selectedDates[0];
        if (!isTimeIn(instance.validSlots, sel)) {
          const snap = nextValidSlot(instance.validSlots, sel);
          if (snap) instance.setDate(snap, true);
        }
        instance._timeManuallyEditing = false; // terminó de editar
      }
    });
  });

  // ==============================
  // 🔹 Envío del formulario
  // ==============================
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const respuestaDiv = document.getElementById('respuesta-agenda');
    respuestaDiv.innerText = 'Procesando solicitud...';

    const fechaInput = document.getElementById('fecha');
    const fechaSeleccionada = fechaInput._flatpickr ? fechaInput._flatpickr.selectedDates[0] : null;

    const datos = {
      servicio: form.servicio.value,
      fecha: fechaSeleccionada ? fechaSeleccionada.toISOString() : form.fecha.value,
      nombre: form.nombre.value,
      telefono: form.telefono.value,
      correo: form.correo.value || ''
    };

    try {
      const response = await fetch(wpaa_vars.webhook_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(datos)
      });

      if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);

      await response.json();
      const mensaje = `Hola, soy ${datos.nombre}. Me gustaría agendar una cita para: ${datos.servicio} el día ${datos.fecha}. Mi teléfono es ${datos.telefono}.`;
      window.location.href = `https://wa.me/5215522992290?text=${encodeURIComponent(mensaje)}`;
    } catch (err) {
      console.error('Error:', err);
      respuestaDiv.innerText = 'Error al agendar. Por favor, intenta más tarde.';
    }
  });
});
