/**
 * Finance Canonical Module — Controlador JavaScript del listado y creación de Contenedores.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical\Finance
 */
(function () {
    'use strict';

    var root = document.getElementById('aa-finance-root');
    if (!root) {
        return;
    }

    var statusEl = document.getElementById('aa-finance-status');
    var listContainerEl = document.getElementById('aa-finance-list-container');
    var gridEl = document.getElementById('aa-finance-grid');
    var paginationEl = document.getElementById('aa-finance-pagination');
    var prevBtn = document.getElementById('aa-finance-prev');
    var nextBtn = document.getElementById('aa-finance-next');
    var pageIndicatorEl = document.getElementById('aa-finance-page-indicator');

    var openCreateBtn = document.getElementById('aa-finance-open-create-btn');
    var createModal = document.getElementById('aa-finance-create-modal');
    var modalBackdrop = document.getElementById('aa-finance-modal-backdrop');
    var modalCloseBtn = document.getElementById('aa-finance-modal-close-btn');
    var createForm = document.getElementById('aa-finance-create-form');
    var modalErrorEl = document.getElementById('aa-finance-modal-error');
    var titleInput = document.getElementById('aa-finance-create-title');
    var titleErrorEl = document.getElementById('aa-finance-title-error');
    var detailsInput = document.getElementById('aa-finance-create-details');
    var detailsErrorEl = document.getElementById('aa-finance-details-error');

    var standardActionsEl = document.getElementById('aa-finance-modal-actions-standard');
    var cancelBtn = document.getElementById('aa-finance-modal-cancel-btn');
    var submitBtn = document.getElementById('aa-finance-modal-submit-btn');

    var uncertainActionsEl = document.getElementById('aa-finance-modal-actions-uncertain');
    var uncertainCloseBtn = document.getElementById('aa-finance-modal-uncertain-close-btn');

    var blockedActionsEl = document.getElementById('aa-finance-modal-actions-blocked');
    var blockedCloseBtn = document.getElementById('aa-finance-modal-blocked-close-btn');

    var recordsContainerEl = document.getElementById('aa-finance-records-container');
    var recordsBackBtn = document.getElementById('aa-finance-records-back');
    var recordsHeadingEl = document.getElementById('aa-finance-records-heading');
    var recordsSummaryEl = document.getElementById('aa-finance-records-summary');
    var recordsStatusEl = document.getElementById('aa-finance-records-status');
    var recordsGridEl = document.getElementById('aa-finance-records-grid');
    var recordsPaginationEl = document.getElementById('aa-finance-records-pagination');
    var recordsPrevBtn = document.getElementById('aa-finance-records-prev');
    var recordsNextBtn = document.getElementById('aa-finance-records-next');
    var recordsPageIndicatorEl = document.getElementById('aa-finance-records-page-indicator');

    var openRecordBtn = document.getElementById('aa-finance-open-record-btn');
    var recordCreateModal = document.getElementById('aa-finance-record-create-modal');
    var recordModalBackdrop = document.getElementById('aa-finance-record-create-modal-backdrop');
    var recordModalCloseBtn = document.getElementById('aa-finance-record-create-close');
    var recordCreateForm = document.getElementById('aa-finance-record-create-form');
    var recordModalErrorEl = document.getElementById('aa-finance-record-create-error');
    var recordTitleInput = document.getElementById('aa-finance-record-create-title');
    var recordTitleErrorEl = document.getElementById('aa-finance-record-title-error');
    var recordDetailsInput = document.getElementById('aa-finance-record-create-details');
    var recordDetailsErrorEl = document.getElementById('aa-finance-record-details-error');
    var recordAmountInput = document.getElementById('aa-finance-record-create-amount');
    var recordAmountErrorEl = document.getElementById('aa-finance-record-amount-error');
    var recordStandardActionsEl = document.getElementById('aa-finance-record-create-actions-standard');
    var recordCancelBtn = document.getElementById('aa-finance-record-create-cancel');
    var recordSubmitBtn = document.getElementById('aa-finance-record-create-submit');
    var recordUncertainActionsEl = document.getElementById('aa-finance-record-create-actions-uncertain');
    var recordUncertainCloseBtn = document.getElementById('aa-finance-record-create-uncertain-close');
    var recordBlockedActionsEl = document.getElementById('aa-finance-record-create-actions-blocked');
    var recordBlockedCloseBtn = document.getElementById('aa-finance-record-create-blocked-close');

    var recordDeleteModal = document.getElementById('aa-finance-record-delete-modal');
    var recordDeleteBackdrop = document.getElementById('aa-finance-record-delete-modal-backdrop');
    var recordDeleteCloseBtn = document.getElementById('aa-finance-record-delete-close');
    var recordDeleteTitleEl = document.getElementById('aa-finance-record-delete-modal-title');
    var recordDeleteBodyEl = document.getElementById('aa-finance-record-delete-body');
    var recordDeleteAmountEl = document.getElementById('aa-finance-record-delete-amount');
    var recordDeleteErrorEl = document.getElementById('aa-finance-record-delete-error');
    var recordDeleteStandardActionsEl = document.getElementById('aa-finance-record-delete-actions-standard');
    var recordDeleteCancelBtn = document.getElementById('aa-finance-record-delete-cancel');
    var recordDeleteConfirmBtn = document.getElementById('aa-finance-record-delete-confirm');
    var recordDeleteUncertainActionsEl = document.getElementById('aa-finance-record-delete-actions-uncertain');
    var recordDeleteUncertainCloseBtn = document.getElementById('aa-finance-record-delete-uncertain-close');
    var recordDeleteBlockedActionsEl = document.getElementById('aa-finance-record-delete-actions-blocked');
    var recordDeleteBlockedCloseBtn = document.getElementById('aa-finance-record-delete-blocked-close');

    function showFatalConfigError() {
        if (statusEl) {
            statusEl.textContent = 'No se pudo iniciar el módulo de Finanzas.';
            statusEl.className = 'text-sm text-red-600 font-medium';
        }
    }

    var cfg = window.AA_FINANCE_DATA;
    if (!cfg || typeof cfg !== 'object') {
        showFatalConfigError();
        return;
    }

    var ajaxUrl = cfg.ajaxUrl;
    var nonce = cfg.nonce;
    var familyKey = cfg.familyKey;
    var variantKey = cfg.variantKey;
    var actions = cfg.actions;

    if (
        typeof ajaxUrl !== 'string' || ajaxUrl.trim() === '' ||
        typeof nonce !== 'string' || nonce.trim() === '' ||
        familyKey !== 'finance' ||
        typeof variantKey !== 'string' || variantKey.trim() === '' ||
        !actions || typeof actions !== 'object' ||
        typeof actions.listContainers !== 'string' || actions.listContainers.trim() === ''
    ) {
        showFatalConfigError();
        return;
    }

    var hasCreateAction = typeof actions.createContainer === 'string' && actions.createContainer.trim() !== '';
    if (!hasCreateAction && openCreateBtn) {
        openCreateBtn.classList.add('hidden');
        openCreateBtn.hidden = true;
    }

    if (root.dataset.aaInitialized === 'true') {
        return;
    }
    root.dataset.aaInitialized = 'true';

    var NAV_MODES = {
        CONTAINER_LIST: 'CONTAINER_LIST',
        RECORDS_DETAIL: 'RECORDS_DETAIL'
    };

    var navMode = NAV_MODES.CONTAINER_LIST;
    var listSnapshotValid = true;
    var listStatusOverride = null;
    var selectedContainerId = null;
    var originOpenButton = null;
    var recordsController = null;
    var recordsNavEnabled = false;
    var recordCreateController = null;
    var recordCreateEnabled = false;
    var nextRecordReviewToken = 1;
    var pendingRecordReview = null;
    var nextDeleteReviewToken = 1;
    var pendingDeleteReview = null;
    var recordMutationLock = 'none';
    var recordDeleteController = null;
    var recordDeleteEnabled = false;

    var PENDING_REVIEW_NOTICE = 'Hay una entrada pendiente de revisión en otra lista. Vuelve a esa lista antes de crear una nueva.';
    var PENDING_DELETE_REVIEW_NOTICE = 'Hay una eliminación pendiente de revisión en otra lista. Vuelve a esa lista para continuar.';

    // Estados formales de creación de contenedores
    var CREATE_STATES = {
        IDLE: 'IDLE',
        EDITING: 'EDITING',
        SUBMITTING: 'SUBMITTING',
        FIELD_REJECTED: 'FIELD_REJECTED',
        BLOCKED_REJECTED: 'BLOCKED_REJECTED',
        CONFIRMED: 'CONFIRMED',
        UNCERTAIN: 'UNCERTAIN',
        REVIEWING_UNCERTAIN: 'REVIEWING_UNCERTAIN',
        DRAFT_REVIEWED: 'DRAFT_REVIEWED'
    };

    var createModalState = CREATE_STATES.IDLE;
    var savedDraft = { title: '', details: '' };

    var confirmedPage = 1;
    var requestedPage = 1;
    var failedPage = null;
    var requestSeq = 0;
    var listAbortController = null;
    var isFetchingList = false;

    var createAbortController = null;
    var createTimeoutId = null;
    var createRequestSeq = 0;
    var focusTimeoutId = null;
    var recordsFocusTimeoutId = null;

    function clearRecordsFocusTimer() {
        if (recordsFocusTimeoutId !== null) {
            clearTimeout(recordsFocusTimeoutId);
            recordsFocusTimeoutId = null;
        }
    }

    function scheduleRecordsHeadingFocus() {
        clearRecordsFocusTimer();
        recordsFocusTimeoutId = setTimeout(function () {
            recordsFocusTimeoutId = null;
            if (navMode !== NAV_MODES.RECORDS_DETAIL || !recordsHeadingEl) {
                return;
            }
            recordsHeadingEl.focus();
        }, 50);
    }

    function hasRecordsMarkup() {
        return !!(
            recordsContainerEl &&
            recordsBackBtn &&
            recordsHeadingEl &&
            recordsSummaryEl &&
            recordsStatusEl &&
            recordsGridEl &&
            recordsPaginationEl &&
            recordsPrevBtn &&
            recordsNextBtn &&
            recordsPageIndicatorEl
        );
    }

    function hasRecordsFactory() {
        return !!(window.AA_FinanceRecords && typeof window.AA_FinanceRecords.createController === 'function');
    }

    function hasListRecordsAction() {
        return !!(actions && typeof actions.listRecords === 'string' && actions.listRecords.trim() !== '');
    }

    function hasRecordCreateAction() {
        return !!(actions && typeof actions.createRecord === 'string' && actions.createRecord.trim() !== '');
    }

    function hasRecordCreateMarkup() {
        return !!(
            openRecordBtn &&
            recordCreateModal &&
            recordModalBackdrop &&
            recordModalCloseBtn &&
            recordCreateForm &&
            recordModalErrorEl &&
            recordTitleInput &&
            recordTitleErrorEl &&
            recordDetailsInput &&
            recordDetailsErrorEl &&
            recordAmountInput &&
            recordAmountErrorEl &&
            recordStandardActionsEl &&
            recordCancelBtn &&
            recordSubmitBtn &&
            recordUncertainActionsEl &&
            recordUncertainCloseBtn &&
            recordBlockedActionsEl &&
            recordBlockedCloseBtn
        );
    }

    function hasRecordCreateFactory() {
        return !!(window.AA_FinanceRecordCreate && typeof window.AA_FinanceRecordCreate.createController === 'function');
    }

    function hasRecordDeleteAction() {
        return !!(
            actions &&
            typeof actions.deleteRecord === 'string' && actions.deleteRecord.trim() !== '' &&
            typeof actions.getRecord === 'string' && actions.getRecord.trim() !== ''
        );
    }

    function hasRecordDeleteMarkup() {
        return !!(
            recordDeleteModal &&
            recordDeleteBackdrop &&
            recordDeleteCloseBtn &&
            recordDeleteTitleEl &&
            recordDeleteBodyEl &&
            recordDeleteAmountEl &&
            recordDeleteErrorEl &&
            recordDeleteStandardActionsEl &&
            recordDeleteCancelBtn &&
            recordDeleteConfirmBtn &&
            recordDeleteUncertainActionsEl &&
            recordDeleteUncertainCloseBtn &&
            recordDeleteBlockedActionsEl &&
            recordDeleteBlockedCloseBtn
        );
    }

    function hasRecordDeleteFactory() {
        return !!(window.AA_FinanceRecordDelete && typeof window.AA_FinanceRecordDelete.createController === 'function');
    }

    function deriveBaseMutationLock() {
        if (pendingRecordReview) {
            return 'create_review';
        }
        if (pendingDeleteReview) {
            return 'delete_review';
        }
        return 'none';
    }

    function applyMutationLock(lock) {
        recordMutationLock = lock;
        syncMutationUI();
    }

    function syncMutationUI() {
        setRecordCreateTriggerVisible(navMode === NAV_MODES.RECORDS_DETAIL);
        if (recordsController && typeof recordsController.setDeleteActionsEnabled === 'function') {
            recordsController.setDeleteActionsEnabled(recordMutationLock === 'none');
        }
    }

    function isRecordCreateGloballyBlocked() {
        return recordMutationLock !== 'none';
    }

    function isRecordDeleteGloballyBlocked() {
        return recordMutationLock !== 'none';
    }

    function focusRecordsDetailHeading() {
        if (recordsHeadingEl) {
            recordsHeadingEl.focus();
        }
    }

    function focusRecordsDetailStatus() {
        if (recordsStatusEl) {
            recordsStatusEl.focus();
        }
    }

    function showDeleteReviewRetryNotice(reviewToken) {
        if (!recordsStatusEl) {
            return;
        }
        while (recordsStatusEl.firstChild) {
            recordsStatusEl.removeChild(recordsStatusEl.firstChild);
        }
        recordsStatusEl.className = 'text-sm text-amber-700 font-medium flex items-center gap-2 flex-wrap';
        var msgSpan = document.createElement('span');
        msgSpan.textContent = 'No pudimos confirmar el estado de la entrada. Revisa el listado antes de intentarlo nuevamente.';
        recordsStatusEl.appendChild(msgSpan);
        var retryBtn = document.createElement('button');
        retryBtn.type = 'button';
        retryBtn.className = 'underline hover:text-amber-900 text-xs font-semibold focus:outline-none';
        retryBtn.textContent = 'Reintentar revisión';
        retryBtn.addEventListener('click', function () {
            if (!pendingDeleteReview || pendingDeleteReview.reviewToken !== reviewToken) {
                return;
            }
            if (recordDeleteController) {
                recordDeleteController.retryReview();
            }
        });
        recordsStatusEl.appendChild(retryBtn);
    }

    function setRecordCreateTriggerVisible(isVisible) {
        if (!openRecordBtn || !hasRecordCreateAction() || !recordCreateEnabled) {
            if (openRecordBtn) {
                openRecordBtn.classList.add('hidden');
                openRecordBtn.hidden = true;
            }
            return;
        }
        if (isVisible && navMode === NAV_MODES.RECORDS_DETAIL) {
            openRecordBtn.classList.remove('hidden');
            openRecordBtn.hidden = false;
            if (isRecordCreateGloballyBlocked()) {
                openRecordBtn.disabled = true;
                openRecordBtn.setAttribute('aria-disabled', 'true');
            } else {
                openRecordBtn.disabled = false;
                openRecordBtn.removeAttribute('aria-disabled');
            }
        } else {
            openRecordBtn.classList.add('hidden');
            openRecordBtn.hidden = true;
            openRecordBtn.setAttribute('aria-disabled', 'true');
        }
    }

    function handlePendingReviewSettlement(payload) {
        if (pendingRecordReview && payload.containerId !== pendingRecordReview.containerId) {
            if (
                navMode === NAV_MODES.RECORDS_DETAIL &&
                selectedContainerId === payload.containerId &&
                (payload.phase === 'READY' || payload.phase === 'EMPTY')
            ) {
                if (recordsStatusEl) {
                    recordsStatusEl.textContent = PENDING_REVIEW_NOTICE;
                    recordsStatusEl.className = 'text-sm text-amber-700 font-medium';
                }
            }
            return;
        }
        if (!pendingRecordReview) {
            return;
        }
        if (payload.containerId !== pendingRecordReview.containerId) {
            return;
        }
        if (payload.page !== pendingRecordReview.awaitingPage) {
            return;
        }
        if (navMode !== NAV_MODES.RECORDS_DETAIL) {
            return;
        }
        if (selectedContainerId !== pendingRecordReview.containerId) {
            return;
        }

        if (payload.phase === 'READY' || payload.phase === 'EMPTY') {
            var reviewToken = pendingRecordReview.reviewToken;
            var containerId = pendingRecordReview.containerId;
            pendingRecordReview = null;
            applyMutationLock(deriveBaseMutationLock());
            if (recordCreateController) {
                recordCreateController.completeReview(reviewToken, containerId);
            }
            setRecordCreateTriggerVisible(true);
            return;
        }

        if (payload.phase === 'NOT_FOUND') {
            handleRecordReviewNotFound(pendingRecordReview.reviewToken, pendingRecordReview.containerId);
        }
    }

    function handlePendingDeleteReviewSettlement(payload) {
        if (pendingDeleteReview && payload.containerId !== pendingDeleteReview.containerId) {
            if (
                navMode === NAV_MODES.RECORDS_DETAIL &&
                selectedContainerId === payload.containerId &&
                (payload.phase === 'READY' || payload.phase === 'EMPTY')
            ) {
                if (recordsStatusEl) {
                    recordsStatusEl.textContent = PENDING_DELETE_REVIEW_NOTICE;
                    recordsStatusEl.className = 'text-sm text-amber-700 font-medium';
                }
            }
            return;
        }
        if (!pendingDeleteReview || !pendingDeleteReview.awaitingListSettlement) {
            return;
        }
        if (payload.containerId !== pendingDeleteReview.containerId) {
            return;
        }
        if (navMode !== NAV_MODES.RECORDS_DETAIL) {
            return;
        }
        if (selectedContainerId !== pendingDeleteReview.containerId) {
            return;
        }
        if (payload.phase !== 'READY' && payload.phase !== 'EMPTY') {
            return;
        }

        var deleteReviewToken = pendingDeleteReview.reviewToken;
        var deleteOutcome = pendingDeleteReview.outcome;
        pendingDeleteReview = null;
        applyMutationLock(deriveBaseMutationLock());
        if (recordDeleteController) {
            recordDeleteController.completeReviewSettlement(deleteReviewToken, deleteOutcome);
        }
    }

    function handleDeleteReviewNotFound(reviewToken, containerId) {
        listSnapshotValid = false;
        pendingDeleteReview = null;
        applyMutationLock(deriveBaseMutationLock());
        if (recordDeleteController) {
            recordDeleteController.cancelReview();
        }
        backToContainerListWithMessage('La lista ya no está disponible. La eliminación pendiente no pudo conservarse.');
    }

    function handleRecordReviewNotFound(reviewToken, containerId) {
        listSnapshotValid = false;
        pendingRecordReview = null;
        applyMutationLock(deriveBaseMutationLock());
        if (recordCreateController) {
            recordCreateController.cancelReview(reviewToken, 'container_not_found');
        }
        setRecordCreateTriggerVisible(false);
        backToContainerListWithMessage('La lista ya no está disponible. La entrada pendiente no pudo conservarse.');
    }

    function backToContainerListWithMessage(message) {
        if (recordsController) {
            recordsController.close();
        }
        clearRecordsFocusTimer();
        navMode = NAV_MODES.CONTAINER_LIST;
        selectedContainerId = null;
        setRecordsViewVisible(false);
        setListViewVisible(true);
        setCreateTriggerVisible(true);
        setRecordCreateTriggerVisible(false);
        if (statusEl) {
            statusEl.textContent = message;
            statusEl.className = 'text-sm text-red-600 font-medium';
            listStatusOverride = message;
            statusEl.focus();
        }
        loadPage(confirmedPage);
        originOpenButton = null;
    }

    function initRecordsController() {
        if (!hasRecordsFactory() || !hasListRecordsAction() || !hasRecordsMarkup()) {
            recordsNavEnabled = false;
            return;
        }

        recordsController = window.AA_FinanceRecords.createController({
            cfg: cfg,
            elements: {
                heading: recordsHeadingEl,
                summary: recordsSummaryEl,
                status: recordsStatusEl,
                grid: recordsGridEl,
                pagination: recordsPaginationEl,
                prev: recordsPrevBtn,
                next: recordsNextBtn,
                pageIndicator: recordsPageIndicatorEl
            },
            isActiveGuard: function () {
                return navMode === NAV_MODES.RECORDS_DETAIL;
            },
            onContainerNotFound: function () {
                listSnapshotValid = false;
            },
            onAuthoritativeLoadSettled: function (payload) {
                handlePendingReviewSettlement(payload);
                handlePendingDeleteReviewSettlement(payload);
                if (payload.phase === 'READY' || payload.phase === 'EMPTY') {
                    scheduleRecordsHeadingFocus();
                }
            },
            onRecordDeleteIntent: function (snapshot, originButton) {
                if (!recordDeleteController || isRecordDeleteGloballyBlocked()) {
                    return;
                }
                recordDeleteController.openModal(snapshot, originButton);
            },
            isDeleteActionEnabled: function () {
                return recordMutationLock === 'none';
            }
        });
        recordsNavEnabled = !!recordsController;
    }

    function initRecordCreateController() {
        if (!hasRecordCreateFactory() || !hasRecordCreateAction() || !hasRecordCreateMarkup()) {
            recordCreateEnabled = false;
            if (openRecordBtn) {
                openRecordBtn.classList.add('hidden');
                openRecordBtn.hidden = true;
            }
            return;
        }

        recordCreateController = window.AA_FinanceRecordCreate.createController({
            cfg: cfg,
            elements: {
                modal: recordCreateModal,
                backdrop: recordModalBackdrop,
                closeBtn: recordModalCloseBtn,
                form: recordCreateForm,
                modalError: recordModalErrorEl,
                titleInput: recordTitleInput,
                titleError: recordTitleErrorEl,
                detailsInput: recordDetailsInput,
                detailsError: recordDetailsErrorEl,
                amountInput: recordAmountInput,
                amountError: recordAmountErrorEl,
                standardActions: recordStandardActionsEl,
                cancelBtn: recordCancelBtn,
                submitBtn: recordSubmitBtn,
                uncertainActions: recordUncertainActionsEl,
                uncertainCloseBtn: recordUncertainCloseBtn,
                blockedActions: recordBlockedActionsEl,
                blockedCloseBtn: recordBlockedCloseBtn,
                openTrigger: openRecordBtn
            },
            getActiveContainerId: function () {
                return selectedContainerId;
            },
            isDetailActive: function () {
                return navMode === NAV_MODES.RECORDS_DETAIL;
            },
            onConfirmedRefresh: function (containerId) {
                if (recordsController && Number.isInteger(containerId) && containerId >= 1) {
                    recordsController.open(containerId, 1);
                }
            },
            onUncertainReviewStart: function (payload) {
                if (navMode !== NAV_MODES.RECORDS_DETAIL) {
                    return null;
                }
                if (!Number.isInteger(payload.containerId) || payload.containerId < 1) {
                    return null;
                }
                if (selectedContainerId !== payload.containerId) {
                    return null;
                }
                nextRecordReviewToken++;
                pendingRecordReview = {
                    reviewToken: nextRecordReviewToken,
                    containerId: payload.containerId,
                    awaitingPage: 1
                };
                applyMutationLock('create_review');
                setRecordCreateTriggerVisible(true);
                if (recordsController) {
                    recordsController.open(payload.containerId, 1);
                }
                return nextRecordReviewToken;
            },
            onContainerNotFoundCreate: function (containerId) {
                listSnapshotValid = false;
                if (pendingRecordReview && pendingRecordReview.containerId === containerId) {
                    handleRecordReviewNotFound(pendingRecordReview.reviewToken, containerId);
                    return;
                }
                backToContainerListWithMessage('La lista ya no está disponible.');
            }
        });
        recordCreateEnabled = !!recordCreateController;

        if (openRecordBtn) {
            openRecordBtn.addEventListener('click', function () {
                if (openRecordBtn.disabled || !recordCreateController) {
                    return;
                }
                recordCreateController.openModal();
            });
        }
    }

    function initRecordDeleteController() {
        if (!hasRecordDeleteFactory() || !hasRecordDeleteAction() || !hasRecordDeleteMarkup()) {
            recordDeleteEnabled = false;
            return;
        }

        recordDeleteController = window.AA_FinanceRecordDelete.createController({
            cfg: cfg,
            elements: {
                modal: recordDeleteModal,
                backdrop: recordDeleteBackdrop,
                closeBtn: recordDeleteCloseBtn,
                title: recordDeleteTitleEl,
                body: recordDeleteBodyEl,
                amount: recordDeleteAmountEl,
                modalError: recordDeleteErrorEl,
                standardActions: recordDeleteStandardActionsEl,
                cancelBtn: recordDeleteCancelBtn,
                confirmBtn: recordDeleteConfirmBtn,
                uncertainActions: recordDeleteUncertainActionsEl,
                uncertainCloseBtn: recordDeleteUncertainCloseBtn,
                blockedActions: recordDeleteBlockedActionsEl,
                blockedCloseBtn: recordDeleteBlockedCloseBtn
            },
            isDetailActive: function () {
                return navMode === NAV_MODES.RECORDS_DETAIL;
            },
            isDeleteAllowed: function () {
                return recordMutationLock === 'none';
            },
            onDeleteActiveStart: function () {
                applyMutationLock('delete_active');
            },
            onDeleteActiveEnd: function () {
                applyMutationLock(deriveBaseMutationLock());
            },
            onDeleteConfirmedRefresh: function (containerId, sourcePage) {
                listSnapshotValid = false;
                if (recordsController && Number.isInteger(containerId) && containerId >= 1) {
                    recordsController.open(containerId, sourcePage);
                }
            },
            onDeleteRecordNotFoundRefresh: function (containerId, sourcePage) {
                listSnapshotValid = false;
                if (recordsStatusEl) {
                    recordsStatusEl.textContent = 'La entrada ya no estaba disponible. Actualizamos la lista.';
                    recordsStatusEl.className = 'text-sm text-amber-700 font-medium';
                }
                if (recordsController && Number.isInteger(containerId) && containerId >= 1) {
                    recordsController.open(containerId, sourcePage);
                }
            },
            onDeleteUncertainInvalidated: function () {
                listSnapshotValid = false;
            },
            onUncertainReviewStart: function (payload) {
                if (!Number.isInteger(payload.containerId) || payload.containerId < 1) {
                    return null;
                }
                if (!Number.isInteger(payload.recordId) || payload.recordId < 1) {
                    return null;
                }
                if (!Number.isInteger(payload.sourcePage) || payload.sourcePage < 1) {
                    return null;
                }
                if (pendingDeleteReview) {
                    return pendingDeleteReview.reviewToken;
                }
                nextDeleteReviewToken++;
                pendingDeleteReview = {
                    reviewToken: nextDeleteReviewToken,
                    containerId: payload.containerId,
                    recordId: payload.recordId,
                    sourcePage: payload.sourcePage
                };
                applyMutationLock('delete_review');
                return nextDeleteReviewToken;
            },
            onReviewAwaitingListSettlement: function (payload) {
                if (!pendingDeleteReview || pendingDeleteReview.reviewToken !== payload.reviewToken) {
                    return;
                }
                pendingDeleteReview.awaitingListSettlement = true;
                pendingDeleteReview.outcome = payload.outcome;
                listSnapshotValid = false;
                if (recordsController) {
                    recordsController.open(payload.containerId, payload.sourcePage);
                }
            },
            onReviewGetUncertain: function (reviewToken) {
                if (!pendingDeleteReview || pendingDeleteReview.reviewToken !== reviewToken) {
                    return;
                }
                showDeleteReviewRetryNotice(reviewToken);
            },
            onReviewRecordExistsNotice: function () {
                if (recordsStatusEl) {
                    recordsStatusEl.textContent = 'La entrada continúa disponible en la lista.';
                    recordsStatusEl.className = 'text-sm text-amber-700 font-medium';
                }
            },
            onContainerNotFoundDelete: function (containerId) {
                listSnapshotValid = false;
                if (pendingDeleteReview && pendingDeleteReview.containerId === containerId) {
                    handleDeleteReviewNotFound(pendingDeleteReview.reviewToken, containerId);
                    return;
                }
                backToContainerListWithMessage('La lista ya no está disponible.');
            },
            onFocusDetailStatus: function () {
                focusRecordsDetailStatus();
            },
            onIdle: function () {
                syncMutationUI();
            }
        });
        recordDeleteEnabled = !!recordDeleteController;
    }

    initRecordsController();
    initRecordCreateController();
    initRecordDeleteController();

    function setListViewVisible(isVisible) {
        if (listContainerEl) {
            if (isVisible) {
                listContainerEl.classList.remove('hidden');
                listContainerEl.hidden = false;
                listContainerEl.setAttribute('aria-hidden', 'false');
            } else {
                listContainerEl.classList.add('hidden');
                listContainerEl.hidden = true;
                listContainerEl.setAttribute('aria-hidden', 'true');
            }
        }
    }

    function setRecordsViewVisible(isVisible) {
        if (!recordsContainerEl) {
            return;
        }
        if (isVisible) {
            recordsContainerEl.classList.remove('hidden');
            recordsContainerEl.hidden = false;
            recordsContainerEl.setAttribute('aria-hidden', 'false');
        } else {
            recordsContainerEl.classList.add('hidden');
            recordsContainerEl.hidden = true;
            recordsContainerEl.setAttribute('aria-hidden', 'true');
        }
    }

    function setCreateTriggerVisible(isVisible) {
        if (!openCreateBtn || !hasCreateAction) {
            return;
        }
        if (isVisible) {
            openCreateBtn.classList.remove('hidden');
            openCreateBtn.hidden = false;
            if (createModalState !== CREATE_STATES.REVIEWING_UNCERTAIN) {
                openCreateBtn.disabled = false;
                openCreateBtn.removeAttribute('aria-disabled');
            }
        } else {
            openCreateBtn.classList.add('hidden');
            openCreateBtn.hidden = true;
            openCreateBtn.setAttribute('aria-disabled', 'true');
        }
    }

    function findFallbackOpenButton() {
        if (!gridEl) {
            return null;
        }
        var buttons = gridEl.querySelectorAll('button[type="button"]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            if (!btn.disabled && !btn.hidden && btn.classList.contains('aa-finance-open-records-btn')) {
                return btn;
            }
        }
        return null;
    }

    function restoreListFocus() {
        var originConnected = originOpenButton && (
            (typeof document.contains === 'function' && document.contains(originOpenButton)) ||
            originOpenButton.parentElement !== null ||
            originOpenButton.parentNode !== null
        );
        if (originConnected && !originOpenButton.disabled) {
            originOpenButton.focus();
            return;
        }
        var fallbackBtn = findFallbackOpenButton();
        if (fallbackBtn) {
            fallbackBtn.focus();
            return;
        }
        if (statusEl) {
            statusEl.focus();
        }
    }

    function openContainerDetail(containerId, originButton) {
        if (!recordsNavEnabled || !recordsController) {
            return;
        }
        if (!Number.isInteger(containerId) || containerId < 1) {
            return;
        }

        if (pendingDeleteReview && recordDeleteController) {
            if (selectedContainerId !== null && selectedContainerId !== containerId) {
                recordDeleteController.abortActiveReviewRequest();
            }
        }

        if (navMode === NAV_MODES.RECORDS_DETAIL && selectedContainerId === containerId) {
            if (pendingRecordReview && pendingRecordReview.containerId === containerId) {
                recordsController.open(containerId, 1);
            }
            if (pendingDeleteReview && pendingDeleteReview.containerId === containerId && recordDeleteController) {
                recordDeleteController.resumeReview(pendingDeleteReview);
            }
            return;
        }

        if (pendingDeleteReview && recordDeleteController && selectedContainerId !== null && selectedContainerId !== containerId) {
            recordDeleteController.abortActiveReviewRequest();
        }

        originOpenButton = originButton || null;
        selectedContainerId = containerId;
        navMode = NAV_MODES.RECORDS_DETAIL;

        setListViewVisible(false);
        setRecordsViewVisible(true);
        setCreateTriggerVisible(false);
        setRecordCreateTriggerVisible(true);
        syncMutationUI();

        recordsController.open(containerId, 1);

        if (pendingDeleteReview && pendingDeleteReview.containerId === containerId && recordDeleteController) {
            recordDeleteController.resumeReview(pendingDeleteReview);
        }

        scheduleRecordsHeadingFocus();
    }

    function backToContainerList() {
        if (pendingDeleteReview && recordDeleteController) {
            recordDeleteController.abortActiveReviewRequest();
        }

        if (recordsController) {
            recordsController.close();
        }

        clearRecordsFocusTimer();
        navMode = NAV_MODES.CONTAINER_LIST;
        selectedContainerId = null;

        setRecordsViewVisible(false);
        setListViewVisible(true);
        setCreateTriggerVisible(true);
        setRecordCreateTriggerVisible(false);

        if (!listSnapshotValid) {
            if (statusEl) {
                statusEl.focus();
            }
            loadPage(confirmedPage);
            originOpenButton = null;
            return;
        }

        restoreListFocus();
        originOpenButton = null;
    }

    if (recordsBackBtn) {
        recordsBackBtn.addEventListener('click', function () {
            backToContainerList();
        });
    }

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
            if (!createModal || createModal.classList.contains('hidden')) {
                return;
            }
            if (createModalState !== CREATE_STATES.EDITING) {
                return;
            }
            if (titleInput) {
                titleInput.focus();
            }
        }, 50);
    }

    function formatCreationDate(rawDate) {
        if (typeof rawDate !== 'string') {
            return '';
        }
        var match = rawDate.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) {
            return rawDate;
        }
        return match[3] + '/' + match[2] + '/' + match[1];
    }

    function validatePayload(data) {
        if (!data || typeof data !== 'object' || Array.isArray(data)) {
            return false;
        }
        if (!Array.isArray(data.items) || data.items.length > 15) {
            return false;
        }
        if (
            !Number.isInteger(data.page) || data.page < 1 ||
            !Number.isInteger(data.per_page) || data.per_page !== 15 ||
            !Number.isInteger(data.total) || data.total < 0 ||
            !Number.isInteger(data.total_pages) || data.total_pages < 0 ||
            typeof data.has_previous !== 'boolean' ||
            typeof data.has_next !== 'boolean'
        ) {
            return false;
        }

        for (var i = 0; i < data.items.length; i++) {
            var item = data.items[i];
            if (!item || typeof item !== 'object' || Array.isArray(item)) {
                return false;
            }
            if (!Number.isInteger(item.id) || item.id < 1) {
                return false;
            }
            if (typeof item.title !== 'string') {
                return false;
            }
            if (item.details !== null && typeof item.details !== 'string') {
                return false;
            }
            if (item.amount_total !== null && typeof item.amount_total !== 'string') {
                return false;
            }
            if (typeof item.created_at !== 'string') {
                return false;
            }
            if (item.family_key !== familyKey || item.variant_key !== variantKey) {
                return false;
            }
        }

        return true;
    }

    function validateCreateResponse(json) {
        if (!json || typeof json !== 'object' || json.success !== true) {
            return false;
        }
        var data = json.data;
        if (!data || typeof data !== 'object' || Array.isArray(data)) {
            return false;
        }
        var c = data.container;
        if (!c || typeof c !== 'object' || Array.isArray(c)) {
            return false;
        }
        if (!Number.isInteger(c.id) || c.id < 1) {
            return false;
        }
        if (c.family_key !== familyKey || c.variant_key !== variantKey) {
            return false;
        }
        if (typeof c.title !== 'string') {
            return false;
        }
        if (c.details !== null && typeof c.details !== 'string') {
            return false;
        }
        if (typeof c.created_at !== 'string') {
            return false;
        }
        return true;
    }

    function clearGrid() {
        if (!gridEl) return;
        while (gridEl.firstChild) {
            gridEl.removeChild(gridEl.firstChild);
        }
    }

    function setStatus(text, isError, showRetry) {
        if (!statusEl) return;
        while (statusEl.firstChild) {
            statusEl.removeChild(statusEl.firstChild);
        }

        if (!text) {
            statusEl.className = 'text-sm text-gray-500';
            return;
        }

        if (isError) {
            statusEl.className = 'text-sm text-red-600 font-medium flex items-center gap-2 flex-wrap';
            var msgSpan = document.createElement('span');
            msgSpan.textContent = text;
            statusEl.appendChild(msgSpan);

            if (showRetry) {
                var retryBtn = document.createElement('button');
                retryBtn.type = 'button';
                retryBtn.className = 'underline hover:text-red-800 text-xs font-semibold focus:outline-none';
                retryBtn.textContent = 'Reintentar';
                retryBtn.addEventListener('click', function () {
                    var target = (failedPage !== null) ? failedPage : requestedPage;
                    loadPage(target);
                });
                statusEl.appendChild(retryBtn);
            }
        } else {
            statusEl.className = 'text-sm text-gray-500';
            statusEl.textContent = text;
        }
    }

    function renderEmptyState() {
        clearGrid();
        if (paginationEl) {
            paginationEl.classList.add('hidden');
            paginationEl.hidden = true;
        }

        var emptyBox = document.createElement('div');
        emptyBox.className = 'col-span-full bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center';

        var iconContainer = document.createElement('div');
        iconContainer.className = 'inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-400 mb-4';

        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'w-6 h-6');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');

        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        path.setAttribute('stroke-width', '1.5');
        path.setAttribute('d', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z');
        svg.appendChild(path);
        iconContainer.appendChild(svg);
        emptyBox.appendChild(iconContainer);

        var titleEl = document.createElement('h3');
        titleEl.className = 'text-base font-semibold text-gray-900 mb-1';
        titleEl.textContent = 'No hay contenedores creados';
        emptyBox.appendChild(titleEl);

        var descEl = document.createElement('p');
        descEl.className = 'text-sm text-gray-500 max-w-md mx-auto';
        descEl.textContent = 'Aún no se han registrado contenedores en esta variante.';
        emptyBox.appendChild(descEl);

        gridEl.appendChild(emptyBox);
    }

    function renderCards(items) {
        clearGrid();

        for (var i = 0; i < items.length; i++) {
            var item = items[i];

            var card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex flex-col justify-between';

            var cardTop = document.createElement('div');

            var cardHeader = document.createElement('div');
            cardHeader.className = 'flex items-start justify-between gap-3 mb-2';

            var titleEl = document.createElement('h3');
            titleEl.className = 'text-base font-semibold text-gray-900 line-clamp-1';
            titleEl.textContent = item.title;
            cardHeader.appendChild(titleEl);

            var amountBadge = document.createElement('span');
            if (item.amount_total === null) {
                amountBadge.className = 'text-xs text-gray-400 italic';
                amountBadge.textContent = 'Sin importes';
            } else {
                amountBadge.className = 'text-xs font-semibold text-gray-700 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded font-mono';
                amountBadge.textContent = item.amount_total;
            }
            cardHeader.appendChild(amountBadge);
            cardTop.appendChild(cardHeader);

            if (item.details !== null && item.details !== '') {
                var detailsEl = document.createElement('p');
                detailsEl.className = 'text-sm text-gray-500 mt-1';
                detailsEl.style.display = '-webkit-box';
                detailsEl.style.webkitLineClamp = '2';
                detailsEl.style.webkitBoxOrient = 'vertical';
                detailsEl.style.overflow = 'hidden';
                detailsEl.textContent = item.details;
                cardTop.appendChild(detailsEl);
            }

            card.appendChild(cardTop);

            var cardFooter = document.createElement('div');
            cardFooter.className = 'mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400';

            var dateSpan = document.createElement('span');
            dateSpan.textContent = formatCreationDate(item.created_at);
            cardFooter.appendChild(dateSpan);

            card.appendChild(cardFooter);

            if (recordsNavEnabled) {
                var openRecordsBtn = document.createElement('button');
                openRecordsBtn.type = 'button';
                openRecordsBtn.className = 'aa-finance-open-records-btn mt-3 inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition';
                openRecordsBtn.textContent = 'Ver registros';
                openRecordsBtn.setAttribute('aria-label', 'Ver registros de «' + item.title + '»');
                openRecordsBtn.setAttribute('data-container-id', String(item.id));

                (function (validatedId, triggerBtn) {
                    openRecordsBtn.addEventListener('click', function () {
                        openContainerDetail(validatedId, triggerBtn);
                    });
                })(item.id, openRecordsBtn);

                card.appendChild(openRecordsBtn);
            }

            gridEl.appendChild(card);
        }
    }

    function updatePaginationControls(data) {
        if (!paginationEl) return;

        if (data.total === 0) {
            paginationEl.classList.add('hidden');
            paginationEl.hidden = true;
            return;
        }

        paginationEl.classList.remove('hidden');
        paginationEl.hidden = false;

        if (prevBtn) {
            prevBtn.disabled = !data.has_previous || isFetchingList;
        }
        if (nextBtn) {
            nextBtn.disabled = !data.has_next || isFetchingList;
        }
        if (pageIndicatorEl) {
            var totalPagesDisplay = data.total_pages > 0 ? data.total_pages : 1;
            pageIndicatorEl.textContent = 'Página ' + data.page + ' de ' + totalPagesDisplay;
        }
    }

    function loadPage(page) {
        if (isFetchingList && listAbortController) {
            listAbortController.abort();
        }

        listAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var seq = ++requestSeq;
        isFetchingList = true;
        requestedPage = page;

        if (gridEl) {
            gridEl.setAttribute('aria-busy', 'true');
        }
        setStatus('Cargando contenedores…', false, false);
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;

        var formData = new FormData();
        formData.append('action', actions.listContainers);
        formData.append('_wpnonce', nonce);
        formData.append('variant_key', variantKey);
        formData.append('page', String(page));

        var fetchOptions = {
            method: 'POST',
            body: formData
        };
        if (listAbortController) {
            fetchOptions.signal = listAbortController.signal;
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
                if (seq !== requestSeq) {
                    return;
                }
                isFetchingList = false;
                if (gridEl) {
                    gridEl.setAttribute('aria-busy', 'false');
                }

                if (!response.ok || !response.json || response.json.success !== true) {
                    failedPage = requestedPage;
                    var errMsg = 'No se pudieron cargar los contenedores.';
                    if (response.json && response.json.data && typeof response.json.data.message === 'string') {
                        errMsg = response.json.data.message;
                    }
                    setStatus(errMsg, true, true);
                    return;
                }

                var data = response.json.data;
                if (!validatePayload(data)) {
                    failedPage = requestedPage;
                    setStatus('Respuesta del servidor no válida.', true, true);
                    return;
                }

                failedPage = null;
                confirmedPage = data.page;
                listSnapshotValid = true;
                if (listStatusOverride) {
                    setStatus(listStatusOverride, true, false);
                    listStatusOverride = null;
                } else {
                    setStatus('', false, false);
                }

                if (createModalState === CREATE_STATES.REVIEWING_UNCERTAIN) {
                    createModalState = CREATE_STATES.DRAFT_REVIEWED;
                    if (openCreateBtn) {
                        openCreateBtn.disabled = false;
                        openCreateBtn.removeAttribute('aria-disabled');
                    }
                }

                if (data.total === 0) {
                    renderEmptyState();
                } else {
                    renderCards(data.items);
                }

                updatePaginationControls(data);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (seq !== requestSeq) {
                    return;
                }
                isFetchingList = false;
                if (gridEl) {
                    gridEl.setAttribute('aria-busy', 'false');
                }
                failedPage = requestedPage;
                setStatus('Error de conexión con el servidor.', true, true);
            });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (confirmedPage > 1 && !isFetchingList) {
                loadPage(confirmedPage - 1);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (!isFetchingList) {
                loadPage(confirmedPage + 1);
            }
        });
    }

    // ==========================================
    // Focus Trap con Visibilidad Real
    // ==========================================
    function isElementVisible(el) {
        if (!el || el.hidden || el.getAttribute('aria-hidden') === 'true' || el.tabIndex === -1) {
            return false;
        }
        var current = el;
        while (current && current !== document.body && current !== root) {
            if (current.hidden || (current.classList && current.classList.contains('hidden')) || current.getAttribute('aria-hidden') === 'true') {
                return false;
            }
            current = current.parentElement;
        }
        return true;
    }

    function getVisibleFocusableElements(container) {
        if (!container) return [];
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
        if (!createModal || createModal.classList.contains('hidden') || e.key !== 'Tab') {
            return;
        }
        var focusables = getVisibleFocusableElements(createModal);
        if (focusables.length === 0) {
            e.preventDefault();
            return;
        }

        var first = focusables[0];
        var last = focusables[focusables.length - 1];

        if (e.shiftKey) {
            if (document.activeElement === first || !createModal.contains(document.activeElement)) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if (document.activeElement === last || !createModal.contains(document.activeElement)) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    document.addEventListener('keydown', handleFocusTrap);

    // ==========================================
    // Gestión del Modal de Creación
    // ==========================================
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
    }

    function setBotoneraState(type) {
        if (standardActionsEl) standardActionsEl.classList.add('hidden');
        if (uncertainActionsEl) uncertainActionsEl.classList.add('hidden');
        if (blockedActionsEl) blockedActionsEl.classList.add('hidden');

        if (type === 'standard' && standardActionsEl) {
            standardActionsEl.classList.remove('hidden');
        } else if (type === 'uncertain' && uncertainActionsEl) {
            uncertainActionsEl.classList.remove('hidden');
        } else if (type === 'blocked' && blockedActionsEl) {
            blockedActionsEl.classList.remove('hidden');
        }
    }

    function openModal() {
        if (!createModal) return;

        clearModalErrors();
        setBotoneraState('standard');

        if (titleInput) {
            titleInput.readOnly = false;
        }
        if (detailsInput) {
            detailsInput.readOnly = false;
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Crear lista';
        }
        if (cancelBtn) {
            cancelBtn.disabled = false;
        }
        if (modalCloseBtn) {
            modalCloseBtn.disabled = false;
        }

        if (createModalState === CREATE_STATES.DRAFT_REVIEWED) {
            if (titleInput) titleInput.value = savedDraft.title;
            if (detailsInput) detailsInput.value = savedDraft.details;
            if (modalErrorEl) {
                modalErrorEl.textContent = 'Antes de volver a crearla, verifica que la lista no aparezca ya en el listado.';
                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-xs font-medium';
                modalErrorEl.classList.remove('hidden');
            }
        } else {
            if (titleInput) titleInput.value = '';
            if (detailsInput) detailsInput.value = '';
        }

        createModalState = CREATE_STATES.EDITING;
        createModal.classList.remove('hidden');
        createModal.setAttribute('aria-hidden', 'false');

        scheduleTitleFocus();
    }

    function closeModal(cleanDraft) {
        if (!createModal) return;

        clearFocusTimer();

        if (createTimeoutId !== null) {
            clearTimeout(createTimeoutId);
            createTimeoutId = null;
        }
        if (createAbortController) {
            createAbortController.abort();
            createAbortController = null;
        }

        createModal.classList.add('hidden');
        createModal.setAttribute('aria-hidden', 'true');

        if (cleanDraft) {
            savedDraft = { title: '', details: '' };
            if (titleInput) titleInput.value = '';
            if (detailsInput) detailsInput.value = '';
            clearModalErrors();
            createModalState = CREATE_STATES.IDLE;
        }

        if (openCreateBtn && !openCreateBtn.disabled) {
            openCreateBtn.focus();
        }
    }

    if (openCreateBtn && hasCreateAction) {
        openCreateBtn.addEventListener('click', function () {
            if (openCreateBtn.disabled) return;
            openModal();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (createModalState === CREATE_STATES.SUBMITTING) return;
            closeModal(true);
        });
    }

    function closeModalFromUserGesture() {
        if (createModalState === CREATE_STATES.SUBMITTING || createModalState === CREATE_STATES.UNCERTAIN) {
            return;
        }
        closeModal(true);
    }

    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeModalFromUserGesture);
    }

    if (blockedCloseBtn) {
        blockedCloseBtn.addEventListener('click', function () {
            closeModal(true);
        });
    }

    if (uncertainCloseBtn) {
        uncertainCloseBtn.addEventListener('click', function () {
            createModalState = CREATE_STATES.REVIEWING_UNCERTAIN;
            if (openCreateBtn) {
                openCreateBtn.disabled = true;
                openCreateBtn.setAttribute('aria-disabled', 'true');
            }
            closeModal(false);
            if (statusEl) {
                statusEl.focus();
            }
            loadPage(1);
        });
    }

    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', closeModalFromUserGesture);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && createModal && !createModal.classList.contains('hidden')) {
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
            if (modalErrorEl && createModalState === CREATE_STATES.EDITING) {
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

    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (createModalState === CREATE_STATES.SUBMITTING || createModalState === CREATE_STATES.UNCERTAIN || createModalState === CREATE_STATES.BLOCKED_REJECTED) {
                return;
            }

            clearModalErrors();

            var rawTitle = titleInput ? titleInput.value : '';
            var rawDetails = detailsInput ? detailsInput.value : '';

            if (rawTitle.trim() === '') {
                createModalState = CREATE_STATES.FIELD_REJECTED;
                if (titleErrorEl) {
                    titleErrorEl.textContent = 'El título no puede estar vacío.';
                    titleErrorEl.classList.remove('hidden');
                }
                if (titleInput) {
                    titleInput.setAttribute('aria-invalid', 'true');
                    titleInput.setAttribute('aria-describedby', 'aa-finance-title-error');
                    titleInput.focus();
                }
                return;
            }

            // Snapshot inmutable
            var submittedSnapshot = {
                title: rawTitle,
                details: rawDetails,
                variantKey: variantKey
            };
            savedDraft = {
                title: rawTitle,
                details: rawDetails
            };

            createModalState = CREATE_STATES.SUBMITTING;
            var currentCreateSeq = ++createRequestSeq;
            var isSettled = false;

            if (titleInput) titleInput.readOnly = true;
            if (detailsInput) detailsInput.readOnly = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creando…';
            }
            if (cancelBtn) cancelBtn.disabled = true;
            if (modalCloseBtn) modalCloseBtn.disabled = true;

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

            function transitionToUncertain() {
                cleanupCreateTimer();
                createModalState = CREATE_STATES.UNCERTAIN;
                setBotoneraState('uncertain');
                if (modalErrorEl) {
                    modalErrorEl.textContent = 'No pudimos confirmar si la lista se creó. Revisa el listado antes de intentarlo nuevamente.';
                    modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                    modalErrorEl.classList.remove('hidden');
                }
            }

            var formData = new FormData();
            formData.append('action', actions.createContainer);
            formData.append('_wpnonce', nonce);
            formData.append('variant_key', submittedSnapshot.variantKey);
            formData.append('title', submittedSnapshot.title);
            formData.append('details', submittedSnapshot.details);

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

                    // 1. Éxito confirmado
                    if (response.ok && response.json && response.json.success === true) {
                        if (validateCreateResponse(response.json)) {
                            createModalState = CREATE_STATES.CONFIRMED;
                            closeModal(true);
                            listSnapshotValid = true;
                            loadPage(1);
                            return;
                        }
                        // Payload de éxito corrupto/inválido -> Incierto
                        transitionToUncertain();
                        return;
                    }

                    // 2. Errores HTTP / JSON estructurados
                    if (response.json && response.json.success === false && response.json.data) {
                        var errCode = response.json.data.code;
                        var errMsg = response.json.data.message || 'Error al crear la lista.';

                        // 2A. Rechazos Corregibles
                        var CORREGIBLES = ['missing_title', 'invalid_title', 'title_too_long', 'invalid_details', 'details_too_long'];
                        if (CORREGIBLES.indexOf(errCode) !== -1) {
                            createModalState = CREATE_STATES.FIELD_REJECTED;
                            if (titleInput) titleInput.readOnly = false;
                            if (detailsInput) detailsInput.readOnly = false;
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Crear lista';
                            }
                            if (cancelBtn) cancelBtn.disabled = false;
                            if (modalCloseBtn) modalCloseBtn.disabled = false;

                            if (errCode === 'missing_title' || errCode === 'invalid_title' || errCode === 'title_too_long') {
                                if (titleErrorEl) {
                                    titleErrorEl.textContent = errMsg;
                                    titleErrorEl.classList.remove('hidden');
                                }
                                if (titleInput) {
                                    titleInput.setAttribute('aria-invalid', 'true');
                                    titleInput.setAttribute('aria-describedby', 'aa-finance-title-error');
                                    titleInput.focus();
                                }
                            } else {
                                if (detailsErrorEl) {
                                    detailsErrorEl.textContent = errMsg;
                                    detailsErrorEl.classList.remove('hidden');
                                }
                                if (detailsInput) {
                                    detailsInput.setAttribute('aria-invalid', 'true');
                                    detailsInput.setAttribute('aria-describedby', 'aa-finance-details-error');
                                    detailsInput.focus();
                                }
                            }
                            return;
                        }

                        // 2B. Rechazos Bloqueantes
                        var BLOQUEANTES = ['bad_nonce', 'unauthorized', 'forbidden', 'invalid_variant_key', 'unknown_variant'];
                        if (BLOQUEANTES.indexOf(errCode) !== -1) {
                            createModalState = CREATE_STATES.BLOCKED_REJECTED;
                            setBotoneraState('blocked');
                            if (modalCloseBtn) modalCloseBtn.disabled = false;
                            if (modalErrorEl) {
                                modalErrorEl.textContent = 'La creación no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.';
                                modalErrorEl.className = 'mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-xs font-medium';
                                modalErrorEl.classList.remove('hidden');
                            }
                            return;
                        }
                    }

                    // 2C. Fallo de persistencia, HTTP 500 o respuesta no reconocida -> Incierto
                    transitionToUncertain();
                })
                .catch(function (err) {
                    if (isSettled || currentCreateSeq !== createRequestSeq) {
                        return;
                    }
                    isSettled = true;
                    cleanupCreateTimer();
                    transitionToUncertain();
                });
        });
    }

    loadPage(1);
})();
