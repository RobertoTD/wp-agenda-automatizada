// ==============================
// 🔹 UI de Selector de Slots para Admin
// ==============================

console.log('🔄 Cargando slotSelectorAdminUI.js...');

/**
 * Renderiza los slots disponibles en un select para admin
 * @param {string} containerId - ID del contenedor
 * @param {Array<Date>} validSlots - Array de fechas disponibles
 * @param {Date} selectedDate - Fecha seleccionada
 * @param {HTMLElement} fechaInput - Input donde se escribe la fecha+hora final
 */
export function renderAdminSlots(containerId, validSlots, selectedDate, fechaInput) {
  const container = document.getElementById(containerId);
  
  if (!container) {
    console.warn(`⚠️ slotSelectorAdminUI: No se encontró contenedor #${containerId}`);
    return;
  }
  
  // Limpiar contenedor
  container.innerHTML = '';
  
  // Caso: No hay slots disponibles
  if (!validSlots || validSlots.length === 0) {
    container.textContent = 'No hay horarios disponibles para esta fecha.';
    console.log('ℹ️ slotSelectorAdminUI: Sin slots disponibles');
    return;
  }
  
  console.log(`📋 slotSelectorAdminUI: Renderizando ${validSlots.length} slots`);
  
  // Crear select
  const select = document.createElement('select');
  select.id = 'slot-selector-admin';
  select.className = 'slot-selector-admin';
  select.style.width = '100%';
  select.style.padding = '8px';
  select.style.marginTop = '10px';
  select.style.fontSize = '14px';
  select.style.border = '1px solid #ddd';
  select.style.borderRadius = '4px';
  
  // Agregar opciones
  validSlots.forEach((slotDate, index) => {
    const option = document.createElement('option');
    option.value = slotDate.toISOString();
    option.textContent = slotDate.toLocaleTimeString('es-MX', { 
      hour: '2-digit', 
      minute: '2-digit' 
    });
    
    select.appendChild(option);
    
    if (index === 0) {
      option.selected = true;
    }
  });
  
  // Evento de cambio
  select.addEventListener('change', () => {
    const chosenSlot = new Date(select.value);
    const formattedDate = selectedDate.toLocaleDateString('es-MX', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
    const formattedTime = chosenSlot.toLocaleTimeString('es-MX', { 
      hour: '2-digit', 
      minute: '2-digit' 
    });
    
    fechaInput.value = `${formattedDate} ${formattedTime}`;
    
    console.log(`🕒 Slot seleccionado: ${fechaInput.value}`);
  });
  
  // Agregar al contenedor
  container.appendChild(select);
  
  // Setear valor inicial en el input
  if (validSlots[0]) {
    const formattedDate = selectedDate.toLocaleDateString('es-MX', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
    const formattedTime = validSlots[0].toLocaleTimeString('es-MX', { 
      hour: '2-digit', 
      minute: '2-digit' 
    });
    
    fechaInput.value = `${formattedDate} ${formattedTime}`;
    
    console.log(`✅ Valor inicial establecido: ${fechaInput.value}`);
  }
}

/**
 * Inicializar escucha de eventos de selección de fecha
 */
function initEventListeners() {
  document.addEventListener('aa:admin:date-selected', (event) => {
    const { containerId, validSlots, selectedDate, fechaInput } = event.detail;
    renderAdminSlots(containerId, validSlots, selectedDate, fechaInput);
  });
  
  console.log('👂 slotSelectorAdminUI: Escuchando eventos aa:admin:date-selected');
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initEventListeners);
} else {
  initEventListeners();
}

// ==============================
// 🔹 Exponer en window para compatibilidad
// ==============================
window.SlotSelectorAdminUI = {
  renderAdminSlots
};

console.log('✅ SlotSelectorAdminUI cargado y expuesto globalmente');