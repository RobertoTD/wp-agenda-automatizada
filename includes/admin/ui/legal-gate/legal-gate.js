/**
 * Legal gate UI — checkbox enablement, accept POST (terms or dual), retry/reload.
 * Browser never receives auth secrets, WordPress user ids, or internal provisioning identifiers.
 */
(function () {
    'use strict';

    var cfg = window.AA_LEGAL_GATE_DATA || null;
    if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
        return;
    }

    var root = document.getElementById('aa-legal-gate-root');
    var termsConsent = document.getElementById('aa-legal-gate-consent');
    var privacyConsent = document.getElementById('aa-legal-gate-privacy-consent');
    var acceptBtn = document.getElementById('aa-legal-gate-accept');
    var retryBtn = document.getElementById('aa-legal-gate-retry');
    var errorEl = document.getElementById('aa-legal-gate-error');
    var submitting = false;
    var isDual = Boolean(cfg.canAcceptDual);

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
        if (!acceptBtn) {
            return;
        }
        if (isDual) {
            acceptBtn.disabled = submitting
                || !privacyConsent
                || !termsConsent
                || !privacyConsent.checked
                || !termsConsent.checked;
            return;
        }
        if (!termsConsent) {
            return;
        }
        acceptBtn.disabled = submitting || !termsConsent.checked;
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

    function messageForCode(code, fallback) {
        if (code === 'privacy_notice_version_outdated' || code === 'terms_document_version_outdated') {
            return 'Los documentos legales se actualizaron. Recargaremos la pantalla para que los revises de nuevo.';
        }
        if (code === 'partial_acceptance_exists' || code === 'legal_gate_status_invalid') {
            return 'El estado legal cambió. Recarga e inténtalo de nuevo.';
        }
        if (code === 'privacy_consent_required') {
            return 'Debes aceptar el Aviso de Privacidad.';
        }
        if (code === 'terms_consent_required') {
            return 'Debes aceptar los Términos.';
        }
        return fallback || 'No se pudo registrar la aceptación.';
    }

    function shouldReloadForCode(code) {
        return code === 'privacy_notice_version_outdated'
            || code === 'terms_document_version_outdated'
            || code === 'partial_acceptance_exists'
            || code === 'legal_gate_status_invalid'
            || code === 'legal_gate_use_terms_endpoint';
    }

    function onAccept() {
        if (submitting) {
            return;
        }

        if (isDual) {
            if (!cfg.canAcceptDual || !privacyConsent || !termsConsent) {
                return;
            }
            if (!privacyConsent.checked || !termsConsent.checked) {
                return;
            }
            if (!cfg.privacyVersion || !cfg.termsVersion) {
                showError('No hay versiones legales disponibles. Reintenta.');
                return;
            }

            submitting = true;
            syncAcceptEnabled();
            clearError();
            if (acceptBtn) {
                acceptBtn.textContent = 'Registrando…';
            }

            postForm(cfg.acceptDualAction, {
                privacy_consent: '1',
                privacy_document_version: cfg.privacyVersion,
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

                var code = json && json.data && json.data.code ? String(json.data.code) : '';
                var message = json && json.data && json.data.message
                    ? String(json.data.message)
                    : '';
                showError(messageForCode(code, message));

                if (shouldReloadForCode(code)) {
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
            return;
        }

        if (!cfg.canAccept || !termsConsent || !termsConsent.checked) {
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
            if (result.httpOk && json && json.success === true && json.data) {
                var access = json.data.access ? String(json.data.access) : '';
                if (access === 'free' || access === 'full' || json.data.status === 'ready') {
                    reloadApp();
                    return;
                }
            }
            reloadApp();
        }).catch(function () {
            if (retryBtn) {
                retryBtn.disabled = false;
                retryBtn.textContent = 'Reintentar';
            }
            showError('No se pudo consultar el estado. Inténtalo de nuevo.');
        });
    }

    if (acceptBtn) {
        if (isDual && privacyConsent && termsConsent) {
            privacyConsent.addEventListener('change', syncAcceptEnabled);
            termsConsent.addEventListener('change', syncAcceptEnabled);
            acceptBtn.addEventListener('click', onAccept);
            syncAcceptEnabled();
        } else if (!isDual && termsConsent) {
            termsConsent.addEventListener('change', syncAcceptEnabled);
            acceptBtn.addEventListener('click', onAccept);
            syncAcceptEnabled();
        }
    }

    if (retryBtn) {
        retryBtn.addEventListener('click', onRetry);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);
})();
