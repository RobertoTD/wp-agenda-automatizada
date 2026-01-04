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
      const slotDuration = parseInt(window.aa_slot_duration, 10) || 60;

      const minDate = new Date();
      const maxDate = new Date();
      maxDate.setDate(minDate.getDate() + futureWindow);

      const availableSlotsPerDay = calculateSlotsRange(
        minDate,
        maxDate,
        schedule,
        busyRanges,
        slotDuration
      );

      // ==============================
      // 🔹 Merge assignment dates (habilitar fechas de asignaciones)
      // ==============================
      const initialAssignments = window.aa_initial_assignments || [];
      
      if (initialAssignments.length > 0) {
        const assignmentDates = window.DateUtils.getAssignmentDatesInRange(
          initialAssignments,
          minDate,
          maxDate
        );
        
        // Agregar fechas de asignaciones que no existen en availableSlotsPerDay
        assignmentDates.forEach(dateStr => {
          if (!availableSlotsPerDay[dateStr]) {
            availableSlotsPerDay[dateStr] = [];
            console.log(`📅 [AvailabilityService] Fecha de asignación agregada: ${dateStr}`);
          }
        });
        
        console.log(`✅ [AvailabilityService] ${assignmentDates.size} fechas de asignaciones mergeadas`);
      }

      return {
        availableSlotsPerDay,
        schedule,
        futureWindow,
        slotDuration,
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