'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it, beforeEach, afterEach } = require('node:test');

const uxPath = path.join(__dirname, '../../assets/js/services/trainingPortalUx.js');
const ux = require(uxPath);
const modalPath = path.join(
    __dirname,
    '../../includes/admin/ui/modals/training-completion/trainingCompletionModal.js'
);
const modalSrc = fs.readFileSync(modalPath, 'utf8');
const moduleSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/training/module.js'),
    'utf8'
);
const layoutSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/shared/layout.php'),
    'utf8'
);

function el(id, extras) {
    const node = {
        id: id,
        classList: {
            _hidden: true,
            toggle: function () {},
            add: function (cls) {
                if (cls === 'hidden') {
                    this._hidden = true;
                }
            },
            remove: function (cls) {
                if (cls === 'hidden') {
                    this._hidden = false;
                }
            },
            contains: function (cls) {
                return cls === 'hidden' ? this._hidden : false;
            }
        },
        style: {},
        children: [],
        attributes: {},
        textContent: '',
        innerHTML: '',
        disabled: false,
        checked: false,
        value: '',
        type: '',
        name: '',
        href: '',
        offsetParent: {},
        setAttribute: function (k, v) {
            this.attributes[k] = String(v);
        },
        getAttribute: function (k) {
            return Object.prototype.hasOwnProperty.call(this.attributes, k)
                ? this.attributes[k]
                : null;
        },
        removeAttribute: function (k) {
            delete this.attributes[k];
        },
        appendChild: function (child) {
            this.children.push(child);
            return child;
        },
        querySelector: function (sel) {
            return findBySelector(this, sel);
        },
        querySelectorAll: function (sel) {
            const out = [];
            collectBySelector(this, sel, out);
            return out;
        },
        addEventListener: function (type, handler) {
            this._listeners = this._listeners || {};
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        removeEventListener: function (type, handler) {
            if (!this._listeners || !this._listeners[type]) {
                return;
            }
            this._listeners[type] = this._listeners[type].filter(function (h) {
                return h !== handler;
            });
        },
        focus: function () {
            this._focused = true;
        },
        click: function () {
            const handlers = (this._listeners && this._listeners.click) || [];
            handlers.forEach(function (h) {
                h({ preventDefault: function () {} });
            });
        }
    };
    return Object.assign(node, extras || {});
}

function findBySelector(node, sel) {
    const all = [];
    collectBySelector(node, sel, all);
    return all[0] || null;
}

function collectBySelector(node, sel, out) {
    if (!node) {
        return;
    }
    if (matches(node, sel)) {
        out.push(node);
    }
    (node.children || []).forEach(function (child) {
        collectBySelector(child, sel, out);
    });
}

function matches(node, sel) {
    if (sel === 'button:not([disabled])') {
        return (node.tagName === 'BUTTON' || node.id === 'button') && !node.disabled;
    }
    if (sel === 'input[type="radio"]') {
        return node.type === 'radio';
    }
    if (sel.indexOf('[data-aa-training-completion-continue]') === 0) {
        return node.getAttribute && node.getAttribute('data-aa-training-completion-continue') === '1';
    }
    if (sel.indexOf('[data-aa-training-completion-submit]') === 0) {
        return node.getAttribute && node.getAttribute('data-aa-training-completion-submit') === '1';
    }
    if (sel.indexOf('button:not([disabled]),') === 0) {
        return (
            ((node.tagName === 'BUTTON' || node.id === 'button') && !node.disabled)
            || node.type === 'radio'
            || (node.getAttribute && node.getAttribute('tabindex') != null)
        );
    }
    return false;
}

function createDom() {
    const root = el('aa-modal-root');
    const title = el('aa-modal-title');
    const body = el('aa-modal-body');
    const footer = el('aa-modal-footer');
    const overlay = el('aa-modal-overlay');
    const modal = el('aa-modal');
    modal.children = [el('aa-modal-header', { children: [title] }), body, footer];
    root.children = [overlay, modal];
    root.querySelector = function (sel) {
        if (sel === '.aa-modal-overlay') return overlay;
        if (sel === '.aa-modal') return modal;
        if (sel === '.aa-modal-title') return title;
        if (sel === '.aa-modal-body') return body;
        if (sel === '.aa-modal-footer') return footer;
        return findBySelector(root, sel);
    };
    return { root: root, title: title, body: body, footer: footer };
}

function loadModal(markCompletedImpl) {
    const dom = createDom();
    let isOpen = false;
    let openCalls = 0;

    globalThis.document = {
        getElementById: function (id) {
            if (id === 'aa-modal-root') {
                return dom.root;
            }
            return findBySelector(dom.root, '#' + id) || findDeepId(dom.root, id);
        },
        createElement: function (tag) {
            return el(tag, { tagName: String(tag).toUpperCase() });
        },
        activeElement: null,
        addEventListener: function () {}
    };

    function findDeepId(node, id) {
        if (!node) return null;
        if (node.id === id) return node;
        const kids = node.children || [];
        for (let i = 0; i < kids.length; i += 1) {
            const found = findDeepId(kids[i], id);
            if (found) return found;
        }
        return null;
    }

    // After open, body/footer content is appended to aa-modal-body/footer; getElementById must find nested ids.
    const originalGet = globalThis.document.getElementById;
    globalThis.document.getElementById = function (id) {
        if (id === 'aa-modal-root') {
            return dom.root;
        }
        return findDeepId(dom.body, id) || findDeepId(dom.footer, id) || findDeepId(dom.root, id);
    };

    globalThis.AAAdmin = {
        modal: {
            open: function (options) {
                openCalls += 1;
                isOpen = true;
                dom.root.classList.remove('hidden');
                if (options.title) {
                    dom.title.innerHTML = '';
                    dom.title.children = [];
                    if (typeof options.title === 'object') {
                        dom.title.appendChild(options.title);
                    }
                }
                if (options.body) {
                    dom.body.innerHTML = '';
                    dom.body.children = [];
                    dom.body.appendChild(options.body);
                }
                if (options.footer) {
                    dom.footer.innerHTML = '';
                    dom.footer.children = [];
                    dom.footer.appendChild(options.footer);
                }
            },
            close: function () {
                isOpen = false;
                dom.root.classList.add('hidden');
                dom.title.innerHTML = '';
                dom.title.children = [];
                dom.body.innerHTML = '';
                dom.body.children = [];
                dom.footer.innerHTML = '';
                dom.footer.children = [];
            },
            isOpen: function () {
                return isOpen;
            }
        }
    };

    globalThis.TrainingPortalUx = ux;
    globalThis.TrainingService = {
        markCompleted: markCompletedImpl || function () {
            return Promise.resolve({ success: true, data: {} });
        }
    };

    delete require.cache[modalPath];
    const modal = require(modalPath);
    modal._resetForTests();

    return {
        modal: modal,
        dom: dom,
        getOpenCalls: function () {
            return openCalls;
        },
        isOpen: function () {
            return isOpen;
        }
    };
}

function sampleFlow() {
    return {
        trigger_label: 'Completar lección',
        questions: [
            {
                question_key: 'q1',
                type: 'single_choice_feedback',
                prompt: '¿Pregunta uno?',
                options: [
                    {
                        option_key: 'a',
                        label: 'Opción A',
                        feedback: { title: 'Bien A', text: 'Feedback A' }
                    },
                    {
                        option_key: 'b',
                        label: 'Opción B',
                        feedback: { title: 'Bien B', text: 'Feedback B' }
                    }
                ]
            },
            {
                question_key: 'q2',
                type: 'single_choice_feedback',
                prompt: '¿Pregunta dos?',
                options: [
                    {
                        option_key: 'c',
                        label: 'Opción C',
                        feedback: { title: 'Ok C', text: 'Feedback C' }
                    },
                    {
                        option_key: 'd',
                        label: 'Opción D',
                        feedback: { title: 'Ok D', text: 'Feedback D' }
                    }
                ]
            }
        ],
        conclusion: {
            title: 'Listo',
            text: 'Has terminado.',
            action_label: 'Completar lección'
        }
    };
}

function flush() {
    return new Promise(function (resolve) {
        setImmediate(resolve);
    });
}

function clickContinue(dom) {
    const btn = findBySelector(dom.footer, '[data-aa-training-completion-continue]')
        || findBySelector(dom.footer, 'button:not([disabled])');
    assert.ok(btn);
    btn.click();
}

function selectFirstRadio(dom) {
    const radios = [];
    collectBySelector(dom.body, 'input[type="radio"]', radios);
    assert.ok(radios.length > 0);
    radios[0].checked = true;
    const handlers = (radios[0]._listeners && radios[0]._listeners.change) || [];
    handlers.forEach(function (h) {
        h();
    });
    return radios[0].value;
}

describe('TrainingCompletionModal C9A5b', () => {
    afterEach(() => {
        if (globalThis.TrainingCompletionModal && globalThis.TrainingCompletionModal._resetForTests) {
            globalThis.TrainingCompletionModal._resetForTests();
        }
        delete globalThis.document;
        delete globalThis.AAAdmin;
        delete globalThis.TrainingService;
        delete globalThis.TrainingPortalUx;
        delete globalThis.TrainingCompletionModal;
        delete require.cache[modalPath];
    });

    it('usa AAAdmin.modal y no crea overlay paralelo', () => {
        assert.match(modalSrc, /AAAdmin\.modal/);
        assert.match(modalSrc, /aa-modal-root/);
        assert.doesNotMatch(modalSrc, /createElement\(['"]div['"]\).*overlay/i);
        assert.match(layoutSrc, /training-completion\/trainingCompletionModal\.js/);
        assert.match(layoutSrc, /training-completion\/index\.php/);
        assert.doesNotMatch(moduleSrc, /aa-modal-overlay/);
    });

    it('CTA helper solo con completion_flow pendiente', () => {
        assert.equal(
            ux.mapLessonCompletionFooter({
                lessonMeta: { progress: { completed: false } },
                completion_flow: { trigger_label: 'Completar lección' }
            }).mode,
            'cta'
        );
        assert.equal(
            ux.mapLessonCompletionFooter({
                lessonMeta: { progress: { completed: true } },
                completion_flow: { trigger_label: 'Completar lección' }
            }).mode,
            'completed'
        );
    });

    it('pregunta exige selección; feedback por opción; varias preguntas', async () => {
        const ctx = loadModal();
        ctx.modal.open({
            lessonKey: 'bienvenida',
            completionFlow: sampleFlow()
        });
        assert.equal(ctx.getOpenCalls(), 1);
        assert.equal(ctx.modal._getSessionForTests().phase, 'question');

        let continueBtn = findBySelector(ctx.dom.footer, '[data-aa-training-completion-continue]');
        assert.equal(continueBtn.disabled, true);

        selectFirstRadio(ctx.dom);
        continueBtn = findBySelector(ctx.dom.footer, '[data-aa-training-completion-continue]');
        assert.equal(continueBtn.disabled, false);
        clickContinue(ctx.dom);

        assert.equal(ctx.modal._getSessionForTests().phase, 'feedback');
        const feedbackTitle = ctx.dom.body.children[0].children[0];
        assert.match(feedbackTitle.textContent, /Bien A/);

        clickContinue(ctx.dom);
        assert.equal(ctx.modal._getSessionForTests().phase, 'question');
        assert.equal(ctx.modal._getSessionForTests().questionIndex, 1);

        selectFirstRadio(ctx.dom);
        clickContinue(ctx.dom);
        clickContinue(ctx.dom);
        assert.equal(ctx.modal._getSessionForTests().phase, 'conclusion');
    });

    it('cierre anticipado no completa y reinicia', async () => {
        let completed = 0;
        let markCalls = 0;
        const ctx = loadModal(function () {
            markCalls += 1;
            return Promise.resolve({ success: true, data: {} });
        });

        ctx.modal.open({
            lessonKey: 'bienvenida',
            completionFlow: sampleFlow(),
            onCompleted: function () {
                completed += 1;
            }
        });
        selectFirstRadio(ctx.dom);
        clickContinue(ctx.dom);
        assert.equal(ctx.modal._getSessionForTests().phase, 'feedback');

        globalThis.AAAdmin.modal.close();
        await flush();
        assert.equal(markCalls, 0);
        assert.equal(completed, 0);

        ctx.modal.open({
            lessonKey: 'bienvenida',
            completionFlow: sampleFlow()
        });
        assert.equal(ctx.modal._getSessionForTests().questionIndex, 0);
        assert.equal(ctx.modal._getSessionForTests().phase, 'question');
        assert.equal(ctx.modal._getSessionForTests().selectedOptionKey, null);
    });

    it('botón final llama markCompleted una vez; retry tras error; éxito cierra', async () => {
        let markCalls = 0;
        let failOnce = true;
        let completed = 0;
        const ctx = loadModal(function () {
            markCalls += 1;
            if (failOnce) {
                failOnce = false;
                return Promise.reject({ code: 'training_network_error' });
            }
            return Promise.resolve({
                success: true,
                data: { lesson_key: 'bienvenida', progress: { opened: true, completed: true } }
            });
        });

        ctx.modal.open({
            lessonKey: 'bienvenida',
            completionFlow: {
                trigger_label: 'Completar',
                questions: [
                    {
                        question_key: 'q1',
                        prompt: 'P',
                        options: [
                            {
                                option_key: 'a',
                                label: 'A',
                                feedback: { title: 'T', text: 'X' }
                            },
                            {
                                option_key: 'b',
                                label: 'B',
                                feedback: { title: 'T2', text: 'Y' }
                            }
                        ]
                    }
                ],
                conclusion: {
                    title: 'Fin',
                    text: 'Ok',
                    action_label: 'Completar lección'
                }
            },
            onCompleted: function () {
                completed += 1;
            }
        });

        selectFirstRadio(ctx.dom);
        clickContinue(ctx.dom);
        clickContinue(ctx.dom);
        assert.equal(ctx.modal._getSessionForTests().phase, 'conclusion');

        const submit = findBySelector(ctx.dom.footer, '[data-aa-training-completion-submit]');
        submit.click();
        await flush();
        assert.equal(markCalls, 1);
        assert.equal(ctx.modal._getSessionForTests().phase, 'error');
        assert.equal(completed, 0);

        const retry = findBySelector(ctx.dom.footer, '[data-aa-training-completion-submit]');
        retry.click();
        await flush();
        assert.equal(markCalls, 2);
        assert.equal(completed, 1);
        assert.equal(ctx.isOpen(), false);
    });

    it('no envía option_key al backend', async () => {
        let payload = null;
        const ctx = loadModal(function (lessonKey) {
            payload = { lessonKey: lessonKey, args: arguments.length };
            return Promise.resolve({ success: true, data: {} });
        });

        ctx.modal.open({
            lessonKey: 'bienvenida',
            completionFlow: {
                trigger_label: 'C',
                questions: [
                    {
                        question_key: 'q1',
                        prompt: 'P',
                        options: [
                            {
                                option_key: 'solo-recordar',
                                label: 'A',
                                feedback: { title: 'T', text: 'X' }
                            },
                            {
                                option_key: 'b',
                                label: 'B',
                                feedback: { title: 'T2', text: 'Y' }
                            }
                        ]
                    }
                ],
                conclusion: { title: 'F', text: 'T', action_label: 'Completar lección' }
            }
        });
        selectFirstRadio(ctx.dom);
        clickContinue(ctx.dom);
        clickContinue(ctx.dom);
        findBySelector(ctx.dom.footer, '[data-aa-training-completion-submit]').click();
        await flush();
        assert.deepEqual(payload, { lessonKey: 'bienvenida', args: 1 });
        assert.doesNotMatch(modalSrc, /option_key.*markCompleted|markCompleted\([^)]*option/);
    });

    it('mapCompletionError sin códigos técnicos', () => {
        assert.equal(
            ux.mapCompletionError({ code: 'training_content_lesson_locked' }).text.indexOf('training_'),
            -1
        );
        assert.equal(
            ux.mapCompletionError({ code: 'training_enrollment_not_active' }).showAccountLink,
            true
        );
        assert.equal(
            ux.mapCompletionError({ code: 'training_content_completion_flow_missing' }).retry,
            false
        );
    });

    it('aplica role=dialog y aria-modal', () => {
        const ctx = loadModal();
        ctx.modal.open({
            lessonKey: 'bienvenida',
            completionFlow: sampleFlow()
        });
        assert.equal(ctx.dom.root.getAttribute('role'), 'dialog');
        assert.equal(ctx.dom.root.getAttribute('aria-modal'), 'true');
        assert.ok(ctx.dom.root.getAttribute('aria-labelledby'));
    });
});

describe('Training module C9A5b refresh hook', () => {
    it('afterLessonCompleted invalida y usa loadCourse', () => {
        assert.match(moduleSrc, /function afterLessonCompleted/);
        assert.match(moduleSrc, /loadCourse\(\)/);
        assert.match(moduleSrc, /onCompleted:\s*function\s*\(\)\s*\{\s*afterLessonCompleted/);
    });
});
