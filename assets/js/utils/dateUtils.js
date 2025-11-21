// ==============================
// 🔹 Utilidades de manejo de fechas
// ==============================

// 🔹 Convertir Date a YYYY-MM-DD en zona horaria LOCAL
export const ymd = d => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// Devuelve el nombre del día en inglés en minúsculas
export function getWeekdayName(date) {
  const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
  const dayIndex = date.getDay();
  console.log(`🗓️ ${date.toDateString()} -> día ${dayIndex} (${days[dayIndex]})`);
  return days[dayIndex];
}

// Convierte "HH:MM" a minutos desde medianoche
export function timeStrToMinutes(str) {
  const [h, m] = str.split(':').map(Number);
  return h * 60 + m;
}

// Convierte Date a minutos desde medianoche
export function minutesFromDate(d) {
  return d.getHours() * 60 + d.getMinutes();
}

// Obtiene intervalos de un día (convertidos a minutos)
export function getDayIntervals(aa_schedule, weekday) {
  if (!aa_schedule || !aa_schedule[weekday] || !aa_schedule[weekday].enabled) return [];
  const intervals = aa_schedule[weekday].intervals || [];
  return intervals.map(iv => ({
    start: timeStrToMinutes(iv.start),
    end: timeStrToMinutes(iv.end)
  }));
}

// ✅ CORREGIDO: Verifica si un slot está ocupado
export function isSlotBusy(slotDate, busyRanges) {
  // Un slot está ocupado si HAY SUPERPOSICIÓN con algún evento
  // Superposición = el slot empieza ANTES de que termine el evento
  //                 Y termina DESPUÉS de que empiece el evento
  
  return busyRanges.some(range => {
    // ✅ CORRECCIÓN: Un slot de UN SOLO INSTANTE se considera ocupado si cae dentro del rango
    // Pero si el slot termina EXACTAMENTE donde empieza el evento, NO está ocupado
    return slotDate >= range.start && slotDate < range.end;
  });
}

// ✅ CORREGIDO: Genera slots disponibles para un día
export function generateSlotsForDay(date, intervals, busyRanges) {
  const slots = [];
  const now = new Date();
  const isToday = date.toDateString() === now.toDateString();
  
  const minAvailableTime = new Date(now.getTime() + 60 * 60 * 1000); // +1 hora
  
  intervals.forEach(iv => {
    // iv.start e iv.end están en MINUTOS desde medianoche
    for (let min = iv.start; min < iv.end; min += 30) {
      const slot = new Date(date);
      slot.setHours(Math.floor(min / 60), min % 60, 0, 0);
      
      // Saltar slots que son hoy y están muy cerca
      if (isToday && slot < minAvailableTime) {
        continue;
      }
      
      // ✅ Verificar si el slot NO está ocupado
      if (!isSlotBusy(slot, busyRanges)) {
        slots.push(slot);
      }
    }
  });
  
  return slots;
}

// ✅ Exponer globalmente para compatibilidad con scripts legacy
window.DateUtils = {
  ymd,
  getWeekdayName,
  timeStrToMinutes,
  minutesFromDate,
  getDayIntervals,
  isSlotBusy,
  generateSlotsForDay
};

console.log('✅ dateUtils.js cargado y exportado');