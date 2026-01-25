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
  if (!aa_schedule || !aa_schedule[weekday]) return [];

  const day = aa_schedule[weekday];

  // 🔒 enabled legacy-safe
  if (day.enabled === '0' || day.enabled === 0) return [];

  let intervals = day.intervals;

  // 🔥 NORMALIZACIÓN CRÍTICA
  if (!Array.isArray(intervals)) {
    intervals = Object.values(intervals || {});
  }

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

// ✅ Verifica si una fecha está dentro de un rango
function isDateInRange(dateStr, minDate, maxDate) {
  const d = new Date(dateStr + 'T00:00:00');
  return d >= minDate && d <= maxDate;
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

// ✅ Normaliza un intervalo a una grilla fija de slots
// Redondea el inicio hacia arriba y el fin hacia abajo al múltiplo más cercano de gridMinutes
// NOTA: Esta función NO valida si cabe una duración específica de cita.
//       Esa validación se hace en generateSlotsForDay() con el parámetro slotDuration.
function normalizeIntervalToSlotGrid(startMin, endMin, gridMinutes = 30) {
  const normalizedStart = Math.ceil(startMin / gridMinutes) * gridMinutes;
  const normalizedEnd = Math.floor(endMin / gridMinutes) * gridMinutes;

  if (normalizedStart > normalizedEnd) {
    return null;
  }

  return {
    start: normalizedStart,
    end: normalizedEnd
  };
}

// ✅ Genera slots desde hora de inicio y duración
// Genera slots de 30 minutos a partir de una hora de inicio y duración total
// @param {string} startTime - Hora de inicio en formato "HH:MM" o "HH:MM:SS"
// @param {number} durationMinutes - Duración total en minutos
// @param {number} slotDuration - Duración de cada slot en minutos (default: 30)
// @returns {Array<string>} Array de slots en formato "HH:MM"
function generateSlotsFromStartTime(startTime, durationMinutes, slotDuration = 30) {
  if (!startTime || !durationMinutes || durationMinutes <= 0) {
    return [];
  }
  
  // Convertir hora de inicio a minutos desde medianoche
  const startMinutes = timeStrToMinutes(startTime);
  
  // Calcular hora de fin
  const endMinutes = startMinutes + durationMinutes;
  
  // Generar slots cada slotDuration minutos
  const slots = [];
  for (let min = startMinutes; min < endMinutes; min += slotDuration) {
    const hours = Math.floor(min / 60);
    const minutes = min % 60;
    slots.push(`${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`);
  }
  
  return slots;
}

// ✅ Genera slots disponibles para un día con duración configurable
function generateSlotsForDay(date, intervals, busyRanges, slotDuration = 30) {
  const slots = [];
  const now = new Date();
  const isToday = date.toDateString() === now.toDateString();
  
  console.log(`🕒 Generando slots para ${ymd(date)} con duración de ${slotDuration} min (política: allow in-progress slot until end time)`);
  
  intervals.forEach(iv => {
    // Calcular el último minuto de inicio permitido para que el slot no rebase el fin del intervalo
    const latestStartMin = iv.end - slotDuration;
    
    // Log debug si el intervalo es insuficiente para generar slots con esta duración
    if (latestStartMin < iv.start) {
      console.log(`⚠️ Intervalo insuficiente: ${Math.floor(iv.start/60)}:${String(iv.start%60).padStart(2,'0')}-${Math.floor(iv.end/60)}:${String(iv.end%60).padStart(2,'0')} no puede alojar slots de ${slotDuration}min (último inicio permitido: ${Math.floor(latestStartMin/60)}:${String(latestStartMin%60).padStart(2,'0')})`);
      return; // Saltar este intervalo (no genera slots)
    }
    
    // Iterar solo hasta latestStartMin para evitar que slotEnd rebase iv.end
    for (let min = iv.start; min <= latestStartMin; min += 30) {
      const slot = new Date(date);
      slot.setHours(Math.floor(min / 60), min % 60, 0, 0);
      
      // Para slots de hoy: permitir solo si el slot aún no ha terminado
      // Usar "gate" de máximo 30 min para el start-bound (independiente de duración real de la cita)
      if (isToday) {
        const gateMinutes = Math.min(30, slotDuration);
        const gateEnd = new Date(slot.getTime() + gateMinutes * 60000);
        
        // Si el slot ya terminó (gateEnd <= now), bloquearlo
        if (gateEnd <= now) {
          continue;
        }
        
        // Si gateEnd > now, el slot está en curso o futuro, puede pasar a validación
      }
      
      if (hasEnoughFreeTime(slot, slotDuration, busyRanges)) {
        slots.push(slot);
      }
    }
  });
  
  console.log(`✅ ${slots.length} slots válidos generados para ${ymd(date)}`);
  
  return slots;
}

// ✅ Extrae fecha en formato YYYY-MM-DD desde diferentes tipos de entrada
// @param {Date|string} value - Valor a convertir (Date, string YYYY-MM-DD, o string datetime parseable)
// @returns {string|null} - Fecha en formato YYYY-MM-DD o null si no se puede parsear
function extractYmd(value) {
  if (!value) return null;
  
  // Si value es Date => return ymd(value)
  if (value instanceof Date) {
    if (isNaN(value.getTime())) {
      return null; // Invalid date
    }
    return ymd(value);
  }
  
  // Si value ya es "YYYY-MM-DD" => return value
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return value;
  }
  
  // Si value es string datetime parseable => const d = new Date(value); si válido return ymd(d)
  if (typeof value === 'string') {
    try {
      const d = new Date(value);
      if (!isNaN(d.getTime())) {
        return ymd(d);
      }
    } catch (e) {
      // Fall through to return null
    }
  }
  
  // Si no se puede parsear => return null
  return null;
}

