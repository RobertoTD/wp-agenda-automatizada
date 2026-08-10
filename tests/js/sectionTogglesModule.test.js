'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/section-toggles-module.js');
const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
const adminSourceCssPath = path.join(__dirname, '../../includes/admin/ui/assets/css/admin.source.css');
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const indexSrc = fs.readFileSync(indexPath, 'utf8');

function makeClassList(initialClasses) {
    var classes = Array.isArray(initialClasses) ? initialClasses.slice() : [];

    return {
        classes: classes,
        add: function (cls) {
            if (classes.indexOf(cls) === -1) {
                classes.push(cls);
            }
            this.classes = classes;
        },
        remove: function (cls) {
            classes = classes.filter(function (item) {
                return item !== cls;
            });
            this.classes = classes;
        },
        toggle: function (cls, force) {
            var has = classes.indexOf(cls) !== -1;
            var next = typeof force === 'boolean' ? force : !has;

            if (next && !has) {
                classes.push(cls);
            }

            if (!next && has) {
                classes = classes.filter(function (item) {
                    return item !== cls;
                });
            }

            this.classes = classes;
            return next;
        },
        contains: function (cls) {
            return classes.indexOf(cls) !== -1;
        }
    };
}

function makeElement(tag, options) {
    var opts = options || {};
    var attributes = Object.assign({}, opts.attributes || {});

    var element = {
        tagName: tag,
        id: opts.id || '',
        classList: opts.classList || makeClassList(opts.classes || []),
        attributes: attributes,
        dataset: {},
        listeners: {},
        inert: false,
        addEventListener: function (type, handler) {
            this.listeners[type] = this.listeners[type] || [];
            this.listeners[type].push(handler);
        },
        setAttribute: function (name, value) {
            this.attributes[name] = String(value);
        },
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
        },
        removeAttribute: function (name) {
            delete this.attributes[name];
        }
    };

    Object.keys(attributes).forEach(function (name) {
        element.setAttribute(name, attributes[name]);
    });

    return element;
}

function buildDom(options) {
    var opts = options || {};

    var execCollapsed = opts.execCollapsed !== false;

    var execBodyClasses = execCollapsed
        ? ['aa-executive-body', 'is-collapsed']
        : ['aa-executive-body'];
    var execBodyAttrs = execCollapsed
        ? { 'aria-hidden': 'true', inert: '' }
        : { 'aria-hidden': 'false' };
    var execToggleAttrs = {
        'aria-expanded': execCollapsed ? 'false' : 'true',
        'aria-controls': 'aa-executive-body'
    };

    var listsBody = makeElement('div', {
        id: 'aa-lists-body',
        classes: ['aa-lists-body']
    });
    var execToggle = makeElement('button', {
        id: 'aa-executive-header-toggle',
        attributes: execToggleAttrs
    });
    var execBody = makeElement('div', {
        id: 'aa-executive-body',
        classes: execBodyClasses,
        attributes: execBodyAttrs
    });

    var elements = {
        'aa-lists-body': listsBody,
        'aa-executive-header-toggle': execToggle,
        'aa-executive-body': execBody
    };

    var documentMock = {
        readyState: 'complete',
        getElementById: function (id) {
            return elements[id] || null;
        },
        addEventListener: function () {}
    };

    return {
        document: documentMock,
        listsBody: listsBody,
        execToggle: execToggle,
        execBody: execBody
    };
}

function buildPartialDom(options) {
    var opts = options || {};
    var elements = {};

    if (opts.includeListsBody) {
        elements['aa-lists-body'] = makeElement('div', {
            id: 'aa-lists-body',
            classes: ['aa-lists-body']
        });
    }

    if (opts.includeExec) {
        var execToggle = makeElement('button', {
            id: 'aa-executive-header-toggle',
            attributes: { 'aria-expanded': 'false', 'aria-controls': 'aa-executive-body' }
        });
        var execBody = makeElement('div', {
            id: 'aa-executive-body',
            classes: ['aa-executive-body', 'is-collapsed'],
            attributes: { 'aria-hidden': 'true', inert: '' }
        });
        elements['aa-executive-header-toggle'] = execToggle;
        elements['aa-executive-body'] = execBody;
    }

    var documentMock = {
        readyState: 'complete',
        getElementById: function (id) {
            return elements[id] || null;
        },
        addEventListener: function () {}
    };

    return {
        document: documentMock,
        elements: elements
    };
}

