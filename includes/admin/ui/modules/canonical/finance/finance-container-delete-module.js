/**
 * Finance Container Delete Module — Controlador de eliminación de contenedores financieros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical\Finance
 */
(function () {
    'use strict';

    var DELETE_STATES = {
        IDLE: 'IDLE',
        CONFIRMING: 'CONFIRMING',
        SUBMITTING: 'SUBMITTING',
        BLOCKED_REJECTED: 'BLOCKED_REJECTED',
        UNCERTAIN: 'UNCERTAIN',
        REVIEWING_UNCERTAIN: 'REVIEWING_UNCERTAIN',
        REVIEW_CONFIRMED_EXISTS: 'REVIEW_CONFIRMED_EXISTS'
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
        var isDeleteAllowed = typeof options.isDeleteAllowed === 'function'
            ? options.isDeleteAllowed
            : function () { return true; };
        var onFlowStateChange = typeof options.onFlowStateChange === 'function'
            ? options.onFlowStateChange
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
        var onFocusStatus = typeof options.onFocusStatus === 'function'
            ? options.onFocusStatus
            : function () {};

        var modalEl = elements.modal || null;
        var backdropEl = elements.backdrop || null;
        var closeBtnEl = elements.closeBtn || null;
        var bodyEl = elements.body || null;
        var amountEl = elements.amount || null;
        var modalErrorEl = elements.modalError || null;
        var standardActionsEl = elements.standardActions || null;
        var cancelBtn = elements.cancelBtn || null;
        var confirmBtn = elements.confirmBtn || null;
        var uncertainActionsEl = elements.uncertainActions || null;
        var uncertainCloseBtn = elements.uncertainCloseBtn || null;
        var blockedActionsEl = elements.blockedActionsEl || elements.blockedActions || null;
        var blockedCloseBtn = elements.blockedCloseBtn || null;

        var ajaxUrl = cfg.ajaxUrl;
        var nonce = cfg.nonce;
        var variantKey = cfg.variantKey;
        var deleteContainerAction = cfg.actions && cfg.actions.deleteContainer;
        var getContainerAction = cfg.actions && cfg.actions.getContainer;

        var modalState = DELETE_STATES.IDLE;
        var submittedSnapshot = null;
        var originDeleteButton = null;
        var activeReviewToken = null;
        var activeReviewContext = null;

        var deleteAbortController = null;
        var reviewAbortController = null;
        var deleteTimeoutId = null;
        var deleteRequestSeq = 0;
        var reviewRequestSeq = 0;
        var focusTimeoutId = null;

        function clearFocusTimer() {
            if (focusTimeoutId !== null) {
                clearTimeout(focusTimeoutId);
                focusTimeoutId = null;
            }
        }

        function scheduleCancelFocus() {
            clearFocusTimer();
            focusTimeoutId = setTimeout(function () {
                focusTimeoutId = null;
                if (cancelBtn && modalState === DELETE_STATES.CONFIRMING) {
                    cancelBtn.focus();
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
            var raw = container.querySelectorAll('button:not([disabled]), [tabindex]:not([tabindex="-1"])');
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

        function formatAmountDisplay(amount) {
            if (amount === null) {
                return 'Sin importes';
            }
            return String(amount);
        }

        function isValidDecimalAmount(value) {
            if (value === null) {
                return true;
            }
            return typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value);
        }

        function validateSnapshot(snapshot) {
            if (!snapshot || typeof snapshot !== 'object') {
                return false;
            }
            if (!Number.isInteger(snapshot.containerId) || snapshot.containerId < 1) {
                return false;
            }
            if (!Number.isInteger(snapshot.sourcePage) || snapshot.sourcePage < 1) {
                return false;
            }
            if (typeof snapshot.title !== 'string') {
                return false;
            }
            if (snapshot.details !== null && typeof snapshot.details !== 'string') {
                return false;
            }
            if (!isValidDecimalAmount(snapshot.amountTotal)) {
                return false;
            }
            return true;
        }

        function validateDeleteSuccess(json, snapshot) {
            if (!json || typeof json !== 'object' || json.success !== true) {
                return false;
            }
            var data = json.data;
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return false;
            }
            if (data.deleted !== true) {
                return false;
            }
            if (!Number.isInteger(data.id) || data.id !== snapshot.containerId) {
                return false;
            }
            return true;
        }

        function restoreOriginFocus() {
            var originConnected = originDeleteButton && (
                (typeof document.contains === 'function' && document.contains(originDeleteButton)) ||
                originDeleteButton.parentElement !== null ||
                originDeleteButton.parentNode !== null
            );
            if (originConnected && !originDeleteButton.disabled) {
                originDeleteButton.focus();
                return;
            }
            onFocusStatus();
        }

        function resetModalVisual() {
            if (modalErrorEl) {
                modalErrorEl.textContent = '';
                modalErrorEl.classList.add('hidden');
            }
            if (bodyEl) {
                bodyEl.textContent = '';
            }
            if (amountEl) {
                amountEl.textContent = '';
                amountEl.classList.add('hidden');
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

        function cleanupDeleteRequest() {
            if (deleteTimeoutId !== null) {
                clearTimeout(deleteTimeoutId);
                deleteTimeoutId = null;
            }
            if (deleteAbortController) {
                deleteAbortController.abort();
                deleteAbortController = null;
            }
        }

        function cleanupReviewRequest() {
            if (reviewAbortController) {
                reviewAbortController.abort();
                reviewAbortController = null;
            }
        }

        function transitionToIdle() {
            modalState = DELETE_STATES.IDLE;
            submittedSnapshot = null;
            originDeleteButton = null;
            activeReviewToken = null;
            activeReviewContext = null;
            onFlowStateChange({ phase: 'idle' });
        }

        function closeModalInternal(releaseActive) {
            clearFocusTimer();
            cleanupDeleteRequest();
            hideModal();
            resetModalVisual();
            var willTransitionIdle = (
                modalState === DELETE_STATES.CONFIRMING ||
                modalState === DELETE_STATES.BLOCKED_REJECTED ||
                modalState === DELETE_STATES.REVIEW_CONFIRMED_EXISTS
            );
            if (releaseActive && !willTransitionIdle && modalState !== DELETE_STATES.REVIEWING_UNCERTAIN) {
                onFlowStateChange({ phase: 'idle' });
            }
            if (willTransitionIdle) {
                transitionToIdle();
                restoreOriginFocus();
            }
        }

        function openModal(recordSnapshot, originButton) {
            if (!modalEl || !deleteContainerAction || !getContainerAction) {
                return;
            }
            if (!isListActive() || !isDeleteAllowed()) {
                return;
            }
            if (
                modalState !== DELETE_STATES.IDLE &&
                modalState !== DELETE_STATES.REVIEW_CONFIRMED_EXISTS
            ) {
                return;
            }
            if (!validateSnapshot(recordSnapshot)) {
                return;
            }

            submittedSnapshot = {
                containerId: recordSnapshot.containerId,
                title: recordSnapshot.title,
                details: recordSnapshot.details,
                amountTotal: recordSnapshot.amountTotal,
                sourcePage: recordSnapshot.sourcePage
            };
            originDeleteButton = originButton || null;

            resetModalVisual();
            setBotoneraState('standard');
            if (confirmBtn) {
                confirmBtn.disabled = false;
            }
            if (cancelBtn) {
                cancelBtn.disabled = false;
            }
            if (closeBtnEl) {
                closeBtnEl.disabled = false;
            }

            if (bodyEl) {
                bodyEl.textContent = 'Se eliminará permanentemente la lista «' + submittedSnapshot.title + '» y todas sus entradas. Esta acción no se puede deshacer.';
            }
            if (amountEl) {
                amountEl.textContent = formatAmountDisplay(submittedSnapshot.amountTotal);
                amountEl.classList.remove('hidden');
            }

            modalState = DELETE_STATES.CONFIRMING;
            onFlowStateChange({ phase: 'delete_active' });
            showModal();
            scheduleCancelFocus();
        }

        function closeModal() {
            if (modalState === DELETE_STATES.SUBMITTING || modalState === DELETE_STATES.UNCERTAIN) {
                return;
            }
            closeModalInternal(true);
        }

        function transitionToUncertain() {
            modalState = DELETE_STATES.UNCERTAIN;
            setBotoneraState('uncertain');
            onFlowStateChange({ phase: 'invalidate_snapshot' });
            if (modalErrorEl) {
                modalErrorEl.textContent = 'No pudimos confirmar si la lista se eliminó. Revisa el resultado antes de intentarlo nuevamente.';
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
        }

        function applyBlockedError(message) {
            modalState = DELETE_STATES.BLOCKED_REJECTED;
            setBotoneraState('blocked');
            if (closeBtnEl) {
                closeBtnEl.disabled = false;
            }
            if (modalErrorEl) {
                modalErrorEl.textContent = message;
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
        }

        function submitDelete() {
            if (modalState !== DELETE_STATES.CONFIRMING || !submittedSnapshot) {
                return;
            }

            modalState = DELETE_STATES.SUBMITTING;
            var currentSeq = ++deleteRequestSeq;
            var isSettled = false;
            var snapshot = submittedSnapshot;

            if (confirmBtn) {
                confirmBtn.disabled = true;
            }
            if (cancelBtn) {
                cancelBtn.disabled = true;
            }
            if (closeBtnEl) {
                closeBtnEl.disabled = true;
            }

            deleteAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            deleteTimeoutId = setTimeout(function () {
                if (isSettled || currentSeq !== deleteRequestSeq) {
                    return;
                }
                isSettled = true;
                if (deleteAbortController) {
                    deleteAbortController.abort();
                }
                transitionToUncertain();
            }, 15000);

            var formData = new FormData();
            formData.append('action', deleteContainerAction);
            formData.append('_wpnonce', nonce);
            formData.append('id', String(snapshot.containerId));
            formData.append('variant_key', variantKey);

            var fetchOptions = {
                method: 'POST',
                body: formData
            };
            if (deleteAbortController) {
                fetchOptions.signal = deleteAbortController.signal;
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
                    if (isSettled || currentSeq !== deleteRequestSeq) {
                        return;
                    }
                    isSettled = true;
                    if (deleteTimeoutId !== null) {
                        clearTimeout(deleteTimeoutId);
                        deleteTimeoutId = null;
                    }

                    if (response.ok && response.json && validateDeleteSuccess(response.json, snapshot)) {
                        cleanupDeleteRequest();
                        hideModal();
                        resetModalVisual();
                        transitionToIdle();
                        onRefreshRequested({
                            reason: 'confirmed',
                            containerId: snapshot.containerId,
                            sourcePage: snapshot.sourcePage
                        });
                        return;
                    }

                    if (response.json && response.json.success === false && response.json.data) {
                        var errCode = response.json.data.code;

                        if (errCode === 'not_found') {
                            cleanupDeleteRequest();
                            hideModal();
                            resetModalVisual();
                            transitionToIdle();
                            onRefreshRequested({
                                reason: 'not_found',
                                containerId: snapshot.containerId,
                                sourcePage: snapshot.sourcePage,
                                message: 'La lista ya no estaba disponible. Actualizamos el listado.'
                            });
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            applyBlockedError('La eliminación no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.');
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
                    if (isSettled || currentSeq !== deleteRequestSeq) {
                        return;
                    }
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    isSettled = true;
                    if (deleteTimeoutId !== null) {
                        clearTimeout(deleteTimeoutId);
                        deleteTimeoutId = null;
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
                            modalState = DELETE_STATES.REVIEWING_UNCERTAIN;
                            onRefreshRequested({
                                reason: 'review_gone',
                                containerId: context.containerId,
                                sourcePage: context.sourcePage,
                                reviewToken: reviewToken,
                                outcome: 'gone'
                            });
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            applyBlockedError('La revisión no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.');
                            showModal();
                            return;
                        }
                    }

                    if (response.ok && response.json && response.json.success === true && response.json.data && response.json.data.container) {
                        var container = response.json.data.container;
                        if (Number.isInteger(container.id) && container.id === context.containerId) {
                            modalState = DELETE_STATES.REVIEW_CONFIRMED_EXISTS;
                            onRefreshRequested({
                                reason: 'review_exists',
                                containerId: context.containerId,
                                sourcePage: context.sourcePage,
                                reviewToken: reviewToken,
                                outcome: 'exists'
                            });
                            return;
                        }
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
            if (!submittedSnapshot) {
                return;
            }
            var reviewToken = onReviewPending({
                containerId: submittedSnapshot.containerId,
                sourcePage: submittedSnapshot.sourcePage
            });
            if (!Number.isInteger(reviewToken) || reviewToken < 1) {
                return;
            }

            activeReviewToken = reviewToken;
            activeReviewContext = {
                reviewToken: reviewToken,
                containerId: submittedSnapshot.containerId,
                sourcePage: submittedSnapshot.sourcePage
            };
            modalState = DELETE_STATES.REVIEWING_UNCERTAIN;
            onFlowStateChange({ phase: 'delete_review' });
            cleanupDeleteRequest();
            hideModal();
            resetModalVisual();
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
            modalState = DELETE_STATES.REVIEWING_UNCERTAIN;
            onFlowStateChange({ phase: 'delete_review' });
            runReviewGet(activeReviewContext);
        }

        function retryReview() {
            if (!activeReviewContext || activeReviewToken !== activeReviewContext.reviewToken) {
                return;
            }
            if (modalState !== DELETE_STATES.REVIEWING_UNCERTAIN) {
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
            submittedSnapshot = null;
            if (outcome === 'exists') {
                modalState = DELETE_STATES.REVIEW_CONFIRMED_EXISTS;
                onFlowStateChange({ phase: 'exists_notice' });
            } else {
                modalState = DELETE_STATES.IDLE;
                onFlowStateChange({ phase: 'idle' });
            }
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                closeModal();
            });
        }

        if (closeBtnEl) {
            closeBtnEl.addEventListener('click', function () {
                closeModal();
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                submitDelete();
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
                closeModal();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modalEl && !modalEl.classList.contains('hidden')) {
                closeModal();
            }
        });

        function destroy() {
            document.removeEventListener('keydown', handleFocusTrap);
            clearFocusTimer();
            cleanupDeleteRequest();
            cleanupReviewRequest();
            deleteRequestSeq++;
            reviewRequestSeq++;
            hideModal();
            resetModalVisual();
            transitionToIdle();
        }

        return {
            openModal: openModal,
            closeModal: closeModal,
            resumeReview: resumeReview,
            retryReview: retryReview,
            abortActiveReviewRequest: abortActiveReviewRequest,
            completeListSettlement: completeListSettlement,
            destroy: destroy
        };
    }

    window.AA_FinanceContainerDelete = {
        createController: createController
    };
})();
