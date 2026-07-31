/**
 * Expediente registros — chronology list + create/edit modal (MC2/MC3/MC4b/MC4c).
 *
 * Loaded only on view=expediente. Create and edit share one modal form.
 * Adjunto opcional: texto primero (create/update), luego aa_attach_expediente_registro.
 * MC4c: miniatura privada lazy por tarjeta vía sign-read; caché solo en memoria.
 */

(function () {
    'use strict';

    window.AAAdmin = window.AAAdmin || {};

    var MAX_IMAGE_EDGE = 2048;
    var MAX_IMAGE_BYTES = 1048576;
    var HEIC_UNSUPPORTED_MESSAGE =
        'Este formato no se puede procesar aquí. Guarda o exporta la foto como JPG e inténtalo de nuevo.';
    var PARTIAL_ATTACH_MESSAGE = 'Registro guardado. No se pudo subir la imagen.';
    var THUMB_ERROR_MESSAGE = 'No se pudo cargar la imagen.';
    // Margen antes de la expiración real (600s backend) para no usar URLs al límite.
    var THUMB_TTL_SAFETY_SECONDS = 60;

    var state = {
        clientId: 0,
        recordsRoot: null,
        records: [],
        loading: false
    };

    /**
     * Controlador de miniaturas (MC4c). Todo vive solo en memoria.
     * Claves de cache/requests: "<client_id>:<record_id>:<adjunto.id>".
     */
    var thumbs = {
        viewEpoch: 0,
        observer: null,
        thumbnailCache: {},
        thumbnailRequests: {},
        resignedIdentities: {}
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

    // ── Miniaturas MC4c ─────────────────────────────────────────────

    function hasValidAdjunto(record) {
        return !!(record && record.adjunto && parseInt(record.adjunto.id, 10) > 0);
    }

    /**
     * Normaliza una colección de adjuntos (MC5a): filtra DTOs inválidos,
     * deduplica por id y ordena id DESC. `adjuntos[]` es la fuente de verdad.
     */
    function normalizeAdjuntosList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        var seen = {};
        var out = [];
        list.forEach(function (dto) {
            if (!isValidAdjuntoDto(dto)) {
                return;
            }
            var id = parseInt(dto.id, 10);
            if (seen[id]) {
                return;
            }
            seen[id] = true;
            out.push(dto);
        });
        out.sort(function (a, b) {
            return parseInt(b.id, 10) - parseInt(a.id, 10);
        });
        return out;
    }

    /**
     * Colección vigente de un registro en estado. Puente MC4c: un registro
     * sin clave `adjuntos` pero con alias `adjunto` válido se trata como
     * colección de un elemento.
     */
    function getRecordAdjuntos(record) {
        if (!record) {
            return [];
        }
        if (Array.isArray(record.adjuntos)) {
            return record.adjuntos;
        }
        if (record.adjunto && isValidAdjuntoDto(record.adjunto)) {
            return [record.adjunto];
        }
        return [];
    }

    /**
     * Fija en el registro la colección normalizada y deriva el alias
     * `adjunto` = adjuntos[0] | null. Muta y devuelve el mismo objeto.
     */
    function setRecordAdjuntos(record, list) {
        record.adjuntos = normalizeAdjuntosList(list);
        record.adjunto = record.adjuntos.length ? record.adjuntos[0] : null;
        return record;
    }

    /**
     * Normaliza un registro recibido del servidor. Prioridad: clave
     * `adjuntos` (autoritativa, incluso []) > clave `adjunto` (puente MC4c,
     * singleton) > colección vacía.
     */
    function normalizeIncomingRecord(record) {
        if (!record) {
            return record;
        }
        if (Object.prototype.hasOwnProperty.call(record, 'adjuntos')) {
            return setRecordAdjuntos(record, record.adjuntos);
        }
        if (Object.prototype.hasOwnProperty.call(record, 'adjunto')) {
            return setRecordAdjuntos(record, record.adjunto ? [record.adjunto] : []);
        }
        return setRecordAdjuntos(record, []);
    }

    /**
     * DTO público de adjunto: { id, width, height, byte_size, created_at }.
     */
    function isValidAdjuntoDto(adjunto) {
        return !!(
            adjunto
            && parseInt(adjunto.id, 10) > 0
            && parseInt(adjunto.width, 10) > 0
            && parseInt(adjunto.height, 10) > 0
            && parseInt(adjunto.byte_size, 10) > 0
            && typeof adjunto.created_at === 'string'
        );
    }

    function thumbKey(recordId, adjuntoId) {
        return String(state.clientId) + ':' + String(recordId) + ':' + String(adjuntoId);
    }

    function abortThumbRequest(key) {
        var pending = thumbs.thumbnailRequests[key];
        if (!pending) {
            return;
        }
        delete thumbs.thumbnailRequests[key];
        if (pending.controller && typeof pending.controller.abort === 'function') {
            try {
                pending.controller.abort();
            } catch (e) {
                // ignore
            }
        }
    }

    function abortAllThumbRequests() {
        Object.keys(thumbs.thumbnailRequests).forEach(abortThumbRequest);
    }

    /**
     * Poda cache/requests/resigned contra las identidades vigentes de state.records.
     * MC5a: válidas todas las identidades de `adjuntos[]`, aunque el render
     * solo muestre adjuntos[0]. Conserva solo URLs cargadas y vigentes.
     */
    function pruneThumbState() {
        var valid = {};
        state.records.forEach(function (record) {
            getRecordAdjuntos(record).forEach(function (dto) {
                if (isValidAdjuntoDto(dto)) {
                    valid[thumbKey(record.id, dto.id)] = true;
                }
            });
        });

        Object.keys(thumbs.thumbnailCache).forEach(function (key) {
            var entry = thumbs.thumbnailCache[key];
            var fresh = entry && typeof entry.deadlineMs === 'number' && entry.deadlineMs > Date.now();
            if (!valid[key] || !fresh) {
                delete thumbs.thumbnailCache[key];
            }
        });

        Object.keys(thumbs.thumbnailRequests).forEach(function (key) {
            if (!valid[key]) {
                abortThumbRequest(key);
            }
        });

        Object.keys(thumbs.resignedIdentities).forEach(function (key) {
            if (!valid[key]) {
                delete thumbs.resignedIdentities[key];
            }
        });
    }

    /**
     * Invalida toda entrada (cache/requests/resigned) de un registro del cliente actual.
     */
    function invalidateThumbForRecord(recordId) {
        var prefix = String(state.clientId) + ':' + String(recordId) + ':';
        Object.keys(thumbs.thumbnailCache).forEach(function (key) {
            if (key.indexOf(prefix) === 0) {
                delete thumbs.thumbnailCache[key];
            }
        });
        Object.keys(thumbs.thumbnailRequests).forEach(function (key) {
            if (key.indexOf(prefix) === 0) {
                abortThumbRequest(key);
            }
        });
        Object.keys(thumbs.resignedIdentities).forEach(function (key) {
            if (key.indexOf(prefix) === 0) {
                delete thumbs.resignedIdentities[key];
            }
        });
    }

    function disconnectThumbObserver() {
        if (thumbs.observer && typeof thumbs.observer.disconnect === 'function') {
            try {
                thumbs.observer.disconnect();
            } catch (e) {
                // ignore
            }
        }
        thumbs.observer = null;
    }

    /**
     * Antes de cada re-render del mismo cliente: observer fuera, firmas en
     * vuelo abortadas, cache podado a identidades vigentes.
     */
    function prepareThumbsForRender() {
        disconnectThumbObserver();
        abortAllThumbRequests();
        pruneThumbState();
    }

    /**
     * POST sign-read con señal de aborto. MC5a: siempre lectura dirigida con
     * attachment_id; el fallback sin attachment_id vive solo en PHP.
     */
    function postSignRead(recordId, attachmentId, signal) {
        var data = getConfig();
        var action = (data.actions && data.actions.signAdjuntoRead) || 'aa_sign_expediente_adjunto_read';

        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', getNonce());
        formData.append('client_id', String(state.clientId));
        formData.append('record_id', String(recordId));
        formData.append('attachment_id', String(attachmentId));

        var options = {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        };
        if (signal) {
            options.signal = signal;
        }

        return fetch(getAjaxUrl(), options).then(function (response) {
            return response.json().then(function (result) {
                return { httpStatus: response.status, result: result };
            });
        });
    }

    function isNodeConnected(node) {
        if (!node) {
            return false;
        }
        if (typeof node.isConnected === 'boolean') {
            return node.isConnected;
        }
        if (typeof document !== 'undefined' && document.contains) {
            return document.contains(node);
        }
        return true;
    }

    function showThumbErrorState(box) {
        if (!box) {
            return;
        }
        box.classList.add('aa-expediente-adjunto-thumb-error');
        box.classList.remove('aa-expediente-adjunto-thumb-loaded');
        var img = box.querySelector('img');
        if (img && img.parentNode === box) {
            box.removeChild(img);
        }
        box.setAttribute('title', THUMB_ERROR_MESSAGE);
    }

    function handleThumbImgError(box, key, recordId) {
        delete thumbs.thumbnailCache[key];
        var img = box ? box.querySelector('img') : null;
        if (img) {
            img.onerror = null;
            img.onload = null;
            img.removeAttribute('src');
            if (img.parentNode === box) {
                box.removeChild(img);
            }
        }

        // Como máximo una refirma automática por identidad durante la vista.
        if (!thumbs.resignedIdentities[key]) {
            thumbs.resignedIdentities[key] = true;
            requestThumbFor(box, recordId);
            return;
        }

        showThumbErrorState(box);
    }

    function applyThumbUrl(box, url, key, recordId) {
        if (!box) {
            return;
        }
        box.classList.remove('aa-expediente-adjunto-thumb-error');
        box.removeAttribute('title');

        var img = box.querySelector('img');
        if (!img) {
            img = document.createElement('img');
            img.className = 'aa-expediente-adjunto-thumb-img';
            img.alt = 'Imagen adjunta';
            img.setAttribute('referrerpolicy', 'no-referrer');
            img.setAttribute('decoding', 'async');
            img.setAttribute('draggable', 'false');
            box.appendChild(img);
        }
        img.onload = function () {
            box.classList.add('aa-expediente-adjunto-thumb-loaded');
        };
        img.onerror = function () {
            handleThumbImgError(box, key, recordId);
        };
        img.src = url;
    }

    /**
     * Solicita (o reutiliza) la firma para la miniatura de una tarjeta.
     * Identidad capturada: viewEpoch + clientId + recordId + adjuntoId.
     */
    function requestThumbFor(box, recordId) {
        if (!box) {
            return;
        }
        var adjuntoId = parseInt(box.getAttribute('data-adjunto-id') || '0', 10);
        var rid = parseInt(recordId, 10);
        if (!(adjuntoId > 0) || !(rid > 0)) {
            return;
        }

        var key = thumbKey(rid, adjuntoId);

        var cached = thumbs.thumbnailCache[key];
        if (cached && typeof cached.deadlineMs === 'number' && cached.deadlineMs > Date.now()) {
            applyThumbUrl(box, cached.url, key, rid);
            return;
        }
        if (cached) {
            delete thumbs.thumbnailCache[key];
        }

        if (thumbs.thumbnailRequests[key]) {
            return;
        }

        var epoch = thumbs.viewEpoch;
        var clientId = state.clientId;
        var controller = null;
        var signal = null;
        if (typeof AbortController !== 'undefined') {
            controller = new AbortController();
            signal = controller.signal;
        }

        thumbs.thumbnailRequests[key] = { controller: controller };

        postSignRead(rid, adjuntoId, signal)
            .then(function (payload) {
                delete thumbs.thumbnailRequests[key];

                // Vista/cliente inactivos: descartar sin tocar DOM ni caché.
                if (epoch !== thumbs.viewEpoch || clientId !== state.clientId) {
                    return;
                }

                var record = findRecordById(rid);
                if (!record) {
                    return;
                }

                var result = payload.result;
                var data = result && result.success ? result.data : null;
                if (!data || typeof data.url !== 'string' || data.url === '' || !isValidAdjuntoDto(data.adjunto)) {
                    if (isNodeConnected(box)) {
                        showThumbErrorState(box);
                    }
                    return;
                }

                var returnedAdjuntoId = parseInt(data.adjunto.id, 10);

                if (returnedAdjuntoId !== adjuntoId) {
                    // Identidad discordante: nunca asociar la URL al nodo/clave
                    // anterior ni cachearla bajo ninguna clave. Reconciliar con
                    // el DTO autoritativo; el nodo nuevo pedirá otra firma.
                    invalidateThumbForRecord(rid);
                    applyAdjuntoToRecord(rid, data.adjunto);
                    return;
                }

                if (!hasValidAdjunto(record) || parseInt(record.adjunto.id, 10) !== adjuntoId) {
                    // El estado cambió mientras la firma volaba: descartar.
                    return;
                }

                var expiresIn = parseInt(data.expires_in, 10);
                var windowSeconds = expiresIn > THUMB_TTL_SAFETY_SECONDS
                    ? expiresIn - THUMB_TTL_SAFETY_SECONDS
                    : expiresIn;
                if (!(windowSeconds > 0)) {
                    windowSeconds = 1;
                }

                thumbs.thumbnailCache[key] = {
                    url: data.url,
                    deadlineMs: Date.now() + windowSeconds * 1000
                };

                if (isNodeConnected(box) && parseInt(box.getAttribute('data-adjunto-id') || '0', 10) === adjuntoId) {
                    applyThumbUrl(box, data.url, key, rid);
                }
            })
            .catch(function (err) {
                delete thumbs.thumbnailRequests[key];
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (epoch !== thumbs.viewEpoch || clientId !== state.clientId) {
                    return;
                }
                if (isNodeConnected(box)) {
                    showThumbErrorState(box);
                }
            });
    }

    /**
     * Observa los thumb boxes recién renderizados. Cache fresco se aplica de
     * inmediato sin firma nueva; el resto espera intersección (o toggle como
     * fallback sin IntersectionObserver).
     */
    function setupThumbObserver(boxes) {
        if (!boxes.length) {
            return;
        }

        var pendingBoxes = [];

        boxes.forEach(function (entry) {
            var key = thumbKey(entry.recordId, entry.adjuntoId);
            var cached = thumbs.thumbnailCache[key];
            if (cached && typeof cached.deadlineMs === 'number' && cached.deadlineMs > Date.now()) {
                applyThumbUrl(entry.box, cached.url, key, entry.recordId);
                return;
            }
            pendingBoxes.push(entry);
        });

        if (!pendingBoxes.length) {
            return;
        }

        if (typeof IntersectionObserver === 'undefined') {
            // Fallback: pedir firma al abrir la tarjeta.
            pendingBoxes.forEach(function (entry) {
                if (entry.details && typeof entry.details.addEventListener === 'function') {
                    entry.details.addEventListener('toggle', function () {
                        if (entry.details.open) {
                            requestThumbFor(entry.box, entry.recordId);
                        }
                    });
                }
            });
            return;
        }

        var byBox = [];
        thumbs.observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (ioEntry) {
                if (!ioEntry.isIntersecting) {
                    return;
                }
                for (var i = 0; i < byBox.length; i++) {
                    if (byBox[i].box === ioEntry.target) {
                        if (thumbs.observer) {
                            thumbs.observer.unobserve(ioEntry.target);
                        }
                        requestThumbFor(byBox[i].box, byBox[i].recordId);
                        return;
                    }
                }
            });
        }, { rootMargin: '200px 0px' });

        pendingBoxes.forEach(function (entry) {
            byBox.push(entry);
            thumbs.observer.observe(entry.box);
        });
    }

    /**
     * Integra un DTO en la colección del registro (MC5a): lo agrega o
     * sustituye por id dentro de `adjuntos[]`, deduplica, ordena id DESC y
     * deriva el alias `adjunto`. Invalida la caché de ese cliente+registro y
     * re-renderiza expandido.
     */
    function applyAdjuntoToRecord(recordId, adjunto) {
        var id = parseInt(recordId, 10);
        if (!(id > 0) || !isValidAdjuntoDto(adjunto)) {
            return false;
        }

        var found = false;
        state.records = state.records.map(function (existing) {
            if (parseInt(existing.id, 10) !== id) {
                return existing;
            }
            found = true;
            var merged = {};
            Object.keys(existing).forEach(function (k) {
                merged[k] = existing[k];
            });
            var adjuntoId = parseInt(adjunto.id, 10);
            var next = getRecordAdjuntos(existing).filter(function (dto) {
                return parseInt(dto.id, 10) !== adjuntoId;
            });
            next.push(adjunto);
            return setRecordAdjuntos(merged, next);
        });

        if (!found) {
            return false;
        }

        invalidateThumbForRecord(id);
        renderRecordsList({ expandId: id });
        return true;
    }

    /**
     * Libera todos los recursos de la vista: época, observer, firmas en vuelo,
     * caché, referencias e identidad del cliente.
     */
    function destroy() {
        thumbs.viewEpoch += 1;
        disconnectThumbObserver();
        abortAllThumbRequests();
        thumbs.thumbnailCache = {};
        thumbs.thumbnailRequests = {};
        thumbs.resignedIdentities = {};

        state.records = [];
        state.recordsRoot = null;
        state.clientId = 0;
        state.loading = false;
    }

    // ── Fin miniaturas MC4c ─────────────────────────────────────────

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

        var thumbBox = null;
        if (hasValidAdjunto(record)) {
            thumbBox = document.createElement('div');
            thumbBox.className = 'aa-expediente-adjunto-thumb';
            thumbBox.setAttribute('data-thumb-record-id', String(record.id));
            thumbBox.setAttribute('data-adjunto-id', String(record.adjunto.id));
            summary.appendChild(thumbBox);
        }

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

        if (thumbBox) {
            details._aaThumbEntry = {
                box: thumbBox,
                details: details,
                recordId: parseInt(record.id, 10),
                adjuntoId: parseInt(record.adjunto.id, 10)
            };
        }

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

        // MC4c: observer fuera, firmas en vuelo abortadas, caché podado.
        prepareThumbsForRender();

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
        var thumbEntries = [];
        state.records.forEach(function (record) {
            var shouldOpen = expandId > 0 && parseInt(record.id, 10) === expandId;
            var details = createRecordDetails(record, { open: shouldOpen });
            if (details._aaThumbEntry) {
                thumbEntries.push(details._aaThumbEntry);
            }
            list.appendChild(details);
        });
        state.recordsRoot.appendChild(list);

        setupThumbObserver(thumbEntries);
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
        // Create recién guardado: normalizar colección (sin claves → []).
        if (record) {
            normalizeIncomingRecord(record);
        }
        state.records = sortRecordsDesc([record].concat(state.records));
        renderRecordsList({ expandId: record && record.id });
    }

    /**
     * Combina el registro existente con los campos recibidos, sin sustituirlo
     * íntegramente. Prioridad de la colección (MC5a): clave `adjuntos`
     * presente (incluso []) = autoritativa; sin ella, clave `adjunto`
     * (incluso null) = autoritativa como singleton (puente MC4c); sin ambas
     * = conservar la colección existente. El alias `adjunto` siempre se
     * deriva de adjuntos[0]. Mantiene posición cronológica y tarjeta abierta.
     *
     * @param {object} record
     */
    function replaceRecord(record) {
        if (!record || !(parseInt(record.id, 10) > 0)) {
            return;
        }
        var id = parseInt(record.id, 10);
        var replaced = false;
        state.records = state.records.map(function (existing) {
            if (parseInt(existing.id, 10) !== id) {
                return existing;
            }
            replaced = true;

            var merged = {};
            Object.keys(existing).forEach(function (key) {
                merged[key] = existing[key];
            });
            Object.keys(record).forEach(function (key) {
                merged[key] = record[key];
            });

            if (Object.prototype.hasOwnProperty.call(record, 'adjuntos')) {
                setRecordAdjuntos(merged, record.adjuntos);
            } else if (Object.prototype.hasOwnProperty.call(record, 'adjunto')) {
                setRecordAdjuntos(merged, record.adjunto ? [record.adjunto] : []);
            } else {
                setRecordAdjuntos(merged, getRecordAdjuntos(existing));
            }
            return merged;
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

        // MC5a: una respuesta posterior a destroy(), cambio de cliente o
        // re-init no debe sobrescribir el estado vigente.
        var epoch = thumbs.viewEpoch;
        var clientId = state.clientId;

        function isStale() {
            return epoch !== thumbs.viewEpoch || clientId !== state.clientId;
        }

        state.loading = true;
        renderStatusMessage('Cargando registros...', 'text-sm text-gray-500');

        return postForm(action, { client_id: String(clientId) })
            .then(function (payload) {
                if (isStale()) {
                    return;
                }
                state.loading = false;
                var result = payload.result;
                if (result && result.success && result.data && Array.isArray(result.data.records)) {
                    state.records = sortRecordsDesc(result.data.records.map(normalizeIncomingRecord));
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
                if (isStale()) {
                    return;
                }
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
            var requestedRecordId = parseInt(recordId || (savedRecord && savedRecord.id) || 0, 10);

            flowState = 'uploading_attachment';
            saveBtn.disabled = true;
            saveBtn.textContent = 'Subiendo imagen...';
            retryBtn.disabled = true;

            return postAttach(
                action,
                {
                    client_id: String(state.clientId),
                    record_id: String(requestedRecordId || ''),
                    upload_operation_id: operationId
                },
                pendingImage.blob,
                'adjunto.jpg'
            ).then(function (payload) {
                var result = payload.result;
                if (result && result.success) {
                    // MC4c: validar identidad y DTO público antes de aplicarlo.
                    var responseData = result.data || {};
                    var returnedRecordId = parseInt(responseData.record_id, 10);
                    if (returnedRecordId === requestedRecordId && isValidAdjuntoDto(responseData.adjunto)) {
                        return { ok: true, recordId: returnedRecordId, adjunto: responseData.adjunto };
                    }
                    // Respuesta malformada: tratar como parcial (reintento idempotente).
                    return { ok: false, message: PARTIAL_ATTACH_MESSAGE };
                }
                return { ok: false, message: PARTIAL_ATTACH_MESSAGE };
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
                        if (!attachResult.skipped) {
                            applyAdjuntoToRecord(attachResult.recordId, attachResult.adjunto);
                        }
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
                            if (!attachResult.skipped) {
                                applyAdjuntoToRecord(attachResult.recordId, attachResult.adjunto);
                            }
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
        // Libera recursos de cualquier montaje previo antes de re-montar.
        destroy();

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
        destroy: destroy,
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
            applyAdjuntoToRecord: applyAdjuntoToRecord,
            normalizeAdjuntosList: normalizeAdjuntosList,
            normalizeIncomingRecord: normalizeIncomingRecord,
            getRecordAdjuntos: getRecordAdjuntos,
            loadRecords: loadRecords,
            requestThumbFor: requestThumbFor,
            handleThumbImgError: handleThumbImgError,
            pruneThumbState: pruneThumbState,
            invalidateThumbForRecord: invalidateThumbForRecord,
            thumbKey: thumbKey,
            isValidAdjuntoDto: isValidAdjuntoDto,
            destroy: destroy,
            getThumbs: function () { return thumbs; },
            MAX_IMAGE_BYTES: MAX_IMAGE_BYTES,
            MAX_IMAGE_EDGE: MAX_IMAGE_EDGE,
            HEIC_UNSUPPORTED_MESSAGE: HEIC_UNSUPPORTED_MESSAGE,
            PARTIAL_ATTACH_MESSAGE: PARTIAL_ATTACH_MESSAGE,
            THUMB_ERROR_MESSAGE: THUMB_ERROR_MESSAGE,
            getState: function () { return state; },
            setState: function (partial) {
                Object.keys(partial || {}).forEach(function (key) {
                    state[key] = partial[key];
                });
            }
        }
    };
})();
