/**
 * Expediente registros — chronology list + create/edit modal (MC2/MC3).
 *
 * Loaded only on view=expediente. Create and edit share one modal form.
 */

(function () {
    'use strict';

    window.AAAdmin = window.AAAdmin || {};

    var state = {
        clientId: 0,
        recordsRoot: null,
        records: [],
        loading: false
    };

    // Solo para abortar el watcher de cierre del modal (foco / limpieza).
    var modalCloseAbort = null;

    function getConfig() {
        return window.AA_CLIENTS_DATA || {};
    }

    function getNonce() {
        var nonces = window.AA_CLIENTS_NONCES || {};
        return nonces.expediente_registros || '';
    }

    function getAjaxUrl() {
        var data = getConfig();
        return data.ajaxUrl || window.ajaxurl || '';
    }

    function formatRecordedAt(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        // MySQL datetime → DD/MM/YYYY HH:mm (local display, no TZ libs)
        var m = value.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if (!m) {
            return value;
        }
        return m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5];
    }

    /**
     * MySQL datetime → HTML time[datetime] without inventing timezone.
     * @param {string} value
     * @returns {string}
     */
    function toDatetimeAttr(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        var m = value.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
        if (!m) {
            return '';
        }
        var seconds = m[6] || '00';
        return m[1] + '-' + m[2] + '-' + m[3] + 'T' + m[4] + ':' + m[5] + ':' + seconds;
    }

    function clearNode(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function renderStatusMessage(text, className) {
        clearNode(state.recordsRoot);
        var p = document.createElement('p');
        p.className = className || 'text-sm text-gray-500';
        p.textContent = text;
        state.recordsRoot.appendChild(p);
    }

    function renderEmpty() {
        renderStatusMessage('Aún no hay registros en este expediente', 'text-sm text-gray-500');
    }

    function renderError(message) {
        renderStatusMessage(message || 'No se pudieron cargar los registros.', 'text-sm text-red-600');
    }

    function findRecordById(recordId) {
        var id = parseInt(recordId, 10);
        if (!(id > 0)) {
            return null;
        }
        for (var i = 0; i < state.records.length; i++) {
            if (parseInt(state.records[i].id, 10) === id) {
                return state.records[i];
            }
        }
        return null;
    }

    function focusEditButtonById(recordId) {
        var id = parseInt(recordId, 10);
        if (!(id > 0) || !state.recordsRoot) {
            return;
        }
        var btn = state.recordsRoot.querySelector(
            '.aa-expediente-btn-editar[data-registro-id="' + id + '"]'
        );
        if (btn && typeof btn.focus === 'function') {
            try {
                btn.focus({ preventScroll: true });
            } catch (e) {
                btn.focus();
            }
        }
    }

    function focusElement(el) {
        if (!el || typeof el.focus !== 'function') {
            return;
        }
        if (typeof document !== 'undefined' && document.contains && !document.contains(el)) {
            return;
        }
        try {
            el.focus({ preventScroll: true });
        } catch (e) {
            el.focus();
        }
    }

    /**
     * Observa el cierre del modal (Cancelar / X / Escape / overlay) sin listeners documentales extra.
     * @param {function():void} onClosed
     * @returns {function(Object=):void} abort
     */
    function watchModalClose(onClosed) {
        var root = typeof document !== 'undefined'
            ? document.getElementById('aa-modal-root')
            : null;
        if (!root || typeof MutationObserver === 'undefined') {
            return function abort() {};
        }

        var done = false;
        var observer = new MutationObserver(function () {
            if (done) {
                return;
            }
            if (root.classList && root.classList.contains('hidden')) {
                done = true;
                observer.disconnect();
                onClosed();
            }
        });
        observer.observe(root, { attributes: true, attributeFilter: ['class'] });

        return function abort(options) {
            if (done) {
                return;
            }
            done = true;
            observer.disconnect();
            if (options && options.runCallback) {
                onClosed();
            }
        };
    }

    function disarmModalCloseWatcher(options) {
        if (!modalCloseAbort) {
            return;
        }
        var abort = modalCloseAbort;
        modalCloseAbort = null;
        abort(options || {});
    }

    function armModalCloseFocus(focusReturnEl) {
        disarmModalCloseWatcher();
        modalCloseAbort = watchModalClose(function () {
            modalCloseAbort = null;
            focusElement(focusReturnEl);
        });
    }

    /**
     * @param {object} record
     * @param {{open?: boolean}} [options]
     * @returns {HTMLDetailsElement}
     */
    function createRecordDetails(record, options) {
        options = options || {};

        var details = document.createElement('details');
        details.className = 'aa-expediente-registro';
        details.setAttribute('data-registro-id', String(record.id));
        if (options.open) {
            details.open = true;
        }

        var summary = document.createElement('summary');
        summary.className = 'aa-expediente-registro-summary';

        var summaryMain = document.createElement('div');
        summaryMain.className = 'aa-expediente-registro-summary-main';

        var titleSpan = document.createElement('span');
        titleSpan.className = 'aa-expediente-registro-title';
        titleSpan.textContent = record.title || 'Sin título';

        var meta = document.createElement('div');
        meta.className = 'aa-expediente-registro-meta';

        var folioSpan = document.createElement('span');
        folioSpan.className = 'aa-expediente-registro-folio';
        folioSpan.textContent = 'Folio #' + String(record.id);

        var timeEl = document.createElement('time');
        timeEl.className = 'aa-expediente-registro-date';
        var datetimeAttr = toDatetimeAttr(record.recorded_at);
        if (datetimeAttr) {
            timeEl.setAttribute('datetime', datetimeAttr);
        }
        timeEl.textContent = formatRecordedAt(record.recorded_at);

        meta.appendChild(folioSpan);
        meta.appendChild(timeEl);

        summaryMain.appendChild(titleSpan);
        summaryMain.appendChild(meta);
        summary.appendChild(summaryMain);

        var panel = document.createElement('div');
        panel.className = 'aa-expediente-registro-panel';

        var body = document.createElement('div');
        body.className = 'aa-expediente-registro-body';
        body.textContent = record.body || '';

        var actions = document.createElement('div');
        actions.className = 'aa-expediente-registro-actions';

        var editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'aa-expediente-btn-editar';
        editBtn.setAttribute('data-registro-id', String(record.id));
        editBtn.textContent = 'Editar';
        editBtn.addEventListener('click', function (event) {
            event.preventDefault();
            openEditForm(record.id, editBtn);
        });
        actions.appendChild(editBtn);

        panel.appendChild(body);
        panel.appendChild(actions);

        details.appendChild(summary);
        details.appendChild(panel);

        return details;
    }

    /**
     * @param {{expandId?: number|string}} [options]
     */
    function renderRecordsList(options) {
        options = options || {};
        var expandId = options.expandId != null ? parseInt(options.expandId, 10) : 0;
        if (!(expandId > 0)) {
            expandId = 0;
        }

        clearNode(state.recordsRoot);

        var toolbar = document.createElement('div');
        toolbar.className = 'aa-expediente-registros-toolbar';

        var newBtn = document.createElement('button');
        newBtn.type = 'button';
        newBtn.className = 'aa-expediente-nuevo-registro-btn';
        newBtn.textContent = 'Nuevo registro';
        newBtn.addEventListener('click', function (event) {
            event.preventDefault();
            openCreateForm(newBtn);
        });
        toolbar.appendChild(newBtn);
        state.recordsRoot.appendChild(toolbar);

        if (!state.records.length) {
            var empty = document.createElement('p');
            empty.className = 'text-sm text-gray-500 aa-expediente-registros-empty';
            empty.textContent = 'Aún no hay registros en este expediente';
            state.recordsRoot.appendChild(empty);
            return;
        }

        var list = document.createElement('div');
        list.className = 'aa-expediente-registros-list';
        state.records.forEach(function (record) {
            var shouldOpen = expandId > 0 && parseInt(record.id, 10) === expandId;
            list.appendChild(createRecordDetails(record, { open: shouldOpen }));
        });
        state.recordsRoot.appendChild(list);
    }

    function sortRecordsDesc(records) {
        return records.slice().sort(function (a, b) {
            var ra = String(a.recorded_at || '');
            var rb = String(b.recorded_at || '');
            if (ra !== rb) {
                return ra < rb ? 1 : -1;
            }
            return (b.id || 0) - (a.id || 0);
        });
    }

    function prependRecord(record) {
        state.records = sortRecordsDesc([record].concat(state.records));
        renderRecordsList({ expandId: record && record.id });
    }

    /**
     * Reemplaza por id sin duplicar ni reordenar por updated_at.
     * @param {object} record
     */
    function replaceRecord(record) {
        if (!record || !(parseInt(record.id, 10) > 0)) {
            return;
        }
        var id = parseInt(record.id, 10);
        var replaced = false;
        state.records = state.records.map(function (existing) {
            if (parseInt(existing.id, 10) === id) {
                replaced = true;
                return record;
            }
            return existing;
        });
        if (!replaced) {
            return;
        }
        renderRecordsList({ expandId: id });
    }

    function postForm(action, fields) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', getNonce());
        Object.keys(fields).forEach(function (key) {
            formData.append(key, fields[key]);
        });

        return fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (result) {
                return { httpStatus: response.status, result: result };
            });
        });
    }

    function loadRecords() {
        var data = getConfig();
        var action = (data.actions && data.actions.listRegistros) || 'aa_list_expediente_registros';

        state.loading = true;
        renderStatusMessage('Cargando registros...', 'text-sm text-gray-500');

        return postForm(action, { client_id: String(state.clientId) })
            .then(function (payload) {
                state.loading = false;
                var result = payload.result;
                if (result && result.success && result.data && Array.isArray(result.data.records)) {
                    state.records = sortRecordsDesc(result.data.records);
                    renderRecordsList();
                    return;
                }
                var message = 'No se pudieron cargar los registros.';
                if (result && result.data && result.data.message) {
                    message = String(result.data.message);
                }
                renderError(message);
            })
            .catch(function (err) {
                state.loading = false;
                console.error('[ExpedienteRegistros] list failed:', err);
                renderError('No se pudieron cargar los registros.');
            });
    }

    /**
     * Shared create/edit form. Mode and record_id live in this opening's closure.
     *
     * @param {{mode:string, record?:object, recordId?:number, focusReturnEl?:HTMLElement}} options
     */
    function openRegistroForm(options) {
        options = options || {};
        var mode = options.mode || 'create';
        var record = options.record || null;
        var recordId = options.recordId != null
            ? parseInt(options.recordId, 10)
            : (record && record.id != null ? parseInt(record.id, 10) : 0);
        if (!(recordId > 0)) {
            recordId = 0;
        }
        var focusReturnEl = options.focusReturnEl || null;

        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('[ExpedienteRegistros] AAAdmin.openModal no disponible');
            return;
        }

        var bodyWrap = document.createElement('div');
        bodyWrap.className = 'aa-expediente-registro-form space-y-3';

        var titleLabel = document.createElement('label');
        titleLabel.className = 'block text-sm font-medium text-gray-700';
        titleLabel.setAttribute('for', 'aa-expediente-registro-title');
        titleLabel.textContent = 'Título';

        var titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.id = 'aa-expediente-registro-title';
        titleInput.className = 'aa-expediente-registro-input';
        titleInput.maxLength = 200;
        titleInput.required = true;
        titleInput.value = '';
        if (mode === 'edit' && record) {
            titleInput.value = record.title || '';
        }

        var bodyLabel = document.createElement('label');
        bodyLabel.className = 'block text-sm font-medium text-gray-700';
        bodyLabel.setAttribute('for', 'aa-expediente-registro-body');
        bodyLabel.textContent = 'Texto';

        var bodyInput = document.createElement('textarea');
        bodyInput.id = 'aa-expediente-registro-body';
        bodyInput.className = 'aa-expediente-registro-textarea';
        bodyInput.rows = 6;
        bodyInput.required = true;
        bodyInput.value = '';
        if (mode === 'edit' && record) {
            bodyInput.value = record.body || '';
        }

        var errorEl = document.createElement('p');
        errorEl.className = 'aa-expediente-registro-form-error text-sm text-red-600 hidden';
        errorEl.setAttribute('role', 'alert');

        bodyWrap.appendChild(titleLabel);
        bodyWrap.appendChild(titleInput);
        bodyWrap.appendChild(bodyLabel);
        bodyWrap.appendChild(bodyInput);
        bodyWrap.appendChild(errorEl);

        var footer = document.createElement('div');
        footer.className = 'flex flex-wrap gap-2 justify-end';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');
        cancelBtn.textContent = 'Cancelar';

        var saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        window.AAAdmin.openModal({
            title: mode === 'edit' ? 'Editar registro' : 'Nuevo registro',
            body: bodyWrap,
            footer: footer
        });

        armModalCloseFocus(focusReturnEl);

        window.setTimeout(function () {
            titleInput.focus();
        }, 50);

        function showFormError(message) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }

        function reenableSave() {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Guardar';
        }

        saveBtn.addEventListener('click', function (event) {
            event.preventDefault();
            errorEl.classList.add('hidden');

            var title = String(titleInput.value || '').trim();
            var body = String(bodyInput.value || '').trim();

            if (!title) {
                showFormError('El título es obligatorio.');
                titleInput.focus();
                return;
            }
            if (!body) {
                showFormError('El texto es obligatorio.');
                bodyInput.focus();
                return;
            }

            var data = getConfig();
            var action;
            var fields = {
                client_id: String(state.clientId),
                title: title,
                body: body
            };

            if (mode === 'edit') {
                if (!(recordId > 0)) {
                    showFormError('Registro no válido.');
                    return;
                }
                action = (data.actions && data.actions.updateRegistro) || 'aa_update_expediente_registro';
                fields.record_id = String(recordId);
            } else {
                action = (data.actions && data.actions.createRegistro) || 'aa_create_expediente_registro';
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';

            postForm(action, fields)
                .then(function (payload) {
                    var result = payload.result;
                    if (result && result.success && result.data && result.data.record) {
                        var saved = result.data.record;
                        // Evitar que el watcher de cierre enfoque el botón viejo / desconectado.
                        disarmModalCloseWatcher();
                        if (typeof window.AAAdmin.closeModal === 'function') {
                            window.AAAdmin.closeModal();
                        }
                        if (mode === 'edit') {
                            replaceRecord(saved);
                            focusEditButtonById(saved.id);
                        } else {
                            prependRecord(saved);
                        }
                        return;
                    }
                    var message = mode === 'edit'
                        ? 'No se pudo actualizar el registro.'
                        : 'No se pudo guardar el registro.';
                    if (result && result.data && result.data.message) {
                        message = String(result.data.message);
                    }
                    showFormError(message);
                    reenableSave();
                })
                .catch(function (err) {
                    console.error('[ExpedienteRegistros] save failed:', err);
                    showFormError(
                        mode === 'edit'
                            ? 'No se pudo actualizar el registro.'
                            : 'No se pudo guardar el registro.'
                    );
                    reenableSave();
                });
        });
    }

    function openCreateForm(focusReturnEl) {
        openRegistroForm({
            mode: 'create',
            focusReturnEl: focusReturnEl || null
        });
    }

    function openEditForm(recordId, focusReturnEl) {
        var record = findRecordById(recordId);
        if (!record) {
            console.error('[ExpedienteRegistros] registro no encontrado en estado');
            return;
        }
        openRegistroForm({
            mode: 'edit',
            record: record,
            recordId: parseInt(record.id, 10),
            focusReturnEl: focusReturnEl || null
        });
    }

    /**
     * @param {{clientId:number, recordsRoot:HTMLElement}} options
     */
    function init(options) {
        if (!options || !options.recordsRoot) {
            console.error('[ExpedienteRegistros] init requiere recordsRoot');
            return;
        }

        var clientId = parseInt(options.clientId, 10);
        if (!(clientId > 0)) {
            console.error('[ExpedienteRegistros] clientId inválido');
            return;
        }

        state.clientId = clientId;
        state.recordsRoot = options.recordsRoot;
        state.records = [];

        loadRecords();
    }

    window.AAAdmin.ExpedienteRegistros = {
        init: init,
        openRegistroForm: openRegistroForm,
        __test__: {
            createRecordDetails: createRecordDetails,
            toDatetimeAttr: toDatetimeAttr,
            formatRecordedAt: formatRecordedAt,
            sortRecordsDesc: sortRecordsDesc,
            renderRecordsList: renderRecordsList,
            prependRecord: prependRecord,
            replaceRecord: replaceRecord,
            findRecordById: findRecordById,
            getState: function () { return state; },
            setState: function (partial) {
                Object.keys(partial || {}).forEach(function (key) {
                    state[key] = partial[key];
                });
            }
        }
    };
})();