// ✅ Filtra un array de objetos para retornar solo aquellos con fecha actual o futura
// @param {Array} items - Array de objetos que contienen una propiedad con fecha
// @param {string} dateField - Nombre del campo que contiene la fecha en formato YYYY-MM-DD (default: 'assignment_date')
// @returns {Array} - Array filtrado con solo elementos de fecha actual o futura
function filterCurrentAndFutureDates(items, dateField = 'assignment_date') {
  if (!Array.isArray(items) || items.length === 0) {
    return [];
  }
  
  // Obtener fecha actual en formato YYYY-MM-DD (sin hora, solo fecha)
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const todayYmd = ymd(today);
  
  // Filtrar elementos cuya fecha sea >= hoy
  return items.filter(function(item) {
    if (!item || !item[dateField]) {
      return false;
    }
    
    // Extraer fecha en formato YYYY-MM-DD
    const itemDateStr = extractYmd(item[dateField]);
    if (!itemDateStr) {
      return false;
    }
    
    // Comparar fechas (YYYY-MM-DD se puede comparar como string)
    return itemDateStr >= todayYmd;
  });
}

// ✅ Parsea datetime tipo MySQL "YYYY-MM-DD HH:MM:SS" a Date
// @param {Date|string} value - Date existente o string datetime
// @returns {Date|null} - Date parseado o null si no se puede parsear
function parseMysqlDateTime(value) {
  if (!value) return null;
  
  // Si ya es Date, retornar Date
  if (value instanceof Date) {
    return isNaN(value.getTime()) ? null : value;
  }
  
  // Si es string con espacio, reemplazar primer espacio por T y hacer new Date
  if (typeof value === 'string') {
    const isoStr = value.replace(' ', 'T');
    const d = new Date(isoStr);
    return isNaN(d.getTime()) ? null : d;
  }
  
  return null;
}

// ✅ Determina si una cita sigue activa (próxima) o ya terminó (pasada)
// Una cita es "activa" mientras NO haya terminado (now < endTime)
// @param {Object} cita - Objeto de cita con fecha, fecha_fin y/o duracion
// @param {Date} now - Fecha/hora de referencia (default: new Date())
// @returns {boolean} - true si la cita sigue activa, false si ya terminó
function isAppointmentActive(cita, now = new Date()) {
  if (!cita || !cita.fecha) return false;
  
  const start = parseMysqlDateTime(cita.fecha);
  if (!start) return false;
  
  // Calcular endTime: usar fecha_fin si existe, si no calcular con duración
  let end;
  if (cita.fecha_fin) {
    end = parseMysqlDateTime(cita.fecha_fin);
    if (!end) return false;
  } else {
    // Fallback: fechaInicio + duración (o 60 min por defecto)
    const dur = parseInt(cita.duracion, 10) || 60;
    end = new Date(start.getTime() + dur * 60000);
  }
  
  // PRÓXIMA/ACTIVA: mientras no haya terminado
  return now < end;
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
  isDateInRange,
  isSlotBusy,
  hasEnoughFreeTime,
  normalizeIntervalToSlotGrid,
  generateSlotsFromStartTime,
  generateSlotsForDay,
  extractYmd,
  filterCurrentAndFutureDates,
  parseMysqlDateTime,
  isAppointmentActive
};

console.log('✅ dateUtils.js cargado y exportado');