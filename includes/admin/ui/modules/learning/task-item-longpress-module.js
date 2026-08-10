/**
 * Task Item Long-Press Module — abre el modal de editar tarea al mantener
 * pulsado el summary de un ítem ejecutable.
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
    var activeEditButton = null;

    function isInteractiveTarget(target) {
        return !!(target && typeof target.closest === 'function' && target.closest(
            'button, a, input, select, textarea, label, [role="menuitem"],'
            + ' [data-aa-task-options-trigger], [data-aa-task-edit],'
            + ' [data-tasks-action]'
        ));
    }

    /**
     * @param {EventTarget|null} target
     * @returns {HTMLElement|null}
     */
    function resolveTaskItemSummary(target) {
        if (!target || typeof target.closest !== 'function') {
            return null;
        }

        var summary = target.closest('summary.aa-executable-item-summary');

        if (!summary) {
            return null;
        }

        var details = summary.parentElement;

        if (!details
            || !details.classList
            || !details.classList.contains('aa-executable-item')) {
            return null;
        }

        return summary;
    }

    /**
     * @param {HTMLElement} summary
     * @returns {HTMLElement|null}
     */
    function resolveEditButton(summary) {
        var details = summary && summary.parentElement;

        if (!details || typeof details.querySelector !== 'function') {
            return null;
        }

        return details.querySelector('[data-aa-task-edit="1"]');
    }

    function clearPress() {
        if (pressTimer !== null) {
            globalRoot.clearTimeout(pressTimer);
            pressTimer = null;
        }

        pressActive = false;
        activeEditButton = null;
    }

    function triggerLongPress() {
        pressTimer = null;
        pressActive = false;
        longPressFired = true;

        var editButton = activeEditButton;
        activeEditButton = null;

        if (globalRoot.navigator && typeof globalRoot.navigator.vibrate === 'function') {
            try {
                globalRoot.navigator.vibrate(15);
            } catch (err) {
                // vibración es best-effort; ignorar fallos
            }
        }

        if (!editButton || editButton.disabled) {
            return;
        }

        if (globalRoot.AATaskEdit
            && typeof globalRoot.AATaskEdit.openEditModalFromButton === 'function') {
            globalRoot.AATaskEdit.openEditModalFromButton(editButton);
            return;
        }

        if (typeof editButton.click === 'function') {
            editButton.click();
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

        var summary = resolveTaskItemSummary(event.target);

        if (!summary) {
            return;
        }

        var editButton = resolveEditButton(summary);

        if (!editButton || editButton.disabled) {
            return;
        }

        pressActive = true;
        activeEditButton = editButton;
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

        if (!resolveTaskItemSummary(event.target)) {
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
        resolveTaskItemSummary: resolveTaskItemSummary,
        resolveEditButton: resolveEditButton,
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
