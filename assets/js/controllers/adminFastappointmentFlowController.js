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
        const stepDate = document.querySelector(stepDateSelector);
        const stepTime = document.querySelector(stepTimeSelector);
        const stepService = document.getElementById('aa-fastappointment-step-service');
        const stepStaff = document.getElementById('aa-fastappointment-step-staff');
        const stepArea = document.getElementById('aa-fastappointment-step-area');
        const timeSelect = document.getElementById(timeSelectId);
        const serviceSelect = document.getElementById('aa-fastappointment-service');
        const staffSelect = document.getElementById('aa-fastappointment-staff');
        const staffMessageBox = document.getElementById('aa-fastappointment-staff-message');
        const areaSelect = document.getElementById('aa-fastappointment-area');
        const areaMessageBox = document.getElementById('aa-fastappointment-area-message');
        const confirmCheckbox = document.getElementById('aa-fastappointment-confirm');
        const formEl = document.getElementById('aa-fastappointment-form');
        const summaryBox = document.querySelector(summarySelector);
        const stepOrder = ['client', 'service', 'date', 'time', 'staff', 'area'];
        const stepElements = {
            client: stepClient,
            date: stepDate,
            time: stepTime,
            service: stepService,
            staff: stepStaff,
            area: stepArea
        };

        if (!stepClient || !clientSelect) {
            console.warn('[AdminFastappointmentFlowController] Bloque Cliente no disponible');
            return null;
        }

        let clientController = null;
        let clientSelectObserver = null;
        let handleClientChange = null;
        let handleTimeChange = null;
        let handleServiceChange = null;
        let handleStaffChange = null;
        let handleAreaChange = null;
        let handleFormSubmit = null;
        let handleClientSavedForFlow = null;
        let handleStepHeaderClick = null;
        let datePicker = null;
        let isDestroyed = false;
        let clientSelectedByUser = false;
        let expandedCompletedStepId = null;
        let timeAvailabilityRequestId = 0;
        let staffAvailabilityRequestId = 0;
        let areaAvailabilityRequestId = 0;

        function updateState(patch) {
            const currentState = getState() || {};

            if (setState) {
                setState(Object.assign({}, currentState, patch));
                renderVisualSteps();
                return;
            }

            Object.assign(currentState, patch);
            renderVisualSteps();
        }

        function getStepBody(stepElement) {
            if (!stepElement) {
                return null;
            }

            return stepElement.querySelector('[data-aa-fastappointment-step-body]');
        }

        function getStepSummaryElement(stepElement) {
            if (!stepElement) {
                return null;
            }

            return stepElement.querySelector('[data-aa-fastappointment-step-summary]');
        }

        function getSelectedOptionLabel(selectElement) {
            if (!selectElement || selectElement.selectedIndex < 0) {
                return '';
            }

            const option = selectElement.options[selectElement.selectedIndex];

            if (!option || !option.value) {
                return '';
            }

            return String(option.textContent || '').trim();
        }

        function formatSelectedDate(dateStr) {
            if (!dateStr) {
                return '';
            }

            try {
                return new Intl.DateTimeFormat('es-MX', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                }).format(new Date(dateStr + 'T00:00:00'));
            } catch (error) {
                return dateStr;
            }
        }

        function getCompletedStepSummaryMap() {
            const state = getState() || {};
            const selectedClient = state.selectedClient || null;

            return {
                client: selectedClient && selectedClient.nombre ? selectedClient.nombre : getSelectedOptionLabel(clientSelect),
                date: formatSelectedDate(state.selectedDate || ''),
                time: state.selectedTime || '',
                service: getSelectedOptionLabel(serviceSelect),
                staff: getSelectedOptionLabel(staffSelect),
                area: getSelectedOptionLabel(areaSelect)
            };
        }

        function getCompletedStepMap() {
            const state = getState() || {};

            return {
                client: !!state.isClientStepReady,
                service: !!state.selectedServiceId,
                date: !!state.selectedDate && !!state.isDateStepReady,
                time: !!state.selectedTime,
                staff: !!state.selectedStaffId && !!state.isSelectedStaffAvailable,
                area: !!state.selectedAreaId && !!state.isSelectedAreaAvailable
            };
        }

        function getSubmitButton() {
            return document.getElementById('aa-fastappointment-submit');
        }

        function isFormReady() {
            const state = getState() || {};

            return !!(
                state.selectedClientId &&
                state.selectedServiceId &&
                state.selectedDate &&
                state.selectedTime &&
                state.selectedStaffId &&
                state.isSelectedStaffAvailable &&
                state.selectedAreaId &&
                state.isSelectedAreaAvailable
            );
        }

        function updateSubmitButtonState() {
            const submitBtn = getSubmitButton();

            if (!submitBtn) {
                return;
            }

            const ready = isFormReady();

            submitBtn.disabled = !ready;
            submitBtn.classList.toggle('opacity-50', !ready);
            submitBtn.classList.toggle('cursor-not-allowed', !ready);
            submitBtn.classList.toggle('hover:bg-indigo-700', ready);

            console.log('[FastAppointment] submit button state:', {
                ready: ready
            });
        }

        function getVisualStepStateMap() {
            const completedMap = getCompletedStepMap();
            const visualStateMap = {};
            let activeStepId = null;
            let activeAssigned = false;

            stepOrder.forEach(function(stepId) {
                if (completedMap[stepId]) {
                    visualStateMap[stepId] = 'completed';
                    return;
                }

                if (!activeAssigned) {
                    activeAssigned = true;
                    activeStepId = stepId;
                    visualStateMap[stepId] = 'active';
                    return;
                }

                visualStateMap[stepId] = 'disabled';
            });

            console.log('[FastAppointmentSteps] active step calculated:', activeStepId);
            console.log('[FastAppointmentSteps] visual state map:', visualStateMap);

            return {
                activeStepId: activeStepId,
                visualStateMap: visualStateMap
            };
        }

        function renderVisualSteps() {
            const visual = getVisualStepStateMap();
            const summaryMap = getCompletedStepSummaryMap();

            if (expandedCompletedStepId && visual.visualStateMap[expandedCompletedStepId] !== 'completed') {
                expandedCompletedStepId = null;
            }

            stepOrder.forEach(function(stepId) {
                const stepElement = stepElements[stepId];
                const status = visual.visualStateMap[stepId] || 'disabled';

                if (!stepElement) {
                    return;
                }

                const header = stepElement.querySelector('[data-aa-fastappointment-step-header]');
                const body = getStepBody(stepElement);
                const summary = getStepSummaryElement(stepElement);
                const check = stepElement.querySelector('[data-aa-fastappointment-step-check]');
                const isExpanded = status === 'active' || expandedCompletedStepId === stepId;

                stepElement.dataset.visualState = status;
                stepElement.classList.remove('border-indigo-100', 'bg-indigo-50', 'border-gray-200', 'bg-white', 'bg-gray-50', 'opacity-60');

                if (status === 'active') {
                    stepElement.classList.add('border-indigo-100', 'bg-indigo-50');
                } else if (status === 'completed') {
                    stepElement.classList.add('border-gray-200', 'bg-white');
                } else {
                    stepElement.classList.add('border-gray-200', 'bg-gray-50', 'opacity-60');
                }

                if (header) {
                    header.disabled = status === 'disabled';
                    header.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                    header.setAttribute('aria-disabled', status === 'disabled' ? 'true' : 'false');
                    header.classList.remove('cursor-pointer', 'cursor-not-allowed');
                    header.classList.add(status === 'disabled' ? 'cursor-not-allowed' : 'cursor-pointer');
                }

                if (body) {
                    body.classList.toggle('hidden', !isExpanded);
                }

                if (summary) {
                    const summaryText = status === 'completed' ? (summaryMap[stepId] || '') : '';
                    summary.textContent = summaryText;
                    summary.classList.toggle('hidden', !summaryText);
                }

                if (check) {
                    check.classList.toggle('hidden', status !== 'completed');
                }
            });

            console.log('[FastAppointmentSteps] visual render applied:', {
                activeStepId: visual.activeStepId,
                expandedCompletedStepId: expandedCompletedStepId,
                visualStateMap: visual.visualStateMap
            });

            updateSubmitButtonState();
        }

        function bindStepHeaderInteraction() {
            if (!formEl) {
                return;
            }

            handleStepHeaderClick = function(event) {
                const header = event.target.closest('[data-aa-fastappointment-step-header]');

                if (!header || !formEl.contains(header)) {
                    return;
                }

                const stepElement = header.closest('[data-aa-fastappointment-step]');
                const stepId = stepElement ? stepElement.dataset.aaFastappointmentStep : '';
                const status = stepElement ? (stepElement.dataset.visualState || 'disabled') : 'disabled';

                if (!stepId || status === 'disabled') {
                    return;
                }

                expandedCompletedStepId = status === 'completed'
                    ? (expandedCompletedStepId === stepId ? null : stepId)
                    : null;

                console.log('[FastAppointmentSteps] header interaction:', {
                    stepId: stepId,
                    status: status,
                    expandedCompletedStepId: expandedCompletedStepId
                });

                renderVisualSteps();
            };

            formEl.addEventListener('click', handleStepHeaderClick);
        }

        function getOrCreatePrerequisitesNotice() {
            if (!stepTime) {
                return null;
            }

            const stepTimeBody = getStepBody(stepTime);
            let notice = stepTime.querySelector('[data-aa-fastappointment-prerequisites]');

            if (notice) {
                return notice;
            }

            notice = document.createElement('div');
            notice.setAttribute('data-aa-fastappointment-prerequisites', '1');
            notice.className = 'hidden rounded-lg border px-3 py-2 text-sm';

            if (stepTimeBody && timeSelect && timeSelect.parentNode === stepTimeBody) {
                stepTimeBody.insertBefore(notice, timeSelect);
            } else if (stepTimeBody) {
                stepTimeBody.appendChild(notice);
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
                notice.className = 'hidden rounded-lg border px-3 py-2 text-sm';
                notice.innerHTML = '';
                return;
            }

            const toneMap = {
                info: 'border-blue-200 bg-blue-50 text-blue-800',
                error: 'border-amber-200 bg-amber-50 text-amber-900',
                success: 'border-emerald-200 bg-emerald-50 text-emerald-800'
            };

            notice.className = 'rounded-lg border px-3 py-2 text-sm ' + (toneMap[tone] || toneMap.info);
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
                var eligibleServices = getEligibleServices(result);
                populateServiceSelect(eligibleServices);

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
            const isReady = clientSelectedByUser && !!selectedClient;

            updateState({
                selectedClientId: selectedClient ? selectedClient.id : null,
                selectedClient: selectedClient,
                isClientStepReady: isReady
            });

            stepClient.dataset.clientReady = isReady ? '1' : '0';
        }

        function bindClientSelection() {
            handleClientChange = function() {
                clientSelectedByUser = true;
                syncSelectedClient();
            };

            clientSelect.addEventListener('change', handleClientChange);
        }

        function observeClientSelectUpdates() {
            clientSelectObserver = new MutationObserver(function() {
                var firstOption = clientSelect.options[0];
                if (!firstOption || firstOption.value !== '') {
                    clientSelectObserver.disconnect();
                    var placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = '-- Selecciona un cliente --';
                    clientSelect.insertBefore(placeholder, clientSelect.firstChild);
                    if (!clientSelectedByUser) {
                        clientSelect.selectedIndex = 0;
                    }
                    clientSelectObserver.observe(clientSelect, { childList: true });
                }
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

            var activeServicesMap = new Map();
            (prerequisites.activeServices || []).forEach(function(s) {
                activeServicesMap.set(String(s.id), s);
            });

            var fromStaff = prerequisites.usableStaff.flatMap(function(s) {
                return s.services || [];
            });
            var eligible = fromStaff.filter(function(s) {
                return activeServicesMap.has(String(s.id));
            });

            var byId = new Map();
            eligible.forEach(function(s) {
                if (!byId.has(s.id)) {
                    var activeData = activeServicesMap.get(String(s.id)) || {};
                    byId.set(s.id, {
                        id: s.id,
                        name: s.name || activeData.name || '',
                        duration_minutes: activeData.duration_minutes || null
                    });
                }
            });
            return Array.from(byId.values()).sort(function(a, b) {
                return (a.name || '').localeCompare(b.name || '');
            });
        }

        function populateServiceSelect(eligibleServices) {
            if (!serviceSelect) {
                return;
            }

            var html = '<option value="">-- Selecciona un servicio --</option>';
            (eligibleServices || []).forEach(function(s) {
                var durationAttr = s.duration_minutes
                    ? ' data-duration-minutes="' + escapeHtml(String(s.duration_minutes)) + '"'
                    : '';
                html += '<option value="' + escapeHtml(String(s.id)) + '"' + durationAttr + '>' + escapeHtml(s.name || '') + '</option>';
            });

            serviceSelect.innerHTML = html;
            serviceSelect.disabled = !(eligibleServices || []).length;
        }

        function populateStaffSelect(allStaff) {
            if (!staffSelect) {
                return;
            }

            var list = allStaff || [];
            var available = list.filter(function(s) { return s.available; });
            var unavailable = list.filter(function(s) { return !s.available; });

            var html = '<option value="">-- Selecciona personal --</option>';

            available.forEach(function(staff) {
                html += '<option value="' + escapeHtml(String(staff.id)) + '"'
                    + ' data-available="1">'
                    + escapeHtml(staff.name || '') + '</option>';
            });

            unavailable.forEach(function(staff) {
                html += '<option value="' + escapeHtml(String(staff.id)) + '"'
                    + ' data-available="0"'
                    + ' data-reason="' + escapeHtml(staff.reason || '') + '">'
                    + escapeHtml(staff.name || '') + ' (no disponible)</option>';
            });

            staffSelect.innerHTML = html;
            staffSelect.disabled = !list.length;
        }

        function renderStaffAvailabilityMessage(text) {
            if (!staffMessageBox) {
                return;
            }

            if (!text) {
                staffMessageBox.textContent = '';
                staffMessageBox.classList.add('hidden');
                return;
            }

            staffMessageBox.textContent = text;
            staffMessageBox.classList.remove('hidden');
        }

        function populateAreaSelect(areas) {
            if (!areaSelect) {
                return;
            }

            var list = areas || [];
            var available = list.filter(function(a) { return !a.occupied; });
            var unavailable = list.filter(function(a) { return a.occupied; });

            var html = '<option value="">-- Selecciona una zona --</option>';

            available.forEach(function(area) {
                html += '<option value="' + escapeHtml(String(area.id)) + '"'
                    + ' data-available="1"'
                    + ' data-occupied="0">'
                    + escapeHtml(area.name || '') + '</option>';
            });

            unavailable.forEach(function(area) {
                html += '<option value="' + escapeHtml(String(area.id)) + '"'
                    + ' data-available="0"'
                    + ' data-occupied="1"'
                    + ' data-reason="' + escapeHtml(area.reason || '') + '">'
                    + escapeHtml(area.name || '') + ' (no disponible)</option>';
            });

            areaSelect.innerHTML = html;
            areaSelect.disabled = !list.length;
        }

        function renderAreaAvailabilityMessage(text) {
            if (!areaMessageBox) {
                return;
            }

            if (!text) {
                areaMessageBox.textContent = '';
                areaMessageBox.classList.add('hidden');
                return;
            }

            areaMessageBox.textContent = text;
            areaMessageBox.classList.remove('hidden');
        }

        function invalidateStaffAvailabilityRequests() {
            staffAvailabilityRequestId++;
        }

        function invalidateAreaAvailabilityRequests() {
            areaAvailabilityRequestId++;
        }

        function resetStepsAfterService() {
            invalidateStaffAvailabilityRequests();
            invalidateAreaAvailabilityRequests();

            if (timeSelect && timeSelect.options.length > 0) {
                timeSelect.selectedIndex = 0;
            }
            if (staffSelect) {
                staffSelect.selectedIndex = 0;
            }
            if (areaSelect && areaSelect.options.length > 0) {
                areaSelect.selectedIndex = 0;
            }
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }

            updateState({
                selectedDate: null,
                isDateStepReady: false,
                selectedTime: null,
                selectedStaffId: null,
                isSelectedStaffAvailable: false,
                selectedAreaId: null,
                isSelectedAreaAvailable: false
            });

            const state = getState() || {};
            setTimeStepBlockedState(!state.canStartFastAppointment);

            if (timeSelect) {
                timeSelect.innerHTML = '<option value="">-- Selecciona una hora --</option>';
            }
            populateStaffSelect([]);
            renderStaffAvailabilityMessage('');
            populateAreaSelect([]);
            renderAreaAvailabilityMessage('');

            var dateInput = document.getElementById(dateInputId);
            if (dateInput) {
                dateInput.value = '';
            }
            if (datePicker && typeof datePicker.clear === 'function') {
                datePicker.clear();
            }
            if (stepDate) {
                stepDate.dataset.dateReady = '0';
            }
        }

        function resetStepsAfterTime() {
            invalidateStaffAvailabilityRequests();
            invalidateAreaAvailabilityRequests();

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
                selectedStaffId: null,
                isSelectedStaffAvailable: false,
                selectedAreaId: null,
                isSelectedAreaAvailable: false
            });

            populateStaffSelect([]);
            renderStaffAvailabilityMessage('');
            populateAreaSelect([]);
            renderAreaAvailabilityMessage('');
        }

        function resetStepsAfterStaff() {
            invalidateAreaAvailabilityRequests();

            if (areaSelect && areaSelect.options.length > 0) {
                areaSelect.selectedIndex = 0;
            }
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }

            updateState({
                selectedAreaId: null,
                isSelectedAreaAvailable: false
            });

            populateAreaSelect([]);
            renderAreaAvailabilityMessage('');
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

        async function loadAvailableStaffForSelection(dateStr, timeStr, serviceId) {
            if (!staffSelect) {
                return null;
            }

            if (!dateStr || !timeStr || !serviceId) {
                populateStaffSelect([]);
                renderStaffAvailabilityMessage('');
                return null;
            }

            if (!window.FastAppointmentTimeAvailabilityService ||
                typeof window.FastAppointmentTimeAvailabilityService.getAllStaffWithAvailability !== 'function') {
                console.warn('[AdminFastappointmentFlowController] FastAppointmentTimeAvailabilityService.getAllStaffWithAvailability no disponible');
                populateStaffSelect([]);
                renderStaffAvailabilityMessage('');
                return null;
            }

            staffAvailabilityRequestId++;
            const myRequestId = staffAvailabilityRequestId;

            const state = getState() || {};
            const prerequisites = state.fastAppointmentPrerequisites || null;
            const usableStaff = prerequisites && Array.isArray(prerequisites.usableStaff)
                ? prerequisites.usableStaff
                : [];

            console.log('[FastAppointmentFlow] loadAvailableStaffForSelection requestId=' + myRequestId +
                ' date=' + dateStr + ' time=' + timeStr + ' serviceId=' + serviceId);

            const result = await window.FastAppointmentTimeAvailabilityService.getAllStaffWithAvailability(
                dateStr,
                timeStr,
                serviceId,
                {
                    prerequisites: prerequisites,
                    usableStaff: usableStaff
                }
            );

            if (isDestroyed) {
                return result;
            }

            if (myRequestId !== staffAvailabilityRequestId) {
                console.warn('[FastAppointmentFlow] Descartando staff obsoleto requestId=' + myRequestId +
                    ' (actual=' + staffAvailabilityRequestId + ')');
                return result;
            }

            var allStaff = result && Array.isArray(result.staff) ? result.staff : [];
            var hasAvailable = allStaff.some(function(s) { return s.available; });

            populateStaffSelect(allStaff);

            if (!allStaff.length) {
                renderStaffAvailabilityMessage(
                    'No hay personal registrado. Agrega personal en Asignaciones > Personal.'
                );
            } else if (!hasAvailable) {
                renderStaffAvailabilityMessage(
                    'Todo el personal esta ocupado u no tiene este servicio asignado a las ' + timeStr + '.'
                );
            } else {
                renderStaffAvailabilityMessage('');
            }

            return result;
        }

        async function loadAvailableAreasForSelection(dateStr, timeStr, staffId) {
            if (!areaSelect) {
                return null;
            }

            if (!dateStr || !timeStr || !staffId) {
                populateAreaSelect([]);
                renderAreaAvailabilityMessage('');
                return null;
            }

            if (!window.FastAppointmentTimeAvailabilityService ||
                typeof window.FastAppointmentTimeAvailabilityService.getAreaAvailabilityBySelection !== 'function') {
                console.warn('[AdminFastappointmentFlowController] FastAppointmentTimeAvailabilityService.getAreaAvailabilityBySelection no disponible');
                populateAreaSelect([]);
                renderAreaAvailabilityMessage('');
                return null;
            }

            areaAvailabilityRequestId++;
            const myRequestId = areaAvailabilityRequestId;

            const state = getState() || {};
            const prerequisites = state.fastAppointmentPrerequisites || null;
            const activeServiceAreas = prerequisites && Array.isArray(prerequisites.activeServiceAreas)
                ? prerequisites.activeServiceAreas
                : [];

            console.log('[FastAppointmentFlow] loadAvailableAreasForSelection requestId=' + myRequestId +
                ' date=' + dateStr + ' time=' + timeStr + ' staffId=' + staffId);

            const selectedServiceId = state.selectedServiceId || null;

            const result = await window.FastAppointmentTimeAvailabilityService.getAreaAvailabilityBySelection(
                dateStr,
                timeStr,
                staffId,
                {
                    activeServiceAreas: activeServiceAreas,
                    serviceId: selectedServiceId
                }
            );

            if (isDestroyed) {
                return result;
            }

            if (myRequestId !== areaAvailabilityRequestId) {
                console.warn('[FastAppointmentFlow] Descartando zonas obsoletas requestId=' + myRequestId +
                    ' (actual=' + areaAvailabilityRequestId + ')');
                return result;
            }

            populateAreaSelect(result && Array.isArray(result.areas) ? result.areas : []);
            renderAreaAvailabilityMessage('');

            return result;
        }

        function bindTimeSelection() {
            if (!timeSelect) {
                return;
            }

            handleTimeChange = async function() {
                const selectedTime = this.value || null;

                updateState({
                    selectedTime: selectedTime
                });

                resetStepsAfterTime();

                if (!selectedTime) {
                    return;
                }

                var state = getState() || {};
                await loadAvailableStaffForSelection(
                    state.selectedDate || null,
                    selectedTime,
                    state.selectedServiceId || null
                );
            };

            timeSelect.addEventListener('change', handleTimeChange);
        }

        function bindServiceSelection() {
            if (!serviceSelect) {
                return;
            }

            handleServiceChange = function() {
                const selectedServiceId = this.value || null;

                updateState({
                    selectedServiceId: selectedServiceId
                });

                resetStepsAfterService();
            };

            serviceSelect.addEventListener('change', handleServiceChange);
        }

        function getSelectedServiceName() {
            if (!serviceSelect || serviceSelect.selectedIndex < 1) {
                return '';
            }
            var opt = serviceSelect.options[serviceSelect.selectedIndex];
            return opt ? (opt.textContent || '').replace(/\s*\(no disponible\)\s*$/, '').trim() : '';
        }

        function bindStaffSelection() {
            if (!staffSelect) {
                return;
            }

            handleStaffChange = async function() {
                const selectedStaffId = this.value || null;
                const selectedOption = this.options[this.selectedIndex];
                const isAvailable = !selectedOption || selectedOption.dataset.available !== '0';
                const reason = selectedOption ? (selectedOption.dataset.reason || '') : '';
                const state = getState() || {};

                updateState({
                    selectedStaffId: selectedStaffId,
                    isSelectedStaffAvailable: selectedStaffId ? isAvailable : false
                });

                resetStepsAfterStaff();

                if (!selectedStaffId) {
                    renderStaffAvailabilityMessage('');
                    return;
                }

                if (!isAvailable) {
                    var serviceName = getSelectedServiceName();
                    if (reason === 'no_service') {
                        renderStaffAvailabilityMessage(
                            'Este profesional no tiene asignado el servicio "' + serviceName +
                            '". Puedes agregarlo en Asignaciones > Personal.'
                        );
                    } else {
                        renderStaffAvailabilityMessage(
                            'Este profesional esta ocupado a las ' + (state.selectedTime || '') +
                            '. Selecciona otra hora u otro profesional.'
                        );
                    }
                    return;
                }

                renderStaffAvailabilityMessage('');

                await loadAvailableAreasForSelection(
                    state.selectedDate || null,
                    state.selectedTime || null,
                    selectedStaffId
                );
            };

            staffSelect.addEventListener('change', handleStaffChange);
        }

        function bindAreaSelection() {
            if (!areaSelect) {
                return;
            }

            handleAreaChange = function() {
                const selectedAreaId = this.value || null;
                const selectedOption = this.options[this.selectedIndex];
                const isAvailable = !selectedOption || selectedOption.dataset.available !== '0';
                const reason = selectedOption ? (selectedOption.dataset.reason || '') : '';
                const state = getState() || {};
                const selectedTime = state.selectedTime || '';

                updateState({
                    selectedAreaId: selectedAreaId,
                    isSelectedAreaAvailable: selectedAreaId ? isAvailable : false
                });

                if (!selectedAreaId || isAvailable) {
                    renderAreaAvailabilityMessage('');
                    return;
                }

                switch (reason) {
                    case 'busy_reservation':
                        renderAreaAvailabilityMessage(
                            'Esta zona esta ocupada por otra reservacion a las ' + selectedTime +
                            '. Elige otra zona o selecciona otra hora disponible.'
                        );
                        break;
                    case 'zone_reserved_for_other_staff':
                        renderAreaAvailabilityMessage(
                            'Esta zona ya esta reservada por otro profesional a las ' + selectedTime +
                            '. Elige otra zona o selecciona otra hora.'
                        );
                        break;
                    case 'service_not_offered':
                        renderAreaAvailabilityMessage(
                            'En esta zona el profesional tiene un turno a las ' + selectedTime +
                            ' que no incluye el servicio seleccionado. Edita el turno o elige otra zona.'
                        );
                        break;
                    case 'out_of_turn':
                        renderAreaAvailabilityMessage(
                            'En esta zona el profesional tiene un turno que no contiene la hora seleccionada. ' +
                            'Elige una hora dentro del turno o cambia de zona.'
                        );
                        break;
                    default:
                        renderAreaAvailabilityMessage(
                            'La zona de atencion seleccionada esta ocupada a las ' + selectedTime +
                            '. Elige otra zona o selecciona otra hora disponible.'
                        );
                }
            };

            areaSelect.addEventListener('change', handleAreaChange);
        }

        function addMinutesToTime(timeStr, minutes) {
            var parts = String(timeStr || '00:00').split(':');
            var total = (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0) + minutes;
            var h = Math.floor(total / 60) % 24;
            var m = total % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }

        async function createAssignmentForFastAppointment(payload, slotDuration) {
            var endTime = addMinutesToTime(payload.time, slotDuration);
            var ajaxUrl = window.ajaxurl || '/wp-admin/admin-ajax.php';

            var assignmentPayload = {
                assignment_date: payload.date,
                start_time: payload.time,
                end_time: endTime,
                staff_id: payload.staff_id,
                service_area_id: payload.service_area_id,
                capacity: 1
            };

            console.log('[FastAppointment] No existe assignment contenedora, creando nueva assignment');
            console.log('[FastAppointment] Creando assignment con payload', assignmentPayload);

            var formData = new FormData();
            formData.append('action', 'aa_create_assignment');
            formData.append('assignment_date', assignmentPayload.assignment_date);
            formData.append('start_time', assignmentPayload.start_time);
            formData.append('end_time', assignmentPayload.end_time);
            formData.append('staff_id', assignmentPayload.staff_id);
            formData.append('service_area_id', assignmentPayload.service_area_id);
            formData.append('capacity', assignmentPayload.capacity);

            var response = await fetch(ajaxUrl, { method: 'POST', body: formData });
            var data = await response.json();

            if (!data.success || !data.data || !data.data.assignment || !data.data.assignment.id) {
                var msg = (data.data && data.data.message) ? data.data.message : 'Respuesta inesperada';
                throw new Error('Error al crear assignment: ' + msg);
            }

            var assignmentId = data.data.assignment.id;
            console.log('[FastAppointment] Assignment creada correctamente ID: ' + assignmentId);

            var svcFormData = new FormData();
            svcFormData.append('action', 'aa_add_assignment_service');
            svcFormData.append('assignment_id', assignmentId);
            svcFormData.append('service_id', payload.service_id);

            var svcResponse = await fetch(ajaxUrl, { method: 'POST', body: svcFormData });
            var svcData = await svcResponse.json();

            if (!svcData.success) {
                var svcMsg = (svcData.data && svcData.data.message) ? svcData.data.message : 'Error desconocido';
                console.error('[FastAppointment] Error al agregar servicio a assignment:', svcMsg);
            } else {
                console.log('[FastAppointment] Servicio agregado a assignment ID: ' + assignmentId);
            }

            return assignmentId;
        }

        function buildSelectedSlotISO(dateStr, timeStr) {
            var ymd = window.DateUtils && window.DateUtils.extractYmd
                ? window.DateUtils.extractYmd(dateStr)
                : dateStr;
            if (!ymd || !timeStr) return null;

            var parts = timeStr.split(':');
            var hours = parseInt(parts[0], 10);
            var minutes = parseInt(parts[1], 10);

            var localDate = new Date(ymd + 'T00:00:00');
            localDate.setHours(hours, minutes, 0, 0);

            return localDate.toISOString();
        }

        function bindFormSubmit() {
            if (!formEl) {
                console.warn('[AdminFastappointmentFlowController] Formulario no encontrado para submit');
                return;
            }

            var submitBtn = document.getElementById('aa-fastappointment-submit');
            var isSubmitting = false;

            handleFormSubmit = async function(e) {
                e.preventDefault();

                if (isSubmitting) {
                    console.log('[FastAppointment] Submit ignorado: ya hay un proceso en curso');
                    return;
                }

                isSubmitting = true;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Agendando\u2026';
                }

                var state = getState() || {};
                var payload = {
                    client_id: state.selectedClientId || null,
                    date: state.selectedDate || null,
                    time: state.selectedTime || null,
                    service_id: state.selectedServiceId || null,
                    staff_id: state.selectedStaffId || null,
                    service_area_id: state.selectedAreaId || null
                };

                console.log('[FastAppointment] Submit payload', payload);

                var prerequisites = state.fastAppointmentPrerequisites || {};
                var selectedServiceData = (prerequisites.activeServices || []).find(function(s) {
                    return String(s.id) === String(payload.service_id);
                });
                var slotDuration = (selectedServiceData && parseInt(selectedServiceData.duration_minutes, 10))
                    || parseInt(window.aa_slot_duration, 10) || 60;

                try {
                    var result = await window.FastAppointmentTimeAvailabilityService
                        .findCompatibleAssignment(payload.date, {
                            staffId: payload.staff_id,
                            areaId: payload.service_area_id,
                            serviceId: payload.service_id,
                            time: payload.time,
                            slotDuration: slotDuration
                        });

                    updateState({
                        resolvedAssignmentId: result.assignment ? result.assignment.id : null,
                        resolvedAssignmentMode: result.mode
                    });

                    console.log('[FastAppointment] State actualizado — resolvedAssignmentId:',
                        result.assignment ? result.assignment.id : null,
                        '| resolvedAssignmentMode:', result.mode);

                    var currentState = getState() || {};

                    if (currentState.resolvedAssignmentMode === 'reject_service_not_offered'
                        || currentState.resolvedAssignmentMode === 'reject_out_of_turn') {
                        console.warn('[FastAppointment] Submit abortado por modo:',
                            currentState.resolvedAssignmentMode,
                            '| rejection:', result.rejection);

                        var rejectionMessage = (result.rejection && result.rejection.message)
                            ? result.rejection.message
                            : 'No se puede agendar la cita con la zona/horario seleccionado.';

                        renderAreaAvailabilityMessage(rejectionMessage);

                        updateState({
                            resolvedAssignmentId: null
                        });

                        isSubmitting = false;
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Agendar cita';
                        }
                        updateSubmitButtonState();
                        return;
                    }

                    if (currentState.resolvedAssignmentMode === 'existing') {
                        console.log('[FastAppointment] Assignment existente reutilizada, ID:',
                            currentState.resolvedAssignmentId);
                    } else if (currentState.resolvedAssignmentMode === 'create_new') {
                        var newAssignmentId = await createAssignmentForFastAppointment(payload, slotDuration);

                        updateState({
                            resolvedAssignmentId: newAssignmentId,
                            resolvedAssignmentMode: 'existing'
                        });

                        console.log('[FastAppointment] State actualizado con nueva assignment ID');
                    }

                    var finalState = getState() || {};
                    var assignmentId = finalState.resolvedAssignmentId;

                    if (!assignmentId) {
                        throw new Error('No se pudo resolver assignment_id para la reservacion');
                    }

                    var selectedClient = finalState.selectedClient || {};
                    var selectedSlotISO = buildSelectedSlotISO(payload.date, payload.time);

                    var autoConfirm = !!(confirmCheckbox && confirmCheckbox.checked && confirmCheckbox.value === 'confirmed');

                    var datos = {
                        servicio: payload.service_id,
                        fecha: selectedSlotISO,
                        nombre: selectedClient.nombre || '',
                        telefono: selectedClient.telefono || '',
                        correo: selectedClient.correo || '',
                        duracion: slotDuration,
                        nonce: (window.aa_asistant_vars && window.aa_asistant_vars.nonce_crear_cita) || '',
                        assignment_id: parseInt(assignmentId, 10)
                    };

                    console.log('[FastAppointment] Creando reservacion con payload', datos);

                    var saveResponse = await window.ReservationService.saveReservation(datos);

                    if (saveResponse.data && saveResponse.data.id) {
                        datos.id_reserva = saveResponse.data.id;
                    } else if (saveResponse.id) {
                        datos.id_reserva = saveResponse.id;
                    }

                    console.log('[FastAppointment] Reservacion creada correctamente ID:', datos.id_reserva || '(sin ID)');

                    if (window.AdminCalendarController && typeof window.AdminCalendarController.recargar === 'function') {
                        window.AdminCalendarController.recargar();
                    }

                    document.dispatchEvent(new CustomEvent('aa:notifications:refresh', {
                        detail: { source: 'fastappointment-created' }
                    }));

                    if (typeof AAAdmin !== 'undefined' && typeof AAAdmin.closeModal === 'function') {
                        AAAdmin.closeModal();
                    }

                    console.log('[FastAppointment] Calendario refrescado y modal cerrado');

                    if (autoConfirm) {
                        if (datos.id_reserva && window.ConfirmService && typeof window.ConfirmService.confirmar === 'function') {
                            console.log('[FastAppointment] Confirmacion inmediata solicitada para reserva ID:', datos.id_reserva);
                            window.ConfirmService.confirmar(datos.id_reserva)
                                .then(function(confirmResp) {
                                    if (confirmResp.success) {
                                        if (
                                            window.AdminConfirmController &&
                                            typeof window.AdminConfirmController.showConfirmResultNotification === 'function'
                                        ) {
                                            window.AdminConfirmController.showConfirmResultNotification(confirmResp);
                                        } else {
                                            console.log('[FastAppointment] Cita confirmada en background');
                                        }
                                        if (window.AdminCalendarController && typeof window.AdminCalendarController.recargar === 'function') {
                                            window.AdminCalendarController.recargar();
                                        }
                                    } else {
                                        console.warn('[FastAppointment] Confirmacion remota fallo');
                                    }
                                })
                                .catch(function(confirmErr) {
                                    console.error('[FastAppointment] Error en confirmacion remota:', confirmErr.message);
                                });
                        }
                    } else {
                        if (datos.correo) {
                            console.log('[FastAppointment] Enviando correo de confirmacion pending para reserva ID:', datos.id_reserva);
                            window.ReservationService.sendConfirmation(datos)
                                .then(function(wpResponse) {
                                    if (
                                        window.AdminConfirmController &&
                                        typeof window.AdminConfirmController.showSendConfirmationResultNotification === 'function'
                                    ) {
                                        window.AdminConfirmController.showSendConfirmationResultNotification(wpResponse);
                                    } else {
                                        console.log('[FastAppointment] Resultado del envío de solicitud:', wpResponse);
                                    }
                                })
                                .catch(function(emailError) {
                                    console.warn('[FastAppointment] Error al enviar correo (no critico):', emailError);
                                    alert('❌ Error de conexión al enviar la solicitud: ' + (emailError.message || emailError));
                                });
                        }
                    }

                } catch (err) {
                    console.error('[FastAppointment] Error en flujo de submit', err);
                    alert('Error al agendar cita: ' + err.message);
                } finally {
                    isSubmitting = false;
                    if (submitBtn) {
                        submitBtn.textContent = 'Agendar cita';
                    }
                    updateSubmitButtonState();
                }
            };

            formEl.addEventListener('submit', handleFormSubmit);
        }

        function resetStepsAfterDate() {
            invalidateStaffAvailabilityRequests();
            invalidateAreaAvailabilityRequests();

            if (timeSelect && timeSelect.options.length > 0) {
                timeSelect.selectedIndex = 0;
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
                selectedStaffId: null,
                isSelectedStaffAvailable: false,
                selectedAreaId: null,
                isSelectedAreaAvailable: false
            });

            const state = getState() || {};
            setTimeStepBlockedState(!state.canStartFastAppointment);

            if (timeSelect) {
                timeSelect.innerHTML = '<option value="">-- Selecciona una hora --</option>';
            }
            populateStaffSelect([]);
            populateAreaSelect([]);
            renderStaffAvailabilityMessage('');
            renderAreaAvailabilityMessage('');
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
                datePicker = flatpickr(dateInput, {
                    dateFormat: 'Y-m-d',
                    locale: 'es',
                    minDate: 'today',
                    allowInput: false,
                    clickOpens: true,
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
            } catch (error) {
                console.error('[AdminFastappointmentFlowController] Error init Flatpickr:', error);
                dateInput.type = 'date';
                var todayStr = new Date().toISOString().split('T')[0];
                dateInput.setAttribute('min', todayStr);
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

        bindStepHeaderInteraction();
        renderVisualSteps();
        initClientController();
        bindClientSelection();

        handleClientSavedForFlow = function() {
            clientSelectedByUser = true;
        };
        document.addEventListener('aa:client:saved', handleClientSavedForFlow);

        bindServiceSelection();
        bindTimeSelection();
        bindStaffSelection();
        bindAreaSelection();
        bindFormSubmit();
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

                if (handleClientSavedForFlow) {
                    document.removeEventListener('aa:client:saved', handleClientSavedForFlow);
                }

                if (handleStepHeaderClick && formEl) {
                    formEl.removeEventListener('click', handleStepHeaderClick);
                }

                if (handleTimeChange && timeSelect) {
                    timeSelect.removeEventListener('change', handleTimeChange);
                }

                if (handleServiceChange && serviceSelect) {
                    serviceSelect.removeEventListener('change', handleServiceChange);
                }

                if (handleStaffChange && staffSelect) {
                    staffSelect.removeEventListener('change', handleStaffChange);
                }

                if (handleAreaChange && areaSelect) {
                    areaSelect.removeEventListener('change', handleAreaChange);
                }

                if (handleFormSubmit && formEl) {
                    formEl.removeEventListener('submit', handleFormSubmit);
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
