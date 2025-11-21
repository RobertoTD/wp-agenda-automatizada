document.addEventListener("DOMContentLoaded", () => {
  const fechaInput = document.getElementById("fecha") || document.getElementById("cita-fecha");
  
  console.log('aa_debug: fechaInput =>', !!fechaInput);
  console.log('aa_debug: fechaInput ID =>', fechaInput ? fechaInput.id : 'null');
  console.log('aa_debug: aa_backend =>', typeof aa_backend !== 'undefined' ? aa_backend : 'undefined');

  if (!fechaInput) {
    console.warn('⚠️ aa_debug: No se encontró input #fecha ni #cita-fecha');
    return;
  }

  // ✅ LEER slot_duration desde localización
  const slotDuration = (typeof window.aa_slot_duration !== 'undefined') 
    ? window.aa_slot_duration 
    : 60;
  
  console.log(`⚙️ Duración de slot configurada: ${slotDuration} minutos`);

  const ajaxUrl = (typeof aa_backend !== 'undefined' && aa_backend.ajax_url) 
    ? aa_backend.ajax_url 
    : '/wp-admin/admin-ajax.php';
    
  const action = (typeof aa_backend !== 'undefined' && aa_backend.action) 
    ? aa_backend.action 
    : 'aa_get_availability';
    
  const email = (typeof aa_backend !== 'undefined' && aa_backend.email) 
    ? aa_backend.email 
    : '';

  const url = `${ajaxUrl}?action=${encodeURIComponent(action)}&email=${encodeURIComponent(email)}`;
  console.log("📡 Consultando disponibilidad:", url);

  let attempts = 0;
  let maxAttempts = 20;
  let intervalId = null;
  let dataReceived = false;

  async function fetchAvailability() {
    if (dataReceived) return;

    attempts++;
    console.log(`🔄 Intento #${attempts} de consultar disponibilidad...`);

    try {
      const start = Date.now();
      const response = await fetch(url, { 
        method: 'GET', 
        credentials: 'same-origin' 
      });
      const duration = Date.now() - start;
      
      console.log(`📥 Respuesta recibida (HTTP ${response.status}) en ${duration}ms`);

      const text = await response.text();
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${text}`);
      }

      const data = JSON.parse(text);
      console.log("✅ Datos recibidos:", data);

      // ✅ Procesar solo data.busy (eventos ocupados)
      if (data.busy && Array.isArray(data.busy)) {
        console.log(`🔍 Procesando ${data.busy.length} eventos ocupados`);
        
        data.busy = data.busy.map(ev => {
          const start = new Date(ev.start);
          const end = new Date(ev.end);
          
          if (isNaN(start.getTime()) || isNaN(end.getTime())) {
            console.warn('⚠️ Evento con fechas inválidas:', ev);
            return null;
          }
          
          return { start, end };
        }).filter(Boolean);
        
        console.log(`✅ ${data.busy.length} eventos válidos procesados`);
      }

      window.aa_availability = data;
      dataReceived = true;

      if (intervalId) clearInterval(intervalId);

      console.log("🔔 Disparando evento 'aa:availability:loaded'");
      document.dispatchEvent(new CustomEvent('aa:availability:loaded', { 
        detail: data 
      }));

    } catch (err) {
      console.warn(`⚠️ Error en intento #${attempts}:`, err.message);
      
      if (attempts >= maxAttempts) {
        console.error("❌ Máximo de intentos alcanzado");
        clearInterval(intervalId);
        document.dispatchEvent(new CustomEvent('aa:availability:error', { 
          detail: { error: err } 
        }));
      }
    }
  }

  fetchAvailability();
  intervalId = setInterval(fetchAvailability, 15000);
});
