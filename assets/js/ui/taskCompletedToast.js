/**
 * Task Completed Toast — notificación success al marcar tarea como completada.
 *
 * Reutiliza AAAdmin.toast (benefit-notification-toast.js). Sin reglas de negocio.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var TOAST_TITLE = 'Tarea completada';
    var FALLBACK_MESSAGE = 'La tarea se marcó como completada.';

    /**
     * @param {unknown} value
     * @returns {string}
     */
    function trimText(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value).trim();
    }

    /**
     * @param {HTMLElement|null|undefined} el
     * @returns {string}
     */
    function readTextContent(el) {
        if (!el) {
            return '';
        }

        return trimText(el.textContent);
    }

    /**
     * @param {{taskTitle?: string, listTitle?: string}|null|undefined} context
     * @returns {string}
     */
    function buildMessage(context) {
        var taskTitle = trimText(context && context.taskTitle);
        var listTitle = trimText(context && context.listTitle);

        if (taskTitle === '') {
            return FALLBACK_MESSAGE;
        }

        var quoted = '"' + taskTitle + '"';

        if (listTitle !== '') {
            return quoted + ' en Lista: ' + listTitle;
        }

        return quoted;
    }

    /**
     * @param {{taskTitle?: string, listTitle?: string}|null|undefined} context
     * @param {{task?: {title?: string}}|null|undefined} ajaxResult
     * @returns {{taskTitle: string, listTitle: string}}
     */
    function enrichContext(context, ajaxResult) {
        var enriched = {
            taskTitle: trimText(context && context.taskTitle),
            listTitle: trimText(context && context.listTitle)
        };

        if (enriched.taskTitle === '' && ajaxResult && ajaxResult.task) {
            enriched.taskTitle = trimText(ajaxResult.task.title);
        }

        return enriched;
    }

    /**
     * @param {HTMLElement} slot
     * @returns {string}
     */
    function parseListLabelFromExecutiveSlot(slot) {
        var spans = slot.querySelectorAll('span');
        var index = 0;

        for (index = 0; index < spans.length; index += 1) {
            var text = readTextContent(spans[index]);

            if (text.indexOf('Lista:') === 0) {
                return trimText(text.replace(/^Lista:\s*/i, ''));
            }
        }

        return '';
    }

    /**
     * @param {HTMLElement} button
     * @returns {{taskTitle: string, listTitle: string}|null}
     */
    function resolveFromExecutiveSlot(button) {
        var slot = button.closest('.aa-executive-slot-current, .aa-executive-slot');

        if (!slot) {
            return null;
        }

        var titleEl = slot.querySelector('.text-base.font-semibold')
            || slot.querySelector('p.text-base.font-semibold');

        return {
            taskTitle: readTextContent(titleEl),
            listTitle: parseListLabelFromExecutiveSlot(slot)
        };
    }

    /**
     * @param {HTMLElement} button
     * @returns {{taskTitle: string, listTitle: string}|null}
     */
    function resolveFromExecutableItem(button) {
        var item = button.closest('.aa-executable-item');

        if (!item) {
            return null;
        }

        var titleEl = item.querySelector('.aa-executable-item-title');
        var listCard = button.closest('.aa-executable-list-card, .aa-task-list-card');
        var listTitleEl = listCard ? listCard.querySelector('h4') : null;

        return {
            taskTitle: readTextContent(titleEl),
            listTitle: readTextContent(listTitleEl)
        };
    }

    /**
     * @param {HTMLElement} button
     * @returns {{taskTitle: string, listTitle: string}|null}
     */
    function resolveFromTaskRow(button) {
        var row = button.closest('.aa-task-row');

        if (!row) {
            return null;
        }

        var titleEl = row.querySelector('.text-sm.font-medium.text-gray-900')
            || row.querySelector('.text-sm.font-medium');

        var listCard = button.closest('.aa-task-list-card');
        var listTitleEl = listCard ? listCard.querySelector('h4') : null;

        return {
            taskTitle: readTextContent(titleEl),
            listTitle: readTextContent(listTitleEl)
        };
    }

    /**
     * @param {HTMLElement} button
     * @returns {{taskTitle: string, listTitle: string}|null}
     */
    function resolveFromExecutiveCandidate(button) {
        var candidate = button.closest('.aa-executive-candidate');

        if (!candidate) {
            return null;
        }

        var titleEl = candidate.querySelector('.text-sm.font-semibold');
        var metaEl = candidate.querySelector('.text-xs.text-gray-600');
        var listTitle = '';

        if (metaEl) {
            var meta = readTextContent(metaEl);
            var match = meta.match(/Lista:\s*([^·]+)/);

            if (match) {
                listTitle = trimText(match[1]);
            }
        }

        return {
            taskTitle: readTextContent(titleEl),
            listTitle: listTitle
        };
    }

    /**
     * @param {HTMLElement|null|undefined} button
     * @returns {{taskTitle: string, listTitle: string}}
     */
    function resolveFromButton(button) {
        if (!button || typeof button.closest !== 'function') {
            return { taskTitle: '', listTitle: '' };
        }

        var resolvers = [
            resolveFromExecutiveSlot,
            resolveFromExecutableItem,
            resolveFromTaskRow,
            resolveFromExecutiveCandidate
        ];
        var index = 0;

        for (index = 0; index < resolvers.length; index += 1) {
            var resolved = resolvers[index](button);

            if (resolved && (resolved.taskTitle !== '' || resolved.listTitle !== '')) {
                return resolved;
            }
        }

        return { taskTitle: '', listTitle: '' };
    }

    /**
     * @param {{taskTitle?: string, listTitle?: string}|null|undefined} context
     * @param {{task?: {title?: string}}|null|undefined} [ajaxResult]
     */
    function show(context, ajaxResult) {
        var toastApi = globalRoot.AAAdmin && globalRoot.AAAdmin.toast;
        var messageContext = enrichContext(context, ajaxResult);

        if (!toastApi || typeof toastApi.show !== 'function') {
            if (typeof console !== 'undefined' && typeof console.debug === 'function') {
                console.debug('[AATaskCompletedToast] Toast API not available.');
            }

            return;
        }

        toastApi.show({
            severity: 'success',
            title: TOAST_TITLE,
            message: buildMessage(messageContext)
        });
    }

    var api = {
        buildMessage: buildMessage,
        enrichContext: enrichContext,
        resolveFromButton: resolveFromButton,
        show: show,
        TOAST_TITLE: TOAST_TITLE,
        FALLBACK_MESSAGE: FALLBACK_MESSAGE
    };

    globalRoot.AATaskCompletedToast = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
