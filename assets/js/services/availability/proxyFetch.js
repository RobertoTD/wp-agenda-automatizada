/**
 * Módulo de Fetch con Reintentos
 * Responsabilidades:
 * - Construir URL de consulta al backend
 * - Realizar fetch con reintentos automáticos
 * - Normalizar datos recibidos (data.busy)
 * - Guardar en window.aa_availability
 * - Emitir eventos de éxito/error
 */

/**
 * Construir URL de consulta
 */
export function buildUrl(config) {
  const { ajaxUrl, action, email } = config;
  return `${ajaxUrl}?action=${encodeURIComponent(action)}&email=${encodeURIComponent(email)}`;
}

/**
 * Estado interno del loop de reintentos
 */
let attempts = 0;
let intervalId = null;
let dataReceived = false;

/**
 * Realizar fetch de disponibilidad (single attempt)
 */
export async function fetchAvailability(config, onSuccess, onError) {
  if (dataReceived) return;

  attempts++;
  console.log(`🔄 Intento #${attempts} de consultar disponibilidad...`);

  const url = buildUrl(config);
  console.log("📡 Consultando disponibilidad:", url);

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

    // ✅ Procesar y normalizar data.busy (eventos ocupados)
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

    // Guardar en window para compatibilidad
    window.aa_availability = data;
    dataReceived = true;

    // Detener reintentos
    stopFetchLoop();

    // Callback de éxito
    if (onSuccess) {
      onSuccess(data);
    }

    // Emitir evento de éxito
    console.log("🔔 Disparando evento 'aa:availability:loaded'");
    document.dispatchEvent(new CustomEvent('aa:availability:loaded', { 
      detail: data
    }));

  } catch (err) {
    console.warn(`⚠️ Error en intento #${attempts}:`, err.message);
    
    if (attempts >= config.maxAttempts) {
      console.error("❌ Máximo de intentos alcanzado");
      stopFetchLoop();
      
      // Callback de error
      if (onError) {
        onError(err);
      }
      
      // Emitir evento de error
      document.dispatchEvent(new CustomEvent('aa:availability:error', { 
        detail: { error: err } 
      }));
    }
  }
}

/**
 * Iniciar loop de reintentos automáticos
 */
export function startFetchLoop(config, onSuccess, onError) {
  console.log("🚀 Iniciando loop de fetch con reintentos");
  
  // Reset estado
  attempts = 0;
  dataReceived = false;
  
  // Primer intento inmediato
  fetchAvailability(config, onSuccess, onError);
  
  // Reintentos periódicos
  intervalId = setInterval(() => {
    fetchAvailability(config, onSuccess, onError);
  }, config.retryInterval);
}

/**
 * Detener reintentos
 */
export function stopFetchLoop() {
  if (intervalId) {
    clearInterval(intervalId);
    intervalId = null;
    console.log("⏹️ Loop de fetch detenido");
  }
}

/**
 * Reset estado (útil para testing o re-inicialización)
 */
export function resetFetchState() {
  attempts = 0;
  dataReceived = false;
  stopFetchLoop();
}