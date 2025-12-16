/**
 * DatePicker Adapter - Flatpickr wrapper
 * 
 * Responsable de:
 * - Inicializar y configurar Flatpickr
 * - Emitir fechas en formato YYYY-MM-DD
 * - Métodos de navegación (nextDay, prevDay)
 * - Callback onDateChange para notificar cambios
 * 
 * NO contiene lógica de negocio ni llamadas AJAX.
 */

window.DatePickerAdapter = (function() {
    'use strict';
    
    let flatpickrInstance = null;
    let onDateChangeCallback = null;
    
    /**
     * Inicializar el date picker
     * @param {string} inputId - ID del input HTML
     * @param {Function} onDateChange - Callback cuando cambia la fecha (recibe YYYY-MM-DD)
     * @param {string} initialDate - Fecha inicial en formato YYYY-MM-DD (opcional)
     */
    function init(inputId, onDateChange, initialDate) {
        const input = document.getElementById(inputId);
        if (!input) {
            console.error('❌ DatePickerAdapter: No se encontró el input con ID:', inputId);
            return;
        }
        
        // Verificar que Flatpickr esté disponible
        if (typeof flatpickr === 'undefined') {
            console.error('❌ DatePickerAdapter: Flatpickr no está disponible');
            return;
        }
        
        onDateChangeCallback = onDateChange;
        
        // Configurar Flatpickr
        const options = {
            dateFormat: 'Y-m-d',
            locale: 'es',
            allowInput: false,
            clickOpens: true,
            onChange: function(selectedDates, dateStr) {
                if (onDateChangeCallback && dateStr) {
                    onDateChangeCallback(dateStr);
                }
            }
        };
        
        flatpickrInstance = flatpickr(input, options);
        
        // Establecer fecha inicial si se proporciona
        if (initialDate) {
            setDate(initialDate);
        }
        
        console.log('✅ DatePickerAdapter inicializado');
    }
    
    /**
     * Establecer fecha programáticamente
     * @param {string} date - Fecha en formato YYYY-MM-DD
     */
    function setDate(date) {
        if (!flatpickrInstance) {
            console.error('❌ DatePickerAdapter: No inicializado');
            return;
        }
        
        if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            console.error('❌ DatePickerAdapter: Formato de fecha inválido:', date);
            return;
        }
        
        flatpickrInstance.setDate(date, false); // false = no disparar onChange
    }
    
    /**
     * Obtener fecha actual seleccionada
     * @returns {string|null} - Fecha en formato YYYY-MM-DD o null
     */
    function getDate() {
        if (!flatpickrInstance) {
            return null;
        }
        
        const selectedDates = flatpickrInstance.selectedDates;
        if (selectedDates.length === 0) {
            return null;
        }
        
        const date = selectedDates[0];
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    /**
     * Avanzar un día
     */
    function nextDay() {
        const currentDate = getDate();
        if (!currentDate) return;
        
        const date = new Date(currentDate + 'T00:00:00');
        date.setDate(date.getDate() + 1);
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const nextDate = `${year}-${month}-${day}`;
        
        setDate(nextDate);
        // Disparar onChange manualmente
        if (onDateChangeCallback) {
            onDateChangeCallback(nextDate);
        }
    }
    
    /**
     * Retroceder un día
     */
    function prevDay() {
        const currentDate = getDate();
        if (!currentDate) return;
        
        const date = new Date(currentDate + 'T00:00:00');
        date.setDate(date.getDate() - 1);
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const prevDate = `${year}-${month}-${day}`;
        
        setDate(prevDate);
        // Disparar onChange manualmente
        if (onDateChangeCallback) {
            onDateChangeCallback(prevDate);
        }
    }
    
    // API pública
    return {
        init: init,
        setDate: setDate,
        getDate: getDate,
        nextDay: nextDay,
        prevDay: prevDay
    };
})();

