// ...existing code...
document.addEventListener("DOMContentLoaded", () => {
  const fechaInput = document.getElementById("fecha");
  console.log('aa_debug: fechaInput =>', !!fechaInput);
  console.log('aa_debug: aa_backend =>', typeof aa_backend !== 'undefined' ? aa_backend : 'undefined');

  if (!fechaInput) return;

  fechaInput.addEventListener("focus", async () => {
    const ajaxUrl = (typeof aa_backend !== 'undefined' && aa_backend.ajax_url) ? aa_backend.ajax_url : '/wp-admin/admin-ajax.php';
    const action = (typeof aa_backend !== 'undefined' && aa_backend.action) ? aa_backend.action : 'aa_get_availability';
    const email = (typeof aa_backend !== 'undefined' && aa_backend.email) ? aa_backend.email : '';

    const url = `${ajaxUrl}?action=${encodeURIComponent(action)}&email=${encodeURIComponent(email)}`;
    console.log("📡 aa_debug: Consultando disponibilidad (proxy):", url);

    try {
      // Forzar GET y mostrar info detallada
      const start = Date.now();
      const response = await fetch(url, { method: 'GET', credentials: 'same-origin' });
      const duration = Date.now() - start;
      console.log(`aa_debug: fetch finished, status=${response.status}, time=${duration}ms, response.url=${response.url}`);

      const text = await response.text();
      console.log("aa_debug: Response text (raw):", text);

      if (!response.ok) {
        throw new Error("Error HTTP " + response.status + " - " + text);
      }

      const data = JSON.parse(text);
      console.log("✅ aa_debug: JSON recibido:", data);

      window.aa_availability = data;
      document.dispatchEvent(new CustomEvent('aa:availability:loaded', { detail: data }));
    } catch (err) {
      console.error("❌ aa_debug: Error al consultar disponibilidad:", err);
      document.dispatchEvent(new CustomEvent('aa:availability:error', { detail: { error: err } }));
    }
  });
});