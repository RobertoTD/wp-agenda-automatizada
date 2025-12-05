// ==============================
// 🔹 Utilidades de manejo de fechas
// ==============================

// 🔹 Convertir Date a YYYY-MM-DD en zona horaria LOCAL
const ymd = d => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// 🔹 Convertir Date a HH:MM en zona horaria LOCAL
const hm = (d) => {
  const hh = String(d.getHours()).padStart(2, '0');
  const mm = String(d.getMinutes()).padStart(2, '0');
  return `${hh}:${mm}`;
};

// Devuelve el nombre del día en inglés en minúsculas
function getWeekdayName(date) {
  const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
  const dayIndex = date.getDay();
  console.log(`🗓️ ${date.toDateString()} -> día ${dayIndex} (${days[dayIndex]})`);
  return days[dayIndex];
}

// Convierte "HH:MM" a minutos desde medianoche
function timeStrToMinutes(str) {
  const [h, m] = str.split(':').map(Number);
  return h * 60 + m;
}

// Convierte Date a minutos desde medianoche
function minutesFromDate(d) {
  return d.getHours() * 60 + d.getMinutes();
}

// Obtiene intervalos de un día (convertidos a minutos)
function getDayIntervals(aa_schedule, weekday) {
  if (!aa_schedule || !aa_schedule[weekday] || !aa_schedule[weekday].enabled) return [];
  const intervals = aa_schedule[weekday].intervals || [];
  return intervals.map(iv => ({
    start: timeStrToMinutes(iv.start),
    end: timeStrToMinutes(iv.end)
  }));
}

// ==============================
// 🔹 Calcular límites de fecha (minDate/maxDate)
// ==============================
function computeLimits(futureWindow) {
  const minDate = new Date();
  const maxDate = new Date();
  maxDate.setDate(minDate.getDate() + futureWindow);
  return { minDate, maxDate };
}

// ==============================
// 🔹 Verificar si un slot tiene suficiente espacio libre
// ==============================
function hasEnoughFreeTime(slotStart, durationMinutes, busyRanges) {
  const slotEnd = new Date(slotStart.getTime() + durationMinutes * 60000);
  
  for (const busy of busyRanges) {
    const overlaps = slotStart < busy.end && slotEnd > busy.start;
    
    if (overlaps) {
      console.log(`❌ Slot ${slotStart.toLocaleTimeString()}-${slotEnd.toLocaleTimeString()} rechazado: intersecta con evento ${busy.start.toLocaleTimeString()}-${busy.end.toLocaleTimeString()}`);
      return false;
    }
  }
  
  return true;
}

// ✅ Verifica si un slot está ocupado (compatibilidad)
function isSlotBusy(slotDate, busyRanges) {
  return busyRanges.some(range => {
    return slotDate >= range.start && slotDate < range.end;
  });
}

// ✅ Genera slots disponibles para un día con duración configurable
function generateSlotsForDay(date, intervals, busyRanges, slotDuration = 30) {
  const slots = [];
  const now = new Date();
  const isToday = date.toDateString() === now.toDateString();
  
  const minAvailableTime = new Date(now.getTime() + 60 * 60 * 1000); // +1 hora
  
  console.log(`🕒 Generando slots para ${ymd(date)} con duración de ${slotDuration} min`);
  
  intervals.forEach(iv => {
    for (let min = iv.start; min < iv.end; min += 30) {
      const slot = new Date(date);
      slot.setHours(Math.floor(min / 60), min % 60, 0, 0);
      
      if (isToday && slot < minAvailableTime) {
        continue;
      }
      
      if (hasEnoughFreeTime(slot, slotDuration, busyRanges)) {
        slots.push(slot);
      }
    }
  });
  
  console.log(`✅ ${slots.length} slots válidos generados para ${ymd(date)}`);
  
  return slots;
}

// ✅ Exponer globalmente
window.DateUtils = {
  ymd,
  hm,
  getWeekdayName,
  timeStrToMinutes,
  minutesFromDate,
  getDayIntervals,
  computeLimits,
  isSlotBusy,
  hasEnoughFreeTime,
  generateSlotsForDay
};

console.log('✅ dateUtils.js cargado y exportado');