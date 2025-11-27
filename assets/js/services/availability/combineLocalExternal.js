/**
 * Módulo de Combinación de Disponibilidad Local y Externa
 * Responsabilidades:
 * - Combinar eventos ocupados de Google Calendar (externos) con reservas locales (BD)
 * - Normalizar fechas a objetos Date
 * - Evitar duplicación de datos
 */

/**
 * Combinar disponibilidad local y externa
 * @param {Object} externalAvailability - Objeto window.aa_availability con datos de Google Calendar
 * @param {Object} localAvailability - Objeto window.aa_local_availability con reservas locales
 */
export function combineLocalExternal(externalAvailability, localAvailability) {
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