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
    // 🔹 Funciones de utilidad
    // ==============================

    function getWeekdayName(date) {
        const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
        return days[date.getDay()];
    }

    function timeStrToMinutes(str) {
        const [h, m] = str.split(':').map(Number);
        return h * 60 + m;
    }

    function getDayIntervals(aa_schedule, weekday) {
        if (!aa_schedule || !aa_schedule[weekday] || !aa_schedule[weekday].enabled) return [];
        const intervals = aa_schedule[weekday].intervals || [];
        return intervals.map(iv => ({
            start: timeStrToMinutes(iv.start),
            end: timeStrToMinutes(iv.end)
        }));
    }

    function isSlotInIntervals(slotMinutes, intervals) {
        return intervals.some(iv => slotMinutes >= iv.start && slotMinutes < iv.end);
    }

    function isSlotBusy(slotDate, busyRanges) {
        return busyRanges.some(range => slotDate >= range.start && slotDate < range.end);
    }

    function generateSlotsForDay(date, intervals) {
        const slots = [];
        intervals.forEach(iv => {
            for (let min = iv.start; min < iv.end; min += 30) {
                const slot = new Date(date);
                slot.setHours(Math.floor(min / 60), min % 60, 0, 0);
                slots.push(new Date(slot));
            }
        });
        return slots;
    }

    // ==============================
    // 🔹 Evento: disponibilidad cargada
    // ==============================
    document.addEventListener("aa:availability:loaded", (e) => {
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

        const enabledDays = Object.entries(aa_schedule)
            .filter(([day, conf]) => conf && conf.enabled)
            .map(([day]) => day);

        // ==============================
        // 🔹 Generar slots válidos por día
        // ==============================
        const availableSlotsPerDay = {};
        let totalIntervals = 0;
        let totalBusy = busyRanges.length;

        for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
            const day = new Date(d);
            const weekday = getWeekdayName(day);
            const intervals = getDayIntervals(aa_schedule, weekday);
            totalIntervals += intervals.length;
            const slots = generateSlotsForDay(day, intervals);
            const available = slots.filter(slot => !isSlotBusy(slot, busyRanges));
            availableSlotsPerDay[day.toISOString().slice(0,10)] = available.length;
        }

        // ==============================
        // 🔹 Nuevas funciones de restricción
        // ==============================
        function isDateAvailable(date) {
            const iso = date.toISOString().slice(0,10);
            return availableSlotsPerDay[iso] && availableSlotsPerDay[iso] > 0;
        }

        function disableDate(date) {
            return !isDateAvailable(date);
        }

        function disableTime(date) {
            const weekday = getWeekdayName(date);
            const intervals = getDayIntervals(aa_schedule, weekday);
            if (!intervals.length) return true;

            const slotMinutes = date.getHours() * 60 + date.getMinutes();
            if (!isSlotInIntervals(slotMinutes, intervals)) return true;
            if (isSlotBusy(date, busyRanges)) return true;
            return false;
        }

        // ==============================
        // 🔹 Inicializar Flatpickr con restricciones reales
        // ==============================
        if (fechaInput._flatpickr) fechaInput._flatpickr.destroy();

        flatpickr(fechaInput, {
            enableTime: true,
            disableMobile: true,
            dateFormat: "d-m-Y H:i",
            minDate: minDate,
            maxDate: maxDate,
            time_24hr: true,
            minuteIncrement: 30,
            locale: "es",
            disable: [disableDate],

            onOpen: function(selectedDates, dateStr, instance) {
                instance.set('disableTime', disableTime);
            },

            onReady: function() {
                console.debug("[AA] Intervals from admin schedule:", totalIntervals);
                console.debug("[AA] Busy events disabled:", totalBusy);
                console.debug("[AA] Effective available slots per date:", availableSlotsPerDay);
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

            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }

            const responseData = await response.json();
            
            const mensaje = `Hola, soy ${datos.nombre}. Me gustaría agendar una cita para: ${datos.servicio} el día ${datos.fecha}. Mi teléfono es ${datos.telefono}.`;
            const whatsappURL = `https://wa.me/5215522992290?text=${encodeURIComponent(mensaje)}`;
            window.location.href = whatsappURL;
            
        } catch (err) {
            console.error('Error:', err);
            respuestaDiv.innerText = 'Error al agendar. Por favor, intenta más tarde.';
        }
    });
});
