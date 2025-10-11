document.addEventListener("DOMContentLoaded", function() {

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
                div.style.marginBottom = "6px";

                  // ✅ (AHORA) Usamos Flatpickr para renderizar el selector de hora
                div.innerHTML = `
                    <input class="aa-timepicker" type="text" name="aa_schedule[${day}][intervals][${index}][start]" required>
                    <input class="aa-timepicker" type="text" name="aa_schedule[${day}][intervals][${index}][end]" required>
                    <button type="button" class="remove-interval button">Eliminar</button>
                `;

                this.insertBefore(div, this.querySelector(".add-interval"));
                 // Inicializar Flatpickr en los inputs recién creados
                div.querySelectorAll(".aa-timepicker").forEach(input => {
                    flatpickr(input, {
                        enableTime: true,
                        noCalendar: true,
                        time_24hr: true,
                        minuteIncrement: 30, // 👈 fuerza intervalos de 30 minutos
                        dateFormat: "H:i"
                    });
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
    } else {
        console.warn("⚠️ Flatpickr no está cargado o no se encontró.");
    }

});
