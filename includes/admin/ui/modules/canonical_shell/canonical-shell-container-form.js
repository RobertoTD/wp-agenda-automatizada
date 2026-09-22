/**
 * Canonical Shell — create/update/delete de contenedor universal (SB1-5B1 / SB1-5B5 / SB1-5B6).
 */
(function () {
    'use strict';

    var cfg = window.AA_CANONICAL_SHELL_CONTAINER_FORM;
    if (!cfg || typeof cfg !== 'object') {
        return;
    }

    var ajaxUrl = typeof cfg.ajaxUrl === 'string' ? cfg.ajaxUrl : '';
    var createAction = typeof cfg.createAction === 'string' ? cfg.createAction : '';
    var createNonce = typeof cfg.createNonce === 'string' ? cfg.createNonce : '';
    var updateAction = typeof cfg.updateAction === 'string' ? cfg.updateAction : '';
    var updateNonce = typeof cfg.updateNonce === 'string' ? cfg.updateNonce : '';
    var deleteAction = typeof cfg.deleteAction === 'string' ? cfg.deleteAction : '';
    var deleteNonce = typeof cfg.deleteNonce === 'string' ? cfg.deleteNonce : '';
    var familyKey = typeof cfg.familyKey === 'string' ? cfg.familyKey : '';
    var listsScope = typeof cfg.listsScope === 'string' ? cfg.listsScope : '';
    var shellView = typeof cfg.shellView === 'string' ? cfg.shellView : 'containers';
    var page = (typeof cfg.page === 'number' && cfg.page > 1) ? cfg.page : null;
    var containersPage = (typeof cfg.containersPage === 'number' && cfg.containersPage > 1)
        ? cfg.containersPage
        : null;
    var availableFamilies = Array.isArray(cfg.availableFamilies) ? cfg.availableFamilies : [];
    var requireFamilySelect = cfg.requireFamilySelect === true;
    var maxTitleLength = typeof cfg.maxTitleLength === 'number' ? cfg.maxTitleLength : 200;
    var canCreateFromAll = listsScope === 'all' && availableFamilies.length > 0;
    var familyCapabilityOptions = (cfg.familyCapabilityOptions && typeof cfg.familyCapabilityOptions === 'object')
        ? cfg.familyCapabilityOptions
        : {};
    var editContainerCapabilities = (cfg.editContainerCapabilities && typeof cfg.editContainerCapabilities === 'object')
        ? cfg.editContainerCapabilities
        : null;
    var familySolutionOptions = (cfg.familySolutionOptions && typeof cfg.familySolutionOptions === 'object')
        ? cfg.familySolutionOptions : {};
    var editContainerSolutions = (cfg.editContainerSolutions && typeof cfg.editContainerSolutions === 'object')
        ? cfg.editContainerSolutions : null;

    if (!ajaxUrl || !createAction || !createNonce || !updateAction || !updateNonce
        || !deleteAction || !deleteNonce) {
        return;
    }
    // Familia filtrada (familyKey), select multi-familia, o Todas con ≥1 familia creable.
    if (!requireFamilySelect && !familyKey && !canCreateFromAll) {
        return;
    }

    var openCreateBtn = document.getElementById('aa-shell-open-create-btn');
    var modal = document.getElementById('aa-shell-container-modal');
    var backdrop = document.getElementById('aa-shell-container-modal-backdrop');
    var closeBtn = document.getElementById('aa-shell-container-modal-close-btn');
    var cancelBtn = document.getElementById('aa-shell-container-modal-cancel-btn');
    var form = document.getElementById('aa-shell-container-form');
    var modalTitle = document.getElementById('aa-shell-container-modal-title');
    var titleInput = document.getElementById('aa-shell-container-title');
    var detailsInput = document.getElementById('aa-shell-container-details');
    var titleError = document.getElementById('aa-shell-container-title-error');
    var statusEl = document.getElementById('aa-shell-container-status');
    var submitBtn = document.getElementById('aa-shell-container-submit-btn');
    var familyField = document.getElementById('aa-shell-container-family-field');
    var familySelect = document.getElementById('aa-shell-container-family');
    var familyError = document.getElementById('aa-shell-container-family-error');
    var capabilitiesMount = document.getElementById('aa-shell-container-capabilities');
    var capabilitiesStatus = document.getElementById('aa-shell-container-capabilities-status');
    var solutionsMount = document.getElementById('aa-shell-container-solutions');
    var solutionsStatus = document.getElementById('aa-shell-container-solutions-status');

    var deleteModal = document.getElementById('aa-shell-delete-container-modal');
    var deleteBackdrop = document.getElementById('aa-shell-delete-container-modal-backdrop');
    var deleteCloseBtn = document.getElementById('aa-shell-delete-container-modal-close-btn');
    var deleteCancelBtn = document.getElementById('aa-shell-delete-container-modal-cancel-btn');
    var deleteConfirmBtn = document.getElementById('aa-shell-delete-container-confirm-btn');
    var DELETE_RESUME_COOLDOWN_MS = 400;
    var deleteResumeAt = 0;
    var deleteReloadBtn = document.getElementById('aa-shell-delete-container-reload-btn');
    var deleteAbortBtn = document.getElementById('aa-shell-delete-container-abort-btn');
    var deleteTitleEl = document.getElementById('aa-shell-delete-container-title');
    var deleteStatusEl = document.getElementById('aa-shell-delete-container-status');

    if (!modal || !form || !titleInput || !submitBtn || !modalTitle) {
        return;
    }
    if (deleteModal && deleteModal.getAttribute('data-aa-shell-container-form') === 'bound') {
        return;
    }
    if (deleteModal) {
        deleteModal.setAttribute('data-aa-shell-container-form', 'bound');
    }

    var MODE_CREATE = 'create';
    var MODE_UPDATE = 'update';
    var mode = MODE_CREATE;
    var currentContainerId = null;
    var currentFamilyKey = familyKey;
    var inFlight = false;
    var previousFocus = null;
    var capabilitiesOperative = false;

    var deleteContainerId = null;
    var deleteFamilyKey = null;
    var deleteInFlight = false;
    var deleteBlocked = false;
    var deletePreviousFocus = null;
    var deleteRedirectUrl = null;
    var deleteConfirmDefaultLabel = deleteConfirmBtn ? deleteConfirmBtn.textContent : 'Eliminar lista';

    function appendReturnContext(body) {
        var views = cfg.capabilityViews || {};
        Object.keys(views).forEach(function (owner) {
            body.append('capability_views[' + owner + ']', views[owner]);
        });
        if (listsScope === 'all') {
            body.append('lists_scope', 'all');
        }
        if (page !== null) {
            body.append('page', String(page));
        }
        if (containersPage !== null) {
            body.append('containers_page', String(containersPage));
        }
        if (shellView === 'records' && mode === MODE_UPDATE) {
            body.append('return_view', 'records');
        }
    }

    function hasFamilyCapabilityRepertoire(nextFamilyKey) {
        if (!nextFamilyKey || typeof familyCapabilityOptions !== 'object' || familyCapabilityOptions === null) {
            return false;
        }
        if (!Object.prototype.hasOwnProperty.call(familyCapabilityOptions, nextFamilyKey)) {
            return false;
        }
        return Array.isArray(familyCapabilityOptions[nextFamilyKey]);
    }

    function optionsForFamily(nextFamilyKey) {
        if (!hasFamilyCapabilityRepertoire(nextFamilyKey)) {
            return [];
        }
        return familyCapabilityOptions[nextFamilyKey];
    }

    function clearCapabilitiesStatus() {
        if (!capabilitiesStatus) {
            return;
        }
        capabilitiesStatus.textContent = '';
        capabilitiesStatus.classList.add('hidden');
    }

    function setCapabilitiesUnavailable(message) {
        capabilitiesOperative = false;
        if (capabilitiesMount) {
            capabilitiesMount.innerHTML = '';
            capabilitiesMount.setAttribute('hidden', '');
            capabilitiesMount.setAttribute('aria-disabled', 'true');
        }
        if (!capabilitiesStatus) {
            return;
        }
        capabilitiesStatus.textContent = message
            || 'No se pudieron cargar los campos y funciones de esta lista.';
        capabilitiesStatus.classList.remove('hidden');
    }

    function renderCapabilityCheckboxes(nextFamilyKey, checkedKeys) {
        if (!capabilitiesMount) {
            capabilitiesOperative = false;
            return;
        }
        capabilitiesOperative = true;
        clearCapabilitiesStatus();
        capabilitiesMount.removeAttribute('hidden');
        capabilitiesMount.removeAttribute('aria-disabled');
        capabilitiesMount.innerHTML = '';

        var opts = optionsForFamily(nextFamilyKey);
        if (opts.length === 0) {
            return;
        }
        if (typeof document.createElement !== 'function') {
            capabilitiesOperative = false;
            return;
        }

        var checkedSet = null;
        if (Array.isArray(checkedKeys)) {
            checkedSet = {};
            var c;
            for (c = 0; c < checkedKeys.length; c++) {
                if (typeof checkedKeys[c] === 'string' && checkedKeys[c] !== '') {
                    checkedSet[checkedKeys[c]] = true;
                }
            }
        }

        var i;
        for (i = 0; i < opts.length; i++) {
            var opt = opts[i];
            if (!opt || typeof opt !== 'object') {
                continue;
            }
            var key = typeof opt.key === 'string' ? opt.key : '';
            if (key === '') {
                continue;
            }
            var labelText = typeof opt.label === 'string' && opt.label !== '' ? opt.label : key;
            var shouldCheck = checkedSet
                ? !!checkedSet[key]
                : opt.is_default === true;

            var row = document.createElement('label');
            row.className = 'flex items-center gap-2 text-sm text-gray-800';

            var input = document.createElement('input');
            input.type = 'checkbox';
            input.value = key;
            input.setAttribute('data-aa-capability-key', key);
            input.className = 'rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
            input.checked = shouldCheck;
            input.disabled = inFlight;

            var span = document.createElement('span');
            span.textContent = labelText;

            row.appendChild(input);
            row.appendChild(span);
            capabilitiesMount.appendChild(row);
        }
    }

    function applyCreateCapabilities(nextFamilyKey) {
        renderCapabilityCheckboxes(nextFamilyKey, null);
    }

    function applyUpdateCapabilities(nextFamilyKey, capabilitiesState) {
        var state = (capabilitiesState && typeof capabilitiesState === 'object')
            ? capabilitiesState
            : editContainerCapabilities;
        if (!state || state.status !== 'ok') {
            setCapabilitiesUnavailable();
            return;
        }
        // Ausencia del mapa ≠ repertorio [] legítimo: omitir selección en update.
        if (!hasFamilyCapabilityRepertoire(nextFamilyKey)) {
            setCapabilitiesUnavailable();
            return;
        }
        var active = Array.isArray(state.active) ? state.active : [];
        var opts = optionsForFamily(nextFamilyKey);
        var repertoire = {};
        var i;
        for (i = 0; i < opts.length; i++) {
            if (opts[i] && typeof opts[i].key === 'string' && opts[i].key !== '') {
                repertoire[opts[i].key] = true;
            }
        }
        var checked = [];
        for (i = 0; i < active.length; i++) {
            if (typeof active[i] === 'string' && repertoire[active[i]]) {
                checked.push(active[i]);
            }
        }
        renderCapabilityCheckboxes(nextFamilyKey, checked);
    }

    function collectCapabilitySelectionFields(body) {
        if (!capabilitiesOperative) {
            return;
        }
        var scope = [];
        var selected = [];
        if (capabilitiesMount && typeof capabilitiesMount.querySelectorAll === 'function') {
            var inputs = capabilitiesMount.querySelectorAll('input[data-aa-capability-key]');
            var i;
            for (i = 0; i < inputs.length; i++) {
                var input = inputs[i];
                var key = input.getAttribute('data-aa-capability-key');
                if (typeof key !== 'string' || key === '') {
                    continue;
                }
                scope.push(key);
                if (input.checked) {
                    selected.push(key);
                }
            }
        }
        body.append('capability_selection_scope', JSON.stringify(scope));
        body.append('capability_selection', JSON.stringify(selected));
    }

    function solutionOptionsForFamily(nextFamilyKey) {
        return familySolutionOptions && Array.isArray(familySolutionOptions[nextFamilyKey])
            ? familySolutionOptions[nextFamilyKey] : [];
    }

    function renderSolutionCheckboxes(nextFamilyKey, checkedKeys) {
        if (!solutionsMount) { return; }
        solutionsMount.innerHTML = '';
        if (solutionsStatus) { solutionsStatus.classList.add('hidden'); solutionsStatus.textContent = ''; }
        var opts = solutionOptionsForFamily(nextFamilyKey);
        var checked = {};
        if (Array.isArray(checkedKeys)) {
            for (var c = 0; c < checkedKeys.length; c++) { checked[checkedKeys[c]] = true; }
        }
        for (var i = 0; i < opts.length; i++) {
            var opt = opts[i] || {};
            if (typeof opt.key !== 'string' || opt.key === '') { continue; }
            var row = document.createElement('label');
            row.className = 'flex items-center gap-2 text-sm text-gray-800';
            var input = document.createElement('input');
            input.type = 'checkbox'; input.value = opt.key;
            input.setAttribute('data-aa-solution-key', opt.key);
            input.className = 'rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
            input.checked = Object.prototype.hasOwnProperty.call(checked, opt.key) ? true : opt.is_default === true;
            input.disabled = inFlight;
            var span = document.createElement('span'); span.textContent = opt.label || opt.key;
            row.appendChild(input); row.appendChild(span); solutionsMount.appendChild(row);
        }
    }

    function applyCreateSolutions(nextFamilyKey) { renderSolutionCheckboxes(nextFamilyKey, null); }
    function applyUpdateSolutions(nextFamilyKey, state) {
        var source = (state && typeof state === 'object') ? state : editContainerSolutions;
        if (source && source.status === 'unavailable') {
            if (solutionsStatus) { solutionsStatus.textContent = 'No se pudieron cargar las soluciones de esta lista.'; solutionsStatus.classList.remove('hidden'); }
            if (solutionsMount) { solutionsMount.innerHTML = ''; }
            return;
        }
        renderSolutionCheckboxes(nextFamilyKey, source && Array.isArray(source.active) ? source.active : []);
    }

    function collectSolutionSelectionFields(body) {
        if (!solutionsMount || typeof solutionsMount.querySelectorAll !== 'function') { return; }
        var scope = [], selected = [], inputs = solutionsMount.querySelectorAll('input[data-aa-solution-key]');
        for (var i = 0; i < inputs.length; i++) {
            var key = inputs[i].getAttribute('data-aa-solution-key');
            if (!key) { continue; }
            scope.push(key); if (inputs[i].checked) { selected.push(key); }
        }
        body.append('solution_selection_scope', JSON.stringify(scope));
        body.append('solution_selection', JSON.stringify(selected));
    }

    function setCapabilityInputsDisabled(disabled) {
        if (!capabilitiesMount || typeof capabilitiesMount.querySelectorAll !== 'function') {
            return;
        }
        var inputs = capabilitiesMount.querySelectorAll('input[data-aa-capability-key]');
        var i;
        for (i = 0; i < inputs.length; i++) {
            inputs[i].disabled = disabled;
        }
    }

    function setFamilyError(message) {
        if (!familyError) {
            return;
        }
        if (!message) {
            familyError.textContent = '';
            familyError.classList.add('hidden');
            if (familySelect) {
                familySelect.removeAttribute('aria-invalid');
                familySelect.removeAttribute('aria-describedby');
            }
            return;
        }
        familyError.textContent = message;
        familyError.classList.remove('hidden');
        if (familySelect) {
            familySelect.setAttribute('aria-invalid', 'true');
            familySelect.setAttribute('aria-describedby', 'aa-shell-container-family-error');
        }
    }

    function setFamilyFieldVisible(visible) {
        if (!familyField) {
            return;
        }
        if (visible) {
            familyField.classList.remove('hidden');
        } else {
            familyField.classList.add('hidden');
        }
        if (familySelect) {
            familySelect.disabled = !visible;
        }
    }

    function resolveInitialCreateFamilyKey() {
        // Única ubicación de la política de preselección al abrir creación.
        if (familyKey !== '') {
            return familyKey;
        }
        if (listsScope !== 'all' || availableFamilies.length === 0) {
            return '';
        }
        var i;
        for (i = 0; i < availableFamilies.length; i++) {
            var row = availableFamilies[i];
            if (row && row.family_key === 'archive') {
                return 'archive';
            }
        }
        if (availableFamilies.length === 1) {
            var only = availableFamilies[0];
            return only && typeof only.family_key === 'string' ? only.family_key : '';
        }
        return '';
    }

    function resolveCreateFamilyKey() {
        if (requireFamilySelect && familySelect) {
            return typeof familySelect.value === 'string' ? familySelect.value : '';
        }
        return currentFamilyKey || familyKey;
    }

    function setStatus(message, isError) {
        if (!statusEl) {
            return;
        }
        if (!message) {
            statusEl.textContent = '';
            statusEl.classList.add('hidden');
            statusEl.classList.remove('bg-red-50', 'text-red-700', 'bg-amber-50', 'text-amber-900');
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.remove('hidden');
        if (isError) {
            statusEl.classList.add('bg-red-50', 'text-red-700');
            statusEl.classList.remove('bg-amber-50', 'text-amber-900');
        } else {
            statusEl.classList.add('bg-amber-50', 'text-amber-900');
            statusEl.classList.remove('bg-red-50', 'text-red-700');
        }
    }

    function setTitleError(message) {
        if (!titleError) {
            return;
        }
        if (!message) {
            titleError.textContent = '';
            titleError.classList.add('hidden');
            titleInput.removeAttribute('aria-invalid');
            titleInput.removeAttribute('aria-describedby');
            return;
        }
        titleError.textContent = message;
        titleError.classList.remove('hidden');
        titleInput.setAttribute('aria-invalid', 'true');
        titleInput.setAttribute('aria-describedby', 'aa-shell-container-title-error');
    }

    function setBusy(busy) {
        inFlight = busy;
        submitBtn.disabled = busy;
        titleInput.disabled = busy;
        if (detailsInput) {
            detailsInput.disabled = busy;
        }
        if (familySelect && mode === MODE_CREATE && requireFamilySelect) {
            familySelect.disabled = busy;
        }
        setCapabilityInputsDisabled(busy);
        if (solutionsMount) {
            var solutionInputs = solutionsMount.querySelectorAll('input[data-aa-solution-key]');
            for (var s = 0; s < solutionInputs.length; s++) { solutionInputs[s].disabled = busy; }
        }
        if (cancelBtn) {
            cancelBtn.disabled = busy;
        }
        if (closeBtn) {
            closeBtn.disabled = busy;
        }
        if (busy) {
            modal.setAttribute('aria-busy', 'true');
            submitBtn.setAttribute('aria-busy', 'true');
        } else {
            modal.removeAttribute('aria-busy');
            submitBtn.removeAttribute('aria-busy');
        }
    }

    function applyModeChrome() {
        if (mode === MODE_UPDATE) {
            modalTitle.textContent = 'Editar lista';
            submitBtn.textContent = 'Guardar cambios';
            setFamilyFieldVisible(false);
        } else {
            modalTitle.textContent = 'Nueva lista';
            submitBtn.textContent = 'Crear lista';
            setFamilyFieldVisible(requireFamilySelect);
        }
    }

    function openModal(nextMode, containerId, titleValue, detailsValue, triggerEl, nextFamilyKey, capabilitiesState) {
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            return;
        }
        mode = nextMode === MODE_UPDATE ? MODE_UPDATE : MODE_CREATE;
        currentContainerId = (mode === MODE_UPDATE && containerId >= 1) ? containerId : null;
        if (mode === MODE_UPDATE) {
            currentFamilyKey = (typeof nextFamilyKey === 'string' && nextFamilyKey !== '')
                ? nextFamilyKey
                : familyKey;
            if (familySelect) {
                familySelect.value = '';
            }
        } else {
            currentFamilyKey = resolveInitialCreateFamilyKey();
            if (familySelect && requireFamilySelect) {
                familySelect.value = currentFamilyKey;
            } else if (familySelect) {
                familySelect.value = '';
            }
        }
        previousFocus = triggerEl || document.activeElement;
        setStatus('', false);
        setTitleError('');
        setFamilyError('');
        applyModeChrome();
        titleInput.value = typeof titleValue === 'string' ? titleValue : '';
        if (detailsInput) {
            detailsInput.value = typeof detailsValue === 'string' ? detailsValue : '';
        }
        if (mode === MODE_UPDATE) {
            applyUpdateCapabilities(currentFamilyKey, capabilitiesState || null);
            applyUpdateSolutions(currentFamilyKey, null);
        } else {
            applyCreateCapabilities(currentFamilyKey);
            applyCreateSolutions(currentFamilyKey);
        }
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        if (mode === MODE_CREATE && requireFamilySelect && familySelect) {
            familySelect.focus();
        } else {
            titleInput.focus();
        }
    }

    function closeModal(restoreFocus) {
        if (inFlight) {
            return;
        }
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        setStatus('', false);
        setTitleError('');
        setFamilyError('');
        mode = MODE_CREATE;
        currentContainerId = null;
        currentFamilyKey = familyKey;
        applyModeChrome();
        if (restoreFocus && previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        } else if (restoreFocus && openCreateBtn) {
            openCreateBtn.focus();
        }
    }

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function parseContainerPayload(raw) {
        if (typeof raw !== 'string' || raw === '') {
            return null;
        }
        var data = parseJsonSafe(raw);
        if (!data || typeof data !== 'object') {
            return null;
        }
        var id = typeof data.id === 'number' ? data.id : parseInt(data.id, 10);
        if (!(id >= 1)) {
            return null;
        }
        var payloadFamily = typeof data.family_key === 'string' ? data.family_key : '';
        if (payloadFamily === '' && familyKey !== '') {
            payloadFamily = familyKey;
        }
        if (payloadFamily === '') {
            return null;
        }
        var capabilities = null;
        if (data.capabilities && typeof data.capabilities === 'object') {
            capabilities = data.capabilities;
        }
        return {
            id: id,
            title: typeof data.title === 'string' ? data.title : '',
            details: typeof data.details === 'string' ? data.details : '',
            family_key: payloadFamily,
            capabilities: capabilities
        };
    }

    function clientValidate() {
        if (mode === MODE_CREATE && requireFamilySelect) {
            var selectedFamily = resolveCreateFamilyKey();
            if (selectedFamily === '') {
                setFamilyError('Selecciona un tipo de registro.');
                if (familySelect) {
                    familySelect.focus();
                }
                return false;
            }
        }
        var raw = titleInput.value || '';
        var trimmed = raw.replace(/^\s+|\s+$/g, '');
        if (trimmed === '') {
            setTitleError('El nombre de la lista no puede estar vacío.');
            titleInput.focus();
            return false;
        }
        if (trimmed.length > maxTitleLength) {
            setTitleError('El nombre de la lista no puede exceder los ' + maxTitleLength + ' caracteres.');
            titleInput.focus();
            return false;
        }
        return true;
    }

    function defaultErrorMessage() {
        return mode === MODE_UPDATE
            ? 'No se pudo actualizar la lista. Inténtalo de nuevo.'
            : 'No se pudo crear la lista. Inténtalo de nuevo.';
    }

    function uncertainMessage(message) {
        if (typeof message === 'string' && message !== '') {
            return message;
        }
        return mode === MODE_UPDATE
            ? 'No fue posible confirmar si los cambios se guardaron. Revisa el listado antes de intentarlo nuevamente.'
            : 'No fue posible confirmar si la lista se creó. Revisa el listado antes de intentarlo nuevamente.';
    }

    function submitForm() {
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        setStatus('', false);
        setTitleError('');
        setFamilyError('');
        if (!clientValidate()) {
            return;
        }
        if (mode === MODE_UPDATE && !(currentContainerId >= 1)) {
            setStatus(defaultErrorMessage(), true);
            return;
        }

        var action = mode === MODE_UPDATE ? updateAction : createAction;
        var nonce = mode === MODE_UPDATE ? updateNonce : createNonce;

        setBusy(true);

        var requestFamilyKey = mode === MODE_UPDATE
            ? currentFamilyKey
            : resolveCreateFamilyKey();
        if (!requestFamilyKey) {
            setBusy(false);
            setStatus(defaultErrorMessage(), true);
            return;
        }

        var body = new FormData();
        body.append('action', action);
        body.append('nonce', nonce);
        body.append('family_key', requestFamilyKey);
        if (mode === MODE_UPDATE) {
            body.append('container_id', String(currentContainerId));
        }
        body.append('title', titleInput.value);
        body.append('details', detailsInput ? detailsInput.value : '');
        appendReturnContext(body);
        collectCapabilitySelectionFields(body);
        collectSolutionSelectionFields(body);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.text().then(function (text) {
                return { httpStatus: response.status, payload: parseJsonSafe(text) };
            });
        }).then(function (result) {
            var payload = result.payload;
            if (!payload || typeof payload !== 'object') {
                setBusy(false);
                setStatus(defaultErrorMessage(), true);
                return;
            }

            if (payload.success === true && payload.data && payload.data.status === 'confirmed') {
                var redirect = payload.data.redirect_url;
                if (typeof redirect === 'string' && redirect !== '') {
                    window.location.assign(redirect);
                    return;
                }
                setBusy(false);
                setStatus(
                    mode === MODE_UPDATE
                        ? 'Los cambios se guardaron, pero no se pudo redirigir. Recarga el listado.'
                        : 'La lista se creó, pero no se pudo redirigir. Recarga el listado.',
                    true
                );
                return;
            }

            setBusy(false);
            var err = payload.data || {};
            var code = typeof err.code === 'string' ? err.code : '';
            var message = typeof err.message === 'string' ? err.message : '';

            if (code === 'uncertain') {
                setStatus(uncertainMessage(message), false);
                return;
            }
            if (code === 'invalid_title' || code === 'title_too_long') {
                setTitleError(message || 'Revisa el nombre de la lista.');
                titleInput.focus();
                return;
            }
            setStatus(message || defaultErrorMessage(), true);
        }).catch(function () {
            setBusy(false);
            setStatus(defaultErrorMessage(), true);
        });
    }

    function setDeleteStatus(message, isError) {
        if (!deleteStatusEl) {
            return;
        }
        if (!message) {
            deleteStatusEl.textContent = '';
            deleteStatusEl.classList.add('hidden');
            deleteStatusEl.classList.remove('bg-red-50', 'text-red-700', 'bg-amber-50', 'text-amber-900');
            return;
        }
        deleteStatusEl.textContent = message;
        deleteStatusEl.classList.remove('hidden');
        if (isError) {
            deleteStatusEl.classList.add('bg-red-50', 'text-red-700');
            deleteStatusEl.classList.remove('bg-amber-50', 'text-amber-900');
        } else {
            deleteStatusEl.classList.add('bg-amber-50', 'text-amber-900');
            deleteStatusEl.classList.remove('bg-red-50', 'text-red-700');
        }
    }

    function setDeleteBusy(busy) {
        deleteInFlight = busy;
        if (deleteConfirmBtn) {
            deleteConfirmBtn.disabled = busy || deleteBlocked;
            if (busy) {
                deleteConfirmBtn.setAttribute('aria-busy', 'true');
            } else {
                deleteConfirmBtn.removeAttribute('aria-busy');
            }
        }
        if (deleteAbortBtn) {
            deleteAbortBtn.disabled = busy || deleteBlocked;
        }
        if (deleteCancelBtn) {
            deleteCancelBtn.disabled = busy;
        }
        if (deleteCloseBtn) {
            deleteCloseBtn.disabled = busy;
        }
        if (deleteModal) {
            if (busy) {
                deleteModal.setAttribute('aria-busy', 'true');
            } else {
                deleteModal.removeAttribute('aria-busy');
            }
        }
    }

    function showDeleteReload(show) {
        if (!deleteReloadBtn) {
            return;
        }
        if (show) {
            deleteReloadBtn.classList.remove('hidden');
        } else {
            deleteReloadBtn.classList.add('hidden');
        }
    }

    function showDeleteAbort(show) {
        if (!deleteAbortBtn) {
            return;
        }
        if (show) {
            deleteAbortBtn.classList.remove('hidden');
        } else {
            deleteAbortBtn.classList.add('hidden');
        }
    }

    function setDeleteConfirmLabel(label) {
        if (!deleteConfirmBtn) {
            return;
        }
        deleteConfirmBtn.textContent = label || deleteConfirmDefaultLabel;
    }

    function openDeleteModal(containerId, titleValue, triggerEl, nextFamilyKey) {
        if (!deleteModal || !deleteConfirmBtn || !deleteTitleEl) {
            return;
        }
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        if (!modal.classList.contains('hidden')) {
            return;
        }
        if (!(containerId >= 1)) {
            return;
        }
        var fk = (typeof nextFamilyKey === 'string' && nextFamilyKey !== '')
            ? nextFamilyKey
            : familyKey;
        if (!fk) {
            return;
        }

        deleteContainerId = containerId;
        deleteFamilyKey = fk;
        deletePreviousFocus = triggerEl || document.activeElement;
        deleteRedirectUrl = null;
        deleteBlocked = false;
        deleteResumeAt = 0;
        setDeleteStatus('', false);
        showDeleteReload(false);
        showDeleteAbort(false);
        setDeleteConfirmLabel(deleteConfirmDefaultLabel);
        deleteTitleEl.textContent = typeof titleValue === 'string' ? titleValue : '';
        if (deleteConfirmBtn) {
            deleteConfirmBtn.disabled = false;
        }
        deleteModal.classList.remove('hidden');
        deleteModal.setAttribute('aria-hidden', 'false');
        if (deleteCancelBtn) {
            deleteCancelBtn.focus();
        }
    }

    function closeDeleteModal(restoreFocus) {
        if (!deleteModal) {
            return;
        }
        if (deleteInFlight) {
            return;
        }
        if (deleteBlocked) {
            return;
        }
        deleteModal.classList.add('hidden');
        deleteModal.setAttribute('aria-hidden', 'true');
        setDeleteStatus('', false);
        showDeleteReload(false);
        showDeleteAbort(false);
        setDeleteConfirmLabel(deleteConfirmDefaultLabel);
        deleteContainerId = null;
        deleteFamilyKey = null;
        deleteRedirectUrl = null;
        if (deleteTitleEl) {
            deleteTitleEl.textContent = '';
        }
        if (restoreFocus && deletePreviousFocus && typeof deletePreviousFocus.focus === 'function') {
            deletePreviousFocus.focus();
        }
    }

    function armDeleteResumeCooldown() {
        deleteResumeAt = Date.now() + DELETE_RESUME_COOLDOWN_MS;
    }

    function submitDelete(retireAction) {
        if (!deleteModal || deleteInFlight || deleteBlocked) {
            return;
        }
        if (retireAction !== 'cancel' && deleteResumeAt > 0 && Date.now() < deleteResumeAt) {
            return;
        }
        if (!(deleteContainerId >= 1) || !deleteFamilyKey) {
            setDeleteStatus('No se pudo eliminar la lista. Inténtalo de nuevo.', true);
            return;
        }

        setDeleteStatus('', false);
        showDeleteReload(false);
        setDeleteBusy(true);

        var body = new FormData();
        body.append('action', deleteAction);
        body.append('nonce', deleteNonce);
        body.append('family_key', deleteFamilyKey);
        body.append('container_id', String(deleteContainerId));
        if (retireAction === 'cancel') {
            body.append('retire_action', 'cancel');
        }
        appendReturnContext(body);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.text().then(function (text) {
                return { httpStatus: response.status, payload: parseJsonSafe(text) };
            });
        }).then(function (result) {
            var payload = result.payload;
            if (!payload || typeof payload !== 'object') {
                setDeleteBusy(false);
                setDeleteStatus('No se pudo eliminar la lista. Inténtalo de nuevo.', true);
                return;
            }

            if (payload.success === true && payload.data && payload.data.status === 'confirmed') {
                var redirect = payload.data.redirect_url;
                if (typeof redirect === 'string' && redirect !== '') {
                    window.location.assign(redirect);
                    return;
                }
                setDeleteBusy(false);
                setDeleteStatus('La lista se eliminó, pero no se pudo redirigir. Recarga el listado.', true);
                return;
            }

            if (payload.success === true && payload.data && payload.data.status === 'cancelled') {
                setDeleteBusy(false);
                closeDeleteModal(true);
                return;
            }

            var err = payload.data || {};
            var code = typeof err.code === 'string' ? err.code : '';
            var message = typeof err.message === 'string' ? err.message : '';
            var errRedirect = typeof err.redirect_url === 'string' ? err.redirect_url : '';

            if (code === 'uncertain') {
                deleteBlocked = true;
                deleteRedirectUrl = errRedirect !== '' ? errRedirect : null;
                setDeleteBusy(false);
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = true;
                }
                showDeleteAbort(false);
                setDeleteStatus(
                    message || 'No fue posible confirmar si la lista se eliminó. Recarga el listado para verificarlo antes de intentarlo nuevamente.',
                    false
                );
                showDeleteReload(true);
                return;
            }

            if (code === 'incomplete') {
                setDeleteBusy(false);
                setDeleteConfirmLabel('Continuar');
                showDeleteAbort(false);
                armDeleteResumeCooldown();
                setDeleteStatus(
                    message || 'La eliminación no terminó. Pulsa Continuar para seguir.',
                    false
                );
                return;
            }

            if (code === 'conflict') {
                setDeleteBusy(false);
                setDeleteConfirmLabel('Reintentar');
                showDeleteAbort(err.can_cancel !== false);
                setDeleteStatus(
                    message || 'No se pudo preparar la eliminación. Puedes cancelarla para desbloquear la lista, o reintentar.',
                    false
                );
                return;
            }

            if (code === 'cancel_rejected') {
                setDeleteBusy(false);
                setDeleteConfirmLabel('Continuar');
                showDeleteAbort(false);
                armDeleteResumeCooldown();
                setDeleteStatus(
                    message || 'Ya hubo comunicación remota. No se puede cancelar. Pulsa Continuar para recuperar el protocolo.',
                    false
                );
                return;
            }

            if (code === 'intervention_required') {
                deleteRedirectUrl = errRedirect !== '' ? errRedirect : null;
                setDeleteBusy(false);
                setDeleteConfirmLabel(deleteConfirmDefaultLabel);
                showDeleteAbort(false);
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = true;
                }
                setDeleteStatus(
                    message || 'Esta eliminación no puede continuar sola. Recarga el listado. Si el problema persiste, hace falta una revisión.',
                    false
                );
                showDeleteReload(true);
                return;
            }

            setDeleteBusy(false);
            setDeleteStatus(message || 'No se pudo eliminar la lista. Inténtalo de nuevo.', true);
        }).catch(function () {
            setDeleteBusy(false);
            setDeleteStatus('No se pudo eliminar la lista. Inténtalo de nuevo.', true);
        });
    }

    function reloadAfterUncertain() {
        if (typeof deleteRedirectUrl === 'string' && deleteRedirectUrl !== '') {
            window.location.assign(deleteRedirectUrl);
            return;
        }
        window.location.reload();
    }

    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', function () {
            openModal(MODE_CREATE, null, '', '', openCreateBtn);
        });
    }

    if (familySelect) {
        familySelect.addEventListener('change', function () {
            if (mode !== MODE_CREATE) {
                return;
            }
            currentFamilyKey = typeof familySelect.value === 'string' ? familySelect.value : '';
            applyCreateCapabilities(currentFamilyKey);
            applyCreateSolutions(currentFamilyKey);
        });
    }

    var editButtons = document.querySelectorAll('.aa-shell-edit-container-btn');
    for (var i = 0; i < editButtons.length; i++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                var container = parseContainerPayload(btn.getAttribute('data-aa-container'));
                if (!container) {
                    return;
                }
                openModal(
                    MODE_UPDATE,
                    container.id,
                    container.title,
                    container.details,
                    btn,
                    container.family_key,
                    container.capabilities
                );
            });
        })(editButtons[i]);
    }

    var deleteButtons = document.querySelectorAll('.aa-shell-delete-container-btn');
    for (var d = 0; d < deleteButtons.length; d++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                var container = parseContainerPayload(btn.getAttribute('data-aa-container'));
                if (!container) {
                    return;
                }
                openDeleteModal(container.id, container.title, btn, container.family_key);
            });
        })(deleteButtons[d]);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeModal(true);
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            closeModal(true);
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            closeModal(true);
        });
    }

    if (deleteCloseBtn) {
        deleteCloseBtn.addEventListener('click', function () {
            closeDeleteModal(true);
        });
    }
    if (deleteCancelBtn) {
        deleteCancelBtn.addEventListener('click', function () {
            closeDeleteModal(true);
        });
    }
    if (deleteBackdrop) {
        deleteBackdrop.addEventListener('click', function () {
            closeDeleteModal(true);
        });
    }
    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.repeat) {
                e.preventDefault();
            }
        });
        deleteConfirmBtn.addEventListener('click', function (e) {
            if (e && e.repeat) {
                return;
            }
            submitDelete();
        });
    }
    if (deleteAbortBtn) {
        deleteAbortBtn.addEventListener('click', function () {
            submitDelete('cancel');
        });
    }
    if (deleteReloadBtn) {
        deleteReloadBtn.addEventListener('click', function () {
            reloadAfterUncertain();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            if (!deleteBlocked) {
                closeDeleteModal(true);
            }
            return;
        }
        if (modal && !modal.classList.contains('hidden')) {
            closeModal(true);
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitForm();
    });
})();
