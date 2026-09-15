/**
 * Módulo images del formulario de registro del shell (Paso 2).
 * Picker + prepare JPEG + attach post-save. No escribe en WriteBag.
 */
(function (global) {
    'use strict';

    var MAX_IMAGE_EDGE = 2048;
    var MAX_IMAGE_BYTES = 1048576;

    var wrap = null;
    var fileInput = null;
    var trigger = null;
    var previewWrap = null;
    var previewImg = null;
    var previewMeta = null;
    var removeBtn = null;
    var retryBtn = null;
    var errorEl = null;
    var statusEl = null;
    var active = false;
    var pending = null;
    var listenersBound = false;
    var attachCtx = null;

    var RECOVERABLE_CODES = {
        transfer_failed: true,
        upload_error: true,
        upload_missing: true,
        upload_unreadable: true,
        authorize_invalid: true,
        authorize_failed: true,
        finalize_mismatch: true,
        resource_busy: true,
        storage_usage_unavailable: true,
        variant_generation_failed: true
    };

    function ensureNodes() {
        if (!wrap) {
            wrap = document.getElementById('aa-shell-record-images-field');
        }
        if (!fileInput) {
            fileInput = document.getElementById('aa-shell-record-image-input');
        }
        if (!trigger) {
            trigger = document.getElementById('aa-shell-record-image-trigger');
        }
        if (!previewWrap) {
            previewWrap = document.getElementById('aa-shell-record-image-preview-wrap');
        }
        if (!previewImg) {
            previewImg = document.getElementById('aa-shell-record-image-preview');
        }
        if (!previewMeta) {
            previewMeta = document.getElementById('aa-shell-record-image-preview-meta');
        }
        if (!removeBtn) {
            removeBtn = document.getElementById('aa-shell-record-image-remove');
        }
        if (!retryBtn) {
            retryBtn = document.getElementById('aa-shell-record-image-retry');
        }
        if (!errorEl) {
            errorEl = document.getElementById('aa-shell-record-images-error');
        }
        if (!statusEl) {
            statusEl = document.getElementById('aa-shell-record-images-status');
        }
    }

    function generateUploadOperationId() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
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

    function clearPending() {
        if (pending) {
            revokePreviewUrl(pending.previewUrl);
        }
        pending = null;
        ensureNodes();
        if (previewWrap) {
            previewWrap.classList.add('hidden');
            previewWrap.setAttribute('hidden', 'hidden');
        }
        if (previewImg) {
            previewImg.removeAttribute('src');
        }
        if (previewMeta) {
            previewMeta.textContent = '';
        }
        if (fileInput) {
            fileInput.value = '';
        }
        hideRetry();
    }

    function setError(message) {
        ensureNodes();
        if (!errorEl) {
            return;
        }
        if (!message) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
            return;
        }
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }

    function setLocalStatus(message) {
        ensureNodes();
        if (!statusEl) {
            return;
        }
        if (!message) {
            statusEl.textContent = '';
            statusEl.classList.add('hidden');
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.remove('hidden');
    }

    function hideRetry() {
        ensureNodes();
        if (!retryBtn) {
            return;
        }
        retryBtn.classList.add('hidden');
        retryBtn.setAttribute('hidden', 'hidden');
        retryBtn.disabled = true;
    }

    function showRetry() {
        ensureNodes();
        if (!retryBtn) {
            return;
        }
        retryBtn.classList.remove('hidden');
        retryBtn.removeAttribute('hidden');
        retryBtn.disabled = false;
    }

    function hideField() {
        ensureNodes();
        if (!wrap) {
            return;
        }
        wrap.classList.add('hidden');
        wrap.setAttribute('hidden', 'hidden');
    }

    function showField() {
        ensureNodes();
        if (!wrap) {
            return;
        }
        wrap.classList.remove('hidden');
        wrap.removeAttribute('hidden');
    }

    function renderPendingPreview() {
        ensureNodes();
        if (!pending || !previewWrap || !previewImg) {
            return;
        }
        previewImg.setAttribute('src', pending.previewUrl || '');
        if (previewMeta) {
            var kb = pending.blob && typeof pending.blob.size === 'number'
                ? Math.max(1, Math.round(pending.blob.size / 1024))
                : 0;
            previewMeta.textContent = kb > 0 ? (kb + ' KB (JPEG)') : 'JPEG listo';
        }
        previewWrap.classList.remove('hidden');
        previewWrap.removeAttribute('hidden');
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

    /**
     * Normaliza a JPEG ≤2048 px y ≤1_048_576 bytes (alineado al validator servidor).
     */
    function prepareCanonicalImage(file) {
        if (!file) {
            return Promise.resolve({ ok: false, message: 'Selecciona una imagen válida.' });
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
                return {
                    width: bitmap.width,
                    height: bitmap.height,
                    source: bitmap,
                    kind: 'bitmap'
                };
            });
        } else {
            objectUrl = URL.createObjectURL(file);
            decodePromise = loadImageElement(objectUrl).then(function (img) {
                return {
                    width: img.naturalWidth || img.width,
                    height: img.naturalHeight || img.height,
                    source: img,
                    kind: 'img'
                };
            });
        }

        return decodePromise
            .catch(function () {
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
                            if (blob.size <= MAX_IMAGE_BYTES) {
                                return blob;
                            }
                            return tryQuality();
                        });
                    }

                    return tryQuality();
                }

                return tryEncode().then(function (blob) {
                    if (decoded.kind === 'bitmap' && typeof decoded.source.close === 'function') {
                        try {
                            decoded.source.close();
                        } catch (e) {
                            // ignore
                        }
                    }
                    revokePreviewUrl(objectUrl);
                    disposeCanvas(canvas);
                    var previewUrl = URL.createObjectURL(blob);
                    return {
                        ok: true,
                        pending: {
                            blob: blob,
                            previewUrl: previewUrl,
                            operationId: generateUploadOperationId()
                        }
                    };
                });
            })
            .catch(function (err) {
                var code = err && err.message ? String(err.message) : 'prepare_failed';
                var message = 'No se pudo preparar la imagen.';
                if (code === 'too_large') {
                    message = 'La imagen es demasiado grande. Usa otra de menor tamaño.';
                } else if (code === 'invalid_dimensions' || code === 'decode_failed') {
                    message = 'Selecciona una imagen válida (JPEG/PNG/WebP).';
                }
                return fail(message);
            });
    }

    function onFileChange() {
        ensureNodes();
        if (!active || !fileInput || !fileInput.files || !fileInput.files[0]) {
            return;
        }
        var file = fileInput.files[0];
        setError('');
        setLocalStatus('Preparando imagen…');
        hideRetry();
        prepareCanonicalImage(file).then(function (result) {
            setLocalStatus('');
            if (!result || !result.ok) {
                clearPending();
                setError((result && result.message) || 'No se pudo preparar la imagen.');
                return;
            }
            clearPending();
            pending = result.pending;
            renderPendingPreview();
        });
    }

    function onRemoveClick(ev) {
        if (ev && typeof ev.preventDefault === 'function') {
            ev.preventDefault();
        }
        clearPending();
        setError('');
        setLocalStatus('');
        hideRetry();
    }

    function onRetryClick(ev) {
        if (ev && typeof ev.preventDefault === 'function') {
            ev.preventDefault();
        }
        if (!attachCtx || !hasPending()) {
            return;
        }
        setError('');
        setLocalStatus('Reintentando imagen…');
        hideRetry();
        runAttach(attachCtx).then(function (result) {
            if (result && result.ok) {
                setLocalStatus('');
                if (typeof attachCtx.onSettled === 'function') {
                    attachCtx.onSettled(result);
                }
                return;
            }
            setLocalStatus('');
            setError((result && result.message) || 'No se pudo adjuntar la imagen.');
            if (result && result.recoverable) {
                showRetry();
            }
            if (typeof attachCtx.onSettled === 'function') {
                attachCtx.onSettled(result);
            }
        });
    }

    function bindListeners() {
        if (listenersBound) {
            return;
        }
        ensureNodes();
        if (fileInput && typeof fileInput.addEventListener === 'function') {
            fileInput.addEventListener('change', onFileChange);
        }
        if (removeBtn && typeof removeBtn.addEventListener === 'function') {
            removeBtn.addEventListener('click', onRemoveClick);
        }
        if (retryBtn && typeof retryBtn.addEventListener === 'function') {
            retryBtn.addEventListener('click', onRetryClick);
        }
        listenersBound = true;
    }

    function clearError() {
        setError('');
    }

    function clear() {
        active = false;
        attachCtx = null;
        clearPending();
        setError('');
        setLocalStatus('');
        hideRetry();
        hideField();
        if (fileInput) {
            fileInput.disabled = true;
        }
        if (trigger) {
            trigger.setAttribute('aria-disabled', 'true');
        }
    }

    /**
     * @param {null|{status:string}} state
     */
    function apply(state) {
        clear();
        ensureNodes();
        bindListeners();
        if (!wrap || !state || typeof state !== 'object' || typeof state.status !== 'string') {
            return;
        }
        active = true;
        if (fileInput) {
            fileInput.disabled = false;
        }
        if (trigger) {
            trigger.removeAttribute('aria-disabled');
        }
        showField();
    }

    function collect() {
        // Images no van en WriteBag; attach es post-save.
    }

    function handleError() {
        return false;
    }

    function hasPending() {
        return !!(pending && pending.blob && pending.operationId);
    }

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function runAttach(ctx) {
        if (!hasPending()) {
            return Promise.resolve({
                ok: false,
                recoverable: false,
                message: 'No hay imagen pendiente.'
            });
        }
        if (!ctx || typeof ctx.ajaxUrl !== 'string' || ctx.ajaxUrl === '') {
            return Promise.resolve({
                ok: false,
                recoverable: false,
                message: 'No se pudo adjuntar la imagen.'
            });
        }
        var recordId = typeof ctx.recordId === 'number' ? ctx.recordId : parseInt(ctx.recordId, 10);
        if (!(recordId >= 1)) {
            return Promise.resolve({
                ok: false,
                recoverable: false,
                message: 'El registro no es válido para adjuntar la imagen.'
            });
        }

        var body = new FormData();
        body.append('action', ctx.attachAction || '');
        body.append('nonce', ctx.attachNonce || '');
        body.append('family_key', ctx.familyKey || '');
        body.append('container_id', String(ctx.containerId || ''));
        body.append('record_id', String(recordId));
        body.append('upload_operation_id', pending.operationId);
        body.append('file', pending.blob, 'image.jpg');

        return fetch(ctx.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.text().then(function (text) {
                return { httpStatus: response.status, payload: parseJsonSafe(text) };
            });
        }).then(function (result) {
            var payload = result.payload;
            if (payload && payload.success === true && payload.data && payload.data.image) {
                clearPending();
                return { ok: true, image: payload.data.image };
            }
            var err = (payload && payload.data) || {};
            var code = typeof err.code === 'string' ? err.code : '';
            var message = typeof err.message === 'string' && err.message !== ''
                ? err.message
                : 'No se pudo adjuntar la imagen. El registro se conservó.';
            var recoverable = !!RECOVERABLE_CODES[code];
            return {
                ok: false,
                recoverable: recoverable,
                code: code,
                message: message
            };
        }).catch(function () {
            return {
                ok: false,
                recoverable: true,
                code: 'network_error',
                message: 'No se pudo adjuntar la imagen. El registro se conservó.'
            };
        });
    }

    /**
     * @param {object} ctx
     * @returns {Promise<{ok:boolean,recoverable?:boolean,message?:string,image?:object}>}
     */
    function afterRecordSaved(ctx) {
        attachCtx = ctx && typeof ctx === 'object' ? ctx : null;
        if (!hasPending()) {
            return Promise.resolve({ ok: true, skipped: true });
        }
        setLocalStatus('Subiendo imagen…');
        setError('');
        hideRetry();
        return runAttach(attachCtx).then(function (result) {
            setLocalStatus('');
            if (result && result.ok) {
                return result;
            }
            setError((result && result.message) || 'No se pudo adjuntar la imagen.');
            if (result && result.recoverable) {
                showRetry();
            }
            return result;
        });
    }

    global.AA_CANONICAL_SHELL_CAPABILITY_MODULES = global.AA_CANONICAL_SHELL_CAPABILITY_MODULES || {};
    global.AA_CANONICAL_SHELL_CAPABILITY_MODULES.images = {
        key: 'images',
        clear: clear,
        clearError: clearError,
        apply: apply,
        collect: collect,
        handleError: handleError,
        hasPending: hasPending,
        afterRecordSaved: afterRecordSaved,
        // Expuestos para tests
        _test: {
            prepareCanonicalImage: prepareCanonicalImage,
            generateUploadOperationId: generateUploadOperationId,
            getPending: function () { return pending; },
            setPendingForTests: function (value) { pending = value; }
        }
    };
}(typeof window !== 'undefined' ? window : this));
