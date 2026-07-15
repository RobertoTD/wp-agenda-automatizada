/**
 * Executive Lists Focus — toggle explícito del organizador (Ciclo A simplificación).
 *
 * Responsabilidad única: clic en #aa-lists-header-toggle controla
 * la expansión/colapso de #aa-lists-body.
 */
(function () {
    'use strict';

    var LISTS_BODY_ID = 'aa-lists-body';
    var LISTS_HEADER_TOGGLE_ID = 'aa-lists-header-toggle';
    var isBound = false;

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

    function handleToggleClick() {
        var body = document.getElementById(LISTS_BODY_ID);
        var toggle = document.getElementById(LISTS_HEADER_TOGGLE_ID);

        if (!body || !toggle) {
            return;
        }

        var isCurrentlyCollapsed = body.classList.contains('is-collapsed');
        var nextCollapsed = !isCurrentlyCollapsed;

        setBodyCollapsed(body, nextCollapsed);
        toggle.setAttribute('aria-expanded', nextCollapsed ? 'false' : 'true');
    }

    function bind() {
        if (isBound) {
            return;
        }

        var toggle = document.getElementById(LISTS_HEADER_TOGGLE_ID);
        var body = document.getElementById(LISTS_BODY_ID);

        if (!toggle || !body) {
            return;
        }

        isBound = true;
        toggle.addEventListener('click', handleToggleClick);
    }

    function init() {
        bind();
    }

    var moduleExports = {
        setBodyCollapsed: setBodyCollapsed,
        handleToggleClick: handleToggleClick,
        bind: bind
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
