/**
 * Canonical Shell — creación de registro universal (SB1-5B2).
 */
(function () {
    'use strict';

    var cfg = window.AA_CANONICAL_SHELL_CREATE_RECORD;
    if (!cfg || typeof cfg !== 'object') {
        return;
    }

    var ajaxUrl = typeof cfg.ajaxUrl === 'string' ? cfg.ajaxUrl : '';
    var action = typeof cfg.action === 'string' ? cfg.action : '';
    var nonce = typeof cfg.nonce === 'string' ? cfg.nonce : '';
    var familyKey = typeof cfg.familyKey === 'string' ? cfg.familyKey : '';
    var variantKey = typeof cfg.variantKey === 'string' ? cfg.variantKey : '';
    var containerId = typeof cfg.containerId === 'number' ? cfg.containerId : parseInt(cfg.containerId, 10);
    var maxTitleLength = typeof cfg.maxTitleLength === 'number' ? cfg.maxTitleLength : 200;

    if (!ajaxUrl || !action || !nonce || !familyKey || !variantKey || !(containerId >= 1)) {
        return;
    }

    var openBtn = document.getElementById('aa-shell-open-create-record-btn');
    var modal = document.getElementById('aa-shell-create-record-modal');
    var backdrop = document.getElementById('aa-shell-create-record-modal-backdrop');
    var closeBtn = document.getElementById('aa-shell-create-record-modal-close-btn');
    var cancelBtn = document.getElementById('aa-shell-create-record-modal-cancel-btn');
    var form = document.getElementById('aa-shell-create-record-form');
    var titleInput = document.getElementById('aa-shell-create-record-title');
    var detailsInput = document.getElementById('aa-shell-create-record-details');
    var titleError = document.getElementById('aa-shell-create-record-title-error');
    var statusEl = document.getElementById('aa-shell-create-record-status');
    var submitBtn = document.getElementById('aa-shell-create-record-submit-btn');

    if (!openBtn || !modal || !form || !titleInput || !submitBtn) {
        return;
    }

    var inFlight = false;
    var previousFocus = null;

    function setStatus(message, isError) {
        if (!statusEl) {
            return;
        }
        if (!message) {
            statusEl.textContent = '';
            statusEl.classList.add('hidden');
            statusEl.classList.remove('bg-red-50', 'text-red-700', 'bg-amber-50', 'text-amber-900');
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.remove('hidden');
        if (isError) {
            statusEl.classList.add('bg-red-50', 'text-red-700');
            statusEl.classList.remove('bg-amber-50', 'text-amber-900');
        } else {
            statusEl.classList.add('bg-amber-50', 'text-amber-900');
            statusEl.classList.remove('bg-red-50', 'text-red-700');
        }
    }

    function setTitleError(message) {
        if (!titleError) {
            return;
        }
        if (!message) {
            titleError.textContent = '';
            titleError.classList.add('hidden');
            titleInput.removeAttribute('aria-invalid');
            titleInput.removeAttribute('aria-describedby');
            return;
        }
        titleError.textContent = message;
        titleError.classList.remove('hidden');
        titleInput.setAttribute('aria-invalid', 'true');
        titleInput.setAttribute('aria-describedby', 'aa-shell-create-record-title-error');
    }

    function setBusy(busy) {
        inFlight = busy;
        submitBtn.disabled = busy;
        titleInput.disabled = busy;
        if (detailsInput) {
            detailsInput.disabled = busy;
        }
        if (cancelBtn) {
            cancelBtn.disabled = busy;
        }
        if (closeBtn) {
            closeBtn.disabled = busy;
        }
        if (busy) {
            modal.setAttribute('aria-busy', 'true');
            submitBtn.setAttribute('aria-busy', 'true');
        } else {
            modal.removeAttribute('aria-busy');
            submitBtn.removeAttribute('aria-busy');
        }
    }

    function openModal() {
        if (inFlight) {
            return;
        }
        previousFocus = document.activeElement;
        setStatus('', false);
        setTitleError('');
        form.reset();
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        titleInput.focus();
    }

    function closeModal(restoreFocus) {
        if (inFlight) {
            return;
        }
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        setStatus('', false);
        setTitleError('');
        if (restoreFocus && previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        } else if (restoreFocus) {
            openBtn.focus();
        }
    }

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function clientValidate() {
        var raw = titleInput.value || '';
        var trimmed = raw.replace(/^\s+|\s+$/g, '');
        if (trimmed === '') {
            setTitleError('El título del registro no puede estar vacío.');
            titleInput.focus();
            return false;
        }
        if (trimmed.length > maxTitleLength) {
            setTitleError('El título del registro no puede exceder los ' + maxTitleLength + ' caracteres.');
            titleInput.focus();
            return false;
        }
        return true;
    }

    function submitCreate() {
        if (inFlight) {
            return;
        }
        setStatus('', false);
        setTitleError('');
        if (!clientValidate()) {
            return;
        }

        setBusy(true);

        var body = new FormData();
        body.append('action', action);
        body.append('nonce', nonce);
        body.append('family_key', familyKey);
        body.append('variant_key', variantKey);
        body.append('container_id', String(containerId));
        body.append('title', titleInput.value);
        body.append('details', detailsInput ? detailsInput.value : '');

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.text().then(function (text) {
                return { httpStatus: response.status, payload: parseJsonSafe(text) };
            });
        }).then(function (result) {
            var payload = result.payload;
            if (!payload || typeof payload !== 'object') {
                setBusy(false);
                setStatus('No se pudo crear el registro. Inténtalo de nuevo.', true);
                return;
            }

            if (payload.success === true && payload.data && payload.data.status === 'confirmed') {
                var redirect = payload.data.redirect_url;
                if (typeof redirect === 'string' && redirect !== '') {
                    window.location.assign(redirect);
                    return;
                }
                setBusy(false);
                setStatus('El registro se creó, pero no se pudo redirigir. Recarga la lista.', true);
                return;
            }

            setBusy(false);
            var err = payload.data || {};
            var code = typeof err.code === 'string' ? err.code : '';
            var message = typeof err.message === 'string' ? err.message : '';

            if (code === 'uncertain') {
                setStatus(
                    message || 'No fue posible confirmar si el registro se creó. Revisa la lista antes de intentarlo nuevamente.',
                    false
                );
                return;
            }
            if (code === 'invalid_title' || code === 'title_too_long') {
                setTitleError(message || 'Revisa el título del registro.');
                titleInput.focus();
                return;
            }
            setStatus(message || 'No se pudo crear el registro.', true);
        }).catch(function () {
            setBusy(false);
            setStatus('No se pudo crear el registro. Inténtalo de nuevo.', true);
        });
    }

    openBtn.addEventListener('click', function () {
        openModal();
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeModal(true);
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            closeModal(true);
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            closeModal(true);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeModal(true);
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitCreate();
    });
})();
