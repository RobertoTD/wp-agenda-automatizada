/**
 * Training Completion Modal (C9A5b).
 *
 * Reuses AAAdmin.modal / #aa-modal-root. State machine:
 * question → feedback → (next question | conclusion) → submitting | error
 *
 * markCompleted is called only from the conclusion action button.
 * Quiz selections are never sent to the backend.
 */
(function (root) {
    'use strict';

    var PRIMARY_BTN =
        'inline-flex items-center justify-center w-full sm:w-auto px-4 py-2.5 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors';
    var SECONDARY_BTN =
        'inline-flex items-center justify-center w-full sm:w-auto px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-60 disabled:cursor-not-allowed transition-colors';

    /** @type {null|{
     *   lessonKey: string,
     *   completionFlow: object,
     *   accountModuleUrl: string,
     *   onCompleted: Function|null,
     *   onDismiss: Function|null,
     *   returnFocusEl: HTMLElement|null,
     *   questionIndex: number,
     *   selectedOptionKey: string|null,
     *   phase: string,
     *   submitting: boolean,
     *   completedOk: boolean,
     *   errorCode: string|null,
     *   patchedClose: boolean,
     *   originalClose: Function|null,
     *   keydownHandler: Function|null
     * }} */
    var session = null;

    function resolveModalApi() {
        if (root.AAAdmin && root.AAAdmin.modal && typeof root.AAAdmin.modal.open === 'function') {
            return root.AAAdmin.modal;
        }
        return null;
    }

    function resolveService() {
        if (root.TrainingService && typeof root.TrainingService.markCompleted === 'function') {
            return root.TrainingService;
        }
        return null;
    }

    function resolveUx() {
        return root.TrainingPortalUx || null;
    }

    function getModalRoot() {
        return document.getElementById('aa-modal-root');
    }

    function getQuestions(flow) {
        return flow && Array.isArray(flow.questions) ? flow.questions : [];
    }

    function getQuestion(flow, index) {
        var questions = getQuestions(flow);
        return questions[index] || null;
    }

    function findOption(question, optionKey) {
        if (!question || !Array.isArray(question.options)) {
            return null;
        }
        for (var i = 0; i < question.options.length; i += 1) {
            if (question.options[i] && question.options[i].option_key === optionKey) {
                return question.options[i];
            }
        }
        return null;
    }

    function resetQuizCursor() {
        if (!session) {
            return;
        }
        session.questionIndex = 0;
        session.selectedOptionKey = null;
        session.phase = 'question';
        session.submitting = false;
        session.errorCode = null;
    }

    function mapError(code) {
        var ux = resolveUx();
        if (ux && typeof ux.mapCompletionError === 'function') {
            return ux.mapCompletionError({ code: code });
        }
        return {
            text: 'No pudimos completar la lección.',
            retry: true,
            showAccountLink: false
        };
    }

    function restoreFocus() {
        var el = session && session.returnFocusEl;
        if (el && typeof el.focus === 'function') {
            try {
                el.focus({ preventScroll: true });
            } catch (e) {
                try {
                    el.focus();
                } catch (e2) {
                    // ignore
                }
            }
        }
    }

    function clearA11y(rootEl) {
        if (!rootEl) {
            return;
        }
        rootEl.removeAttribute('role');
        rootEl.removeAttribute('aria-modal');
        rootEl.removeAttribute('aria-labelledby');
        if (session && session.keydownHandler) {
            rootEl.removeEventListener('keydown', session.keydownHandler, true);
            session.keydownHandler = null;
        }
    }

    function applyA11y(rootEl, titleId) {
        if (!rootEl) {
            return;
        }
        rootEl.setAttribute('role', 'dialog');
        rootEl.setAttribute('aria-modal', 'true');
        if (titleId) {
            rootEl.setAttribute('aria-labelledby', titleId);
        }

        session.keydownHandler = function (event) {
            if (!session || event.key !== 'Tab') {
                return;
            }
            var focusables = rootEl.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            var list = Array.prototype.slice.call(focusables).filter(function (node) {
                return node.offsetParent !== null || node === document.activeElement;
            });
            if (list.length === 0) {
                return;
            }
            var first = list[0];
            var last = list[list.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };
        rootEl.addEventListener('keydown', session.keydownHandler, true);
    }

    function focusInitial(container) {
        if (!container) {
            return;
        }
        var radio = container.querySelector('input[type="radio"]');
        var btn = container.querySelector('button:not([disabled])');
        var target = radio || btn;
        if (target && typeof target.focus === 'function') {
            setTimeout(function () {
                try {
                    target.focus({ preventScroll: true });
                } catch (e) {
                    target.focus();
                }
            }, 0);
        }
    }

    function unpatchClose() {
        var api = resolveModalApi();
        if (!session || !session.patchedClose || !api || !session.originalClose) {
            return;
        }
        api.close = session.originalClose;
        session.patchedClose = false;
        session.originalClose = null;
    }

    function finishClose(fromSuccess) {
        var rootEl = getModalRoot();
        clearA11y(rootEl);
        unpatchClose();

        var onCompleted = session && session.onCompleted;
        var onDismiss = session && session.onDismiss;
        var completedOk = !!(session && session.completedOk);

        resetQuizCursor();
        if (session) {
            session.completedOk = false;
        }

        if (fromSuccess || completedOk) {
            if (typeof onCompleted === 'function') {
                onCompleted();
            }
        } else if (typeof onDismiss === 'function') {
            onDismiss();
        }

        restoreFocus();
    }

    function patchClose() {
        var api = resolveModalApi();
        if (!api || !session || session.patchedClose) {
            return;
        }
        session.originalClose = api.close.bind(api);
        session.patchedClose = true;
        api.close = function () {
            var wasSuccess = !!(session && session.completedOk);
            if (session && session.originalClose) {
                session.originalClose();
            }
            finishClose(wasSuccess);
        };
    }

    function createBodyShell() {
        var body = document.createElement('div');
        body.id = 'aa-training-completion-body';
        body.className = 'aa-training-completion-body space-y-4 max-h-[60vh] overflow-y-auto';
        return body;
    }

    function createFooterShell() {
        var footer = document.createElement('div');
        footer.id = 'aa-training-completion-footer';
        footer.className = 'aa-training-completion-footer flex flex-col sm:flex-row flex-wrap gap-2 w-full';
        return footer;
    }

    function renderQuestionView(body, footer) {
        var question = getQuestion(session.completionFlow, session.questionIndex);
        clearNode(body);
        clearNode(footer);

        if (!question) {
            session.phase = 'conclusion';
            renderConclusionView(body, footer);
            return;
        }

        session.phase = 'question';

        var prompt = document.createElement('p');
        prompt.id = 'aa-training-completion-prompt';
        prompt.className = 'text-sm font-medium text-gray-900';
        prompt.textContent = typeof question.prompt === 'string' ? question.prompt : '';
        body.appendChild(prompt);

        var fieldset = document.createElement('fieldset');
        fieldset.className = 'space-y-2';
        var legend = document.createElement('legend');
        legend.className = 'sr-only';
        legend.textContent = 'Opciones';
        fieldset.appendChild(legend);

        var options = Array.isArray(question.options) ? question.options : [];
        options.forEach(function (option, index) {
            if (!option || typeof option !== 'object') {
                return;
            }
            var optionKey = typeof option.option_key === 'string' ? option.option_key : '';
            var labelText = typeof option.label === 'string' ? option.label : '';
            var id = 'aa-training-completion-option-' + index;

            var label = document.createElement('label');
            label.className =
                'flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-3 text-sm text-gray-800 cursor-pointer hover:border-violet-300';
            label.setAttribute('for', id);

            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'aa-training-completion-option';
            input.id = id;
            input.value = optionKey;
            input.className = 'mt-0.5';
            if (session.selectedOptionKey === optionKey) {
                input.checked = true;
            }
            input.addEventListener('change', function () {
                session.selectedOptionKey = optionKey;
                updateContinueEnabled(footer);
            });

            var span = document.createElement('span');
            span.className = 'min-w-0 flex-1';
            span.textContent = labelText;

            label.appendChild(input);
            label.appendChild(span);
            fieldset.appendChild(label);
        });

        body.appendChild(fieldset);

        var continueBtn = document.createElement('button');
        continueBtn.type = 'button';
        continueBtn.className = PRIMARY_BTN;
        continueBtn.textContent = 'Continuar';
        continueBtn.disabled = !session.selectedOptionKey;
        continueBtn.setAttribute('data-aa-training-completion-continue', '1');
        continueBtn.addEventListener('click', function () {
            if (!session.selectedOptionKey) {
                return;
            }
            session.phase = 'feedback';
            paint();
        });
        footer.appendChild(continueBtn);
    }

    function updateContinueEnabled(footer) {
        var btn = footer.querySelector('[data-aa-training-completion-continue]');
        if (btn) {
            btn.disabled = !session.selectedOptionKey;
        }
    }

    function renderFeedbackView(body, footer) {
        var question = getQuestion(session.completionFlow, session.questionIndex);
        var option = findOption(question, session.selectedOptionKey);
        var feedback = option && option.feedback && typeof option.feedback === 'object'
            ? option.feedback
            : {};

        clearNode(body);
        clearNode(footer);
        session.phase = 'feedback';

        var title = document.createElement('h3');
        title.className = 'text-base font-semibold text-gray-900';
        title.textContent = typeof feedback.title === 'string' ? feedback.title : '';
        body.appendChild(title);

        var text = document.createElement('p');
        text.className = 'text-sm text-gray-700 whitespace-pre-wrap';
        text.textContent = typeof feedback.text === 'string' ? feedback.text : '';
        body.appendChild(text);

        var continueBtn = document.createElement('button');
        continueBtn.type = 'button';
        continueBtn.className = PRIMARY_BTN;
        continueBtn.textContent = 'Continuar';
        continueBtn.addEventListener('click', function () {
            var questions = getQuestions(session.completionFlow);
            var nextIndex = session.questionIndex + 1;
            session.selectedOptionKey = null;
            if (nextIndex < questions.length) {
                session.questionIndex = nextIndex;
                session.phase = 'question';
            } else {
                session.phase = 'conclusion';
            }
            paint();
        });
        footer.appendChild(continueBtn);
    }

    function renderConclusionView(body, footer) {
        var conclusion = session.completionFlow && session.completionFlow.conclusion
            ? session.completionFlow.conclusion
            : {};

        clearNode(body);
        clearNode(footer);
        if (session.phase !== 'submitting' && session.phase !== 'error') {
            session.phase = 'conclusion';
        }

        var title = document.createElement('h3');
        title.className = 'text-base font-semibold text-gray-900';
        title.textContent = typeof conclusion.title === 'string' ? conclusion.title : '';
        body.appendChild(title);

        var text = document.createElement('p');
        text.className = 'text-sm text-gray-700 whitespace-pre-wrap';
        text.textContent = typeof conclusion.text === 'string' ? conclusion.text : '';
        body.appendChild(text);

        if (session.errorCode) {
            var mapped = mapError(session.errorCode);
            var err = document.createElement('div');
            err.className = 'rounded-lg border border-red-200 bg-red-50 p-3 space-y-2';
            err.setAttribute('role', 'alert');

            var errText = document.createElement('p');
            errText.className = 'text-sm text-red-800';
            errText.textContent = mapped.text;
            err.appendChild(errText);

            if (mapped.showAccountLink && session.accountModuleUrl) {
                var link = document.createElement('a');
                link.href = String(session.accountModuleUrl);
                link.className = SECONDARY_BTN;
                link.textContent = 'Ir a Cuenta';
                err.appendChild(link);
            }

            body.appendChild(err);
        }

        var actionLabel = typeof conclusion.action_label === 'string' && conclusion.action_label
            ? conclusion.action_label
            : 'Completar lección';

        var actionBtn = document.createElement('button');
        actionBtn.type = 'button';
        actionBtn.className = PRIMARY_BTN;
        actionBtn.textContent = session.submitting
            ? 'Completando…'
            : (session.errorCode ? 'Reintentar' : actionLabel);
        actionBtn.disabled = !!session.submitting;
        actionBtn.setAttribute('data-aa-training-completion-submit', '1');
        actionBtn.addEventListener('click', function () {
            submitCompleted();
        });
        footer.appendChild(actionBtn);
    }

    function clearNode(node) {
        if (!node) {
            return;
        }
        node.innerHTML = '';
        if (Array.isArray(node.children)) {
            node.children.splice(0, node.children.length);
        }
    }

    function paint() {
        if (!session) {
            return;
        }
        var body = document.getElementById('aa-training-completion-body');
        var footer = document.getElementById('aa-training-completion-footer');
        if (!body || !footer) {
            return;
        }

        if (session.phase === 'feedback') {
            renderFeedbackView(body, footer);
        } else if (session.phase === 'conclusion' || session.phase === 'submitting' || session.phase === 'error') {
            renderConclusionView(body, footer);
        } else {
            renderQuestionView(body, footer);
        }

        focusInitial(getModalRoot());
    }

    function submitCompleted() {
        if (!session || session.submitting) {
            return;
        }

        var service = resolveService();
        if (!service) {
            session.errorCode = 'training_backend_error';
            session.phase = 'error';
            paint();
            return;
        }

        session.submitting = true;
        session.errorCode = null;
        session.phase = 'submitting';
        paint();

        var lessonKey = session.lessonKey;
        var requestToken = lessonKey + ':' + String(Date.now());

        Promise.resolve()
            .then(function () {
                return service.markCompleted(lessonKey);
            })
            .then(function () {
                if (!session || session.lessonKey !== lessonKey) {
                    return;
                }
                session.submitting = false;
                session.completedOk = true;
                var api = resolveModalApi();
                if (api) {
                    api.close();
                } else {
                    finishClose(true);
                }
            })
            .catch(function (err) {
                if (!session || session.lessonKey !== lessonKey) {
                    return;
                }
                session.submitting = false;
                session.errorCode = err && typeof err.code === 'string' ? err.code : 'training_network_error';
                session.phase = 'error';
                paint();
            });

        return requestToken;
    }

    /**
     * @param {{
     *   lessonKey: string,
     *   completionFlow: object,
     *   accountModuleUrl?: string,
     *   onCompleted?: Function,
     *   onDismiss?: Function,
     *   returnFocusEl?: HTMLElement|null
     * }} options
     */
    function open(options) {
        var api = resolveModalApi();
        if (!api) {
            console.error('[TrainingCompletionModal] AAAdmin.modal not found');
            return;
        }

        if (!options || typeof options.lessonKey !== 'string' || !options.lessonKey) {
            return;
        }
        if (!options.completionFlow || typeof options.completionFlow !== 'object') {
            return;
        }

        if (api.isOpen && api.isOpen()) {
            api.close();
        }

        session = {
            lessonKey: options.lessonKey,
            completionFlow: options.completionFlow,
            accountModuleUrl: typeof options.accountModuleUrl === 'string' ? options.accountModuleUrl : '',
            onCompleted: typeof options.onCompleted === 'function' ? options.onCompleted : null,
            onDismiss: typeof options.onDismiss === 'function' ? options.onDismiss : null,
            returnFocusEl: options.returnFocusEl || null,
            questionIndex: 0,
            selectedOptionKey: null,
            phase: 'question',
            submitting: false,
            completedOk: false,
            errorCode: null,
            patchedClose: false,
            originalClose: null,
            keydownHandler: null
        };

        var titleId = 'aa-training-completion-title';
        var title = document.createElement('span');
        title.id = titleId;
        title.textContent = 'Completar lección';

        var body = createBodyShell();
        var footer = createFooterShell();

        patchClose();
        api.open({
            title: title,
            body: body,
            footer: footer
        });

        applyA11y(getModalRoot(), titleId);
        paint();
    }

    function close() {
        var api = resolveModalApi();
        if (api && api.isOpen && api.isOpen()) {
            api.close();
            return;
        }
        finishClose(false);
    }

    function _getSessionForTests() {
        return session;
    }

    function _resetForTests() {
        unpatchClose();
        clearA11y(getModalRoot());
        session = null;
    }

    var api = {
        open: open,
        close: close,
        _getSessionForTests: _getSessionForTests,
        _resetForTests: _resetForTests,
        _submitCompletedForTests: submitCompleted,
        _paintForTests: paint
    };

    root.TrainingCompletionModal = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
