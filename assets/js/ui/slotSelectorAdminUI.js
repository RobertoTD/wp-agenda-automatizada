// ==============================
// 🔹 UI de Selector de Slots para Admin
// ==============================

console.log('🔄 Cargando slotSelectorAdminUI.js...');

(function() {
  'use strict';

  /**
   * Renderiza los slots disponibles en un select para admin
   * @param {string} containerId - ID del contenedor
   * @param {Array<Date>} validSlots - Array de fechas disponibles
   * @param {Date} selectedDate - Fecha seleccionada
   * @param {HTMLElement} fechaInput - Input donde se escribe la fecha+hora final
   */
  function renderAdminSlots(containerId, validSlots, selectedDate, fechaInput) {
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
    
    // ✅ NO setear valor inicial automáticamente
    // El valor solo debe cambiar cuando el usuario seleccione explícitamente
    console.log(`✅ Select renderizado con ${validSlots.length} opciones`);
  }

  /**
   * Inicializar escucha de eventos de selección de fecha
   */
  function initEventListeners() {
    document.addEventListener('aa:admin:date-selected', (event) => {
      console.log('📨 slotSelectorAdminUI: Evento aa:admin:date-selected recibido');
      const { containerId, validSlots, selectedDate, fechaInput } = event.detail;
      renderAdminSlots(containerId, validSlots, selectedDate, fechaInput);
    });
    
    console.log('👂 slotSelectorAdminUI: Escuchando eventos aa:admin:date-selected');
  }

  // ✅ IMPORTANTE: Inicializar INMEDIATAMENTE (no esperar DOMContentLoaded)
  // porque availabilityController.js dispara eventos durante DOMContentLoaded
  initEventListeners();

  // ==============================
  // 🔹 Exponer en window
  // ==============================
  window.SlotSelectorAdminUI = {
    renderAdminSlots
  };

  console.log('✅ SlotSelectorAdminUI cargado y expuesto globalmente');
})();
