/**
 * Calendar Module - Module-specific JavaScript
 */

(function() {
    'use strict';

    // Variables del módulo para recarga
    let currentSlotRowIndex = null;
    let currentTimeSlots = null;
    let eventListenersConfigured = false;
    let gridClickHandler = null;

    // Función para esperar a que las dependencias estén disponibles
    function waitForDependencies(callback, maxAttempts = 50) {
        const hasDateUtils = typeof window.DateUtils !== 'undefined' && 
                            typeof window.DateUtils.getWeekdayName === 'function' &&
                            typeof window.DateUtils.getDayIntervals === 'function';
        
        const hasSchedule = typeof window.AA_CALENDAR_DATA !== 'undefined' && 
                           window.AA_CALENDAR_DATA?.schedule;
        
        const hasCalendarService = typeof window.AdminCalendarService !== 'undefined' &&
                                  typeof window.AdminCalendarService.esCitaProxima === 'function' &&
                                  typeof window.AdminCalendarService.calcularPosicionCita === 'function';
        
        const hasCalendarController = typeof window.AdminCalendarController !== 'undefined' &&
                                     typeof window.AdminCalendarController.handleCitaAction === 'function';
        
        const hasDatePickerAdapter = typeof window.DatePickerAdapter !== 'undefined' &&
                                    typeof window.DatePickerAdapter.init === 'function';
        
        const hasFlatpickr = typeof flatpickr !== 'undefined';
        
        const hasCalendarAppointmentCard = typeof window.CalendarAppointmentCard !== 'undefined' &&
                                          typeof window.CalendarAppointmentCard.crearCardCita === 'function';
        
        const hasCalendarAppointments = typeof window.CalendarAppointments !== 'undefined' &&
                                       typeof window.CalendarAppointments.cargarYRenderizarCitas === 'function';
        
        const hasCalendarTimeline = typeof window.CalendarTimeline !== 'undefined' &&
                                   typeof window.CalendarTimeline.renderTimelineForDate === 'function';
        
        if (hasDateUtils && hasSchedule && hasCalendarService && hasCalendarController && hasDatePickerAdapter && hasFlatpickr && hasCalendarAppointmentCard && hasCalendarAppointments && hasCalendarTimeline) {
            callback();
            return;
        }
        
        if (maxAttempts <= 0) {
            console.error('❌ Dependencias no disponibles después de múltiples intentos');
            console.error('  - DateUtils:', typeof window.DateUtils);
            console.error('  - AA_CALENDAR_DATA:', typeof window.AA_CALENDAR_DATA);
            console.error('  - AdminCalendarService:', typeof window.AdminCalendarService);
            console.error('  - AdminCalendarController:', typeof window.AdminCalendarController);
            console.error('  - DatePickerAdapter:', typeof window.DatePickerAdapter);
            console.error('  - flatpickr:', typeof flatpickr);
            console.error('  - CalendarAppointmentCard:', typeof window.CalendarAppointmentCard);
            console.error('  - CalendarAppointments:', typeof window.CalendarAppointments);
            console.error('  - CalendarTimeline:', typeof window.CalendarTimeline);
            return;
        }
        
        setTimeout(() => waitForDependencies(callback, maxAttempts - 1), 100);
    }

    /**
     * Inicializar el selector de fecha
     */
    function initDatePicker() {
        // Obtener fecha inicial (hoy)
        const today = new Date();
        const todayStr = window.DateUtils.ymd(today);
        
        // Callback cuando cambia la fecha
        function onDateChange(fecha) {
            if (window.AdminCalendarController?.setDate) {
                window.AdminCalendarController.setDate(fecha);
            }
        }
        
        // Inicializar adapter
        if (window.DatePickerAdapter) {
            window.DatePickerAdapter.init('aa-date-picker', onDateChange, todayStr);
        }
        
        // Configurar botones de navegación
        const btnPrev = document.getElementById('aa-date-prev');
        const btnNext = document.getElementById('aa-date-next');
        
        if (btnPrev) {
            btnPrev.addEventListener('click', function() {
                if (window.DatePickerAdapter) {
                    window.DatePickerAdapter.prevDay();
                }
            });
        }
        
        if (btnNext) {
            btnNext.addEventListener('click', function() {
                if (window.DatePickerAdapter) {
                    window.DatePickerAdapter.nextDay();
                }
            });
        }
    }

    /**
     * Obtener intervalos de asignaciones para una fecha específica
     * @param {string} fechaStr - Fecha en formato YYYY-MM-DD
     * @returns {Promise<Array<{start: number, end: number}>>} - Intervalos normalizados en minutos
     */
    function fetchAssignmentIntervals(fechaStr) {
        return new Promise(function(resolve) {
            const ajaxurl = (window.AA_CALENDAR_DATA && window.AA_CALENDAR_DATA.ajaxurl) 
                || window.ajaxurl 
                || '/wp-admin/admin-ajax.php';
            
            const formData = new FormData();
            formData.append('action', 'aa_get_assignments');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (!data.success || !data.data || !data.data.assignments) {
                    resolve([]);
                    return;
                }
                
                const assignments = data.data.assignments;
                const intervals = [];
                
                // Filtrar asignaciones del día seleccionado
                assignments.forEach(function(assignment) {
                    if (assignment.assignment_date !== fechaStr) {
                        return;
                    }
                    
                    // Convertir start_time y end_time a minutos
                    const startMin = window.DateUtils.timeStrToMinutes(assignment.start_time);
                    const endMin = window.DateUtils.timeStrToMinutes(assignment.end_time);
                    
                    // Normalizar el intervalo usando DateUtils
                    // normalizeIntervalToSlotGrid devuelve {start, end} donde end es el último slot válido
                    const normalized = window.DateUtils.normalizeIntervalToSlotGrid(startMin, endMin, 30);
                    
                    // Ignorar los null (intervalos inválidos)
                    if (normalized) {
                        // Convertir end de "último slot" a "límite superior" sumando 30
                        // Esto es necesario porque el timeline genera slots con: min < end
                        intervals.push({
                            start: normalized.start,
                            end: normalized.end + 30
                        });
                    }
                });
                
                resolve(intervals);
            })
            .catch(function(error) {
                console.error('[Calendar Module] Error al obtener asignaciones:', error);
                resolve([]);
            });
        });
    }

    /**
     * Renderizar timeline para una fecha específica
     * @param {string} fechaStr - Fecha en formato YYYY-MM-DD
     */
    function renderTimelineForDate(fechaStr) {
        // Primero obtener intervalos de asignaciones, luego renderizar
        fetchAssignmentIntervals(fechaStr).then(function(assignmentIntervals) {
            // Preparar options con los intervalos de asignaciones
            const options = {
                assignmentIntervals: assignmentIntervals
            };
            
            // Delegar render del timeline a CalendarTimeline
            const result = window.CalendarTimeline?.renderTimelineForDate(fechaStr, options);
            
            if (!result) {
                // Si no hay resultado (día sin horarios ni asignaciones), no hay nada que hacer
                return;
            }
            
            // Guardar referencias para recarga
            currentSlotRowIndex = result.slotRowIndex;
            currentTimeSlots = result.timeSlots;
            
            // Cargar y renderizar citas del día seleccionado usando CalendarAppointments
            if (window.CalendarAppointments?.cargarYRenderizarCitas) {
                window.CalendarAppointments.cargarYRenderizarCitas(result.slotRowIndex, result.timeSlots, fechaStr);
            }
            
            // Configurar event listener delegado para acciones de botones
            configurarEventListeners();
        });
    }

    function initCalendar() {
        // Inicializar selector de fecha
        initDatePicker();
        
        // Obtener fecha inicial (hoy o la del controller si existe)
        let fechaInicial;
        if (window.AdminCalendarController?.getCurrentDate) {
            fechaInicial = window.AdminCalendarController.getCurrentDate();
        }
        
        if (!fechaInicial) {
            const today = new Date();
            fechaInicial = window.DateUtils.ymd(today);
        }
        
        // Renderizar timeline para la fecha inicial
        renderTimelineForDate(fechaInicial);
        
        // Inicializar el controller con callbacks
        if (window.AdminCalendarController?.init) {
            window.AdminCalendarController.init(
                recargarTimelineDelDiaActual,
                function(fecha) {
                    // Callback para cargar citas de un día específico
                    renderTimelineForDate(fecha);
                }
            );
        }
        
        // Inicializar AdminConfirmController con el callback de recarga del timeline
        // Esto permite que confirmar/cancelar actualicen automáticamente el timeline
        if (window.AdminConfirmController?.init) {
            window.AdminConfirmController.init(recargarTimelineDelDiaActual);
        }
    }


    /**
     * Configurar event listeners delegados para acciones de botones
     * Protegido contra múltiples registros
     */
    function configurarEventListeners() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Si ya están configurados, remover el listener anterior antes de agregar uno nuevo
        if (eventListenersConfigured && gridClickHandler) {
            grid.removeEventListener('click', gridClickHandler);
        }
        
        // Crear handler y guardar referencia
        gridClickHandler = function(e) {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            
            const action = btn.getAttribute('data-action');
            const citaId = btn.getAttribute('data-id');
            
            if (!action || !citaId) return;
            
            e.stopPropagation();
            
            // Delegar al controller
            if (window.AdminCalendarController?.handleCitaAction) {
                window.AdminCalendarController.handleCitaAction(action, citaId);
            }
        };
        
        // Registrar el listener
        grid.addEventListener('click', gridClickHandler);
        eventListenersConfigured = true;
    }

    /**
     * Recargar el timeline del día actual sin recargar la página
     */
    function recargarTimelineDelDiaActual() {
        // Obtener fecha actual del controller
        let fecha;
        if (window.AdminCalendarController?.getCurrentDate) {
            fecha = window.AdminCalendarController.getCurrentDate();
        }
        
        if (!fecha) {
            const today = new Date();
            fecha = window.DateUtils.ymd(today);
        }
        
        // Re-renderizar timeline completo para la fecha actual
        renderTimelineForDate(fecha);
    }

    // Esperar a que el DOM esté listo Y las dependencias estén disponibles
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            waitForDependencies(initCalendar);
        });
    } else {
        waitForDependencies(initCalendar);
    }

})();