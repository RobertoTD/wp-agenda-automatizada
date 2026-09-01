/**
 * Finance Record Edit Module — Controlador de edición de registros financieros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical\Finance
 */
(function () {
    'use strict';

    var EDIT_STATES = {
        IDLE: 'IDLE',
        LOADING_SOURCE: 'LOADING_SOURCE',
        EDITING: 'EDITING',
        FIELD_REJECTED: 'FIELD_REJECTED',
        SUBMITTING: 'SUBMITTING',
        BLOCKED_REJECTED: 'BLOCKED_REJECTED',
        UNCERTAIN: 'UNCERTAIN'
    };

    var CORREGIBLES = {
        missing_title: 'title',
        invalid_title: 'title',
        title_too_long: 'title',
        invalid_details: 'details',
        details_too_long: 'details',
        invalid_amount: 'amount',
        amount_too_many_decimals: 'amount',
        amount_out_of_range: 'amount'
    };

    var CONTRACT_BLOCKED = [
        'missing_details',
        'missing_amount'
    ];

    var BLOQUEANTES = [
        'bad_nonce',
        'unauthorized',
        'forbidden',
        'invalid_variant_key',
        'unknown_variant',
        'invalid_record_id',
        'invalid_container_id'
    ];

    var UNCERTAIN_CODES = [
        'persistence_failed',
        'canonical_unavailable'
    ];

    function createController(options) {
        var cfg = options.cfg;
        var elements = options.elements || {};
        var isDetailActive = typeof options.isDetailActive === 'function'
            ? options.isDetailActive
            : function () { return false; };
        var isEditAllowed = typeof options.isEditAllowed === 'function'
            ? options.isEditAllowed
            : function () { return true; };
        var onFlowStateChange = typeof options.onFlowStateChange === 'function'
            ? options.onFlowStateChange
            : function () {};
        var onGetSourceFailed = typeof options.onGetSourceFailed === 'function'
            ? options.onGetSourceFailed
            : function () {};
        var onRefreshRequested = typeof options.onRefreshRequested === 'function'
            ? options.onRefreshRequested
            : function () {};
        var onUncertainReviewStart = typeof options.onUncertainReviewStart === 'function'
            ? options.onUncertainReviewStart
            : function () { return null; };
        var onReviewGetResolved = typeof options.onReviewGetResolved === 'function'
            ? options.onReviewGetResolved
            : function () {};
        var onReviewGetUncertain = typeof options.onReviewGetUncertain === 'function'
            ? options.onReviewGetUncertain
            : function () {};
        var onFocusDetailStatus = typeof options.onFocusDetailStatus === 'function'
            ? options.onFocusDetailStatus
            : function () {};
        var onContainerNotFoundEdit = typeof options.onContainerNotFoundEdit === 'function'
            ? options.onContainerNotFoundEdit
            : function () {};

        var modalEl = elements.modal || null;
        var backdropEl = elements.backdrop || null;
        var closeBtnEl = elements.closeBtn || null;
        var formEl = elements.form || null;
        var modalErrorEl = elements.modalError || null;
        var titleInput = elements.titleInput || null;
        var titleErrorEl = elements.titleError || null;
        var detailsInput = elements.detailsInput || null;
        var detailsErrorEl = elements.detailsError || null;
        var amountInput = elements.amountInput || null;
        var amountErrorEl = elements.amountError || null;
        var standardActionsEl = elements.standardActions || null;
        var cancelBtn = elements.cancelBtn || null;
        var submitBtn = elements.submitBtn || null;
        var uncertainActionsEl = elements.uncertainActions || null;
        var uncertainCloseBtn = elements.uncertainCloseBtn || null;
        var blockedActionsEl = elements.blockedActions || null;
        var blockedCloseBtn = elements.blockedCloseBtn || null;

        var ajaxUrl = cfg.ajaxUrl;
        var nonce = cfg.nonce;
        var familyKey = cfg.familyKey;
        var variantKey = cfg.variantKey;
        var getRecordAction = cfg.actions && cfg.actions.getRecord;
        var updateRecordAction = cfg.actions && cfg.actions.updateRecord;

        var modalState = EDIT_STATES.IDLE;
        var sourceSnapshot = null;
        var submissionSnapshot = null;
        var originEditButton = null;
        var activeReviewToken = null;
        var activeReviewContext = null;
        var activeRecordId = null;
        var activeContainerId = null;
        var activeSourcePage = null;

        var sourceAbortController = null;
        var updateAbortController = null;
        var reviewAbortController = null;
        var sourceTimeoutId = null;
        var updateTimeoutId = null;
        var sourceRequestSeq = 0;
        var updateRequestSeq = 0;
        var reviewRequestSeq = 0;
        var focusTimeoutId = null;

        function clearFocusTimer() {
            if (focusTimeoutId !== null) {
                clearTimeout(focusTimeoutId);
                focusTimeoutId = null;
            }
        }

        function scheduleTitleFocus() {
            clearFocusTimer();
            focusTimeoutId = setTimeout(function () {
                focusTimeoutId = null;
                if (!modalEl || modalEl.classList.contains('hidden')) {
                    return;
                }
                if (
                    modalState !== EDIT_STATES.EDITING &&
                    modalState !== EDIT_STATES.FIELD_REJECTED
                ) {
                    return;
                }
                if (titleInput) {
                    titleInput.focus();
                }
            }, 50);
        }

        function isElementVisible(el) {
            if (!el || el.hidden || el.getAttribute('aria-hidden') === 'true' || el.tabIndex === -1) {
                return false;
            }
            var current = el;
            while (current && current !== document.body) {
                if (current.hidden || (current.classList && current.classList.contains('hidden')) || current.getAttribute('aria-hidden') === 'true') {
                    return false;
                }
                current = current.parentElement;
            }
            return true;
        }

        function getVisibleFocusableElements(container) {
            if (!container) {
                return [];
            }
            var raw = container.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
            var visible = [];
            for (var i = 0; i < raw.length; i++) {
                if (isElementVisible(raw[i])) {
                    visible.push(raw[i]);
                }
            }
            return visible;
        }

        function handleFocusTrap(e) {
            if (!modalEl || modalEl.classList.contains('hidden') || e.key !== 'Tab') {
                return;
            }
            var focusables = getVisibleFocusableElements(modalEl);
            if (focusables.length === 0) {
                e.preventDefault();
                return;
            }
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first || !modalEl.contains(document.activeElement)) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last || !modalEl.contains(document.activeElement)) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }

        document.addEventListener('keydown', handleFocusTrap);

        function setBotoneraState(type) {
            if (standardActionsEl) {
                standardActionsEl.classList.add('hidden');
            }
            if (uncertainActionsEl) {
                uncertainActionsEl.classList.add('hidden');
            }
            if (blockedActionsEl) {
                blockedActionsEl.classList.add('hidden');
            }
            if (type === 'standard' && standardActionsEl) {
                standardActionsEl.classList.remove('hidden');
            } else if (type === 'uncertain' && uncertainActionsEl) {
                uncertainActionsEl.classList.remove('hidden');
            } else if (type === 'blocked' && blockedActionsEl) {
                blockedActionsEl.classList.remove('hidden');
            }
        }

        function validateRecordDto(record, expectedRecordId, expectedContainerId) {
            if (!record || typeof record !== 'object' || Array.isArray(record)) {
                return false;
            }
            if (!Number.isInteger(record.id) || record.id !== expectedRecordId) {
                return false;
            }
            if (!Number.isInteger(record.container_id) || record.container_id !== expectedContainerId) {
                return false;
            }
            if (record.family_key !== familyKey || record.variant_key !== variantKey) {
                return false;
            }
            if (typeof record.title !== 'string') {
                return false;
            }
            if (record.details !== null && typeof record.details !== 'string') {
                return false;
            }
            if (record.amount !== null && typeof record.amount !== 'string') {
                return false;
            }
            if (typeof record.created_at !== 'string') {
                return false;
            }
            return true;
        }

        function validateGetRecordSuccess(json, recordId, containerId) {
            if (!json || typeof json !== 'object' || json.success !== true) {
                return false;
            }
            var data = json.data;
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return false;
            }
            return validateRecordDto(data.record, recordId, containerId);
        }

        function validateUpdateSuccess(json, snapshot) {
            if (!json || typeof json !== 'object' || json.success !== true) {
                return false;
            }
            var data = json.data;
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return false;
            }
            return validateRecordDto(data.record, snapshot.recordId, snapshot.containerId);
        }

        function clearFieldErrors() {
            if (modalErrorEl) {
                modalErrorEl.textContent = '';
                modalErrorEl.classList.add('hidden');
            }
            if (titleErrorEl) {
                titleErrorEl.textContent = '';
                titleErrorEl.classList.add('hidden');
            }
            if (detailsErrorEl) {
                detailsErrorEl.textContent = '';
                detailsErrorEl.classList.add('hidden');
            }
            if (amountErrorEl) {
                amountErrorEl.textContent = '';
                amountErrorEl.classList.add('hidden');
            }
            if (titleInput) {
                titleInput.removeAttribute('aria-invalid');
                titleInput.removeAttribute('aria-describedby');
            }
            if (detailsInput) {
                detailsInput.removeAttribute('aria-invalid');
                detailsInput.removeAttribute('aria-describedby');
            }
            if (amountInput) {
                amountInput.removeAttribute('aria-invalid');
                amountInput.removeAttribute('aria-describedby');
            }
        }

        function resetFormInputs() {
            if (titleInput) {
                titleInput.value = '';
                titleInput.readOnly = false;
            }
            if (detailsInput) {
                detailsInput.value = '';
                detailsInput.readOnly = false;
            }
            if (amountInput) {
                amountInput.value = '';
                amountInput.readOnly = false;
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Guardar cambios';
            }
            if (cancelBtn) {
                cancelBtn.disabled = false;
            }
            if (closeBtnEl) {
                closeBtnEl.disabled = false;
            }
        }

        function hideModal() {
            if (!modalEl) {
                return;
            }
            modalEl.classList.add('hidden');
            modalEl.setAttribute('aria-hidden', 'true');
        }

        function showModal() {
            if (!modalEl) {
                return;
            }
            modalEl.classList.remove('hidden');
            modalEl.setAttribute('aria-hidden', 'false');
        }

        function cleanupSourceRequest() {
            if (sourceTimeoutId !== null) {
                clearTimeout(sourceTimeoutId);
                sourceTimeoutId = null;
            }
            if (sourceAbortController) {
                sourceAbortController.abort();
                sourceAbortController = null;
            }
        }

        function cleanupUpdateRequest() {
            if (updateTimeoutId !== null) {
                clearTimeout(updateTimeoutId);
                updateTimeoutId = null;
            }
            if (updateAbortController) {
                updateAbortController.abort();
                updateAbortController = null;
            }
        }

        function cleanupReviewRequest() {
            if (reviewAbortController) {
                reviewAbortController.abort();
                reviewAbortController = null;
            }
        }

        function clearSnapshots() {
            sourceSnapshot = null;
            submissionSnapshot = null;
        }

        function transitionToIdle(releaseLock) {
            modalState = EDIT_STATES.IDLE;
            clearSnapshots();
            originEditButton = null;
            activeReviewToken = null;
            activeReviewContext = null;
            activeRecordId = null;
            activeContainerId = null;
            activeSourcePage = null;
            if (releaseLock !== false) {
                onFlowStateChange({ phase: 'idle' });
            }
        }

        function restoreOriginFocus() {
            var originConnected = originEditButton && (
                (typeof document.contains === 'function' && document.contains(originEditButton)) ||
                originEditButton.parentElement !== null ||
                originEditButton.parentNode !== null
            );
            if (originConnected && !originEditButton.disabled) {
                originEditButton.focus();
                return;
            }
            onFocusDetailStatus();
        }

        function populateDraftFromSource() {
            if (!sourceSnapshot) {
                return;
            }
            if (titleInput) {
                titleInput.value = sourceSnapshot.title;
            }
            if (detailsInput) {
                detailsInput.value = sourceSnapshot.details;
            }
            if (amountInput) {
                amountInput.value = sourceSnapshot.amount;
            }
        }

        function closeModalInternal(cleanAll) {
            clearFocusTimer();
            cleanupSourceRequest();
            cleanupUpdateRequest();

            if (modalState === EDIT_STATES.SUBMITTING || modalState === EDIT_STATES.UNCERTAIN) {
                return;
            }

            hideModal();
            clearFieldErrors();
            resetFormInputs();
            setBotoneraState('standard');

            if (cleanAll) {
                transitionToIdle(true);
                restoreOriginFocus();
            }
        }

        function beginEdit(recordId, containerId, sourcePage, originButton) {
            if (!modalEl || !getRecordAction || !updateRecordAction) {
                return;
            }
            if (!isDetailActive() || !isEditAllowed()) {
                return;
            }
            if (modalState !== EDIT_STATES.IDLE) {
                return;
            }
            if (!Number.isInteger(recordId) || recordId < 1) {
                return;
            }
            if (!Number.isInteger(containerId) || containerId < 1) {
                return;
            }
            if (!Number.isInteger(sourcePage) || sourcePage < 1) {
                return;
            }

            originEditButton = originButton || null;
            activeRecordId = recordId;
            activeContainerId = containerId;
            activeSourcePage = sourcePage;
            modalState = EDIT_STATES.LOADING_SOURCE;
            onFlowStateChange({ phase: 'edit_active' });

            cleanupSourceRequest();
            var currentSeq = ++sourceRequestSeq;
            var isSettled = false;

            sourceAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            sourceTimeoutId = setTimeout(function () {
                if (isSettled || currentSeq !== sourceRequestSeq) {
                    return;
                }
                isSettled = true;
                if (sourceAbortController) {
                    sourceAbortController.abort();
                }
                modalState = EDIT_STATES.IDLE;
                onGetSourceFailed({
                    reason: 'recoverable',
                    recordId: recordId,
                    containerId: containerId,
                    sourcePage: sourcePage,
                    message: 'No pudimos cargar la entrada para editar. Intenta de nuevo.'
                });
                onFlowStateChange({ phase: 'idle' });
            }, 15000);

            var formData = new FormData();
            formData.append('action', getRecordAction);
            formData.append('_wpnonce', nonce);
            formData.append('record_id', String(recordId));
            formData.append('container_id', String(containerId));
            formData.append('variant_key', variantKey);

            var fetchOptions = {
                method: 'POST',
                body: formData
            };
            if (sourceAbortController) {
                fetchOptions.signal = sourceAbortController.signal;
            }

            fetch(ajaxUrl, fetchOptions)
                .then(function (res) {
                    return res.json().then(function (json) {
                        return { ok: res.ok, status: res.status, json: json };
                    }).catch(function () {
                        return { ok: res.ok, status: res.status, json: null };
                    });
                })
                .then(function (response) {
                    if (isSettled || currentSeq !== sourceRequestSeq) {
                        return;
                    }
                    isSettled = true;
                    if (sourceTimeoutId !== null) {
                        clearTimeout(sourceTimeoutId);
                        sourceTimeoutId = null;
                    }

                    if (response.json && response.json.success === false && response.json.data) {
                        var errCode = response.json.data.code;
                        var errMessage = typeof response.json.data.message === 'string'
                            ? response.json.data.message
                            : '';

                        if (errCode === 'record_not_found') {
                            modalState = EDIT_STATES.IDLE;
                            onGetSourceFailed({
                                reason: 'record_not_found',
                                recordId: recordId,
                                containerId: containerId,
                                sourcePage: sourcePage,
                                message: 'La entrada ya no está disponible. Actualizamos la lista.'
                            });
                            onFlowStateChange({ phase: 'idle' });
                            return;
                        }

                        if (errCode === 'container_not_found') {
                            modalState = EDIT_STATES.IDLE;
                            onContainerNotFoundEdit(containerId);
                            onFlowStateChange({ phase: 'idle' });
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            modalState = EDIT_STATES.IDLE;
                            onGetSourceFailed({
                                reason: 'blocked',
                                recordId: recordId,
                                containerId: containerId,
                                sourcePage: sourcePage,
                                message: errMessage || 'La edición no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.'
                            });
                            onFlowStateChange({ phase: 'idle' });
                            return;
                        }
                    }

                    if (validateGetRecordSuccess(response.json, recordId, containerId)) {
                        var record = response.json.data.record;
                        sourceSnapshot = {
                            recordId: record.id,
                            containerId: record.container_id,
                            sourcePage: sourcePage,
                            variantKey: variantKey,
                            title: record.title,
                            details: record.details === null ? '' : record.details,
                            amount: record.amount === null ? '' : record.amount
                        };
                        clearFieldErrors();
                        resetFormInputs();
                        populateDraftFromSource();
                        setBotoneraState('standard');
                        modalState = EDIT_STATES.EDITING;
                        showModal();
                        scheduleTitleFocus();
                        return;
                    }

                    modalState = EDIT_STATES.IDLE;
                    onGetSourceFailed({
                        reason: 'recoverable',
                        recordId: recordId,
                        containerId: containerId,
                        sourcePage: sourcePage,
                        message: 'No pudimos cargar la entrada para editar. Intenta de nuevo.'
                    });
                    onFlowStateChange({ phase: 'idle' });
                })
                .catch(function (err) {
                    if (isSettled || currentSeq !== sourceRequestSeq) {
                        return;
                    }
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    isSettled = true;
                    if (sourceTimeoutId !== null) {
                        clearTimeout(sourceTimeoutId);
                        sourceTimeoutId = null;
                    }
                    modalState = EDIT_STATES.IDLE;
                    onGetSourceFailed({
                        reason: 'recoverable',
                        recordId: recordId,
                        containerId: containerId,
                        sourcePage: sourcePage,
                        message: 'No pudimos cargar la entrada para editar. Intenta de nuevo.'
                    });
                    onFlowStateChange({ phase: 'idle' });
                });
        }

        function responseMessageFromCode(code) {
            var messages = {
                missing_title: 'El título es obligatorio.',
                invalid_title: 'El título no es válido.',
                title_too_long: 'El título es demasiado largo.',
                invalid_details: 'Los detalles no son válidos.',
                details_too_long: 'Los detalles son demasiado largos.',
                invalid_amount: 'El importe no es válido.',
                amount_too_many_decimals: 'El importe tiene demasiados decimales.',
                amount_out_of_range: 'El importe está fuera de rango.',
                missing_details: 'Error interno: faltan los detalles en la solicitud. Recarga la página.',
                missing_amount: 'Error interno: falta el importe en la solicitud. Recarga la página.'
            };
            return messages[code] || '';
        }

        function applyFieldError(code) {
            modalState = EDIT_STATES.FIELD_REJECTED;
            submissionSnapshot = null;
            setBotoneraState('standard');

            if (titleInput) {
                titleInput.readOnly = false;
            }
            if (detailsInput) {
                detailsInput.readOnly = false;
            }
            if (amountInput) {
                amountInput.readOnly = false;
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Guardar cambios';
            }
            if (cancelBtn) {
                cancelBtn.disabled = false;
            }
            if (closeBtnEl) {
                closeBtnEl.disabled = false;
            }

            var field = CORREGIBLES[code];
            var message = responseMessageFromCode(code) || 'Revisa el valor ingresado.';

            if (field === 'title' && titleErrorEl && titleInput) {
                titleErrorEl.textContent = message;
                titleErrorEl.classList.remove('hidden');
                titleInput.setAttribute('aria-invalid', 'true');
                titleInput.setAttribute('aria-describedby', titleErrorEl.id || 'aa-finance-record-edit-title-error');
                titleInput.focus();
            } else if (field === 'details' && detailsErrorEl && detailsInput) {
                detailsErrorEl.textContent = message;
                detailsErrorEl.classList.remove('hidden');
                detailsInput.setAttribute('aria-invalid', 'true');
                detailsInput.setAttribute('aria-describedby', detailsErrorEl.id || 'aa-finance-record-edit-details-error');
                detailsInput.focus();
            } else if (field === 'amount' && amountErrorEl && amountInput) {
                amountErrorEl.textContent = message;
                amountErrorEl.classList.remove('hidden');
                amountInput.setAttribute('aria-invalid', 'true');
                amountInput.setAttribute('aria-describedby', amountErrorEl.id || 'aa-finance-record-edit-amount-error');
                amountInput.focus();
            } else if (modalErrorEl) {
                modalErrorEl.textContent = message;
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
        }

        function applyBlockedError(message) {
            modalState = EDIT_STATES.BLOCKED_REJECTED;
            submissionSnapshot = null;
            setBotoneraState('blocked');
            if (modalErrorEl) {
                modalErrorEl.textContent = message;
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
            if (titleInput) {
                titleInput.readOnly = true;
            }
            if (detailsInput) {
                detailsInput.readOnly = true;
            }
            if (amountInput) {
                amountInput.readOnly = true;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            if (cancelBtn) {
                cancelBtn.disabled = true;
            }
            if (closeBtnEl) {
                closeBtnEl.disabled = false;
            }
        }

        function transitionToUncertain() {
            modalState = EDIT_STATES.UNCERTAIN;
            setBotoneraState('uncertain');
            onFlowStateChange({ phase: 'invalidate_snapshot' });
            if (modalErrorEl) {
                modalErrorEl.textContent = 'No pudimos confirmar si los cambios se guardaron. Revisa el listado antes de intentarlo nuevamente.';
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
        }

        function submitUpdate() {
            if (
                modalState !== EDIT_STATES.EDITING &&
                modalState !== EDIT_STATES.FIELD_REJECTED
            ) {
                return;
            }
            if (!sourceSnapshot) {
                return;
            }

            clearFieldErrors();

            var rawTitle = titleInput ? titleInput.value : '';
            var rawDetails = detailsInput ? detailsInput.value : '';
            var rawAmount = amountInput ? amountInput.value : '';

            submissionSnapshot = {
                recordId: sourceSnapshot.recordId,
                containerId: sourceSnapshot.containerId,
                sourcePage: sourceSnapshot.sourcePage,
                variantKey: sourceSnapshot.variantKey,
                title: rawTitle,
                details: rawDetails,
                amount: rawAmount
            };

            modalState = EDIT_STATES.SUBMITTING;
            var currentSeq = ++updateRequestSeq;
            var isSettled = false;
            var snapshot = submissionSnapshot;

            if (titleInput) {
                titleInput.readOnly = true;
            }
            if (detailsInput) {
                detailsInput.readOnly = true;
            }
            if (amountInput) {
                amountInput.readOnly = true;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Guardando…';
            }
            if (cancelBtn) {
                cancelBtn.disabled = true;
            }
            if (closeBtnEl) {
                closeBtnEl.disabled = true;
            }

            cleanupUpdateRequest();
            updateAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            updateTimeoutId = setTimeout(function () {
                if (isSettled || currentSeq !== updateRequestSeq) {
                    return;
                }
                isSettled = true;
                if (updateAbortController) {
                    updateAbortController.abort();
                }
                transitionToUncertain();
            }, 15000);

            var formData = new FormData();
            formData.append('action', updateRecordAction);
            formData.append('_wpnonce', nonce);
            formData.append('record_id', String(snapshot.recordId));
            formData.append('container_id', String(snapshot.containerId));
            formData.append('title', snapshot.title);
            formData.append('details', snapshot.details);
            formData.append('amount', snapshot.amount);
            formData.append('variant_key', snapshot.variantKey);

            var fetchOptions = {
                method: 'POST',
                body: formData
            };
            if (updateAbortController) {
                fetchOptions.signal = updateAbortController.signal;
            }

            fetch(ajaxUrl, fetchOptions)
                .then(function (res) {
                    return res.json().then(function (json) {
                        return { ok: res.ok, status: res.status, json: json };
                    }).catch(function () {
                        return { ok: res.ok, status: res.status, json: null };
                    });
                })
                .then(function (response) {
                    if (isSettled || currentSeq !== updateRequestSeq) {
                        return;
                    }
                    isSettled = true;
                    if (updateTimeoutId !== null) {
                        clearTimeout(updateTimeoutId);
                        updateTimeoutId = null;
                    }

                    if (validateUpdateSuccess(response.json, snapshot)) {
                        cleanupUpdateRequest();
                        hideModal();
                        clearFieldErrors();
                        resetFormInputs();
                        setBotoneraState('standard');
                        var confirmedPage = snapshot.sourcePage;
                        transitionToIdle(true);
                        onRefreshRequested({
                            reason: 'confirmed',
                            recordId: snapshot.recordId,
                            containerId: snapshot.containerId,
                            sourcePage: confirmedPage
                        });
                        return;
                    }

                    if (response.json && response.json.success === false && response.json.data) {
                        var errCode = response.json.data.code;
                        var errMessage = typeof response.json.data.message === 'string'
                            ? response.json.data.message
                            : '';

                        if (errCode === 'record_not_found') {
                            cleanupUpdateRequest();
                            hideModal();
                            clearFieldErrors();
                            resetFormInputs();
                            setBotoneraState('standard');
                            transitionToIdle(true);
                            onRefreshRequested({
                                reason: 'not_found',
                                recordId: snapshot.recordId,
                                containerId: snapshot.containerId,
                                sourcePage: snapshot.sourcePage
                            });
                            return;
                        }

                        if (errCode === 'container_not_found') {
                            cleanupUpdateRequest();
                            hideModal();
                            clearFieldErrors();
                            resetFormInputs();
                            transitionToIdle(false);
                            onContainerNotFoundEdit(snapshot.containerId);
                            return;
                        }

                        if (CORREGIBLES[errCode]) {
                            applyFieldError(errCode);
                            return;
                        }

                        if (CONTRACT_BLOCKED.indexOf(errCode) !== -1 || BLOQUEANTES.indexOf(errCode) !== -1) {
                            applyBlockedError(errMessage || responseMessageFromCode(errCode) || 'La edición no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.');
                            return;
                        }

                        if (UNCERTAIN_CODES.indexOf(errCode) !== -1 || !response.ok || response.status >= 500) {
                            transitionToUncertain();
                            return;
                        }
                    }

                    transitionToUncertain();
                })
                .catch(function (err) {
                    if (isSettled || currentSeq !== updateRequestSeq) {
                        return;
                    }
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    isSettled = true;
                    if (updateTimeoutId !== null) {
                        clearTimeout(updateTimeoutId);
                        updateTimeoutId = null;
                    }
                    transitionToUncertain();
                });
        }

        function runReviewGet(context) {
            if (!context || !getRecordAction) {
                return;
            }
            cleanupReviewRequest();
            var seq = ++reviewRequestSeq;
            var reviewToken = context.reviewToken;

            reviewAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            var formData = new FormData();
            formData.append('action', getRecordAction);
            formData.append('_wpnonce', nonce);
            formData.append('record_id', String(context.recordId));
            formData.append('container_id', String(context.containerId));
            formData.append('variant_key', variantKey);

            var fetchOptions = {
                method: 'POST',
                body: formData
            };
            if (reviewAbortController) {
                fetchOptions.signal = reviewAbortController.signal;
            }

            fetch(ajaxUrl, fetchOptions)
                .then(function (res) {
                    return res.json().then(function (json) {
                        return { ok: res.ok, status: res.status, json: json };
                    }).catch(function () {
                        return { ok: res.ok, status: res.status, json: null };
                    });
                })
                .then(function (response) {
                    if (seq !== reviewRequestSeq) {
                        return;
                    }
                    if (activeReviewToken !== reviewToken) {
                        return;
                    }
                    if (!activeReviewContext || activeReviewContext.reviewToken !== reviewToken) {
                        return;
                    }

                    if (response.json && response.json.success === false && response.json.data) {
                        var errCode = response.json.data.code;

                        if (errCode === 'record_not_found') {
                            onReviewGetResolved({
                                reviewToken: reviewToken,
                                recordId: context.recordId,
                                containerId: context.containerId,
                                sourcePage: context.sourcePage,
                                outcome: 'gone'
                            });
                            return;
                        }

                        if (errCode === 'container_not_found') {
                            cancelReview();
                            onContainerNotFoundEdit(context.containerId);
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            onReviewGetUncertain({ reviewToken: reviewToken });
                            return;
                        }
                    }

                    if (validateGetRecordSuccess(response.json, context.recordId, context.containerId)) {
                        onReviewGetResolved({
                            reviewToken: reviewToken,
                            recordId: context.recordId,
                            containerId: context.containerId,
                            sourcePage: context.sourcePage,
                            outcome: 'present'
                        });
                        return;
                    }

                    onReviewGetUncertain({ reviewToken: reviewToken });
                })
                .catch(function (err) {
                    if (seq !== reviewRequestSeq) {
                        return;
                    }
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    if (activeReviewToken !== reviewToken) {
                        return;
                    }
                    onReviewGetUncertain({ reviewToken: reviewToken });
                });
        }

        function startUncertainReview() {
            if (!submissionSnapshot) {
                return;
            }
            var pendingDescriptor = onUncertainReviewStart({
                recordId: submissionSnapshot.recordId,
                containerId: submissionSnapshot.containerId,
                sourcePage: submissionSnapshot.sourcePage,
                submissionSnapshot: {
                    recordId: submissionSnapshot.recordId,
                    containerId: submissionSnapshot.containerId,
                    sourcePage: submissionSnapshot.sourcePage,
                    variantKey: submissionSnapshot.variantKey,
                    title: submissionSnapshot.title,
                    details: submissionSnapshot.details,
                    amount: submissionSnapshot.amount
                }
            });
            if (
                !pendingDescriptor ||
                !Number.isInteger(pendingDescriptor.reviewToken) ||
                pendingDescriptor.reviewToken < 1
            ) {
                if (modalErrorEl) {
                    modalErrorEl.textContent = 'No pudimos iniciar la revisión. Intenta de nuevo o recarga la página.';
                    modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                    modalErrorEl.classList.remove('hidden');
                }
                return;
            }

            sourceSnapshot = null;
            cleanupUpdateRequest();
            hideModal();
            clearFieldErrors();
            resetFormInputs();
            setBotoneraState('standard');
            modalState = EDIT_STATES.IDLE;
            originEditButton = null;
            onFocusDetailStatus();
            resumeReview(pendingDescriptor);
        }

        function abortActiveTransport() {
            cleanupSourceRequest();
            cleanupUpdateRequest();
            cleanupReviewRequest();
            sourceRequestSeq++;
            updateRequestSeq++;
            reviewRequestSeq++;
        }

        function resumeReview(pendingDescriptor) {
            if (!pendingDescriptor) {
                return;
            }
            if (!Number.isInteger(pendingDescriptor.reviewToken) || pendingDescriptor.reviewToken < 1) {
                return;
            }
            if (!Number.isInteger(pendingDescriptor.recordId) || pendingDescriptor.recordId < 1) {
                return;
            }
            if (!Number.isInteger(pendingDescriptor.containerId) || pendingDescriptor.containerId < 1) {
                return;
            }
            if (!Number.isInteger(pendingDescriptor.sourcePage) || pendingDescriptor.sourcePage < 1) {
                return;
            }

            activeReviewToken = pendingDescriptor.reviewToken;
            activeReviewContext = {
                reviewToken: pendingDescriptor.reviewToken,
                recordId: pendingDescriptor.recordId,
                containerId: pendingDescriptor.containerId,
                sourcePage: pendingDescriptor.sourcePage
            };

            if (pendingDescriptor.phase === 'awaiting_get') {
                runReviewGet(activeReviewContext);
            }
        }

        function retryReview() {
            if (!activeReviewContext || activeReviewToken !== activeReviewContext.reviewToken) {
                return;
            }
            runReviewGet(activeReviewContext);
        }

        function cancelReview() {
            cleanupReviewRequest();
            reviewRequestSeq++;
            activeReviewToken = null;
            activeReviewContext = null;
            submissionSnapshot = null;
        }

        function completeReviewSettlement(reviewToken) {
            if (activeReviewToken !== reviewToken) {
                return;
            }
            cleanupReviewRequest();
            reviewRequestSeq++;
            activeReviewToken = null;
            activeReviewContext = null;
            submissionSnapshot = null;
            modalState = EDIT_STATES.IDLE;
        }

        function closeModalFromUserGesture() {
            if (modalState === EDIT_STATES.SUBMITTING || modalState === EDIT_STATES.UNCERTAIN) {
                return;
            }
            closeModalInternal(true);
        }

        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                e.preventDefault();
                if (
                    modalState === EDIT_STATES.SUBMITTING ||
                    modalState === EDIT_STATES.UNCERTAIN ||
                    modalState === EDIT_STATES.BLOCKED_REJECTED ||
                    modalState === EDIT_STATES.LOADING_SOURCE
                ) {
                    return;
                }
                submitUpdate();
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                closeModalFromUserGesture();
            });
        }

        if (closeBtnEl) {
            closeBtnEl.addEventListener('click', function () {
                closeModalFromUserGesture();
            });
        }

        if (blockedCloseBtn) {
            blockedCloseBtn.addEventListener('click', function () {
                closeModalInternal(true);
            });
        }

        if (uncertainCloseBtn) {
            uncertainCloseBtn.addEventListener('click', function () {
                startUncertainReview();
            });
        }

        if (backdropEl) {
            backdropEl.addEventListener('click', function () {
                closeModalFromUserGesture();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modalEl && !modalEl.classList.contains('hidden')) {
                closeModalFromUserGesture();
            }
        });

        if (titleInput) {
            titleInput.addEventListener('input', function () {
                if (titleErrorEl) {
                    titleErrorEl.textContent = '';
                    titleErrorEl.classList.add('hidden');
                }
                titleInput.removeAttribute('aria-invalid');
                titleInput.removeAttribute('aria-describedby');
            });
        }

        if (detailsInput) {
            detailsInput.addEventListener('input', function () {
                if (detailsErrorEl) {
                    detailsErrorEl.textContent = '';
                    detailsErrorEl.classList.add('hidden');
                }
                detailsInput.removeAttribute('aria-invalid');
                detailsInput.removeAttribute('aria-describedby');
            });
        }

        if (amountInput) {
            amountInput.addEventListener('input', function () {
                if (amountErrorEl) {
                    amountErrorEl.textContent = '';
                    amountErrorEl.classList.add('hidden');
                }
                amountInput.removeAttribute('aria-invalid');
                amountInput.removeAttribute('aria-describedby');
            });
        }

        function destroy() {
            document.removeEventListener('keydown', handleFocusTrap);
            clearFocusTimer();
            abortActiveTransport();
            hideModal();
            clearFieldErrors();
            resetFormInputs();
            transitionToIdle(true);
        }

        return {
            beginEdit: beginEdit,
            resumeReview: resumeReview,
            retryReview: retryReview,
            abortActiveTransport: abortActiveTransport,
            cancelReview: cancelReview,
            completeReviewSettlement: completeReviewSettlement,
            destroy: destroy,
            getModalState: function () { return modalState; },
            getSourceSnapshot: function () { return sourceSnapshot; },
            getSubmissionSnapshot: function () { return submissionSnapshot; }
        };
    }

    window.AA_FinanceRecordEdit = {
        createController: createController
    };
})();
