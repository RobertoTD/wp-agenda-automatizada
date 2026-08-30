/**
 * Finance Canonical Module — Controlador JavaScript del listado de Contenedores en modo lectura.
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
    var gridEl = document.getElementById('aa-finance-grid');
    var paginationEl = document.getElementById('aa-finance-pagination');
    var prevBtn = document.getElementById('aa-finance-prev');
    var nextBtn = document.getElementById('aa-finance-next');
    var pageIndicatorEl = document.getElementById('aa-finance-page-indicator');

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

    if (root.dataset.aaInitialized === 'true') {
        return;
    }
    root.dataset.aaInitialized = 'true';

    var confirmedPage = 1;
    var requestedPage = 1;
    var failedPage = null;
    var requestSeq = 0;
    var activeAbortController = null;
    var isFetching = false;

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
            prevBtn.disabled = !data.has_previous || isFetching;
        }
        if (nextBtn) {
            nextBtn.disabled = !data.has_next || isFetching;
        }
        if (pageIndicatorEl) {
            var totalPagesDisplay = data.total_pages > 0 ? data.total_pages : 1;
            pageIndicatorEl.textContent = 'Página ' + data.page + ' de ' + totalPagesDisplay;
        }
    }

    function loadPage(page) {
        if (isFetching && activeAbortController) {
            activeAbortController.abort();
        }

        activeAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var seq = ++requestSeq;
        isFetching = true;
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
        if (activeAbortController) {
            fetchOptions.signal = activeAbortController.signal;
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
                isFetching = false;
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
                setStatus('', false, false);

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
                isFetching = false;
                if (gridEl) {
                    gridEl.setAttribute('aria-busy', 'false');
                }
                failedPage = requestedPage;
                setStatus('Error de conexión con el servidor.', true, true);
            });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (confirmedPage > 1 && !isFetching) {
                loadPage(confirmedPage - 1);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (!isFetching) {
                loadPage(confirmedPage + 1);
            }
        });
    }

    loadPage(1);
})();
