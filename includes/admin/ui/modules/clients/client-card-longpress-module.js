/**
 * Client Long-Press Module —
 * - Título de página (#aa-page-title) en vista lista: abre modal de nuevo cliente.
 * - Header de tarjeta en #aa-clients-grid: abre el expediente del cliente.
 *
 * El click rápido conserva su comportamiento nativo.
 * Solo un click sostenido (>= LONG_PRESS_MS) sin desplazamiento dispara la acción.
 * Compatible con mouse y touch vía Pointer Events.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var LONG_PRESS_MS = 500;
    var MOVE_TOLERANCE_PX = 10;
    var ACTION_CREATE = 'create';
    var ACTION_EXPEDIENTE = 'expediente';

    var pressTimer = null;
    var pressActive = false;
    var longPressFired = false;
    var startX = 0;
    var startY = 0;
    var activeAction = '';
    var activeExpedienteButton = null;

    function isInteractiveTarget(target) {
        return !!(target && typeof target.closest === 'function' && target.closest(
            'button, a, input, select, textarea, label, [role="menuitem"], #aa-clients-area-tools'
        ));
    }

    /**
     * Superficie de create: #aa-page-title mientras la lista de clientes está visible.
     *
     * @param {EventTarget|null} target
     * @returns {HTMLElement|null}
     */
    function resolveClientsSectionHeader(target) {
        if (!target || typeof target.closest !== 'function') {
            return null;
        }

        var pageTitle = target.closest('#aa-page-title');

        if (!pageTitle) {
            return null;
        }

        var listRoot = typeof document !== 'undefined' && document.getElementById
            ? document.getElementById('aa-clients-list-root')
            : null;

        if (!listRoot || (listRoot.classList && listRoot.classList.contains('hidden'))) {
            return null;
        }

        return pageTitle;
    }

    /**
     * @param {EventTarget|null} target
     * @returns {HTMLElement|null}
     */
    function resolveClientCardHeader(target) {
        if (!target || typeof target.closest !== 'function') {
            return null;
        }

        var header = target.closest('.aa-appointment-header');

        if (!header) {
            return null;
        }

        var card = header.closest('.aa-appointment-card');

        if (!card || !card.closest('#aa-clients-grid')) {
            return null;
        }

        return header;
    }

    /**
     * @param {HTMLElement} header
     * @returns {HTMLElement|null}
     */
    function resolveExpedienteButton(header) {
        var card = header && typeof header.closest === 'function'
            ? header.closest('.aa-appointment-card')
            : null;

        if (!card || typeof card.querySelector !== 'function') {
            return null;
        }

        return card.querySelector('.aa-btn-expediente-cliente');
    }

    function clearPress() {
        if (pressTimer !== null) {
            globalRoot.clearTimeout(pressTimer);
            pressTimer = null;
        }

        pressActive = false;
        activeAction = '';
        activeExpedienteButton = null;
    }

    function openCreateClientModal() {
        if (globalRoot.AAAdmin
            && globalRoot.AAAdmin.ClientCreateModal
            && typeof globalRoot.AAAdmin.ClientCreateModal.openCreate === 'function') {
            globalRoot.AAAdmin.ClientCreateModal.openCreate();
            return;
        }

        console.error('[ClientsLongPress] AAAdmin.ClientCreateModal.openCreate no disponible');
    }

    function triggerLongPress() {
        pressTimer = null;
        pressActive = false;
        longPressFired = true;

        var action = activeAction;
        var expedienteButton = activeExpedienteButton;
        activeAction = '';
        activeExpedienteButton = null;

        if (globalRoot.navigator && typeof globalRoot.navigator.vibrate === 'function') {
            try {
                globalRoot.navigator.vibrate(15);
            } catch (err) {
                // vibración es best-effort; ignorar fallos
            }
        }

        if (action === ACTION_CREATE) {
            openCreateClientModal();
            return;
        }

        if (action !== ACTION_EXPEDIENTE || !expedienteButton || expedienteButton.disabled) {
            return;
        }

        if (typeof expedienteButton.click === 'function') {
            expedienteButton.click();
        }
    }

    function handlePointerDown(event) {
        longPressFired = false;
        clearPress();

        if (event.button != null && event.button !== 0) {
            return;
        }

        if (isInteractiveTarget(event.target)) {
            return;
        }

        var sectionHeader = resolveClientsSectionHeader(event.target);

        if (sectionHeader) {
            pressActive = true;
            activeAction = ACTION_CREATE;
            startX = event.clientX;
            startY = event.clientY;
            pressTimer = globalRoot.setTimeout(triggerLongPress, LONG_PRESS_MS);
            return;
        }

        var header = resolveClientCardHeader(event.target);

        if (!header) {
            return;
        }

        var expedienteButton = resolveExpedienteButton(header);

        if (!expedienteButton || expedienteButton.disabled) {
            return;
        }

        pressActive = true;
        activeAction = ACTION_EXPEDIENTE;
        activeExpedienteButton = expedienteButton;
        startX = event.clientX;
        startY = event.clientY;

        pressTimer = globalRoot.setTimeout(triggerLongPress, LONG_PRESS_MS);
    }

    function handlePointerMove(event) {
        if (!pressActive) {
            return;
        }

        var dx = event.clientX - startX;
        var dy = event.clientY - startY;

        if ((dx * dx + dy * dy) > (MOVE_TOLERANCE_PX * MOVE_TOLERANCE_PX)) {
            clearPress();
        }
    }

    function handlePointerUp() {
        clearPress();
    }

    function handlePointerCancel() {
        clearPress();
    }

    function handleClickCapture(event) {
        if (!longPressFired) {
            return;
        }

        longPressFired = false;

        if (typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }
    }

    function handleContextMenu(event) {
        if (!pressActive && !longPressFired) {
            return;
        }

        if (!resolveClientsSectionHeader(event.target) && !resolveClientCardHeader(event.target)) {
            return;
        }

        if (typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
    }

    var isBound = false;

    function bindLongPressModule() {
        if (isBound || !document.getElementById('aa-clients-list-root')) {
            return;
        }

        isBound = true;

        document.addEventListener('pointerdown', handlePointerDown, true);
        document.addEventListener('pointermove', handlePointerMove, true);
        document.addEventListener('pointerup', handlePointerUp, true);
        document.addEventListener('pointercancel', handlePointerCancel, true);
        document.addEventListener('click', handleClickCapture, true);
        document.addEventListener('contextmenu', handleContextMenu, true);
    }

    function initLongPressModule() {
        bindLongPressModule();
    }

    var moduleExports = {
        LONG_PRESS_MS: LONG_PRESS_MS,
        MOVE_TOLERANCE_PX: MOVE_TOLERANCE_PX,
        resolveClientsSectionHeader: resolveClientsSectionHeader,
        resolveClientCardHeader: resolveClientCardHeader,
        resolveExpedienteButton: resolveExpedienteButton,
        isInteractiveTarget: isInteractiveTarget
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLongPressModule);
    } else {
        initLongPressModule();
    }
})();
