/**
 * WPAgenda - API Pública del Plugin
 * 
 * Este archivo define únicamente la estructura pública del namespace WPAgenda.
 * No contiene lógica de UI ni lógica de negocio.
 * 
 * Los adaptadores (calendar, slots, modal) serán registrados externamente
 * por versiones default o premium del plugin.
 */

(function(global) {
    'use strict';

    // =========================================================================
    // Sistema interno de eventos
    // =========================================================================

    /**
     * Tabla interna de listeners para el sistema de eventos.
     * Estructura: { eventName: [callback1, callback2, ...] }
     * @private
     */
    const _eventListeners = {};

    /**
     * Registra un listener para un evento específico.
     * 
     * @param {string} eventName - Nombre del evento a escuchar.
     * @param {Function} callback - Función a ejecutar cuando se emita el evento.
     * @returns {void}
     * 
     * @example
     * WPAgenda.on('dateSelected', function(data) {
     *     console.log('Fecha seleccionada:', data);
     * });
     */
    function on(eventName, callback) {
        if (typeof eventName !== 'string' || !eventName.trim()) {
            console.warn('[WPAgenda] on: eventName debe ser un string válido.');
            return;
        }

        if (typeof callback !== 'function') {
            console.warn('[WPAgenda] on: callback debe ser una función.');
            return;
        }

        if (!_eventListeners[eventName]) {
            _eventListeners[eventName] = [];
        }

        _eventListeners[eventName].push(callback);
    }

    /**
     * Emite un evento, ejecutando todos los listeners registrados.
     * 
     * @param {string} eventName - Nombre del evento a emitir.
     * @param {*} [data] - Datos opcionales a pasar a los listeners.
     * @returns {void}
     * 
     * @example
     * WPAgenda.emit('dateSelected', { date: '2025-12-04' });
     */
    function emit(eventName, data) {
        if (typeof eventName !== 'string' || !eventName.trim()) {
            console.warn('[WPAgenda] emit: eventName debe ser un string válido.');
            return;
        }

        const listeners = _eventListeners[eventName];

        if (!listeners || listeners.length === 0) {
            return;
        }

        listeners.forEach(function(callback) {
            try {
                callback(data);
            } catch (error) {
                console.error('[WPAgenda] Error en listener de "' + eventName + '":', error);
            }
        });
    }

    // =========================================================================
    // Contenedor de adaptadores UI
    // =========================================================================

    /**
     * Objeto que contiene los adaptadores de UI registrados.
     * Los valores serán asignados mediante los métodos register*.
     * 
     * @property {Object|null} calendar - Adaptador para el calendario.
     * @property {Object|null} slots - Adaptador para los slots de horarios.
     * @property {Object|null} modal - Adaptador para el modal de confirmación.
     */
    const ui = {
        calendar: null,
        slots: null,
        modal: null
    };

    // =========================================================================
    // Métodos de registro de adaptadores
    // =========================================================================

    /**
     * Valida que un adaptador tenga la estructura mínima esperada.
     * 
     * @private
     * @param {*} adapter - Adaptador a validar.
     * @param {string} adapterName - Nombre del adaptador (para mensajes de error).
     * @returns {boolean} - True si el adaptador es válido.
     */
    function _validateAdapter(adapter, adapterName) {
        if (!adapter || typeof adapter !== 'object') {
            console.error('[WPAgenda] ' + adapterName + ': el adaptador debe ser un objeto válido.');
            return false;
        }

        return true;
    }

    /**
     * Registra un adaptador para el componente Calendar.
     * 
     * @param {Object} adapter - Objeto adaptador que implementa la interfaz de calendario.
     * @returns {boolean} - True si el registro fue exitoso.
     * 
     * @example
     * WPAgenda.registerCalendarAdapter({
     *     render: function(container, options) { ... },
     *     destroy: function() { ... }
     * });
     */
    function registerCalendarAdapter(adapter) {
        if (!_validateAdapter(adapter, 'registerCalendarAdapter')) {
            return false;
        }

        ui.calendar = adapter;
        return true;
    }

    /**
     * Registra un adaptador para el componente Slots (horarios disponibles).
     * 
     * @param {Object} adapter - Objeto adaptador que implementa la interfaz de slots.
     * @returns {boolean} - True si el registro fue exitoso.
     * 
     * @example
     * WPAgenda.registerSlotsAdapter({
     *     render: function(container, slots) { ... },
     *     destroy: function() { ... }
     * });
     */
    function registerSlotsAdapter(adapter) {
        if (!_validateAdapter(adapter, 'registerSlotsAdapter')) {
            return false;
        }

        ui.slots = adapter;
        return true;
    }

    /**
     * Registra un adaptador para el componente Modal.
     * 
     * @param {Object} adapter - Objeto adaptador que implementa la interfaz de modal.
     * @returns {boolean} - True si el registro fue exitoso.
     * 
     * @example
     * WPAgenda.registerModalAdapter({
     *     open: function(options) { ... },
     *     close: function() { ... }
     * });
     */
    function registerModalAdapter(adapter) {
        if (!_validateAdapter(adapter, 'registerModalAdapter')) {
            return false;
        }

        ui.modal = adapter;
        return true;
    }

    // =========================================================================
    // Namespace global WPAgenda
    // =========================================================================

    /**
     * Namespace global del plugin WPAgenda.
     * 
     * @namespace WPAgenda
     * @property {Object} ui - Contenedor de adaptadores de UI (calendar, slots, modal).
     * @property {Function} registerCalendarAdapter - Registra un adaptador de calendario.
     * @property {Function} registerSlotsAdapter - Registra un adaptador de slots.
     * @property {Function} registerModalAdapter - Registra un adaptador de modal.
     * @property {Function} on - Registra un listener para un evento.
     * @property {Function} emit - Emite un evento con datos opcionales.
     */
    global.WPAgenda = {
        ui: ui,
        registerCalendarAdapter: registerCalendarAdapter,
        registerSlotsAdapter: registerSlotsAdapter,
        registerModalAdapter: registerModalAdapter,
        on: on,
        emit: emit
    };

})(window);