function loadModule(dom) {
    var context = {
        window: {},
        console: console,
        document: dom.document,
        module: { exports: {} }
    };

    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    return {
        exports: context.module.exports,
        context: context
    };
}

function clickToggle(toggle) {
    (toggle.listeners['click'] || []).forEach(function (handler) {
        handler();
    });
}

describe('section-toggles-module — toggles de secciones', () => {
    it('index.php no contiene data-work-zone', () => {
        assert.doesNotMatch(indexSrc, /data-work-zone/);
    });

    it('index.php conserva body de listas siempre visible; tools viven en shell header', () => {
        assert.doesNotMatch(indexSrc, /id="aa-lists-header"/);
        assert.match(indexSrc, /id="aa-lists-body"/);
        assert.doesNotMatch(indexSrc, /id="aa-lists-area-tools"/);
        assert.doesNotMatch(indexSrc, /id="aa-lists-header-toggle"/);
        assert.doesNotMatch(indexSrc, /aa-lists-header-chevron/);
        assert.doesNotMatch(indexSrc, /Listas de tareas/);
    });

    it('CSS no contiene reglas de opacidad del ejecutor ni is-muted', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.doesNotMatch(css, /#aa-executive-proposal\.is-muted/);
        assert.doesNotMatch(css, /#aa-executive-proposal\s*\{[^}]*transition:\s*opacity/);
        assert.doesNotMatch(css, /#aa-lists-header-toggle/);
    });

    // --- Organizer always visible ---
    it('organizador no tiene toggle y el body permanece expandido', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.document.getElementById('aa-lists-header-toggle'), null);
        assert.equal(moduleSrc.includes('aa-lists-header-toggle'), false);
    });

    // --- Executive initial state ---
    it('ejecutor inicia colapsado', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
    });

    it('ejecutor tiene aria-hidden=true inicial', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.execBody.getAttribute('aria-hidden'), 'true');
    });

    it('ejecutor tiene inert inicial', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.execBody.getAttribute('inert'), '');
    });

    // --- Executive toggle behavior ---
    it('clic en toggle ejecutivo expande solo el ejecutor', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);

        assert.equal(dom.execBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.execBody.inert, false);
        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
    });

    it('segundo clic en toggle ejecutivo vuelve a colapsarlo', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);
        clickToggle(dom.execToggle);

        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.execBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.execBody.inert, true);
    });

    // --- Independence ---
    it('abrir el ejecutor no colapsa las listas', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
    });

    it('módulo solo registra el par ejecutivo', () => {
        var loaded = loadModule(buildDom({ execCollapsed: true }));

        assert.equal(loaded.exports.PAIRS.length, 1);
        assert.equal(loaded.exports.PAIRS[0].toggleId, 'aa-executive-header-toggle');
        assert.equal(loaded.exports.PAIRS[0].bodyId, 'aa-executive-body');
    });

    // --- Accessibility sync ---
    it('atributos accesibles permanecen sincronizados tras ciclos', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.execBody.inert, false);

        clickToggle(dom.execToggle);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.execBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.execBody.inert, true);
    });

    // --- Idempotent binding ---
    it('inicializar dos veces no duplica listeners', () => {
        var dom = buildDom({ execCollapsed: true });
        var loaded = loadModule(dom);

        var execBefore = (dom.execToggle.listeners['click'] || []).length;

        loaded.exports.bind();
        loaded.exports.bind();

        assert.equal((dom.execToggle.listeners['click'] || []).length, execBefore);
    });

    // --- Partial DOM resilience ---
    it('ausencia del body de listas no rompe el toggle ejecutivo', () => {
        var partial = buildPartialDom({ includeListsBody: false, includeExec: true });
        loadModule(partial);

        var execToggle = partial.elements['aa-executive-header-toggle'];
        var execBody = partial.elements['aa-executive-body'];

        clickToggle(execToggle);
        assert.equal(execBody.classList.contains('is-collapsed'), false);
        assert.equal(execToggle.getAttribute('aria-expanded'), 'true');

        clickToggle(execToggle);
        assert.equal(execBody.classList.contains('is-collapsed'), true);
    });

    it('ausencia del toggle del organizador no rompe el toggle ejecutivo', () => {
        var partial = buildPartialDom({ includeListsBody: true, includeExec: true });
        loadModule(partial);

        var execToggle = partial.elements['aa-executive-header-toggle'];
        var execBody = partial.elements['aa-executive-body'];

        clickToggle(execToggle);
        assert.equal(execBody.classList.contains('is-collapsed'), false);
        assert.equal(execToggle.getAttribute('aria-expanded'), 'true');

        clickToggle(execToggle);
        assert.equal(execBody.classList.contains('is-collapsed'), true);
    });

    // --- Internal clicks don't trigger toggle ---
    it('clic en botones internos del ejecutor no cambia el estado', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.execBody.listeners['click'], undefined);
        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
    });

    // --- No scroll/wheel/focusin ---
    it('scroll, wheel y focusin siguen sin cambiar estados', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.listsBody.listeners['wheel'], undefined);
        assert.equal(dom.listsBody.listeners['focusin'], undefined);
        assert.equal(dom.execBody.listeners['wheel'], undefined);
        assert.equal(dom.execBody.listeners['focusin'], undefined);
    });

    // --- No opacity / is-muted ---
    it('no se aplica is-muted a ninguna sección', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);

        assert.equal(dom.execBody.classList.contains('is-muted'), false);
        assert.equal(dom.listsBody.classList.contains('is-muted'), false);
        assert.doesNotMatch(moduleSrc, /is-muted/);
    });

    // --- No data-work-zone ---
    it('no se usa data-work-zone', () => {
        assert.doesNotMatch(moduleSrc, /data-work-zone/);
        assert.doesNotMatch(moduleSrc, /workZone/);
    });

    // --- Module does not register window listeners ---
    it('módulo no registra listeners en window', () => {
        var windowListeners = {};
        var dom = buildDom({ execCollapsed: true });

        var context = {
            window: {},
            console: console,
            document: dom.document,
            module: { exports: {} },
            addEventListener: function (type) {
                windowListeners[type] = true;
            }
        };
        context.window = context;
        context.globalThis = context;

        vm.runInNewContext(moduleSrc, context, { filename: modulePath });

        assert.equal(windowListeners['scroll'], undefined);
        assert.equal(windowListeners['wheel'], undefined);
        assert.equal(windowListeners['resize'], undefined);
    });

    // --- Absence of all nodes doesn't crash ---
    it('la ausencia de todos los nodos no produce una excepción fatal', () => {
        var emptyDoc = {
            readyState: 'complete',
            getElementById: function () { return null; },
            addEventListener: function () {}
        };

        var context = {
            window: {},
            console: console,
            document: emptyDoc,
            module: { exports: {} }
        };
        context.window = context;
        context.globalThis = context;

        assert.doesNotThrow(function () {
            vm.runInNewContext(moduleSrc, context, { filename: modulePath });
        });
    });

    // --- Index.php structure checks (Cycle B + C) ---
    it('Cycle B: index.php define aa-lists-section antes de aa-executive-proposal', () => {
        var listsPos = indexSrc.indexOf('id="aa-lists-section"');
        var execPos = indexSrc.indexOf('id="aa-executive-proposal"');

        assert.ok(listsPos !== -1, 'aa-lists-section debe existir');
        assert.ok(execPos !== -1, 'aa-executive-proposal debe existir');
        assert.ok(listsPos < execPos, 'aa-lists-section debe aparecer antes');
    });

    it('Cycle B: no existe toggle del organizador', () => {
        assert.doesNotMatch(indexSrc, /id="aa-lists-header-toggle"/);
    });

    it('Cycle B: aa-lists-body inicia sin is-collapsed ni aria-hidden', () => {
        assert.match(indexSrc, /id="aa-lists-body"\s+class="aa-lists-body"/);
        assert.doesNotMatch(indexSrc, /id="aa-lists-body"[^>]*aria-hidden/);
        assert.doesNotMatch(indexSrc, /id="aa-lists-body"[^>]*\binert\b/);
    });

    it('Cycle B: no existe divisor .aa-executive-lists-divider', () => {
        assert.doesNotMatch(indexSrc, /aa-executive-lists-divider/);
    });

    it('Cycle B: aa-tasks-module-root tiene pb-24', () => {
        assert.match(indexSrc, /id="aa-tasks-module-root"[^>]*pb-24/);
    });

    // --- Cycle C structure checks ---
    it('Cycle C: existe #aa-executive-section-header', () => {
        assert.match(indexSrc, /id="aa-executive-section-header"/);
    });

    it('Cycle C: existe #aa-executive-header-toggle con aria-expanded=false', () => {
        assert.match(indexSrc, /id="aa-executive-header-toggle"[^>]*aria-expanded="false"/);
    });

    it('Cycle C: toggle ejecutivo controla aa-executive-body', () => {
        assert.match(indexSrc, /id="aa-executive-header-toggle"[^>]*aria-controls="aa-executive-body"/);
    });

    it('Cycle C: texto del toggle es aria-label Ejecutar', () => {
        assert.match(indexSrc, /id="aa-executive-header-toggle"[^>]*aria-label="Ejecutar"/);
    });

    it('Cycle C: existe #aa-executive-body con is-collapsed', () => {
        assert.match(indexSrc, /id="aa-executive-body"[^>]*class="aa-executive-body is-collapsed"/);
    });

    it('Cycle C: #aa-executive-body tiene aria-hidden=true', () => {
        assert.match(indexSrc, /id="aa-executive-body"[^>]*aria-hidden="true"/);
    });

    it('Cycle C: #aa-executive-body tiene inert', () => {
        assert.match(indexSrc, /id="aa-executive-body"[^>]*\binert\b/);
    });

    it('Cycle C: no hay título ejecutivo visible duplicado', () => {
        assert.doesNotMatch(indexSrc, /<h3[^>]*>Propuesta ejecutiva<\/h3>/);
    });

    it('Cycle C: propuesta ejecutiva conserva nodos clave', () => {
        assert.match(indexSrc, /id="aa-executive-status"/);
        assert.match(indexSrc, /id="aa-executive-header-actions"/);
        assert.match(indexSrc, /id="aa-executive-list"/);
    });

    it('Cycle C: no queda chevron del organizador', () => {
        assert.doesNotMatch(indexSrc, /aa-lists-header-chevron/);
    });

    it('Cycle C: toggle ejecutivo usa icono play sin chevron de sección', () => {
        assert.doesNotMatch(indexSrc, /aa-executive-header-chevron/);
        assert.match(indexSrc, /id="aa-executive-header-toggle"[\s\S]*?M8 5v14l11-7z/);
    });

    // --- Cycle D: chevron rotation & cleanup ---
    it('Cycle D: CSS no referencia chevrons de header de listas', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');
        assert.doesNotMatch(css, /#aa-lists-header-toggle/);
        assert.doesNotMatch(css, /aa-lists-header-chevron/);
    });

    it('Cycle 2A: aria-expanded inicial de header ejecutivo intacto', () => {
        assert.match(indexSrc, /id="aa-executive-header-toggle"[\s\S]*?aria-expanded="false"/);
        assert.match(indexSrc, /id="aa-executive-header-toggle"[\s\S]*?aria-controls="aa-executive-body"/);
        assert.doesNotMatch(indexSrc, /id="aa-lists-header-toggle"/);
    });

    it('Cycle D: rotación no depende de JavaScript adicional', () => {
        var src = fs.readFileSync(modulePath, 'utf8');
        assert.doesNotMatch(src, /rotate/);
        assert.doesNotMatch(src, /transform/);
        assert.doesNotMatch(src, /chevron/);
    });

    it('Cycle D: abrir ejecutor no introduce toggle de listas', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.document.getElementById('aa-lists-header-toggle'), null);
    });

    it('Cycle D: módulo fue renombrado a section-toggles-module.js', () => {
        assert.match(indexSrc, /section-toggles-module\.js/);
        assert.doesNotMatch(indexSrc, /executive-lists-focus-module\.js/);
    });

    it('Cycle D: el toggle ejecutivo sigue sincronizando atributos accesibles', () => {
        var dom = buildDom({ execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.execBody.inert, false);

        clickToggle(dom.execToggle);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.execBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.execBody.inert, true);
    });

    // --- Cycle E: isolation from data ---
    it('Cycle E: módulo de toggles no conoce datos de propuesta ni summary', () => {
        assert.doesNotMatch(moduleSrc, /summary/i);
        assert.doesNotMatch(moduleSrc, /proposal/i);
        assert.doesNotMatch(moduleSrc, /updateHeaderSummary/);
        assert.doesNotMatch(moduleSrc, /syncHeaderSummary/);
        assert.doesNotMatch(moduleSrc, /resolveCurrentTask/);
        assert.doesNotMatch(moduleSrc, /textContent/);
    });

    it('Cycle E: header ejecutivo compacto sin summary/label', () => {
        assert.doesNotMatch(indexSrc, /id="aa-executive-header-summary"/);
        assert.doesNotMatch(indexSrc, /aa-executive-header-label/);
    });
});
