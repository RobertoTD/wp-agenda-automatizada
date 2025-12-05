// ==============================
// 🔹 Funciones UI de selector de slots
// ==============================

/**
 * Renderiza selector de slots para frontend
 */
function render(containerId, validSlots, onSelectSlot) {
  const container = document.getElementById(containerId);
  
  if (!container) {
    console.warn(`⚠️ SlotSelectorUI: No se encontró contenedor #${containerId}`);
    return;
  }
  
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

/**
 * LEGACY: Mantener compatibilidad
 */
function renderAvailableSlots(containerId, validSlots, onSelectSlot) {
  console.warn('⚠️ renderAvailableSlots() es legacy, usa render() en su lugar');
  return render(containerId, validSlots, onSelectSlot);
}

// ==============================
// 🔹 Exponer en window
// ==============================
window.SlotSelectorUI = {
  render,
  renderAvailableSlots
};

console.log('✅ SlotSelectorUI cargado y expuesto globalmente');