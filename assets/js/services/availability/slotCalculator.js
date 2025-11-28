/**
 * Módulo de Cálculo de Slots
 * Responsabilidades:
 * - Calcular slots disponibles para una fecha específica
 * - Calcular slots disponibles para un rango de fechas
 * - Aplicar restricciones de horario y eventos ocupados
 */

import { 
  ymd, 
  getWeekdayName, 
  getDayIntervals, 
  generateSlotsForDay 
} from '../../utils/dateUtils.js';

/**
 * Calcular slots disponibles para una fecha específica
 * @param {Date} date - Fecha para calcular slots
 * @param {Object} schedule - Horario configurado (aa_schedule)
 * @param {Array} busyRanges - Eventos ocupados
 * @param {number} slotDuration - Duración del slot en minutos
 * @returns {Array<Date>} Array de slots disponibles
 */
export function calculateSlotsForDate(date, schedule, busyRanges, slotDuration) {
  const weekday = getWeekdayName(date);
  const intervals = getDayIntervals(schedule, weekday);
  const slots = generateSlotsForDay(date, intervals, busyRanges, slotDuration);
  
  return slots;
}

/**
 * Calcular slots disponibles para un rango de fechas
 * @param {Date} minDate - Fecha mínima
 * @param {Date} maxDate - Fecha máxima
 * @param {Object} schedule - Horario configurado (aa_schedule)
 * @param {Array} busyRanges - Eventos ocupados
 * @param {number} slotDuration - Duración del slot en minutos
 * @returns {Object} Mapa de slots por fecha { 'YYYY-MM-DD': [Date, Date, ...] }
 */
export function calculateSlotsRange(minDate, maxDate, schedule, busyRanges, slotDuration) {
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