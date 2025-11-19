/**
 * Servicio: Confirmación de Citas
 * 
 * Responsable de:
 * - Llamadas AJAX para confirmar citas
 * - Llamadas AJAX para cancelar citas
 * - Llamadas AJAX para crear clientes desde citas
 * 
 * NO contiene lógica de UI ni callbacks.
 */

window.ConfirmService = (function() {
    'use strict';
    
    /**
     * Confirmar una cita
     * @param {number} id - ID de la cita
     * @returns {Promise<Object>} Respuesta del servidor
     */
    function confirmar(id) {
        const formData = new FormData();
        formData.append('action', 'aa_confirmar_cita');
        formData.append('id', id);
        formData.append('_wpnonce', aa_asistant_vars.nonce_confirmar);
        
        return fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json());
    }
    
    /**
     * Cancelar una cita
     * @param {number} id - ID de la cita
     * @returns {Promise<Object>} Respuesta del servidor
     */
    function cancelar(id) {
        const formData = new FormData();
        formData.append('action', 'aa_cancelar_cita');
        formData.append('id', id);
        formData.append('_wpnonce', aa_asistant_vars.nonce_cancelar);
        
        return fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json());
    }
    
    /**
     * Crear cliente desde una cita
     * @param {number} reservaId - ID de la reserva
     * @param {string} nombre - Nombre del cliente
     * @param {string} telefono - Teléfono del cliente
     * @param {string} correo - Correo del cliente
     * @returns {Promise<Object>} Respuesta del servidor
     */
    function crearClienteDesdeCita(reservaId, nombre, telefono, correo) {
        const formData = new FormData();
        formData.append('action', 'aa_crear_cliente_desde_cita');
        formData.append('reserva_id', reservaId);
        formData.append('nombre', nombre);
        formData.append('telefono', telefono);
        formData.append('correo', correo);
        formData.append('_wpnonce', aa_asistant_vars.nonce_crear_cliente_desde_cita);
        
        return fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json());
    }
    
    // ===============================
    // 🔹 API Pública
    // ===============================
    return {
        confirmar,
        cancelar,
        crearClienteDesdeCita
    };
})();
