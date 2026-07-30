/**
 * Expediente registros — chronology list + create modal (MC2).
 *
 * Loaded only on view=expediente. Create/edit share one form builder; MC2 wires create only.
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

    function createRecordDetails(record) {
        var details = document.createElement('details');
        details.className = 'aa-expediente-registro';
        details.setAttribute('data-registro-id', String(record.id));

        var summary = document.createElement('summary');
        summary.className = 'aa-expediente-registro-summary';

        var dateSpan = document.createElement('span');
        dateSpan.className = 'aa-expediente-registro-date';
        dateSpan.textContent = formatRecordedAt(record.recorded_at);

        var titleSpan = document.createElement('span');
        titleSpan.className = 'aa-expediente-registro-title';
        titleSpan.textContent = record.title || 'Sin título';

        summary.appendChild(dateSpan);
        summary.appendChild(document.createTextNode(' · '));
        summary.appendChild(titleSpan);

        var body = document.createElement('div');
        body.className = 'aa-expediente-registro-body';
        body.textContent = record.body || '';

        details.appendChild(summary);
        details.appendChild(body);

        return details;
    }

    function renderRecordsList() {
        clearNode(state.recordsRoot);

        var toolbar = document.createElement('div');
        toolbar.className = 'aa-expediente-registros-toolbar';

        var newBtn = document.createElement('button');
        newBtn.type = 'button';
        newBtn.className = 'aa-expediente-nuevo-registro-btn';
        newBtn.textContent = 'Nuevo registro';
        newBtn.addEventListener('click', function (event) {
            event.preventDefault();
            openCreateForm();
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
            list.appendChild(createRecordDetails(record));
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
        renderRecordsList();
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
     * Shared create/edit form builder. MC2 only wires mode=create.
     *
     * @param {{mode:string, record?:object}} options
     */
    function openRegistroForm(options) {
        var mode = (options && options.mode) || 'create';
        var record = (options && options.record) || null;

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

        window.setTimeout(function () {
            titleInput.focus();
        }, 50);

        function showFormError(message) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
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

            if (mode !== 'create') {
                // MC3 will wire edit here.
                showFormError('La edición aún no está disponible.');
                return;
            }

            var data = getConfig();
            var action = (data.actions && data.actions.createRegistro) || 'aa_create_expediente_registro';

            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';

            postForm(action, {
                client_id: String(state.clientId),
                title: title,
                body: body
            })
                .then(function (payload) {
                    var result = payload.result;
                    if (result && result.success && result.data && result.data.record) {
                        if (typeof window.AAAdmin.closeModal === 'function') {
                            window.AAAdmin.closeModal();
                        }
                        prependRecord(result.data.record);
                        return;
                    }
                    var message = 'No se pudo guardar el registro.';
                    if (result && result.data && result.data.message) {
                        message = String(result.data.message);
                    }
                    showFormError(message);
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar';
                })
                .catch(function (err) {
                    console.error('[ExpedienteRegistros] create failed:', err);
                    showFormError('No se pudo guardar el registro.');
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar';
                });
        });
    }

    function openCreateForm() {
        openRegistroForm({ mode: 'create' });
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

    AAAdmin.ExpedienteRegistros = {
        init: init,
        openRegistroForm: openRegistroForm
    };
})();
