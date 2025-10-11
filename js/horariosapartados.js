// ...existing code...
document.addEventListener("DOMContentLoaded", () => {
  const fechaInput = document.getElementById("fecha");
  
   // 🔹 Comprueba si el input con id="fecha" existe en el DOM
  console.log('aa_debug: fechaInput =>', !!fechaInput);
  // 🔹 Muestra el objeto global "aa_backend" que PHP pasa desde wp_localize_script()
  console.log('aa_debug: aa_backend =>', typeof aa_backend !== 'undefined' ? aa_backend : 'undefined');

  if (!fechaInput) return;// 🔹 Si no hay input, no sigue (previene errores)

  fechaInput.addEventListener("focus", async () => {
    // 🔹 Recupera desde el objeto global "aa_backend" los datos enviados por PHP (admin-ajax, acción y email)
    const ajaxUrl = (typeof aa_backend !== 'undefined' && aa_backend.ajax_url) ? aa_backend.ajax_url : '/wp-admin/admin-ajax.php';
    const action = (typeof aa_backend !== 'undefined' && aa_backend.action) ? aa_backend.action : 'aa_get_availability';
    const email = (typeof aa_backend !== 'undefined' && aa_backend.email) ? aa_backend.email : '';

     // 🔹 Construye la URL para hacer la petición AJAX al proxy PHP
    
    const url = `${ajaxUrl}?action=${encodeURIComponent(action)}&email=${encodeURIComponent(email)}`;
    console.log("📡 aa_debug: Consultando disponibilidad (proxy):", url);

    try {
      // Forzar GET y mostrar info detallada
       // 🔹 Hace la petición GET al endpoint AJAX de WordPress (admin-ajax.php)
      // 🔹 'credentials: same-origin' asegura que se envíen cookies si hay sesión activa
      const start = Date.now();
      const response = await fetch(url, { method: 'GET', credentials: 'same-origin' });
      const duration = Date.now() - start;
      console.log(`aa_debug: fetch finished, status=${response.status}, time=${duration}ms, response.url=${response.url}`);
      
      // 🔹 Lee la respuesta cruda (texto) para fines de depuración
      const text = await response.text();
      console.log("aa_debug: Response text (raw):", text);
      
      // 🔹 Si la respuesta no es OK (código distinto de 200–299), lanza error
      if (!response.ok) {
        throw new Error("Error HTTP " + response.status + " - " + text);
      }
        // 🔹 Parsea la respuesta a JSON (la que viene del backend Render vía proxy PHP)
      const data = JSON.parse(text);
      console.log("✅ aa_debug: JSON recibido:", data);
       // 🔹 Guarda la respuesta globalmente (útil si luego el datepicker necesita esos datos)
      window.aa_availability = data;
       // 🔹 Lanza un evento personalizado para que otros scripts puedan reaccionar cuando la disponibilidad esté cargada
      document.dispatchEvent(new CustomEvent('aa:availability:loaded', { detail: data }));
    } catch (err) {
       // 🔹 Captura y muestra cualquier error de red o parseo
      console.error("❌ aa_debug: Error al consultar disponibilidad:", err);
      // 🔹 Lanza un evento personalizado de error (por si otro script lo necesita)
      document.dispatchEvent(new CustomEvent('aa:availability:error', { detail: { error: err } }));
    }
  });
});