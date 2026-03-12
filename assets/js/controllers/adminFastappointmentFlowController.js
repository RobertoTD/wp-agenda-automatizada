/**
 * Admin Fast Appointment Flow Controller
 *
 * Conecta el bloque Cliente del modal Fast Appointment sin acoplarlo al modal de reservation.
 * Reutiliza ReservationClientController con IDs propios y sincroniza el cliente seleccionado
 * con el estado del flujo para que después pueda avanzar a Fecha.
 *
 * GUARDRAIL: This controller consumes FastAppointmentTimeAvailabilityService
 * (confirmed-reservation occupancy, 30-min base slots).  It must NOT import
 * or call availabilityAssignments.js logic.
 * See docs/fast-appointment-vs-assignment-availability.md
 *
 * @package AgendaAutomatizada
 * @since 2.0.0
 */
(function() {
    'use strict';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function createController(opts) {
        const config = opts || {};
        const getState = typeof config.getState === 'function'
            ? config.getState
            : function() { return {}; };
        const setState = typeof config.setState === 'function'
            ? config.setState
            : null;
        const selectors = config.selectors || {};

        const stepClientSelector = selectors.stepClientSelector || '#aa-fastappointment-step-client';
        const searchInputId = selectors.searchInputId || 'aa-fastappointment-client-search';
        const createButtonId = selectors.createButtonId || 'aa-fastappointment-client-create';
        const inlineContainerId = selectors.inlineContainerId || 'aa-fastappointment-client-inline';
        const clientSelectId = selectors.clientSelectId || 'aa-fastappointment-client';
        const stepDateSelector = selectors.stepDateSelector || '#aa-fastappointment-step-date';
        const dateInputId = selectors.dateInputId || 'aa-fastappointment-date';
        const stepTimeSelector = selectors.stepTimeSelector || '#aa-fastappointment-step-time';
        const timeSelectId = selectors.timeSelectId || 'aa-fastappointment-time';
        const summarySelector = selectors.summarySelector || '#aa-fastappointment-summary';

        const stepClient = document.querySelector(stepClientSelector);
        const clientSelect = document.getElementById(clientSelectId);
        const stepTime = document.querySelector(stepTimeSelector);
        const timeSelect = document.getElementById(timeSelectId);
        const summaryBox = document.querySelector(summarySelector);

        if (!stepClient || !clientSelect) {
            console.warn('[AdminFastappointmentFlowController] Bloque Cliente no disponible');
            return null;
        }

        let clientController = null;
        let clientSelectObserver = null;
        let handleClientChange = null;
        let handleTimeChange = null;
        let datePicker = null;
        let isDestroyed = false;
        let timeAvailabilityRequestId = 0;

        function updateState(patch) {
            const currentState = getState() || {};

            if (setState) {
                setState(Object.assign({}, currentState, patch));
                return;
            }

            Object.assign(currentState, patch);
        }

        function getOrCreatePrerequisitesNotice() {
            if (!stepTime) {
                return null;
            }

            let notice = stepTime.querySelector('[data-aa-fastappointment-prerequisites]');

            if (notice) {
                return notice;
            }

            notice = document.createElement('div');
            notice.setAttribute('data-aa-fastappointment-prerequisites', '1');
            notice.className = 'hidden rounded-lg border px-3 py-3 text-sm';

            if (timeSelect && timeSelect.parentNode === stepTime) {
                stepTime.insertBefore(notice, timeSelect);
            } else {
                stepTime.appendChild(notice);
            }

            return notice;
        }

        function renderPrerequisitesNotice(messages, tone) {
            const notice = getOrCreatePrerequisitesNotice();

            if (!notice) {
                return;
            }

            const list = Array.isArray(messages) ? messages.filter(Boolean) : [];

            if (!list.length) {
                notice.className = 'hidden rounded-lg border px-3 py-3 text-sm';
                notice.innerHTML = '';
                return;
            }

            const toneMap = {
                info: 'border-blue-200 bg-blue-50 text-blue-800',
                error: 'border-amber-200 bg-amber-50 text-amber-900',
                success: 'border-emerald-200 bg-emerald-50 text-emerald-800'
            };

            notice.className = 'rounded-lg border px-3 py-3 text-sm ' + (toneMap[tone] || toneMap.info);
            notice.innerHTML = list.map(function(message) {
                return '<p>' + escapeHtml(message) + '</p>';
            }).join('');
        }

        function renderSummaryMessage(text) {
            if (!summaryBox) {
                return;
            }

            if (!text) {
                summaryBox.textContent = 'Aqui se podra mostrar un resumen de la seleccion antes del envio.';
                return;
            }

            summaryBox.textContent = text;
        }

        function setTimeStepBlockedState(blocked) {
            if (timeSelect) {
                const hasRealOptions = timeSelect.options.length > 1;

                timeSelect.disabled = !!blocked || !hasRealOptions;
                timeSelect.classList.toggle('opacity-60', !!blocked);
                timeSelect.classList.toggle('cursor-not-allowed', !!blocked);
            }

            if (stepTime) {
                stepTime.dataset.blocked = blocked ? '1' : '0';
            }
        }

        function applyPrerequisitesResult(result) {
            const normalized = result || {
                hasServices: false,
                hasUsableStaff: false,
                hasAreas: false,
                canStart: false,
                messages: ['No se pudieron validar los prerrequisitos de Fast Appointment.']
            };
            const canStart = !!normalized.canStart;
            const messages = Array.isArray(normalized.messages) ? normalized.messages : [];

            updateState({
                fastAppointmentPrerequisites: normalized,
                canStartFastAppointment: canStart,
                isTimeStepBlocked: !canStart,
                blockingMessages: canStart ? [] : messages.slice()
            });

            setTimeStepBlockedState(!canStart);

            if (canStart) {
                renderPrerequisitesNotice([], 'success');
                renderSummaryMessage('');
                return;
            }

            renderPrerequisitesNotice(messages, 'error');
            renderSummaryMessage(messages.join(' '));
        }

        async function validateFastAppointmentPrerequisites() {
            if (!window.FastAppointmentPrerequisitesService || typeof window.FastAppointmentPrerequisitesService.evaluate !== 'function') {
                const missingServiceResult = {
                    hasServices: false,
                    hasUsableStaff: false,
                    hasAreas: false,
                    canStart: false,
                    messages: ['Fast Appointment no pudo validar prerrequisitos porque el service no esta disponible.']
                };

                applyPrerequisitesResult(missingServiceResult);
                return missingServiceResult;
            }

            setTimeStepBlockedState(true);
            renderPrerequisitesNotice(['Validando prerrequisitos de Fast Appointment...'], 'info');
            renderSummaryMessage('Validando prerrequisitos de Fast Appointment...');

            const result = await window.FastAppointmentPrerequisitesService.evaluate();

            if (isDestroyed) {
                return result;
            }

            applyPrerequisitesResult(result);

            if (result && result.canStart) {
                const currentState = getState() || {};
                const currentDate = currentState.selectedDate || null;

                if (currentDate) {
                    console.log('[FastAppointmentFlow] Prerrequisitos listos, re-trigger disponibilidad para:', currentDate);
                    loadTimeAvailabilityForDate(currentDate);
                }
            }

            return result;
        }

        function getSelectedClientData() {
            const option = clientSelect.options[clientSelect.selectedIndex];

            if (!option || !option.value) {
                return null;
            }

            return {
                id: option.value,
                nombre: option.dataset.nombre || '',
                telefono: option.dataset.telefono || '',
                correo: option.dataset.correo || ''
            };
        }

        function syncSelectedClient() {
            const selectedClient = getSelectedClientData();

            updateState({
                selectedClientId: selectedClient ? selectedClient.id : null,
                selectedClient: selectedClient,
                isClientStepReady: !!selectedClient
            });

            stepClient.dataset.clientReady = selectedClient ? '1' : '0';
        }

        function bindClientSelection() {
            handleClientChange = function() {
                syncSelectedClient();
            };

            clientSelect.addEventListener('change', handleClientChange);
        }

        function observeClientSelectUpdates() {
            clientSelectObserver = new MutationObserver(function() {
                syncSelectedClient();
            });

            clientSelectObserver.observe(clientSelect, {
                childList: true
            });
        }

        function initClientController() {
            if (!window.ReservationClientController || typeof window.ReservationClientController.init !== 'function') {
                console.warn('[AdminFastappointmentFlowController] ReservationClientController no disponible');
                return;
            }

            clientController = window.ReservationClientController.init({
                searchInputId: searchInputId,
                selectId: clientSelectId,
                inlineContainerId: inlineContainerId,
                createButtonId: createButtonId
            });
        }

        function getEligibleServices(prerequisites) {
            if (!prerequisites || !Array.isArray(prerequisites.usableStaff)) {
                return [];
            }

            var activeServiceIds = new Set(
                (prerequisites.activeServices || []).map(function(s) { return String(s.id); })
            );
            var fromStaff = prerequisites.usableStaff.flatMap(function(s) {
                return s.services || [];
            });
            var eligible = fromStaff.filter(function(s) {
                return activeServiceIds.has(String(s.id));
            });

            var byId = new Map();
            eligible.forEach(function(s) {
                byId.set(s.id, s);
            });
            return Array.from(byId.values()).sort(function(a, b) {
                return (a.name || '').localeCompare(b.name || '');
            });
        }

        function populateServiceSelect(eligibleServices) {
            var serviceSelect = document.getElementById('aa-fastappointment-service');
            if (!serviceSelect) {
                return;
            }

            var html = '<option value="">-- Selecciona un servicio --</option>';
            (eligibleServices || []).forEach(function(s) {
                html += '<option value="' + escapeHtml(String(s.id)) + '">' + escapeHtml(s.name || '') + '</option>';
            });

            serviceSelect.innerHTML = html;
        }

        function resetStepsAfterTime() {
            const serviceSelect = document.getElementById('aa-fastappointment-service');
            const staffSelect = document.getElementById('aa-fastappointment-staff');
            const areaSelect = document.getElementById('aa-fastappointment-area');
            const confirmCheckbox = document.getElementById('aa-fastappointment-confirm');

            if (serviceSelect && serviceSelect.options.length > 0) {
                serviceSelect.selectedIndex = 0;
            }
            if (staffSelect && staffSelect.options.length > 0) {
                staffSelect.selectedIndex = 0;
            }
            if (areaSelect && areaSelect.options.length > 0) {
                areaSelect.selectedIndex = 0;
            }
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }

            updateState({
                selectedServiceId: null,
                selectedStaffId: null,
                selectedAreaId: null
            });
        }

        function renderTimeOptions(availabilityResult) {
            if (!timeSelect) {
                return;
            }

            const result = availabilityResult || {};
            const slots = Array.isArray(result.slots) ? result.slots : [];
            const state = getState() || {};
            let html = '<option value="">-- Selecciona una hora --</option>';

            slots.forEach(function(slot) {
                html += '<option value="' + escapeHtml(slot.value) + '">' + escapeHtml(slot.label) + '</option>';
            });

            console.log('[FastAppointmentFlow] renderTimeOptions: ' + slots.length + ' slots ->',
                slots.map(function(s) { return s.value; }));

            timeSelect.innerHTML = html;
            timeSelect.disabled = !slots.length || !!state.isTimeStepBlocked;
        }

        async function loadTimeAvailabilityForDate(dateStr) {
            if (!dateStr) {
                renderTimeOptions({ slots: [] });
                return null;
            }

            if (!window.FastAppointmentTimeAvailabilityService ||
                typeof window.FastAppointmentTimeAvailabilityService.getAvailabilityByDate !== 'function') {
                console.warn('[AdminFastappointmentFlowController] FastAppointmentTimeAvailabilityService no disponible');
                renderTimeOptions({ slots: [] });
                return null;
            }

            timeAvailabilityRequestId++;
            const myRequestId = timeAvailabilityRequestId;

            const state = getState() || {};
            const prerequisites = state.fastAppointmentPrerequisites || null;
            const usableStaff = prerequisites && Array.isArray(prerequisites.usableStaff)
                ? prerequisites.usableStaff
                : [];

            console.log('[FastAppointmentFlow] loadTimeAvailabilityForDate requestId=' + myRequestId +
                ' date=' + dateStr + ' usableStaff.length=' + usableStaff.length);

            const result = await window.FastAppointmentTimeAvailabilityService.getAvailabilityByDate(dateStr, {
                prerequisites: prerequisites,
                usableStaff: usableStaff
            });

            if (isDestroyed) {
                return result;
            }

            if (myRequestId !== timeAvailabilityRequestId) {
                console.warn('[FastAppointmentFlow] Descartando resultado obsoleto requestId=' + myRequestId +
                    ' (actual=' + timeAvailabilityRequestId + ')');
                return result;
            }

            console.log('[FastAppointmentFlow] Availability result requestId=' + myRequestId + ':',
                result && result.slots ? result.slots.map(function(s) { return s.value; }) : []);

            renderTimeOptions(result);

            return result;
        }

        function bindTimeSelection() {
            if (!timeSelect) {
                return;
            }

            handleTimeChange = function() {
                const selectedTime = this.value || null;

                updateState({
                    selectedTime: selectedTime
                });

                resetStepsAfterTime();

                var state = getState() || {};
                var prerequisites = state.fastAppointmentPrerequisites || null;
                var eligibleServices = getEligibleServices(prerequisites);
                populateServiceSelect(eligibleServices);
            };

            timeSelect.addEventListener('change', handleTimeChange);
        }

        /**
         * Reset pasos posteriores a Fecha: hora, servicio, staff, zona, confirmar.
         * Limpia UI y estado para que se vuelvan a elegir tras cambiar la fecha.
         */
        function resetStepsAfterDate() {
            const timeSelect = document.getElementById('aa-fastappointment-time');
            const serviceSelect = document.getElementById('aa-fastappointment-service');
            const staffSelect = document.getElementById('aa-fastappointment-staff');
            const areaSelect = document.getElementById('aa-fastappointment-area');
            const confirmCheckbox = document.getElementById('aa-fastappointment-confirm');

            if (timeSelect && timeSelect.options.length > 0) {
                timeSelect.selectedIndex = 0;
            }
            if (serviceSelect && serviceSelect.options.length > 0) {
                serviceSelect.selectedIndex = 0;
            }
            if (staffSelect && staffSelect.options.length > 0) {
                staffSelect.selectedIndex = 0;
            }
            if (areaSelect && areaSelect.options.length > 0) {
                areaSelect.selectedIndex = 0;
            }
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }

            updateState({
                selectedTime: null,
                selectedServiceId: null,
                selectedStaffId: null,
                selectedAreaId: null
            });

            const state = getState() || {};
            setTimeStepBlockedState(!state.canStartFastAppointment);

            if (timeSelect) {
                timeSelect.innerHTML = '<option value="">-- Selecciona una hora --</option>';
            }
        }

        /**
         * Inicializar date picker en #aa-fastappointment-date.
         * Mismo patrón que assignment-modal: Flatpickr con minDate 'today', fallback a input type="date".
         */
        function initDatePicker() {
            const dateInput = document.getElementById(dateInputId);
            const stepDate = document.querySelector(stepDateSelector);

            if (!dateInput) {
                console.warn('[AdminFastappointmentFlowController] Date input no encontrado');
                return;
            }

            if (typeof flatpickr === 'undefined') {
                dateInput.type = 'date';
                var todayStr = new Date().toISOString().split('T')[0];
                dateInput.setAttribute('min', todayStr);
                dateInput.value = todayStr;
                if (stepDate) {
                    stepDate.dataset.dateReady = '1';
                }
                updateState({
                    selectedDate: todayStr,
                    isDateStepReady: true
                });
                resetStepsAfterDate();
                loadTimeAvailabilityForDate(todayStr);
                dateInput.addEventListener('change', function() {
                    var selectedValue = dateInput.value || '';

                    if (!selectedValue) {
                        return;
                    }

                    updateState({
                        selectedDate: selectedValue,
                        isDateStepReady: true
                    });

                    if (stepDate) {
                        stepDate.dataset.dateReady = '1';
                    }

                    resetStepsAfterDate();
                    loadTimeAvailabilityForDate(selectedValue);
                });
                return;
            }

            if (datePicker) {
                datePicker.destroy();
                datePicker = null;
            }

            try {
                var today = new Date();
                var todayStr = today.getFullYear() + '-' +
                    String(today.getMonth() + 1).padStart(2, '0') + '-' +
                    String(today.getDate()).padStart(2, '0');

                datePicker = flatpickr(dateInput, {
                    dateFormat: 'Y-m-d',
                    locale: 'es',
                    minDate: 'today',
                    allowInput: false,
                    clickOpens: true,
                    defaultDate: todayStr,
                    onChange: async function(selectedDates, dateStr) {
                        if (dateStr) {
                            updateState({
                                selectedDate: dateStr,
                                isDateStepReady: true
                            });
                            if (stepDate) {
                                stepDate.dataset.dateReady = '1';
                            }
                            resetStepsAfterDate();
                            await loadTimeAvailabilityForDate(dateStr);
                        }
                    }
                });

                updateState({
                    selectedDate: todayStr,
                    isDateStepReady: true
                });
                if (stepDate) {
                    stepDate.dataset.dateReady = '1';
                }
                resetStepsAfterDate();
                loadTimeAvailabilityForDate(todayStr);
            } catch (error) {
                console.error('[AdminFastappointmentFlowController] Error init Flatpickr:', error);
                dateInput.type = 'date';
                var todayStr = new Date().toISOString().split('T')[0];
                dateInput.setAttribute('min', todayStr);
                dateInput.value = todayStr;
                if (stepDate) {
                    stepDate.dataset.dateReady = '1';
                }
                updateState({
                    selectedDate: todayStr,
                    isDateStepReady: true
                });
                resetStepsAfterDate();
                loadTimeAvailabilityForDate(todayStr);
                dateInput.addEventListener('change', function() {
                    var selectedValue = dateInput.value || '';

                    if (!selectedValue) {
                        return;
                    }

                    updateState({
                        selectedDate: selectedValue,
                        isDateStepReady: true
                    });

                    if (stepDate) {
                        stepDate.dataset.dateReady = '1';
                    }

                    resetStepsAfterDate();
                    loadTimeAvailabilityForDate(selectedValue);
                });
            }
        }

        function cleanupDatePicker() {
            if (datePicker) {
                try {
                    datePicker.destroy();
                    datePicker = null;
                } catch (e) {
                    console.warn('[AdminFastappointmentFlowController] cleanupDatePicker:', e);
                }
            }
        }

        initClientController();
        bindClientSelection();
        bindTimeSelection();
        observeClientSelectUpdates();
        syncSelectedClient();
        initDatePicker();
        validateFastAppointmentPrerequisites();

        console.log('[AdminFastappointmentFlowController] Cliente, Fecha y prerrequisitos conectados');

        return {
            validatePrerequisites: validateFastAppointmentPrerequisites,
            destroy: function() {
                isDestroyed = true;
                cleanupDatePicker();

                if (handleClientChange) {
                    clientSelect.removeEventListener('change', handleClientChange);
                }

                if (handleTimeChange && timeSelect) {
                    timeSelect.removeEventListener('change', handleTimeChange);
                }

                if (clientSelectObserver) {
                    clientSelectObserver.disconnect();
                    clientSelectObserver = null;
                }

                if (clientController && typeof clientController.destroy === 'function') {
                    clientController.destroy();
                }

                console.log('[AdminFastappointmentFlowController] Destruido');
            }
        };
    }

    window.AdminFastappointmentFlowController = {
        init: createController
    };
})();
