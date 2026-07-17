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

    var listsCollapsed = opts.listsCollapsed === true;
    var execCollapsed = opts.execCollapsed !== false;

    var listsBodyClasses = listsCollapsed
        ? ['aa-lists-body', 'is-collapsed']
        : ['aa-lists-body'];
    var listsBodyAttrs = listsCollapsed
        ? { 'aria-hidden': 'true', inert: '' }
        : { 'aria-hidden': 'false' };
    var listsToggleAttrs = {
        'aria-expanded': listsCollapsed ? 'false' : 'true',
        'aria-controls': 'aa-lists-body'
    };

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

    var listsToggle = makeElement('button', {
        id: 'aa-lists-header-toggle',
        attributes: listsToggleAttrs
    });
    var listsBody = makeElement('div', {
        id: 'aa-lists-body',
        classes: listsBodyClasses,
        attributes: listsBodyAttrs
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
        'aa-lists-header-toggle': listsToggle,
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
        listsToggle: listsToggle,
        listsBody: listsBody,
        execToggle: execToggle,
        execBody: execBody
    };
}

function buildPartialDom(options) {
    var opts = options || {};
    var elements = {};

    if (opts.includeLists) {
        var listsToggle = makeElement('button', {
            id: 'aa-lists-header-toggle',
            attributes: { 'aria-expanded': 'true', 'aria-controls': 'aa-lists-body' }
        });
        var listsBody = makeElement('div', {
            id: 'aa-lists-body',
            classes: ['aa-lists-body'],
            attributes: { 'aria-hidden': 'false' }
        });
        elements['aa-lists-header-toggle'] = listsToggle;
        elements['aa-lists-body'] = listsBody;
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

    it('index.php conserva header persistente y body contraíble del organizador', () => {
        assert.match(indexSrc, /Organizador · Listas de tareas/);
        assert.match(indexSrc, /id="aa-lists-header"/);
        assert.match(indexSrc, /id="aa-lists-body"/);
        assert.match(indexSrc, /id="aa-lists-header-toggle"/);
    });

    it('CSS no contiene reglas de opacidad del ejecutor ni is-muted', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.doesNotMatch(css, /#aa-executive-proposal\.is-muted/);
        assert.doesNotMatch(css, /#aa-executive-proposal\s*\{[^}]*transition:\s*opacity/);
        assert.doesNotMatch(css, /#aa-lists-header-toggle\s*\{[^}]*transition:\s*opacity/);
        assert.doesNotMatch(css, /#aa-lists-header-toggle\[aria-expanded="false"\]\s*\{[^}]*opacity/);
    });

    // --- Organizer initial state ---
    it('organizador inicia expandido', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'true');
    });

    // --- Executive initial state ---
    it('ejecutor inicia colapsado', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
    });

    it('ejecutor tiene aria-hidden=true inicial', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.execBody.getAttribute('aria-hidden'), 'true');
    });

    it('ejecutor tiene inert inicial', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.execBody.getAttribute('inert'), '');
    });

    // --- Executive toggle behavior ---
    it('clic en toggle ejecutivo expande solo el ejecutor', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);

        assert.equal(dom.execBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.execBody.inert, false);
        // organizador no fue afectado
        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'true');
    });

    it('segundo clic en toggle ejecutivo vuelve a colapsarlo', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);
        clickToggle(dom.execToggle);

        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.execBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.execBody.inert, true);
    });

    // --- Organizer toggle behavior ---
    it('clic en toggle del organizador afecta solo al organizador', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.listsToggle);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'false');
        // ejecutor no fue afectado
        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
    });

    // --- Independence ---
    it('abrir el ejecutor no cierra el organizador', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'true');
    });

    it('cerrar el organizador no abre el ejecutor', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.listsToggle);

        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
    });

    it('ambas secciones pueden quedar abiertas', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.execBody.classList.contains('is-collapsed'), false);
    });

    it('ambas secciones pueden quedar cerradas', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.listsToggle);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
    });

    // --- Accessibility sync ---
    it('atributos accesibles permanecen sincronizados tras ciclos', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.execBody.inert, false);

        clickToggle(dom.execToggle);
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.execBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.execBody.inert, true);

        clickToggle(dom.listsToggle);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.listsBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.listsBody.inert, true);

        clickToggle(dom.listsToggle);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.listsBody.inert, false);
    });

    // --- Idempotent binding ---
    it('inicializar dos veces no duplica listeners', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        var loaded = loadModule(dom);

        var listsBefore = (dom.listsToggle.listeners['click'] || []).length;
        var execBefore = (dom.execToggle.listeners['click'] || []).length;

        loaded.exports.bind();
        loaded.exports.bind();

        assert.equal((dom.listsToggle.listeners['click'] || []).length, listsBefore);
        assert.equal((dom.execToggle.listeners['click'] || []).length, execBefore);
    });

    // --- Partial DOM resilience ---
    it('ausencia del toggle ejecutivo no rompe el toggle del organizador', () => {
        var partial = buildPartialDom({ includeLists: true, includeExec: false });
        var loaded = loadModule(partial);

        var listsToggle = partial.elements['aa-lists-header-toggle'];
        var listsBody = partial.elements['aa-lists-body'];

        clickToggle(listsToggle);
        assert.equal(listsBody.classList.contains('is-collapsed'), true);

        clickToggle(listsToggle);
        assert.equal(listsBody.classList.contains('is-collapsed'), false);
    });

    it('ausencia del toggle del organizador no rompe el toggle ejecutivo', () => {
        var partial = buildPartialDom({ includeLists: false, includeExec: true });
        var loaded = loadModule(partial);

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
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        // Only the toggle has listeners, so internal button clicks won't propagate
        assert.equal(dom.execBody.listeners['click'], undefined);
        assert.equal(dom.execBody.classList.contains('is-collapsed'), true);
    });

    // --- No scroll/wheel/focusin ---
    it('scroll, wheel y focusin siguen sin cambiar estados', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        assert.equal(dom.listsBody.listeners['wheel'], undefined);
        assert.equal(dom.listsBody.listeners['focusin'], undefined);
        assert.equal(dom.execBody.listeners['wheel'], undefined);
        assert.equal(dom.execBody.listeners['focusin'], undefined);
    });

    // --- No opacity / is-muted ---
    it('no se aplica is-muted a ninguna sección', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
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
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });

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

    it('Cycle B: toggle del organizador inicia con aria-expanded=true', () => {
        assert.match(indexSrc, /id="aa-lists-header-toggle"[^>]*aria-expanded="true"/);
    });

    it('Cycle B: aa-lists-body inicia sin is-collapsed', () => {
        assert.match(indexSrc, /id="aa-lists-body"\s+class="aa-lists-body"/);
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

    it('Cycle C: texto del toggle es Propuesta de ejecución', () => {
        assert.match(indexSrc, /Propuesta de ejecución/);
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

    it('Cycle C: chevron del organizador no fue modificado en tamaño/tono', () => {
        assert.match(indexSrc, /aa-lists-header-chevron/);
        assert.match(indexSrc, /aa-lists-header-chevron[^"]*w-4 h-4/);
        assert.match(indexSrc, /aa-lists-header-chevron[^"]*text-gray-500/);
    });

    it('Cycle C: existe chevron del ejecutor', () => {
        assert.match(indexSrc, /aa-executive-header-chevron/);
    });

    // --- Cycle D: chevron rotation & cleanup ---
    it('Cycle D: chevron del organizador tiene transition-transform', () => {
        assert.match(indexSrc, /aa-lists-header-chevron[^"]*transition-transform/);
    });

    it('Cycle D: chevron del ejecutor tiene transition-transform', () => {
        assert.match(indexSrc, /aa-executive-header-chevron[^"]*transition-transform/);
    });

    it('Cycle 2A: ambos headers usan chevron derecho como base', () => {
        var matches = indexSrc.match(/M9 5l7 7-7 7/g);
        assert.ok(matches && matches.length >= 2, 'Al menos dos chevrons con path hacia la derecha');
        assert.doesNotMatch(indexSrc, /aa-lists-header-chevron[\s\S]{0,200}M19 9l-7 7-7-7/);
        assert.doesNotMatch(indexSrc, /aa-executive-header-chevron[\s\S]{0,200}M19 9l-7 7-7-7/);
    });

    it('Cycle D: CSS tiene regla de rotación para aria-expanded=true en organizador', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');
        assert.match(css, /#aa-lists-header-toggle\[aria-expanded="true"\]\s*\.aa-lists-header-chevron/);
    });

    it('Cycle D: CSS tiene regla de rotación para aria-expanded=true en ejecutor', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');
        assert.match(css, /#aa-executive-header-toggle\[aria-expanded="true"\]\s*\.aa-executive-header-chevron/);
    });

    it('Cycle 2A: headers usan rotate(90deg) al expandir', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');
        var ruleMatch = css.match(/#aa-lists-header-toggle\[aria-expanded="true"\][\s\S]*?\{([^}]*)\}/);
        assert.ok(ruleMatch, 'Regla encontrada');
        assert.match(ruleMatch[1], /rotate\(90deg\)/);
        assert.doesNotMatch(ruleMatch[1], /rotate\(180deg\)/);
    });

    it('Cycle 2A: aria-expanded inicial de headers intacto', () => {
        assert.match(indexSrc, /id="aa-lists-header-toggle"[\s\S]*?aria-expanded="true"/);
        assert.match(indexSrc, /id="aa-executive-header-toggle"[\s\S]*?aria-expanded="false"/);
        assert.match(indexSrc, /id="aa-lists-header-toggle"[\s\S]*?aria-controls="aa-lists-body"/);
        assert.match(indexSrc, /id="aa-executive-header-toggle"[\s\S]*?aria-controls="aa-executive-body"/);
    });

    it('Cycle D: no existe regla que oculte chevrons con display:none', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');
        assert.doesNotMatch(css, /aa-lists-header-chevron[^}]*display:\s*none/);
        assert.doesNotMatch(css, /aa-executive-header-chevron[^}]*display:\s*none/);
    });

    it('Cycle D: no existe regla que cambie opacidad de chevrons', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');
        assert.doesNotMatch(css, /aa-lists-header-chevron[^}]*opacity/);
        assert.doesNotMatch(css, /aa-executive-header-chevron[^}]*opacity/);
    });

    it('Cycle D: rotación no depende de JavaScript adicional', () => {
        var src = fs.readFileSync(modulePath, 'utf8');
        assert.doesNotMatch(src, /rotate/);
        assert.doesNotMatch(src, /transform/);
        assert.doesNotMatch(src, /chevron/);
    });

    it('Cycle D: abrir una sección no modifica el chevron de la otra (test vía attrs)', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.execToggle);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'true');

        clickToggle(dom.listsToggle);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.execToggle.getAttribute('aria-expanded'), 'true');
    });

    it('Cycle D: módulo fue renombrado a section-toggles-module.js', () => {
        assert.match(indexSrc, /section-toggles-module\.js/);
        assert.doesNotMatch(indexSrc, /executive-lists-focus-module\.js/);
    });

    it('Cycle D: el toggle sigue sincronizando atributos accesibles tras rotación', () => {
        var dom = buildDom({ listsCollapsed: false, execCollapsed: true });
        loadModule(dom);

        clickToggle(dom.listsToggle);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.listsBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.listsBody.inert, true);

        clickToggle(dom.listsToggle);
        assert.equal(dom.listsToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.listsBody.inert, false);
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

    it('Cycle E: CSS oculta summary cuando aria-expanded=true', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');
        assert.match(css, /#aa-executive-header-toggle\[aria-expanded="true"\]\s*\.aa-executive-header-summary/);
        assert.match(css, /display:\s*none/);
    });

    it('Cycle E: existe #aa-executive-header-summary en index.php', () => {
        assert.match(indexSrc, /id="aa-executive-header-summary"/);
    });

    it('Cycle E: summary está entre label y chevron', () => {
        var labelPos = indexSrc.indexOf('aa-executive-header-label');
        var summaryPos = indexSrc.indexOf('aa-executive-header-summary');
        var chevronPos = indexSrc.indexOf('aa-executive-header-chevron');
        assert.ok(labelPos < summaryPos);
        assert.ok(summaryPos < chevronPos);
    });

    it('Cycle E: summary no tiene aria-hidden ni aria-live', () => {
        var match = indexSrc.match(/id="aa-executive-header-summary"[^>]*/);
        assert.ok(match);
        assert.doesNotMatch(match[0], /aria-hidden/);
        assert.doesNotMatch(match[0], /aria-live/);
    });
});
