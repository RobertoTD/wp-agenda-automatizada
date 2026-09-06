'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const switcherPath = path.join(
    __dirname,
    '../../includes/admin/ui/assets/js/canonical-family-switcher.js'
);
const sidebarJsPath = path.join(
    __dirname,
    '../../includes/admin/ui/assets/js/sidebar.js'
);
const switcherSrc = fs.readFileSync(switcherPath, 'utf8');
const sidebarSrc = fs.readFileSync(sidebarJsPath, 'utf8');

function createClassList(initial) {
    const set = new Set(initial || []);
    return {
        add(name) {
            set.add(name);
        },
        remove(name) {
            set.delete(name);
        },
        contains(name) {
            return set.has(name);
        }
    };
}

function createEl(overrides) {
    const el = {
        id: '',
        attributes: {},
        classList: createClassList([]),
        _listeners: {},
        children: [],
        focusCalls: 0,
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
        },
        removeAttribute(name) {
            delete this.attributes[name];
        },
        hasAttribute(name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name);
        },
        addEventListener(type, fn) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(fn);
        },
        focus() {
            this.focusCalls += 1;
            if (el._doc) {
                el._doc.activeElement = el;
            }
        },
        contains(node) {
            if (node === el) {
                return true;
            }
            return this.children.indexOf(node) !== -1
                || this.children.some(function (child) {
                    return child.contains && child.contains(node);
                });
        },
        querySelector(sel) {
            if (sel === 'a[aria-current="page"]') {
                return this._currentLink || null;
            }
            if (sel === 'a[href]') {
                return this._firstLink || this._currentLink || null;
            }
            return null;
        }
    };
    Object.assign(el, overrides || {});
    return el;
}

function bootSwitcher() {
    const currentLink = createEl({ id: 'link-archive', href: '/archive' });
    currentLink.setAttribute('aria-current', 'page');
    const firstLink = createEl({ id: 'link-finance', href: '/finance' });
    const panel = createEl({
        id: 'aa-family-switcher-panel',
        classList: createClassList(['hidden']),
        _currentLink: currentLink,
        _firstLink: firstLink
    });
    panel.setAttribute('hidden', '');
    const trigger = createEl({
        id: 'aa-page-title',
        classList: createClassList([])
    });
    trigger.setAttribute('data-aa-title-mode', 'family-switcher');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'aa-family-switcher-panel');
    const root = createEl({
        id: 'aa-family-switcher',
        children: [trigger, panel]
    });
    const outside = createEl({ id: 'outside' });

    const documentListeners = {};
    const documentMock = {
        readyState: 'complete',
        activeElement: null,
        getElementById(id) {
            if (id === 'aa-family-switcher') {
                return root;
            }
            if (id === 'aa-page-title') {
                return trigger;
            }
            if (id === 'aa-family-switcher-panel') {
                return panel;
            }
            return null;
        },
        addEventListener(type, fn, options) {
            documentListeners[type] = documentListeners[type] || [];
            documentListeners[type].push({ fn: fn, options: options });
        }
    };

    trigger._doc = documentMock;
    currentLink._doc = documentMock;
    firstLink._doc = documentMock;

    const context = {
        document: documentMock,
        module: { exports: {} },
        window: null
    };
    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(switcherSrc, context, { filename: switcherPath });

    return {
        api: context.module.exports,
        trigger: trigger,
        panel: panel,
        root: root,
        outside: outside,
        currentLink: currentLink,
        documentListeners: documentListeners,
        documentMock: documentMock
    };
}

function fire(listeners, type, event) {
    const list = listeners[type] || [];
    list.forEach(function (entry) {
        entry.fn(event);
    });
}

describe('canonical family switcher disclosure', () => {
    it('script es disclosure sin role menu', () => {
        assert.match(switcherSrc, /aa-family-switcher/);
        assert.match(switcherSrc, /Escape/);
        assert.doesNotMatch(switcherSrc, /role\s*=\s*['"]menu['"]/);
        assert.doesNotMatch(switcherSrc, /menuitem/);
        assert.doesNotMatch(switcherSrc, /aria-haspopup/);
    });

    it('abre con click y marca aria-expanded', () => {
        const env = bootSwitcher();
        assert.equal(typeof env.api.init, 'function');
        // Auto-init already ran (readyState complete); click via trigger listeners.
        const clickHandlers = env.trigger._listeners.click || [];
        assert.ok(clickHandlers.length >= 1, 'trigger tiene listener click');
        clickHandlers[0]({ preventDefault: function () {} });
        assert.equal(env.trigger.getAttribute('aria-expanded'), 'true');
        assert.equal(env.panel.hasAttribute('hidden'), false);
        assert.equal(env.panel.classList.contains('hidden'), false);
        assert.equal(env.api.isOpen(), true);
        assert.ok(env.documentMock.activeElement === env.currentLink, 'foco en enlace actual');
    });

    it('Escape cierra y restaura foco al trigger', () => {
        const env = bootSwitcher();
        const clickHandlers = env.trigger._listeners.click || [];
        clickHandlers[0]({ preventDefault: function () {} });
        fire(env.documentListeners, 'keydown', { key: 'Escape', stopPropagation: function () {} });
        assert.equal(env.trigger.getAttribute('aria-expanded'), 'false');
        assert.equal(env.api.isOpen(), false);
        assert.ok(env.documentMock.activeElement === env.trigger, 'foco vuelve al trigger');
        assert.ok(env.trigger.focusCalls >= 1, 'trigger.focus llamado');
    });

    it('click exterior cierra el disclosure', () => {
        const env = bootSwitcher();
        const clickHandlers = env.trigger._listeners.click || [];
        clickHandlers[0]({ preventDefault: function () {} });
        fire(env.documentListeners, 'click', { target: env.outside });
        assert.equal(env.api.isOpen(), false);
        assert.equal(env.trigger.getAttribute('aria-expanded'), 'false');
    });
});

describe('syncHeaderPageTitle respeta modos SSR', () => {
    it('sidebar.js guarda early-return para family-switcher y family-static', () => {
        assert.match(sidebarSrc, /data-aa-title-mode/);
        assert.match(sidebarSrc, /family-switcher/);
        assert.match(sidebarSrc, /family-static/);
        const guardPos = sidebarSrc.indexOf("titleMode === 'family-switcher'");
        const assignPos = sidebarSrc.indexOf('titleEl.textContent = text');
        assert.ok(guardPos !== -1 && assignPos !== -1 && guardPos < assignPos);
    });
});
