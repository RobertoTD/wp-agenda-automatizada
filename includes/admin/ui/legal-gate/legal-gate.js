/**
 * Legal gate UI — checkbox enablement, accept POST, retry/reload.
 * Browser never receives auth secrets or internal provisioning identifiers.
 */
(function () {
    'use strict';

    var cfg = window.AA_LEGAL_GATE_DATA || null;
    if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
        return;
    }

    var root = document.getElementById('aa-legal-gate-root');
    var consent = document.getElementById('aa-legal-gate-consent');
    var acceptBtn = document.getElementById('aa-legal-gate-accept');
    var retryBtn = document.getElementById('aa-legal-gate-retry');
    var errorEl = document.getElementById('aa-legal-gate-error');
    var submitting = false;

    function showError(message) {
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message || 'Ocurrió un error. Inténtalo de nuevo.';
        errorEl.classList.add('is-visible');
    }

    function clearError() {
        if (!errorEl) {
            return;
        }
        errorEl.textContent = '';
        errorEl.classList.remove('is-visible');
    }

    function syncAcceptEnabled() {
        if (!acceptBtn || !consent) {
            return;
        }
        acceptBtn.disabled = submitting || !consent.checked;
    }

    function reloadApp() {
        window.location.reload();
    }

    function postForm(action, fields) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('_wpnonce', cfg.nonce);
        Object.keys(fields || {}).forEach(function (key) {
            body.set(key, String(fields[key]));
        });

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (json) {
                return { httpOk: response.ok, json: json };
            });
        });
    }

    function onAccept() {
        if (!cfg.canAccept || !consent || !consent.checked || submitting) {
            return;
        }
        if (!cfg.termsVersion) {
            showError('No hay una versión de Términos disponible. Reintenta.');
            return;
        }

        submitting = true;
        syncAcceptEnabled();
        clearError();
        if (acceptBtn) {
            acceptBtn.textContent = 'Registrando…';
        }

        postForm(cfg.acceptAction, {
            terms_consent: '1',
            terms_document_version: cfg.termsVersion
        }).then(function (result) {
            var json = result.json;
            if (result.httpOk && json && json.success === true) {
                reloadApp();
                return;
            }

            submitting = false;
            syncAcceptEnabled();
            if (acceptBtn) {
                acceptBtn.textContent = 'Aceptar y continuar';
            }

            var message = json && json.data && json.data.message
                ? String(json.data.message)
                : 'No se pudo registrar la aceptación.';
            showError(message);

            if (json && json.data && json.data.code === 'terms_document_version_outdated') {
                // Force a clean status re-check so the shown version refreshes.
                setTimeout(reloadApp, 1200);
            }
        }).catch(function () {
            submitting = false;
            syncAcceptEnabled();
            if (acceptBtn) {
                acceptBtn.textContent = 'Aceptar y continuar';
            }
            showError('No se pudo contactar al servidor. Inténtalo de nuevo.');
        });
    }

    function onRetry() {
        if (submitting) {
            return;
        }
        clearError();
        if (retryBtn) {
            retryBtn.disabled = true;
            retryBtn.textContent = 'Consultando…';
        }

        postForm(cfg.statusAction, {}).then(function (result) {
            var json = result.json;
            if (result.httpOk && json && json.success === true && json.data && json.data.status === 'ready') {
                reloadApp();
                return;
            }
            // Any other status or error: reload so PHP re-renders the correct gate.
            reloadApp();
        }).catch(function () {
            if (retryBtn) {
                retryBtn.disabled = false;
                retryBtn.textContent = 'Reintentar';
            }
            showError('No se pudo consultar el estado. Inténtalo de nuevo.');
        });
    }

    if (consent && acceptBtn) {
        consent.addEventListener('change', syncAcceptEnabled);
        acceptBtn.addEventListener('click', onAccept);
        syncAcceptEnabled();
    }

    if (retryBtn) {
        retryBtn.addEventListener('click', onRetry);
    }

    // Hardening: no Escape / history tricks should dismiss this screen.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);
})();
