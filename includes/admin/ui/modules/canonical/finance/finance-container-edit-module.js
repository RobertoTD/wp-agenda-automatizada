/**
 * Finance Container Edit Module — Controlador de edición de contenedores financieros.
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
        missing_details: 'details',
        invalid_details: 'details',
        details_too_long: 'details'
    };

    var BLOQUEANTES = [
        'bad_nonce',
        'unauthorized',
        'forbidden',
        'invalid_id',
        'invalid_variant_key',
        'unknown_variant'
    ];

    var UNCERTAIN_CODES = [
        'persistence_failed',
        'canonical_unavailable'
    ];

    function createController(options) {
        var cfg = options.cfg;
        var elements = options.elements || {};
        var isListActive = typeof options.isListActive === 'function'
            ? options.isListActive
            : function () { return true; };
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
        var onReviewPending = typeof options.onReviewPending === 'function'
            ? options.onReviewPending
            : function () { return null; };
        var onReviewUncertain = typeof options.onReviewUncertain === 'function'
            ? options.onReviewUncertain
            : function () {};
        var onReviewAwaitingList = typeof options.onReviewAwaitingList === 'function'
            ? options.onReviewAwaitingList
            : function () {};
        var onFocusStatus = typeof options.onFocusStatus === 'function'
            ? options.onFocusStatus
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
        var getContainerAction = cfg.actions && cfg.actions.getContainer;
        var updateContainerAction = cfg.actions && cfg.actions.updateContainer;

        var modalState = EDIT_STATES.IDLE;
        var sourceSnapshot = null;
        var submissionSnapshot = null;
        var originEditButton = null;
        var activeReviewToken = null;
        var activeReviewContext = null;

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

        function validateGetContainerSuccess(json, requestedId) {
            if (!json || typeof json !== 'object' || json.success !== true) {
                return false;
            }
            var data = json.data;
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return false;
            }
            var container = data.container;
            if (!container || typeof container !== 'object' || Array.isArray(container)) {
                return false;
            }
            if (!Number.isInteger(container.id) || container.id !== requestedId) {
                return false;
            }
            if (container.family_key !== familyKey || container.variant_key !== variantKey) {
                return false;
            }
            if (typeof container.title !== 'string') {
                return false;
            }
            if (container.details !== null && typeof container.details !== 'string') {
                return false;
            }
            if (typeof container.created_at !== 'string') {
                return false;
            }
            return true;
        }

        function validateUpdateSuccess(json, snapshot) {
            if (!json || typeof json !== 'object' || json.success !== true) {
                return false;
            }
            var data = json.data;
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return false;
            }
            var container = data.container;
            if (!container || typeof container !== 'object' || Array.isArray(container)) {
                return false;
            }
            if (!Number.isInteger(container.id) || container.id !== snapshot.containerId) {
                return false;
            }
            if (container.family_key !== familyKey || container.variant_key !== variantKey) {
                return false;
            }
            if (typeof container.title !== 'string') {
                return false;
            }
            if (container.details !== null && typeof container.details !== 'string') {
                return false;
            }
            if (typeof container.created_at !== 'string') {
                return false;
            }
            return true;
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
            if (titleInput) {
                titleInput.removeAttribute('aria-invalid');
                titleInput.removeAttribute('aria-describedby');
            }
            if (detailsInput) {
                detailsInput.removeAttribute('aria-invalid');
                detailsInput.removeAttribute('aria-describedby');
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
            onFocusStatus();
        }

        function populateDraftFromSource() {
            if (!sourceSnapshot) {
                return;
            }
            if (titleInput) {
                titleInput.value = sourceSnapshot.title;
            }
            if (detailsInput) {
                detailsInput.value = sourceSnapshot.details === null ? '' : sourceSnapshot.details;
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

        function beginEdit(containerId, sourcePage, originButton) {
            if (!modalEl || !getContainerAction || !updateContainerAction) {
                return;
            }
            if (!isListActive() || !isEditAllowed()) {
                return;
            }
            if (modalState !== EDIT_STATES.IDLE) {
                return;
            }
            if (!Number.isInteger(containerId) || containerId < 1) {
                return;
            }
            if (!Number.isInteger(sourcePage) || sourcePage < 1) {
                return;
            }

            originEditButton = originButton || null;
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
                    containerId: containerId,
                    sourcePage: sourcePage,
                    message: 'No pudimos cargar la lista para editar. Intenta de nuevo.'
                });
            }, 15000);

            var formData = new FormData();
            formData.append('action', getContainerAction);
            formData.append('_wpnonce', nonce);
            formData.append('id', String(containerId));
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

                        if (errCode === 'not_found') {
                            modalState = EDIT_STATES.IDLE;
                            onGetSourceFailed({
                                reason: 'not_found',
                                containerId: containerId,
                                sourcePage: sourcePage,
                                message: 'La lista ya no está disponible. Actualizamos el listado.'
                            });
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            modalState = EDIT_STATES.IDLE;
                            onGetSourceFailed({
                                reason: 'blocked',
                                containerId: containerId,
                                sourcePage: sourcePage,
                                message: 'La edición no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.'
                            });
                            return;
                        }
                    }

                    if (validateGetContainerSuccess(response.json, containerId)) {
                        var container = response.json.data.container;
                        sourceSnapshot = {
                            containerId: container.id,
                            variantKey: variantKey,
                            sourcePage: sourcePage,
                            title: container.title,
                            details: container.details,
                            createdAt: container.created_at
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
                        containerId: containerId,
                        sourcePage: sourcePage,
                        message: 'No pudimos cargar la lista para editar. Intenta de nuevo.'
                    });
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
                        containerId: containerId,
                        sourcePage: sourcePage,
                        message: 'No pudimos cargar la lista para editar. Intenta de nuevo.'
                    });
                });
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
            var message = 'Revisa el valor ingresado.';
            if (responseMessageFromCode(code)) {
                message = responseMessageFromCode(code);
            }

            if (field === 'title' && titleErrorEl && titleInput) {
                titleErrorEl.textContent = message;
                titleErrorEl.classList.remove('hidden');
                titleInput.setAttribute('aria-invalid', 'true');
                titleInput.setAttribute('aria-describedby', titleErrorEl.id || 'aa-finance-container-edit-title-error');
                titleInput.focus();
            } else if (field === 'details' && detailsErrorEl && detailsInput) {
                detailsErrorEl.textContent = message;
                detailsErrorEl.classList.remove('hidden');
                detailsInput.setAttribute('aria-invalid', 'true');
                detailsInput.setAttribute('aria-describedby', detailsErrorEl.id || 'aa-finance-container-edit-details-error');
                detailsInput.focus();
            } else if (modalErrorEl) {
                modalErrorEl.textContent = message;
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
        }

        function responseMessageFromCode(code) {
            var messages = {
                missing_title: 'El título es obligatorio.',
                invalid_title: 'El título no es válido.',
                title_too_long: 'El título es demasiado largo.',
                missing_details: 'Los detalles son obligatorios.',
                invalid_details: 'Los detalles no son válidos.',
                details_too_long: 'Los detalles son demasiado largos.'
            };
            return messages[code] || '';
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

            if (rawTitle.trim() === '') {
                modalState = EDIT_STATES.FIELD_REJECTED;
                if (titleErrorEl) {
                    titleErrorEl.textContent = 'El título no puede estar vacío.';
                    titleErrorEl.classList.remove('hidden');
                }
                if (titleInput) {
                    titleInput.setAttribute('aria-invalid', 'true');
                    titleInput.setAttribute('aria-describedby', titleErrorEl ? (titleErrorEl.id || 'aa-finance-container-edit-title-error') : 'aa-finance-container-edit-title-error');
                    titleInput.focus();
                }
                return;
            }

            submissionSnapshot = {
                containerId: sourceSnapshot.containerId,
                variantKey: sourceSnapshot.variantKey,
                sourcePage: sourceSnapshot.sourcePage,
                title: rawTitle,
                details: rawDetails
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
            formData.append('action', updateContainerAction);
            formData.append('_wpnonce', nonce);
            formData.append('id', String(snapshot.containerId));
            formData.append('title', snapshot.title);
            formData.append('details', snapshot.details);
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

                        if (CORREGIBLES[errCode]) {
                            applyFieldError(errCode);
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            applyBlockedError(errMessage || 'La edición no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.');
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
            if (!context || !getContainerAction) {
                return;
            }
            cleanupReviewRequest();
            var seq = ++reviewRequestSeq;
            var reviewToken = context.reviewToken;

            reviewAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            var formData = new FormData();
            formData.append('action', getContainerAction);
            formData.append('_wpnonce', nonce);
            formData.append('id', String(context.containerId));
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

                        if (errCode === 'not_found') {
                            onReviewAwaitingList({
                                reviewToken: reviewToken,
                                containerId: context.containerId,
                                sourcePage: context.sourcePage,
                                outcome: 'gone'
                            });
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            onReviewUncertain({ reviewToken: reviewToken, blocked: true });
                            return;
                        }
                    }

                    if (validateGetContainerSuccess(response.json, context.containerId)) {
                        onReviewAwaitingList({
                            reviewToken: reviewToken,
                            containerId: context.containerId,
                            sourcePage: context.sourcePage,
                            outcome: 'present'
                        });
                        return;
                    }

                    onReviewUncertain({ reviewToken: reviewToken });
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
                    onReviewUncertain({ reviewToken: reviewToken });
                });
        }

        function startUncertainReview() {
            if (!submissionSnapshot) {
                return;
            }
            var reviewToken = onReviewPending({
                containerId: submissionSnapshot.containerId,
                sourcePage: submissionSnapshot.sourcePage,
                submissionSnapshot: {
                    containerId: submissionSnapshot.containerId,
                    variantKey: submissionSnapshot.variantKey,
                    sourcePage: submissionSnapshot.sourcePage,
                    title: submissionSnapshot.title,
                    details: submissionSnapshot.details
                }
            });
            if (!Number.isInteger(reviewToken) || reviewToken < 1) {
                return;
            }

            activeReviewToken = reviewToken;
            activeReviewContext = {
                reviewToken: reviewToken,
                containerId: submissionSnapshot.containerId,
                sourcePage: submissionSnapshot.sourcePage
            };

            sourceSnapshot = null;
            cleanupUpdateRequest();
            hideModal();
            clearFieldErrors();
            resetFormInputs();
            setBotoneraState('standard');
            modalState = EDIT_STATES.IDLE;
            originEditButton = null;

            onFlowStateChange({ phase: 'edit_review' });
            onFocusStatus();
            runReviewGet(activeReviewContext);
        }

        function abortActiveReviewRequest() {
            cleanupReviewRequest();
            reviewRequestSeq++;
        }

        function resumeReview(pendingReview) {
            if (!pendingReview) {
                return;
            }
            if (!Number.isInteger(pendingReview.reviewToken) || pendingReview.reviewToken < 1) {
                return;
            }
            activeReviewToken = pendingReview.reviewToken;
            activeReviewContext = {
                reviewToken: pendingReview.reviewToken,
                containerId: pendingReview.containerId,
                sourcePage: pendingReview.sourcePage
            };
            onFlowStateChange({ phase: 'edit_review' });

            if (pendingReview.phase === 'awaiting_get') {
                runReviewGet(activeReviewContext);
            }
        }

        function retryReviewGet() {
            if (!activeReviewContext || activeReviewToken !== activeReviewContext.reviewToken) {
                return;
            }
            runReviewGet(activeReviewContext);
        }

        function completeListSettlement(reviewToken, outcome) {
            if (activeReviewToken !== reviewToken) {
                return;
            }
            cleanupReviewRequest();
            reviewRequestSeq++;
            activeReviewToken = null;
            activeReviewContext = null;
            submissionSnapshot = null;
            modalState = EDIT_STATES.IDLE;
            onFlowStateChange({ phase: 'idle' });
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

        function destroy() {
            document.removeEventListener('keydown', handleFocusTrap);
            clearFocusTimer();
            cleanupSourceRequest();
            cleanupUpdateRequest();
            cleanupReviewRequest();
            sourceRequestSeq++;
            updateRequestSeq++;
            reviewRequestSeq++;
            hideModal();
            clearFieldErrors();
            resetFormInputs();
            transitionToIdle(true);
        }

        return {
            beginEdit: beginEdit,
            closeModal: closeModalFromUserGesture,
            resumeReview: resumeReview,
            retryReviewGet: retryReviewGet,
            abortActiveReviewRequest: abortActiveReviewRequest,
            completeListSettlement: completeListSettlement,
            destroy: destroy,
            getModalState: function () { return modalState; },
            getSourceSnapshot: function () { return sourceSnapshot; },
            getSubmissionSnapshot: function () { return submissionSnapshot; }
        };
    }

    window.AA_FinanceContainerEdit = {
        createController: createController
    };
})();
