/**
 * Finance Records Module — Controlador de lectura de registros financieros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical\Finance
 */
(function () {
    'use strict';

    var RECORDS_PHASE = {
        IDLE: 'RECORDS_IDLE',
        LOADING: 'RECORDS_LOADING',
        READY: 'RECORDS_READY',
        EMPTY: 'RECORDS_EMPTY',
        RECOVERABLE_ERROR: 'RECORDS_RECOVERABLE_ERROR',
        BLOCKED_ERROR: 'RECORDS_BLOCKED_ERROR',
        NOT_FOUND: 'RECORDS_NOT_FOUND'
    };

    var BLOCKED_CODES = [
        'bad_nonce',
        'unauthorized',
        'forbidden',
        'invalid_variant_key',
        'unknown_variant',
        'invalid_container_id'
    ];

    var RECOVERABLE_CODES = [
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

    function formatCreationDate(rawDate) {
        if (typeof rawDate !== 'string') {
            return '';
        }
        var dateMatch = rawDate.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!dateMatch) {
            return rawDate;
        }
        return dateMatch[3] + '/' + dateMatch[2] + '/' + dateMatch[1];
    }

    function createAmountPresentation(amount) {
        if (amount === null) {
            return {
                text: 'Sin importe',
                className: 'text-xs text-gray-400 italic',
                ariaLabel: 'Sin importe'
            };
        }
        if (amount === '0.00') {
            return {
                text: '0.00',
                className: 'text-xs font-semibold text-gray-700 font-mono',
                ariaLabel: '0.00'
            };
        }
        if (typeof amount === 'string' && amount.charAt(0) === '-') {
            return {
                text: amount,
                className: 'text-xs font-semibold text-red-700 font-mono',
                ariaLabel: 'Importe negativo: ' + amount
            };
        }
        return {
            text: amount,
            className: 'text-xs font-semibold text-gray-900 font-mono',
            ariaLabel: String(amount)
        };
    }

    function createTotalPresentation(amountTotal) {
        if (amountTotal === null) {
            return {
                text: 'Sin importes',
                className: 'text-sm text-gray-400 italic',
                ariaLabel: 'Sin importes'
            };
        }
        if (amountTotal === '0.00') {
            return {
                text: '0.00',
                className: 'text-sm font-semibold text-gray-700 font-mono',
                ariaLabel: 'Total: 0.00'
            };
        }
        if (typeof amountTotal === 'string' && amountTotal.charAt(0) === '-') {
            return {
                text: amountTotal,
                className: 'text-sm font-semibold text-red-700 font-mono',
                ariaLabel: 'Total negativo: ' + amountTotal
            };
        }
        return {
            text: amountTotal,
            className: 'text-sm font-semibold text-gray-900 font-mono',
            ariaLabel: 'Total: ' + amountTotal
        };
    }

    function appendMultilineText(parent, text) {
        var lines = String(text).split('\n');
        for (var i = 0; i < lines.length; i++) {
            if (i > 0) {
                parent.appendChild(document.createElement('br'));
            }
            var lineSpan = document.createElement('span');
            lineSpan.textContent = lines[i];
            parent.appendChild(lineSpan);
        }
    }

    function createController(options) {
        var cfg = options.cfg;
        var elements = options.elements || {};
        var onContainerNotFound = typeof options.onContainerNotFound === 'function'
            ? options.onContainerNotFound
            : function () {};
        var onAuthoritativeLoadSettled = typeof options.onAuthoritativeLoadSettled === 'function'
            ? options.onAuthoritativeLoadSettled
            : null;
        var onRecordDeleteIntent = typeof options.onRecordDeleteIntent === 'function'
            ? options.onRecordDeleteIntent
            : null;
        var onRecordEditIntent = typeof options.onRecordEditIntent === 'function'
            ? options.onRecordEditIntent
            : null;
        var isDeleteActionEnabled = typeof options.isDeleteActionEnabled === 'function'
            ? options.isDeleteActionEnabled
            : function () { return false; };
        var isEditActionEnabled = typeof options.isEditActionEnabled === 'function'
            ? options.isEditActionEnabled
            : function () { return false; };
        var isActiveGuard = typeof options.isActiveGuard === 'function'
            ? options.isActiveGuard
            : function () { return true; };

        var headingEl = elements.heading || null;
        var summaryEl = elements.summary || null;
        var statusEl = elements.status || null;
        var gridEl = elements.grid || null;
        var paginationEl = elements.pagination || null;
        var prevBtn = elements.prev || null;
        var nextBtn = elements.next || null;
        var pageIndicatorEl = elements.pageIndicator || null;

        var ajaxUrl = cfg.ajaxUrl;
        var nonce = cfg.nonce;
        var familyKey = cfg.familyKey;
        var variantKey = cfg.variantKey;
        var listRecordsAction = cfg.actions && cfg.actions.listRecords;

        var recordsPhase = RECORDS_PHASE.IDLE;
        var recordsAbortController = null;
        var recordsRequestSeq = 0;
        var selectedContainerId = null;
        var confirmedRecordsPage = 1;
        var lastRequestedPage = 1;
        var lastErrorRecoverable = false;
        var confirmedPagination = {
            has_previous: false,
            has_next: false,
            page: 1,
            total_pages: 0
        };

        var boundPrevHandler = null;
        var boundNextHandler = null;

        function isContextActive(containerId, seq) {
            if (!isActiveGuard()) {
                return false;
            }
            if (seq !== recordsRequestSeq) {
                return false;
            }
            if (selectedContainerId === null || selectedContainerId !== containerId) {
                return false;
            }
            return true;
        }

        function clearNode(node) {
            if (!node) {
                return;
            }
            while (node.firstChild) {
                node.removeChild(node.firstChild);
            }
        }

        function setRecordsBusy(isBusy) {
            if (gridEl) {
                gridEl.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            }
        }

        function setStatusMessage(text, isError, showRetry) {
            if (!statusEl) {
                return;
            }
            clearNode(statusEl);
            statusEl.className = isError
                ? 'text-sm text-red-600 font-medium flex items-center gap-2 flex-wrap'
                : 'text-sm text-gray-500';

            if (!text) {
                statusEl.className = 'text-sm text-gray-500';
                return;
            }

            var msgSpan = document.createElement('span');
            msgSpan.textContent = text;
            statusEl.appendChild(msgSpan);

            if (showRetry) {
                var retryBtn = document.createElement('button');
                retryBtn.type = 'button';
                retryBtn.className = 'underline hover:text-red-800 text-xs font-semibold focus:outline-none';
                retryBtn.textContent = 'Reintentar';
                retryBtn.addEventListener('click', function () {
                    retry();
                });
                statusEl.appendChild(retryBtn);
            }
        }

        function updatePaginationControls(data, loading) {
            if (!paginationEl) {
                return;
            }

            if (!data || data.total === 0) {
                paginationEl.classList.add('hidden');
                paginationEl.hidden = true;
                return;
            }

            paginationEl.classList.remove('hidden');
            paginationEl.hidden = false;

            if (prevBtn) {
                prevBtn.disabled = loading || !data.has_previous;
            }
            if (nextBtn) {
                nextBtn.disabled = loading || !data.has_next;
            }
            if (pageIndicatorEl) {
                var totalPagesDisplay = data.total_pages > 0 ? data.total_pages : 1;
                pageIndicatorEl.textContent = 'Página ' + data.page + ' de ' + totalPagesDisplay;
            }
        }

        function validateEnvelope(data, expectedContainerId) {
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return false;
            }

            var container = data.container;
            if (!container || typeof container !== 'object' || Array.isArray(container)) {
                return false;
            }
            if (!Number.isInteger(container.id) || container.id < 1 || container.id !== expectedContainerId) {
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

            if (data.total === 0) {
                if (data.total_pages !== 0 || data.page !== 1 || data.items.length !== 0 || data.amount_total !== null) {
                    return false;
                }
            } else {
                if (data.total_pages < 1 || data.page > data.total_pages) {
                    return false;
                }
            }

            if (!isValidDecimalAmount(data.amount_total)) {
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
                if (!Number.isInteger(item.container_id) || item.container_id !== expectedContainerId) {
                    return false;
                }
                if (item.family_key !== familyKey || item.variant_key !== variantKey) {
                    return false;
                }
                if (typeof item.title !== 'string') {
                    return false;
                }
                if (item.details !== null && typeof item.details !== 'string') {
                    return false;
                }
                if (!isValidDecimalAmount(item.amount)) {
                    return false;
                }
                if (typeof item.created_at !== 'string') {
                    return false;
                }
            }

            return true;
        }

        function renderContainerSummary(container, total, amountTotal) {
            if (headingEl) {
                headingEl.textContent = container.title;
            }
            if (!summaryEl) {
                return;
            }
            clearNode(summaryEl);

            if (container.details !== null && container.details !== '') {
                var detailsEl = document.createElement('p');
                detailsEl.className = 'text-sm text-gray-500 mt-2';
                detailsEl.style.whiteSpace = 'pre-wrap';
                appendMultilineText(detailsEl, container.details);
                summaryEl.appendChild(detailsEl);
            }

            var metaRow = document.createElement('div');
            metaRow.className = 'mt-3 flex flex-wrap items-center gap-4 text-sm';

            var totalWrap = document.createElement('div');
            var totalLabel = document.createElement('span');
            totalLabel.className = 'text-gray-500 mr-1';
            totalLabel.textContent = 'Total:';
            totalWrap.appendChild(totalLabel);
            var totalValue = document.createElement('span');
            var totalPresentation = createTotalPresentation(amountTotal);
            totalValue.className = totalPresentation.className;
            totalValue.textContent = totalPresentation.text;
            totalValue.setAttribute('aria-label', totalPresentation.ariaLabel);
            totalWrap.appendChild(totalValue);
            metaRow.appendChild(totalWrap);

            var countWrap = document.createElement('div');
            countWrap.className = 'text-gray-500';
            var countText = total === 1 ? '1 registro' : String(total) + ' registros';
            countWrap.textContent = countText;
            metaRow.appendChild(countWrap);

            summaryEl.appendChild(metaRow);
        }

        function setDeleteActionsEnabled(isEnabled) {
            if (!gridEl) {
                return;
            }
            var buttons = gridEl.querySelectorAll('.aa-finance-delete-record-btn');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].disabled = !isEnabled;
                if (isEnabled) {
                    buttons[i].removeAttribute('aria-disabled');
                } else {
                    buttons[i].setAttribute('aria-disabled', 'true');
                }
            }
        }

        function setEditActionsEnabled(isEnabled) {
            if (!gridEl) {
                return;
            }
            var buttons = gridEl.querySelectorAll('.aa-finance-edit-record-btn');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].disabled = !isEnabled;
                if (isEnabled) {
                    buttons[i].removeAttribute('aria-disabled');
                } else {
                    buttons[i].setAttribute('aria-disabled', 'true');
                }
            }
        }

        function renderRecords(items) {
            if (!gridEl) {
                return;
            }
            clearNode(gridEl);
            var renderSourcePage = confirmedRecordsPage;

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var card = document.createElement('article');
                card.className = 'bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col gap-2';
                card.setAttribute('data-record-id', String(item.id));

                var header = document.createElement('div');
                header.className = 'flex items-start justify-between gap-3';

                var titleEl = document.createElement('h4');
                titleEl.className = 'text-sm font-semibold text-gray-900';
                titleEl.textContent = item.title;
                header.appendChild(titleEl);

                var amountEl = document.createElement('span');
                var amountPresentation = createAmountPresentation(item.amount);
                amountEl.className = amountPresentation.className;
                amountEl.textContent = amountPresentation.text;
                amountEl.setAttribute('aria-label', amountPresentation.ariaLabel);
                header.appendChild(amountEl);

                card.appendChild(header);

                if (item.details !== null && item.details !== '') {
                    var detailsEl = document.createElement('p');
                    detailsEl.className = 'text-sm text-gray-500';
                    detailsEl.style.whiteSpace = 'pre-wrap';
                    appendMultilineText(detailsEl, item.details);
                    card.appendChild(detailsEl);
                }

                var footer = document.createElement('div');
                footer.className = 'flex items-center justify-between pt-2 border-t border-gray-100 mt-2';
                var dateSpan = document.createElement('span');
                dateSpan.className = 'text-xs text-gray-400';
                dateSpan.textContent = formatCreationDate(item.created_at);
                footer.appendChild(dateSpan);

                var actionsWrap = document.createElement('div');
                actionsWrap.className = 'flex items-center gap-3';

                if (onRecordEditIntent && editRecordActionEnabled()) {
                    var editBtn = document.createElement('button');
                    editBtn.type = 'button';
                    editBtn.className = 'aa-finance-edit-record-btn text-xs font-semibold text-indigo-700 hover:text-indigo-900 underline focus:outline-none';
                    editBtn.textContent = 'Editar';
                    editBtn.setAttribute('aria-label', 'Editar entrada «' + item.title + '»');
                    var editEnabled = isEditActionEnabled();
                    editBtn.disabled = !editEnabled;
                    if (!editEnabled) {
                        editBtn.setAttribute('aria-disabled', 'true');
                    }
                    (function (snapshotItem, sourcePage, triggerBtn) {
                        editBtn.addEventListener('click', function () {
                            if (triggerBtn.disabled) {
                                return;
                            }
                            onRecordEditIntent({
                                recordId: snapshotItem.id,
                                containerId: snapshotItem.container_id,
                                sourcePage: sourcePage
                            }, triggerBtn);
                        });
                    })(item, renderSourcePage, editBtn);
                    actionsWrap.appendChild(editBtn);
                }

                if (onRecordDeleteIntent && deleteRecordActionEnabled()) {
                    var deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'aa-finance-delete-record-btn text-xs font-semibold text-red-700 hover:text-red-900 underline focus:outline-none';
                    deleteBtn.textContent = 'Eliminar';
                    deleteBtn.setAttribute('aria-label', 'Eliminar entrada «' + item.title + '»');
                    var deleteEnabled = isDeleteActionEnabled();
                    deleteBtn.disabled = !deleteEnabled;
                    if (!deleteEnabled) {
                        deleteBtn.setAttribute('aria-disabled', 'true');
                    }
                    (function (snapshotItem, sourcePage, triggerBtn) {
                        deleteBtn.addEventListener('click', function () {
                            if (triggerBtn.disabled) {
                                return;
                            }
                            onRecordDeleteIntent({
                                recordId: snapshotItem.id,
                                containerId: snapshotItem.container_id,
                                title: snapshotItem.title,
                                amount: snapshotItem.amount,
                                sourcePage: sourcePage
                            }, triggerBtn);
                        });
                    })(item, renderSourcePage, deleteBtn);
                    actionsWrap.appendChild(deleteBtn);
                }

                if (actionsWrap.childNodes.length > 0) {
                    footer.appendChild(actionsWrap);
                }

                card.appendChild(footer);
                gridEl.appendChild(card);
            }
        }

        function deleteRecordActionEnabled() {
            return !!onRecordDeleteIntent;
        }

        function editRecordActionEnabled() {
            return !!onRecordEditIntent;
        }

        function renderEmptyState() {
            if (!gridEl) {
                return;
            }
            clearNode(gridEl);

            var emptyBox = document.createElement('div');
            emptyBox.className = 'col-span-full bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center';

            var titleEl = document.createElement('h4');
            titleEl.className = 'text-base font-semibold text-gray-900 mb-1';
            titleEl.textContent = 'Esta lista no tiene registros';
            emptyBox.appendChild(titleEl);

            var descEl = document.createElement('p');
            descEl.className = 'text-sm text-gray-500';
            descEl.textContent = 'Aún no se han agregado registros a este contenedor.';
            emptyBox.appendChild(descEl);

            gridEl.appendChild(emptyBox);
        }

        function focusHeading() {
            if (headingEl && typeof headingEl.focus === 'function') {
                headingEl.focus();
            }
        }

        function mapPhaseToSettlement(phase) {
            if (phase === RECORDS_PHASE.READY) {
                return 'READY';
            }
            if (phase === RECORDS_PHASE.EMPTY) {
                return 'EMPTY';
            }
            if (phase === RECORDS_PHASE.RECOVERABLE_ERROR) {
                return 'RECOVERABLE_ERROR';
            }
            if (phase === RECORDS_PHASE.BLOCKED_ERROR) {
                return 'BLOCKED_ERROR';
            }
            if (phase === RECORDS_PHASE.NOT_FOUND) {
                return 'NOT_FOUND';
            }
            return null;
        }

        function emitAuthoritativeLoadSettled(containerId, page, seq, phase) {
            if (!onAuthoritativeLoadSettled) {
                return;
            }
            if (!isContextActive(containerId, seq)) {
                return;
            }
            var settlementPhase = mapPhaseToSettlement(phase);
            if (!settlementPhase) {
                return;
            }
            onAuthoritativeLoadSettled({
                containerId: containerId,
                page: page,
                requestSeq: seq,
                phase: settlementPhase
            });
        }

        function handleSuccess(data, containerId, seq) {
            if (!isContextActive(containerId, seq)) {
                return;
            }

            setRecordsBusy(false);
            confirmedRecordsPage = data.page;
            confirmedPagination = {
                has_previous: data.has_previous,
                has_next: data.has_next,
                page: data.page,
                total_pages: data.total_pages
            };

            renderContainerSummary(data.container, data.total, data.amount_total);

            if (data.total === 0) {
                recordsPhase = RECORDS_PHASE.EMPTY;
                renderEmptyState();
                setStatusMessage('', false, false);
                updatePaginationControls(data, false);
            } else {
                recordsPhase = RECORDS_PHASE.READY;
                renderRecords(data.items);
                setStatusMessage('', false, false);
                updatePaginationControls(data, false);
            }

            focusHeading();
            emitAuthoritativeLoadSettled(containerId, data.page, seq, recordsPhase);
        }

        function handleErrorState(phase, message, recoverable, containerId, seq) {
            if (!isContextActive(containerId, seq)) {
                return;
            }
            recordsPhase = phase;
            setRecordsBusy(false);
            clearNode(gridEl);
            setStatusMessage(message, true, recoverable);
            updatePaginationControls({ total: 0 }, false);
            lastErrorRecoverable = recoverable;
            emitAuthoritativeLoadSettled(containerId, lastRequestedPage, seq, recordsPhase);
        }

        function fetchRecords(containerId, page) {
            if (recordsAbortController) {
                recordsAbortController.abort();
            }

            recordsAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            var seq = ++recordsRequestSeq;
            selectedContainerId = containerId;
            lastRequestedPage = page;
            recordsPhase = RECORDS_PHASE.LOADING;
            lastErrorRecoverable = false;

            setRecordsBusy(true);
            setStatusMessage('Cargando registros…', false, false);
            updatePaginationControls(confirmedPagination.total_pages > 0 ? {
                total: 1,
                page: lastRequestedPage,
                total_pages: confirmedPagination.total_pages,
                has_previous: confirmedPagination.has_previous,
                has_next: confirmedPagination.has_next
            } : { total: 0 }, true);

            clearNode(gridEl);

            var formData = new FormData();
            formData.append('action', listRecordsAction);
            formData.append('_wpnonce', nonce);
            formData.append('variant_key', variantKey);
            formData.append('container_id', String(containerId));
            formData.append('page', String(page));

            var fetchOptions = {
                method: 'POST',
                body: formData
            };
            if (recordsAbortController) {
                fetchOptions.signal = recordsAbortController.signal;
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
                    if (!isContextActive(containerId, seq)) {
                        return;
                    }

                    if (response.json && response.json.success === true) {
                        var data = response.json.data;
                        if (!validateEnvelope(data, containerId)) {
                            handleErrorState(
                                RECORDS_PHASE.RECOVERABLE_ERROR,
                                'Respuesta del servidor no válida.',
                                true,
                                containerId,
                                seq
                            );
                            return;
                        }
                        handleSuccess(data, containerId, seq);
                        return;
                    }

                    var errCode = '';
                    var errMsg = 'No se pudieron cargar los registros.';
                    if (response.json && response.json.data) {
                        if (typeof response.json.data.message === 'string') {
                            errMsg = response.json.data.message;
                        }
                        if (typeof response.json.data.code === 'string') {
                            errCode = response.json.data.code;
                        }
                    }

                    if (errCode === 'container_not_found') {
                        if (!isContextActive(containerId, seq)) {
                            return;
                        }
                        recordsPhase = RECORDS_PHASE.NOT_FOUND;
                        setRecordsBusy(false);
                        clearNode(gridEl);
                        setStatusMessage('Este contenedor ya no está disponible.', true, false);
                        updatePaginationControls({ total: 0 }, false);
                        onContainerNotFound(containerId);
                        emitAuthoritativeLoadSettled(containerId, lastRequestedPage, seq, recordsPhase);
                        return;
                    }

                    if (BLOCKED_CODES.indexOf(errCode) !== -1) {
                        handleErrorState(
                            RECORDS_PHASE.BLOCKED_ERROR,
                            'La lectura de registros no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.',
                            false,
                            containerId,
                            seq
                        );
                        return;
                    }

                    if (RECOVERABLE_CODES.indexOf(errCode) !== -1 || !response.ok || response.status >= 500) {
                        handleErrorState(
                            RECORDS_PHASE.RECOVERABLE_ERROR,
                            errMsg,
                            true,
                            containerId,
                            seq
                        );
                        return;
                    }

                    handleErrorState(
                        RECORDS_PHASE.RECOVERABLE_ERROR,
                        errMsg,
                        true,
                        containerId,
                        seq
                    );
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    handleErrorState(
                        RECORDS_PHASE.RECOVERABLE_ERROR,
                        'Error de conexión con el servidor.',
                        true,
                        containerId,
                        seq
                    );
                });
        }

        function open(containerId, page) {
            if (!Number.isInteger(containerId) || containerId < 1) {
                return;
            }
            var targetPage = Number.isInteger(page) && page >= 1 ? page : 1;
            fetchRecords(containerId, targetPage);
        }

        function retry() {
            if (selectedContainerId === null) {
                return;
            }
            if (recordsPhase === RECORDS_PHASE.BLOCKED_ERROR || recordsPhase === RECORDS_PHASE.NOT_FOUND) {
                return;
            }
            fetchRecords(selectedContainerId, lastRequestedPage);
        }

        function close() {
            if (recordsAbortController) {
                recordsAbortController.abort();
                recordsAbortController = null;
            }
            recordsRequestSeq++;
            recordsPhase = RECORDS_PHASE.IDLE;
            selectedContainerId = null;
            confirmedRecordsPage = 1;
            lastRequestedPage = 1;
            lastErrorRecoverable = false;
            confirmedPagination = {
                has_previous: false,
                has_next: false,
                page: 1,
                total_pages: 0
            };
            setRecordsBusy(false);
            setStatusMessage('', false, false);
            clearNode(gridEl);
            clearNode(summaryEl);
            if (headingEl) {
                headingEl.textContent = '';
            }
            if (paginationEl) {
                paginationEl.classList.add('hidden');
                paginationEl.hidden = true;
            }
        }

        function destroy() {
            if (prevBtn && boundPrevHandler) {
                prevBtn.removeEventListener('click', boundPrevHandler);
            }
            if (nextBtn && boundNextHandler) {
                nextBtn.removeEventListener('click', boundNextHandler);
            }
            close();
        }

        boundPrevHandler = function () {
            if (recordsPhase === RECORDS_PHASE.LOADING || selectedContainerId === null) {
                return;
            }
            if (confirmedRecordsPage > 1) {
                open(selectedContainerId, confirmedRecordsPage - 1);
            }
        };

        boundNextHandler = function () {
            if (recordsPhase === RECORDS_PHASE.LOADING || selectedContainerId === null) {
                return;
            }
            if (confirmedPagination.has_next) {
                open(selectedContainerId, confirmedRecordsPage + 1);
            }
        };

        if (prevBtn) {
            prevBtn.addEventListener('click', boundPrevHandler);
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', boundNextHandler);
        }

        return {
            open: open,
            retry: retry,
            close: close,
            destroy: destroy,
            getConfirmedPage: function () {
                return confirmedRecordsPage;
            },
            setDeleteActionsEnabled: setDeleteActionsEnabled,
            setEditActionsEnabled: setEditActionsEnabled
        };
    }

    window.AA_FinanceRecords = {
        createController: createController
    };
})();
