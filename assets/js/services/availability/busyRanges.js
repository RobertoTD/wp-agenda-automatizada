/**
 * Módulo de Busy Ranges
 */

/**
 * Generar busyRanges desde eventos ocupados
 */
function generateBusyRanges(busyEvents) {
  if (!busyEvents || !Array.isArray(busyEvents)) {
    console.warn('⚠️ generateBusyRanges: No se recibieron eventos válidos');
    return [];
  }

  console.log(`🔍 Generando busyRanges desde ${busyEvents.length} eventos ocupados`);
  
  const busyRanges = busyEvents.map(ev => ({
    start: new Date(ev.start),
    end: new Date(ev.end)
  }));
  
  console.log(`✅ ${busyRanges.length} busyRanges generados`);
  
  return busyRanges;
}

/**
 * Cargar disponibilidad local desde window
 */
function loadLocalBusyRanges() {
  const localBusyRanges = [];
  console.log('[BusyRanges] loadLocalBusyRanges: typeof aa_local_availability=', typeof window.aa_local_availability, window.aa_local_availability ? 'local_busy=' + (window.aa_local_availability.local_busy ? window.aa_local_availability.local_busy.length : 0) : 'N/A');

  if (typeof window.aa_local_availability !== 'undefined' && window.aa_local_availability.local_busy) {
    console.log('✅ Datos locales encontrados:', window.aa_local_availability);
    
    window.aa_local_availability.local_busy.forEach((slot) => {
      const start = new Date(slot.start);
      const end = new Date(slot.end);
      
      if (!isNaN(start.getTime()) && !isNaN(end.getTime())) {
        localBusyRanges.push({ start, end });
      }
    });
    
    console.log(`📊 Total eventos locales: ${localBusyRanges.length}`);
  } else {
    console.log('ℹ️ No hay datos locales de disponibilidad');
  }
  
  return localBusyRanges;
}

/**
 * Convierte busyRanges con strings de fecha a objetos Date
 * @param {Array} busyRangesRaw - Array de objetos con {start: string, end: string, title?: string}
 * @returns {Array} Array de objetos con {start: Date, end: Date, title?: string}
 */
function toDateRanges(busyRangesRaw) {
  if (!Array.isArray(busyRangesRaw)) {
    console.warn('⚠️ toDateRanges: busyRangesRaw debe ser un array');
    return [];
  }

  const dateRanges = [];
  
  busyRangesRaw.forEach((range, index) => {
    if (!range || typeof range !== 'object') {
      console.warn(`⚠️ toDateRanges: Rango en índice ${index} no es un objeto válido`);
      return;
    }

    const start = new Date(range.start);
    const end = new Date(range.end);
    
    // Filtrar inválidos (NaN dates)
    if (isNaN(start.getTime()) || isNaN(end.getTime())) {
      console.warn(`⚠️ toDateRanges: Rango en índice ${index} tiene fechas inválidas`, range);
      return;
    }

    const dateRange = {
      start: start,
      end: end
    };

    // Preservar title si existe
    if (range.title) {
      dateRange.title = range.title;
    }

    dateRanges.push(dateRange);
  });

  return dateRanges;
}

/**
 * Filtra slots según busyRanges usando DateUtils.hasEnoughFreeTime
 * @param {Array<Date>} slots - Array de fechas/horas de slots
 * @param {number} durationMinutes - Duración del slot en minutos
 * @param {Array} busyRanges - Array de objetos {start: Date, end: Date, ...}
 * @returns {Array<Date>} Array de slots filtrados (solo los que tienen suficiente tiempo libre)
 */
function filterSlotsByBusyRanges(slots, durationMinutes, busyRanges) {
  if (!Array.isArray(slots)) {
    console.warn('⚠️ filterSlotsByBusyRanges: slots debe ser un array');
    return [];
  }

  if (!Array.isArray(busyRanges)) {
    console.warn('⚠️ filterSlotsByBusyRanges: busyRanges debe ser un array');
    return slots; // Retornar todos los slots si no hay busyRanges válidos
  }

  // Verificar si DateUtils existe
  if (typeof window.DateUtils === 'undefined' || typeof window.DateUtils.hasEnoughFreeTime !== 'function') {
    console.warn('⚠️ filterSlotsByBusyRanges: DateUtils.hasEnoughFreeTime no disponible, retornando todos los slots');
    return slots; // Fallback: retornar todos los slots
  }

  const filteredSlots = slots.filter(slot => {
    if (!(slot instanceof Date) || isNaN(slot.getTime())) {
      return false; // Filtrar slots inválidos
    }
    
    return window.DateUtils.hasEnoughFreeTime(slot, durationMinutes, busyRanges);
  });

  return filteredSlots;
}

/**
 * Construye busyRanges solo con datos locales
 * @param {Object} [opts={}] - Opciones
 * @param {boolean} [opts.includeLocal=true] - Incluir busy ranges locales
 * @returns {Object} { busyRanges: Array, localBusy: Array }
 */
function buildBusyRanges(opts = {}) {
  const {
    includeLocal = true
  } = opts;

  // Obtener busy ranges locales
  const localBusy = includeLocal && typeof loadLocalBusyRanges === 'function'
    ? loadLocalBusyRanges()
    : [];

  return { busyRanges: localBusy, localBusy };
}

// ✅ Exponer globalmente
window.BusyRanges = {
  generateBusyRanges,
  loadLocalBusyRanges,
  toDateRanges,
  filterSlotsByBusyRanges,
  buildBusyRanges
};

console.log('✅ busyRanges.js cargado (local only)');