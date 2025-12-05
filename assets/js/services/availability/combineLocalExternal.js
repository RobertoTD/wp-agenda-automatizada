/**
 * Módulo de Combinación de Disponibilidad Local y Externa
 */

/**
 * Combinar disponibilidad local y externa
 */
function combineLocalExternal(externalAvailability, localAvailability) {
  if (!localAvailability || !localAvailability.local_busy) {
    console.log('ℹ️ No hay datos locales para combinar');
    return;
  }

  console.log("📊 Combinando disponibilidad local con datos externos");
  
  if (!externalAvailability) {
    console.warn('⚠️ No hay disponibilidad externa para combinar');
    return;
  }

  const externalBusy = externalAvailability.busy || [];
  const localBusy = localAvailability.local_busy.map(slot => ({
    start: new Date(slot.start),
    end: new Date(slot.end)
  }));
  
  // ✅ COMBINAR sin duplicar
  externalAvailability.busy = [...externalBusy, ...localBusy];
  
  console.log(`✅ Total combinado: ${externalAvailability.busy.length}`);
  console.log(`   - Google Calendar: ${externalBusy.length}`);
  console.log(`   - Local: ${localBusy.length}`);
}

// ✅ Exponer globalmente
window.CombineLocalExternal = {
  combineLocalExternal
};

console.log('✅ combineLocalExternal.js cargado');