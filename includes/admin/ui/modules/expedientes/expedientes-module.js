/**
 * Expedientes Module — listado paginado de expedientes padre.
 *
 * Consume window.AA_EXPEDIENTES_DATA y aa_list_expedientes.
 * Sin reglas de negocio: el servidor pagina, filtra y autoriza.
 */
(function () {
    'use strict';

    var FOLDER_SVG = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>';

    var SEARCH_DEBOUNCE_MS = 300;
    var MSG_LOADING = 'Cargando expedientes…';
    var MSG_ERROR = 'No se pudieron cargar los expedientes.';
    var MSG_EMPTY = 'Aún no hay expedientes.';
    var MSG_NO_RESULTS = 'No se encontraron expedientes.';

    var currentQuery = '';
    var currentPage = 1;
    var hasPrevious = false;
    var hasNext = false;
    var searchTimeout = null;
    var activeController = null;
    var requestSeq = 0;
    var listenersBound = false;
    var initialized = false;

    function getConfig() {
        var cfg = window.AA_EXPEDIENTES_DATA || {};
        var actions = cfg.actions && typeof cfg.actions === 'object' ? cfg.actions : {};

        return {
            ajaxUrl: cfg.ajaxUrl || window.ajaxurl || '',
            nonce: cfg.nonce || '',
            listAction: actions.list || 'aa_list_expedientes',
            moduleBaseUrl: typeof cfg.moduleBaseUrl === 'string' ? cfg.moduleBaseUrl : ''
        };
    }

    function buildDetailUrl(expedienteId) {
        var id = parseInt(expedienteId, 10);
        if (!(id > 0)) {
            return '';
        }

        var base = getConfig().moduleBaseUrl;
        if (!base) {
            return '';
        }

        try {
            var origin = (window.location && typeof window.location.href === 'string')
                ? window.location.href
                : undefined;
            var url = origin ? new URL(base, origin) : new URL(base);
            url.searchParams.set('view', 'detail');
            url.searchParams.set('expediente_id', String(id));
            return url.toString();
        } catch (err) {
            return '';
        }
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function setStatus(message, isError) {
        var status = byId('aa-expedientes-status');
        if (!status) {
            return;
        }

        status.textContent = message || '';
        if (isError) {
            status.classList.add('is-error');
        } else {
            status.classList.remove('is-error');
        }
    }

    function clearGrid() {
        var grid = byId('aa-expedientes-grid');
        if (!grid) {
            return;
        }

        while (grid.firstChild) {
            grid.removeChild(grid.firstChild);
        }
    }

    function setGridBusy(busy) {
        var grid = byId('aa-expedientes-grid');
        if (!grid) {
            return;
        }

        if (busy) {
            grid.setAttribute('aria-busy', 'true');
        } else {
            grid.removeAttribute('aria-busy');
        }
    }

    function showLoading() {
        setGridBusy(true);
        clearGrid();
        setStatus(MSG_LOADING, false);
    }

    function showError() {
        setGridBusy(false);
        clearGrid();
        setStatus(MSG_ERROR, true);
        updatePagination({
            totalPages: 0,
            hasPrevious: false,
            hasNext: false
        });
    }

    function formatCreatedAt(value) {
        if (!value || typeof value !== 'string') {
            return '—';
        }

        var match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) {
            return value;
        }

        return match[3] + '/' + match[2] + '/' + match[1];
    }

    function createMetaRow(label, value) {
        var row = document.createElement('div');
        row.className = 'aa-expediente-card-meta';

        var title = document.createElement('span');
        title.className = 'aa-expediente-card-meta-label';
        title.textContent = label + ' ';

        var content = document.createElement('span');
        content.className = 'aa-expediente-card-meta-value';
        content.textContent = value;

        row.appendChild(title);
        row.appendChild(content);
        return row;
    }

    function createExpedienteCard(expediente) {
        var item = expediente && typeof expediente === 'object' ? expediente : {};
        var id = parseInt(item.id, 10);
        var titleText = typeof item.title === 'string' && item.title !== '' ? item.title : 'Sin título';
        var category = item.category && typeof item.category === 'object' ? item.category : {};
        var categoryName = typeof category.name === 'string' && category.name !== ''
            ? category.name
            : '—';
        var detailUrl = id > 0 ? buildDetailUrl(id) : '';

        var card = document.createElement('div');
        card.className = 'aa-expediente-card';
        card.setAttribute('data-aa-card', '');
        if (id > 0) {
            card.setAttribute('data-expediente-card-id', String(id));
        }

        var link = document.createElement(detailUrl ? 'a' : 'div');
        link.className = 'aa-expediente-card-link';
        if (detailUrl) {
            link.setAttribute('href', detailUrl);
        }

        var titleRow = document.createElement('div');
        titleRow.className = 'aa-expediente-card-header flex items-center min-w-0';

        var iconWrap = document.createElement('span');
        iconWrap.className = 'flex items-center justify-center w-8 h-8 text-gray-600 shrink-0';
        iconWrap.setAttribute('aria-hidden', 'true');
        iconWrap.innerHTML = FOLDER_SVG;

        var name = document.createElement('span');
        name.className = 'aa-expediente-card-title min-w-0 truncate text-lg font-semibold text-gray-600';
        name.textContent = titleText;

        titleRow.appendChild(iconWrap);
        titleRow.appendChild(name);
        link.appendChild(titleRow);
        link.appendChild(createMetaRow('Categoría:', categoryName));
        link.appendChild(createMetaRow('Creado:', formatCreatedAt(item.created_at)));

        card.appendChild(link);

        return card;
    }

    function renderGrid(items) {
        var grid = byId('aa-expedientes-grid');
        if (!grid) {
            return;
        }

        clearGrid();
        items.forEach(function (item) {
            grid.appendChild(createExpedienteCard(item));
        });
    }

    function updatePagination(state) {
        var container = byId('aa-expedientes-pagination');
        var prevButton = byId('aa-expedientes-prev');
        var nextButton = byId('aa-expedientes-next');
        var totalPages = state && typeof state.totalPages === 'number' ? state.totalPages : 0;
        var show = totalPages > 1;

        hasPrevious = !!(state && state.hasPrevious);
        hasNext = !!(state && state.hasNext);

        if (container) {
            if (show) {
                container.classList.remove('hidden');
                container.removeAttribute('hidden');
            } else {
                container.classList.add('hidden');
                container.setAttribute('hidden', '');
            }
        }

        if (prevButton) {
            prevButton.disabled = !show || !hasPrevious;
        }

        if (nextButton) {
            nextButton.disabled = !show || !hasNext;
        }
    }

    function applyListResult(data, query) {
        var page = parseInt(data.page, 10);
        currentPage = page >= 1 ? page : 1;
        currentQuery = typeof query === 'string' ? query : '';

        var items = Array.isArray(data.expedientes) ? data.expedientes : [];
        var total = parseInt(data.total, 10);
        if (!(total >= 0)) {
            total = items.length;
        }
        var totalPages = parseInt(data.total_pages, 10);
        if (!(totalPages >= 0)) {
            totalPages = 0;
        }

        updatePagination({
            totalPages: totalPages,
            hasPrevious: data.has_previous === true,
            hasNext: data.has_next === true
        });

        setGridBusy(false);

        if (items.length === 0) {
            clearGrid();
            setStatus(currentQuery !== '' ? MSG_NO_RESULTS : MSG_EMPTY, false);
            return;
        }

        setStatus('', false);
        renderGrid(items);
    }

    function isAbortError(err) {
        if (!err) {
            return false;
        }
        if (err.name === 'AbortError') {
            return true;
        }
        return typeof DOMException !== 'undefined'
            && typeof DOMException.ABORT_ERR !== 'undefined'
            && err.code === DOMException.ABORT_ERR;
    }

    function loadExpedientes(query, page) {
        var cfg = getConfig();
        var seq = requestSeq + 1;
        requestSeq = seq;
        var requestedQuery = typeof query === 'string' ? query : '';
        var requestedPage = parseInt(page, 10);
        if (!(requestedPage >= 1)) {
            requestedPage = 1;
        }

        if (activeController && typeof activeController.abort === 'function') {
            activeController.abort();
        }
        activeController = typeof AbortController === 'function' ? new AbortController() : null;

        showLoading();

        if (!cfg.ajaxUrl || !cfg.nonce) {
            showError();
            return Promise.resolve();
        }

        var formData = new FormData();
        formData.append('action', cfg.listAction);
        formData.append('_wpnonce', cfg.nonce);
        formData.append('query', requestedQuery);
        formData.append('page', String(requestedPage));

        var fetchOpts = {
            method: 'POST',
            body: formData
        };
        if (activeController) {
            fetchOpts.signal = activeController.signal;
        }

        return fetch(cfg.ajaxUrl, fetchOpts)
            .then(function (response) {
                if (seq !== requestSeq) {
                    return null;
                }
                if (!response || response.ok === false) {
                    throw new Error('http');
                }
                return response.json();
            })
            .then(function (result) {
                if (result === null || seq !== requestSeq) {
                    return;
                }
                if (!result || result.success !== true || !result.data || typeof result.data !== 'object') {
                    showError();
                    return;
                }
                applyListResult(result.data, requestedQuery);
            })
            .catch(function (err) {
                if (seq !== requestSeq || isAbortError(err)) {
                    return;
                }
                showError();
            });
    }

    function readSearchQuery() {
        var input = byId('aa-expedientes-search');
        if (!input || typeof input.value !== 'string') {
            return '';
        }
        return input.value.trim();
    }

    function scheduleSearch() {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(function () {
            searchTimeout = null;
            loadExpedientes(readSearchQuery(), 1);
        }, SEARCH_DEBOUNCE_MS);
    }

    function searchNow() {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
            searchTimeout = null;
        }
        loadExpedientes(readSearchQuery(), 1);
    }

    function resetSearchAndReload() {
        var input = byId('aa-expedientes-search');
        if (input) {
            input.value = '';
        }
        if (searchTimeout) {
            clearTimeout(searchTimeout);
            searchTimeout = null;
        }
        return loadExpedientes('', 1);
    }

    function bindFab() {
        var fab = byId('aa-expedientes-new-expediente');
        if (!fab) {
            return;
        }
        fab.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            if (window.AAAdmin
                && window.AAAdmin.ExpedienteCreateModal
                && typeof window.AAAdmin.ExpedienteCreateModal.openCreate === 'function') {
                window.AAAdmin.ExpedienteCreateModal.openCreate();
                return;
            }
            console.error('[Expedientes] ExpedienteCreateModal.openCreate no disponible');
        });
    }

    function bindUi() {
        if (listenersBound) {
            return;
        }
        listenersBound = true;

        var input = byId('aa-expedientes-search');
        var prevButton = byId('aa-expedientes-prev');
        var nextButton = byId('aa-expedientes-next');

        if (input) {
            input.addEventListener('input', function () {
                scheduleSearch();
            });
            input.addEventListener('keydown', function (event) {
                if (!event) {
                    return;
                }
                if (event.key === 'Enter' || event.keyCode === 13) {
                    event.preventDefault();
                    searchNow();
                }
            });
        }

        if (prevButton) {
            prevButton.addEventListener('click', function () {
                if (!hasPrevious || currentPage <= 1) {
                    return;
                }
                loadExpedientes(currentQuery, currentPage - 1);
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                if (!hasNext) {
                    return;
                }
                loadExpedientes(currentQuery, currentPage + 1);
            });
        }
    }

    function bindSavedListener() {
        document.addEventListener('aa:expediente:saved', function () {
            resetSearchAndReload();
        });
    }

    function init() {
        if (initialized) {
            return Promise.resolve();
        }
        if (!byId('aa-expedientes-root')) {
            return Promise.resolve();
        }
        initialized = true;
        bindUi();
        bindFab();
        bindSavedListener();
        return loadExpedientes('', 1);
    }

    window.AAAdmin = window.AAAdmin || {};
    window.AAAdmin.ExpedientesModule = {
        init: init,
        load: loadExpedientes,
        resetSearchAndReload: resetSearchAndReload,
        formatCreatedAt: formatCreatedAt,
        createCard: createExpedienteCard,
        buildDetailUrl: buildDetailUrl,
        SEARCH_DEBOUNCE_MS: SEARCH_DEBOUNCE_MS
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
