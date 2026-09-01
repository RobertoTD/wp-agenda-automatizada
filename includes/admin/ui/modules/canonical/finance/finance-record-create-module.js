/**
 * Finance Record Create Module — Controlador de creación de registros financieros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical\Finance
 */
(function () {
    'use strict';

    var CREATE_STATES = {
        IDLE: 'IDLE',
        EDITING: 'EDITING',
        FIELD_REJECTED: 'FIELD_REJECTED',
        SUBMITTING: 'SUBMITTING',
        CONFIRMED: 'CONFIRMED',
        BLOCKED_REJECTED: 'BLOCKED_REJECTED',
        UNCERTAIN: 'UNCERTAIN',
        REVIEWING_UNCERTAIN: 'REVIEWING_UNCERTAIN',
        DRAFT_REVIEWED: 'DRAFT_REVIEWED'
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

    var BLOQUEANTES = [
        'bad_nonce',
        'unauthorized',
        'forbidden',
        'invalid_container_id',
        'invalid_variant_key',
        'unknown_variant',
        'container_not_found'
    ];

    var UNCERTAIN_CODES = [
        'persistence_failed',
        'canonical_unavailable'
    ];

    function isValidDecimalAmount(value) {
        if (value === null) {
            return true;
        }
        if (typeof value !== 'string') {
            return false;
        }
        var match = value.match(/^(-)?([0-9]+)\.([0-9]{2})$/);
        if (!match) {
            return false;
        }
        var isNegative = match[1] === '-';
        var intPart = match[2];
        var decPart = match[3];
        if (intPart === '0' && decPart === '00') {
            return !isNegative;
        }
        if (isNegative && intPart === '0') {
            return false;
        }
        if (intPart.length > 1 && intPart.charAt(0) === '0') {
            return false;
        }
        var normalizedInt = intPart.replace(/^0+/, '');
        if (normalizedInt === '') {
            normalizedInt = '0';
        }
        if (normalizedInt.length > 17) {
            return false;
        }
        return true;
    }

    function createController(options) {
        var cfg = options.cfg;
        var elements = options.elements || {};
        var getActiveContainerId = typeof options.getActiveContainerId === 'function'
            ? options.getActiveContainerId
            : function () { return null; };
        var isDetailActive = typeof options.isDetailActive === 'function'
            ? options.isDetailActive
            : function () { return false; };
        var onConfirmedRefresh = typeof options.onConfirmedRefresh === 'function'
            ? options.onConfirmedRefresh
            : function () {};
        var onUncertainReviewStart = typeof options.onUncertainReviewStart === 'function'
            ? options.onUncertainReviewStart
            : function () { return null; };
        var onContainerNotFoundCreate = typeof options.onContainerNotFoundCreate === 'function'
            ? options.onContainerNotFoundCreate
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
        var openTriggerBtn = elements.openTrigger || null;

        var ajaxUrl = cfg.ajaxUrl;
        var nonce = cfg.nonce;
        var familyKey = cfg.familyKey;
        var variantKey = cfg.variantKey;
        var createRecordAction = cfg.actions && cfg.actions.createRecord;

        var recordCreateModalState = CREATE_STATES.IDLE;
        var savedDraft = {
            containerId: null,
            title: '',
            details: '',
            amount: ''
        };
        var submittedSnapshot = null;
        var activeReviewToken = null;
        var reviewCompleted = false;

        var createAbortController = null;
        var createTimeoutId = null;
        var createRequestSeq = 0;
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
                if (recordCreateModalState !== CREATE_STATES.EDITING && recordCreateModalState !== CREATE_STATES.DRAFT_REVIEWED) {
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

        function clearModalErrors() {
            if (modalErrorEl) {
                modalErrorEl.textContent = '';
                modalErrorEl.classList.add('hidden');
            }
            if (titleErrorEl) {
                titleErrorEl.textContent = '';
                titleErrorEl.classList.add('hidden');
            }
            if (titleInput) {
                titleInput.removeAttribute('aria-invalid');
                titleInput.removeAttribute('aria-describedby');
            }
            if (detailsErrorEl) {
                detailsErrorEl.textContent = '';
                detailsErrorEl.classList.add('hidden');
            }
            if (detailsInput) {
                detailsInput.removeAttribute('aria-invalid');
                detailsInput.removeAttribute('aria-describedby');
            }
            if (amountErrorEl) {
                amountErrorEl.textContent = '';
                amountErrorEl.classList.add('hidden');
            }
            if (amountInput) {
                amountInput.removeAttribute('aria-invalid');
                amountInput.removeAttribute('aria-describedby');
            }
        }

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

        function setFieldsEditable(editable) {
            if (titleInput) {
                titleInput.readOnly = !editable;
            }
            if (detailsInput) {
                detailsInput.readOnly = !editable;
            }
            if (amountInput) {
                amountInput.readOnly = !editable;
            }
        }

        function validateCreateResponse(json, expectedContainerId) {
            if (!json || typeof json !== 'object' || json.success !== true) {
                return false;
            }
            var data = json.data;
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return false;
            }
            var record = data.record;
            if (!record || typeof record !== 'object' || Array.isArray(record)) {
                return false;
            }
            if (!Number.isInteger(record.id) || record.id < 1) {
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
            if (!isValidDecimalAmount(record.amount)) {
                return false;
            }
            if (typeof record.created_at !== 'string') {
                return false;
            }
            return true;
        }

        function openModal() {
            if (!modalEl || !createRecordAction) {
                return;
            }
            if (!isDetailActive()) {
                return;
            }
            var containerId = getActiveContainerId();
            if (!Number.isInteger(containerId) || containerId < 1) {
                return;
            }
            if (recordCreateModalState === CREATE_STATES.REVIEWING_UNCERTAIN) {
                return;
            }

            clearModalErrors();
            setBotoneraState('standard');
            setFieldsEditable(true);

            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Crear entrada';
            }
            if (cancelBtn) {
                cancelBtn.disabled = false;
            }
            if (closeBtnEl) {
                closeBtnEl.disabled = false;
            }

            if (recordCreateModalState === CREATE_STATES.DRAFT_REVIEWED && savedDraft.containerId === containerId) {
                if (titleInput) {
                    titleInput.value = savedDraft.title;
                }
                if (detailsInput) {
                    detailsInput.value = savedDraft.details;
                }
                if (amountInput) {
                    amountInput.value = savedDraft.amount;
                }
                if (modalErrorEl) {
                    modalErrorEl.textContent = 'Antes de volver a crearla, verifica que la entrada no aparezca ya en el listado.';
                    modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-xs font-medium';
                    modalErrorEl.classList.remove('hidden');
                }
            } else {
                if (titleInput) {
                    titleInput.value = '';
                }
                if (detailsInput) {
                    detailsInput.value = '';
                }
                if (amountInput) {
                    amountInput.value = '';
                }
            }

            recordCreateModalState = CREATE_STATES.EDITING;
            modalEl.classList.remove('hidden');
            modalEl.setAttribute('aria-hidden', 'false');
            scheduleTitleFocus();
        }

        function closeModal(cleanDraft) {
            if (!modalEl) {
                return;
            }

            clearFocusTimer();

            if (createTimeoutId !== null) {
                clearTimeout(createTimeoutId);
                createTimeoutId = null;
            }
            if (createAbortController) {
                createAbortController.abort();
                createAbortController = null;
            }

            modalEl.classList.add('hidden');
            modalEl.setAttribute('aria-hidden', 'true');

            if (cleanDraft) {
                savedDraft = {
                    containerId: null,
                    title: '',
                    details: '',
                    amount: ''
                };
                submittedSnapshot = null;
                activeReviewToken = null;
                reviewCompleted = false;
                if (titleInput) {
                    titleInput.value = '';
                }
                if (detailsInput) {
                    detailsInput.value = '';
                }
                if (amountInput) {
                    amountInput.value = '';
                }
                clearModalErrors();
                recordCreateModalState = CREATE_STATES.IDLE;
            }

            if (openTriggerBtn && !openTriggerBtn.disabled && document.activeElement && modalEl.contains(document.activeElement)) {
                openTriggerBtn.focus();
            }
        }

        function completeReview(reviewToken, containerId) {
            if (recordCreateModalState !== CREATE_STATES.REVIEWING_UNCERTAIN) {
                return;
            }
            if (reviewCompleted) {
                return;
            }
            if (activeReviewToken !== reviewToken) {
                return;
            }
            if (savedDraft.containerId !== containerId) {
                return;
            }
            reviewCompleted = true;
            activeReviewToken = null;
            recordCreateModalState = CREATE_STATES.DRAFT_REVIEWED;
        }

        function cancelReview(reviewToken, reason) {
            if (activeReviewToken !== null && activeReviewToken !== reviewToken) {
                return;
            }
            activeReviewToken = null;
            reviewCompleted = false;
            savedDraft = {
                containerId: null,
                title: '',
                details: '',
                amount: ''
            };
            submittedSnapshot = null;
            recordCreateModalState = CREATE_STATES.IDLE;
            if (reason === 'container_not_found') {
                closeModal(true);
            }
        }

        function transitionToUncertain() {
            recordCreateModalState = CREATE_STATES.UNCERTAIN;
            setBotoneraState('uncertain');
            if (modalErrorEl) {
                modalErrorEl.textContent = 'No pudimos confirmar si la entrada se creó. Revisa el listado antes de intentarlo nuevamente.';
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
        }

        function applyFieldError(field, errMsg) {
            recordCreateModalState = CREATE_STATES.FIELD_REJECTED;
            setFieldsEditable(true);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Crear entrada';
            }
            if (cancelBtn) {
                cancelBtn.disabled = false;
            }
            if (closeBtnEl) {
                closeBtnEl.disabled = false;
            }

            if (field === 'title') {
                if (titleErrorEl) {
                    titleErrorEl.textContent = errMsg;
                    titleErrorEl.classList.remove('hidden');
                }
                if (titleInput) {
                    titleInput.setAttribute('aria-invalid', 'true');
                    titleInput.setAttribute('aria-describedby', 'aa-finance-record-title-error');
                    titleInput.focus();
                }
            } else if (field === 'details') {
                if (detailsErrorEl) {
                    detailsErrorEl.textContent = errMsg;
                    detailsErrorEl.classList.remove('hidden');
                }
                if (detailsInput) {
                    detailsInput.setAttribute('aria-invalid', 'true');
                    detailsInput.setAttribute('aria-describedby', 'aa-finance-record-details-error');
                    detailsInput.focus();
                }
            } else if (field === 'amount') {
                if (amountErrorEl) {
                    amountErrorEl.textContent = errMsg;
                    amountErrorEl.classList.remove('hidden');
                }
                if (amountInput) {
                    amountInput.setAttribute('aria-invalid', 'true');
                    amountInput.setAttribute('aria-describedby', 'aa-finance-record-amount-error');
                    amountInput.focus();
                }
            }
        }

        function applyBlockedError(message, notifyContainerNotFound) {
            recordCreateModalState = CREATE_STATES.BLOCKED_REJECTED;
            setBotoneraState('blocked');
            if (closeBtnEl) {
                closeBtnEl.disabled = false;
            }
            if (modalErrorEl) {
                modalErrorEl.textContent = message;
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
            if (notifyContainerNotFound && submittedSnapshot) {
                onContainerNotFoundCreate(submittedSnapshot.containerId);
            }
        }

        function closeModalFromUserGesture() {
            if (
                recordCreateModalState === CREATE_STATES.SUBMITTING ||
                recordCreateModalState === CREATE_STATES.UNCERTAIN ||
                recordCreateModalState === CREATE_STATES.REVIEWING_UNCERTAIN
            ) {
                return;
            }
            closeModal(true);
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                if (recordCreateModalState === CREATE_STATES.SUBMITTING) {
                    return;
                }
                closeModal(true);
            });
        }

        if (closeBtnEl) {
            closeBtnEl.addEventListener('click', closeModalFromUserGesture);
        }

        if (blockedCloseBtn) {
            blockedCloseBtn.addEventListener('click', function () {
                closeModal(true);
            });
        }

        if (uncertainCloseBtn) {
            uncertainCloseBtn.addEventListener('click', function () {
                if (!submittedSnapshot) {
                    return;
                }
                var reviewToken = onUncertainReviewStart({
                    containerId: submittedSnapshot.containerId
                });
                if (!Number.isInteger(reviewToken) || reviewToken < 1) {
                    return;
                }
                activeReviewToken = reviewToken;
                reviewCompleted = false;
                recordCreateModalState = CREATE_STATES.REVIEWING_UNCERTAIN;
                closeModal(false);
            });
        }

        if (backdropEl) {
            backdropEl.addEventListener('click', closeModalFromUserGesture);
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
                if (modalErrorEl && recordCreateModalState === CREATE_STATES.EDITING) {
                    modalErrorEl.classList.add('hidden');
                }
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

        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                e.preventDefault();

                if (
                    recordCreateModalState === CREATE_STATES.SUBMITTING ||
                    recordCreateModalState === CREATE_STATES.UNCERTAIN ||
                    recordCreateModalState === CREATE_STATES.BLOCKED_REJECTED ||
                    recordCreateModalState === CREATE_STATES.REVIEWING_UNCERTAIN
                ) {
                    return;
                }

                if (!isDetailActive()) {
                    return;
                }

                var activeContainerId = getActiveContainerId();
                if (!Number.isInteger(activeContainerId) || activeContainerId < 1) {
                    return;
                }

                clearModalErrors();

                var rawTitle = titleInput ? titleInput.value : '';
                var rawDetails = detailsInput ? detailsInput.value : '';
                var rawAmount = amountInput ? amountInput.value : '';

                if (rawTitle.trim() === '') {
                    applyFieldError('title', 'El título no puede estar vacío.');
                    return;
                }

                submittedSnapshot = {
                    containerId: activeContainerId,
                    title: rawTitle,
                    details: rawDetails,
                    amount: rawAmount,
                    variantKey: variantKey
                };
                savedDraft = {
                    containerId: activeContainerId,
                    title: rawTitle,
                    details: rawDetails,
                    amount: rawAmount
                };

                recordCreateModalState = CREATE_STATES.SUBMITTING;
                var currentCreateSeq = ++createRequestSeq;
                var isSettled = false;

                setFieldsEditable(false);
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Creando…';
                }
                if (cancelBtn) {
                    cancelBtn.disabled = true;
                }
                if (closeBtnEl) {
                    closeBtnEl.disabled = true;
                }

                createAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;

                createTimeoutId = setTimeout(function () {
                    if (isSettled || currentCreateSeq !== createRequestSeq) {
                        return;
                    }
                    isSettled = true;
                    if (createAbortController) {
                        createAbortController.abort();
                    }
                    transitionToUncertain();
                }, 15000);

                function cleanupCreateTimer() {
                    if (createTimeoutId !== null) {
                        clearTimeout(createTimeoutId);
                        createTimeoutId = null;
                    }
                }

                var formData = new FormData();
                formData.append('action', createRecordAction);
                formData.append('_wpnonce', nonce);
                formData.append('container_id', String(submittedSnapshot.containerId));
                formData.append('variant_key', submittedSnapshot.variantKey);
                formData.append('title', submittedSnapshot.title);
                formData.append('details', submittedSnapshot.details);
                if (submittedSnapshot.amount.trim() !== '') {
                    formData.append('amount', submittedSnapshot.amount);
                }

                var fetchOptions = {
                    method: 'POST',
                    body: formData
                };
                if (createAbortController) {
                    fetchOptions.signal = createAbortController.signal;
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
                        if (isSettled || currentCreateSeq !== createRequestSeq) {
                            return;
                        }
                        isSettled = true;
                        cleanupCreateTimer();

                        if (response.ok && response.json && response.json.success === true) {
                            if (validateCreateResponse(response.json, submittedSnapshot.containerId)) {
                                if (getActiveContainerId() !== submittedSnapshot.containerId || !isDetailActive()) {
                                    transitionToUncertain();
                                    return;
                                }
                                recordCreateModalState = CREATE_STATES.CONFIRMED;
                                activeReviewToken = null;
                                reviewCompleted = false;
                                var confirmedContainerId = submittedSnapshot.containerId;
                                closeModal(true);
                                onConfirmedRefresh(confirmedContainerId);
                                return;
                            }
                            transitionToUncertain();
                            return;
                        }

                        if (response.json && response.json.success === false && response.json.data) {
                            var errCode = response.json.data.code;
                            var errMsg = response.json.data.message || 'Error al crear la entrada.';

                            if (Object.prototype.hasOwnProperty.call(CORREGIBLES, errCode)) {
                                applyFieldError(CORREGIBLES[errCode], errMsg);
                                return;
                            }

                            if (BLOQUEANTES.indexOf(errCode) !== -1) {
                                var blockedMsg = 'La creación no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.';
                                var notifyNotFound = errCode === 'container_not_found' || errCode === 'invalid_container_id';
                                if (notifyNotFound) {
                                    blockedMsg = 'Este contenedor ya no está disponible.';
                                }
                                applyBlockedError(blockedMsg, notifyNotFound);
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
                        if (isSettled || currentCreateSeq !== createRequestSeq) {
                            return;
                        }
                        if (err && err.name === 'AbortError') {
                            return;
                        }
                        isSettled = true;
                        cleanupCreateTimer();
                        transitionToUncertain();
                    });
            });
        }

        function destroy() {
            document.removeEventListener('keydown', handleFocusTrap);
            if (createTimeoutId !== null) {
                clearTimeout(createTimeoutId);
                createTimeoutId = null;
            }
            if (createAbortController) {
                createAbortController.abort();
                createAbortController = null;
            }
            clearFocusTimer();
            createRequestSeq++;
            recordCreateModalState = CREATE_STATES.IDLE;
            savedDraft = {
                containerId: null,
                title: '',
                details: '',
                amount: ''
            };
            submittedSnapshot = null;
            activeReviewToken = null;
            reviewCompleted = false;
        }

        return {
            openModal: openModal,
            closeModal: closeModal,
            completeReview: completeReview,
            cancelReview: cancelReview,
            destroy: destroy
        };
    }

    window.AA_FinanceRecordCreate = {
        createController: createController
    };
})();
