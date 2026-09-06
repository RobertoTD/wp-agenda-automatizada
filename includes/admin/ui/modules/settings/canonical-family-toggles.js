/**
 * Canonical family enablement toggles (Settings — PCU-5A).
 *
 * Independent AJAX switches for declared families. Nav/header refresh via
 * navigation or full reload (no live sidebar regeneration).
 */
(function (window, document) {
    'use strict';

    var inFlight = Object.create(null);

    function getConfig() {
        return window.AA_CANONICAL_FAMILY_ENABLED || {};
    }

    function statusEl(row) {
        return row ? row.querySelector('[data-aa-family-status]') : null;
    }

    function setStatus(row, text) {
        var el = statusEl(row);
        if (el) {
            el.textContent = text || '';
        }
    }

    function setBusy(input, row, busy) {
        input.disabled = !!busy;
        if (busy) {
            input.setAttribute('aria-busy', 'true');
            if (row) {
                row.setAttribute('aria-busy', 'true');
            }
        } else {
            input.removeAttribute('aria-busy');
            if (row) {
                row.removeAttribute('aria-busy');
            }
        }
    }

    function parseJson(response) {
        return response.text().then(function (text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                return null;
            }
        });
    }

    function onToggleChange(event) {
        var input = event.target;
        if (!input || !input.matches || !input.matches('[data-aa-canonical-family-toggle]')) {
            return;
        }

        var familyKey = input.getAttribute('data-family-key') || '';
        if (familyKey === '' || inFlight[familyKey]) {
            event.preventDefault();
            input.checked = !input.checked;
            return;
        }

        var row = input.closest('[data-aa-canonical-family-row]');
        var previousChecked = !input.checked;
        var desiredChecked = !!input.checked;
        var cfg = getConfig();
        var ajaxUrl = typeof cfg.ajaxUrl === 'string' ? cfg.ajaxUrl : '';
        var action = typeof cfg.action === 'string' ? cfg.action : '';
        var nonce = typeof cfg.nonce === 'string' ? cfg.nonce : '';

        if (ajaxUrl === '' || action === '' || nonce === '') {
            input.checked = previousChecked;
            setStatus(row, 'No se pudo guardar. Intenta de nuevo.');
            return;
        }

        inFlight[familyKey] = true;
        setBusy(input, row, true);
        setStatus(row, 'Guardando…');

        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', nonce);
        body.set('family_key', familyKey);
        body.set('enabled', desiredChecked ? '1' : '0');

        window.fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return parseJson(response).then(function (json) {
                return { ok: response.ok, json: json };
            });
        }).then(function (result) {
            var json = result.json;
            if (!json || json.success !== true || !json.data) {
                input.checked = previousChecked;
                setStatus(row, 'No se pudo guardar. Intenta de nuevo.');
                return;
            }

            var data = json.data;
            var serverEnabled = !!data.is_enabled;
            input.checked = serverEnabled;
            setStatus(row, serverEnabled ? 'Activado' : 'Desactivado');
        }).catch(function () {
            input.checked = previousChecked;
            setStatus(row, 'No se pudo guardar. Intenta de nuevo.');
        }).then(function () {
            setBusy(input, row, false);
            delete inFlight[familyKey];
        });
    }

    function bind() {
        var root = document.getElementById('aa-canonical-record-types-root');
        if (!root) {
            return;
        }
        root.addEventListener('change', onToggleChange);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }

    window.AACanonicalFamilyToggles = {};
}(window, document));
