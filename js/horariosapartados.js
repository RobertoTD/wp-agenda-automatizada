document.addEventListener("DOMContentLoaded", () => {
  const fechaInput = document.getElementById("fecha");
  if (!fechaInput) return;

  fechaInput.addEventListener("focus", async () => {
    console.log("📡 Consultando disponibilidad en:", aa_backend.url);

    try {
      const response = await fetch(aa_backend.url);
      if (!response.ok) throw new Error("Error HTTP " + response.status);

      const data = await response.json();
      console.log("✅ Respuesta recibida del backend:", data);

      // Solo mostramos la respuesta por ahora
      alert("Disponibilidad consultada con éxito. Revisa la consola para ver el JSON.");
    } catch (err) {
      console.error("❌ Error al consultar disponibilidad:", err);
      alert("Error al consultar disponibilidad: " + err.message);
    }
  });
});
