/**
 * Servicio Proxy de Disponibilidad
 * Responsabilidades:
 * - Construir URL de consulta al backend
 * - Realizar fetch con reintentos automáticos
 * - Normalizar datos recibidos (data.busy)
 * - Emitir eventos de éxito/error
 */

class AvailabilityProxy {
  constructor(config = {}) {
    this.ajaxUrl = config.ajaxUrl || '/wp-admin/admin-ajax.php';
    this.action = config.action || 'aa_get_availability';
    this.email = config.email || '';
    this.maxAttempts = config.maxAttempts || 20;
    this.retryInterval = config.retryInterval || 15000;
    
    this.attempts = 0;
    this.intervalId = null;
    this.dataReceived = false;
  }

  /**
   * Construir URL de consulta
   */
  buildUrl() {
    return `${this.ajaxUrl}?action=${encodeURIComponent(this.action)}&email=${encodeURIComponent(this.email)}`;
  }

  /**
   * Realizar fetch de disponibilidad con reintentos
   */
  async fetchAvailability() {
    if (this.dataReceived) return;

    this.attempts++;
    console.log(`🔄 Intento #${this.attempts} de consultar disponibilidad...`);

    const url = this.buildUrl();
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
      this.dataReceived = true;

      // Detener reintentos
      if (this.intervalId) {
        clearInterval(this.intervalId);
        this.intervalId = null;
      }

      // Emitir evento de éxito
      console.log("🔔 Disparando evento 'aa:availability:loaded'");
      document.dispatchEvent(new CustomEvent('aa:availability:loaded', { 
        detail: data 
      }));

    } catch (err) {
      console.warn(`⚠️ Error en intento #${this.attempts}:`, err.message);
      
      if (this.attempts >= this.maxAttempts) {
        console.error("❌ Máximo de intentos alcanzado");
        this.stop();
        
        // Emitir evento de error
        document.dispatchEvent(new CustomEvent('aa:availability:error', { 
          detail: { error: err } 
        }));
      }
    }
  }

  /**
   * Iniciar consulta con reintentos automáticos
   */
  start() {
    console.log("🚀 Iniciando AvailabilityProxy");
    
    // Primer intento inmediato
    this.fetchAvailability();
    
    // Reintentos periódicos
    this.intervalId = setInterval(() => {
      this.fetchAvailability();
    }, this.retryInterval);
  }

  /**
   * Detener reintentos
   */
  stop() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
      console.log("⏹️ AvailabilityProxy detenido");
    }
  }
}

// Exportar para uso global
window.AvailabilityProxy = AvailabilityProxy;

console.log('✅ AvailabilityProxy cargado');