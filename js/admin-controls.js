document.addEventListener("DOMContentLoaded", function () {

    // ================================
    // 🔹 Inicializar Motivos de la Cita
    // ================================
    const hiddenInput = document.getElementById("aa-google-motivo-hidden");
    const motivosList = document.getElementById("aa-motivos-list");
    const addButton = document.getElementById("aa-add-motivo");
    const motivoInput = document.getElementById("aa-motivo-input");

    if (!hiddenInput || !motivosList || !addButton || !motivoInput) return;

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
                <button type="button" class="button remove-motivo" data-index="${index}">Eliminar</button>
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
});
