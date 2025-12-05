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

// ✅ Exponer globalmente
window.BusyRanges = {
  generateBusyRanges,
  loadLocalBusyRanges
};

console.log('✅ busyRanges.js cargado');