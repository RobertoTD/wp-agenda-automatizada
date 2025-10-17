// ...existing code...
document.addEventListener("DOMContentLoaded", () => {
  const fechaInput = document.getElementById("fecha");
  
   // 🔹 Comprueba si el input con id="fecha" existe en el DOM
  console.log('aa_debug: fechaInput =>', !!fechaInput);
  // 🔹 Muestra el objeto global "aa_backend" que PHP pasa desde wp_localize_script()
  console.log('aa_debug: aa_backend =>', typeof aa_backend !== 'undefined' ? aa_backend : 'undefined');

  if (!fechaInput) return;// 🔹 Si no hay input, no sigue (previene errores)

  // ================================
// 🔹 Llamar al backend automáticamente
// ================================

const ajaxUrl = (typeof aa_backend !== 'undefined' && aa_backend.ajax_url) ? aa_backend.ajax_url : '/wp-admin/admin-ajax.php';
const action  = (typeof aa_backend !== 'undefined' && aa_backend.action)  ? aa_backend.action  : 'aa_get_availability';
const email   = (typeof aa_backend !== 'undefined' && aa_backend.email)   ? aa_backend.email   : '';

const url = `${ajaxUrl}?action=${encodeURIComponent(action)}&email=${encodeURIComponent(email)}`;
console.log("📡 aa_debug: preparando consulta automática de disponibilidad:", url);

let attempts = 0;
let maxAttempts = 20; // ≈ 5 minutos de reintentos
let intervalId = null;
let dataReceived = false;

async function fetchAvailability() {
  if (dataReceived) return;

  attempts++;
  console.log(`aa_debug: intento #${attempts} de consultar disponibilidad...`);

  try {
    const start = Date.now();
    const response = await fetch(url, { method: 'GET', credentials: 'same-origin' });
    const duration = Date.now() - start;
    console.log(`aa_debug: fetch finished, status=${response.status}, time=${duration}ms`);

    const text = await response.text();
    if (!response.ok) throw new Error("Error HTTP " + response.status + " - " + text);

    const data = JSON.parse(text);
    console.log("✅ aa_debug: JSON recibido:", data);

    window.aa_availability = data;
    dataReceived = true;

    // Detener los reintentos
    if (intervalId) clearInterval(intervalId);

    // Disparar evento para form-handler.js
    document.dispatchEvent(new CustomEvent('aa:availability:loaded', { detail: data }));

  } catch (err) {
    console.warn("⚠️ aa_debug: fallo en intento #"+attempts, err.message);
    if (attempts >= maxAttempts) {
      console.error("❌ aa_debug: se alcanzó el máximo de intentos sin éxito");
      clearInterval(intervalId);
      document.dispatchEvent(new CustomEvent('aa:availability:error', { detail: { error: err } }));
    }
  }
}

// Ejecutar al cargar
fetchAvailability();
// Y repetir cada 15 segundos hasta éxito
intervalId = setInterval(fetchAvailability, 15000);


  
  });
