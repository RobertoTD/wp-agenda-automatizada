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
        
        // Mostrar / ocultar bloques de intervalos según el checkbox del día
        document.querySelectorAll(".aa-day-block input[type=checkbox]").forEach(checkbox => {
            checkbox.addEventListener("change", function() {
                const day = this.name.match(/\[(.*?)\]/)[1]; // ejemplo: monday, tuesday...
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
                    div.classList.add("flex", "items-center", "gap-3", "mb-2");

                    // ✅ Usamos Flatpickr para renderizar el selector de hora
                    div.innerHTML = `
                        <input class="aa-timepicker border rounded px-3 py-2" type="text" name="aa_schedule[${day}][intervals][${index}][start]" required>
                        <span class="aa-interval-separator">—</span>
                        <input class="aa-timepicker border rounded px-3 py-2" type="text" name="aa_schedule[${day}][intervals][${index}][end]" required>
                        <button type="button" class="remove-interval px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600">Eliminar</button>
                    `;

                    this.insertBefore(div, this.querySelector(".add-interval"));
                    
                    // Inicializar Flatpickr en los inputs recién creados
                    div.querySelectorAll(".aa-timepicker").forEach(input => {
                        if (typeof flatpickr !== "undefined") {
                            flatpickr(input, {
                                enableTime: true,
                                noCalendar: true,
                                time_24hr: true,
                                minuteIncrement: 30, // 👈 fuerza intervalos de 30 minutos
                                dateFormat: "H:i"
                            });
                        }
                    });
                }

                // Eliminar intervalo
                if (e.target.classList.contains("remove-interval")) {
                    e.preventDefault();
                    e.target.closest(".interval").remove();
                }
            });
        });

        // ===============================
        // 🔹 Inicializar Flatpickr en inputs existentes
        // ===============================
        if (typeof flatpickr !== "undefined") {
            // Wait a bit for Flatpickr to be fully loaded
            setTimeout(function() {
                document.querySelectorAll('input[type="time"], .aa-timepicker').forEach(input => {
                    // Marcar los ya inicializados para evitar duplicaciones
                    if (!input.classList.contains("flatpickr-applied")) {
                        input.classList.add("flatpickr-applied");
                        flatpickr(input, {
                            enableTime: true,
                            noCalendar: true,
                            time_24hr: true,
                            minuteIncrement: 30,
                            dateFormat: "H:i"
                        });
                    }
                });
            }, 100);
        } else {
            console.warn("⚠️ Flatpickr no está cargado o no se encontró.");
        }
    });

})();
