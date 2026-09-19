/**
 * Acción Expediente (capability dossier) en tarjetas del shell de Contactos.
 * POST protegido; navega con redirect_url del servidor.
 */
(function (global) {
    'use strict';

    var cfg = global.AA_CANONICAL_SHELL_DOSSIER || null;
    if (!cfg || !cfg.ajaxUrl || !cfg.action || !cfg.nonce) {
        return;
    }

    var busy = false;

    function postOpen(recordId, button) {
        if (busy || !recordId) {
            return;
        }
        busy = true;
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        var body = new FormData();
        body.append('action', cfg.action);
        body.append('nonce', cfg.nonce);
        body.append('family_key', cfg.familyKey || 'contact');
        body.append('container_id', String(cfg.containerId || ''));
        body.append('record_id', String(recordId));
        if (cfg.listsScope) {
            body.append('lists_scope', cfg.listsScope);
        }
        if (cfg.page) {
            body.append('page', String(cfg.page));
        }
        if (cfg.containersPage) {
            body.append('containers_page', String(cfg.containersPage));
        }

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body,
        })
            .then(function (res) {
                return res.json().then(function (payload) {
                    return { ok: res.ok, status: res.status, payload: payload };
                });
            })
            .then(function (result) {
                var payload = result.payload || {};
                if (payload.success && payload.data && typeof payload.data.redirect_url === 'string') {
                    global.location.href = payload.data.redirect_url;
                    return;
                }
                var err = (payload.data && payload.data.message) ? payload.data.message : 'No se pudo abrir el expediente.';
                if (typeof global.alert === 'function') {
                    global.alert(err);
                }
            })
            .catch(function () {
                if (typeof global.alert === 'function') {
                    global.alert('No se pudo abrir el expediente.');
                }
            })
            .then(function () {
                busy = false;
                if (button) {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                }
            });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) {
            return;
        }
        var btn = target.closest('[data-aa-dossier-open]');
        if (!btn || btn.disabled) {
            return;
        }
        event.preventDefault();
        var recordId = parseInt(btn.getAttribute('data-aa-dossier-open'), 10);
        if (!recordId || recordId < 1) {
            return;
        }
        postOpen(recordId, btn);
    });
}(window));
