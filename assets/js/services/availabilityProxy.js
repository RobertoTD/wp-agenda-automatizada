/**
 * Servicio Proxy de Disponibilidad
 * Responsabilidades:
 * - Construir URL de consulta al backend
 * - Realizar fetch con reintentos automáticos
 * - Normalizar datos recibidos (data.busy)
 * - Combinar disponibilidad local y externa
 * - Calcular slots disponibles por día
 * - Emitir eventos de éxito/error
 */

import { 
  ymd, 
  getWeekdayName, 
  getDayIntervals, 
  generateSlotsForDay
} from '../utils/dateUtils.js';

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
    this.availableSlotsPerDay = {};
    this.busyRanges = [];
  }

  /**
   * Construir URL de consulta
   */
  buildUrl() {
    return `${this.ajaxUrl}?action=${encodeURIComponent(this.action)}&email=${encodeURIComponent(this.email)}`;
  }

  /**
   * Combinar disponibilidad local y externa
   */
  combineAvailabilityData() {
    if (typeof window.aa_local_availability !== 'undefined' && window.aa_local_availability.local_busy) {
      console.log("📊 Combinando disponibilidad local con datos externos");
      
      if (window.aa_availability) {
        const externalBusy = window.aa_availability.busy || [];
        const localBusy = window.aa_local_availability.local_busy.map(slot => ({
          start: new Date(slot.start),
          end: new Date(slot.end)
        }));
        
        window.aa_availability.busy = [...externalBusy, ...localBusy];
        
        console.log(`✅ Total combinado: ${window.aa_availability.busy.length}`);
        console.log(`   - Google Calendar: ${externalBusy.length}`);
        console.log(`   - Local: ${localBusy.length}`);
      }
    }
  }

  /**
   * Generar busyRanges desde window.aa_availability
   */
  generateBusyRanges() {
    const busy = (window.aa_availability?.busy) || [];
    console.log(`🔍 Generando busyRanges desde ${busy.length} eventos ocupados`);
    
    this.busyRanges = busy.map(ev => ({
      start: new Date(ev.start),
      end: new Date(ev.end)
    }));
    
    return this.busyRanges;
  }

  /**
   * Calcular slots disponibles por día
   */
  calculateAvailableSlots(schedule, futureWindow, slotDuration) {
    const minDate = new Date();
    const maxDate = new Date();
    maxDate.setDate(minDate.getDate() + futureWindow);

    this.availableSlotsPerDay = {};
    
    console.log(`🗓️ Calculando slots disponibles del ${ymd(minDate)} al ${ymd(maxDate)}`);
    
    for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
      const day = new Date(d);
      const weekday = getWeekdayName(day);
      const intervals = getDayIntervals(schedule, weekday);
      const slots = generateSlotsForDay(day, intervals, this.busyRanges, slotDuration);
      
      this.availableSlotsPerDay[ymd(day)] = slots;
      
      if (slots.length > 0) {
        console.log(`📅 ${ymd(day)}: ${slots.length} slots disponibles`);
      }
    }
    
    return this.availableSlotsPerDay;
  }

  /**
   * Verificar si una fecha tiene slots disponibles
   */
  isDateAvailable(date) {
    return (this.availableSlotsPerDay[ymd(date)]?.length || 0) > 0;
  }

  /**
   * Callback para deshabilitar fechas sin disponibilidad
   */
  disableDate(date) {
    return !this.isDateAvailable(date);
  }

  /**
   * Obtener slots para una fecha específica
   */
  getSlotsForDate(date) {
    return this.availableSlotsPerDay[ymd(date)] || [];
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

      // ✅ Combinar con datos locales
      this.combineAvailabilityData();

      // ✅ Generar busyRanges
      this.generateBusyRanges();

      // Detener reintentos
      if (this.intervalId) {
        clearInterval(this.intervalId);
        this.intervalId = null;
      }

      // Emitir evento de éxito
      console.log("🔔 Disparando evento 'aa:availability:loaded'");
      document.dispatchEvent(new CustomEvent('aa:availability:loaded', { 
        detail: {
          ...data,
          busyRanges: this.busyRanges,
          proxy: this // Pasar referencia al proxy para acceder a métodos
        }
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

// ==============================
// 🔹 Capa de Servicio: Abstracción sobre AvailabilityProxy
// ==============================
export const AvailabilityService = {

  /**
   * Calcula slots disponibles usando el proxy
   */
  calculate(proxy, { schedule, futureWindow, slotDuration }) {
    console.log('🔧 AvailabilityService.calculate() invocado');
    return proxy.calculateAvailableSlots(schedule, futureWindow, slotDuration);
  },

  /**
   * Determina si una fecha debe estar deshabilitada
   */
  disable(proxy, date) {
    return proxy.disableDate(date);
  },

  /**
   * Obtiene slots para una fecha específica
   */
  slotsForDate(proxy, date) {
    return proxy.getSlotsForDate(date);
  }
};

// Exponer servicio globalmente
window.AvailabilityService = AvailabilityService;

console.log('✅ AvailabilityProxy cargado');
console.log('✅ AvailabilityService cargado');