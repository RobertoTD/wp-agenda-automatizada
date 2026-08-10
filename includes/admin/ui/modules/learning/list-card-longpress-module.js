/**
 * List Card Long-Press Module — abre el modal de nueva tarea (con la lista
 * preseleccionada) al mantener pulsado el summary de una tarjeta de lista
 * de usuario (Mis listas). Las listas de sistema (Agenda app) no reaccionan.
 *
 * El click rápido conserva su comportamiento nativo (toggle expandir/colapsar).
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

    var pressTimer = null;
    var pressActive = false;
    var longPressFired = false;
    var startX = 0;
    var startY = 0;
    var activeListId = '';

    function asString(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function isInteractiveTarget(target) {
        return !!(target && typeof target.closest === 'function' && target.closest(
            'button, a, input, select, textarea, label, [role="menuitem"],'
            + ' [data-aa-list-options-trigger], [data-aa-list-add-task], [data-aa-list-details-toggle]'
        ));
    }

    /**
     * @param {EventTarget|null} target
     * @returns {HTMLElement|null}
     */
    function resolveListCardSummary(target) {
        if (!target || typeof target.closest !== 'function') {
            return null;
        }

        var summary = target.closest('summary');

        if (!summary) {
            return null;
        }

        var details = summary.parentElement;

        if (!details
            || !details.classList
            || !details.classList.contains('aa-executable-list-card')) {
            return null;
        }

        return summary;
    }

    /**
     * Misma elegibilidad que el menú "+ Tarea" / select del modal de crear tarea.
     *
     * @param {HTMLElement|null} summary
     * @returns {HTMLElement|null}
     */
    function resolveAddTaskButton(summary) {
        var details = summary && summary.parentElement;

        if (!details || typeof details.querySelector !== 'function') {
            return null;
        }

        return details.querySelector('[data-aa-list-add-task="1"]');
    }

    /**
     * @param {HTMLElement|null} summary
     * @returns {boolean}
     */
    function isUserManualListCard(summary) {
        return !!resolveAddTaskButton(summary);
    }

    function clearPress() {
        if (pressTimer !== null) {
            globalRoot.clearTimeout(pressTimer);
            pressTimer = null;
        }

        pressActive = false;
        activeListId = '';
    }

    function triggerLongPress() {
        pressTimer = null;
        pressActive = false;
        longPressFired = true;

        var listId = activeListId;
        activeListId = '';

        if (globalRoot.navigator && typeof globalRoot.navigator.vibrate === 'function') {
            try {
                globalRoot.navigator.vibrate(15);
            } catch (err) {
                // vibración es best-effort; ignorar fallos
            }
        }

        if (listId
            && globalRoot.AATasksBoard
            && typeof globalRoot.AATasksBoard.openNewTaskForList === 'function') {
            globalRoot.AATasksBoard.openNewTaskForList(listId);
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

        var summary = resolveListCardSummary(event.target);

        if (!summary || !isUserManualListCard(summary)) {
            return;
        }

        var listId = asString(summary.parentElement.getAttribute('data-list-id')).trim();

        if (listId === '') {
            return;
        }

        pressActive = true;
        activeListId = listId;
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

        if (typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
    }

    var isBound = false;

    function bindLongPressModule() {
        if (isBound || !document.getElementById('aa-tasks-module-root')) {
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
        resolveListCardSummary: resolveListCardSummary,
        resolveAddTaskButton: resolveAddTaskButton,
        isUserManualListCard: isUserManualListCard,
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
