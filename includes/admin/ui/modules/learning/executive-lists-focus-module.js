/**
 * Executive Lists Focus — toggles explícitos de secciones (Ciclo C generalización).
 *
 * Registra pares toggle/body independientes:
 *   #aa-lists-header-toggle     → #aa-lists-body
 *   #aa-executive-header-toggle → #aa-executive-body
 */
(function () {
    'use strict';

    var PAIRS = [
        { toggleId: 'aa-lists-header-toggle', bodyId: 'aa-lists-body' },
        { toggleId: 'aa-executive-header-toggle', bodyId: 'aa-executive-body' }
    ];

    function setBodyCollapsed(body, collapsed) {
        if (!body || !body.classList) {
            return;
        }

        body.classList.toggle('is-collapsed', !!collapsed);

        if (collapsed) {
            body.setAttribute('aria-hidden', 'true');

            if (typeof body.inert !== 'undefined') {
                body.inert = true;
            } else {
                body.setAttribute('inert', '');
            }

            return;
        }

        body.removeAttribute('aria-hidden');

        if (typeof body.inert !== 'undefined') {
            body.inert = false;
        } else {
            body.removeAttribute('inert');
        }
    }

    function makeToggleHandler(toggleId, bodyId) {
        return function handleToggleClick() {
            var body = document.getElementById(bodyId);
            var toggle = document.getElementById(toggleId);

            if (!body || !toggle) {
                return;
            }

            var isCurrentlyCollapsed = body.classList.contains('is-collapsed');
            var nextCollapsed = !isCurrentlyCollapsed;

            setBodyCollapsed(body, nextCollapsed);
            toggle.setAttribute('aria-expanded', nextCollapsed ? 'false' : 'true');
        };
    }

    function bindSectionToggle(toggleId, bodyId) {
        var toggle = document.getElementById(toggleId);
        var body = document.getElementById(bodyId);

        if (!toggle || !body) {
            return;
        }

        if (toggle.dataset && toggle.dataset.aaBound === '1') {
            return;
        }

        if (toggle.dataset) {
            toggle.dataset.aaBound = '1';
        }

        toggle.addEventListener('click', makeToggleHandler(toggleId, bodyId));
    }

    function bind() {
        for (var i = 0; i < PAIRS.length; i++) {
            bindSectionToggle(PAIRS[i].toggleId, PAIRS[i].bodyId);
        }
    }

    function init() {
        bind();
    }

    var moduleExports = {
        setBodyCollapsed: setBodyCollapsed,
        bindSectionToggle: bindSectionToggle,
        bind: bind,
        PAIRS: PAIRS
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
