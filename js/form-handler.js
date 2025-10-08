document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('agenda-form');
    
    // Asegurarnos que Flatpickr está disponible
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#fecha", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            locale: "es",
            time_24hr: true,
            minuteIncrement: 30
        });
    } else {
        console.error('Flatpickr no está cargado correctamente');
    }

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
