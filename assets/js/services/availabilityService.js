/**
 * Servicio Proxy de Disponibilidad
 */
(function() {
  'use strict';

  // ✅ Referencias locales (dentro del IIFE)
  const { startFetchLoop, stopFetchLoop } = window.ProxyFetch;
  const { combineLocalExternal } = window.CombineLocalExternal;
  const { generateBusyRanges, loadLocalBusyRanges } = window.BusyRanges;
  const { calculateSlotsRange } = window.SlotCalculator;

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

    calculateAvailableSlots(schedule, futureWindow, slotDuration) {
      const minDate = new Date();
      const maxDate = new Date();
      maxDate.setDate(minDate.getDate() + futureWindow);

      this.availableSlotsPerDay = calculateSlotsRange(
        minDate, 
        maxDate, 
        schedule, 
        this.busyRanges, 
        slotDuration
      );
      
      return this.availableSlotsPerDay;
    }

    isDateAvailable(date) {
      return (this.availableSlotsPerDay[window.DateUtils.ymd(date)]?.length || 0) > 0;
    }

    disableDate(date) {
      return !this.isDateAvailable(date);
    }

    getSlotsForDate(date) {
      const key = window.DateUtils.ymd(date);
      const slots = this.availableSlotsPerDay[key] || [];
      
      console.log(`🔍 getSlotsForDate(${key}): ${slots.length} slots disponibles`);
      
      return slots;
    }

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
        combineLocalExternal(window.aa_availability, window.aa_local_availability);
        this.busyRanges = generateBusyRanges(window.aa_availability?.busy || []);

        console.log("🔔 Disparando evento 'aa:availability:loaded' con proxy");
        document.dispatchEvent(new CustomEvent('aa:availability:loaded', { 
          detail: {
            ...data,
            busyRanges: this.busyRanges,
            proxy: this
          }
        }));
      };

      const onError = (err) => {
        console.error("❌ Error al cargar disponibilidad:", err);
      };

      startFetchLoop(config, onSuccess, onError);
    }

    stop() {
      stopFetchLoop();
    }
  }

  // ✅ Exponer clase globalmente
  window.AvailabilityProxy = AvailabilityProxy;

  // ==============================
  // 🔹 Capa de Servicio
  // ==============================
  const AvailabilityService = {

    loadLocal() {
      return loadLocalBusyRanges();
    },

    calculateInitial(busyRanges) {
      const schedule = window.aa_schedule || {};
      const futureWindow = window.aa_future_window || 14;

      const minDate = new Date();
      minDate.setHours(0, 0, 0, 0);
      const maxDate = new Date();
      maxDate.setDate(minDate.getDate() + futureWindow);
      maxDate.setHours(23, 59, 59, 999);

      console.log('📅 [AvailabilityService] Calculando días disponibles...');
      console.log(`   Rango: ${window.DateUtils.ymd(minDate)} al ${window.DateUtils.ymd(maxDate)}`);
      console.log(`   Ventana futura: ${futureWindow} días`);

      const availableDays = {};

      // Iterar día por día
      for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
        const day = new Date(d);
        const weekday = window.DateUtils.getWeekdayName(day);
        const dayKey = window.DateUtils.ymd(day);

        // Verificar si el día de la semana está habilitado en el schedule
        if (schedule[weekday] && schedule[weekday].enabled === 1) {
          availableDays[dayKey] = true;
        } else {
          availableDays[dayKey] = false;
        }
      }

      const totalAvailable = Object.values(availableDays).filter(v => v === true).length;
      console.log(`✅ [AvailabilityService] Días disponibles calculados: ${totalAvailable} de ${Object.keys(availableDays).length}`);

      return {
        availableDays,
        schedule,
        futureWindow,
        minDate,
        maxDate
      };
    },

    findFirstAvailable(minDate, maxDate, availableSlotsPerDay) {
      for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
        const day = new Date(d);
        const slots = availableSlotsPerDay[window.DateUtils.ymd(day)] || [];
        
        if (slots.length > 0) {
          return day;
        }
      }
      
      return null;
    },

    calculate(proxy, { schedule, futureWindow, slotDuration }) {
      return proxy.calculateAvailableSlots(schedule, futureWindow, slotDuration);
    },

    disable(proxy, date) {
      return proxy.disableDate(date);
    },

    slotsForDate(proxy, date) {
      return proxy.getSlotsForDate(date);
    }
  };

  // ✅ Exponer servicio globalmente
  window.AvailabilityService = AvailabilityService;

  console.log('✅ AvailabilityProxy cargado');
  console.log('✅ AvailabilityService cargado');
})();