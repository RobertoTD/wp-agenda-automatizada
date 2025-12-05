/**
 * Módulo de Cálculo de Slots
 */
(function() {
  'use strict';

  // ✅ Referencias locales (dentro del IIFE, no hay conflicto)
  const { ymd, getWeekdayName, getDayIntervals, generateSlotsForDay } = window.DateUtils;

  /**
   * Calcular slots disponibles para una fecha específica
   */
  function calculateSlotsForDate(date, schedule, busyRanges, slotDuration) {
    const weekday = getWeekdayName(date);
    const intervals = getDayIntervals(schedule, weekday);
    const slots = generateSlotsForDay(date, intervals, busyRanges, slotDuration);
    
    return slots;
  }

  /**
   * Calcular slots disponibles para un rango de fechas
   */
  function calculateSlotsRange(minDate, maxDate, schedule, busyRanges, slotDuration) {
    const availableSlotsPerDay = {};
    
    console.log(`🗓️ Calculando slots disponibles del ${ymd(minDate)} al ${ymd(maxDate)}`);
    console.log(`⚙️ Configuración: duración=${slotDuration}min, eventos ocupados=${busyRanges.length}`);
    
    for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
      const day = new Date(d);
      const slots = calculateSlotsForDate(day, schedule, busyRanges, slotDuration);
      
      availableSlotsPerDay[ymd(day)] = slots;
      
      if (slots.length > 0) {
        console.log(`📅 ${ymd(day)}: ${slots.length} slots disponibles`);
      }
    }
    
    console.log(`✅ Cálculo completado para ${Object.keys(availableSlotsPerDay).length} días`);
    
    return availableSlotsPerDay;
  }

  // ✅ Exponer globalmente
  window.SlotCalculator = {
    calculateSlotsForDate,
    calculateSlotsRange
  };

  console.log('✅ slotCalculator.js cargado');
})();