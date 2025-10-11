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

                div.innerHTML = `
                    <input type="time" name="aa_schedule[${day}][intervals][${index}][start]" step="1800" required>
                    <input type="time" name="aa_schedule[${day}][intervals][${index}][end]" step="1800" required>
                    <button type="button" class="remove-interval button">Eliminar</button>
                `;

                this.insertBefore(div, this.querySelector(".add-interval"));
            }

            // Eliminar intervalo
            if (e.target.classList.contains("remove-interval")) {
                e.preventDefault();
                e.target.closest(".interval").remove();
            }
        });
    });

});
