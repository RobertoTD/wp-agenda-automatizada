// ==============================
// 🔹 Funciones UI de selector de slots
// ==============================

/**
 * Crea un <select> con los horarios disponibles del día seleccionado
 * @param {string} containerId - ID del contenedor donde se renderizará el selector
 * @param {Array<Date>} validSlots - Array de fechas disponibles
 * @param {Function} onSelectSlot - Callback cuando se selecciona un slot
 */
export function renderAvailableSlots(containerId, validSlots, onSelectSlot) {
  const container = document.getElementById(containerId);
  container.innerHTML = ''; // limpiar

  if (!validSlots.length) {
    container.textContent = 'No hay horarios disponibles para este día.';
    return;
  }

  const label = document.createElement('label');
  label.textContent = 'Horarios disponibles:';
  label.style.display = 'block';
  label.style.marginTop = '8px';

  const select = document.createElement('select');
  select.id = 'slot-selector';
  select.style.marginTop = '4px';
  select.style.width = '100%';
  select.style.padding = '4px';

  validSlots.forEach(date => {
    const option = document.createElement('option');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    
    // 🔹 Usar toISOString() que siempre genera UTC
    // El backend lo convertirá a la zona horaria correcta
    option.value = date.toISOString();
    option.textContent = `${hours}:${minutes}`;
    select.appendChild(option);
  });

  select.addEventListener('change', () => {
    const chosen = new Date(select.value);
    onSelectSlot(chosen);
  });

  container.appendChild(label);
  container.appendChild(select);
}

// ==============================
// 🔹 Exponer en window para compatibilidad con código no-modular
// ==============================
window.SlotSelectorUI = {
  renderAvailableSlots
};

console.log('✅ SlotSelectorUI cargado y expuesto globalmente');