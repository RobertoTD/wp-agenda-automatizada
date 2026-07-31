/**
 * Expediente registros — chronology list + create/edit modal (MC2/MC3/MC4b).
 *
 * Loaded only on view=expediente. Create and edit share one modal form.
 * Adjunto opcional: texto primero (create/update), luego aa_attach_expediente_registro.
 */

(function () {
    'use strict';

    window.AAAdmin = window.AAAdmin || {};

    var MAX_IMAGE_EDGE = 2048;
    var MAX_IMAGE_BYTES = 1048576;
    var HEIC_UNSUPPORTED_MESSAGE =
        'Este formato no se puede procesar aquí. Guarda o exporta la foto como JPG e inténtalo de nuevo.';
    var PARTIAL_ATTACH_MESSAGE = 'Registro guardado. No se pudo subir la imagen.';

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

    /**
     * Multipart attach: solo contexto mínimo + upload_operation_id + file.
     * @param {string} action
     * @param {{client_id:string, record_id:string, upload_operation_id:string}} fields
     * @param {Blob} fileBlob
     * @param {string} fileName
     */
    function postAttach(action, fields, fileBlob, fileName) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', getNonce());
        formData.append('client_id', fields.client_id);
        formData.append('record_id', fields.record_id);
        formData.append('upload_operation_id', fields.upload_operation_id);
        formData.append('file', fileBlob, fileName || 'adjunto.jpg');

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

    function generateUploadOperationId() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        // Fallback UUID v4
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function revokePreviewUrl(url) {
        if (!url) {
            return;
        }
        try {
            if (typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
                URL.revokeObjectURL(url);
            }
        } catch (e) {
            // ignore
        }
    }

    function clearPendingImage(pending) {
        if (!pending) {
            return null;
        }
        revokePreviewUrl(pending.previewUrl);
        pending.previewUrl = '';
        pending.blob = null;
        pending.operationId = '';
        return null;
    }

    function isLikelyHeic(file) {
        if (!file) {
            return false;
        }
        var type = String(file.type || '').toLowerCase();
        if (type === 'image/heic' || type === 'image/heif') {
            return true;
        }
        var name = String(file.name || '').toLowerCase();
        return /\.(heic|heif)$/.test(name);
    }

    function loadImageElement(src) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload = function () {
                resolve(img);
            };
            img.onerror = function () {
                reject(new Error('decode_failed'));
            };
            img.src = src;
        });
    }

    function canvasToJpegBlob(canvas, quality) {
        return new Promise(function (resolve, reject) {
            if (!canvas || typeof canvas.toBlob !== 'function') {
                reject(new Error('canvas_unavailable'));
                return;
            }
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('toblob_failed'));
                    return;
                }
                resolve(blob);
            }, 'image/jpeg', quality);
        });
    }

    function disposeCanvas(canvas) {
        if (!canvas) {
            return;
        }
        try {
            canvas.width = 0;
            canvas.height = 0;
        } catch (e) {
            // ignore
        }
    }

    /**
     * Normaliza a JPEG ≤2048 px y ≤1_048_576 bytes. Nunca devuelve el original.
     * Genera upload_operation_id solo tras preparar OK.
     *
     * @param {File|Blob} file
     * @returns {Promise<{ok:true, pending:object}|{ok:false, message:string}>}
     */
    function prepareExpedienteImage(file) {
        if (!file) {
            return Promise.resolve({
                ok: false,
                message: 'Selecciona una imagen válida.'
            });
        }

        var objectUrl = '';
        var canvas = null;

        function fail(message) {
            revokePreviewUrl(objectUrl);
            disposeCanvas(canvas);
            return { ok: false, message: message };
        }

        var decodePromise;
        if (typeof createImageBitmap === 'function') {
            decodePromise = createImageBitmap(file).then(function (bitmap) {
                return { width: bitmap.width, height: bitmap.height, source: bitmap, kind: 'bitmap' };
            });
        } else {
            objectUrl = URL.createObjectURL(file);
            decodePromise = loadImageElement(objectUrl).then(function (img) {
                return { width: img.naturalWidth || img.width, height: img.naturalHeight || img.height, source: img, kind: 'img' };
            });
        }

        return decodePromise
            .catch(function () {
                if (isLikelyHeic(file)) {
                    throw new Error('heic_unsupported');
                }
                throw new Error('decode_failed');
            })
            .then(function (decoded) {
                var srcW = decoded.width;
                var srcH = decoded.height;
                if (!(srcW > 0) || !(srcH > 0)) {
                    throw new Error('invalid_dimensions');
                }

                var scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(srcW, srcH));
                var targetW = Math.max(1, Math.round(srcW * scale));
                var targetH = Math.max(1, Math.round(srcH * scale));

                canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    throw new Error('canvas_unavailable');
                }

                var qualities = [0.85, 0.75, 0.65, 0.55, 0.45];
                var maxPasses = 8;
                var pass = 0;
                var lastBlob = null;

                function tryEncode() {
                    if (pass >= maxPasses) {
                        return Promise.reject(new Error('too_large'));
                    }
                    pass += 1;
                    canvas.width = targetW;
                    canvas.height = targetH;
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, targetW, targetH);
                    ctx.drawImage(decoded.source, 0, 0, targetW, targetH);

                    var qIndex = 0;

                    function tryQuality() {
                        if (qIndex >= qualities.length) {
                            // Reducir dimensiones y reiniciar calidades (terminación acotada).
                            if (targetW <= 320 && targetH <= 320) {
                                return Promise.reject(new Error('too_large'));
                            }
                            targetW = Math.max(1, Math.round(targetW * 0.75));
                            targetH = Math.max(1, Math.round(targetH * 0.75));
                            return tryEncode();
                        }
                        var quality = qualities[qIndex];
                        qIndex += 1;
                        return canvasToJpegBlob(canvas, quality).then(function (blob) {
                            lastBlob = blob;
                            if (blob.size <= MAX_IMAGE_BYTES) {
                                return blob;
                            }
                            return tryQuality();
                        });
                    }

                    return tryQuality();
                }

                return tryEncode().then(function (blob) {
                    if (!blob || blob.type !== 'image/jpeg' || blob.size < 1 || blob.size > MAX_IMAGE_BYTES) {
                        throw new Error('too_large');
                    }

                    if (decoded.kind === 'bitmap' && typeof decoded.source.close === 'function') {
                        try {
                            decoded.source.close();
                        } catch (e) {
                            // ignore
                        }
                    }

                    disposeCanvas(canvas);
                    canvas = null;
                    revokePreviewUrl(objectUrl);
                    objectUrl = '';

                    var previewUrl = URL.createObjectURL(blob);
                    var operationId = generateUploadOperationId();

                    return {
                        ok: true,
                        pending: {
                            operationId: operationId,
                            blob: blob,
                            previewUrl: previewUrl,
                            width: targetW,
                            height: targetH,
                            byteSize: blob.size
                        }
                    };
                });
            })
            .catch(function (err) {
                var code = err && err.message ? String(err.message) : 'decode_failed';
                if (code === 'heic_unsupported') {
                    return fail(HEIC_UNSUPPORTED_MESSAGE);
                }
                if (code === 'too_large') {
                    return fail('La imagen es demasiado grande. Prueba con otra foto más liviana.');
                }
                return fail('No se pudo procesar la imagen. Prueba con JPG o PNG.');
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

        /** @type {{operationId:string, blob:Blob, previewUrl:string, width:number, height:number, byteSize:number}|null} */
        var pendingImage = null;
        /** @type {'idle'|'saving_record'|'uploading_attachment'|'partial_attachment_failed'} */
        var flowState = 'idle';
        var cleanedUp = false;

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

        var adjuntoBlock = document.createElement('div');
        adjuntoBlock.className = 'aa-expediente-adjunto-block';

        var adjuntoLabel = document.createElement('label');
        adjuntoLabel.className = 'aa-expediente-adjunto-label';
        adjuntoLabel.setAttribute('for', 'aa-expediente-registro-adjunto');
        adjuntoLabel.textContent = 'Adjuntar imagen (opcional)';

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.id = 'aa-expediente-registro-adjunto';
        fileInput.className = 'aa-expediente-adjunto-input';
        fileInput.accept = 'image/jpeg,image/png,image/webp,image/heic,image/heif';
        // Sin capture / cámara.

        var previewWrap = document.createElement('div');
        previewWrap.className = 'aa-expediente-adjunto-preview-wrap hidden';

        var previewImg = document.createElement('img');
        previewImg.className = 'aa-expediente-adjunto-preview';
        previewImg.alt = 'Vista previa de la imagen adjunta';

        var previewMeta = document.createElement('p');
        previewMeta.className = 'aa-expediente-adjunto-meta';

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'aa-expediente-adjunto-remove';
        removeBtn.textContent = 'Quitar imagen';

        previewWrap.appendChild(previewImg);
        previewWrap.appendChild(previewMeta);
        previewWrap.appendChild(removeBtn);

        adjuntoBlock.appendChild(adjuntoLabel);
        adjuntoBlock.appendChild(fileInput);
        adjuntoBlock.appendChild(previewWrap);

        var errorEl = document.createElement('p');
        errorEl.className = 'aa-expediente-registro-form-error text-sm text-red-600 hidden';
        errorEl.setAttribute('role', 'alert');

        var retryBtn = document.createElement('button');
        retryBtn.type = 'button';
        retryBtn.className = 'aa-expediente-btn-reintentar-imagen hidden';
        retryBtn.textContent = 'Reintentar imagen';

        bodyWrap.appendChild(titleLabel);
        bodyWrap.appendChild(titleInput);
        bodyWrap.appendChild(bodyLabel);
        bodyWrap.appendChild(bodyInput);
        bodyWrap.appendChild(adjuntoBlock);
        bodyWrap.appendChild(errorEl);
        bodyWrap.appendChild(retryBtn);

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

        function cleanupPendingOnClose() {
            if (cleanedUp) {
                return;
            }
            cleanedUp = true;
            pendingImage = clearPendingImage(pendingImage);
            fileInput.value = '';
            previewWrap.classList.add('hidden');
            previewImg.removeAttribute('src');
        }

        disarmModalCloseWatcher();
        modalCloseAbort = watchModalClose(function () {
            modalCloseAbort = null;
            cleanupPendingOnClose();
            focusElement(focusReturnEl);
        });

        window.setTimeout(function () {
            titleInput.focus();
        }, 50);

        function showFormError(message) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }

        function hideRetry() {
            retryBtn.classList.add('hidden');
            retryBtn.disabled = false;
            retryBtn.textContent = 'Reintentar imagen';
        }

        function showPartialAttachFailure() {
            flowState = 'partial_attachment_failed';
            showFormError(PARTIAL_ATTACH_MESSAGE);
            retryBtn.classList.remove('hidden');
            reenableSave();
        }

        function reenableSave() {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Guardar';
        }

        function setModalTitle(text) {
            var titleNode = typeof document !== 'undefined'
                ? document.querySelector('#aa-modal-root .aa-modal-title')
                : null;
            if (titleNode) {
                titleNode.textContent = text;
            }
        }

        function renderPendingPreview() {
            if (!pendingImage) {
                previewWrap.classList.add('hidden');
                previewImg.removeAttribute('src');
                previewMeta.textContent = '';
                return;
            }
            previewImg.src = pendingImage.previewUrl;
            previewMeta.textContent =
                Math.round(pendingImage.byteSize / 1024) + ' KB · JPEG';
            previewWrap.classList.remove('hidden');
        }

        function removePendingImage() {
            pendingImage = clearPendingImage(pendingImage);
            fileInput.value = '';
            renderPendingPreview();
            if (flowState === 'partial_attachment_failed') {
                flowState = 'idle';
                errorEl.classList.add('hidden');
                hideRetry();
            }
        }

        removeBtn.addEventListener('click', function (event) {
            event.preventDefault();
            if (flowState === 'saving_record' || flowState === 'uploading_attachment') {
                return;
            }
            removePendingImage();
        });

        fileInput.addEventListener('change', function () {
            if (flowState === 'saving_record' || flowState === 'uploading_attachment') {
                fileInput.value = '';
                return;
            }

            errorEl.classList.add('hidden');
            hideRetry();
            if (flowState === 'partial_attachment_failed') {
                flowState = 'idle';
            }

            var files = fileInput.files;
            var file = files && files.length ? files[0] : null;
            // Una sola imagen; ignorar selección múltiple implícita.
            pendingImage = clearPendingImage(pendingImage);
            renderPendingPreview();

            if (!file) {
                return;
            }

            saveBtn.disabled = true;
            fileInput.disabled = true;

            prepareExpedienteImage(file)
                .then(function (result) {
                    fileInput.disabled = false;
                    saveBtn.disabled = false;
                    if (!result || !result.ok) {
                        fileInput.value = '';
                        showFormError(
                            (result && result.message) || 'No se pudo procesar la imagen.'
                        );
                        return;
                    }
                    pendingImage = result.pending;
                    renderPendingPreview();
                })
                .catch(function (err) {
                    fileInput.disabled = false;
                    saveBtn.disabled = false;
                    fileInput.value = '';
                    console.error('[ExpedienteRegistros] prepare image failed:', err);
                    showFormError('No se pudo procesar la imagen.');
                });
        });

        function persistRecordInList(saved) {
            if (mode === 'edit') {
                replaceRecord(saved);
            } else {
                prependRecord(saved);
            }
        }

        function promoteCreateToEdit(saved) {
            mode = 'edit';
            recordId = parseInt(saved.id, 10);
            record = saved;
            setModalTitle('Editar registro');
        }

        function closeAfterFullSuccess(saved) {
            disarmModalCloseWatcher();
            cleanupPendingOnClose();
            if (typeof window.AAAdmin.closeModal === 'function') {
                window.AAAdmin.closeModal();
            }
            if (mode === 'edit') {
                focusEditButtonById(saved && saved.id);
            }
        }

        function attachPendingImage(savedRecord) {
            if (!pendingImage || !pendingImage.blob || !pendingImage.operationId) {
                return Promise.resolve({ ok: true, skipped: true });
            }

            var data = getConfig();
            var action = (data.actions && data.actions.attachRegistro) || 'aa_attach_expediente_registro';
            var operationId = pendingImage.operationId;

            flowState = 'uploading_attachment';
            saveBtn.disabled = true;
            saveBtn.textContent = 'Subiendo imagen...';
            retryBtn.disabled = true;

            return postAttach(
                action,
                {
                    client_id: String(state.clientId),
                    record_id: String(recordId || (savedRecord && savedRecord.id) || ''),
                    upload_operation_id: operationId
                },
                pendingImage.blob,
                'adjunto.jpg'
            ).then(function (payload) {
                var result = payload.result;
                if (result && result.success) {
                    return { ok: true };
                }
                var message = PARTIAL_ATTACH_MESSAGE;
                if (result && result.data && result.data.message) {
                    // Mensaje genérico de parcial; detalle de attach no sustituye el copy aprobado.
                    message = PARTIAL_ATTACH_MESSAGE;
                }
                return { ok: false, message: message };
            });
        }

        function runAttachRetry() {
            if (flowState === 'saving_record' || flowState === 'uploading_attachment') {
                return;
            }
            if (!pendingImage || !(recordId > 0)) {
                showFormError(PARTIAL_ATTACH_MESSAGE);
                return;
            }

            errorEl.classList.add('hidden');
            retryBtn.disabled = true;
            retryBtn.textContent = 'Reintentando...';

            attachPendingImage({ id: recordId })
                .then(function (attachResult) {
                    if (attachResult && attachResult.ok) {
                        flowState = 'idle';
                        hideRetry();
                        closeAfterFullSuccess({ id: recordId });
                        return;
                    }
                    showPartialAttachFailure();
                })
                .catch(function (err) {
                    console.error('[ExpedienteRegistros] attach retry failed:', err);
                    showPartialAttachFailure();
                });
        }

        retryBtn.addEventListener('click', function (event) {
            event.preventDefault();
            runAttachRetry();
        });

        saveBtn.addEventListener('click', function (event) {
            event.preventDefault();
            if (flowState === 'saving_record' || flowState === 'uploading_attachment') {
                return;
            }

            // Tras fallo parcial, Guardar reintenta solo la imagen (nunca create de nuevo).
            if (flowState === 'partial_attachment_failed') {
                runAttachRetry();
                return;
            }

            errorEl.classList.add('hidden');
            hideRetry();

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

            flowState = 'saving_record';
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
            fileInput.disabled = true;

            postForm(action, fields)
                .then(function (payload) {
                    var result = payload.result;
                    if (!(result && result.success && result.data && result.data.record)) {
                        var message = mode === 'edit'
                            ? 'No se pudo actualizar el registro.'
                            : 'No se pudo guardar el registro.';
                        if (result && result.data && result.data.message) {
                            message = String(result.data.message);
                        }
                        flowState = 'idle';
                        fileInput.disabled = false;
                        showFormError(message);
                        reenableSave();
                        return null;
                    }

                    var saved = result.data.record;
                    // Actualizar lista de inmediato (también ante fallo posterior de imagen).
                    persistRecordInList(saved);

                    if (mode === 'create') {
                        promoteCreateToEdit(saved);
                    } else {
                        recordId = parseInt(saved.id, 10);
                        record = saved;
                    }

                    if (!pendingImage) {
                        flowState = 'idle';
                        closeAfterFullSuccess(saved);
                        return null;
                    }

                    return attachPendingImage(saved).then(function (attachResult) {
                        fileInput.disabled = false;
                        if (attachResult && attachResult.ok) {
                            flowState = 'idle';
                            closeAfterFullSuccess(saved);
                            return;
                        }
                        showPartialAttachFailure();
                    });
                })
                .catch(function (err) {
                    console.error('[ExpedienteRegistros] save failed:', err);
                    flowState = 'idle';
                    fileInput.disabled = false;
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
            prepareExpedienteImage: prepareExpedienteImage,
            generateUploadOperationId: generateUploadOperationId,
            clearPendingImage: clearPendingImage,
            postAttach: postAttach,
            MAX_IMAGE_BYTES: MAX_IMAGE_BYTES,
            MAX_IMAGE_EDGE: MAX_IMAGE_EDGE,
            HEIC_UNSUPPORTED_MESSAGE: HEIC_UNSUPPORTED_MESSAGE,
            PARTIAL_ATTACH_MESSAGE: PARTIAL_ATTACH_MESSAGE,
            getState: function () { return state; },
            setState: function (partial) {
                Object.keys(partial || {}).forEach(function (key) {
                    state[key] = partial[key];
                });
            }
        }
    };
})();
