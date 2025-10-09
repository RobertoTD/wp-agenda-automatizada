document.addEventListener("DOMContentLoaded", () => {
  const fechaInput = document.getElementById("fecha");
  if (!fechaInput) return;

  fechaInput.addEventListener("focus", async () => {
    console.log("📡 Consultando disponibilidad en:", aa_backend.url);

    try {
      const response = await fetch(aa_backend.url, { credentials: 'same-origin' });
      if (!response.ok) throw new Error("Error HTTP " + response.status);

      const data = await response.json();
      console.log("✅ Respuesta recibida del backend:", data);

      // Guardar la disponibilidad en una variable global para que otros scripts la usen
      window.aa_availability = data;

      // Emitir un evento personalizado que puede escuchar form-handler.js u otros
      document.dispatchEvent(new CustomEvent('aa:availability:loaded', { detail: data }));

      // No abrir nuevas páginas ni alerts — la UI la maneja form-handler.js
    } catch (err) {
      console.error("❌ Error al consultar disponibilidad:", err);
      // Emitir evento de error si interesa manejarlo en la UI
      document.dispatchEvent(new CustomEvent('aa:availability:error', { detail: { error: err } }));
    }
  });
});
