document.addEventListener('DOMContentLoaded', function() {
    const btnToggle = document.getElementById('btn-toggle-form-nueva-cita');
    const formNuevaCita = document.getElementById('form-nueva-cita');
    const btnCancelar = document.getElementById('btn-cancelar-cita-form');
    const form = document.getElementById('form-crear-cita-admin');
    
    if (!btnToggle || !formNuevaCita || !form) return;
    
    // Toggle formulario
    btnToggle.addEventListener('click', function() {
        formNuevaCita.classList.toggle('visible');
        if (formNuevaCita.classList.contains('visible')) {
            btnToggle.textContent = '− Ocultar formulario';
        } else {
            btnToggle.textContent = '+ Crear nueva cita';
        }
    });
    
    // Cancelar formulario
    if (btnCancelar) {
        btnCancelar.addEventListener('click', function() {
            formNuevaCita.classList.remove('visible');
            btnToggle.textContent = '+ Crear nueva cita';
            form.reset();
            document.getElementById('slot-container-admin').innerHTML = '';
        });
    }
    
    // ==============================
    // 🔹 Función local para renderizar slots (específica del admin)
    // ==============================
    function renderAvailableSlots(containerId, validSlots, onSelectSlot) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        
        if (!validSlots.length) {
            container.textContent = 'No hay horarios disponibles para este día.';
            return;
        }
        
        const label = document.createElement('label');
        label.textContent = 'Horarios disponibles:';
        label.style.display = 'block';
        label.style.marginTop = '8px';
        
        const select = document.createElement('select');
        select.id = 'slot-selector-admin';
        select.style.marginTop = '4px';
        select.style.width = '100%';
        select.style.padding = '8px';
        
        validSlots.forEach(date => {
            const option = document.createElement('option');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
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
        
        // Disparar evento con el primer slot
        if (validSlots.length > 0) {
            onSelectSlot(validSlots[0]);
        }
    }
    
    // ==============================
    // 🔹 Esperar disponibilidad
    // ==============================
    document.addEventListener("aa:availability:loaded", () => {
        const fechaInput = document.getElementById('cita-fecha');
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
        
        const availableSlotsPerDay = {};
        
        for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
            const day = new Date(d);
            const weekday = getWeekdayName(day);
            const intervals = getDayIntervals(aa_schedule, weekday);
            const slots = generateSlotsForDay(day, intervals, busyRanges);
            availableSlotsPerDay[ymd(day)] = slots.length;
        }
        
        function isDateAvailable(date) {
            return (availableSlotsPerDay[ymd(date)] || 0) > 0;
        }
        
        function disableDate(date) {
            return !isDateAvailable(date);
        }
        
        if (fechaInput._flatpickr) fechaInput._flatpickr.destroy();
        
        let selectedSlotISO = null;
        
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
                
                renderAvailableSlots('slot-container-admin', validSlots, chosen => {
                    selectedSlotISO = chosen.toISOString();
                    fechaInput.value = `${sel.toLocaleDateString()} ${chosen.getHours().toString().padStart(2,'0')}:${chosen.getMinutes().toString().padStart(2,'0')}`;
                });
                
                if (validSlots.length > 0) {
                    const firstSlot = validSlots[0];
                    selectedSlotISO = firstSlot.toISOString();
                    fechaInput.value = `${sel.toLocaleDateString()} ${firstSlot.getHours().toString().padStart(2,'0')}:${firstSlot.getMinutes().toString().padStart(2,'0')}`;
                }
            }
        });
        
        console.log('📅 Flatpickr inicializado en panel del asistente');
    });
    
    // ==============================
    // 🔹 Envío del formulario
    // ==============================
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const clienteSelect = document.getElementById('cita-cliente');
        const slotSelector = document.getElementById('slot-selector-admin');
        const selectedSlotISO = slotSelector ? slotSelector.value : null;
        
        if (!selectedSlotISO) {
            alert('❌ Por favor, selecciona una fecha y hora válidas.');
            return;
        }
        
        const clienteOption = clienteSelect.options[clienteSelect.selectedIndex];
        
        const datos = {
            servicio: document.getElementById('cita-servicio').value,
            fecha: selectedSlotISO,
            nombre: clienteOption.dataset.nombre,
            telefono: clienteOption.dataset.telefono,
            correo: clienteOption.dataset.correo,
            nonce: aa_asistant_vars.nonce_crear_cita || ''
        };
        
        try {
            // 🔹 PASO 1: Guardar la cita en WordPress
            console.log('📝 Guardando cita en WordPress...');
            const response = await fetch(ajaxurl + '?action=aa_save_reservation', {
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
            
            // 🔹 PASO 2: Añadir ID de la reserva al objeto
            if (data.data && data.data.id) {
                datos.id_reserva = data.data.id;
                console.log('🆔 ID de reserva asignado:', datos.id_reserva);
            } else if (data.id) {
                datos.id_reserva = data.id;
                console.warn('⚠️ ID de reserva recibido en formato alternativo:', datos.id_reserva);
            } else {
                console.warn('⚠️ No se recibió ID de reserva en la respuesta del backend.');
            }
            
            // 🔹 PASO 3: Enviar correo de confirmación al backend
            console.log("📧 Enviando correo de confirmación...");
            console.log("📦 Datos que se envían:", datos);
            
            fetch(ajaxurl + '?action=aa_enviar_confirmacion', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(datos)
            }).then(emailResponse => {
                return emailResponse.json();
            }).then(emailData => {
                console.log('📧 Resultado del envío de correo:', emailData);
                if (emailData.success) {
                    console.log('✅ Correo enviado correctamente');
                } else {
                    console.warn('⚠️ Error al enviar correo:', emailData);
                }
            }).catch(emailError => {
                console.warn('⚠️ Error al enviar correo (no crítico):', emailError);
            });
            
            // 🔹 PASO 4: Mostrar mensaje de éxito y recargar
            alert('✅ Cita agendada correctamente. Se ha enviado correo de confirmación.');
            location.reload();
            
        } catch (err) {
            console.error('❌ Error al agendar:', err);
            alert('❌ Error al agendar: ' + err.message);
        }
    });
});