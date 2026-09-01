/**
 * Finance Record Delete Module — Controlador de eliminación de registros financieros.
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
        'invalid_container_id',
        'invalid_record_id',
        'invalid_variant_key',
        'unknown_variant',
        'container_not_found'
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
        var isDeleteAllowed = typeof options.isDeleteAllowed === 'function'
            ? options.isDeleteAllowed
            : function () { return true; };
        var onDeleteActiveStart = typeof options.onDeleteActiveStart === 'function'
            ? options.onDeleteActiveStart
            : function () {};
        var onDeleteActiveEnd = typeof options.onDeleteActiveEnd === 'function'
            ? options.onDeleteActiveEnd
            : function () {};
        var onDeleteConfirmedRefresh = typeof options.onDeleteConfirmedRefresh === 'function'
            ? options.onDeleteConfirmedRefresh
            : function () {};
        var onDeleteRecordNotFoundRefresh = typeof options.onDeleteRecordNotFoundRefresh === 'function'
            ? options.onDeleteRecordNotFoundRefresh
            : function () {};
        var onDeleteUncertainInvalidated = typeof options.onDeleteUncertainInvalidated === 'function'
            ? options.onDeleteUncertainInvalidated
            : function () {};
        var onUncertainReviewStart = typeof options.onUncertainReviewStart === 'function'
            ? options.onUncertainReviewStart
            : function () { return null; };
        var onReviewAwaitingListSettlement = typeof options.onReviewAwaitingListSettlement === 'function'
            ? options.onReviewAwaitingListSettlement
            : function () {};
        var onReviewGetUncertain = typeof options.onReviewGetUncertain === 'function'
            ? options.onReviewGetUncertain
            : function () {};
        var onReviewRecordExistsNotice = typeof options.onReviewRecordExistsNotice === 'function'
            ? options.onReviewRecordExistsNotice
            : function () {};
        var onContainerNotFoundDelete = typeof options.onContainerNotFoundDelete === 'function'
            ? options.onContainerNotFoundDelete
            : function () {};
        var onFocusDetailStatus = typeof options.onFocusDetailStatus === 'function'
            ? options.onFocusDetailStatus
            : function () {};
        var onIdle = typeof options.onIdle === 'function'
            ? options.onIdle
            : function () {};

        var modalEl = elements.modal || null;
        var backdropEl = elements.backdrop || null;
        var closeBtnEl = elements.closeBtn || null;
        var titleEl = elements.title || null;
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
        var deleteRecordAction = cfg.actions && cfg.actions.deleteRecord;
        var getRecordAction = cfg.actions && cfg.actions.getRecord;

        var recordDeleteModalState = DELETE_STATES.IDLE;
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
                if (cancelBtn && recordDeleteModalState === DELETE_STATES.CONFIRMING) {
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
                return 'Sin importe';
            }
            return String(amount);
        }

        function validateSnapshot(snapshot) {
            if (!snapshot || typeof snapshot !== 'object') {
                return false;
            }
            if (!Number.isInteger(snapshot.recordId) || snapshot.recordId < 1) {
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
            if (snapshot.amount !== null && typeof snapshot.amount !== 'string') {
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
            if (!Number.isInteger(data.id) || data.id !== snapshot.recordId) {
                return false;
            }
            if (!Number.isInteger(data.container_id) || data.container_id !== snapshot.containerId) {
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
            onFocusDetailStatus();
        }

        function resetModalVisual() {
            if (modalErrorEl) {
                modalErrorEl.textContent = '';
                modalErrorEl.classList.add('hidden');
            }
            if (titleEl) {
                titleEl.textContent = '';
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
            recordDeleteModalState = DELETE_STATES.IDLE;
            submittedSnapshot = null;
            originDeleteButton = null;
            activeReviewToken = null;
            activeReviewContext = null;
            onDeleteActiveEnd();
            onIdle();
        }

        function closeModalInternal(releaseActive) {
            clearFocusTimer();
            cleanupDeleteRequest();
            hideModal();
            resetModalVisual();
            var willTransitionIdle = (
                recordDeleteModalState === DELETE_STATES.CONFIRMING ||
                recordDeleteModalState === DELETE_STATES.BLOCKED_REJECTED ||
                recordDeleteModalState === DELETE_STATES.REVIEW_CONFIRMED_EXISTS
            );
            if (releaseActive && !willTransitionIdle && recordDeleteModalState !== DELETE_STATES.REVIEWING_UNCERTAIN) {
                onDeleteActiveEnd();
            }
            if (willTransitionIdle) {
                transitionToIdle();
                restoreOriginFocus();
            }
        }

        function openModal(recordSnapshot, originButton) {
            if (!modalEl || !deleteRecordAction || !getRecordAction) {
                return;
            }
            if (!isDetailActive() || !isDeleteAllowed()) {
                return;
            }
            if (
                recordDeleteModalState !== DELETE_STATES.IDLE &&
                recordDeleteModalState !== DELETE_STATES.REVIEW_CONFIRMED_EXISTS
            ) {
                return;
            }
            if (!validateSnapshot(recordSnapshot)) {
                return;
            }

            submittedSnapshot = {
                recordId: recordSnapshot.recordId,
                containerId: recordSnapshot.containerId,
                title: recordSnapshot.title,
                amount: recordSnapshot.amount,
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
                bodyEl.textContent = 'Se eliminará permanentemente la entrada «' + submittedSnapshot.title + '» de esta lista. Esta acción no se puede deshacer.';
            }
            if (amountEl) {
                amountEl.textContent = formatAmountDisplay(submittedSnapshot.amount);
                amountEl.classList.remove('hidden');
            }

            recordDeleteModalState = DELETE_STATES.CONFIRMING;
            onDeleteActiveStart();
            showModal();
            scheduleCancelFocus();
        }

        function closeModal() {
            if (recordDeleteModalState === DELETE_STATES.SUBMITTING || recordDeleteModalState === DELETE_STATES.UNCERTAIN) {
                return;
            }
            closeModalInternal(true);
        }

        function transitionToUncertain() {
            recordDeleteModalState = DELETE_STATES.UNCERTAIN;
            setBotoneraState('uncertain');
            onDeleteUncertainInvalidated();
            if (modalErrorEl) {
                modalErrorEl.textContent = 'No pudimos confirmar si la entrada se eliminó. Revisa el resultado antes de intentarlo nuevamente.';
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
        }

        function applyBlockedError(message, notifyContainerNotFound) {
            recordDeleteModalState = DELETE_STATES.BLOCKED_REJECTED;
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
                onContainerNotFoundDelete(submittedSnapshot.containerId);
            }
        }

        function submitDelete() {
            if (recordDeleteModalState !== DELETE_STATES.CONFIRMING || !submittedSnapshot) {
                return;
            }

            recordDeleteModalState = DELETE_STATES.SUBMITTING;
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
            formData.append('action', deleteRecordAction);
            formData.append('_wpnonce', nonce);
            formData.append('container_id', String(snapshot.containerId));
            formData.append('record_id', String(snapshot.recordId));
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
                        onDeleteConfirmedRefresh(snapshot.containerId, snapshot.sourcePage);
                        return;
                    }

                    if (response.json && response.json.success === false && response.json.data) {
                        var errCode = response.json.data.code;
                        var errMsg = response.json.data.message || 'No se pudo eliminar la entrada.';

                        if (errCode === 'record_not_found') {
                            cleanupDeleteRequest();
                            hideModal();
                            resetModalVisual();
                            transitionToIdle();
                            onDeleteRecordNotFoundRefresh(snapshot.containerId, snapshot.sourcePage);
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            var blockedMsg = 'La eliminación no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.';
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
            formData.append('container_id', String(context.containerId));
            formData.append('record_id', String(context.recordId));
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
                            recordDeleteModalState = DELETE_STATES.REVIEWING_UNCERTAIN;
                            onReviewAwaitingListSettlement({
                                reviewToken: reviewToken,
                                containerId: context.containerId,
                                sourcePage: context.sourcePage,
                                outcome: 'gone'
                            });
                            return;
                        }

                        if (errCode === 'container_not_found') {
                            cancelReview();
                            onContainerNotFoundDelete(context.containerId);
                            return;
                        }

                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            cancelReview();
                            onContainerNotFoundDelete(context.containerId);
                            return;
                        }
                    }

                    if (response.ok && response.json && response.json.success === true && response.json.data && response.json.data.record) {
                        var record = response.json.data.record;
                        if (
                            Number.isInteger(record.id) &&
                            record.id === context.recordId &&
                            Number.isInteger(record.container_id) &&
                            record.container_id === context.containerId
                        ) {
                            recordDeleteModalState = DELETE_STATES.REVIEW_CONFIRMED_EXISTS;
                            onReviewAwaitingListSettlement({
                                reviewToken: reviewToken,
                                containerId: context.containerId,
                                sourcePage: context.sourcePage,
                                outcome: 'exists'
                            });
                            return;
                        }
                    }

                    onReviewGetUncertain(reviewToken);
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
                    onReviewGetUncertain(reviewToken);
                });
        }

        function startUncertainReview() {
            if (!submittedSnapshot) {
                return;
            }
            var reviewToken = onUncertainReviewStart({
                containerId: submittedSnapshot.containerId,
                recordId: submittedSnapshot.recordId,
                sourcePage: submittedSnapshot.sourcePage
            });
            if (!Number.isInteger(reviewToken) || reviewToken < 1) {
                return;
            }

            activeReviewToken = reviewToken;
            activeReviewContext = {
                reviewToken: reviewToken,
                containerId: submittedSnapshot.containerId,
                recordId: submittedSnapshot.recordId,
                sourcePage: submittedSnapshot.sourcePage
            };
            recordDeleteModalState = DELETE_STATES.REVIEWING_UNCERTAIN;
            cleanupDeleteRequest();
            hideModal();
            resetModalVisual();
            onFocusDetailStatus();
            runReviewGet(activeReviewContext);
        }

        function abortActiveReviewRequest() {
            cleanupReviewRequest();
            reviewRequestSeq++;
        }

        function resumeReview(pendingDeleteReview) {
            if (!pendingDeleteReview) {
                return;
            }
            if (!Number.isInteger(pendingDeleteReview.reviewToken) || pendingDeleteReview.reviewToken < 1) {
                return;
            }
            activeReviewToken = pendingDeleteReview.reviewToken;
            activeReviewContext = {
                reviewToken: pendingDeleteReview.reviewToken,
                containerId: pendingDeleteReview.containerId,
                recordId: pendingDeleteReview.recordId,
                sourcePage: pendingDeleteReview.sourcePage
            };
            recordDeleteModalState = DELETE_STATES.REVIEWING_UNCERTAIN;
            runReviewGet(activeReviewContext);
        }

        function retryReview() {
            if (!activeReviewContext || activeReviewToken !== activeReviewContext.reviewToken) {
                return;
            }
            if (recordDeleteModalState !== DELETE_STATES.REVIEWING_UNCERTAIN) {
                return;
            }
            runReviewGet(activeReviewContext);
        }

        function cancelReview() {
            cleanupReviewRequest();
            reviewRequestSeq++;
            activeReviewToken = null;
            activeReviewContext = null;
            submittedSnapshot = null;
            recordDeleteModalState = DELETE_STATES.IDLE;
            onDeleteActiveEnd();
            onIdle();
        }

        function completeReviewSettlement(reviewToken, outcome) {
            if (activeReviewToken !== reviewToken) {
                return;
            }
            cleanupReviewRequest();
            reviewRequestSeq++;
            activeReviewToken = null;
            activeReviewContext = null;
            submittedSnapshot = null;
            if (outcome === 'exists') {
                recordDeleteModalState = DELETE_STATES.REVIEW_CONFIRMED_EXISTS;
                onReviewRecordExistsNotice();
            } else {
                recordDeleteModalState = DELETE_STATES.IDLE;
            }
            onDeleteActiveEnd();
            onIdle();
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
            cancelReview: cancelReview,
            abortActiveReviewRequest: abortActiveReviewRequest,
            completeReviewSettlement: completeReviewSettlement,
            destroy: destroy
        };
    }

    window.AA_FinanceRecordDelete = {
        createController: createController
    };
})();
