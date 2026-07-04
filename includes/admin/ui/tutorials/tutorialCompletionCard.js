/**
 * Ephemeral tutorial completion card (D1).
 *
 * Visual only — no durable state, no AATutorial runtime, no backend.
 */
(function () {
    'use strict';

    var ROOT_ID = 'aa-tutorial-completion-card-root';
    var TUTORIAL_ENGINE_ROOT_ID = 'aa-tutorial-root';

    var DEFAULT_TITLE = '¡Listo! Creaste tu cita de prueba';
    var DEFAULT_TEXT = 'Tu cita ya está en la Agenda. Haz clic en ella para ver el detalle y, según corresponda, confirmarla, cancelarla o registrar si el cliente asistió. También puedes contactarlo por WhatsApp desde la ficha.';
    var DEFAULT_BUTTON_LABEL = 'Cerrar';

    function normalizeString(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function createElement(tag, className) {
        if (typeof document === 'undefined' || !document || typeof document.createElement !== 'function') {
            return null;
        }

        var element = document.createElement(tag);

        if (className) {
            element.className = className;
        }

        return element;
    }

    function setText(element, value) {
        if (!element) {
            return;
        }

        element.textContent = value || '';
    }

    function dismiss() {
        if (typeof document === 'undefined' || !document || typeof document.getElementById !== 'function') {
            return;
        }

        var root = document.getElementById(ROOT_ID);

        if (root && root.parentNode) {
            root.parentNode.removeChild(root);
        }
    }

    /**
     * @param {{title?: string, text?: string, buttonLabel?: string}|undefined} options
     */
    function show(options) {
        var input = options || {};

        if (typeof document === 'undefined' || !document || !document.body) {
            return false;
        }

        dismiss();

        var root = createElement('div', 'aa-tutorial-root is-interactive');
        var backdrop = createElement('div', 'aa-tutorial-backdrop');
        var card = createElement('div', 'aa-tutorial-card');
        var title = createElement('h3', 'aa-tutorial-title');
        var text = createElement('p', 'aa-tutorial-text');
        var actions = createElement('div', 'aa-tutorial-actions');
        var button = createElement('button', 'aa-tutorial-button');

        if (!root || !backdrop || !card || !title || !text || !actions || !button) {
            return false;
        }

        root.id = ROOT_ID;

        setText(title, normalizeString(input.title) || DEFAULT_TITLE);
        setText(text, normalizeString(input.text) || DEFAULT_TEXT);
        setText(button, normalizeString(input.buttonLabel) || DEFAULT_BUTTON_LABEL);

        button.type = 'button';
        button.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            dismiss();
        });

        card.appendChild(title);
        card.appendChild(text);
        actions.appendChild(button);
        card.appendChild(actions);

        card.style.top = '50%';
        card.style.left = '50%';
        card.style.transform = 'translate(-50%, -50%)';

        root.appendChild(backdrop);
        root.appendChild(card);
        document.body.appendChild(root);

        return true;
    }

    var api = {
        show: show,
        dismiss: dismiss,
        ROOT_ID: ROOT_ID,
        TUTORIAL_ENGINE_ROOT_ID: TUTORIAL_ENGINE_ROOT_ID,
        DEFAULT_TITLE: DEFAULT_TITLE,
        DEFAULT_TEXT: DEFAULT_TEXT,
        DEFAULT_BUTTON_LABEL: DEFAULT_BUTTON_LABEL
    };

    window.TutorialCompletionCard = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
