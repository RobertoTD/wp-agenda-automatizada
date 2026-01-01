/**
 * Availability Assignments Service
 * 
 * Provides methods to query assignment-based availability.
 * This is a PARALLEL service - does NOT replace legacy AvailabilityService.
 * 
 * Exposes:
 * - window.AAAssignmentsAvailability
 * 
 * Methods:
 * - getAssignmentDates()
 * - getAssignmentDatesByService(serviceKey, startDate, endDate)
 * - getAssignmentsByServiceAndDate(serviceKey, date)
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

(function() {
    'use strict';

    console.log('🔄 [AAAssignmentsAvailability] Cargando módulo...');

    /**
     * Get AJAX URL
     * @returns {string}
     */
    function getAjaxUrl() {
        return window.ajaxurl || '/wp-admin/admin-ajax.php';
    }

    /**
     * Make AJAX request
     * @param {string} action - AJAX action name
     * @param {Object} data - Additional data to send
     * @returns {Promise<Object>}
     */
    async function ajaxRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        console.log(`📤 [AAAssignmentsAvailability] Enviando request: ${action}`, data);

        try {
            const response = await fetch(getAjaxUrl(), {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            console.log(`📥 [AAAssignmentsAvailability] Respuesta de ${action}:`, result);

            return result;
        } catch (error) {
            console.error(`❌ [AAAssignmentsAvailability] Error en ${action}:`, error);
            throw error;
        }
    }

    /**
     * Get all assignment dates
     * Returns all unique dates that have active assignments
     * 
     * @returns {Promise<Object>} Response with dates array
     */
    async function getAssignmentDates() {
        console.log('🔍 [AAAssignmentsAvailability] getAssignmentDates() llamado');
        
        const result = await ajaxRequest('aa_get_assignment_dates');
        
        if (result.success) {
            console.log(`✅ [AAAssignmentsAvailability] ${result.data.dates?.length || 0} fechas encontradas`);
        }
        
        return result;
    }

    /**
     * Get assignment dates by service
     * Returns dates that have assignments for a specific service
     * 
     * @param {string} serviceKey - Service identifier (e.g., 'consulta_general')
     * @param {string} startDate - Start date (YYYY-MM-DD), optional
     * @param {string} endDate - End date (YYYY-MM-DD), optional
     * @returns {Promise<Object>} Response with dates array
     */
    async function getAssignmentDatesByService(serviceKey, startDate = null, endDate = null) {
        console.log(`🔍 [AAAssignmentsAvailability] getAssignmentDatesByService("${serviceKey}", "${startDate}", "${endDate}") llamado`);
        
        const data = { service_key: serviceKey };
        
        if (startDate) {
            data.start_date = startDate;
        }
        if (endDate) {
            data.end_date = endDate;
        }
        
        const result = await ajaxRequest('aa_get_assignment_dates_by_service', data);
        
        if (result.success) {
            console.log(`✅ [AAAssignmentsAvailability] ${result.data.dates?.length || 0} fechas encontradas para servicio "${serviceKey}"`);
        }
        
        return result;
    }

    /**
     * Get assignments by service and date
     * Returns all assignments for a specific service on a specific date
     * 
     * @param {string} serviceKey - Service identifier
     * @param {string} date - Date (YYYY-MM-DD)
     * @returns {Promise<Object>} Response with assignments array
     */
    async function getAssignmentsByServiceAndDate(serviceKey, date) {
        console.log(`🔍 [AAAssignmentsAvailability] getAssignmentsByServiceAndDate("${serviceKey}", "${date}") llamado`);
        
        const result = await ajaxRequest('aa_get_assignments_by_service_and_date', {
            service_key: serviceKey,
            date: date
        });
        
        if (result.success) {
            console.log(`✅ [AAAssignmentsAvailability] ${result.data.assignments?.length || 0} asignaciones encontradas`);
        }
        
        return result;
    }

    // ============================================
    // Expose to global namespace
    // ============================================
    window.AAAssignmentsAvailability = {
        getAssignmentDates: getAssignmentDates,
        getAssignmentDatesByService: getAssignmentDatesByService,
        getAssignmentsByServiceAndDate: getAssignmentsByServiceAndDate
    };

    console.log('✅ [AAAssignmentsAvailability] Módulo cargado y expuesto en window.AAAssignmentsAvailability');
    console.log('📋 Métodos disponibles:');
    console.log('   - AAAssignmentsAvailability.getAssignmentDates()');
    console.log('   - AAAssignmentsAvailability.getAssignmentDatesByService(serviceKey, startDate?, endDate?)');
    console.log('   - AAAssignmentsAvailability.getAssignmentsByServiceAndDate(serviceKey, date)');

})();

