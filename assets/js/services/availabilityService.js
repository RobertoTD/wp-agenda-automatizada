/**
 * Servicio Proxy de Disponibilidad
 * Responsabilidades:
 * - Combinar disponibilidad local y externa
 * - Calcular slots disponibles por día
 * - Proveer interfaz de consulta de disponibilidad
 */

import { ymd } from '../utils/dateUtils.js';

import {
  startFetchLoop,
  stopFetchLoop
} from './availability/proxyFetch.js';

import { combineLocalExternal } from './availability/combineLocalExternal.js';
import { generateBusyRanges, loadLocalBusyRanges } from './availability/busyRanges.js';
import { calculateSlotsRange } from './availability/slotCalculator.js';

class AvailabilityProxy {
  constructor(config = {}) {
    this.ajaxUrl = config.ajaxUrl || '/wp-admin/admin-ajax.php';
    this.action = config.action || 'aa_get_availability';
    this.email = config.email || '';
    this.maxAttempts = config.maxAttempts || 20;
    this.retryInterval = config.retryInterval || 15000;
    
    this.availableSlotsPerDay = {};
    this.busyRanges = [];
  }

  /**
   * Calcular slots disponibles por día
   */
  calculateAvailableSlots(schedule, futureWindow, slotDuration) {
    const minDate = new Date();
    const maxDate = new Date();
    maxDate.setDate(minDate.getDate() + futureWindow);

    // ✅ Delegar cálculo a slotCalculator
    this.availableSlotsPerDay = calculateSlotsRange(
      minDate, 
      maxDate, 
      schedule, 
      this.busyRanges, 
      slotDuration
    );
    
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
   * Iniciar consulta con reintentos automáticos
   */
  start() {
    console.log("🚀 Iniciando AvailabilityProxy");
    
    const config = {
      ajaxUrl: this.ajaxUrl,
      action: this.action,
      email: this.email,
      maxAttempts: this.maxAttempts,
      retryInterval: this.retryInterval
    };

    const onSuccess = (data) => {
      // ✅ Combinar con datos locales
      combineLocalExternal(window.aa_availability, window.aa_local_availability);

      // ✅ Generar busyRanges
      this.busyRanges = generateBusyRanges(window.aa_availability?.busy || []);

      // Emitir evento extendido con proxy
      console.log("🔔 Disparando evento 'aa:availability:loaded' con proxy");
      document.dispatchEvent(new CustomEvent('aa:availability:loaded', { 
        detail: {
          ...data,
          busyRanges: this.busyRanges,
          proxy: this // Pasar referencia al proxy para acceder a métodos
        }
      }));
    };

    const onError = (err) => {
      console.error("❌ Error al cargar disponibilidad:", err);
    };

    startFetchLoop(config, onSuccess, onError);
  }

  /**
   * Detener reintentos
   */
  stop() {
    stopFetchLoop();
  }
}

// Exportar para uso global
window.AvailabilityProxy = AvailabilityProxy;

// ==============================
// 🔹 Capa de Servicio: Abstracción sobre AvailabilityProxy
// ==============================
export const AvailabilityService = {

  /**
   * Cargar disponibilidad local desde window
   */
  loadLocal() {
    return loadLocalBusyRanges();
  },

  /**
   * Calcular slots iniciales con busy ranges dados
   */
  calculateInitial(busyRanges) {
    const schedule = window.aa_schedule || {};
    const futureWindow = window.aa_future_window || 14;
    const slotDuration = parseInt(window.aa_slot_duration, 10) || 60;

    const minDate = new Date();
    const maxDate = new Date();
    maxDate.setDate(minDate.getDate() + futureWindow);

    // ✅ Delegar cálculo a slotCalculator
    const availableSlotsPerDay = calculateSlotsRange(
      minDate,
      maxDate,
      schedule,
      busyRanges,
      slotDuration
    );

    return {
      availableSlotsPerDay,
      schedule,
      futureWindow,
      slotDuration,
      minDate,
      maxDate
    };
  },

  /**
   * Encontrar primera fecha disponible
   */
  findFirstAvailable(minDate, maxDate, availableSlotsPerDay) {
    for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
      const day = new Date(d);
      const slots = availableSlotsPerDay[ymd(day)] || [];
      
      if (slots.length > 0) {
        return day;
      }
    }
    
    return null;
  },

  /**
   * Calcula slots disponibles usando el proxy
   */
  calculate(proxy, { schedule, futureWindow, slotDuration }) {
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