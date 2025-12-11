/**
 * Settings Module - Module-specific JavaScript
 * 
 * Combines functionality from:
 * - admin-controls.js (motivos management, virtual checkbox)
 * - admin-schedule.js (schedule intervals, Flatpickr)
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        console.log('Settings module loaded');

        // ================================
        // 🔹 Inicializar Motivos de la Cita
        // ================================
        const hiddenInput = document.getElementById("aa-google-motivo-hidden");
        const motivosList = document.getElementById("aa-motivos-list");
        const addButton = document.getElementById("aa-add-motivo");
        const motivoInput = document.getElementById("aa-motivo-input");

        if (hiddenInput && motivosList && addButton && motivoInput) {
            // Intenta leer el JSON guardado o usa []
            let motivos = [];
            try {
                motivos = JSON.parse(hiddenInput.value || "[]");
                if (!Array.isArray(motivos)) motivos = [];
            } catch (e) {
                motivos = [];
            }

            // Renderizar lista inicial
            function renderMotivos() {
                motivosList.innerHTML = "";
                motivos.forEach((motivo, index) => {
                    const li = document.createElement("li");
                    li.style.marginBottom = "5px";
                    li.style.display = "flex";
                    li.style.alignItems = "center";
                    li.style.gap = "10px";

                    li.innerHTML = `
                        <span>${motivo}</span>
                        <button type="button" class="remove-motivo px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600" data-index="${index}">Eliminar</button>
                    `;
                    motivosList.appendChild(li);
                });
                hiddenInput.value = JSON.stringify(motivos);
            }

            // Agregar motivo nuevo
            addButton.addEventListener("click", function () {
                const nuevo = motivoInput.value.trim();
                if (!nuevo) return;
                motivos.push(nuevo);
                motivoInput.value = "";
                renderMotivos();
            });

            // Eliminar motivo
            motivosList.addEventListener("click", function (e) {
                if (e.target.classList.contains("remove-motivo")) {
                    const index = parseInt(e.target.dataset.index, 10);
                    motivos.splice(index, 1);
                    renderMotivos();
                }
            });

            // Render inicial
            renderMotivos();
        }

        // ================================
        // 🔹 Bloquear dirección si es virtual
        // ================================
        const virtualCheckbox = document.getElementById('aa-is-virtual-checkbox');
        const addressField = document.getElementById('aa-business-address');
        const addressRow = document.getElementById('aa-address-row');

        if (virtualCheckbox && addressField && addressRow) {
            function toggleAddressField() {
                if (virtualCheckbox.checked) {
                    addressField.disabled = true;
                    addressField.style.opacity = '0.5';
                    addressRow.style.opacity = '0.6';
                } else {
                    addressField.disabled = false;
                    addressField.style.opacity = '1';
                    addressRow.style.opacity = '1';
                }
            }

            // Ejecutar al cargar y al cambiar
            toggleAddressField();
            virtualCheckbox.addEventListener('change', toggleAddressField);
        }

        // ================================
        // 🔹 Schedule Intervals Management
        // ================================
        
        // Helper: Detectar si es móvil
        function isMobile() {
            return window.innerWidth < 640; // sm breakpoint de Tailwind
        }

        // Helper: Convertir HH:mm a objeto {hour, minute}
        function parseTime(timeStr) {
            if (!timeStr) return { hour: 0, minute: 0 };
            const [hour, minute] = timeStr.split(':').map(Number);
            return { hour: hour || 0, minute: minute || 0 };
        }

        // Helper: Convertir {hour, minute} a HH:mm
        function formatTime(hour, minute) {
            return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        }

        // Helper: Crear selector de hora para desktop
        function createTimeSelector(name, value, isStart = true) {
            const { hour, minute } = parseTime(value);
            const wrapper = document.createElement('div');
            wrapper.className = 'aa-time-input-wrapper flex items-center gap-1';
            
            if (isMobile()) {
                // Móvil: usar input type="time" nativo con step de 30 minutos
                const input = document.createElement('input');
                input.type = 'time';
                input.name = name;
                input.step = '1800'; // 30 minutos en segundos (30 * 60)
                // Normalizar el valor a 00 o 30 minutos
                const { hour: h, minute: m } = parseTime(value);
                const normalizedM = m < 15 ? 0 : (m >= 15 && m < 45 ? 30 : 0);
                input.value = formatTime(h, normalizedM) || '00:00';
                input.className = 'aa-timepicker-mobile w-20 sm:w-20 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow';
                wrapper.appendChild(input);
            } else {
                // Desktop: usar selectores nativos
                const hourSelect = document.createElement('select');
                hourSelect.className = 'aa-time-hour w-16 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow bg-white';
                
                for (let h = 0; h < 24; h++) {
                    const option = document.createElement('option');
                    option.value = h;
                    option.textContent = String(h).padStart(2, '0');
                    if (h === hour) option.selected = true;
                    hourSelect.appendChild(option);
                }

                const minuteSelect = document.createElement('select');
                minuteSelect.className = 'aa-time-minute w-16 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow bg-white';
                
                // Solo minutos en incrementos de 30 (00 y 30 únicamente)
                const validMinutes = [0, 30];
                // Normalizar el minuto al valor más cercano válido
                const normalizedMinute = minute < 15 ? 0 : (minute >= 15 && minute < 45 ? 30 : 0);
                
                validMinutes.forEach(m => {
                    const option = document.createElement('option');
                    option.value = m;
                    option.textContent = String(m).padStart(2, '0');
                    if (m === normalizedMinute) {
                        option.selected = true;
                    }
                    minuteSelect.appendChild(option);
                });

                // Input hidden para el valor completo (este es el que se envía)
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = name;
                hiddenInput.value = formatTime(hour, normalizedMinute);
                hiddenInput.className = 'aa-time-value';

                // Actualizar hidden input cuando cambien los selectores
                function updateHidden() {
                    const h = parseInt(hourSelect.value);
                    const m = parseInt(minuteSelect.value);
                    // Asegurar que el minuto sea solo 0 o 30
                    const validM = validMinutes.includes(m) ? m : 0;
                    hiddenInput.value = formatTime(h, validM);
                }
                hourSelect.addEventListener('change', updateHidden);
                minuteSelect.addEventListener('change', updateHidden);

                // Crear el separador dos puntos
                const colon = document.createElement('span');
                colon.className = 'text-gray-400';
                colon.textContent = ':';

                // Hacer el wrapper clickeable para abrir los selectores
                wrapper.style.cursor = 'pointer';
                wrapper.setAttribute('role', 'button');
                wrapper.setAttribute('tabindex', '0');
                wrapper.addEventListener('click', function(e) {
                    // Si se hace click en el wrapper o en el separador (no en los selectores), enfocar el primer selector
                    if (e.target === wrapper || e.target === colon || (!e.target.closest('select'))) {
                        e.preventDefault();
                        e.stopPropagation();
                        hourSelect.focus();
                        // Forzar el click en el selector para abrir el dropdown
                        hourSelect.click();
                    }
                });
                // También permitir activar con Enter o Espacio
                wrapper.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        hourSelect.focus();
                        hourSelect.click();
                    }
                });

                wrapper.appendChild(hourSelect);
                wrapper.appendChild(colon);
                wrapper.appendChild(minuteSelect);
                wrapper.appendChild(hiddenInput);
            }
            
            return wrapper;
        }

        // Convertir inputs existentes a selectores en desktop
        function initTimeInputs() {
            document.querySelectorAll('.aa-timepicker-mobile').forEach(input => {
                if (input.dataset.converted) return;
                
                if (!isMobile()) {
                    const wrapper = input.closest('.aa-time-input-wrapper');
                    if (!wrapper) return;
                    
                    const name = input.name;
                    const value = input.value || '00:00';
                    
                    // Crear nuevo selector
                    const newWrapper = createTimeSelector(name, value);
                    
                    // Reemplazar el wrapper completo
                    if (wrapper.parentNode) {
                        wrapper.parentNode.replaceChild(newWrapper, wrapper);
                    }
                } else {
                    // En móvil, solo marcar como procesado
                    input.dataset.converted = 'true';
                }
            });
        }

        // Mostrar / ocultar bloques de intervalos según el checkbox del día
        document.querySelectorAll(".aa-day-block input[type=checkbox]").forEach(checkbox => {
            checkbox.addEventListener("change", function() {
                const day = this.name.match(/\[(.*?)\]/)[1];
                const container = document.querySelector(`.day-intervals[data-day="${day}"]`);
                if (this.checked) {
                    container.style.display = "block";
                } else {
                    container.style.display = "none";
                }
            });
        });

        // Manejo de añadir y eliminar intervalos
        document.querySelectorAll(".day-intervals").forEach(container => {
            container.addEventListener("click", function(e) {
                // Añadir intervalo
                if (e.target.classList.contains("add-interval")) {
                    e.preventDefault();
                    const day = this.dataset.day;
                    const index = this.querySelectorAll(".interval").length;

                    const div = document.createElement("div");
                    div.classList.add("interval");
                    div.classList.add("flex", "flex-wrap", "items-center", "gap-2", "sm:gap-3");
                    div.setAttribute('data-day', day);
                    div.setAttribute('data-index', index);

                    const startWrapper = createTimeSelector(`aa_schedule[${day}][intervals][${index}][start]`, '00:00', true);
                    const endWrapper = createTimeSelector(`aa_schedule[${day}][intervals][${index}][end]`, '23:30', false);

                    div.appendChild(startWrapper);
                    div.appendChild(document.createTextNode('—'));
                    div.appendChild(endWrapper);
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'remove-interval ml-auto p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors';
                    removeBtn.title = 'Eliminar intervalo';
                    removeBtn.innerHTML = `
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    `;
                    div.appendChild(removeBtn);

                    this.insertBefore(div, this.querySelector(".add-interval"));
                }

                // Eliminar intervalo
                if (e.target.classList.contains("remove-interval") || e.target.closest(".remove-interval")) {
                    e.preventDefault();
                    const interval = e.target.closest(".interval");
                    if (interval) interval.remove();
                }
            });
        });

        // Inicializar inputs existentes
        initTimeInputs();

        // Re-inicializar al cambiar tamaño de ventana (solo si cambia de móvil a desktop o viceversa)
        let resizeTimeout;
        let wasMobile = isMobile();
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                const nowMobile = isMobile();
                if (wasMobile !== nowMobile) {
                    // Cambió el tipo de dispositivo, recargar para re-renderizar
                    location.reload();
                }
                wasMobile = nowMobile;
            }, 300);
        });
    });

})();
