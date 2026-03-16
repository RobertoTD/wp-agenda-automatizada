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
        const serviceSelect = document.getElementById('aa-fastappointment-service');
        const staffSelect = document.getElementById('aa-fastappointment-staff');
        const staffMessageBox = document.getElementById('aa-fastappointment-staff-message');
        const areaSelect = document.getElementById('aa-fastappointment-area');
        const areaMessageBox = document.getElementById('aa-fastappointment-area-message');
        const formEl = document.getElementById('aa-fastappointment-form');
        const summaryBox = document.querySelector(summarySelector);

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
        let datePicker = null;
        let isDestroyed = false;
        let timeAvailabilityRequestId = 0;
        let staffAvailabilityRequestId = 0;
        let areaAvailabilityRequestId = 0;

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
            if (!serviceSelect) {
                return;
            }

            var html = '<option value="">-- Selecciona un servicio --</option>';
            (eligibleServices || []).forEach(function(s) {
                html += '<option value="' + escapeHtml(String(s.id)) + '">' + escapeHtml(s.name || '') + '</option>';
            });

            serviceSelect.innerHTML = html;
            serviceSelect.disabled = !(eligibleServices || []).length;
        }

        function populateStaffSelect(availableStaff) {
            if (!staffSelect) {
                return;
            }

            var html = '<option value="">-- Selecciona personal --</option>';
            (availableStaff || []).forEach(function(staff) {
                html += '<option value="' + escapeHtml(String(staff.id)) + '">' + escapeHtml(staff.name || '') + '</option>';
            });

            staffSelect.innerHTML = html;
            staffSelect.disabled = !(availableStaff || []).length;
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

            var html = '<option value="">-- Selecciona una zona --</option>';
            (areas || []).forEach(function(area) {
                var label = area.name || '';
                if (area.occupied) {
                    label += ' /ocupado';
                }

                html += '<option value="' + escapeHtml(String(area.id)) + '" data-occupied="' +
                    (area.occupied ? '1' : '0') + '">' + escapeHtml(label) + '</option>';
            });

            areaSelect.innerHTML = html;
            areaSelect.disabled = !(areas || []).length;
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
            const confirmCheckbox = document.getElementById('aa-fastappointment-confirm');

            invalidateStaffAvailabilityRequests();
            invalidateAreaAvailabilityRequests();

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
                selectedStaffId: null,
                selectedAreaId: null
            });

            populateStaffSelect([]);
            renderStaffAvailabilityMessage('');
            populateAreaSelect([]);
            renderAreaAvailabilityMessage('');
        }

        function resetStepsAfterTime() {
            const confirmCheckbox = document.getElementById('aa-fastappointment-confirm');

            invalidateStaffAvailabilityRequests();
            invalidateAreaAvailabilityRequests();

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

            populateStaffSelect([]);
            renderStaffAvailabilityMessage('');
            populateAreaSelect([]);
            renderAreaAvailabilityMessage('');
        }

        function resetStepsAfterStaff() {
            const confirmCheckbox = document.getElementById('aa-fastappointment-confirm');

            invalidateAreaAvailabilityRequests();

            if (areaSelect && areaSelect.options.length > 0) {
                areaSelect.selectedIndex = 0;
            }
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }

            updateState({
                selectedAreaId: null
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
            if (!serviceSelect || !staffSelect) {
                return null;
            }

            if (!dateStr || !timeStr || !serviceId) {
                populateStaffSelect([]);
                renderStaffAvailabilityMessage('');
                return null;
            }

            if (!window.FastAppointmentTimeAvailabilityService ||
                typeof window.FastAppointmentTimeAvailabilityService.getAvailableStaffBySelection !== 'function') {
                console.warn('[AdminFastappointmentFlowController] FastAppointmentTimeAvailabilityService.getAvailableStaffBySelection no disponible');
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

            const result = await window.FastAppointmentTimeAvailabilityService.getAvailableStaffBySelection(
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

            var availableStaff = result && Array.isArray(result.staff) ? result.staff : [];

            populateStaffSelect(availableStaff);

            if (!availableStaff.length) {
                renderStaffAvailabilityMessage(
                    'El personal que brinda este servicio esta ocupado a las ' + timeStr + '. Elige otra hora o selecciona otro servicio.'
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

            const result = await window.FastAppointmentTimeAvailabilityService.getAreaAvailabilityBySelection(
                dateStr,
                timeStr,
                staffId,
                {
                    activeServiceAreas: activeServiceAreas
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

            handleTimeChange = function() {
                const selectedTime = this.value || null;

                updateState({
                    selectedTime: selectedTime
                });

                resetStepsAfterTime();

                if (!selectedTime) {
                    populateServiceSelect([]);
                    return;
                }

                var state = getState() || {};
                var prerequisites = state.fastAppointmentPrerequisites || null;
                var eligibleServices = getEligibleServices(prerequisites);
                populateServiceSelect(eligibleServices);
            };

            timeSelect.addEventListener('change', handleTimeChange);
        }

        function bindServiceSelection() {
            if (!serviceSelect) {
                return;
            }

            handleServiceChange = async function() {
                const selectedServiceId = this.value || null;

                updateState({
                    selectedServiceId: selectedServiceId
                });

                resetStepsAfterService();

                if (!selectedServiceId) {
                    return;
                }

                const state = getState() || {};
                await loadAvailableStaffForSelection(
                    state.selectedDate || null,
                    state.selectedTime || null,
                    selectedServiceId
                );
            };

            serviceSelect.addEventListener('change', handleServiceChange);
        }

        function bindStaffSelection() {
            if (!staffSelect) {
                return;
            }

            handleStaffChange = async function() {
                const selectedStaffId = this.value || null;

                updateState({
                    selectedStaffId: selectedStaffId
                });

                resetStepsAfterStaff();

                if (!selectedStaffId) {
                    return;
                }

                const state = getState() || {};
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
                const isOccupied = !!(selectedOption && selectedOption.dataset.occupied === '1');
                const state = getState() || {};
                const selectedTime = state.selectedTime || null;

                updateState({
                    selectedAreaId: selectedAreaId
                });

                if (!selectedAreaId || !isOccupied || !selectedTime) {
                    renderAreaAvailabilityMessage('');
                    return;
                }

                renderAreaAvailabilityMessage(
                    'La zona de atencion seleccionada esta ocupada a las ' + selectedTime +
                    '. Elige otra zona o selecciona otra hora disponible.'
                );
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

                var slotDuration = parseInt(window.aa_slot_duration, 10) || 60;

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

                    var confirmEl = document.getElementById('aa-fastappointment-confirm');
                    var autoConfirm = !!(confirmEl && confirmEl.checked && confirmEl.value === 'confirmed');

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
                                        console.log('[FastAppointment] Cita confirmada en background');
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
                            window.ReservationService.sendConfirmation(datos).catch(function(emailError) {
                                console.warn('[FastAppointment] Error al enviar correo (no critico):', emailError);
                            });
                        }
                    }

                } catch (err) {
                    console.error('[FastAppointment] Error en flujo de submit', err);
                    alert('Error al agendar cita: ' + err.message);
                } finally {
                    isSubmitting = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Agendar cita';
                    }
                }
            };

            formEl.addEventListener('submit', handleFormSubmit);
        }

        /**
         * Reset pasos posteriores a Fecha: hora, servicio, staff, zona, confirmar.
         * Limpia UI y estado para que se vuelvan a elegir tras cambiar la fecha.
         */
        function resetStepsAfterDate() {
            const confirmCheckbox = document.getElementById('aa-fastappointment-confirm');

            invalidateStaffAvailabilityRequests();
            invalidateAreaAvailabilityRequests();

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
            populateServiceSelect([]);
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
        bindServiceSelection();
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
