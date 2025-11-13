// ==============================
// 🔹 Utilidades de manejo de fechas
// ==============================

// convertir fecha con formato YYYY-MM-DDTHH:mm:ss.sssZ de Date a YYYY-MM-DD string
const ymd = d => d.toISOString().slice(0, 10);

// devuelve el nombre del día en inglés en minúsculas
function getWeekdayName(date) {
  const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
  // 🔹 Usar getDay() directamente - ya está en zona horaria local correcta
  const dayIndex = date.getDay();
  console.log(`🗓️ Debug getWeekdayName: ${date.toDateString()} -> día ${dayIndex} (${days[dayIndex]})`);
  return days[dayIndex];
}

// convierte el formato "HH:MM" a minutos desde medianoche ejemplo: "14:30" => 870
function timeStrToMinutes(str) {
  const [h, m] = str.split(':').map(Number);
  return h * 60 + m;
}

// convierte un objeto Date a minutos desde medianoche  Date(14:30) => 870
function minutesFromDate(d) {
  return d.getHours() * 60 + d.getMinutes();
}

// obtiene los intervalos de un día específico del horario admin 
function getDayIntervals(aa_schedule, weekday) {
  if (!aa_schedule || !aa_schedule[weekday] || !aa_schedule[weekday].enabled) return [];
  const intervals = aa_schedule[weekday].intervals || [];
  return intervals.map(iv => ({
    start: timeStrToMinutes(iv.start),
    end: timeStrToMinutes(iv.end)
  }));
}

// verifica si una fecha dada cae dentro de algún rango ocupado
function isSlotBusy(slotDate, busyRanges) {
  return busyRanges.some(range => slotDate >= range.start && slotDate < range.end);
}

// genera todos los slots disponibles para un día dado, excluyendo los ocupados
function generateSlotsForDay(date, intervals, busyRanges) {
  const slots = [];
  const now = new Date();
  const isToday = date.toDateString() === now.toDateString();
  
  // 🔹 Calcular hora mínima disponible (1 hora después de ahora)
  const minAvailableTime = new Date(now.getTime() + 60 * 60 * 1000); // +1 hora
  
  intervals.forEach(iv => {
    for (let min = iv.start; min < iv.end; min += 30) {
      const slot = new Date(date);
      slot.setHours(Math.floor(min / 60), min % 60, 0, 0);
      
      // 🔹 Si es hoy, filtrar slots que ya pasaron o están muy cerca
      if (isToday && slot < minAvailableTime) {
        continue; // Saltar este slot
      }
      
      if (!isSlotBusy(slot, busyRanges)) slots.push(slot);
    }
  });
  return slots;
}