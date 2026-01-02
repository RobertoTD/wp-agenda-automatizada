/**
 * Busy Ranges Assignments Service
 * 
 * Provides methods to get busy ranges from assignments.
 * This is a PARALLEL service - does NOT replace legacy BusyRanges.
 * 
 * Exposes:
 * - window.AABusyRangesAssignments
 * 
 * Methods:
 * - getBusyRangesByAssignments(assignmentIds, date)
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

(function() {
    'use strict';

    console.log('🔄 [AABusyRangesAssignments] Cargando módulo...');

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
            const value = data[key];
            // Handle arrays
            if (Array.isArray(value)) {
                formData.append(key, JSON.stringify(value));
            } else {
                formData.append(key, value);
            }
        });

        console.log(`📤 [AABusyRangesAssignments] Enviando request: ${action}`, data);

        try {
            const response = await fetch(getAjaxUrl(), {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            console.log(`📥 [AABusyRangesAssignments] Respuesta de ${action}:`, result);

            return result;
        } catch (error) {
            console.error(`❌ [AABusyRangesAssignments] Error en ${action}:`, error);
            throw error;
        }
    }

    /**
     * Get busy ranges by assignment IDs
     * Returns busy time ranges for specific assignments on a date
     * 
     * @param {Array<number>} assignmentIds - Array of assignment IDs
     * @param {string} date - Date (YYYY-MM-DD)
     * @returns {Promise<Object>} Response with busy_ranges array
     */
    async function getBusyRangesByAssignments(assignmentIds, date) {
        console.log(`🔍 [AABusyRangesAssignments] getBusyRangesByAssignments([${assignmentIds.join(', ')}], "${date}") llamado`);
        
        if (!Array.isArray(assignmentIds) || assignmentIds.length === 0) {
            console.warn('⚠️ [AABusyRangesAssignments] assignmentIds debe ser un array no vacío');
            return {
                success: false,
                data: { message: 'assignmentIds debe ser un array no vacío' }
            };
        }

        const result = await ajaxRequest('aa_get_busy_ranges_by_assignments', {
            assignment_ids: assignmentIds,
            date: date
        });
        
        if (result.success) {
            console.log(`✅ [AABusyRangesAssignments] ${result.data.busy_ranges?.length || 0} rangos ocupados encontrados`);
        }
        
        return result;
    }

    /**
     * Get busy ranges by assignment IDs as intervals in minutes
     * Calls the AJAX endpoint and converts time strings to minute intervals
     * 
     * @param {Array<number>} assignmentIds - Array of assignment IDs
     * @param {string} date - Date (YYYY-MM-DD)
     * @returns {Promise<Array<{start: number, end: number}>|null>} Array of busy intervals in minutes, or null on error
     */
    async function getBusyRangesByAssignmentIds(assignmentIds, date) {
        console.log(`🔍 [AABusyRangesAssignments] getBusyRangesByAssignmentIds([${assignmentIds.join(', ')}], "${date}") llamado`);
        
        if (!Array.isArray(assignmentIds) || assignmentIds.length === 0) {
            console.warn('[AABusyRangesAssignments] assignmentIds debe ser un array no vacío');
            return [];
        }

        // Verificar que DateUtils esté disponible
        if (typeof window.DateUtils === 'undefined' || typeof window.DateUtils.timeStrToMinutes === 'undefined') {
            console.error('[AABusyRangesAssignments] DateUtils.timeStrToMinutes no disponible');
            return null;
        }

        try {
            const result = await ajaxRequest('aa_get_busy_ranges_by_assignments', {
                assignment_ids: assignmentIds,
                date: date
            });

            if (!result.success || !result.data || !result.data.busy_ranges) {
                console.warn('[AABusyRangesAssignments] No se obtuvieron busy ranges');
                return [];
            }

            const rawRanges = result.data.busy_ranges;
            console.log('[AABusyRangesAssignments] Busy ranges (raw):', rawRanges);

            // Convertir a intervalos en minutos usando DateUtils
            const intervalsInMinutes = rawRanges.map(function(range) {
                return {
                    start: window.DateUtils.timeStrToMinutes(range.start),
                    end: window.DateUtils.timeStrToMinutes(range.end)
                };
            });

            console.log('[AABusyRangesAssignments] Busy ranges (minutos):', intervalsInMinutes);

            return intervalsInMinutes;

        } catch (error) {
            console.error('[AABusyRangesAssignments] Error al obtener busy ranges:', error);
            return null;
        }
    }

    // ============================================
    // Expose to global namespace
    // ============================================
    window.AABusyRangesAssignments = {
        getBusyRangesByAssignments: getBusyRangesByAssignments,
        getBusyRangesByAssignmentIds: getBusyRangesByAssignmentIds
    };

    console.log('✅ [AABusyRangesAssignments] Módulo cargado y expuesto en window.AABusyRangesAssignments');
    console.log('📋 Métodos disponibles:');
    console.log('   - AABusyRangesAssignments.getBusyRangesByAssignments(assignmentIds, date)');
    console.log('   - AABusyRangesAssignments.getBusyRangesByAssignmentIds(assignmentIds, date)');

})();

