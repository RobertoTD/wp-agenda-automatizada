/**
 * Canonical family enablement toggles (Settings — PCU-5A).
 *
 * Independent AJAX switches for declared families. Updates sidebar nav via
 * local DOM + postMessage to parent.
 */
(function (window, document) {
    'use strict';

    var MESSAGE_TYPE = 'aa-canonical-family-enabled-changed';
    var inFlight = Object.create(null);

    function getConfig() {
        return window.AA_CANONICAL_FAMILY_ENABLED || {};
    }

    function targetOrigin() {
        var cfg = getConfig();
        if (typeof cfg.targetOrigin === 'string' && cfg.targetOrigin !== '') {
            return cfg.targetOrigin;
        }
        return window.location.origin;
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

    function isAllowlistedNavUrl(url) {
        if (typeof url !== 'string' || url === '') {
            return false;
        }
        try {
            var parsed = new URL(url, window.location.origin);
            if (parsed.origin !== window.location.origin) {
                return false;
            }
            if (parsed.pathname.indexOf('admin-post.php') === -1) {
                return false;
            }
            var action = parsed.searchParams.get('action');
            var module = parsed.searchParams.get('module');
            return action === 'aa_iframe_content' && module === 'canonical_shell';
        } catch (e) {
            return false;
        }
    }

    function renderNav(nav) {
        var container = document.getElementById('aa-canonical-record-types-nav');
        if (!container || !Array.isArray(nav)) {
            return;
        }

        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }

        nav.forEach(function (item) {
            if (!item || typeof item !== 'object') {
                return;
            }
            var key = typeof item.family_key === 'string' ? item.family_key : '';
            var label = typeof item.label === 'string' ? item.label : '';
            var url = typeof item.url === 'string' ? item.url : '';
            if (key === '' || label === '' || !isAllowlistedNavUrl(url)) {
                return;
            }

            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = url;
            a.setAttribute('data-aa-nav-module', 'canonical_shell');
            a.setAttribute('data-aa-nav-family', key);
            a.className = 'flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-gray-600 hover:bg-gray-100';

            var iconWrap = document.createElement('span');
            iconWrap.className = 'flex items-center justify-center w-6 h-6 text-gray-500';
            iconWrap.setAttribute('aria-hidden', 'true');

            var labelSpan = document.createElement('span');
            labelSpan.className = 'text-base !font-semibold';
            labelSpan.textContent = label;

            a.appendChild(iconWrap);
            a.appendChild(labelSpan);
            li.appendChild(a);
            container.appendChild(li);
        });
    }

    function notifyParent(nav) {
        if (!window.parent || window.parent === window) {
            return;
        }
        try {
            window.parent.postMessage({
                type: MESSAGE_TYPE,
                nav: Array.isArray(nav) ? nav : []
            }, targetOrigin());
        } catch (e) {
            // Ignore cross-origin refusal.
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
            renderNav(data.nav);
            notifyParent(data.nav);
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

    window.AACanonicalFamilyToggles = {
        renderNav: renderNav,
        isAllowlistedNavUrl: isAllowlistedNavUrl,
        MESSAGE_TYPE: MESSAGE_TYPE
    };
}(window, document));
