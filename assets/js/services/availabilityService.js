/**
 * Servicio de Disponibilidad Local
 */
(function() {
  'use strict';

  // ✅ Referencias locales (dentro del IIFE)
  const { loadLocalBusyRanges } = window.BusyRanges;
  const { calculateSlotsRange } = window.SlotCalculator;
  const { ymd, getWeekdayName, getDayIntervals, computeLimits, isDateInRange } = window.DateUtils;

  // ==============================
  // 🔹 Capa de Servicio
  // ==============================
  const AvailabilityService = {

    loadLocal() {
      return loadLocalBusyRanges();
    },

    async calculateInitial(busyRanges) {
      const schedule = window.aa_schedule || {};
      const futureWindow = window.aa_future_window || 14;

      // Calcular límites usando DateUtils.computeLimits si existe, o manualmente
      let minDate, maxDate;
      if (typeof computeLimits === 'function') {
        const limits = computeLimits(futureWindow);
        minDate = limits.minDate;
        maxDate = limits.maxDate;
        minDate.setHours(0, 0, 0, 0);
        maxDate.setHours(23, 59, 59, 999);
      } else {
        minDate = new Date();
        minDate.setHours(0, 0, 0, 0);
        maxDate = new Date();
        maxDate.setDate(minDate.getDate() + futureWindow);
        maxDate.setHours(23, 59, 59, 999);
      }

      console.log('📅 [AvailabilityService] Calculando días disponibles...');
      console.log(`   Rango: ${ymd(minDate)} al ${ymd(maxDate)}`);
      console.log(`   Ventana futura: ${futureWindow} días`);

      const availableDays = {};

      // Iterar día por día desde schedule
      for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
        const day = new Date(d);
        const weekday = getWeekdayName(day);
        const dayKey = ymd(day);

        // Usar EXACTAMENTE la misma lógica que SlotCalculator
        // Un día es válido si getDayIntervals devuelve al menos un intervalo
        const intervals = getDayIntervals(schedule, weekday);
        
        if (intervals.length > 0) {
          availableDays[dayKey] = true;
        } else {
          availableDays[dayKey] = false;
        }
      }

      const scheduleDaysCount = Object.values(availableDays).filter(v => v === true).length;
      console.log(`📅 [AvailabilityService] Días desde schedule: ${scheduleDaysCount}`);

      // Función helper para combinar días de assignments
      async function mergeAssignmentDays(availableDays, minDate, maxDate) {
        if (!window.AAAssignmentsAvailability) {
          console.log('ℹ️ [AvailabilityService] AAAssignmentsAvailability no disponible, omitiendo assignments');
          return availableDays;
        }

        try {
          const result = await window.AAAssignmentsAvailability.getAssignmentDates();

          if (!result.success || !Array.isArray(result.data.dates)) {
            console.warn('⚠️ [AvailabilityService] No se pudieron obtener fechas de assignments');
            return availableDays;
          }

          let assignmentDaysAdded = 0;
          result.data.dates.forEach(dateStr => {
            if (isDateInRange(dateStr, minDate, maxDate)) {
              // Marcar como disponible (puede sobrescribir false desde schedule)
              availableDays[dateStr] = true;
              assignmentDaysAdded++;
            }
          });

          console.log(`📅 [AvailabilityService] Días desde assignments: ${assignmentDaysAdded}`);
          return availableDays;
        } catch (error) {
          console.error('❌ [AvailabilityService] Error al obtener fechas de assignments:', error);
          return availableDays;
        }
      }

      // Combinar días de assignments
      await mergeAssignmentDays(availableDays, minDate, maxDate);

      const totalAvailable = Object.values(availableDays).filter(v => v === true).length;
      console.log(`✅ [AvailabilityService] Días disponibles totales: ${totalAvailable} de ${Object.keys(availableDays).length}`);

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

    calculate(busyRanges, { schedule, futureWindow, slotDuration }) {
      const minDate = new Date();
      const maxDate = new Date();
      maxDate.setDate(minDate.getDate() + futureWindow);

      return calculateSlotsRange(
        minDate, 
        maxDate, 
        schedule, 
        busyRanges, 
        slotDuration
      );
    },

    isDateAvailable(availableSlotsPerDay, date) {
      const key = window.DateUtils.ymd(date);
      return (availableSlotsPerDay[key]?.length || 0) > 0;
    },

    disable(availableSlotsPerDay, date) {
      return !this.isDateAvailable(availableSlotsPerDay, date);
    },

    slotsForDate(availableSlotsPerDay, date) {
      const key = window.DateUtils.ymd(date);
      return availableSlotsPerDay[key] || [];
    }
  };

  // ✅ Exponer servicio globalmente
  window.AvailabilityService = AvailabilityService;

  console.log('✅ AvailabilityService cargado (local only)');
})();