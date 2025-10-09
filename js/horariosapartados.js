// ...existing code...
document.addEventListener("DOMContentLoaded", () => {
  const fechaInput = document.getElementById("fecha");
  if (!fechaInput) return;

  fechaInput.addEventListener("focus", async () => {
    // Llamamos al proxy interno de WP para evitar CORS
    const url = `${aa_backend.ajax_url}?action=${encodeURIComponent(aa_backend.action)}&email=${encodeURIComponent(aa_backend.email)}`;
    console.log("📡 Consultando disponibilidad (proxy):", url);

    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      if (!response.ok) throw new Error("Error HTTP " + response.status);

      const data = await response.json();
      console.log("✅ Respuesta recibida del backend (vía proxy):", data);

      // Guardar la disponibilidad en una variable global para que otros scripts la usen
      window.aa_availability = data;

      // Emitir un evento personalizado que puede escuchar form-handler.js u otros
      document.dispatchEvent(new CustomEvent('aa:availability:loaded', { detail: data }));
    } catch (err) {
      console.error("❌ Error al consultar disponibilidad:", err);
      document.dispatchEvent(new CustomEvent('aa:availability:error', { detail: { error: err } }));
    }
  });
});