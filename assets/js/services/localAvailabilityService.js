/**
 * Servicio: Disponibilidad Local
 * 
 * Responsable de:
 * - Refrescar disponibilidad local desde BD vía AJAX
 * - Actualizar window.aa_local_availability
 * - Disparar eventos para recalcular slots si es necesario
 * 
 * @package WP_Agenda_Automatizada
 */

window.LocalAvailabilityService = (function() {
    'use strict';
    
    /**
     * Refrescar disponibilidad local desde BD
     * @param {Date} [selectedDate] - Fecha opcional para disparar recálculo de slots
     * @returns {Promise<Object>} Datos de disponibilidad actualizados
     */
    async function refresh(selectedDate) {
        try {
            // Usar window.ajaxurl (WordPress lo define en admin) o URL directa como fallback
            const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
            const formData = new FormData();
            formData.append('action', 'aa_get_local_availability');
            
            const response = await fetch(ajaxurl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success && result.data) {
                // Actualizar window.aa_local_availability con datos frescos desde BD
                window.aa_local_availability = result.data;
                console.log('[LocalAvailabilityService] ✅ Disponibilidad local actualizada');
                
                // Si se proporciona selectedDate, disparar evento para recalcular slots
                if (selectedDate && selectedDate instanceof Date && !isNaN(selectedDate.getTime())) {
                    document.dispatchEvent(new CustomEvent('aa:admin:date-selected', {
                        detail: { selectedDate }
                    }));
                    console.log('[LocalAvailabilityService] 📅 Evento aa:admin:date-selected disparado');
                }
                
                return result.data;
            } else {
                console.warn('[LocalAvailabilityService] ⚠️ No se pudo refrescar disponibilidad local:', result);
                return null;
            }
        } catch (err) {
            console.warn('[LocalAvailabilityService] ⚠️ Error al refrescar disponibilidad local:', err);
            return null;
        }
    }
    
    // ===============================
    // 🔹 API Pública
    // ===============================
    return {
        refresh
    };
})();

console.log('✅ LocalAvailabilityService cargado');
